<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model;

use InvalidArgumentException;

class Reference
{
    public static function build(string $installation, string $kind, int|string $entityId): string
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/', $installation) !== 1) {
            throw new InvalidArgumentException('Installation must be a lowercase Magento installation identifier.');
        }

        if (!in_array($kind, ['product', 'order', 'checkout', 'refund'], true)) {
            throw new InvalidArgumentException('Reference kind must be product, order, checkout or refund.');
        }

        return sprintf('magento:%s:%s:%s', $installation, $kind, self::positiveId($entityId, 'Entity ID'));
    }

    private static function positiveId(int|string $value, string $label): string
    {
        if (is_int($value)) {
            if ($value > 0) {
                return (string) $value;
            }
        } elseif (preg_match('/^\d+$/', $value) === 1) {
            $normalized = ltrim($value, '0');
            if ($normalized !== '') {
                return $normalized;
            }
        }

        throw new InvalidArgumentException("{$label} must be a positive numeric ID.");
    }
}
