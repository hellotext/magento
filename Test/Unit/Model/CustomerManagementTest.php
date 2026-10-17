<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Test\Unit\Model;

use Hellotext\Tracking\Model\CustomerManagement;
use Magento\Customer\Model\ResourceModel\Customer\Collection as CustomerCollection;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Newsletter\Model\ResourceModel\Subscriber\Collection as SubscriberCollection;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class CustomerManagementTest extends TestCase
{
    /** @dataProvider customerNames */
    public function testImportedNamesUseACharacterLimit(?string $name, ?string $expected): void
    {
        $collection = $this->createMock(CustomerCollection::class);
        foreach (['addFieldToSelect', 'addFieldToFilter', 'setOrder', 'setPageSize', 'setCurPage'] as $method) {
            $collection->method($method)->willReturnSelf();
        }
        $collection->method('getItems')->willReturn([new DataObject([
            'id' => 42,
            'email' => 'buyer@example.com',
            'firstname' => $name,
            'lastname' => $name,
        ])]);
        $customers = $this->createMock(CustomerCollectionFactory::class);
        $customers->method('create')->willReturn($collection);
        $subscriberCollection = $this->createMock(SubscriberCollection::class);
        $subscriberCollection->method('addFieldToFilter')->willReturnSelf();
        $subscriberCollection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $subscribers = $this->createMock(SubscriberCollectionFactory::class);
        $subscribers->method('create')->willReturn($subscriberCollection);
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnMap([
            ['hellotext/tracking/installation_id', 'store', 1, 'store-one'],
            ['hellotext/tracking/business_id', 'store', 1, 'business-one'],
        ]);
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getWebsiteId')->willReturn(1);
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);
        $stores->method('getStores')->willReturn([$store]);
        $subject = new CustomerManagement($customers, $subscribers, $config, $stores, $this->createMock(Response::class));

        $page = $subject->getCustomers();
        $customer = $page->getItems()[0];

        self::assertSame($expected, $customer->getFirstName());
        self::assertSame($expected, $customer->getLastName());
        self::assertSame('buyer@example.com', $customer->getEmail());
        self::assertSame(42, $page->getNextCursor());
    }

    public static function customerNames(): array
    {
        return [
            'localized name above 255 bytes' => [str_repeat('林', 100), str_repeat('林', 100)],
            'localized name at the character limit' => [str_repeat('林', 255), str_repeat('林', 255)],
            'four-byte characters at the limit' => [str_repeat('😀', 255), str_repeat('😀', 255)],
            'name above the character limit' => [str_repeat('林', 256), null],
            'trimmed name' => ["  José \t", 'José'],
            'blank name' => [" \t\n", null],
            'missing name' => [null, null],
            'invalid UTF-8' => ["Jos\xC3", null],
            'control character' => ["Jo\x01sé", null],
        ];
    }
}
