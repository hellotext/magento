<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model;

use Hellotext\Tracking\Api\StockManagementInterface;
use Hellotext\Tracking\Api\Data\StockResultInterface;
use Hellotext\Tracking\Model\Data\StockItem;
use Hellotext\Tracking\Model\Data\StockResult;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Webapi\Rest\Response;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class StockManagement implements StockManagementInterface
{
    public function __construct(
        private CollectionFactory $collections,
        private ScopeConfigInterface $config,
        private StoreManagerInterface $stores,
        private StockResolverInterface $stockResolver,
        private AreProductsSalableInterface $salability,
        private Response $response
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
            $website = $this->stores->getWebsite((int) $store->getWebsiteId());
            $stock = $this->stockResolver->execute('website', (string) $website->getCode());
            foreach ($this->salability->execute(array_values($skus), (int) $stock->getStockId()) as $result) {
                $salable[$result->getSku()] = $result->isSalable();
            }
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
}
