<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Api;

interface StatusManagementInterface
{
    /** @return \Hellotext\Tracking\Api\Data\StatusInterface */
    public function getStatus(): \Hellotext\Tracking\Api\Data\StatusInterface;
}
