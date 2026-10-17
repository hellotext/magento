<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Api;

interface StockManagementInterface
{
    /**
     * @param int[] $productIds
     * @return \Hellotext\Tracking\Api\Data\StockResultInterface
     */
    public function getStock(array $productIds): \Hellotext\Tracking\Api\Data\StockResultInterface;
}
