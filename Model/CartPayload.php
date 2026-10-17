<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model;

use InvalidArgumentException;

class CartPayload
{
    public static function items(array $rows, string $installation, string $storeId, string $currency): array
    {
        Reference::build($installation, 'product', 1);
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Unsupported cart currency.');
        }

        $result = [];
        $references = [];
        foreach ($rows as $row) {
            if (!in_array($row['type'] ?? null, ['simple', 'configurable'], true) || !empty($row['custom_options'])) {
                throw new InvalidArgumentException('Unsupported cart product.');
            }
            $product = $row['type'] === 'configurable' ? ($row['child'] ?? []) : $row;
            if (($product['type'] ?? null) !== 'simple') {
                throw new InvalidArgumentException('A configurable cart item requires one simple child.');
            }
            $reference = Reference::build($installation, 'product', $product['id'] ?? '');
            if (isset($references[$reference])) {
                throw new InvalidArgumentException('Repeated canonical products cannot preserve line semantics.');
            }
            $references[$reference] = true;
            $quantity = (string) ($row['quantity'] ?? '');
            $price = (string) ($row['price'] ?? '');
            if (!preg_match('/^[1-9]\d*(?:\.0+)?$/', $quantity) || (float) $quantity > 2147483647) {
                throw new InvalidArgumentException('Cart quantity must be a positive integer.');
            }
            if (!preg_match('/^\d+(?:\.\d+)?$/', $price) || !is_finite((float) $price)) {
                throw new InvalidArgumentException('Cart unit price must be a finite nonnegative decimal.');
            }
            foreach (['name', 'sku'] as $field) {
                if (!is_string($product[$field] ?? null) || trim($product[$field]) === '') {
                    throw new InvalidArgumentException('Missing canonical product data.');
                }
            }
            $result[] = [
                'quantity' => (int) $quantity,
                'price' => ['amount' => $price, 'currency' => $currency],
                'product' => [
                    'name' => $product['name'],
                    'reference' => $reference,
                    'sku' => $product['sku'],
                    'source' => 'magento',
                ],
            ];
        }
        usort($result, static fn(array $a, array $b): int => strcmp($a['product']['reference'], $b['product']['reference']));
        return $result;
    }
}
