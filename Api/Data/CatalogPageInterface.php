<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Api\Data;

interface CatalogPageInterface
{
    /** @return int */
    public function getSchemaVersion(): int;
    /** @return string */
    public function getInstallationId(): string;
    /** @return int */
    public function getStoreId(): int;
    /** @return string */
    public function getBusinessId(): string;
    /** @return \Hellotext\Tracking\Api\Data\CatalogItemInterface[] */
    public function getItems(): array;
    /** @return bool */
    public function getHasMore(): bool;
    /** @return int */
    public function getNextCursor(): int;
}
