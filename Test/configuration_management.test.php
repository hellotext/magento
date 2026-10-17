<?php
declare(strict_types=1);

namespace Magento\Framework\App\Config\Storage {
    interface WriterInterface { public function save($path, $value, $scope = 'default', $scopeId = 0); public function delete($path, $scope = 'default', $scopeId = 0); }
}
namespace Magento\Framework\App\Config {
    interface ReinitableConfigInterface { public function reinit(); }
}
namespace Magento\Framework\App\Cache {
    interface TypeListInterface { public function cleanType($typeCode); }
}
namespace Magento\Framework\Exception {
    class InputException extends \Exception { public function __construct($phrase) { parent::__construct((string) $phrase); } }
}
namespace Magento\Store\Model {
    interface StoreManagerInterface { public function getStore($storeId = null); }
    class ScopeInterface { public const SCOPE_WEBSITES = 'websites'; public const SCOPE_STORES = 'stores'; }
}
namespace {
    function __($text) { return $text; }

    require __DIR__ . '/../Api/Data/StatusInterface.php';
    require __DIR__ . '/../Api/StatusManagementInterface.php';
    require __DIR__ . '/../Api/ConfigurationManagementInterface.php';
    require __DIR__ . '/../Model/Reference.php';
    require __DIR__ . '/../Model/WebsiteScope.php';
    require __DIR__ . '/../Model/ConfigurationManagement.php';

    use Hellotext\Tracking\Model\ConfigurationManagement;
    use Magento\Framework\Exception\InputException;

    function fixture(int $storeId): array
    {
        $writer = new class implements \Magento\Framework\App\Config\Storage\WriterInterface {
            public array $saved = [];
            public array $deleted = [];
            public function save($path, $value, $scope = 'default', $scopeId = 0) { $this->saved[] = [$path, $value, $scope, $scopeId]; }
            public function delete($path, $scope = 'default', $scopeId = 0) { $this->deleted[] = [$path, $scope, $scopeId]; }
        };
        $config = new class implements \Magento\Framework\App\Config\ReinitableConfigInterface {
            public int $reinits = 0;
            public function reinit() { $this->reinits++; }
        };
        $cache = new class implements \Magento\Framework\App\Cache\TypeListInterface {
            public array $cleaned = [];
            public function cleanType($typeCode) { $this->cleaned[] = $typeCode; }
        };
        $stores = new class($storeId) implements \Magento\Store\Model\StoreManagerInterface {
            public function __construct(private int $id) {}
            public function getStore($storeId = null) { return new class($this->id) { public function __construct(private int $id) {} public function getId() { return $this->id; } public function getWebsiteId() { return 9; } }; }
            public function getStores() { return array_map(static fn (int $id) => new class($id) { public function __construct(private int $id) {} public function getId() { return $this->id; } public function getWebsiteId() { return 9; } }, [3, 4]); }
        };
        $status = new class implements \Hellotext\Tracking\Api\StatusManagementInterface {
            public function getStatus(): \Hellotext\Tracking\Api\Data\StatusInterface
            {
                return new class implements \Hellotext\Tracking\Api\Data\StatusInterface {
                    public function getSchemaVersion(): int { return 1; }
                    public function getInstallationId(): string { return ''; }
                    public function getStoreId(): int { return 1; }
                    public function getWebsiteId(): int { return 9; }
                    public function getStoreCodes(): array { return ['default']; }
                    public function getBusinessId(): string { return ''; }
                    public function getConfigurationValid(): bool { return true; }
                    public function getModuleVersion() { return null; }
                    public function getSdkVersion(): string { return ''; }
                    public function getMagentoVersion(): string { return ''; }
                    public function getMagentoEdition(): string { return ''; }
                    public function getPhpVersion(): string { return ''; }
                };
            }
        };
        return [new ConfigurationManagement($writer, $config, $cache, $stores, $status), $writer, $config, $cache];
    }

    function expectInput(callable $call, string $label): void
    {
        try { $call(); } catch (InputException) { return; }
        throw new RuntimeException("Expected input rejection: $label");
    }

    $key = '2b89d85a-739c-4b5b-a644-1bdfbe36467a';

    [$management, $writer, $config, $cache] = fixture(3);
    $management->save($key, '4EPvZP3w', true);
    if ($writer->saved !== [
        ['hellotext/tracking/installation_id', $key, 'websites', 9],
        ['hellotext/tracking/business_id', '4EPvZP3w', 'websites', 9],
        ['hellotext/tracking/identity_enabled', '1', 'websites', 9],
    ]) throw new RuntimeException('Expected the full connection configuration at website scope: ' . json_encode($writer->saved));
    if (count($writer->deleted) !== 6 || $writer->deleted[0] !== ['hellotext/tracking/installation_id', 'stores', 3] ||
        $writer->deleted[5] !== ['hellotext/tracking/identity_enabled', 'stores', 4]) throw new RuntimeException('Expected old store-view overrides to be cleared for every website view');
    if ($config->reinits !== 1 || $cache->cleaned !== ['config', 'full_page']) throw new RuntimeException('Expected config reinit and config + FPC cleaning');

    [$management, $writer] = fixture(3);
    $management->save($key, '', false);
    if ($writer->saved[1] !== ['hellotext/tracking/business_id', '', 'websites', 9]) throw new RuntimeException('Expected disconnect to clear the website business ID');

    [$management, $writer, , $cache] = fixture(0);
    expectInput(fn () => $management->save($key, '4EPvZP3w', false), 'admin/all store scope');
    if ($writer->saved !== [] || $cache->cleaned !== []) throw new RuntimeException('Rejected requests must not write');

    [$management, $writer] = fixture(1);
    expectInput(fn () => $management->save('Not A Key', '4EPvZP3w', false), 'installation format');
    expectInput(fn () => $management->save($key, 'bad id!', false), 'business format');
    expectInput(fn () => $management->save($key, '', true), 'identity without business');
    if ($writer->saved !== []) throw new RuntimeException('Rejected requests must not write');

    echo "configuration management: ok\n";
}
