<?php
declare(strict_types=1);

namespace Magento\Framework\App\Config {
    interface ScopeConfigInterface { public function getValue($path, $scopeType = 'default', $scopeCode = null); }
}
namespace Magento\Framework\Exception {
    class LocalizedException extends \Exception {}
    class NoSuchEntityException extends LocalizedException {}
}
namespace Magento\Framework\Webapi\Rest {
    class Response {
        public array $headers = [];
        public function setHeader($name, $value, $replace = false) { $this->headers[$name] = $value; }
    }
}
namespace Magento\Store\Model {
    interface StoreManagerInterface { public function getStore($storeId = null); public function getStores(); }
    class ScopeInterface { public const SCOPE_STORE = 'store'; }
}
namespace Psr\Log {
    interface LoggerInterface { public function warning($message, array $context = []); }
}
namespace Magento\Sales\Model\ResourceModel\Order {
    class CollectionFactory {
        public array $queries = [];
        public function __construct(public array $records) {}
        public function create(): object {
            $collection = new class($this->records) {
                public array $filters = [];
                public int $size = 0;
                private string $field = 'entity_id';
                private string $direction = 'ASC';
                public function __construct(private array $records) {}
                public function addFieldToFilter($field, $filter): self { $this->filters[$field] = $filter; return $this; }
                public function setOrder($field, $direction): self { $this->field = $field; $this->direction = $direction; return $this; }
                public function setPageSize($size): self { $this->size = $size; return $this; }
                public function setCurPage($page): self { if ($page !== 1) throw new \RuntimeException('Expected cursor paging'); return $this; }
                public function getItems(): array {
                    $result = array_filter($this->records, function ($order): bool {
                        foreach ($this->filters as $field => $filter) {
                            $value = $order->data[$field];
                            foreach ($filter as $operator => $expected) {
                                if ($operator === 'eq' && $value !== $expected || $operator === 'in' && !in_array($value, $expected, true) || $operator === 'gt' && $value <= $expected || $operator === 'lt' && $value >= $expected ) return false;
                            }
                        }
                        return true;
                    });
                    usort($result, fn ($left, $right) => $this->direction === 'ASC' ? $left->data[$this->field] <=> $right->data[$this->field] : $right->data[$this->field] <=> $left->data[$this->field]);
                    return array_slice($result, 0, $this->size);
                }
            };
            $this->queries[] = $collection;
            return $collection;
        }
    }
}
namespace {
    function __($text) { return $text; }
    spl_autoload_register(function (string $class): void {
        $prefix = 'Hellotext\\Tracking\\';
        if (str_starts_with($class, $prefix)) require __DIR__ . '/../' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    });

    use Hellotext\Tracking\Model\OrderManagement;
    use Magento\Framework\Exception\LocalizedException;
    use Magento\Framework\Exception\NoSuchEntityException;

    final class HistoryOrder {
        public function __construct(public array $data, private bool $virtual = false) {}
        public function getStoreId(): int { return $this->data['store_id']; }
        public function getEntityId(): int { return $this->data['entity_id']; }
        public function getCreatedAt(): string { return $this->data['created_at']; }
        public function getUpdatedAt(): string { return $this->data['updated_at'] ?? $this->data['created_at']; }
        public function getState(): string { return $this->data['state'] ?? 'processing'; }
        public function getStatus(): string { return $this->data['status'] ?? 'custom_processing'; }
        public function getTotalRefunded(): ?string { return $this->data['refunded_total'] ?? null; }
        public function getIsVirtual(): bool { return $this->virtual; }
        public function getOrderCurrencyCode(): string { return 'USD'; }
        public function getAllVisibleItems(): array {
            if (isset($this->data['items'])) return $this->data['items'];
            return [new class {
                public function getProductType(): string { return 'simple'; }
                public function getIsVirtual(): bool { return false; }
                public function getProductId(): int { return 307; }
                public function getName(): string { return 'Shirt'; }
                public function getSku(): string { return 'SHIRT-1'; }
                public function getQtyOrdered(): string { return '2.000'; }
                public function getPrice(): string { return '29.990000'; }
            }];
        }
        public function getIncrementId(): string { return $this->data['increment_id'] ?? '000' . $this->getEntityId(); }
        public function getCustomerId(): int { return 42; }
        public function getCustomerFirstname(): string { return 'PrivateFirstName'; }
        public function getCustomerLastname(): string { return 'PrivateLastName'; }
        public function getGrandTotal(): string { return '65.00'; }
        public function getShippingMethod(): string { return 'flatrate_flatrate'; }
        public function getPayment(): ?object { return null; }
        public function getData($key) { return $this->data[$key] ?? null; }
    }

    function order(int $id, int $store = 1, string $created = '2020-01-01 00:00:00', bool $virtual = false, array $extra = []): HistoryOrder {
        return new HistoryOrder(array_merge(['entity_id' => $id, 'store_id' => $store, 'created_at' => $created], $extra), $virtual);
    }

    function largeItems(string $text): array {
        $item = new class($text) {
            public function __construct(private string $text) {}
            public function getProductType(): string { return 'simple'; }
            public function getIsVirtual(): bool { return false; }
            public function getProductId(): int { return 307; }
            public function getName(): string { return $this->text; }
            public function getSku(): string { return $this->text; }
            public function getQtyOrdered(): string { return '1'; }
            public function getPrice(): string { return '1.00'; }
        };
        return array_fill(0, 500, $item);
    }

    function fixture(array $records, int $store = 1, string $installation = 'magento-local', string $business = 'Business1'): array {
        $factory = new \Magento\Sales\Model\ResourceModel\Order\CollectionFactory($records);
        $config = new class($installation, $business) implements \Magento\Framework\App\Config\ScopeConfigInterface {
            public array $reads = [];
            public function __construct(private string $installation, private string $business) {}
            public function getValue($path, $scopeType = 'default', $scopeCode = null) {
                $this->reads[] = [$path, $scopeType, $scopeCode];
                return str_ends_with($path, 'installation_id') ? $this->installation : $this->business;
            }
        };
        $stores = new class($store) implements \Magento\Store\Model\StoreManagerInterface {
            public function __construct(private int $id) {}
            public function getStore($storeId = null) { return new class($this->id) { public function __construct(private int $id) {} public function getId() { return $this->id; } public function getWebsiteId() { return 3; } }; }
            public function getStores() { return array_map(static fn (int $id) => new class($id) { public function __construct(private int $id) {} public function getId() { return $this->id; } public function getWebsiteId() { return $this->id === 3 ? 4 : 3; } }, [1, 2, 3]); }
        };
        $response = new \Magento\Framework\Webapi\Rest\Response();
        $logger = new class implements \Psr\Log\LoggerInterface {
            public array $messages = [];
            public function warning($message, array $context = []) { $this->messages[] = [$message, $context]; }
        };
        return [new OrderManagement($factory, $config, $stores, $response, $logger), $factory, $response, $logger, $config];
    }

    function expectException(callable $operation, string $type): void {
        try { $operation(); } catch (\Throwable $error) {
            if ($error instanceof $type) return;
            throw $error;
        }
        throw new \RuntimeException('Expected exception: ' . $type);
    }

    // Assert the explicit DTO contract, including nested objects and required
    // nullable descriptors. Real Magento REST serialization remains its own gate.
    function wire(object $object, string $interface): array {
        $result = [];
        foreach ((new \ReflectionClass($interface))->getMethods() as $method) {
            $doc = $method->getDocComment();
            if (!preg_match('/@return\s+(\S+)/', $doc, $match)) throw new \RuntimeException('Missing serializer return descriptor');
            $type = $match[1];
            $value = $object->{$method->name}();
            if ($value === null && str_contains($type, 'null')) continue;
            if (is_object($value)) $value = wire($value, $type);
            if (is_array($value)) $value = array_map(fn ($item) => is_object($item) ? wire($item, substr($type, 0, -2)) : $item, $value);
            $key = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', substr($method->name, 3)));
            $result[$key] = $value;
        }
        return $result;
    }

    $cutoff = '2026-10-03 00:00:00';
    [$manager, $factory, $response, , $config] = fixture([
        order(4, 1, $cutoff), order(2, 2), order(3), order(1), order(5, 1, '2026-10-04 00:00:00'),
    ]);
    $page = $manager->getOrders($cutoff, 1);
    $data = wire($page, \Hellotext\Tracking\Api\Data\OrderPageInterface::class);
    if ($data['schema_version'] !== 1 || $data['store_id'] !== 1 || $data['website_id'] !== 3 || $data['business_id'] !== 'Business1' ||
        $data['installation_id'] !== 'magento-local' || $data['next_cursor'] !== 1 || $data['has_more'] !== true || count($data['items']) !== 1) throw new \RuntimeException('Invalid scoped history envelope');
    $snapshot = $data['items'][0];
    if ($snapshot['order_id'] !== 1 || $snapshot['reference'] !== 'magento:magento-local:order:1' || $snapshot['state'] !== 'processing' ||
        $snapshot['status'] !== 'custom_processing' || $snapshot['created_at'] !== '2020-01-01 00:00:00' || $snapshot['session'] !== null ||
        $snapshot['payment_method'] !== null || $snapshot['refunded_total'] !== '0' || isset($snapshot['customer_email']) ||
        $snapshot['items'][0]['price']['amount'] !== '29.990000' || $snapshot['items'][0]['product']['reference'] !== 'magento:magento-local:product:307') throw new \RuntimeException('Snapshot DTO lost canonical mapping or nullable fields');
    if ($factory->queries[0]->size !== 2 || $factory->queries[0]->filters['store_id'] !== ['in' => [1, 2]] ||
        !in_array(['hellotext/tracking/business_id', 'store', 1], $config->reads, true) || !str_contains($response->headers['Cache-Control'], 'no-store')) throw new \RuntimeException('Scope, lookahead or cache boundary failed');

    $page = $manager->getOrders($cutoff, 1, 1);
    if (!$page->getHasMore() || $page->getNextCursor() !== 2 || $page->getItems()[0]->getOrderId() !== 2) throw new \RuntimeException('Website-wide history cursor failed');
    $page = $manager->getOrders($cutoff, 1, 2);
    if ($page->getHasMore() || $page->getNextCursor() !== 3 || $page->getItems()[0]->getOrderId() !== 3) throw new \RuntimeException('Cursor or exclusive cutoff failed');
    $page = $manager->getOrders($cutoff, 100, 3);
    if ($page->getItems() !== [] || $page->getHasMore() || $page->getNextCursor() !== 3) throw new \RuntimeException('Empty terminal cursor failed');

    [$manager, , , $logger] = fixture([order(1, virtual: true), order(2, virtual: true), order(3)]);
    $page = $manager->getOrders($cutoff, 2);
    if ($page->getItems() !== [] || !$page->getHasMore() || $page->getNextCursor() !== 2 || count($logger->messages) !== 2) throw new \RuntimeException('Unsupported page did not advance');
    $page = $manager->getOrders($cutoff, 2, $page->getNextCursor());
    if ($page->getItems()[0]->getOrderId() !== 3 || $page->getHasMore()) throw new \RuntimeException('Unsupported orders trapped later history');
    if (str_contains(json_encode($logger->messages), 'Private') || $logger->messages[0][1] !== ['order_id' => 1, 'reason' => 'unsupported_virtual_order']) throw new \RuntimeException('Unsupported diagnostics contain private data');

    [$unsupported] = fixture([order(7, virtual: true)]);
    $page = $unsupported->getOrders($cutoff);
    if ($page->getItems() !== [] || $page->getHasMore() || $page->getNextCursor() !== 7) throw new \RuntimeException('Unsupported terminal page lost scanned cursor');

    foreach ([['2026-02-30 00:00:00', 50, 0], ['2026-10-03T00:00:00Z', 50, 0], ['', 50, 0], [$cutoff, 0, 0], [$cutoff, 101, 0], [$cutoff, 50, -1]] as $arguments) {
        expectException(fn () => $manager->getOrders(...$arguments), LocalizedException::class);
    }
    foreach ([[0, 'magento-local', 'Business1'], [1, 'Invalid Key', 'Business1'], [1, 'magento-local', '']] as $scope) {
        [$invalid, $queries, $response] = fixture([], ...$scope);
        expectException(fn () => $invalid->getOrders($cutoff), LocalizedException::class);
        if ($queries->queries !== [] || !str_contains($response->headers['Cache-Control'], 'no-store')) throw new \RuntimeException('Invalid configuration queried orders or became cacheable');
    }

    [$manager] = fixture([order(1), order(2, 2), order(99, 3), order(3, virtual: true), order(4, extra: ['updated_at' => '2026-02-30 00:00:00']),
        order(5, extra: ['state' => 'closed', 'status' => 'closed', 'refunded_total' => '65.00']),
        order(6, extra: ['increment_id' => "invalid\xFF"])]);
    $page = $manager->getOrder(1);
    if ($page->getHasMore() || $page->getNextCursor() !== 1 || count($page->getItems()) !== 1) throw new \RuntimeException('Single order envelope failed');
    expectException(fn () => $manager->getOrder(0), LocalizedException::class);
    if ($manager->getOrder(2)->getItems()[0]->getOrderId() !== 2) throw new \RuntimeException('Single lookup excluded a sibling store-view order');
    expectException(fn () => $manager->getOrder(99), NoSuchEntityException::class);
    expectException(fn () => $manager->getOrder(99), NoSuchEntityException::class);
    expectException(fn () => $manager->getOrder(3), LocalizedException::class);
    expectException(fn () => $manager->getOrder(4), LocalizedException::class);
    expectException(fn () => $manager->getOrder(6), LocalizedException::class);
    $closed = $manager->getOrder(5)->getItems()[0];
    if ($closed->getState() !== 'closed' || $closed->getRefundedTotal() !== '65.00') throw new \RuntimeException('Refunded order snapshot lost native evidence');

    // A full native page can exceed the consumer's response cap even though
    // every order fits independently. Preserve each supported order on replay.
    $large = largeItems(str_repeat('界', 85)); // 255 UTF-8 bytes, 510 escaped JSON bytes per field.
    [$manager] = fixture([order(1, extra: ['items' => $large]), order(2, virtual: true),
        order(3, extra: ['items' => $large]), order(4)]);
    $page = $manager->getOrders($cutoff, 100);
    $encoded = json_encode(wire($page, \Hellotext\Tracking\Api\Data\OrderPageInterface::class), JSON_THROW_ON_ERROR);
    if (count($page->getItems()) !== 1 || !$page->getHasMore() || $page->getNextCursor() !== 2 || strlen($encoded) > 768 * 1024) throw new \RuntimeException('Large Unicode page did not split safely after its last consumed ID');
    $next = $manager->getOrders($cutoff, 100, $page->getNextCursor());
    if ($next->getHasMore() || $next->getNextCursor() !== 4 || array_map(fn ($item) => $item->getOrderId(), $next->getItems()) !== [3, 4]) throw new \RuntimeException('Byte limit dropped the next supported order');
    if (strlen(json_encode(wire($next, \Hellotext\Tracking\Api\Data\OrderPageInterface::class), JSON_THROW_ON_ERROR)) > 768 * 1024) throw new \RuntimeException('Continuation exceeded the JSON byte budget');

    // One pathological order cannot fit even alone. Skip it with a fixed safe
    // diagnostic and advance, rather than retrying an impossible response.
    [$manager, , , $logger] = fixture([order(9, extra: ['items' => largeItems(str_repeat('é', 127))]), order(10)]);
    $page = $manager->getOrders($cutoff, 1);
    if ($page->getItems() !== [] || !$page->getHasMore() || $page->getNextCursor() !== 9 ||
        $logger->messages[0][1] !== ['order_id' => 9, 'reason' => 'unsupported_snapshot_size']) throw new \RuntimeException('An oversized single snapshot trapped the cursor');
    if ($manager->getOrders($cutoff, 1, 9)->getItems()[0]->getOrderId() !== 10) throw new \RuntimeException('Oversized snapshot blocked the next supported order');
    expectException(fn () => $manager->getOrder(9), LocalizedException::class);
    if (strlen(json_encode(wire($manager->getOrder(10), \Hellotext\Tracking\Api\Data\OrderPageInterface::class), JSON_THROW_ON_ERROR)) > 768 * 1024) throw new \RuntimeException('Single lookup exceeded the JSON byte budget');

    // The session saved at placement travels with the snapshot; a malformed value never does.
    $session = '123e4567-e89b-42d3-a456-426614174000';
    [$manager] = fixture([order(1, extra: ['hellotext_session' => $session]), order(2, extra: ['hellotext_session' => 'not-a-uuid'])]);
    $items = $manager->getOrders($cutoff)->getItems();
    if ($items[0]->toArray()['session'] !== $session || $items[1]->toArray()['session'] !== null) throw new \RuntimeException('Placement session was not carried safely');

    echo "Order history management checks passed.\n";
}
