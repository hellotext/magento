<?php

namespace Magento\Catalog\Block\Product {
    class View {}
}
namespace Magento\Catalog\Model\Product\Attribute\Source {
    class Status { public const STATUS_ENABLED = 1; }
}
namespace Magento\ConfigurableProduct\Model\Product\Type {
    class Configurable { public const TYPE_CODE = 'configurable'; }
}
namespace {
    require __DIR__ . '/../Block/Product.php';

    class Variant {
        public function __construct(private int $id, private int $status) {}
        public function getId() { return $this->id; }
        public function getStatus() { return $this->status; }
        public function getData($key) { return $this->id; }
    }
    $attribute = new class {
        public function getProductAttribute() { return $this; }
        public function getAttributeId() { return 93; }
        public function getAttributeCode() { return 'color'; }
    };
    $product = new class($attribute) {
        public function __construct(private object $attribute) {}
        public function getTypeId() { return 'configurable'; }
        public function getTypeInstance() { return $this; }
        public function getConfigurableAttributes($product) { return [$this->attribute]; }
        public function getUsedProducts($product) { return [new Variant(1, 1), new Variant(2, 2)]; }
    };
    $block = new class($product) extends \Hellotext\Tracking\Block\Product {
        public function __construct(private object $product) {}
        public function getProduct() { return $this->product; }
        public function getProductPayload($product = null): ?array { return ['reference' => $product->getId()]; }
    };
    if ($block->getVariantPayloads() !== ['93:1' => ['reference' => 1]]) {
        throw new \RuntimeException('Only enabled configurable children may be rendered.');
    }
    $bundle = new class { public function getTypeId() { return 'bundle'; } };
    $real = new class extends \Hellotext\Tracking\Block\Product { public function __construct() {} };
    if ($real->getProductPayload($bundle) !== null) {
        throw new \RuntimeException('Product types outside the catalog import must not be tracked.');
    }
    echo "Product variant visibility checks passed.\n";
}
