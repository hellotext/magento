<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Api;

interface CatalogManagementInterface
{
    /**
     * @param int $limit
     * @param int $afterId
     * @return \Hellotext\Tracking\Api\Data\CatalogPageInterface
     */
    public function getProducts(int $limit = 50, int $afterId = 0): \Hellotext\Tracking\Api\Data\CatalogPageInterface;
}
