<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Api;

interface CustomerManagementInterface
{
    /**
     * @param int $limit
     * @param int $afterId
     * @return \Hellotext\Tracking\Api\Data\CustomerPageInterface
     */
    public function getCustomers(int $limit = 50, int $afterId = 0): \Hellotext\Tracking\Api\Data\CustomerPageInterface;
}
