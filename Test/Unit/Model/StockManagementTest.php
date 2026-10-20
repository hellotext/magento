<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Test\Unit\Model;

use Hellotext\Tracking\Model\StockManagement;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Webapi\Rest\Response;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\Data\IsProductSalableResultInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use PHPUnit\Framework\TestCase;

class StockManagementTest extends TestCase
{
    private const INSTALLATION = '12345678-1234-4234-8234-123456789012';

    private StockManagement $subject;
    private Collection $collection;
    private AreProductsSalableInterface $salability;
    private StockResolverInterface $resolver;
    private StockRegistryInterface $legacyStock;
    private bool $inventoryEnabled = true;

    protected function setUp(): void
    {
        $this->collection = $this->createMock(Collection::class);
        foreach (['setStoreId', 'addStoreFilter', 'addWebsiteFilter', 'addIdFilter', 'addAttributeToSelect', 'addAttributeToFilter'] as $method) {
            $this->collection->method($method)->willReturnSelf();
        }
        $collections = $this->createMock(CollectionFactory::class);
        $collections->method('create')->willReturn($this->collection);
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnCallback(function (string $path, string $scope, int $storeId): string {
            self::assertSame(ScopeInterface::SCOPE_STORE, $scope);
            self::assertSame(4, $storeId);
            return str_ends_with($path, 'installation_id') ? self::INSTALLATION : 'business123';
        });
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(4);
        $store->method('getWebsiteId')->willReturn(2);
        $website = $this->createMock(Website::class);
        $website->method('getCode')->willReturn('second_website');
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);
        $stores->method('getWebsite')->with(2)->willReturn($website);
        $stock = $this->createMock(StockInterface::class);
        $stock->method('getStockId')->willReturn(7);
        $this->resolver = $this->createMock(StockResolverInterface::class);
        $this->resolver->method('execute')->with('website', 'second_website')->willReturn($stock);
        $this->salability = $this->createMock(AreProductsSalableInterface::class);
        $response = $this->createMock(Response::class);
        $response->expects(self::once())->method('setHeader')->with(
            'Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store', true
        );
        $modules = $this->createMock(ModuleManager::class);
        $modules->method('isEnabled')->with('Magento_InventorySalesApi')->willReturnCallback(fn (): bool => $this->inventoryEnabled);
        $objects = $this->createMock(ObjectManagerInterface::class);
        $objects->method('get')->willReturnMap([
            [StockResolverInterface::class, $this->resolver],
            [AreProductsSalableInterface::class, $this->salability],
        ]);
        $this->legacyStock = $this->createMock(StockRegistryInterface::class);
        $this->subject = new StockManagement($collections, $config, $stores, $response, $modules, $objects, $this->legacyStock);
    }

    public function testFallsBackToTheGlobalStockWhenInventoryIsDisabled(): void
    {
        $this->inventoryEnabled = false;
        $this->collection->method('getItems')->willReturn([
            $this->product(11, 'SIMPLE'), $this->product(12, 'GONE'), $this->product(13, 'SOLD-OUT')
        ]);
        $this->legacyStock->method('getProductStockStatusBySku')->willReturnCallback(static function (string $sku): int {
            if ($sku === 'GONE') {
                throw new NoSuchEntityException(__('Missing'));
            }
            return $sku === 'SIMPLE' ? 1 : 0;
        });
        $this->resolver->expects(self::never())->method('execute');
        $this->salability->expects(self::never())->method('execute');

        $result = $this->subject->getStock([11, 12, 13]);

        self::assertSame([true, false, false], array_map(static fn ($item) => $item->getSalable(), $result->getItems()));
    }

    public function testChecksSimpleProductsParentsAndExactChildrenTogetherInTheWebsiteStock(): void
    {
        $this->collection->expects(self::once())->method('setStoreId')->with(4);
        $this->collection->expects(self::once())->method('addStoreFilter')->with(4);
        $this->collection->expects(self::once())->method('addWebsiteFilter')->with([2]);
        $this->collection->expects(self::once())->method('addIdFilter')->with([13, 99, 12, 11]);
        $this->collection->expects(self::exactly(2))->method('addAttributeToFilter')->withConsecutive(
            ['status', Status::STATUS_ENABLED], ['type_id', ['in' => ['simple', 'configurable']]]
        );
        $this->collection->method('getItems')->willReturn([
            $this->product(11, 'SIMPLE'), $this->product(12, 'PARENT'), $this->product(13, 'CHILD')
        ]);
        $this->resolver->expects(self::once())->method('execute');
        $this->salability->expects(self::once())->method('execute')->with(['SIMPLE', 'PARENT', 'CHILD'], 7)->willReturn([
            $this->result('CHILD', false), $this->result('PARENT', true), $this->result('SIMPLE', true)
        ]);

        $result = $this->subject->getStock([13, 99, 12, 11, 13]);

        self::assertSame(1, $result->getSchemaVersion());
        self::assertSame(self::INSTALLATION, $result->getInstallationId());
        self::assertSame(4, $result->getStoreId());
        self::assertSame('business123', $result->getBusinessId());
        self::assertSame([13, 99, 12, 11], array_map(static fn ($item) => $item->getId(), $result->getItems()));
        self::assertSame([false, false, true, true], array_map(static fn ($item) => $item->getSalable(), $result->getItems()));
    }

    public function testMissingDisabledOrForeignProductsAreUnavailableWithoutAnInventoryRequest(): void
    {
        $this->collection->method('getItems')->willReturn([]);
        $this->salability->expects(self::never())->method('execute');
        $this->resolver->expects(self::never())->method('execute');

        $result = $this->subject->getStock([99]);

        self::assertSame(99, $result->getItems()[0]->getId());
        self::assertFalse($result->getItems()[0]->getSalable());
    }

    public function testAnEmptyRequestDoesNotQueryInventory(): void
    {
        $this->collection->expects(self::never())->method('getItems');
        $this->salability->expects(self::never())->method('execute');

        self::assertSame([], $this->subject->getStock([])->getItems());
    }

    /** @dataProvider invalidRequests */
    public function testRejectsInvalidBatchesBeforeLoadingProducts(array $ids): void
    {
        $this->collection->expects(self::never())->method('getItems');
        $this->salability->expects(self::never())->method('execute');
        $this->expectException(LocalizedException::class);

        $this->subject->getStock($ids);
    }

    public static function invalidRequests(): array
    {
        return [[range(1, 101)], [[0]], [[-1]]];
    }

    public function testAnIncompleteInventoryResponseDoesNotInventAStockout(): void
    {
        $this->collection->method('getItems')->willReturn([$this->product(11, 'SIMPLE')]);
        $this->salability->method('execute')->willReturn([]);
        $this->expectException(LocalizedException::class);

        $this->subject->getStock([11]);
    }

    public function testInventoryFailuresPropagate(): void
    {
        $this->collection->method('getItems')->willReturn([$this->product(11, 'SIMPLE')]);
        $this->salability->method('execute')->willThrowException(new \RuntimeException('Inventory unavailable'));
        $this->expectException(\RuntimeException::class);

        $this->subject->getStock([11]);
    }

    private function product(int $id, string $sku): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getSku')->willReturn($sku);
        return $product;
    }

    private function result(string $sku, bool $salable): IsProductSalableResultInterface
    {
        $result = $this->createMock(IsProductSalableResultInterface::class);
        $result->method('getSku')->willReturn($sku);
        $result->method('isSalable')->willReturn($salable);
        return $result;
    }
}
