<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model;

/**
 * Pure parts of `bin/magento hellotext:connect`: the code Hellotext printed in its install commands, the
 * form Magento's own Activate posts plus that code, and what each Hellotext answer means for the operator.
 */
class ConnectLink
{
    private const CODE = '/^[A-Za-z0-9_=-]{20,512}$/';

    public static function isValidCode(string $code): bool
    {
        return preg_match(self::CODE, $code) === 1;
    }

    /** @return array<string, string> */
    public static function fields(string $consumerKey, string $consumerSecret, string $storeBaseUrl, string $verifier, string $code): array
    {
        return [
            'oauth_consumer_key' => $consumerKey,
            'oauth_consumer_secret' => $consumerSecret,
            'store_base_url' => $storeBaseUrl,
            'oauth_verifier' => $verifier,
            'connect_code' => $code,
        ];
    }

    /**
     * Posted before anything is revoked: Hellotext only answers whether the code is still valid.
     *
     * @return array<string, string>
     */
    public static function checkFields(string $storeBaseUrl, string $code): array
    {
        return ['store_base_url' => $storeBaseUrl, 'connect_code' => $code];
    }

    public static function outcome(int $status): string
    {
        return match (true) {
            $status >= 200 && $status < 300 => 'linked',
            $status === 409 => 'refused',
            $status === 429 => 'rate_limited',
            default => 'unreachable',
        };
    }
}
