<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Api\Data;

interface OrderItemInterface
{
    /** @return \Hellotext\Tracking\Api\Data\OrderProductInterface */
    public function getProduct(): \Hellotext\Tracking\Api\Data\OrderProductInterface;
    /** @return int */
    public function getQuantity(): int;
    /** @return \Hellotext\Tracking\Api\Data\OrderPriceInterface */
    public function getPrice(): \Hellotext\Tracking\Api\Data\OrderPriceInterface;
}
