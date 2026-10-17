<?php
namespace Magento\Framework\Stdlib {
    interface CookieManagerInterface { public function getCookie($name); }
}
namespace Magento\Cookie\Helper {
    class Cookie {
        public bool $denied = false;
        public function isUserNotAllowSaveCookie() { return $this->denied; }
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
    require __DIR__ . '/../Observer/RememberOrderSession.php';

    $cookies = new class implements \Magento\Framework\Stdlib\CookieManagerInterface {
        public ?string $session = null;
        public function getCookie($name) {
            if ($name !== 'hello_session') throw new \RuntimeException('Unexpected cookie');
            return $this->session;
        }
    };
    $consent = new \Magento\Cookie\Helper\Cookie();
    $observer = new \Hellotext\Tracking\Observer\RememberOrderSession($cookies, $consent);

    foreach ([null, '', str_repeat('a', 37), 'AbC123xy', '123e4567-e89b-42d3-a456-426614174000'] as $session) {
        $cookies->session = $session;
        $order = new class { public array $data = []; public function setData($key, $value) { $this->data[$key] = $value; } };
        $observer->execute(new \Magento\Framework\Event\Observer($order));
        $expected = $session !== null && $session !== '' && strlen($session) <= 36 ? ['hellotext_session' => $session] : [];
        if ($order->data !== $expected) throw new \RuntimeException('The SDK cookie was not kept on the order.');
    }

    $order->data = [];
    $consent->denied = true;
    $observer->execute(new \Magento\Framework\Event\Observer($order));
    if ($order->data !== []) throw new \RuntimeException('Cookie restrictions must prevent attribution.');

    $consent->denied = false;
    $failing = new class { public function setData($key, $value) { throw new \RuntimeException('save failed'); } };
    $observer->execute(new \Magento\Framework\Event\Observer($failing));
    $observer->execute(new \Magento\Framework\Event\Observer(null));

    echo "Order placement session checks passed.\n";
}
