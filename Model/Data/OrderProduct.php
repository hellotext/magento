<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model\Data;

use Hellotext\Tracking\Api\Data\OrderProductInterface;

class OrderProduct implements OrderProductInterface
{
    public function __construct(private array $data) {}

    public function getSource(): string { return $this->data['source']; }
    public function getReference(): string { return $this->data['reference']; }
    public function getName(): string { return $this->data['name']; }
    public function getSku(): string { return $this->data['sku']; }
}
