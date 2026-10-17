<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model\Data;

use Hellotext\Tracking\Api\Data\CustomerPageInterface;

class CustomerPage implements CustomerPageInterface
{
    public function __construct(
        private string $installationId,
        private int $storeId,
        private int $websiteId,
        private string $businessId,
        private array $items,
        private bool $hasMore,
        private int $nextCursor
    ) {}

    public function getSchemaVersion(): int { return 1; }
    public function getInstallationId(): string { return $this->installationId; }
    public function getStoreId(): int { return $this->storeId; }
    public function getWebsiteId(): int { return $this->websiteId; }
    public function getBusinessId(): string { return $this->businessId; }
    public function getItems(): array { return $this->items; }
    public function getHasMore(): bool { return $this->hasMore; }
    public function getNextCursor(): int { return $this->nextCursor; }
}
