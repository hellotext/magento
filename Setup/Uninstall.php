<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Setup;

use Hellotext\Tracking\Model\IntegrationEndpoints;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\OauthServiceInterface;

/**
 * `bin/magento module:uninstall Hellotext_Tracking --remove-data` leaves no Hellotext access behind: the
 * declared integration and its OAuth consumer (so its token stops working), plus the module configuration.
 */
class Uninstall implements UninstallInterface
{
    /**
     * @param IntegrationServiceInterface $integrations
     * @param OauthServiceInterface $oauth
     */
    public function __construct(
        private IntegrationServiceInterface $integrations,
        private OauthServiceInterface $oauth
    ) {
    }

    /**
     * @inheritdoc
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $integration = $this->integrations->findByName(IntegrationEndpoints::NAME);
        if ($integration->getId()) {
            $data = $this->integrations->delete((int) $integration->getId());
            // IntegrationService::delete keeps the consumer; delete it too so no token survives.
            if (!empty($data['consumer_id'])) {
                $this->oauth->deleteConsumer((int) $data['consumer_id']);
            }
        }

        $connection = $setup->getConnection();
        $connection->delete($setup->getTable('core_config_data'), ['path LIKE ?' => 'hellotext/%']);
        $setup->endSetup();
    }
}
