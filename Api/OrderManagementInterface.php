<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Api;

interface OrderManagementInterface
{
    /**
     * @param string $beforeCreatedAt Exclusive UTC creation cutoff, Y-m-d H:i:s.
     * @param int $limit
     * @param int $afterId
     * @return \Hellotext\Tracking\Api\Data\OrderPageInterface
     */
    public function getOrders(string $beforeCreatedAt, int $limit = 50, int $afterId = 0): \Hellotext\Tracking\Api\Data\OrderPageInterface;

    /**
     * @param int $orderId
     * @return \Hellotext\Tracking\Api\Data\OrderPageInterface
     */
    public function getOrder(int $orderId): \Hellotext\Tracking\Api\Data\OrderPageInterface;
}
