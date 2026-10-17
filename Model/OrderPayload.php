<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

class OrderPayload
{
    public static function fromArray(array $order, string $installation): array
    {
        $storeId = self::id($order['store_id'] ?? null, 'Store ID');
        $entityId = self::id($order['entity_id'] ?? null, 'Order entity ID');
        $incrementId = self::requiredString($order['increment_id'] ?? null, 'Order increment ID');
        $currency = self::currency($order['order_currency_code'] ?? null);
        $grandTotal = self::money($order['grand_total'] ?? null, 'Grand total');
        self::assertPhysicalFulfillment($order);
        self::assertSupportedShippingMethod($order['shipping_method'] ?? null);

        return [
            'profile' => [
                'email' => self::email($order['customer_email'] ?? null),
                'first_name' => self::optionalString($order['customer_firstname'] ?? null),
                'last_name' => self::optionalString($order['customer_lastname'] ?? null),
            ],
            'event' => [
                'action' => 'order.placed',
                'amount' => $grandTotal,
                'currency' => $currency,
                'tracked_at' => self::trackedAt($order['created_at'] ?? null),
                'object_parameters' => [
                    'name' => $incrementId,
                    'reference' => Reference::build($installation, 'order', $entityId),
                    'source' => 'magento',
                    'delivery' => 'deliver',
                    'payment_method' => self::paymentMethod($order['payment'] ?? null),
                    'sales_channel' => 'magento',
                    'total' => ['amount' => $grandTotal, 'currency' => $currency],
                    'metadata' => [
                        'magento' => [
                            'entity_id' => $entityId,
                            'increment_id' => $incrementId,
                            'quote_id' => self::optionalId($order['quote_id'] ?? null, 'Quote ID'),
                            'store_id' => $storeId,
                            'subtotal' => self::money($order['subtotal'] ?? null, 'Subtotal'),
                            'discount_amount' => self::money($order['discount_amount'] ?? null, 'Discount amount', true),
                            'shipping_amount' => self::money($order['shipping_amount'] ?? null, 'Shipping amount'),
                            'tax_amount' => self::money($order['tax_amount'] ?? null, 'Tax amount'),
                            'grand_total' => $grandTotal,
                        ],
                    ],
                    'items' => self::items($order['items'] ?? null, $installation, $currency),
                ],
            ],
        ];
    }

    private static function items(mixed $items, string $installation, string $currency): array
    {
        if (!is_array($items) || $items === []) {
            throw new InvalidArgumentException('Order items must be a non-empty array.');
        }

        $byItemId = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Order item must be an array.');
            }

            $type = $item['product_type'] ?? null;
            if ($type === 'virtual') {
                throw new InvalidArgumentException('Virtual order items are unsupported until a fulfillment mapping is agreed.');
            }
            if (in_array($type, ['bundle', 'grouped', 'downloadable'], true) || !in_array($type, ['simple', 'configurable'], true)) {
                throw new InvalidArgumentException('Unsupported Magento product type.');
            }

            $itemId = self::id($item['item_id'] ?? null, 'Order item ID');
            if (isset($byItemId[$itemId])) {
                throw new InvalidArgumentException('Order item IDs must be unique.');
            }
            $byItemId[$itemId] = $item;
        }

        $payload = [];
        $references = [];
        foreach ($byItemId as $itemId => $item) {
            $itemId = (string) $itemId;
            $parentId = $item['parent_item_id'] ?? null;
            if ($parentId !== null && $parentId !== '') {
                $parentId = self::id($parentId, 'Parent order item ID');
                if (!isset($byItemId[$parentId]) || ($byItemId[$parentId]['product_type'] ?? null) !== 'configurable') {
                    throw new InvalidArgumentException('Order item parent must be a configurable item.');
                }
                continue;
            }

            if ($item['product_type'] === 'configurable') {
                $children = array_filter(
                    $byItemId,
                    static fn(array $child): bool => ($child['parent_item_id'] ?? null) !== null && ($child['parent_item_id'] ?? null) !== '' && self::id($child['parent_item_id'], 'Parent order item ID') === $itemId
                );
                if (count($children) !== 1) {
                    throw new InvalidArgumentException('Configurable items must have exactly one child.');
                }

                $child = array_values($children)[0];
                if (($child['product_type'] ?? null) !== 'simple') {
                    throw new InvalidArgumentException('Configurable item child must be simple.');
                }
                $payload[] = self::item($child, $item, $installation, $currency, $references);
                continue;
            }

            $payload[] = self::item($item, $item, $installation, $currency, $references);
        }

        return $payload;
    }

    private static function assertPhysicalFulfillment(array $order): void
    {
        if (($order['is_virtual'] ?? false) === true || ($order['is_virtual'] ?? false) === 1 || ($order['is_virtual'] ?? false) === '1') {
            throw new InvalidArgumentException('Virtual orders are unsupported until a fulfillment mapping is agreed.');
        }
    }

    private static function assertSupportedShippingMethod(mixed $value): void
    {
        if ($value !== 'flatrate_flatrate') {
            throw new InvalidArgumentException('Only the flatrate_flatrate shipping method is supported by this development order adapter.');
        }
    }

    private static function item(array $product, array $sale, string $installation, string $currency, array &$references): array
    {
        $reference = Reference::build($installation, 'product', self::id($product['product_id'] ?? null, 'Product ID'));
        if (isset($references[$reference])) {
            throw new InvalidArgumentException('Multiple order lines for the same canonical product are unsupported because Hellotext merges them.');
        }
        $references[$reference] = true;

        return [
            'quantity' => self::quantity($sale['qty_ordered'] ?? null),
            'price' => ['amount' => self::money($sale['price'] ?? null, 'Item price'), 'currency' => $currency],
            'product' => [
                'name' => self::requiredString($product['name'] ?? null, 'Product name'),
                'reference' => $reference,
                'sku' => self::requiredString($product['sku'] ?? null, 'Product SKU'),
                'source' => 'magento',
            ],
        ];
    }

    private static function id(mixed $value, string $label): string
    {
        if (is_int($value) && $value > 0) {
            return (string) $value;
        }
        if (is_string($value) && preg_match('/^\d+$/', $value) === 1 && ($normalized = ltrim($value, '0')) !== '') {
            return $normalized;
        }
        throw new InvalidArgumentException("{$label} must be a positive numeric ID.");
    }

    private static function optionalId(mixed $value, string $label): ?string
    {
        return $value === null || $value === '' ? null : self::id($value, $label);
    }

    private static function money(mixed $value, string $label, bool $allowNegative = false): string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        $pattern = $allowNegative ? '/^-?\d+(?:\.\d+)?$/' : '/^\d+(?:\.\d+)?$/';
        if (!is_string($value) || preg_match($pattern, $value) !== 1) {
            throw new InvalidArgumentException("{$label} must be a finite decimal string.");
        }
        return $value;
    }

    private static function quantity(mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^(\d+)(?:\.0+)?$/', $value, $matches) === 1) {
            $integer = ltrim($matches[1], '0');
            if ($integer !== '' && (strlen($integer) < strlen((string) PHP_INT_MAX) || (strlen($integer) === strlen((string) PHP_INT_MAX) && strcmp($integer, (string) PHP_INT_MAX) <= 0))) {
                return (int) $integer;
            }
        }
        throw new InvalidArgumentException('Item quantity must be a positive integer.');
    }

    private static function currency(mixed $value): string
    {
        if (!is_string($value) || preg_match('/^[A-Z]{3}$/', $value) !== 1) {
            throw new InvalidArgumentException('Order currency must be an ISO 4217 code.');
        }
        return $value;
    }

    private static function email(mixed $value): string
    {
        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Order customer email is required.');
        }
        return strtolower($value);
    }

    private static function paymentMethod(mixed $payment): ?string
    {
        if ($payment === null) {
            return null;
        }
        if (!is_array($payment)) {
            throw new InvalidArgumentException('Payment data must be an array.');
        }
        return self::optionalString($payment['method'] ?? null);
    }

    private static function trackedAt(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException('Order created_at is required.');
        }
        $utc = new DateTimeZone('UTC');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $utc);
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $parsed->format('Y-m-d H:i:s') !== $value) {
            throw new InvalidArgumentException('Order created_at must be a valid timestamp.');
        }
        return $parsed->format('Y-m-d\\TH:i:s\\Z');
    }

    private static function requiredString(mixed $value, string $label): string
    {
        $value = self::optionalString($value);
        if ($value === null) {
            throw new InvalidArgumentException("{$label} is required.");
        }
        return $value;
    }

    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
