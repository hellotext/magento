<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model;

use Hellotext\Tracking\Api\CatalogManagementInterface;
use Hellotext\Tracking\Api\Data\CatalogPageInterface;
use Hellotext\Tracking\Model\Data\CatalogItem;
use Hellotext\Tracking\Model\Data\CatalogPage;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class CatalogManagement implements CatalogManagementInterface
{
    public function __construct(
        private CollectionFactory $collections,
        private ResourceConnection $resource,
        private ScopeConfigInterface $config,
        private StoreManagerInterface $stores,
        private Response $response
    ) {}

    public function getProducts(int $limit = 50, int $afterId = 0): CatalogPageInterface
    {
        $this->response->setHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store', true);
        if ($limit < 1 || $limit > 100 || $afterId < 0) {
            throw new LocalizedException(__('Catalog cursor or limit is invalid.'));
        }

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

        $collection = $this->eligibleCollection($storeId, (int) $store->getWebsiteId());
        $collection->addAttributeToFilter('entity_id', ['gt' => $afterId]);
        $collection->addAttributeToFilter('type_id', ['in' => ['simple', 'configurable']]);
        $collection->setOrder('entity_id', 'ASC')->setPageSize($limit + 1)->setCurPage(1);
        $products = array_values($collection->getItems());
        $hasMore = count($products) > $limit;
        $products = array_slice($products, 0, $limit);
        $simpleIds = [];
        foreach ($products as $product) {
            if ((string) $product->getTypeId() === 'simple') {
                $simpleIds[] = (int) $product->getId();
            }
        }
        $parentIds = $this->eligibleParentIds($simpleIds, $storeId, (int) $store->getWebsiteId());
        $items = [];
        foreach ($products as $product) {
            $id = (int) $product->getId();
            $parentId = $parentIds[$id] ?? null;
            if ((int) $product->getVisibility() === Visibility::VISIBILITY_NOT_VISIBLE && $parentId === null) {
                continue;
            }
            $name = mb_substr(trim((string) $product->getName()), 0, 255);
            if ($name === '') continue;
            $items[] = new CatalogItem(
                $id,
                Reference::build($installation, 'product', $id),
                $parentId === null ? null : Reference::build($installation, 'product', $parentId),
                (string) $product->getTypeId(),
                mb_substr((string) $product->getSku(), 0, 255),
                $name,
                self::safeImagePath((string) $product->getImage())
            );
        }

        return new CatalogPage(
            $installation,
            $storeId,
            $business,
            $items,
            $hasMore,
            $products === [] ? $afterId : (int) end($products)->getId()
        );
    }

    private function eligibleCollection(int $storeId, int $websiteId): \Magento\Catalog\Model\ResourceModel\Product\Collection
    {
        $collection = $this->collections->create();
        $collection->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addWebsiteFilter([$websiteId])
            ->addAttributeToSelect(['name', 'image', 'status', 'visibility', 'sku'])
            ->addAttributeToFilter('status', Status::STATUS_ENABLED);
        return $collection;
    }

    /** @param int[] $childIds @return array<int, int> */
    private function eligibleParentIds(array $childIds, int $storeId, int $websiteId): array
    {
        if ($childIds === []) return [];

        $connection = $this->resource->getConnection();
        $links = $connection->fetchAll($connection->select()
            ->from($this->resource->getTableName('catalog_product_super_link'), ['product_id', 'parent_id'])
            ->where('product_id IN (?)', $childIds)
            ->order('parent_id ASC'));
        if ($links === []) return [];

        $parents = $this->eligibleCollection($storeId, $websiteId);
        $parents->addIdFilter(array_unique(array_map(static fn (array $link): int => (int) $link['parent_id'], $links)))
            ->addAttributeToFilter('type_id', 'configurable')
            ->addAttributeToFilter('visibility', ['neq' => Visibility::VISIBILITY_NOT_VISIBLE]);
        $allowed = array_fill_keys(array_map('intval', array_keys($parents->getItems())), true);
        $result = [];
        foreach ($links as $link) {
            $parentId = (int) $link['parent_id'];
            $childId = (int) $link['product_id'];
            if (isset($allowed[$parentId]) && !isset($result[$childId])) $result[$childId] = $parentId;
        }
        return $result;
    }

    private static function safeImagePath(string $path): ?string
    {
        if ($path === '' || $path === 'no_selection' || strlen($path) > 512 ||
            !str_starts_with($path, '/') || str_starts_with($path, '//') ||
            preg_match('#(?:^|/)\.\.?(/|$)#', $path) === 1 ||
            preg_match('#^/[a-zA-Z0-9_./-]+$#', $path) !== 1) {
            return null;
        }
        return $path;
    }
}
