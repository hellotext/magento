<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model;

use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Store\Model\ScopeInterface;

/**
 * Best-effort server-side order lifecycle tracking.
 *
 * Magento Open Source has observers rather than public order webhooks, so committed
 * order changes go to the same tracking API as Hellotext.track. Only the reference is
 * sent: Hellotext reads the order from the store's signed snapshot. A failed request
 * is ignored so analytics can never affect Magento's order persistence.
 */
class OrderLifecycleTracker
{
    private const ACTIONS = [
        'new' => 'order.placed',
        'pending_payment' => 'order.placed',
        'payment_review' => 'order.placed',
        'holded' => 'order.placed',
        'processing' => 'order.confirmed',
        'complete' => 'order.shipped',
        'canceled' => 'order.cancelled',
    ];

    public function __construct(
        private CurlFactory $curls,
        private ScopeConfigInterface $config,
        private State $appState
    ) {}

    public function track(object $order): void
    {
        try {
            $storeId = (int) $order->getStoreId();
            $orderId = (int) $order->getEntityId();
            $state = (string) $order->getState();
            if ($storeId <= 0 || $orderId <= 0 || $this->placedInCheckout($order)) {
                return;
            }

            $installation = trim((string) $this->config->getValue('hellotext/tracking/installation_id', ScopeInterface::SCOPE_STORE, $storeId));
            $business = trim((string) $this->config->getValue('hellotext/tracking/business_id', ScopeInterface::SCOPE_STORE, $storeId));
            $apiRoot = trim((string) $this->config->getValue('hellotext/tracking/api_root', ScopeInterface::SCOPE_STORE, $storeId));
            if ($business === '' || !Configuration::isSafeApiRoot($apiRoot)) {
                return;
            }

            $actions = [];
            if (!method_exists($order, 'dataHasChangedFor') || $order->dataHasChangedFor('state')) {
                $action = self::ACTIONS[$state] ?? null;
                if ($action) {
                    $actions[] = [$action, Reference::build($installation, 'order', $orderId)];
                }
            }
            $refunded = method_exists($order, 'getTotalRefunded') ? (float) $order->getTotalRefunded() : 0.0;
            if ($refunded > 0 && (!method_exists($order, 'dataHasChangedFor') || $order->dataHasChangedFor('total_refunded'))) {
                $actions[] = ['refund.received', Reference::build($installation, 'refund', $orderId)];
            }

            // State and refund events of one order share its session, so it derives from the order reference.
            $session = $this->session($order, $business, Reference::build($installation, 'order', $orderId));
            foreach ($actions as [$action, $reference]) {
                $this->post($apiRoot, $business, $action, $reference, $session);
            }
        } catch (\Throwable) {
            // An analytics failure must never affect Magento order persistence.
        }
    }

    private function post(string $apiRoot, string $business, string $action, string $reference, string $session): void
    {
        try {
            $payload = json_encode([
                'action' => $action,
                'session' => $session,
                'object_parameters' => ['reference' => $reference, 'source' => 'magento'],
            ], JSON_THROW_ON_ERROR);

            $curl = $this->curls->create();
            $curl->addHeader('Authorization', 'Bearer ' . $business);
            $curl->addHeader('Content-Type', 'application/json');
            $curl->setTimeout(2);
            if (defined('CURLOPT_CONNECTTIMEOUT')) {
                $curl->setOption(CURLOPT_CONNECTTIMEOUT, 1);
            }
            $curl->post(rtrim($apiRoot, '/') . '/track/events', $payload);
        } catch (\Throwable) {
            // An analytics failure must never affect Magento order persistence.
        }
    }

    // Magento sets an order's original data only when it loads one, so an order without it was created in
    // this request. Storefront checkout must not wait on Hellotext: its success page reports the order.
    // An Admin order has no success page, so it is reported here.
    private function placedInCheckout(object $order): bool
    {
        if (!method_exists($order, 'getOrigData') || $order->getOrigData('entity_id') !== null) {
            return false;
        }
        try {
            return $this->appState->getAreaCode() !== Area::AREA_ADMINHTML;
        } catch (\Throwable) {
            return true;
        }
    }

    public static function actionForState(string $state): ?string
    {
        return self::ACTIONS[$state] ?? null;
    }

    private function session(object $order, string $business, string $reference): string
    {
        // The tracking API requires a session. The storefront session that placed the order is
        // reused; an order placed elsewhere (Admin, API) gets one stable UUID instead of a new
        // anonymous session per change. Rails takes the attribution session from the snapshot.
        $placed = method_exists($order, 'getData') ? $order->getData('hellotext_session') : null;
        if (is_string($placed) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $placed) === 1) {
            return $placed;
        }
        $bytes = substr(hash('sha256', $business . "\0" . $reference, true), 0, 16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x50);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
