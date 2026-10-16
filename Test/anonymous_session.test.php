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
    class Observer {}
    interface ObserverInterface { public function execute(Observer $observer); }
}
namespace {
    require __DIR__ . '/../Model/AnonymousSession.php';
    require __DIR__ . '/../Observer/ResetAnonymousSession.php';
    $checkout = new \Magento\Checkout\Model\Session();
    $session = new \Hellotext\Tracking\Model\AnonymousSession($checkout);
    $other = new \Hellotext\Tracking\Model\AnonymousSession(new \Magento\Checkout\Model\Session());
    $context = ['store', '1', 'business', 'api', 'USD'];
    $id = $session->id($context);
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id)
        || $id !== $session->id($context)
        || $id !== $session->id($context, '00042')
        || $checkout->data['hellotext_anonymous']['quote_id'] !== '42'
        || $id !== $session->id($context, '42')
        || $id === $other->id($context)
        || $id === $session->id(['store', '2', 'business', 'api', 'USD'])) {
        throw new \RuntimeException('Anonymous UUID isolation failed.');
    }

    $observed = $session->id($context, '42');
    if ($observed !== $session->id($context)
        || $session->id($context, '42') !== $session->id($context, '43')) {
        throw new \RuntimeException('Quote changes must preserve storefront attribution.');
    }

    $checkout->data['hellotext_anonymous'] = ['context' => $context, 'id' => $session->id($context, '43'), 'quote_id' => 'invalid'];
    if ($checkout->data['hellotext_anonymous']['id'] === $session->id($context, '43')) {
        throw new \RuntimeException('Corrupt quote marker did not rotate UUID.');
    }

    $checkout->data['hellotext_anonymous']['quote_id'] = ['unexpected' => 'array'];
    $beforeCorruptScalar = $checkout->data['hellotext_anonymous']['id'];
    if ($session->id($context, '43') === $beforeCorruptScalar) {
        throw new \RuntimeException('Non-scalar quote marker did not rotate UUID.');
    }

    if ((new \Hellotext\Tracking\Model\AnonymousSession(new \Magento\Checkout\Model\Session()))->current() !== null
        || $session->current() !== $session->id($context)) {
        throw new \RuntimeException('Placement must read the current UUID without creating one.');
    }

    $before = $session->id([]);
    (new \Hellotext\Tracking\Observer\ResetAnonymousSession($session))->execute(new \Magento\Framework\Event\Observer());
    if ($before === $session->id([])) { throw new \RuntimeException('Authentication boundary did not rotate UUID.'); }
    echo "Anonymous session continuity and logout rotation checks passed.\n";
}
