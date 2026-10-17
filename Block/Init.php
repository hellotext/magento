<?php

namespace Hellotext\Tracking\Block;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;
use Hellotext\Tracking\Model\Configuration;
use Hellotext\Tracking\Model\Reference;

class Init extends Template
{
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->scopeConfig = $scopeConfig;
    }

    public function getBusinessId(): string
    {
        return trim((string) $this->scopeConfig->getValue(
            'hellotext/tracking/business_id',
            ScopeInterface::SCOPE_STORE
        ));
    }

    public function getApiRoot(): string
    {
        return trim((string) $this->scopeConfig->getValue(
            'hellotext/tracking/api_root',
            ScopeInterface::SCOPE_STORE
        ));
    }

    public function getInstallationId(): string
    {
        return (string) $this->scopeConfig->getValue(
            'hellotext/tracking/installation_id',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getTrackingStoreId(): string
    {
        return (string) $this->_storeManager->getStore()->getId();
    }

    public function getTrackingCurrency(): string
    {
        return (string) $this->_storeManager->getStore()->getCurrentCurrencyCode();
    }

    public function hasSafeConfig(): bool
    {
        // Only the first one-page checkout screen and success page load tracking. Payment controller
        // actions and multishipping stay excluded.
        $request = $this->getRequest();
        $success = $request->getRouteName() === 'checkout' && $request->getControllerName() === 'onepage'
            && $request->getActionName() === 'success';
        if ($request->getRouteName() === 'multishipping'
            || ($request->getRouteName() === 'checkout' && $request->getControllerName() !== 'cart'
                && !$success && !$this->isCheckoutStartedPage())) {
            return false;
        }
        try {
            Reference::build($this->getInstallationId(), 'product', 1);
        } catch (\InvalidArgumentException $error) {
            return false;
        }

        return $this->getBusinessId() !== '' && Configuration::isSafeApiRoot($this->getApiRoot());
    }

    public function isCheckoutStartedPage(): bool
    {
        $request = $this->getRequest();

        return $request->getRouteName() === 'checkout'
            && $request->getControllerName() === 'index'
            && $request->getActionName() === 'index';
    }
}
