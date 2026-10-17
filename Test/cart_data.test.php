<?php
namespace Magento\Checkout\CustomerData { class Cart {} }
namespace Magento\Checkout\Model {
    class Session {
        public array $data = [];
        public object $quote;
        public function getData($key) { return $this->data[$key] ?? null; }
        public function setData($key, $value) { $this->data[$key] = $value; }
        public function unsetData($key) { unset($this->data[$key]); }
        public function getQuote() { return $this->quote; }
    }
}
namespace Magento\Customer\Model { class Session {
    public bool $loggedIn = false;
    public int $customerId = 42;
    public function isLoggedIn() { return $this->loggedIn; }
    public function getCustomerId() { return $this->customerId; }
} }
namespace Magento\Cookie\Helper { class Cookie {
    public bool $denied = false;
    public function isUserNotAllowSaveCookie() { return $this->denied; }
} }
namespace Magento\Framework\App\Config { interface ScopeConfigInterface { public function getValue($path, $scope = null, $id = null); public function isSetFlag($path, $scope = null, $id = null); } }
namespace Magento\Store\Model {
    class ScopeInterface { public const SCOPE_STORE = 'store'; }
    interface StoreManagerInterface { public function getStore(); }
}
namespace {
    foreach (['Reference', 'Configuration', 'CartPayload'] as $file) { require __DIR__ . "/../Model/$file.php"; }
    require __DIR__ . '/../Plugin/CartData.php';
    $checkout = new \Magento\Checkout\Model\Session();
    $customer = new \Magento\Customer\Model\Session();
    $config = new class implements \Magento\Framework\App\Config\ScopeConfigInterface {
        public string $api = 'http://api.lvh.me:3200/v1';
        public bool $identity = true;
        public function isSetFlag($path, $scope = null, $id = null) { return $path === 'hellotext/tracking/identity_enabled' && $this->identity; }
        public function getValue($path, $scope = null, $id = null) { return match ($path) {
            'hellotext/tracking/installation_id' => 'magento-local',
            'hellotext/tracking/business_id' => 'public-business',
            default => $this->api
        }; }
    };
    $stores = new class implements \Magento\Store\Model\StoreManagerInterface {
        public function getStore() { return $this; }
        public function getId() { return 1; }
        public function getCurrentCurrencyCode() { return 'USD'; }
    };
    $quote = new class {
        public int $store = 1;
        public ?int $customer = null;
        public ?int $id = null;
        public array $items = [];
        public function getId() { return $this->id; }
        public function getStoreId() { return $this->store; }
        public function getCustomerId() { return $this->customer; }
        public function getQuoteCurrencyCode() { return 'USD'; }
        public function getAllVisibleItems() { return $this->items; }
    };
    $checkout->quote = $quote;
    $cookies = new \Magento\Cookie\Helper\Cookie();
    $plugin = new \Hellotext\Tracking\Plugin\CartData($checkout, $customer, $config, $stores, $cookies);
    $read = fn() => $plugin->afterGetSectionData(new \Magento\Checkout\CustomerData\Cart(), ['items' => ['native-preserved']]);
    $assert = function ($value) { if (!$value) { throw new \RuntimeException('Private cart assertion failed.'); } };
    $data = $read();
    $assert($data['items'] === ['native-preserved'] && $data['hellotext']['items'] === [] && $data['hellotext']['enabled']);
    $assert(!array_key_exists('session', $data['hellotext']) && !array_key_exists('anonymous', $data['hellotext']));
    $quote->id = 17;
    $assert($read()['hellotext']['checkout_reference'] === 'magento:magento-local:checkout:17');
    $assert($read()['hellotext']['context']['currency'] === 'USD');
    $assert(!array_key_exists('quote_id', $read()['hellotext']) && !array_key_exists('quoteId', $read()['hellotext']['context']));
    // Cookie Restriction Mode disables tracking until consent is granted.
    $cookies->denied = true;
    $assert($read()['hellotext'] === ['enabled' => false]);
    $cookies->denied = false;
    $resumed = $read()['hellotext'];
    $assert($resumed['enabled'] === true && !array_key_exists('session', $resumed));
    $quote->id = null;
    $assert($read()['hellotext']['checkout_reference'] === null);
    // A signed-in shopper keeps being tracked and is identified by their ID only, with their own quote.
    $customer->loggedIn = true;
    $signedIn = $read()['hellotext'];
    $assert($signedIn['enabled'] === true && $signedIn['identity'] === ['customer_id' => '42']);
    $quote->customer = 42;
    $assert($read()['hellotext']['identity'] === ['customer_id' => '42']);
    $config->identity = false;
    $assert($read()['hellotext']['identity'] === null);
    $config->identity = true;
    // Another customer's quote is never reported for this visitor.
    $quote->customer = 7;
    $assert($read()['hellotext'] === ['enabled' => false]);
    $customer->loggedIn = false;
    $quote->customer = 42;
    $assert($read()['hellotext'] === ['enabled' => false]);
    $quote->customer = null;
    $assert($read()['hellotext']['identity'] === null);
    $quote->store = 2;
    $assert($read()['hellotext'] === ['enabled' => false]);
    $quote->store = 1;
    $config->api = 'https://api.hellotext.com/v1';
    $assert($read()['hellotext']['enabled'] === true);
    $config->api = 'http://api.lvh.me:3200/v1';
    $item = new class {
        public bool $additional = false;
        public function getProductType() { return 'simple'; }
        public function getProductId() { return 7; }
        public function getName() { return 'Native product'; }
        public function getSku() { return 'NATIVE'; }
        public function getQty() { return 2; }
        public function getCalculationPrice() { return 24.99; }
        public function getBuyRequest() { return $this; }
        public function getData($key) { return null; }
        public function getOptionByCode($code) { return $code === 'additional_options' && $this->additional; }
    };
    $quote->items = [$item];
    $assert($read()['hellotext']['items'][0]['product']['reference'] === 'magento:magento-local:product:7');
    $item->additional = true;
    $assert($read()['hellotext'] === ['enabled' => false]);
    $quote->items = [];
    $assert($checkout->data === []);
    $assert(array_keys($read()['hellotext']) === ['enabled', 'context', 'items', 'cart_ready', 'checkout_reference', 'identity']);
    $assert($read()['hellotext']['context'] === ['businessId' => 'public-business', 'apiRoot' => 'http://api.lvh.me:3200/v1', 'installationId' => 'magento-local', 'storeId' => '1', 'currency' => 'USD']);
    echo "Private cart configuration, authentication and scope checks passed.\n";
}
