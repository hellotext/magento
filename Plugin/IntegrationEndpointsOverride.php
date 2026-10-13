<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Plugin;

use Hellotext\Tracking\Model\IntegrationEndpoints;
use Magento\Framework\App\DeploymentConfig;
use Magento\Integration\Model\ConsolidatedConfig;

class IntegrationEndpointsOverride
{
    public function __construct(private DeploymentConfig $deployment) {}

    public function afterGetIntegrations(ConsolidatedConfig $subject, $result)
    {
        return is_array($result)
            ? IntegrationEndpoints::apply($result, $this->deployment->get(IntegrationEndpoints::CONFIG_PATH))
            : $result;
    }
}
