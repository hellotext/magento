<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Api;

interface ConfigurationManagementInterface
{
    /**
     * Applies the Hellotext connection to the store view in the REST path. Merchants never copy
     * these values by hand: Hellotext writes them after the store owner authorizes the integration.
     *
     * @param string $installationId Hellotext installation key (Default Config scope)
     * @param string $businessId Public business ID for this store view; empty disables the storefront
     * @param bool $identityEnabled
     * @return \Hellotext\Tracking\Api\Data\StatusInterface
     */
    public function save(
        string $installationId,
        string $businessId,
        bool $identityEnabled
    ): \Hellotext\Tracking\Api\Data\StatusInterface;
}
