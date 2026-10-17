<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model;

use Hellotext\Tracking\Api\Data\StatusInterface;
use Hellotext\Tracking\Api\StatusManagementInterface;
use Hellotext\Tracking\Model\Data\Status;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class StatusManagement implements StatusManagementInterface
{
    public function __construct(
        private ScopeConfigInterface $config,
        private StoreManagerInterface $stores,
        private VersionInformation $versions,
        private Response $response
    ) {}

    public function getStatus(): StatusInterface
    {
        $this->response->setHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store', true);
        try {
            $scope = WebsiteScope::current($this->stores);
        } catch (\InvalidArgumentException) {
            return new Status('', 0, 0, [], '', false, $this->versions->moduleVersion(), VersionInformation::SDK_VERSION,
                $this->versions->magentoVersion(), $this->versions->magentoEdition(), $this->versions->phpVersion());
        }
        $storeId = $scope['store_id'];
        $installation = trim((string) $this->config->getValue('hellotext/tracking/installation_id', ScopeInterface::SCOPE_STORE, $storeId));
        $business = trim((string) $this->config->getValue(
            'hellotext/tracking/business_id',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $apiRoot = trim((string) $this->config->getValue(
            'hellotext/tracking/api_root',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        return new Status(
            $installation,
            $storeId,
            $scope['website_id'],
            array_values(array_map(static fn ($store): string => (string) $store->getCode(), array_filter(
                $this->stores->getStores(), static fn ($store): bool => (int) $store->getWebsiteId() === $scope['website_id']
            ))),
            $business,
            $this->validConfiguration($installation, $storeId, $business, $apiRoot),
            $this->versions->moduleVersion(),
            VersionInformation::SDK_VERSION,
            $this->versions->magentoVersion(),
            $this->versions->magentoEdition(),
            $this->versions->phpVersion()
        );
    }

    private function validConfiguration(string $installation, int $storeId, string $business, string $apiRoot): bool
    {
        if ($business === '' || !Configuration::isSafeApiRoot($apiRoot)) {
            return false;
        }
        try {
            Reference::build($installation, 'product', 1);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
