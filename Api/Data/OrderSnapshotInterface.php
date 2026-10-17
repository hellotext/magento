<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Api\Data;

interface OrderSnapshotInterface
{
    /** @return int */
    public function getOrderId(): int;
    /** @return string */
    public function getIncrementId(): string;
    /** @return string */
    public function getReference(): string;
    /**
     * Magento preserves this nullable value with a required scalar descriptor.
     * @return string
     */
    public function getSession();
    /** @return int */
    public function getCustomerId();
    /** @return string */
    public function getCustomerFirstName();
    /** @return string */
    public function getCustomerLastName();
    /** @return string */
    public function getCurrency(): string;
    /** @return string */
    public function getGrandTotal(): string;
    /** @return string */
    public function getDelivery(): string;
    /** @return string */
    public function getPaymentMethod();
    /** @return string */
    public function getCreatedAt(): string;
    /** @return string */
    public function getUpdatedAt(): string;
    /** @return string */
    public function getState(): string;
    /** @return string */
    public function getStatus(): string;
    /** @return string */
    public function getRefundedTotal(): string;
    /** @return \Hellotext\Tracking\Api\Data\OrderItemInterface[] */
    public function getItems(): array;
}
