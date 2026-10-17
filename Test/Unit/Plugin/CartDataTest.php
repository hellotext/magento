<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Test\Unit\Plugin;

use Hellotext\Tracking\Plugin\CartData;
use Magento\Checkout\CustomerData\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Cookie\Helper\Cookie as CookieHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class CartDataTest extends TestCase
{
    private CheckoutSession $checkout;
    private CustomerSession $customer;
    private CookieHelper $consent;
    private CartData $subject;

    protected function setUp(): void
    {
        $this->checkout = $this->createMock(CheckoutSession::class);
        $this->customer = $this->createMock(CustomerSession::class);
        $this->consent = $this->createMock(CookieHelper::class);
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnMap([
            ['hellotext/tracking/installation_id', 'store', '1', 'store-one'],
            ['hellotext/tracking/business_id', 'store', '1', 'business-one'],
            ['hellotext/tracking/api_root', 'store', '1', 'https://api.hellotext.com/v1'],
        ]);
        $config->method('isSetFlag')->willReturn(true);
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getCurrentCurrencyCode')->willReturn('USD');
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);
        $this->subject = new CartData($this->checkout, $this->customer, $config, $stores, $this->consent);
    }

    /** @dataProvider customers */
    public function testCartTrackingNeedsNoMagentoSessionForGuestsOrCustomers(int $customerId): void
    {
        $this->customer->method('isLoggedIn')->willReturn($customerId > 0);
        $this->customer->method('getCustomerId')->willReturn($customerId);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getStoreId', 'getAllVisibleItems'])
            ->getMock();
        $quote->setData(['customer_id' => $customerId, 'quote_currency_code' => 'USD']);
        $quote->method('getId')->willReturn(42);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getAllVisibleItems')->willReturn([]);
        $this->checkout->method('getQuote')->willReturn($quote);

        $result = $this->subject->afterGetSectionData($this->createMock(Cart::class), ['summary_count' => 0]);

        self::assertSame(0, $result['summary_count']);
        self::assertTrue($result['hellotext']['enabled']);
        self::assertTrue($result['hellotext']['cart_ready']);
        self::assertSame([], $result['hellotext']['items']);
        self::assertSame('magento:store-one:checkout:42', $result['hellotext']['checkout_reference']);
        self::assertSame($customerId > 0 ? ['customer_id' => (string) $customerId] : null, $result['hellotext']['identity']);
        self::assertArrayNotHasKey('session', $result['hellotext']);
        self::assertArrayNotHasKey('anonymous', $result['hellotext']);
    }

    public static function customers(): array
    {
        return ['guest' => [0], 'signed in' => [7]];
    }

    public function testCookieRestrictionsStillDisableTracking(): void
    {
        $this->consent->method('isUserNotAllowSaveCookie')->willReturn(true);
        $this->checkout->expects(self::never())->method('getQuote');

        $result = $this->subject->afterGetSectionData($this->createMock(Cart::class), []);

        self::assertSame(['enabled' => false], $result['hellotext']);
    }

    /** @dataProvider customers */
    public function testUnavailableCartKeepsTrackingAndCustomerIdentity(int $customerId): void
    {
        $this->customer->method('isLoggedIn')->willReturn($customerId > 0);
        $this->customer->method('getCustomerId')->willReturn($customerId);
        $this->checkout->method('getQuote')->willThrowException(new \RuntimeException('Cart unavailable'));

        $result = $this->subject->afterGetSectionData($this->createMock(Cart::class), ['summary_count' => 0]);

        self::assertSame(0, $result['summary_count']);
        self::assertTrue($result['hellotext']['enabled']);
        self::assertFalse($result['hellotext']['cart_ready']);
        self::assertSame([], $result['hellotext']['items']);
        self::assertNull($result['hellotext']['checkout_reference']);
        self::assertSame($customerId > 0 ? ['customer_id' => (string) $customerId] : null, $result['hellotext']['identity']);
    }
}
