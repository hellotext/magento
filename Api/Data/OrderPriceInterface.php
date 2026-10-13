<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Api\Data;

interface OrderPriceInterface
{
    /** @return string */
    public function getAmount(): string;
    /** @return string */
    public function getCurrency(): string;
}
