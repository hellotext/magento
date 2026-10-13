<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model;

use Hellotext\Tracking\Api\ConfigurationManagementInterface;
use Hellotext\Tracking\Api\Data\StatusInterface;
use Hellotext\Tracking\Api\StatusManagementInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Exception\InputException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ConfigurationManagement implements ConfigurationManagementInterface
{
    private const BUSINESS_ID = '/^[A-Za-z0-9]{4,32}$/';

    public function __construct(
        private WriterInterface $writer,
        private ReinitableConfigInterface $config,
        private TypeListInterface $cache,
        private StoreManagerInterface $stores,
        private StatusManagementInterface $status
    ) {}

    public function save(
        string $installationId,
        string $businessId,
        bool $identityEnabled
    ): StatusInterface {
        try {
            $scope = WebsiteScope::current($this->stores);
        } catch (\InvalidArgumentException) {
            throw new InputException(__('Use a store view code in the request path.'));
        }
        try {
            Reference::build($installationId, 'product', 1);
        } catch (\InvalidArgumentException) {
            throw new InputException(__('The installation ID is invalid.'));
        }
        if ($businessId !== '' && preg_match(self::BUSINESS_ID, $businessId) !== 1) {
            throw new InputException(__('The business ID is invalid.'));
        }
        if ($businessId === '' && $identityEnabled) {
            throw new InputException(__('Delivery requires a business ID.'));
        }

        $values = [
            'hellotext/tracking/installation_id' => $installationId,
            'hellotext/tracking/business_id' => $businessId,
            'hellotext/tracking/identity_enabled' => $identityEnabled ? '1' : '0',
        ];
        foreach ($values as $path => $value) {
            $this->writer->save($path, $value, ScopeInterface::SCOPE_WEBSITES, $scope['website_id']);
        }
        foreach ($scope['store_ids'] as $storeId) {
            foreach (array_keys($values) as $path) {
                $this->writer->delete($path, ScopeInterface::SCOPE_STORES, $storeId);
            }
        }

        $this->config->reinit();
        // Storefront HTML embeds the business ID; stale full-page cache would keep the old one.
        $this->cache->cleanType('config');
        $this->cache->cleanType('full_page');

        return $this->status->getStatus();
    }
}
