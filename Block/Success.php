<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Block;

use Hellotext\Tracking\Model\Reference;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;

/**
 * Exposes only the canonical reference of the order just placed. Hellotext reads its totals, lines and
 * buyer from the store's signed API; nothing else about the order reaches the browser.
 */
class Success extends Template
{
    public function __construct(
        Template\Context $context,
        private ScopeConfigInterface $scopeConfig,
        private CheckoutSession $checkout,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOrderReference(): string
    {
        try {
            $orderId = (int) $this->checkout->getLastRealOrder()->getId();
            if ($orderId <= 0) {
                return '';
            }
            $installation = (string) $this->scopeConfig->getValue('hellotext/tracking/installation_id', ScopeInterface::SCOPE_STORE);

            return Reference::build($installation, 'order', $orderId);
        } catch (\Throwable) {
            // Optional analytics must never break the success page.
            return '';
        }
    }
}
