<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model\Data;

use Hellotext\Tracking\Api\Data\OrderSnapshotInterface;

class OrderSnapshot implements OrderSnapshotInterface
{
    public function __construct(private array $data) {}

    /** Canonical wire fields used to budget a page before Magento serializes its DTOs. */
    public function toArray(): array { return $this->data; }

    public function getOrderId(): int { return $this->data['order_id']; }
    public function getIncrementId(): string { return $this->data['increment_id']; }
    public function getReference(): string { return $this->data['reference']; }
    public function getSession(): ?string { return $this->data['session']; }
    public function getCustomerId(): ?int { return $this->data['customer_id']; }
    public function getCustomerFirstName(): ?string { return $this->data['customer_first_name']; }
    public function getCustomerLastName(): ?string { return $this->data['customer_last_name']; }
    public function getCurrency(): string { return $this->data['currency']; }
    public function getGrandTotal(): string { return $this->data['grand_total']; }
    public function getDelivery(): string { return $this->data['delivery']; }
    public function getPaymentMethod(): ?string { return $this->data['payment_method']; }
    public function getCreatedAt(): string { return $this->data['created_at']; }
    public function getUpdatedAt(): string { return $this->data['updated_at']; }
    public function getState(): string { return $this->data['state']; }
    public function getStatus(): string { return $this->data['status']; }
    public function getRefundedTotal(): string { return $this->data['refunded_total']; }
    public function getItems(): array { return array_map(static fn (array $item): OrderItem => new OrderItem($item), $this->data['items']); }
}
