<?php

require_once __DIR__ . '/../Model/Reference.php';

use Hellotext\Tracking\Model\Reference;

$reference = Reference::build('install-one', 'product', '042');
if ($reference !== 'magento:install-one:product:42') {
    throw new RuntimeException('References must normalize native IDs and be independent of store view.');
}
if (Reference::build('install-one', 'product', 42) !== $reference ||
    Reference::build('install-two', 'product', 42) === $reference ||
    Reference::build('install-one', 'order', 42) === $reference ||
    Reference::build('install-one', 'checkout', 42) !== 'magento:install-one:checkout:42' ||
    Reference::build('install-one', 'refund', 42) !== 'magento:install-one:refund:42') {
    throw new RuntimeException('References must be shared across store views but distinct by installation and entity kind.');
}
foreach ([['', 'product', 1], ['../bad', 'product', 1], ['INSTALL', 'product', 1], ['install-one', 'unknown', 1], ['install-one', 'product', '1.5']] as $arguments) {
    try {
        Reference::build(...$arguments);
    } catch (InvalidArgumentException $error) {
        continue;
    }
    throw new RuntimeException('Invalid reference input was accepted.');
}
echo "Installation/store reference checks passed\n";
