<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model\Data;

use Hellotext\Tracking\Api\Data\OrderItemInterface;

class OrderItem implements OrderItemInterface
{
    public function __construct(private array $data) {}

    public function getProduct(): OrderProduct { return new OrderProduct($this->data['product']); }
    public function getQuantity(): int { return $this->data['quantity']; }
    public function getPrice(): OrderPrice { return new OrderPrice($this->data['price']); }
}
