<?php
declare(strict_types=1);

require_once __DIR__ . '/../Model/Reference.php';
require_once __DIR__ . '/../Model/OrderPayload.php';

use Hellotext\Tracking\Model\OrderPayload;
use Hellotext\Tracking\Model\Reference;
function assertSameValue(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message ?: 'Expected values to match.');
    }
}

function assertInvalid(callable $callback, ?string $message = null): void
{
    try {
        $callback();
    } catch (\InvalidArgumentException $exception) {
        if ($message !== null && $exception->getMessage() !== $message) {
            throw new RuntimeException("Expected '{$message}', got '{$exception->getMessage()}'.");
        }
        return;
    }
    throw new RuntimeException('Expected InvalidArgumentException.');
}

function orderFixture(array $changes = []): array
{
    return array_replace_recursive([
        'entity_id' => 101,
        'increment_id' => '000000101',
        'quote_id' => 77,
        'store_id' => 2,
        'customer_email' => 'ada@example.test',
        'customer_firstname' => 'Ada',
        'customer_lastname' => 'Lovelace',
        'created_at' => '2026-09-22 10:30:00',
        'order_currency_code' => 'USD',
        'grand_total' => '25.00',
        'subtotal' => '20.00',
        'discount_amount' => '-2.00',
        'shipping_amount' => '3.00',
        'shipping_method' => 'flatrate_flatrate',
        'tax_amount' => '4.00',
        'is_virtual' => false,
        'payment' => ['method' => 'checkmo'],
        'items' => [[
            'item_id' => 1,
            'product_id' => 11,
            'name' => 'Simple tee',
            'sku' => 'TEE-1',
            'product_type' => 'simple',
            'qty_ordered' => '2',
            'price' => '10.00',
        ]],
    ], $changes);
}

$simple = OrderPayload::fromArray(orderFixture(), 'local_store');
assertSameValue('order.placed', $simple['event']['action']);
assertSameValue('2026-09-22T10:30:00Z', $simple['event']['tracked_at']);
assertSameValue('25.00', $simple['event']['amount']);
assertSameValue('magento:local_store:order:101', $simple['event']['object_parameters']['reference']);
assertSameValue(2, $simple['event']['object_parameters']['items'][0]['quantity']);
assertSameValue('10.00', $simple['event']['object_parameters']['items'][0]['price']['amount']);
assertSameValue('magento:local_store:product:11', $simple['event']['object_parameters']['items'][0]['product']['reference']);
assertSameValue(false, array_key_exists('subscribe', $simple['profile']));
assertSameValue('ada@example.test', OrderPayload::fromArray(orderFixture(['customer_email' => 'Ada@Example.TEST']), 'local_store')['profile']['email']);

$configurable = OrderPayload::fromArray(orderFixture(['items' => [
    ['item_id' => 1, 'product_id' => 20, 'name' => 'Parent', 'sku' => 'PARENT', 'product_type' => 'configurable', 'qty_ordered' => '2', 'price' => '12.50'],
    ['item_id' => 2, 'parent_item_id' => 1, 'product_id' => 21, 'name' => 'Red', 'sku' => 'PARENT-RED', 'product_type' => 'simple', 'qty_ordered' => '2', 'price' => '12.50'],
]]), 'local_store');
assertSameValue(1, count($configurable['event']['object_parameters']['items']));
assertSameValue('magento:local_store:product:21', $configurable['event']['object_parameters']['items'][0]['product']['reference']);
assertSameValue(2, $configurable['event']['object_parameters']['items'][0]['quantity']);
assertSameValue('12.50', $configurable['event']['object_parameters']['items'][0]['price']['amount']);

$decimalQuantity = OrderPayload::fromArray(orderFixture(['items' => [[
    'item_id' => 1, 'product_id' => 11, 'name' => 'Simple tee', 'sku' => 'TEE-1', 'product_type' => 'simple', 'qty_ordered' => '2.0000', 'price' => '10.00',
]]]), 'local_store');
assertSameValue(2, $decimalQuantity['event']['object_parameters']['items'][0]['quantity']);

$emptyParent = OrderPayload::fromArray(orderFixture(['quote_id' => null, 'items' => [[
    'item_id' => 1, 'parent_item_id' => '', 'product_id' => 11, 'name' => 'Simple tee', 'sku' => 'TEE-1', 'product_type' => 'simple', 'qty_ordered' => '1.0000', 'price' => '10.00',
]]]), 'local_store');
assertSameValue(null, $emptyParent['event']['object_parameters']['metadata']['magento']['quote_id']);
assertSameValue(1, count($emptyParent['event']['object_parameters']['items']));

assertSameValue('magento:shop_one:product:1', Reference::build('shop_one', 'product', 1));
assertSameValue('magento:shop_two:product:1', Reference::build('shop_two', 'product', 1));

$free = OrderPayload::fromArray(orderFixture(['grand_total' => '0.00', 'subtotal' => '0.00', 'shipping_amount' => '0.00', 'tax_amount' => '0.00', 'discount_amount' => '0.00']), 'local_store');
assertSameValue('0.00', $free['event']['object_parameters']['total']['amount']);
assertSameValue('deliver', $free['event']['object_parameters']['delivery']);

assertInvalid(fn() => Reference::build('Bad', 'product', 1));
assertInvalid(fn() => Reference::build('local_store', 'product', 0));
assertInvalid(fn() => OrderPayload::fromArray(orderFixture(['customer_email' => '']), 'local_store'));
assertInvalid(fn() => OrderPayload::fromArray(orderFixture(['entity_id' => 0]), 'local_store'));
assertInvalid(fn() => OrderPayload::fromArray(orderFixture(['grand_total' => '-1.00']), 'local_store'));
assertInvalid(fn() => OrderPayload::fromArray(orderFixture(['grand_total' => INF]), 'local_store'));
assertInvalid(fn() => OrderPayload::fromArray(orderFixture(['order_currency_code' => 'US']), 'local_store'));
assertInvalid(fn() => OrderPayload::fromArray(orderFixture(['items' => [['item_id' => 1, 'product_id' => 11, 'name' => 'Simple', 'sku' => 'S', 'product_type' => 'simple', 'qty_ordered' => '1.5', 'price' => '10.00']]]), 'local_store'));
assertInvalid(fn() => OrderPayload::fromArray(orderFixture(['items' => [['item_id' => 1, 'product_id' => 11, 'name' => 'Simple', 'sku' => 'S', 'product_type' => 'simple', 'qty_ordered' => ((string) PHP_INT_MAX) . '0', 'price' => '10.00']]]), 'local_store'));
assertInvalid(fn() => OrderPayload::fromArray(orderFixture(['items' => [['item_id' => 1, 'product_id' => 11, 'name' => 'Bundle', 'sku' => 'B', 'product_type' => 'bundle', 'qty_ordered' => '1', 'price' => '10.00']]]), 'local_store'));
assertInvalid(fn() => OrderPayload::fromArray(orderFixture(['is_virtual' => true]), 'local_store'), 'Virtual orders are unsupported until a fulfillment mapping is agreed.');
assertInvalid(fn() => OrderPayload::fromArray(orderFixture(['shipping_method' => 'pickup_in_store']), 'local_store'), 'Only the flatrate_flatrate shipping method is supported by this development order adapter.');
assertInvalid(fn() => OrderPayload::fromArray(orderFixture(['shipping_method' => 'freeshipping_freeshipping']), 'local_store'), 'Only the flatrate_flatrate shipping method is supported by this development order adapter.');
assertInvalid(fn() => OrderPayload::fromArray(orderFixture(['shipping_method' => '']), 'local_store'), 'Only the flatrate_flatrate shipping method is supported by this development order adapter.');
assertInvalid(fn() => OrderPayload::fromArray(orderFixture(['items' => [
    ['item_id' => 1, 'product_id' => 11, 'name' => 'Physical', 'sku' => 'PHYSICAL', 'product_type' => 'simple', 'qty_ordered' => '1', 'price' => '10.00'],
    ['item_id' => 2, 'product_id' => 12, 'name' => 'Virtual', 'sku' => 'VIRTUAL', 'product_type' => 'virtual', 'qty_ordered' => '1', 'price' => '10.00'],
]]), 'local_store'), 'Virtual order items are unsupported until a fulfillment mapping is agreed.');
assertInvalid(fn() => OrderPayload::fromArray(orderFixture(['created_at' => '2026-02-30 10:30:00']), 'local_store'));
assertInvalid(fn() => OrderPayload::fromArray(orderFixture(['items' => [['item_id' => 2, 'parent_item_id' => 1, 'product_id' => 11, 'name' => 'Orphan', 'sku' => 'O', 'product_type' => 'simple', 'qty_ordered' => '1.0000', 'price' => '10.00']]]), 'local_store'));
assertInvalid(fn() => OrderPayload::fromArray(orderFixture(['items' => [
    ['item_id' => 1, 'product_id' => 20, 'name' => 'Parent', 'sku' => 'PARENT', 'product_type' => 'configurable', 'qty_ordered' => '1', 'price' => '10.00'],
    ['item_id' => 2, 'parent_item_id' => 1, 'product_id' => 21, 'name' => 'Red', 'sku' => 'PARENT-RED', 'product_type' => 'simple', 'qty_ordered' => '1', 'price' => '10.00'],
    ['item_id' => 3, 'parent_item_id' => 1, 'product_id' => 22, 'name' => 'Blue', 'sku' => 'PARENT-BLUE', 'product_type' => 'simple', 'qty_ordered' => '1', 'price' => '10.00'],
]]), 'local_store'));
assertInvalid(fn() => OrderPayload::fromArray(orderFixture(['items' => [
    ['item_id' => 1, 'product_id' => 11, 'name' => 'One', 'sku' => 'ONE', 'product_type' => 'simple', 'qty_ordered' => '1', 'price' => '10.00'],
    ['item_id' => 2, 'product_id' => 11, 'name' => 'One', 'sku' => 'ONE', 'product_type' => 'simple', 'qty_ordered' => '1', 'price' => '10.00'],
]]), 'local_store'), 'Multiple order lines for the same canonical product are unsupported because Hellotext merges them.');

echo "order_payload.test.php: ok\n";
