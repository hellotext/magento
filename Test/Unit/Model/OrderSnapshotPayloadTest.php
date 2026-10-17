<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Test\Unit\Model;

use Hellotext\Tracking\Model\OrderSnapshotPayload;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;

class OrderSnapshotPayloadTest extends TestCase
{
    /** @dataProvider buyerNames */
    public function testBuyerNamesAreLimitedByUtf8Characters(?string $name, ?string $expected): void
    {
        $order = new DataObject([
            'store_id' => 1,
            'entity_id' => 42,
            'increment_id' => '000000042',
            'order_currency_code' => 'USD',
            'grand_total' => '25.0000',
            'customer_firstname' => $name,
            'customer_lastname' => $name,
            'all_visible_items' => [new DataObject([
                'product_type' => 'simple',
                'product_id' => 11,
                'name' => 'Shirt',
                'sku' => 'SHIRT',
                'qty_ordered' => '1.0000',
                'price' => '25.0000',
            ])],
        ]);

        $payload = OrderSnapshotPayload::fromOrder($order, 'store-one', null);

        self::assertSame($expected, $payload['customer_first_name']);
        self::assertSame($expected, $payload['customer_last_name']);
        if ($expected !== null) {
            self::assertTrue(mb_check_encoding($payload['customer_first_name'], 'UTF-8'));
            self::assertLessThanOrEqual(255, mb_strlen($payload['customer_first_name'], 'UTF-8'));
        }
    }

    public static function buyerNames(): array
    {
        return [
            'multibyte name below the character limit' => [str_repeat('é', 128), str_repeat('é', 128)],
            'four-byte character at the limit' => [str_repeat('a', 254) . '😀', str_repeat('a', 254) . '😀'],
            'multibyte name above the character limit' => [str_repeat('😀', 256), str_repeat('😀', 255)],
            'trimmed name' => ["  José \t", 'José'],
            'blank name' => [" \t\n", null],
            'missing name' => [null, null],
        ];
    }
}
