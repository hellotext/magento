<?php
namespace Magento\Checkout\Model {
    class Session {
        public array $data = [];
        public function getData($key) { return $this->data[$key] ?? null; }
        public function setData($key, $value) { $this->data[$key] = $value; }
        public function unsetData($key) { unset($this->data[$key]); }
    }
}
namespace Magento\Framework\Event {
    class Observer {
        public function __construct(private ?object $order) {}
        public function getEvent(): object { return new class($this->order) { public function __construct(private ?object $order) {} public function getOrder() { return $this->order; } }; }
    }
    interface ObserverInterface { public function execute(Observer $observer); }
}
namespace {
    require __DIR__ . '/../Model/AnonymousSession.php';
    require __DIR__ . '/../Observer/RememberOrderSession.php';

    $order = new class { public array $data = []; public function setData($key, $value) { $this->data[$key] = $value; } };
    $checkout = new \Magento\Checkout\Model\Session();
    $anonymous = new \Hellotext\Tracking\Model\AnonymousSession($checkout);
    $observer = new \Hellotext\Tracking\Observer\RememberOrderSession($anonymous);

    $observer->execute(new \Magento\Framework\Event\Observer($order));
    if ($order->data !== [] || $checkout->data !== []) throw new \RuntimeException('An untracked shopper must not get a session at placement.');

    $id = $anonymous->id(['store', '1', 'business', 'api', 'USD'], '42');
    $observer->execute(new \Magento\Framework\Event\Observer($order));
    if ($order->data !== ['hellotext_session' => $id]) throw new \RuntimeException('The storefront session was not kept on the order.');

    $failing = new class { public function setData($key, $value) { throw new \RuntimeException('save failed'); } };
    $observer->execute(new \Magento\Framework\Event\Observer($failing));
    $observer->execute(new \Magento\Framework\Event\Observer(null));

    echo "Order placement session checks passed.\n";
}
