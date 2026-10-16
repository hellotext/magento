<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model\Data;

use Hellotext\Tracking\Api\Data\StockItemInterface;

class StockItem implements StockItemInterface
{
    public function __construct(private int $id, private bool $salable) {}

    public function getId(): int { return $this->id; }
    public function getSalable(): bool { return $this->salable; }
}
