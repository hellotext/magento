<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Observer;

use Hellotext\Tracking\Model\AnonymousSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Keeps the shopper's Hellotext session on the order it places, so the signed snapshot carries the
 * attribution the storefront built and no browser claim about the order is needed.
 */
class RememberOrderSession implements ObserverInterface
{
    public function __construct(private AnonymousSession $anonymous) {}

    public function execute(Observer $observer): void
    {
        try {
            $order = $observer->getEvent()->getOrder();
            $session = $this->anonymous->current();
            if (is_object($order) && $session !== null) {
                $order->setData('hellotext_session', $session);
            }
        } catch (\Throwable) {
            // Attribution is optional and must never block checkout.
        }
    }
}
