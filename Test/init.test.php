<?php
namespace Magento\Framework\App\Config { interface ScopeConfigInterface { public function getValue($path, $scope = null); } }
namespace Magento\Framework\View\Element\Template { class Context {
    public string $route = 'catalog';
    public string $controller = 'product';
    public string $action = 'view';
    public function getRouteName() { return $this->route; }
    public function getControllerName() { return $this->controller; }
    public function getActionName() { return $this->action; }
} }
namespace Magento\Framework\View\Element { class Template {
    public function __construct(private \Magento\Framework\View\Element\Template\Context $context, array $data = []) {}
    public function getRequest() { return $this->context; }
} }
namespace Magento\Store\Model { class ScopeInterface { public const SCOPE_STORE = 'store'; } }
namespace {
    foreach (['Reference', 'Configuration'] as $name) { require __DIR__ . "/../Model/$name.php"; }
    require __DIR__ . '/../Block/Init.php';
    $context = new \Magento\Framework\View\Element\Template\Context();
    $config = new class implements \Magento\Framework\App\Config\ScopeConfigInterface {
        public function getValue($path, $scope = null) { return match ($path) {
            'hellotext/tracking/installation_id' => 'magento-local',
            'hellotext/tracking/business_id' => 'public-business',
            default => 'http://api.lvh.me:3200/v1'
        }; }
    };
    $block = new \Hellotext\Tracking\Block\Init($context, $config);
    foreach ([['catalog', 'product', 'view', true], ['checkout', 'cart', 'index', true], ['checkout', 'index', 'index', true],
              ['checkout', 'onepage', 'saveOrder', false], ['checkout', 'onepage', 'success', true], ['multishipping', 'checkout', 'success', false]]
             as [$route, $controller, $action, $expected]) {
        $context->route = $route;
        $context->controller = $controller;
        $context->action = $action;
        if ($block->hasSafeConfig() !== $expected) { throw new \RuntimeException('Checkout route guard failed.'); }
    }
    echo "Storefront checkout-route guards passed.\n";
}
