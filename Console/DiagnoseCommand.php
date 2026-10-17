<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Console;

use Hellotext\Tracking\Model\Configuration;
use Hellotext\Tracking\Model\IntegrationEndpoints;
use Hellotext\Tracking\Model\Reference;
use Hellotext\Tracking\Model\VersionInformation;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DiagnoseCommand extends Command
{
    public function __construct(
        private ScopeConfigInterface $config,
        private StoreManagerInterface $stores,
        private VersionInformation $versions,
        private IntegrationServiceInterface $integrations
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('hellotext:diagnose')
            ->setDescription('Print safe Hellotext installation and delivery diagnostics.')
            ->addOption('store', null, InputOption::VALUE_REQUIRED, 'Magento store-view code', 'default')
            ->addOption('ping', null, InputOption::VALUE_NONE, 'Check transport reachability of the configured API root.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $store = $this->stores->getStore((string) $input->getOption('store'));
            $storeId = (int) $store->getId();
            if ($storeId <= 0) {
                throw new \InvalidArgumentException('Invalid store.');
            }
        } catch (\Throwable) {
            $output->writeln('store: invalid');
            return Command::FAILURE;
        }

        $installation = trim((string) $this->config->getValue('hellotext/tracking/installation_id', ScopeInterface::SCOPE_STORE, $storeId));
        $business = trim((string) $this->config->getValue('hellotext/tracking/business_id', ScopeInterface::SCOPE_STORE, $storeId));
        $apiRoot = trim((string) $this->config->getValue('hellotext/tracking/api_root', ScopeInterface::SCOPE_STORE, $storeId));
        $validInstallation = true;
        try {
            Reference::build($installation, 'product', 1);
        } catch (\InvalidArgumentException) {
            $validInstallation = false;
        }
        $validApi = Configuration::isSafeApiRoot($apiRoot);

        $output->writeln('module: Hellotext_Tracking');
        $output->writeln('module_version: ' . ($this->versions->moduleVersion() ?? 'source checkout (unversioned)'));
        $output->writeln('magento: ' . $this->versions->magentoEdition() . ' ' . $this->versions->magentoVersion());
        $output->writeln('php: ' . $this->versions->phpVersion());
        $output->writeln('integration: ' . $this->integrationState());
        $output->writeln('store: ' . $store->getCode() . ' (' . $storeId . ')');
        $output->writeln('installation_key: ' . ($validInstallation ? 'configured' : 'invalid'));
        $output->writeln('business_id: ' . ($business === '' ? 'missing' : 'configured'));
        $output->writeln('api_root: ' . ($validApi ? $apiRoot : 'invalid'));
        $output->writeln('identity_delivery: ' . $this->flag('hellotext/tracking/identity_enabled', $storeId));
        $output->writeln('php_to_api_transport: ' . ($input->getOption('ping') && $validApi
            ? $this->ping($apiRoot) : 'not checked (use --ping)'));

        return $validInstallation && $business !== '' && $validApi ? Command::SUCCESS : Command::FAILURE;
    }

    private function integrationState(): string
    {
        try {
            $integration = $this->integrations->findByName(IntegrationEndpoints::NAME);
        } catch (\Throwable) {
            return 'unavailable';
        }
        if (!$integration->getId()) {
            return 'missing (run setup:upgrade)';
        }
        $endpoint = parse_url((string) $integration->getEndpoint());
        $origin = is_array($endpoint) && isset($endpoint['scheme'], $endpoint['host'])
            ? $endpoint['scheme'] . '://' . $endpoint['host'] . (isset($endpoint['port']) ? ':' . $endpoint['port'] : '')
            : 'invalid endpoint';
        $state = match ((int) $integration->getStatus()) {
            1 => 'active',
            2 => 'recreated (authorize it again in System > Integrations)',
            default => 'inactive (activate it in System > Integrations)',
        };
        return $state . ' via ' . $origin;
    }

    private function flag(string $path, int $storeId): string
    {
        return $this->config->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId) ? 'on' : 'off';
    }

    private function ping(string $apiRoot): string
    {
        if (!function_exists('curl_init')) {
            return 'unavailable (cURL missing)';
        }
        $handle = curl_init(rtrim($apiRoot, '/') . '/');
        if ($handle === false) {
            return 'unavailable (cURL initialization)';
        }
        curl_setopt_array($handle, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROXY => '',
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        try {
            $result = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            return $result === false || $status === 0 ? 'unreachable' : 'reachable (HTTP ' . $status . ')';
        } finally {
            curl_close($handle);
        }
    }
}
