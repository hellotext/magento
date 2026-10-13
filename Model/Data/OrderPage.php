<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model\Data;

use Hellotext\Tracking\Api\Data\OrderPageInterface;

class OrderPage implements OrderPageInterface
{
    public function __construct(private array $scope, private array $items, private bool $hasMore, private int $nextCursor) {}

    public function getSchemaVersion(): int { return 1; }
    public function getInstallationId(): string { return $this->scope['installation_id']; }
    public function getStoreId(): int { return $this->scope['store_id']; }
    public function getWebsiteId(): int { return $this->scope['website_id']; }
    public function getBusinessId(): string { return $this->scope['business_id']; }
    public function getItems(): array { return $this->items; }
    public function getHasMore(): bool { return $this->hasMore; }
    public function getNextCursor(): int { return $this->nextCursor; }
}
