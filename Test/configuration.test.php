<?php

require_once __DIR__ . '/../Model/Configuration.php';

use Hellotext\Tracking\Model\Configuration;

foreach ([3100, 3200] as $port) {
    foreach (['', '/'] as $suffix) {
        if (!Configuration::isSafeApiRoot("http://api.lvh.me:{$port}/v1{$suffix}")) {
            throw new RuntimeException('Expected an explicitly supported local API root');
        }
    }
}

foreach (['http://localhost:3200/v1', 'http://localhost:3200/v1/'] as $url) {
    if (Configuration::isSafeApiRoot($url)) {
        throw new RuntimeException("Expected host-only Rails URL to be rejected as an API root: $url");
    }
}

foreach (['https://api.hellotext.com/v1', 'https://api.hellotext.com/v1/'] as $url) {
    if (!Configuration::isSafeApiRoot($url)) {
        throw new RuntimeException('Expected the production API root to be supported');
    }
}

foreach ([
    'http://api.lvh.me:3200/v1?redirect=production',
    'http://api.lvh.me:3200/v1#fragment',
    'http://user@api.lvh.me:3200/v1',
    'http://user:pass@api.lvh.me:3200/v1',
    'http://api.lvh.me.:3200/v1',
    'http://api.lvh.me:3201/v1',
    'http://api.lvh.me:3200/v1//',
    '//api.lvh.me:3200/v1',
] as $url) {
    if (Configuration::isSafeApiRoot($url)) {
        throw new RuntimeException('Expected an unsafe or unsupported API root to be rejected');
    }
}

echo "API configuration checks passed\n";
