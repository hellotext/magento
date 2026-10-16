<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Api\Data;

interface StockResultInterface
{
    /** @return int */
    public function getSchemaVersion(): int;
    /** @return string */
    public function getInstallationId(): string;
    /** @return int */
    public function getStoreId(): int;
    /** @return string */
    public function getBusinessId(): string;
    /** @return \Hellotext\Tracking\Api\Data\StockItemInterface[] */
    public function getItems(): array;
}
