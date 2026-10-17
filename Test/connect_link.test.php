<?php
declare(strict_types=1);

require __DIR__ . '/../Model/ConnectLink.php';

use Hellotext\Tracking\Model\ConnectLink;

$code = 'eyJfcmFpbHMiOnsiZGF0YSI6WzEsMl19fQ--0123456789abcdef_-';
if (!ConnectLink::isValidCode($code)) throw new RuntimeException('Expected a Hellotext link code to be accepted');
foreach (['', 'short', "abc'; rm -rf /", str_repeat('a', 513), "line\nbreak" . str_repeat('a', 30)] as $bad) {
    if (ConnectLink::isValidCode($bad)) throw new RuntimeException('Expected an invalid link code to be rejected');
}

$fields = ConnectLink::fields('key', 'secret', 'https://shop.example/', 'verifier', $code);
if (array_keys($fields) !== ['oauth_consumer_key', 'oauth_consumer_secret', 'store_base_url', 'oauth_verifier', 'connect_code']) {
    throw new RuntimeException('Expected the native Activate form plus the link code');
}

if (ConnectLink::checkFields('https://shop.example/', $code) !== ['store_base_url' => 'https://shop.example/', 'connect_code' => $code]) {
    throw new RuntimeException('Expected the code check to carry no credentials');
}

foreach ([200 => 'linked', 204 => 'linked', 409 => 'refused', 429 => 'rate_limited', 500 => 'unreachable', 0 => 'unreachable', 302 => 'unreachable'] as $status => $outcome) {
    if (ConnectLink::outcome($status) !== $outcome) throw new RuntimeException("Unexpected outcome for HTTP $status");
}

echo "connect link: ok\n";
