<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Test\Unit\Model;

use Hellotext\Tracking\Model\OrderLifecycleTracker;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\DataObject;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use PHPUnit\Framework\TestCase;

class OrderLifecycleTrackerTest extends TestCase
{
    /** @dataProvider sessions */
    public function testLifecycleEventsReuseThePlacedSession(?string $session): void
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnMap([
            ['hellotext/tracking/installation_id', 'store', 1, 'store-one'],
            ['hellotext/tracking/business_id', 'store', 1, 'business-one'],
            ['hellotext/tracking/api_root', 'store', 1, 'https://api.hellotext.com/v1'],
        ]);
        $curls = $this->createMock(CurlFactory::class);
        $curl = $this->createMock(Curl::class);
        $curls->method('create')->willReturn($curl);
        $sent = [];
        $curl->expects(self::exactly(2))->method('post')->with(
            'https://api.hellotext.com/v1/track/events',
            self::callback(static function (string $body) use (&$sent): bool {
                $sent[] = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
                return true;
            })
        );
        $order = new DataObject(['store_id' => 1, 'entity_id' => 42, 'state' => 'processing', 'hellotext_session' => $session]);
        $tracker = new OrderLifecycleTracker($curls, $config, $this->createMock(State::class));

        $tracker->track($order);
        $order->setData('state', 'complete');
        $tracker->track($order);

        self::assertSame(['order.confirmed', 'order.shipped'], array_column($sent, 'action'));
        self::assertSame($sent[0]['session'], $sent[1]['session']);
        if ($session !== null) {
            self::assertSame($session, $sent[0]['session']);
        } else {
            self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $sent[0]['session']);
        }
    }

    public static function sessions(): array
    {
        return [['AbC123xy'], ['12345678-1234-4234-8234-123456789012'], [null]];
    }
}
