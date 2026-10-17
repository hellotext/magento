<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model\Data;

use Hellotext\Tracking\Api\Data\StatusInterface;

class Status implements StatusInterface
{
    public function __construct(
        private string $installationId,
        private int $storeId,
        private int $websiteId,
        private array $storeCodes,
        private string $businessId,
        private bool $configurationValid,
        private ?string $moduleVersion,
        private string $sdkVersion,
        private string $magentoVersion,
        private string $magentoEdition,
        private string $phpVersion
    ) {}

    public function getSchemaVersion(): int { return 1; }
    public function getInstallationId(): string { return $this->installationId; }
    public function getStoreId(): int { return $this->storeId; }
    public function getWebsiteId(): int { return $this->websiteId; }
    public function getStoreCodes(): array { return $this->storeCodes; }
    public function getBusinessId(): string { return $this->businessId; }
    public function getConfigurationValid(): bool { return $this->configurationValid; }
    public function getModuleVersion(): ?string { return $this->moduleVersion; }
    public function getSdkVersion(): string { return $this->sdkVersion; }
    public function getMagentoVersion(): string { return $this->magentoVersion; }
    public function getMagentoEdition(): string { return $this->magentoEdition; }
    public function getPhpVersion(): string { return $this->phpVersion; }
}
