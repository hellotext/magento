<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model\Data;

use Hellotext\Tracking\Api\Data\CatalogItemInterface;

class CatalogItem implements CatalogItemInterface
{
    public function __construct(
        private int $id,
        private string $reference,
        private ?string $parentReference,
        private string $type,
        private string $sku,
        private string $name,
        private ?string $imagePath
    ) {}

    public function getId(): int { return $this->id; }
    public function getReference(): string { return $this->reference; }
    public function getParentReference(): ?string { return $this->parentReference; }
    public function getType(): string { return $this->type; }
    public function getSku(): string { return $this->sku; }
    public function getName(): string { return $this->name; }
    public function getImagePath(): ?string { return $this->imagePath; }
}
