<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Console;

use Hellotext\Tracking\Model\ConnectLink;
use Hellotext\Tracking\Model\IntegrationEndpoints;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\OauthServiceInterface;
use Magento\Integration\Model\Integration;
use Magento\Integration\Model\Oauth\TokenFactory;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Links this store to the Hellotext business that printed the install commands. It performs the same steps
 * as Admin → System → Integrations → Activate on the declared integration and adds the one-time code, so
 * Hellotext detects the store without the Admin popup. The owner still confirms in Hellotext.
 */
class ConnectCommand extends Command
{
    private const MESSAGES = [
        'linked' => '<info>Store linked. Go back to Hellotext to confirm the connection.</info>',
        'refused' => '<error>Hellotext refused the link: the code expired or is not for this store. Reload the Hellotext page and run the new command.</error>',
        'rate_limited' => '<error>Hellotext is limiting requests. Wait a minute and run the command again.</error>',
        'unreachable' => '<error>Could not reach Hellotext. Check that this server can make outbound HTTPS requests, then retry.</error>',
        'failed' => '<error>Magento could not prepare the integration credentials. Run bin/magento setup:upgrade and retry.</error>',
    ];

    public function __construct(
        private IntegrationServiceInterface $integrations,
        private OauthServiceInterface $oauth,
        private TokenFactory $tokens,
        private StoreManagerInterface $stores,
        private CurlFactory $curls,
        private State $state,
        private DateTime $dates
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('hellotext:connect')
            ->setDescription('Link this store to Hellotext with the code shown in Hellotext.')
            ->addArgument('code', InputArgument::REQUIRED, 'Link code from Hellotext → Settings → Integrations → Magento');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = trim((string) $input->getArgument('code'));
        if (!ConnectLink::isValidCode($code)) {
            $output->writeln('<error>That is not a Hellotext link code. Copy the full command from Hellotext.</error>');
            return Command::FAILURE;
        }
        try {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Throwable) {
            // Already set by another command in the same process.
        }

        $integration = $this->integrations->findByName(IntegrationEndpoints::NAME);
        if (!$integration->getId()) {
            $output->writeln('<error>The Hellotext integration is missing. Run bin/magento setup:upgrade first.</error>');
            return Command::FAILURE;
        }
        $endpoint = (string) $integration->getEndpoint();
        if (!IntegrationEndpoints::isAllowed($endpoint)) {
            $output->writeln('<error>The Hellotext integration has an invalid endpoint. Run bin/magento setup:upgrade.</error>');
            return Command::FAILURE;
        }

        $storeBaseUrl = (string) $this->stores->getStore(0)->getBaseUrl();
        $outcome = $this->post($endpoint, ConnectLink::checkFields($storeBaseUrl, $code));
        if ($outcome !== 'linked') {
            $output->writeln(self::MESSAGES[$outcome]);
            return Command::FAILURE;
        }

        try {
            // Same as Admin → Reauthorize: any previous token stops working before new credentials are sent.
            $consumerId = (int) $integration->getConsumerId();
            $this->oauth->deleteIntegrationToken($consumerId);
            $integration->setStatus(Integration::STATUS_INACTIVE)->save();
            $consumer = $this->oauth->loadConsumer($consumerId);
            // Magento rejects a consumer whose credentials were posted too long ago; Activate refreshes it too.
            $consumer->setUpdatedAt($this->dates->gmtDate())->save();
            $verifier = $this->tokens->create()->createVerifierToken($consumerId)->getVerifier();
            $fields = ConnectLink::fields((string) $consumer->getKey(), (string) $consumer->getSecret(),
                $storeBaseUrl, (string) $verifier, $code);
        } catch (\Throwable) {
            // Exceptions may carry credentials; never print them.
            $output->writeln(self::MESSAGES['failed']);
            return Command::FAILURE;
        }

        $outcome = $this->post($endpoint, $fields);
        $output->writeln(self::MESSAGES[$outcome]);

        return $outcome === 'linked' ? Command::SUCCESS : Command::FAILURE;
    }

    /** @param array<string, string> $fields */
    private function post(string $endpoint, array $fields): string
    {
        try {
            $curl = $this->curls->create();
            $curl->setTimeout(20);
            $curl->setOption(CURLOPT_FOLLOWLOCATION, false);
            $curl->post($endpoint, $fields);
            return ConnectLink::outcome((int) $curl->getStatus());
        } catch (\Throwable) {
            // Exceptions may carry request data; never print them.
            return 'unreachable';
        }
    }
}
