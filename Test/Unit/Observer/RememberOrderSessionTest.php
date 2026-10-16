<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Test\Unit\Observer;

use Hellotext\Tracking\Observer\RememberOrderSession;
use Magento\Cookie\Helper\Cookie as CookieHelper;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Stdlib\CookieManagerInterface;
use PHPUnit\Framework\TestCase;

class RememberOrderSessionTest extends TestCase
{
    /** @dataProvider sessions */
    public function testRecordsTheSdkCookieOnTheOrder(?string $session, ?string $expected): void
    {
        $cookies = $this->createMock(CookieManagerInterface::class);
        $cookies->expects(self::once())->method('getCookie')->with('hello_session')->willReturn($session);
        $consent = $this->createMock(CookieHelper::class);
        $consent->method('isUserNotAllowSaveCookie')->willReturn(false);
        $order = new DataObject();

        (new RememberOrderSession($cookies, $consent))->execute(new Observer(['event' => new Event(['order' => $order])]));

        self::assertSame($expected, $order->getData('hellotext_session'));
    }

    public static function sessions(): array
    {
        return [
            'tracked link' => ['AbC123xy', 'AbC123xy'],
            'anonymous UUID' => ['12345678-1234-4234-8234-123456789012', '12345678-1234-4234-8234-123456789012'],
            'missing cookie' => [null, null],
            'empty cookie' => ['', null],
            'beyond column size' => [str_repeat('a', 37), null],
        ];
    }

    public function testCookieRestrictionStillPreventsOrderAttribution(): void
    {
        $cookies = $this->createMock(CookieManagerInterface::class);
        $cookies->expects(self::never())->method('getCookie');
        $consent = $this->createMock(CookieHelper::class);
        $consent->method('isUserNotAllowSaveCookie')->willReturn(true);
        $order = new DataObject();

        (new RememberOrderSession($cookies, $consent))->execute(new Observer(['event' => new Event(['order' => $order])]));

        self::assertNull($order->getData('hellotext_session'));
    }

    public function testAttributionFailureDoesNotInterruptOrderPlacement(): void
    {
        $cookies = $this->createMock(CookieManagerInterface::class);
        $cookies->method('getCookie')->willThrowException(new \RuntimeException());
        $consent = $this->createMock(CookieHelper::class);
        $consent->method('isUserNotAllowSaveCookie')->willReturn(false);
        $order = new DataObject();

        (new RememberOrderSession($cookies, $consent))->execute(new Observer(['event' => new Event(['order' => $order])]));

        self::assertNull($order->getData('hellotext_session'));
    }
}
