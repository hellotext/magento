<?php
declare(strict_types=1);

namespace Magento\Framework\App\Config {
    interface ScopeConfigInterface {
        public function isSetFlag(string $path, string $scope, int $scopeId): bool;
        public function getValue(string $path, ?string $scope = null, ?int $scopeId = null): string;
    }
}

namespace Magento\Framework\HTTP\Client {
    class Curl {
        public array $headers = [];
        public array $options = [];
        public ?int $timeout = null;
        public ?string $url = null;
        public ?string $body = null;
        public bool $fail = false;
        public function addHeader(string $name, string $value): void { $this->headers[$name] = $value; }
        public function setOption(int $option, int $value): void { $this->options[$option] = $value; }
        public function setTimeout(int $timeout): void { $this->timeout = $timeout; }
        public function post(string $url, string $body): void {
            if ($this->fail) throw new \RuntimeException('network unavailable');
            $this->url = $url;
            $this->body = $body;
        }
    }
    class CurlFactory {
        public array $clients = [];
        public bool $fail = false;
        public function create(): Curl {
            $client = new Curl();
            $client->fail = $this->fail;
            return $this->clients[] = $client;
        }
    }
}

namespace Magento\Store\Model { interface ScopeInterface { public const SCOPE_STORE = 'store'; } }

namespace Magento\Framework\App {
    class Area { public const AREA_ADMINHTML = 'adminhtml'; }
    class State {
        public ?string $area = 'adminhtml';
        public function getAreaCode(): string {
            if ($this->area === null) throw new \RuntimeException('Area code is not set');
            return $this->area;
        }
    }
}

namespace Magento\Framework\Event {
    class Observer { public function __construct(private object $event) {} public function getEvent(): object { return $this->event; } }
    interface ObserverInterface { public function execute(Observer $observer): void; }
}

namespace {
    require __DIR__ . '/../Model/Configuration.php';
    require __DIR__ . '/../Model/Reference.php';
    require __DIR__ . '/../Model/OrderLifecycleTracker.php';
    require __DIR__ . '/../Observer/TrackOrderLifecycle.php';

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new \RuntimeException($message);
    };
    $config = new class implements \Magento\Framework\App\Config\ScopeConfigInterface {
        public bool $enabled = true;
        public string $api = 'http://api.lvh.me:3200/v1/';
        public string $business = 'public-business';
        public function isSetFlag(string $path, string $scope, int $scopeId): bool { return $this->enabled; }
        public function getValue(string $path, ?string $scope = null, ?int $scopeId = null): string {
            return match ($path) {
                'hellotext/tracking/installation_id' => 'magento-local',
                'hellotext/tracking/business_id' => $this->business,
                default => $this->api,
            };
        }
    };
    $curls = new \Magento\Framework\HTTP\Client\CurlFactory();
    $appState = new \Magento\Framework\App\State();
    $tracker = new \Hellotext\Tracking\Model\OrderLifecycleTracker($curls, $config, $appState);
    $order = new class {
        public string $state = 'new';
        public int $store = 1;
        public int $id = 21;
        public bool $stateChanged = true;
        public bool $refundChanged = false;
        public float $refunded = 0;
        public function getState(): string { return $this->state; }
        public function getStoreId(): int { return $this->store; }
        public function getEntityId(): int { return $this->id; }
        public function dataHasChangedFor(string $field): bool {
            return $field === 'state' ? $this->stateChanged : ($field === 'total_refunded' && $this->refundChanged);
        }
        public function getTotalRefunded(): float { return $this->refunded; }
        public ?string $session = null;
        public bool $loaded = true;
        public function getData(string $key) { return $key === 'hellotext_session' ? $this->session : null; }
        public function getOrigData(string $key) { return $this->loaded && $key === 'entity_id' ? $this->id : null; }
    };

    $sessions = [];
    foreach ([
        'new' => 'order.placed', 'pending_payment' => 'order.placed', 'payment_review' => 'order.placed',
        'holded' => 'order.placed', 'processing' => 'order.confirmed', 'complete' => 'order.shipped',
        'canceled' => 'order.cancelled',
    ] as $state => $action) {
        $order->state = $state;
        $tracker->track($order);
        $curl = $curls->clients[array_key_last($curls->clients)];
        $payload = json_decode((string) $curl->body, true, flags: JSON_THROW_ON_ERROR);
        $assert($payload['action'] === $action, "Unexpected action for {$state}");
        $assert($payload['object_parameters'] === ['reference' => 'magento:magento-local:order:21', 'source' => 'magento'], "Unexpected payload for {$state}");
        $assert(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $payload['session']) === 1, 'Lifecycle tracking session must be a deterministic UUID v5.');
        $sessions[] = $payload['session'];
        $assert($curl->url === 'http://api.lvh.me:3200/v1/track/events', 'Tracking endpoint was unsafe or malformed.');
        $assert($curl->headers === ['Authorization' => 'Bearer public-business', 'Content-Type' => 'application/json'], 'Tracking headers changed.');
        $assert($curl->timeout === 2, 'Tracking request must have a short total timeout.');
    }
    $assert(count(array_unique($sessions)) === 1, 'One order must reuse one server tracking session across lifecycle changes.');
    $order->session = '123e4567-e89b-42d3-a456-426614174000';
    $tracker->track($order);
    $placed = json_decode((string) $curls->clients[array_key_last($curls->clients)]->body, true, flags: JSON_THROW_ON_ERROR);
    $assert($placed['session'] === $order->session, 'An order placed from the storefront must reuse its placing session.');
    $order->session = null;

    $before = count($curls->clients);
    foreach (['closed', 'pending', 'fraud'] as $state) {
        $order->state = $state;
        $tracker->track($order);
    }
    $assert(count($curls->clients) === $before, 'Unsupported states, including closed, must not emit lifecycle events.');

    $config->business = '';
    $order->state = 'processing';
    $tracker->track($order);
    $assert(count($curls->clients) === $before, 'A store without a connected business must not call the API.');
    $config->business = 'public-business';
    $config->api = 'https://example.test/v1';
    $tracker->track($order);
    $assert(count($curls->clients) === $before, 'An unsafe API root must not be called.');
    $config->api = 'http://api.lvh.me:3200/v1';
    $curls->fail = true;
    $tracker->track($order);
    $assert(count($curls->clients) === $before + 1, 'A network failure must be contained after one best-effort request.');

    $curls->fail = false;
    $event = new class($order) { public function __construct(private object $order) {} public function getOrder(): object { return $this->order; } };
    (new \Hellotext\Tracking\Observer\TrackOrderLifecycle($tracker))->execute(new \Magento\Framework\Event\Observer($event));
    $assert(count($curls->clients) === $before + 2, 'The commit observer did not delegate to lifecycle tracking.');
    $order->stateChanged = false;
    (new \Hellotext\Tracking\Observer\TrackOrderLifecycle($tracker))->execute(new \Magento\Framework\Event\Observer($event));
    $assert(count($curls->clients) === $before + 2, 'An unchanged order state must not call the tracking API again.');
    $order->refunded = 10;
    $order->refundChanged = true;
    (new \Hellotext\Tracking\Observer\TrackOrderLifecycle($tracker))->execute(new \Magento\Framework\Event\Observer($event));
    $refund = json_decode((string) $curls->clients[array_key_last($curls->clients)]->body, true, flags: JSON_THROW_ON_ERROR);
    $assert($refund['action'] === 'refund.received', 'A native refund must use the shared refund action.');
    $assert($refund['object_parameters'] === ['reference' => 'magento:magento-local:refund:21', 'source' => 'magento'], 'A native refund must use its canonical reference only.');
    $assert($refund['session'] === $sessions[0], 'A refund must reuse its order session.');
    $assert(count($curls->clients) === $before + 3, 'A changed refunded total must call the tracking API.');
    // An order created in this request is being placed: storefront checkout never waits on Hellotext.
    $order->refundChanged = false;
    $order->stateChanged = true;
    $order->loaded = false;
    $count = count($curls->clients);
    foreach (['frontend', 'webapi_rest', 'graphql', null] as $area) {
        $appState->area = $area;
        $tracker->track($order);
    }
    $assert(count($curls->clients) === $count, 'Checkout placement must not call the tracking API.');
    $appState->area = 'adminhtml';
    $tracker->track($order);
    $assert(count($curls->clients) === $count + 1, 'An Admin order has no success page and must be reported.');
    $order->loaded = true;
    $appState->area = 'frontend';
    $tracker->track($order);
    $assert(count($curls->clients) === $count + 2, 'A later change in any area must be reported.');

    $globalEvents = file_get_contents(__DIR__ . '/../etc/events.xml');
    $frontendEvents = file_get_contents(__DIR__ . '/../etc/frontend/events.xml');
    $assert(str_contains($globalEvents, 'sales_order_save_commit_after'), 'Order lifecycle observer must be registered globally for Admin and API changes.');
    $assert(!str_contains($frontendEvents, 'sales_order_save_commit_after'), 'Order lifecycle observer must not be limited to storefront requests.');
    echo "Order lifecycle observer checks passed.\n";
}
