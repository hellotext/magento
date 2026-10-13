<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Api\Data;

interface CustomerItemInterface
{
    /** @return int */
    public function getId(): int;
    /** @return string|null */
    public function getEmail(): ?string;
    /** @return string|null */
    public function getFirstName(): ?string;
    /** @return string|null */
    public function getLastName(): ?string;
    /** @return string */
    public function getNewsletterStatus(): string;
}
