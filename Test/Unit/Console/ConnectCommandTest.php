<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Test\Unit\Console;

use Hellotext\Tracking\Console\ConnectCommand;
use Magento\Framework\App\State;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\OauthServiceInterface;
use Magento\Integration\Model\Integration;
use Magento\Integration\Model\Oauth\Consumer;
use Magento\Integration\Model\Oauth\Token;
use Magento\Integration\Model\Oauth\TokenFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ConnectCommandTest extends TestCase
{
    public function testBothLinkRequestsAdvertiseTheSecureStoreBaseUrl(): void
    {
        $integration = $this->getMockBuilder(Integration::class)->disableOriginalConstructor()
            ->onlyMethods(['getId', 'save'])->getMock();
        $integration->method('getId')->willReturn(1);
        $integration->setData(['endpoint' => 'https://app.hellotext.com/oauth/magento/callback', 'consumer_id' => 7]);
        $integrations = $this->createMock(IntegrationServiceInterface::class);
        $integrations->method('findByName')->with('Hellotext')->willReturn($integration);
        $consumer = $this->getMockBuilder(Consumer::class)->disableOriginalConstructor()
            ->onlyMethods(['save', 'getKey', 'getSecret'])->getMock();
        $consumer->method('getKey')->willReturn('test-consumer-key');
        $consumer->method('getSecret')->willReturn('test-consumer-secret');
        $oauth = $this->createMock(OauthServiceInterface::class);
        $oauth->method('loadConsumer')->with(7)->willReturn($consumer);
        $token = $this->createMock(Token::class);
        $token->method('createVerifierToken')->with(7)->willReturnSelf();
        $token->method('getVerifier')->willReturn('test-verifier');
        $tokens = $this->createMock(TokenFactory::class);
        $tokens->method('create')->willReturn($token);
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturnCallback(
            static fn ($type = 'link', $secure = null): string => $secure === true
                ? 'https://secure.example.com/store/' : 'http://insecure.example.com/store/'
        );
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->with(0)->willReturn($store);
        $curl = $this->createMock(Curl::class);
        $curl->method('getStatus')->willReturn(200);
        $requests = [];
        $curl->expects(self::exactly(2))->method('post')->with(
            'https://app.hellotext.com/oauth/magento/callback',
            self::callback(static function (array $fields) use (&$requests): bool {
                $requests[] = $fields;
                return true;
            })
        );
        $curls = $this->createMock(CurlFactory::class);
        $curls->method('create')->willReturn($curl);
        $dates = $this->createMock(DateTime::class);
        $dates->method('gmtDate')->willReturn('2026-10-16 12:00:00');
        $tester = new CommandTester(new ConnectCommand(
            $integrations, $oauth, $tokens, $stores, $curls, $this->createMock(State::class), $dates
        ));

        $status = $tester->execute(['code' => 'valid-hellotext-connect-code']);

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame('https://secure.example.com/store/', $requests[0]['store_base_url']);
        self::assertSame('https://secure.example.com/store/', $requests[1]['store_base_url']);
        self::assertSame('test-verifier', $requests[1]['oauth_verifier']);
    }
}
