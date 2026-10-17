<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model\Data;

use Hellotext\Tracking\Api\Data\StockResultInterface;

class StockResult implements StockResultInterface
{
    public function __construct(
        private string $installationId,
        private int $storeId,
        private string $businessId,
        private array $items
    ) {}

    public function getSchemaVersion(): int { return 1; }
    public function getInstallationId(): string { return $this->installationId; }
    public function getStoreId(): int { return $this->storeId; }
    public function getBusinessId(): string { return $this->businessId; }
    public function getItems(): array { return $this->items; }
}
