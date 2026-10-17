<?php
declare(strict_types=1);

namespace Magento\Framework\Setup {
    interface ModuleContextInterface {}
    interface SchemaSetupInterface
    {
        public function startSetup();
        public function endSetup();
        public function getConnection();
        public function getTable($name);
    }
    interface UninstallInterface { public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context); }
}
namespace Magento\Integration\Api {
    interface IntegrationServiceInterface { public function findByName($name); public function delete($id); }
    interface OauthServiceInterface { public function deleteConsumer($id); }
}
namespace {
    require __DIR__ . '/../Model/IntegrationEndpoints.php';
    require __DIR__ . '/../Setup/Uninstall.php';

    $calls = [];
    $integrations = new class($calls) implements \Magento\Integration\Api\IntegrationServiceInterface {
        public function __construct(private array &$calls) {}
        public function findByName($name) { $this->calls[] = "find:$name"; return new class { public function getId() { return 7; } }; }
        public function delete($id) { $this->calls[] = "delete:$id"; return ['consumer_id' => 9]; }
    };
    $oauth = new class($calls) implements \Magento\Integration\Api\OauthServiceInterface {
        public function __construct(private array &$calls) {}
        public function deleteConsumer($id) { $this->calls[] = "consumer:$id"; }
    };
    $connection = new class($calls) {
        public function __construct(private array &$calls) {}
        public function delete($table, $where) { $this->calls[] = "config:$table:" . json_encode($where); }
        public function dropTable($table) { $this->calls[] = "drop:$table"; }
    };
    $setup = new class($connection) implements \Magento\Framework\Setup\SchemaSetupInterface {
        public function __construct(private $connection) {}
        public function startSetup() {}
        public function endSetup() {}
        public function getConnection() { return $this->connection; }
        public function getTable($name) { return $name; }
    };

    (new \Hellotext\Tracking\Setup\Uninstall($integrations, $oauth))
        ->uninstall($setup, new class implements \Magento\Framework\Setup\ModuleContextInterface {});

    $expected = ['find:Hellotext', 'delete:7', 'consumer:9', 'config:core_config_data:{"path LIKE ?":"hellotext\/%"}'];
    if ($calls !== $expected) throw new RuntimeException('Unexpected uninstall steps: ' . json_encode($calls));

    echo "uninstall: ok\n";
}
