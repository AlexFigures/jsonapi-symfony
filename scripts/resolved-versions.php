<?php

declare(strict_types=1);

$lock = json_decode(file_get_contents('composer.lock'), true, flags: JSON_THROW_ON_ERROR);
$versions = ['php' => PHP_VERSION];
foreach (array_merge($lock['packages'], $lock['packages-dev'] ?? []) as $package) {
    if (str_starts_with($package['name'], 'symfony/') || str_starts_with($package['name'], 'doctrine/')) {
        $versions[$package['name']] = $package['version'];
    }
}
echo json_encode($versions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
