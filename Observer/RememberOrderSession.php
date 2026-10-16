<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Observer;

use Magento\Cookie\Helper\Cookie as CookieHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;

/**
 * Keeps the shopper's Hellotext session on the order it places, so the signed snapshot carries the
 * attribution the storefront built and no browser claim about the order is needed.
 */
class RememberOrderSession implements ObserverInterface
{
    public function __construct(
        private CookieManagerInterface $cookies,
        private CookieHelper $consent
    ) {}

    public function execute(Observer $observer): void
    {
        try {
            if ($this->consent->isUserNotAllowSaveCookie()) {
                return;
            }
            $order = $observer->getEvent()->getOrder();
            // Hellotext.js stores the selected URL, cookie or anonymous session in this cookie.
            $session = $this->cookies->getCookie('hello_session');
            if (is_object($order) && is_string($session) && $session !== '' && strlen($session) <= 36) {
                $order->setData('hellotext_session', $session);
            }
        } catch (\Throwable) {
            // Attribution is optional and must never block checkout.
        }
    }
}
