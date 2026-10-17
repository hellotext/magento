<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Plugin;

use Hellotext\Tracking\Model\CartPayload;
use Hellotext\Tracking\Model\Configuration;
use Hellotext\Tracking\Model\Reference;
use Magento\Checkout\CustomerData\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Cookie\Helper\Cookie as CookieHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class CartData
{
    public function __construct(
        private CheckoutSession $checkout,
        private CustomerSession $customer,
        private ScopeConfigInterface $config,
        private StoreManagerInterface $stores,
        private CookieHelper $cookies
    ) {}

    public function afterGetSectionData(Cart $subject, array $result): array
    {
        $result['hellotext'] = ['enabled' => false];
        try {
            // Magento's Cookie Restriction Mode: no tracking until the visitor allows cookies on this website.
            if ($this->cookies->isUserNotAllowSaveCookie()) {
                return $result;
            }
            // Signed-in shoppers keep being tracked; the storefront identifies them by this ID only.
            $customerId = $this->customer->isLoggedIn() ? (int) $this->customer->getCustomerId() : 0;
            $store = $this->stores->getStore();
            $storeId = (string) $store->getId();
            $installation = (string) $this->config->getValue('hellotext/tracking/installation_id', ScopeInterface::SCOPE_STORE, $storeId);
            $business = trim((string) $this->config->getValue('hellotext/tracking/business_id', ScopeInterface::SCOPE_STORE, $storeId));
            $api = trim((string) $this->config->getValue('hellotext/tracking/api_root', ScopeInterface::SCOPE_STORE, $storeId));
            if ($business === '' || !Configuration::isSafeApiRoot($api)) {
                return $result;
            }
            $currency = (string) $store->getCurrentCurrencyCode();
            $result['hellotext'] = [
                'enabled' => true,
                'context' => ['businessId' => $business, 'apiRoot' => $api, 'installationId' => $installation, 'storeId' => $storeId, 'currency' => $currency],
                'items' => [],
                'cart_ready' => false,
                'checkout_reference' => null,
                'identity' => $customerId > 0 && $this->config->isSetFlag('hellotext/tracking/identity_enabled', ScopeInterface::SCOPE_STORE, $storeId)
                    ? ['customer_id' => (string) $customerId]
                    : null,
            ];
            $quote = $this->checkout->getQuote();
            // A quote of another customer (or store) must never be reported for this visitor.
            if ((string) $quote->getStoreId() !== $storeId
                || ($quote->getCustomerId() && (int) $quote->getCustomerId() !== $customerId)) {
                return $result;
            }
            $currency = (string) ($quote->getQuoteCurrencyCode() ?: $currency);
            $rows = [];
            foreach ($quote->getAllVisibleItems() as $item) {
                $row = $this->product($item);
                $row['quantity'] = $item->getQty();
                $row['price'] = $item->getCalculationPrice();
                $request = $item->getBuyRequest()->getData('super_product_config');
                $row['custom_options'] = (bool) $item->getOptionByCode('option_ids')
                    || (bool) $item->getOptionByCode('additional_options')
                    || (is_array($request) && ($request['product_type'] ?? null) !== 'configurable');
                if ($row['type'] === 'configurable') {
                    $children = $item->getChildren();
                    if (count($children) !== 1) {
                        throw new \InvalidArgumentException('Ambiguous configurable cart item.');
                    }
                    $row['child'] = $this->product(reset($children));
                }
                $rows[] = $row;
            }
            $items = CartPayload::items($rows, $installation, $storeId, $currency);
            $reference = $quote->getId()
                ? Reference::build($installation, 'checkout', (int) $quote->getId())
                : null;
            $result['hellotext']['items'] = $items;
            $result['hellotext']['cart_ready'] = true;
            $result['hellotext']['checkout_reference'] = $reference;
        } catch (\Throwable $error) {
            // Keep tracking enabled when only the cart could not be serialized.
        }
        return $result;
    }

    private function product($item): array
    {
        return [
            'type' => $item->getProductType(),
            'id' => (string) $item->getProductId(),
            'name' => (string) $item->getName(),
            'sku' => (string) $item->getSku(),
        ];
    }
}
