<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Api\Data;

interface StockItemInterface
{
    /** @return int */
    public function getId(): int;
    /** @return bool */
    public function getSalable(): bool;
}
