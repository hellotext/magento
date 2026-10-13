<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model;

use Hellotext\Tracking\Api\CustomerManagementInterface;
use Hellotext\Tracking\Api\Data\CustomerPageInterface;
use Hellotext\Tracking\Model\Data\CustomerItem;
use Hellotext\Tracking\Model\Data\CustomerPage;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class CustomerManagement implements CustomerManagementInterface
{
    public function __construct(
        private CustomerCollectionFactory $customers,
        private SubscriberCollectionFactory $subscribers,
        private ScopeConfigInterface $config,
        private StoreManagerInterface $stores,
        private Response $response
    ) {}

    public function getCustomers(int $limit = 50, int $afterId = 0): CustomerPageInterface
    {
        $this->response->setHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store', true);
        if ($limit < 1 || $limit > 100 || $afterId < 0) {
            throw new LocalizedException(__('Customer cursor or limit is invalid.'));
        }

        try {
            $scope = WebsiteScope::current($this->stores);
        } catch (\InvalidArgumentException) {
            throw new LocalizedException(__('Hellotext customer configuration is invalid for this website.'));
        }
        $storeId = $scope['store_id'];
        $websiteId = $scope['website_id'];
        $installation = trim((string) $this->config->getValue('hellotext/tracking/installation_id', ScopeInterface::SCOPE_STORE, $storeId));
        $business = trim((string) $this->config->getValue('hellotext/tracking/business_id', ScopeInterface::SCOPE_STORE, $storeId));
        try {
            Reference::build($installation, 'product', 1);
        } catch (\InvalidArgumentException) {
            throw new LocalizedException(__('Hellotext customer configuration is invalid for this store.'));
        }
        if ($business === '' || $websiteId <= 0) {
            throw new LocalizedException(__('Hellotext customer configuration is invalid for this store.'));
        }

        $collection = $this->customers->create();
        $collection->addFieldToSelect(['entity_id', 'email', 'firstname', 'lastname', 'website_id'])
            ->addFieldToFilter('website_id', ['eq' => $websiteId])
            ->addFieldToFilter('entity_id', ['gt' => $afterId])
            ->setOrder('entity_id', 'ASC')->setPageSize($limit + 1)->setCurPage(1);
        $records = array_values($collection->getItems());
        $hasMore = count($records) > $limit;
        $records = array_slice($records, 0, $limit);

        $customerIds = array_map(static fn ($customer): int => (int) $customer->getId(), $records);
        $newsletterStatuses = [];
        if ($customerIds !== []) {
            $subscribers = $this->subscribers->create()
                ->addFieldToFilter('customer_id', ['in' => $customerIds])
                ->addFieldToFilter('store_id', ['in' => $scope['store_ids']]);
            foreach ($subscribers as $subscriber) {
                $status = match ((int) $subscriber->getSubscriberStatus()) {
                    Subscriber::STATUS_SUBSCRIBED => 'subscribed',
                    Subscriber::STATUS_UNSUBSCRIBED => 'unsubscribed',
                    default => 'unconfirmed',
                };
                $id = (int) $subscriber->getCustomerId();
                $priority = ['unconfirmed' => 0, 'subscribed' => 1, 'unsubscribed' => 2];
                if (!isset($newsletterStatuses[$id]) || $priority[$status] > $priority[$newsletterStatuses[$id]]) {
                    $newsletterStatuses[$id] = $status;
                }
            }
        }

        $items = [];
        foreach ($records as $customer) {
            $id = (int) $customer->getId();
            $items[] = new CustomerItem(
                $id,
                self::safeText($customer->getEmail(), 255),
                self::safeText($customer->getFirstname(), 255),
                self::safeText($customer->getLastname(), 255),
                $newsletterStatuses[$id] ?? 'unconfirmed'
            );
        }

        return new CustomerPage(
            $installation,
            $storeId,
            $websiteId,
            $business,
            $items,
            $hasMore,
            $records === [] ? $afterId : (int) end($records)->getId()
        );
    }

    private static function safeText(mixed $value, int $maxBytes): ?string
    {
        if (!is_string($value)) return null;
        $value = trim($value);
        if ($value === '' || strlen($value) > $maxBytes || !mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) {
            return null;
        }
        return $value;
    }
}
