<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model;

/**
 * Development and staging stores point the declared integration at their own Hellotext through
 * app/etc/env.php, without editing the frozen integration.xml:
 *
 *   'hellotext' => ['integration' => [
 *       'endpoint_url' => 'https://callback.example.test/oauth/magento/callback',
 *       'identity_link_url' => 'https://app.example.test/magento/connect',
 *   ]]
 */
class IntegrationEndpoints
{
    public const NAME = 'Hellotext';
    public const CONFIG_PATH = 'hellotext/integration';
    private const KEYS = ['endpoint_url', 'identity_link_url'];

    /**
     * @param array<string, mixed> $integrations Consolidated integration config, keyed by name
     * @param mixed $overrides Deployment config value at CONFIG_PATH
     */
    public static function apply(array $integrations, $overrides): array
    {
        if (!isset($integrations[self::NAME]) || !is_array($overrides)) {
            return $integrations;
        }

        foreach (self::KEYS as $key) {
            if (isset($overrides[$key]) && is_string($overrides[$key]) && self::isAllowed($overrides[$key])) {
                $integrations[self::NAME][$key] = $overrides[$key];
            }
        }

        return $integrations;
    }

    public static function isAllowed(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }
        if ($parts['scheme'] === 'https') {
            return true;
        }

        // Plain HTTP only for loopback development hosts; Magento posts OAuth secrets to this URL.
        return $parts['scheme'] === 'http' && (
            $parts['host'] === 'localhost' || $parts['host'] === 'lvh.me' || str_ends_with($parts['host'], '.lvh.me')
        );
    }
}
