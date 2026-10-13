<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model;

use Magento\Checkout\Model\Session;

class AnonymousSession
{
    public function __construct(private Session $session) {}

    public function id(array $context, ?string $quoteId = null): string
    {
        $stored = $this->session->getData('hellotext_anonymous');
        $quoteId = $this->normalizeQuoteId($quoteId);
        if (!$this->isValidStoredSession($stored, $context)) {
            $stored = $this->newStoredSession($context, $quoteId);
        } elseif ($stored['quote_id'] === null && $quoteId !== null) {
            $stored['quote_id'] = $quoteId;
        }

        $this->session->setData('hellotext_anonymous', $stored);

        return $stored['id'];
    }

    public function reset(): void
    {
        $this->session->unsetData('hellotext_anonymous');
    }

    /**
     * Returns the UUID the storefront is using without creating one, so placing
     * an order never starts tracking a shopper the storefront did not track.
     */
    public function current(): ?string
    {
        $stored = $this->session->getData('hellotext_anonymous');

        return is_array($stored) && is_array($stored['context'] ?? null) && $this->isValidStoredSession($stored, $stored['context'])
            ? $stored['id'] : null;
    }

    private function isValidStoredSession(mixed $stored, array $context): bool
    {
        return is_array($stored)
            && ($stored['context'] ?? null) === $context
            && is_string($stored['id'] ?? null)
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $stored['id']) === 1
            && array_key_exists('quote_id', $stored)
            && ($stored['quote_id'] === null
                || (is_string($stored['quote_id']) && $this->normalizeQuoteId($stored['quote_id']) === $stored['quote_id']));
    }

    private function newStoredSession(array $context, ?string $quoteId): array
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return [
            'context' => $context,
            'id' => substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20),
            'quote_id' => $quoteId,
        ];
    }

    private function normalizeQuoteId(mixed $quoteId): ?string
    {
        if ($quoteId !== null && !is_int($quoteId) && !is_string($quoteId)) {
            return null;
        }
        $quoteId = trim((string) $quoteId);
        if ($quoteId === '' || preg_match('/^[0-9]+$/', $quoteId) !== 1) {
            return null;
        }

        $quoteId = ltrim($quoteId, '0');

        return $quoteId === '' ? null : $quoteId;
    }
}
