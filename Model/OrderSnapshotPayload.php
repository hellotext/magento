<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model;

class OrderSnapshotPayload
{
    public static function fromOrder(object $order, string $installation, ?string $session): array
    {
        $storeId = self::positive($order->getStoreId(), 'store');
        $orderId = self::positive($order->getEntityId(), 'order');
        if ($order->getIsVirtual()) throw new \InvalidArgumentException('unsupported_virtual_order');
        $currency = (string) $order->getOrderCurrencyCode();
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) throw new \InvalidArgumentException('invalid_currency');
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $type = (string) $item->getProductType();
            if (!in_array($type, ['simple', 'configurable'], true)) throw new \InvalidArgumentException('unsupported_item_type:' . $type);
            $product = $type === 'configurable' ? self::configurableChild($item) : $item;
            if ($item->getIsVirtual() || $product->getIsVirtual()) throw new \InvalidArgumentException('unsupported_virtual_item');
            $items[] = [
                'product' => ['source' => 'magento', 'reference' => Reference::build($installation, 'product', self::positive($product->getProductId(), 'product')), 'name' => (string) $product->getName(), 'sku' => (string) $product->getSku()],
                'quantity' => self::quantity($item->getQtyOrdered()),
                'price' => ['amount' => self::money($item->getPrice()), 'currency' => $currency],
            ];
        }
        if ($items === []) throw new \InvalidArgumentException('missing_items');
        $shipping = (string) $order->getShippingMethod();
        return [
            'order_id' => (int) $orderId,
            'increment_id' => (string) $order->getIncrementId(),
            'reference' => Reference::build($installation, 'order', $orderId),
            'session' => $session,
            'customer_id' => ($id = (int) $order->getCustomerId()) > 0 ? $id : null,
            'customer_first_name' => self::optional($order->getCustomerFirstname()),
            'customer_last_name' => self::optional($order->getCustomerLastname()),
            'currency' => $currency,
            'grand_total' => self::money($order->getGrandTotal()),
            'delivery' => str_contains($shipping, 'pickup') ? 'collect' : 'deliver',
            'payment_method' => ($payment = $order->getPayment()) ? ($payment->getMethod() ?: null) : null,
            'items' => $items,
        ];
    }

    private static function configurableChild(object $item): object
    {
        $children = $item->getChildrenItems();
        if (!is_array($children) || count($children) !== 1 || (string) $children[0]->getProductType() !== 'simple') throw new \InvalidArgumentException('unsupported_configurable_children');
        return $children[0];
    }
    private static function positive(mixed $value, string $field): string { $value = (string) $value; if (preg_match('/^[1-9][0-9]*$/', $value) !== 1) throw new \InvalidArgumentException('invalid_' . $field); return $value; }
    private static function quantity(mixed $value): int { $value = (string) $value; if (preg_match('/^[1-9][0-9]*(?:\.0+)?$/', $value) !== 1) throw new \InvalidArgumentException('invalid_quantity'); return (int) $value; }
    private static function money(mixed $value): string { $value = (string) $value; if (preg_match('/^\d+(?:\.\d{1,8})?$/', $value) !== 1) throw new \InvalidArgumentException('invalid_money'); return $value; }
    private static function optional(mixed $value): ?string { $value = trim((string) $value); return $value === '' ? null : substr($value, 0, 255); }
}
