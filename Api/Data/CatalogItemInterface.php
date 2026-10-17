<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Api\Data;

interface CatalogItemInterface
{
    /** @return int */
    public function getId(): int;
    /** @return string */
    public function getReference(): string;
    /** @return string|null */
    public function getParentReference(): ?string;
    /** @return string */
    public function getType(): string;
    /** @return string */
    public function getSku(): string;
    /** @return string */
    public function getName(): string;
    /** @return string|null */
    public function getImagePath(): ?string;
}
