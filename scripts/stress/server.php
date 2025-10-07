#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Built-in PHP Server Starter for Stress Tests
 * 
 * Starts a PHP built-in server for the stress test application.
 * 
 * Usage:
 *   php scripts/stress/server.php [port]
 * 
 * Default port: 8765
 */

$port = (int) ($argv[1] ?? 8765);
$host = '127.0.0.1';
$router = __DIR__ . '/router.php';

if (!file_exists($router)) {
    fwrite(STDERR, "Error: Router file not found: $router\n");
    exit(1);
}

echo "🚀 Starting stress test server on http://$host:$port\n";
echo "📊 Dataset: 1000 Articles, 100 Authors, 500 Tags\n";
echo "🔧 Press Ctrl+C to stop\n\n";

// Start the server
$command = sprintf(
    'php -S %s:%d %s',
    escapeshellarg($host),
    $port,
    escapeshellarg($router)
);

passthru($command, $exitCode);
exit($exitCode);

