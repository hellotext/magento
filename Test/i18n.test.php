<?php
declare(strict_types=1);

// Every translatable Admin/API string of the module needs its Spanish translation.
$root = dirname(__DIR__);
$translations = [];
foreach (file("$root/i18n/es_ES.csv", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    [$source, $target] = str_getcsv($line, ',', '"', '');
    if (trim($target) === '') throw new RuntimeException("Empty translation for: $source");
    $translations[$source] = true;
}

$strings = [];
preg_match_all('/<(?:label|comment)>([^<]+)</', file_get_contents("$root/etc/adminhtml/system.xml"), $m);
array_push($strings, ...$m[1]);
preg_match_all('/title="([^"]+)"/', file_get_contents("$root/etc/acl.xml"), $m);
array_push($strings, ...$m[1]);
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    $path = $file->getPathname();
    if (!str_ends_with($path, '.php') || preg_match('#/(Test|dev|tmp|vendor)/#', substr($path, strlen($root)))) continue;
    preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)+)'/", file_get_contents($path), $m);
    array_push($strings, ...$m[1]);
}

$missing = array_values(array_diff(array_unique($strings), array_keys($translations)));
if ($missing) throw new RuntimeException("Missing es_ES translations:\n" . implode("\n", $missing));

echo "i18n: ok (" . count(array_unique($strings)) . " strings)\n";
