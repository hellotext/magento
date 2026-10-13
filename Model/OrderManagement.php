<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model;

use Hellotext\Tracking\Api\OrderManagementInterface;
use Hellotext\Tracking\Api\Data\OrderPageInterface;
use Hellotext\Tracking\Model\Data\OrderPage;
use Hellotext\Tracking\Model\Data\OrderSnapshot;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/** Reads native order snapshots without modifying checkout. */
class OrderManagement implements OrderManagementInterface
{
    // Keep headroom below the Rails client's 1 MiB response limit, counting
    // default Unicode escaping as Magento's JSON serializer does.
    private const MAX_PAGE_BYTES = 768 * 1024;
    private const JSON_FLAGS = JSON_THROW_ON_ERROR;

    public function __construct(
        private CollectionFactory $orders,
        private ScopeConfigInterface $config,
        private StoreManagerInterface $stores,
        private Response $response,
        private LoggerInterface $logger
    ) {}

    public function getOrders(string $beforeCreatedAt, int $limit = 50, int $afterId = 0): OrderPageInterface
    {
        $scope = $this->scope();
        if ($limit < 1 || $limit > 100 || $afterId < 0 || !self::validTimestamp($beforeCreatedAt)) {
            throw new LocalizedException(__('Order cutoff, cursor or limit is invalid.'));
        }

        $records = array_values($this->orders->create()
            ->addFieldToFilter('store_id', ['in' => $scope['store_ids']])
            ->addFieldToFilter('entity_id', ['gt' => $afterId])
            ->addFieldToFilter('created_at', ['lt' => $beforeCreatedAt])
            ->setOrder('entity_id', 'ASC')->setPageSize($limit + 1)->setCurPage(1)->getItems());
        $hasMore = count($records) > $limit;
        $records = array_slice($records, 0, $limit);
        $items = [];
        $overhead = self::pageOverhead($scope);
        $pageBytes = $overhead;
        $nextCursor = $afterId;
        foreach ($records as $order) {
            try {
                $item = $this->snapshot($order, $scope);
                $itemBytes = self::snapshotBytes($item);
                if ($overhead + $itemBytes > self::MAX_PAGE_BYTES) {
                    throw new \InvalidArgumentException('unsupported_snapshot_size');
                }
                $additionalBytes = $itemBytes + ($items === [] ? 0 : 1);
                if ($pageBytes + $additionalBytes > self::MAX_PAGE_BYTES) {
                    // This supported order fits on an empty page. Leave its ID
                    // unconsumed so the next request returns it in full.
                    $hasMore = true;
                    break;
                }
                $items[] = $item;
                $pageBytes += $additionalBytes;
            } catch (\InvalidArgumentException $error) {
                // Unsupported native orders must not trap the history cursor.
                // Only a numeric ID and fixed diagnostic are logged, never PII.
                $this->logger->warning('Hellotext order history skipped an unsupported snapshot.', [
                    'order_id' => (int) $order->getEntityId(),
                    'reason' => self::safeReason($error),
                ]);
            }
            $nextCursor = (int) $order->getEntityId();
        }

        return new OrderPage($scope, $items, $hasMore, $nextCursor);
    }

    public function getOrder(int $orderId): OrderPageInterface
    {
        $scope = $this->scope();
        if ($orderId <= 0) {
            throw new LocalizedException(__('Order ID is invalid.'));
        }
        $records = array_values($this->orders->create()
            ->addFieldToFilter('store_id', ['in' => $scope['store_ids']])
            ->addFieldToFilter('entity_id', ['eq' => $orderId])
            ->setPageSize(1)->setCurPage(1)->getItems());
        if ($records === []) {
            throw new NoSuchEntityException(__('Order does not exist in this website.'));
        }
        try {
            $item = $this->snapshot($records[0], $scope);
            if (self::pageOverhead($scope) + self::snapshotBytes($item) > self::MAX_PAGE_BYTES) {
                throw new \InvalidArgumentException('unsupported_snapshot_size');
            }
        } catch (\InvalidArgumentException) {
            throw new LocalizedException(__('Order snapshot is unsupported or invalid.'));
        }

        return new OrderPage($scope, [$item], false, $orderId);
    }

    private function scope(): array
    {
        $this->response->setHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store', true);
        try {
            $scope = WebsiteScope::current($this->stores);
        } catch (\InvalidArgumentException) {
            throw new LocalizedException(__('Hellotext order configuration is invalid for this website.'));
        }
        $storeId = $scope['store_id'];
        $websiteId = $scope['website_id'];
        $installation = trim((string) $this->config->getValue('hellotext/tracking/installation_id', ScopeInterface::SCOPE_STORE, $storeId));
        $business = trim((string) $this->config->getValue('hellotext/tracking/business_id', ScopeInterface::SCOPE_STORE, $storeId));
        try {
            Reference::build($installation, 'order', 1);
        } catch (\InvalidArgumentException) {
            throw new LocalizedException(__('Hellotext order configuration is invalid for this store.'));
        }
        if ($business === '' || $websiteId <= 0) {
            throw new LocalizedException(__('Hellotext order configuration is invalid for this store.'));
        }

        return ['installation_id' => $installation, 'store_id' => $storeId, 'store_ids' => $scope['store_ids'],
            'website_id' => $websiteId, 'business_id' => $business];
    }

    private function snapshot(object $order, array $scope): OrderSnapshot
    {
        if (!in_array((int) $order->getStoreId(), $scope['store_ids'], true)) {
            throw new NoSuchEntityException(__('Order does not exist in this website.'));
        }
        $createdAt = (string) $order->getCreatedAt();
        $updatedAt = (string) $order->getUpdatedAt();
        if (!self::validTimestamp($createdAt) || !self::validTimestamp($updatedAt)) {
            throw new \InvalidArgumentException('invalid_timestamp');
        }
        $state = (string) $order->getState();
        $status = (string) $order->getStatus();
        if (!self::validState($state) || !self::validState($status)) {
            throw new \InvalidArgumentException('invalid_state');
        }
        $refunded = (string) ($order->getTotalRefunded() ?? '0');
        if (preg_match('/^\d+(?:\.\d{1,8})?$/', $refunded) !== 1) {
            throw new \InvalidArgumentException('invalid_refunded_total');
        }
        // Reuses placement's canonical physical-item mapping, including configurable child IDs.
        $payload = OrderSnapshotPayload::fromOrder($order, $scope['installation_id'], self::session($order->getData('hellotext_session')));
        if (count($payload['items']) > 500) {
            throw new \InvalidArgumentException('unsupported_item_count');
        }
        if (!self::validText($payload['increment_id']) ||
            !self::validText($payload['customer_first_name'], true) || !self::validText($payload['customer_last_name'], true) ||
            !self::validText($payload['payment_method'], true)) {
            throw new \InvalidArgumentException('invalid_text');
        }
        foreach ($payload['items'] as $item) {
            if (!self::validText($item['product']['name']) || !self::validText($item['product']['sku']) || $item['quantity'] > 2147483647) {
                throw new \InvalidArgumentException('invalid_item');
            }
        }

        return new OrderSnapshot(array_merge($payload, [
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'state' => $state,
            'status' => $status,
            'refunded_total' => $refunded,
        ]));
    }

    // The storefront session recorded at placement; anything else is dropped rather than trusted.
    private static function session(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value) === 1
            ? $value : null;
    }

    private static function validTimestamp(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) !== 1) return false;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        return $date !== false && $date->format('Y-m-d H:i:s') === $value;
    }

    private static function pageOverhead(array $scope): int
    {
        // Reserve the larger boolean and largest native integer cursor, so the
        // eventual envelope never exceeds this estimate after a page is split.
        return strlen(json_encode(array_merge(['schema_version' => 1], $scope, [
            'items' => [], 'has_more' => false, 'next_cursor' => PHP_INT_MAX,
        ]), self::JSON_FLAGS));
    }

    private static function snapshotBytes(OrderSnapshot $item): int
    {
        return strlen(json_encode($item->toArray(), self::JSON_FLAGS));
    }

    private static function validState(string $value): bool
    {
        return strlen($value) <= 64 && preg_match('/^[a-zA-Z0-9_-]+$/', $value) === 1;
    }

    private static function validText(?string $value, bool $nullable = false): bool
    {
        return $value === null ? $nullable : $value !== '' && strlen($value) <= 255 && mb_check_encoding($value, 'UTF-8') &&
            preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) !== 1;
    }

    private static function safeReason(\InvalidArgumentException $error): string
    {
        $reason = $error->getMessage();
        if (str_starts_with($reason, 'unsupported_item_type:')) return 'unsupported_item_type';
        return in_array($reason, [
            'invalid_store', 'invalid_order', 'invalid_currency', 'invalid_product', 'invalid_quantity', 'invalid_money',
            'invalid_timestamp', 'invalid_state', 'invalid_refunded_total', 'invalid_text', 'invalid_item', 'missing_items',
            'unsupported_configurable_children', 'unsupported_virtual_order', 'unsupported_virtual_item', 'unsupported_item_count',
            'unsupported_snapshot_size',
        ], true) ? $reason : 'invalid_snapshot';
    }
}
