<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model;

use Hellotext\Tracking\Api\StockManagementInterface;
use Hellotext\Tracking\Api\Data\StockResultInterface;
use Hellotext\Tracking\Model\Data\StockItem;
use Hellotext\Tracking\Model\Data\StockResult;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Webapi\Rest\Response;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class StockManagement implements StockManagementInterface
{
    // Magento Inventory (MSI) is optional: its services are resolved only when it is enabled,
    // so stores that removed or disabled it can still install and compile the module.
    public function __construct(
        private CollectionFactory $collections,
        private ScopeConfigInterface $config,
        private StoreManagerInterface $stores,
        private Response $response,
        private ModuleManager $modules,
        private ObjectManagerInterface $objects,
        private StockRegistryInterface $legacyStock
    ) {}

    public function getStock(array $productIds): StockResultInterface
    {
        $this->response->setHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store', true);
        if (count($productIds) > 100 || array_filter($productIds, static fn ($id): bool => !is_int($id) || $id < 1)) {
            throw new LocalizedException(__('Request at most 100 positive product IDs.'));
        }
        $productIds = array_values(array_unique($productIds));
        $store = $this->stores->getStore();
        $storeId = (int) $store->getId();
        $installation = trim((string) $this->config->getValue('hellotext/tracking/installation_id', ScopeInterface::SCOPE_STORE, $storeId));
        $business = trim((string) $this->config->getValue('hellotext/tracking/business_id', ScopeInterface::SCOPE_STORE, $storeId));
        try {
            Reference::build($installation, 'product', 1);
        } catch (\InvalidArgumentException) {
            throw new LocalizedException(__('Hellotext catalog configuration is invalid for this store.'));
        }
        if ($business === '') {
            throw new LocalizedException(__('Hellotext catalog configuration is invalid for this store.'));
        }

        $skus = [];
        if ($productIds !== []) {
            $products = $this->collections->create();
            $products->setStoreId($storeId)
                ->addStoreFilter($storeId)
                ->addWebsiteFilter([(int) $store->getWebsiteId()])
                ->addIdFilter($productIds)
                ->addAttributeToSelect(['sku', 'status'])
                ->addAttributeToFilter('status', Status::STATUS_ENABLED)
                ->addAttributeToFilter('type_id', ['in' => ['simple', 'configurable']]);
            foreach ($products->getItems() as $product) {
                // Hidden simple children must be checked by their own SKU.
                $skus[(int) $product->getId()] = (string) $product->getSku();
            }
        }

        $salable = [];
        if ($skus !== []) {
            $salable = $this->modules->isEnabled('Magento_InventorySalesApi')
                ? $this->inventorySalability(array_values($skus), (int) $store->getWebsiteId())
                : $this->legacySalability(array_values($skus));
            foreach ($skus as $sku) {
                if (!array_key_exists($sku, $salable)) {
                    throw new LocalizedException(__('Inventory did not return a result for every product.'));
                }
            }
        }

        // Missing, disabled and foreign-website products cannot be purchased.
        $items = array_map(
            static fn (int $id): StockItem => new StockItem($id, isset($skus[$id]) && $salable[$skus[$id]]),
            $productIds
        );
        return new StockResult($installation, $storeId, $business, $items);
    }

    /** @return array<string, bool> */
    private function inventorySalability(array $skus, int $websiteId): array
    {
        $website = $this->stores->getWebsite($websiteId);
        $stock = $this->objects->get(StockResolverInterface::class)->execute('website', (string) $website->getCode());
        $salable = [];
        foreach ($this->objects->get(AreProductsSalableInterface::class)->execute($skus, (int) $stock->getStockId()) as $result) {
            $salable[$result->getSku()] = $result->isSalable();
        }
        return $salable;
    }

    /**
     * Without MSI, Magento keeps one global stock whose status already accounts for quantity and backorders.
     *
     * @return array<string, bool>
     */
    private function legacySalability(array $skus): array
    {
        $salable = [];
        foreach ($skus as $sku) {
            try {
                $salable[$sku] = (int) $this->legacyStock->getProductStockStatusBySku($sku) === 1;
            } catch (NoSuchEntityException) {
                $salable[$sku] = false;
            }
        }
        return $salable;
    }
}
