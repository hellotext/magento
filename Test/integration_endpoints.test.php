<?php
declare(strict_types=1);

require __DIR__ . '/../Model/IntegrationEndpoints.php';

use Hellotext\Tracking\Model\IntegrationEndpoints;

$declared = ['Hellotext' => [
    'email' => 'info@hellotext.com',
    'endpoint_url' => 'https://www.hellotext.com/oauth/magento/callback',
    'identity_link_url' => 'https://www.hellotext.com/magento/connect',
    'resource' => ['Magento_Backend::admin'],
], 'Other' => ['endpoint_url' => 'https://other.example/callback']];

if (IntegrationEndpoints::apply($declared, null) !== $declared) throw new RuntimeException('No override must keep production URLs');

$local = IntegrationEndpoints::apply($declared, [
    'endpoint_url' => 'https://callback.magento.lvh.me:3443/oauth/magento/callback',
    'identity_link_url' => 'http://localhost:3200/magento/connect',
]);
if ($local['Hellotext']['endpoint_url'] !== 'https://callback.magento.lvh.me:3443/oauth/magento/callback' ||
    $local['Hellotext']['identity_link_url'] !== 'http://localhost:3200/magento/connect' ||
    $local['Hellotext']['resource'] !== ['Magento_Backend::admin'] || $local['Other'] !== $declared['Other']) {
    throw new RuntimeException('Expected only the Hellotext URLs to be overridden');
}

foreach ([
    'http://staging.example.com/oauth/magento/callback',   // secrets over plain HTTP
    'https://user:pass@example.com/callback',
    'https://example.com/callback?next=https://evil.test',
    'https://example.com/callback#fragment',
    'javascript:alert(1)',
    '/relative/callback',
] as $url) {
    $result = IntegrationEndpoints::apply($declared, ['endpoint_url' => $url]);
    if ($result['Hellotext']['endpoint_url'] !== $declared['Hellotext']['endpoint_url']) {
        throw new RuntimeException("Expected unsafe override to be ignored: $url");
    }
}

if (IntegrationEndpoints::apply(['Other' => []], ['endpoint_url' => 'https://x.test/cb']) !== ['Other' => []]) {
    throw new RuntimeException('Expected no change when the Hellotext integration is not declared');
}

echo "integration endpoints: ok\n";
