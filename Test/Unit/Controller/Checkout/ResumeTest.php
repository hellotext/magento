<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Test\Unit\Controller\Checkout;

use Hellotext\Tracking\Controller\Checkout\Resume;
use Hellotext\Tracking\Model\Checkout\Resume as ResumeCheckout;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadata;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ResumeTest extends TestCase
{
    private $request;
    private $checkout;
    private $redirect;
    private $messages;
    private $cookies;
    private $logger;
    private Resume $subject;

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->checkout = $this->createMock(ResumeCheckout::class);
        $this->redirect = $this->createMock(Redirect::class);
        $this->messages = $this->createMock(ManagerInterface::class);
        $this->cookies = $this->createMock(CookieManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $redirects = $this->createMock(RedirectFactory::class);
        $redirects->method('create')->willReturn($this->redirect);
        $this->redirect->method('setPath')->willReturnSelf();
        $metadata = $this->createMock(CookieMetadataFactory::class);
        $metadata->method('createCookieMetadata')->willReturn(new CookieMetadata());

        $this->subject = new Resume(
            $this->request, $this->checkout, $redirects, $this->messages, $this->cookies, $metadata, $this->logger
        );
    }

    public function testPreparedCartRedirectsToSecureCheckoutAndRefreshesPrivateCartData(): void
    {
        $id = str_repeat('a', 32);
        $this->request->method('getParam')->willReturnMap([['cart', null, $id]]);
        $this->checkout->expects(self::once())->method('execute')->with($id);
        $this->cookies->expects(self::once())->method('deleteCookie')->with(
            'mage-cache-sessid', self::callback(static fn(CookieMetadata $metadata): bool => $metadata->getPath() === '/')
        );
        $this->redirect->expects(self::exactly(2))->method('setHeader')->withConsecutive(
            ['Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true],
            ['Referrer-Policy', 'no-referrer', true]
        );
        $this->redirect->expects(self::once())->method('setPath')->with('checkout', ['_secure' => true]);
        $this->messages->expects(self::never())->method('addErrorMessage');

        self::assertSame($this->redirect, $this->subject->execute());
    }

    public function testUnavailableCartRedirectsToTheBasketWithoutInvalidatingIt(): void
    {
        $this->request->method('getParam')->willReturnMap([['cart', null, 'invalid']]);
        $this->checkout->method('execute')->willThrowException(new NoSuchEntityException());
        $this->cookies->expects(self::never())->method('deleteCookie');
        $this->logger->expects(self::never())->method('error');
        $this->messages->expects(self::once())->method('addErrorMessage');
        $this->redirect->expects(self::once())->method('setPath')->with('checkout/cart', ['_secure' => true]);

        self::assertSame($this->redirect, $this->subject->execute());
    }

    public function testArrayParameterIsPassedAsAnInvalidId(): void
    {
        $this->request->method('getParam')->willReturnMap([['cart', null, ['invalid']]]);
        $this->checkout->expects(self::once())->method('execute')->with('')->willThrowException(new NoSuchEntityException());
        $this->cookies->expects(self::never())->method('deleteCookie');
        $this->redirect->expects(self::once())->method('setPath')->with('checkout/cart', ['_secure' => true]);

        $this->subject->execute();
    }

    public function testUnexpectedFailureDoesNotLogThePrivateCartIdOrExceptionMessage(): void
    {
        $id = str_repeat('a', 32);
        $this->request->method('getParam')->willReturnMap([['cart', null, $id]]);
        $this->checkout->method('execute')->willThrowException(new \RuntimeException('Failure for ' . $id));
        $this->logger->expects(self::once())->method('error')->with(
            'Unable to resume a Hellotext checkout.', ['exception_type' => \RuntimeException::class]
        );
        $this->messages->expects(self::once())->method('addErrorMessage');
        $this->redirect->expects(self::once())->method('setPath')->with('checkout/cart', ['_secure' => true]);

        $this->subject->execute();
    }

    /** @dataProvider redirectSessions */
    public function testPreservesTheSessionOnSuccessfulAndFailedHandoffs($session, bool $available, array $query): void
    {
        $id = str_repeat('a', 32);
        $this->request->method('getParam')->willReturnMap([
            ['cart', null, $id], ['hello_session', null, $session]
        ]);
        if (!$available) {
            $this->checkout->method('execute')->willThrowException(new NoSuchEntityException());
        }
        $params = ['_secure' => true];
        if ($query !== []) {
            $params['_query'] = $query;
        }
        $this->redirect->expects(self::once())->method('setPath')->with($available ? 'checkout' : 'checkout/cart', $params);

        $this->subject->execute();
    }

    public static function redirectSessions(): array
    {
        return [
            'tracked link' => ['AbC123xy', true, ['hello_session' => 'AbC123xy']],
            'anonymous UUID' => ['12345678-1234-4234-8234-123456789012', true, ['hello_session' => '12345678-1234-4234-8234-123456789012']],
            'failed handoff' => ['AbC123xy', false, ['hello_session' => 'AbC123xy']],
            'empty session' => ['', true, []],
            'array parameter' => [['AbC123xy'], true, []],
        ];
    }
}
