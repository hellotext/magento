<?php

namespace Hellotext\Tracking\Block;

use Magento\Catalog\Block\Product\View;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Store\Model\ScopeInterface;
use Hellotext\Tracking\Model\Reference;

class Product extends View
{
    public function getProductPayload($product = null): ?array
    {
        $product = $product ?? $this->getProduct();
        // Only the types the catalog import maps; other types have no Hellotext product identity.
        if (!$product || !in_array((string) $product->getTypeId(), ['simple', 'configurable'], true)) {
            return null;
        }

        try {
            $reference = Reference::build(
                (string) $this->_scopeConfig->getValue('hellotext/tracking/installation_id', ScopeInterface::SCOPE_STORE, (int) $this->_storeManager->getStore()->getId()),
                'product',
                $product->getId()
            );
        } catch (\InvalidArgumentException $error) {
            return null;
        }

        return [
            'name' => (string) $product->getName(),
            'reference' => $reference,
            'sku' => (string) $product->getSku(),
            'source' => 'magento',
        ];
    }

    public function getVariantPayloads(): array
    {
        $product = $this->getProduct();
        if (!$product || $product->getTypeId() !== Configurable::TYPE_CODE) {
            return [];
        }

        $variants = [];
        $attributes = $product->getTypeInstance()->getConfigurableAttributes($product);
        foreach ($product->getTypeInstance()->getUsedProducts($product) as $variant) {
            if ((int) $variant->getStatus() !== Status::STATUS_ENABLED) {
                continue;
            }
            $selection = [];
            foreach ($attributes as $attribute) {
                $productAttribute = $attribute->getProductAttribute();
                $selection[] = sprintf(
                    '%s:%s',
                    $productAttribute->getAttributeId(),
                    $variant->getData($productAttribute->getAttributeCode())
                );
            }
            sort($selection);
            $payload = $this->getProductPayload($variant);
            if ($payload !== null) {
                $variants[implode('|', $selection)] = $payload;
            }
        }

        return $variants;
    }
}
