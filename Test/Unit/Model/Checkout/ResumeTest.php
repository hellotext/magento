<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Test\Unit\Model\Checkout;

use Hellotext\Tracking\Model\Checkout\Resume;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteMutexInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class ResumeTest extends TestCase
{
    private const MASK = 'abcdef0123456789abcdef0123456789';

    private $maskedIds;
    private $quotes;
    private $carts;
    private $mutex;
    private $checkout;
    private $customer;
    private $stores;
    private Resume $subject;

    protected function setUp(): void
    {
        $this->maskedIds = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);
        $this->quotes = $this->createMock(CartRepositoryInterface::class);
        $this->carts = $this->createMock(CartManagementInterface::class);
        $this->mutex = $this->createMock(QuoteMutexInterface::class);
        $this->customer = $this->createMock(CustomerSession::class);
        $this->stores = $this->createMock(StoreManagerInterface::class);
        $this->checkout = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['replaceQuote', 'resetCheckout', 'clearHelperData', 'getQuote'])
            ->addMethods(['setLastSuccessQuoteId', 'setCartWasUpdated'])
            ->getMock();
        $this->checkout->method('replaceQuote')->willReturnSelf();
        $this->checkout->method('resetCheckout')->willReturnSelf();
        $this->mutex->method('execute')->willReturnCallback(static function ($ids, $callback) {
            return $callback();
        });

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $this->stores->method('getStore')->willReturn($store);

        $this->subject = new Resume(
            $this->maskedIds,
            $this->quotes,
            $this->carts,
            $this->mutex,
            $this->checkout,
            $this->customer,
            $this->stores
        );
    }

    public function testGuestUsesThePreparedCartWithoutMergingTheSessionBasket(): void
    {
        $quote = $this->quote();
        $this->maskedIds->expects(self::once())->method('execute')->with(self::MASK)->willReturn(42);
        $this->quotes->expects(self::once())->method('getActive')->with(42)->willReturn($quote);
        $this->mutex->expects(self::once())->method('execute')->with([self::MASK], self::isType('callable'));
        $this->quotes->expects(self::once())->method('save')->with($quote);
        $this->carts->expects(self::never())->method('assignCustomer');
        $this->checkout->expects(self::never())->method('getQuote');
        $this->checkout->expects(self::once())->method('replaceQuote')->with($quote);
        $this->checkout->expects(self::once())->method('resetCheckout');
        $this->checkout->expects(self::once())->method('clearHelperData');
        $this->checkout->expects(self::once())->method('setLastSuccessQuoteId')->with(null);
        $this->checkout->expects(self::once())->method('setCartWasUpdated')->with(true);

        $this->subject->execute(self::MASK);
    }

    public function testSignedInCustomerUsesNativeAssignmentAndItsMergedCart(): void
    {
        $guest = $this->quote();
        $merged = $this->quote(customerId: 7);
        $this->maskedIds->method('execute')->willReturn(42);
        $this->quotes->expects(self::exactly(2))->method('getActive')->with(42)->willReturnOnConsecutiveCalls($guest, $merged);
        $this->customer->method('isLoggedIn')->willReturn(true);
        $this->customer->method('getCustomerId')->willReturn(7);
        $this->carts->expects(self::once())->method('assignCustomer')->with(42, 7, 1)->willReturn(true);
        $this->quotes->expects(self::once())->method('save')->with($merged);
        $this->checkout->expects(self::once())->method('replaceQuote')->with($merged);

        $this->subject->execute(self::MASK);
    }

    /** @dataProvider invalidIds */
    public function testInvalidIdNeverReadsOrChangesACart(string $id): void
    {
        $this->mutex->expects(self::never())->method('execute');
        $this->maskedIds->expects(self::never())->method('execute');
        $this->checkout->expects(self::never())->method('replaceQuote');
        $this->expectException(NoSuchEntityException::class);

        $this->subject->execute($id);
    }

    public static function invalidIds(): array
    {
        return [[''], ['42'], [str_repeat('a', 33)], [str_repeat('a', 31) . '/']];
    }

    /** @dataProvider unavailableCarts */
    public function testUnavailableCartPreservesTheCurrentSession(int $storeId, int $customerId, bool $hasItems): void
    {
        $this->maskedIds->method('execute')->willReturn(42);
        $this->quotes->method('getActive')->willReturn($this->quote($storeId, $customerId, $hasItems));
        $this->quotes->expects(self::never())->method('save');
        $this->carts->expects(self::never())->method('assignCustomer');
        $this->checkout->expects(self::never())->method('replaceQuote');
        $this->expectException(NoSuchEntityException::class);

        $this->subject->execute(self::MASK);
    }

    public static function unavailableCarts(): array
    {
        return ['another store' => [2, 0, true], 'customer owned' => [1, 7, true], 'empty' => [1, 0, false]];
    }

    public function testUnknownIdPreservesTheCurrentSession(): void
    {
        $this->maskedIds->method('execute')->willThrowException(new NoSuchEntityException());
        $this->quotes->expects(self::never())->method('getActive');
        $this->checkout->expects(self::never())->method('replaceQuote');
        $this->expectException(NoSuchEntityException::class);

        $this->subject->execute(self::MASK);
    }

    public function testInactiveCartPreservesTheCurrentSession(): void
    {
        $this->maskedIds->method('execute')->willReturn(42);
        $this->quotes->method('getActive')->willThrowException(new NoSuchEntityException());
        $this->carts->expects(self::never())->method('assignCustomer');
        $this->checkout->expects(self::never())->method('replaceQuote');
        $this->expectException(NoSuchEntityException::class);

        $this->subject->execute(self::MASK);
    }

    public function testFailedSaveDoesNotSwitchTheBrowserSession(): void
    {
        $this->maskedIds->method('execute')->willReturn(42);
        $this->quotes->method('getActive')->willReturn($this->quote());
        $this->quotes->method('save')->willThrowException(new \RuntimeException('Write failed'));
        $this->checkout->expects(self::never())->method('replaceQuote');
        $this->expectException(\RuntimeException::class);

        $this->subject->execute(self::MASK);
    }

    public function testFailedCustomerAssignmentDoesNotSwitchTheBrowserSession(): void
    {
        $this->maskedIds->method('execute')->willReturn(42);
        $this->quotes->method('getActive')->willReturn($this->quote());
        $this->customer->method('isLoggedIn')->willReturn(true);
        $this->customer->method('getCustomerId')->willReturn(7);
        $this->carts->method('assignCustomer')->willThrowException(new NoSuchEntityException());
        $this->quotes->expects(self::never())->method('save');
        $this->checkout->expects(self::never())->method('replaceQuote');
        $this->expectException(NoSuchEntityException::class);

        $this->subject->execute(self::MASK);
    }

    private function quote(int $storeId = 1, int $customerId = 0, bool $hasItems = true): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStoreId', 'hasItems', 'collectTotals'])
            ->addMethods(['getCustomerId', 'setTotalsCollectedFlag'])
            ->getMock();
        $quote->method('getStoreId')->willReturn($storeId);
        $quote->method('getCustomerId')->willReturn($customerId);
        $quote->method('hasItems')->willReturn($hasItems);
        $quote->method('collectTotals')->willReturnSelf();

        return $quote;
    }
}
