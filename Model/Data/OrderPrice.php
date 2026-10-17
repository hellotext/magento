<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model\Data;

use Hellotext\Tracking\Api\Data\OrderPriceInterface;

class OrderPrice implements OrderPriceInterface
{
    public function __construct(private array $data) {}

    public function getAmount(): string { return $this->data['amount']; }
    public function getCurrency(): string { return $this->data['currency']; }
}
