<?php
declare(strict_types=1);

namespace Magento\Framework\App\Config {
    interface ScopeConfigInterface { public function getValue($path, $scopeType = 'default', $scopeCode = null); }
}
namespace Magento\Framework\Exception {
    class LocalizedException extends \Exception {}
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
namespace Magento\Newsletter\Model {
    class Subscriber { public const STATUS_SUBSCRIBED = 1; public const STATUS_UNSUBSCRIBED = 3; }
}
namespace Magento\Customer\Model\ResourceModel\Customer {
    class CollectionFactory {
        public function __construct(private array $records) {}
        public function create(): object {
            return new class($this->records) {
                private array $filters = [];
                private int $limit = 0;
                public function __construct(private array $records) {}
                public function addFieldToSelect($fields): self { return $this; }
                public function addFieldToFilter($field, $filter): self { $this->filters[$field] = $filter; return $this; }
                public function setOrder($field, $direction): self { return $this; }
                public function setPageSize($limit): self { $this->limit = $limit; return $this; }
                public function setCurPage($page): self { return $this; }
                public function getItems(): array {
                    $rows = array_values(array_filter($this->records, function ($row): bool {
                        foreach ($this->filters as $field => $filter) {
                            if ($field === 'website_id' && $row['website_id'] !== $filter['eq'] ||
                                $field === 'entity_id' && $row['entity_id'] <= $filter['gt']) return false;
                        }
                        return true;
                    }));
                    usort($rows, static fn ($a, $b) => $a['entity_id'] <=> $b['entity_id']);
                    return array_map(static fn ($row) => new class($row) {
                        public function __construct(private array $row) {}
                        public function getId(): int { return $this->row['entity_id']; }
                        public function getEmail(): string { return $this->row['email']; }
                        public function getFirstname(): string { return $this->row['firstname']; }
                        public function getLastname(): string { return $this->row['lastname']; }
                    }, array_slice($rows, 0, $this->limit));
                }
            };
        }
    }
}
namespace Magento\Newsletter\Model\ResourceModel\Subscriber {
    class CollectionFactory {
        public array $filters = [];
        public function __construct(private array $records) {}
        public function create(): object {
            $factory = $this;
            return new class($this->records, $factory) implements \IteratorAggregate {
                private array $filters = [];
                public function __construct(private array $records, private CollectionFactory $factory) {}
                public function addFieldToFilter($field, $filter): self { $this->filters[$field] = $filter; $this->factory->filters = $this->filters; return $this; }
                public function getIterator(): \Traversable {
                    foreach ($this->records as $row) {
                        if (isset($this->filters['customer_id']) && !in_array($row['customer_id'], $this->filters['customer_id']['in'], true)) continue;
                        if (isset($this->filters['store_id']) && !in_array($row['store_id'], $this->filters['store_id']['in'], true)) continue;
                        yield new class($row) {
                            public function __construct(private array $row) {}
                            public function getCustomerId(): int { return $this->row['customer_id']; }
                            public function getSubscriberStatus(): int { return $this->row['status']; }
                        };
                    }
                }
            };
        }
    }
}
namespace {
    function __($text) { return $text; }
    spl_autoload_register(function (string $class): void {
        $prefix = 'Hellotext\\Tracking\\';
        if (str_starts_with($class, $prefix)) require __DIR__ . '/../' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    });

    $customers = new \Magento\Customer\Model\ResourceModel\Customer\CollectionFactory([
        ['entity_id' => 1, 'website_id' => 3, 'email' => 'one@example.com', 'firstname' => 'One', 'lastname' => 'Customer'],
        ['entity_id' => 2, 'website_id' => 3, 'email' => 'two@example.com', 'firstname' => 'Two', 'lastname' => 'Customer'],
        ['entity_id' => 3, 'website_id' => 3, 'email' => 'three@example.com', 'firstname' => 'Three', 'lastname' => 'Customer'],
        ['entity_id' => 4, 'website_id' => 4, 'email' => 'other@example.com', 'firstname' => 'Other', 'lastname' => 'Website'],
    ]);
    $subscribers = new \Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory([
        ['customer_id' => 1, 'store_id' => 1, 'status' => 1], ['customer_id' => 1, 'store_id' => 2, 'status' => 3],
        ['customer_id' => 2, 'store_id' => 1, 'status' => 1], ['customer_id' => 2, 'store_id' => 2, 'status' => 4],
        ['customer_id' => 3, 'store_id' => 2, 'status' => 4], ['customer_id' => 4, 'store_id' => 3, 'status' => 3],
    ]);
    $config = new class implements \Magento\Framework\App\Config\ScopeConfigInterface {
        public function getValue($path, $scopeType = 'default', $scopeCode = null) {
            return str_ends_with($path, 'installation_id') ? 'magento-local' : 'Business1';
        }
    };
    $stores = new class implements \Magento\Store\Model\StoreManagerInterface {
        public function getStore($storeId = null) { return new class { public function getId(): int { return 1; } public function getWebsiteId(): int { return 3; } }; }
        public function getStores() { return array_map(static fn (int $id) => new class($id) { public function __construct(private int $id) {} public function getId(): int { return $this->id; } public function getWebsiteId(): int { return $this->id === 3 ? 4 : 3; } }, [1, 2, 3]); }
    };
    $response = new \Magento\Framework\Webapi\Rest\Response();
    $management = new \Hellotext\Tracking\Model\CustomerManagement($customers, $subscribers, $config, $stores, $response);
    $page = $management->getCustomers(100);
    $items = $page->getItems();
    if ($page->getStoreId() !== 1 || $page->getWebsiteId() !== 3 || count($items) !== 3 ||
        $items[0]->getNewsletterStatus() !== 'unsubscribed' || $items[1]->getNewsletterStatus() !== 'subscribed' ||
        $items[2]->getNewsletterStatus() !== 'unconfirmed' || $subscribers->filters['store_id'] !== ['in' => [1, 2]] ||
        !str_contains($response->headers['Cache-Control'], 'no-store')) {
        throw new \RuntimeException('Website-wide customer import or newsletter precedence failed.');
    }
    try { $management->getCustomers(101); } catch (\Magento\Framework\Exception\LocalizedException) { echo "Website customer scope and consent checks passed.\n"; return; }
    throw new \RuntimeException('Invalid customer page limit was accepted.');
}
