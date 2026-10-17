<?php
require_once __DIR__ . '/../Model/Reference.php';
require_once __DIR__ . '/../Model/CartPayload.php';

use Hellotext\Tracking\Model\CartPayload;

function check(bool $condition): void {
    if (!$condition) { throw new RuntimeException('Cart payload assertion failed.'); }
}
function rejected(array $rows, string $currency = 'USD'): void {
    try { CartPayload::items($rows, 'magento-local', '1', $currency); }
    catch (InvalidArgumentException $error) { return; }
    throw new RuntimeException('Invalid cart was accepted.');
}
$simple = ['id' => '1', 'type' => 'simple', 'name' => 'Shirt', 'sku' => 'SHIRT', 'quantity' => '2.0000', 'price' => '24.9900'];
$items = CartPayload::items([$simple], 'magento-local', '1', 'USD');
check($items[0]['quantity'] === 2);
check($items[0]['product']['reference'] === 'magento:magento-local:product:1');
check($items[0]['price'] === ['amount' => '24.9900', 'currency' => 'USD']);
$configurable = array_replace($simple, ['type' => 'configurable', 'id' => '4', 'quantity' => 3, 'price' => 29.99, 'child' => array_replace($simple, ['id' => '2', 'sku' => 'RED'])]);
$items = CartPayload::items([$configurable], 'magento-local', '1', 'USD');
check($items[0]['quantity'] === 3 && $items[0]['product']['sku'] === 'RED');
check($items[0]['product']['reference'] === 'magento:magento-local:product:2');
check($items[0]['price']['amount'] === '29.99');
check(CartPayload::items([], 'magento-local', '1', 'USD') === []);
$many = array_map(static fn(int $id): array => array_replace($simple, ['id' => (string) $id, 'sku' => "SKU-$id"]), range(1, 15));
check(count(CartPayload::items($many, 'magento-local', '1', 'USD')) === 15);
foreach (['virtual', 'bundle', 'grouped', 'downloadable', 'unknown'] as $type) { rejected([array_replace($simple, ['type' => $type])]); }
foreach ([0, -1, '1.5', 'NaN', '2147483648', '1e3'] as $quantity) { rejected([array_replace($simple, ['quantity' => $quantity])]); }
foreach (['-1', 'NaN', '1e3', INF] as $price) { rejected([array_replace($simple, ['price' => $price])]); }
rejected([$simple, $simple]);
rejected([array_replace($simple, ['custom_options' => true])]);
rejected([array_replace($simple, ['type' => 'configurable'])]);
rejected([array_replace($simple, ['sku' => ''])]);
rejected([$simple], 'usd');
check(!str_contains(json_encode($items), 'customer'));
echo "Cart mapping checks passed.\n";
