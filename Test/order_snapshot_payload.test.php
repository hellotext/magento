<?php
declare(strict_types=1);

require __DIR__ . '/../Model/Reference.php';
require __DIR__ . '/../Model/OrderSnapshotPayload.php';

use Hellotext\Tracking\Model\OrderSnapshotPayload;

final class FixtureItem
{
    public function __construct(private bool $virtual = false, private string $type = 'simple') {}
    public function getProductType(): string { return $this->type; }
    public function getIsVirtual(): bool { return $this->virtual; }
    public function getProductId(): int { return 11; }
    public function getName(): string { return 'Tee'; }
    public function getSku(): string { return 'TEE'; }
    public function getQtyOrdered(): string { return '2.000'; }
    public function getPrice(): string { return '10.00'; }
}

final class FixtureOrder
{
    public function __construct(private array $items, private bool $virtual = false) {}
    public function getStoreId(): int { return 1; }
    public function getEntityId(): int { return 21; }
    public function getIsVirtual(): bool { return $this->virtual; }
    public function getOrderCurrencyCode(): string { return 'USD'; }
    public function getAllVisibleItems(): array { return $this->items; }
    public function getIncrementId(): string { return '000021'; }
    public function getCustomerId(): int { return 0; }
    public function getCustomerFirstname(): string { return 'Guest'; }
    public function getCustomerLastname(): string { return 'Customer'; }
    public function getGrandTotal(): string { return '20.00'; }
    public function getShippingMethod(): string { return 'flatrate_flatrate'; }
    public function getPayment(): ?object { return null; }
}

function expectMappingFailure(FixtureOrder $order, string $reason): void
{
    try {
        OrderSnapshotPayload::fromOrder($order, 'magento-local', null);
    } catch (InvalidArgumentException $error) {
        if ($error->getMessage() === $reason) return;
        throw $error;
    }
    throw new RuntimeException('Expected mapping failure: ' . $reason);
}

$payload = OrderSnapshotPayload::fromOrder(new FixtureOrder([new FixtureItem()]), 'magento-local', '123e4567-e89b-42d3-a456-426614174000');
if ($payload['reference'] !== 'magento:magento-local:order:21' || $payload['session'] === null || $payload['delivery'] !== 'deliver' ||
    $payload['items'][0]['product']['reference'] !== 'magento:magento-local:product:11' || $payload['customer_id'] !== null || isset($payload['customer_email'])) {
    throw new RuntimeException('Order snapshot payload mapping failed.');
}
expectMappingFailure(new FixtureOrder([new FixtureItem(false, 'bundle')]), 'unsupported_item_type:bundle');
expectMappingFailure(new FixtureOrder([new FixtureItem()], true), 'unsupported_virtual_order');
expectMappingFailure(new FixtureOrder([new FixtureItem(), new FixtureItem(true)]), 'unsupported_virtual_item');
echo "Order snapshot payload checks passed.\n";
