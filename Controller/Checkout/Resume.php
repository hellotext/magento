<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Controller\Checkout;

use Hellotext\Tracking\Model\Checkout\Resume as ResumeCheckout;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Psr\Log\LoggerInterface;

class Resume implements HttpGetActionInterface
{
    public function __construct(
        private RequestInterface $request,
        private ResumeCheckout $checkout,
        private RedirectFactory $redirects,
        private ManagerInterface $messages,
        private CookieManagerInterface $cookies,
        private CookieMetadataFactory $cookieMetadata,
        private LoggerInterface $logger
    ) {}

    public function execute(): Redirect
    {
        $result = $this->redirects->create();
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $result->setHeader('Referrer-Policy', 'no-referrer', true);
        $session = $this->request->getParam('hello_session');
        $redirectParams = ['_secure' => true];
        if (is_string($session) && $session !== '') {
            $redirectParams['_query'] = ['hello_session' => $session];
        }

        try {
            $maskedId = $this->request->getParam('cart');
            $this->checkout->execute(is_string($maskedId) ? $maskedId : '');

            // A GET redirect does not trigger Magento's POST invalidation.
            // Clear the browser's cached cart and checkout data before checkout.
            $metadata = $this->cookieMetadata->createCookieMetadata()->setPath('/');
            $this->cookies->deleteCookie('mage-cache-sessid', $metadata);

            return $result->setPath('checkout', $redirectParams);
        } catch (LocalizedException) {
            $this->messages->addErrorMessage(__('This checkout link is no longer available.'));
        } catch (\Exception $error) {
            $this->logger->error('Unable to resume a Hellotext checkout.', ['exception_type' => $error::class]);
            $this->messages->addErrorMessage(__('We could not open this checkout. Please try again.'));
        }

        return $result->setPath('checkout/cart', $redirectParams);
    }
}
