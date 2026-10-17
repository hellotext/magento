<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model\Data;

use Hellotext\Tracking\Api\Data\CustomerItemInterface;

class CustomerItem implements CustomerItemInterface
{
    public function __construct(
        private int $id,
        private ?string $email,
        private ?string $firstName,
        private ?string $lastName,
        private string $newsletterStatus
    ) {}

    public function getId(): int { return $this->id; }
    public function getEmail(): ?string { return $this->email; }
    public function getFirstName(): ?string { return $this->firstName; }
    public function getLastName(): ?string { return $this->lastName; }
    public function getNewsletterStatus(): string { return $this->newsletterStatus; }
}
