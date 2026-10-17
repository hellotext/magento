<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model;

use Magento\Framework\App\ProductMetadataInterface;

class VersionInformation
{
    // The storefront follows the latest release; the exact version is whatever jsDelivr serves.
    public const SDK_VERSION = 'latest';

    public function __construct(private ProductMetadataInterface $magento) {}

    public function moduleVersion(): ?string
    {
        if (!class_exists(\Composer\InstalledVersions::class)) {
            return null;
        }
        try {
            return \Composer\InstalledVersions::getPrettyVersion('hellotext/module-tracking') ?: null;
        } catch (\OutOfBoundsException) {
            // app/code source mounts do not have a Composer package version.
            return null;
        }
    }

    public function magentoVersion(): string { return $this->magento->getVersion(); }
    public function magentoEdition(): string { return $this->magento->getEdition(); }
    public function phpVersion(): string { return PHP_VERSION; }
}
