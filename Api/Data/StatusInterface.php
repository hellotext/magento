<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Api\Data;

interface StatusInterface
{
    /** @return int */
    public function getSchemaVersion(): int;
    /** @return string */
    public function getInstallationId(): string;
    /** @return int */
    public function getStoreId(): int;
    /** @return int */
    public function getWebsiteId(): int;
    /** @return string[] */
    public function getStoreCodes(): array;
    /** @return string */
    public function getBusinessId(): string;
    /** @return bool */
    public function getConfigurationValid(): bool;
    /**
     * A required scalar descriptor keeps an app/code `null` in Magento's JSON
     * output. The concrete DTO deliberately returns ?string.
     *
     * @return string
     */
    public function getModuleVersion();
    /** @return string */
    public function getSdkVersion(): string;
    /** @return string */
    public function getMagentoVersion(): string;
    /** @return string */
    public function getMagentoEdition(): string;
    /** @return string */
    public function getPhpVersion(): string;
}
