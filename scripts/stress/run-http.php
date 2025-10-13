#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * HTTP-Based Memory & Performance Stress Test Script
 *
 * Tests real HTTP requests to controllers instead of simulation.
 * Requires the stress test server to be running.
 *
 * Usage:
 *   # Start server in one terminal:
 *   php scripts/stress/server.php
 *
 *   # Run stress tests in another terminal:
 *   php scripts/stress/run-http.php --profile=mem
 *   php scripts/stress/run-http.php --profile=perf
 *   php scripts/stress/run-http.php --profile=all
 *
 * Metrics:
 *   - memory_get_usage(true) — real memory usage
 *   - memory_get_peak_usage(true) — peak usage
 *   - HTTP response times
 *   - No monotonic memory growth
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/http-client.php';

use AlexFigures\Symfony\StressTest\HttpClient;

// Configuration
$config = [
    'batches' => [
        'collections' => 1000,
        'resources' => 500,
        'related' => 300,
        'relationships' => 200,
        'atomic' => 100,
        'writes' => 200,
    ],
    'memory_threshold_mb' => 50,
    'gc_interval' => 100,
    'server_url' => 'http://127.0.0.1:8765',
];

// Parse arguments
$profile = 'all';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--profile=')) {
        $profile = substr($arg, strlen('--profile='));
    }
    if (str_starts_with($arg, '--server=')) {
        $config['server_url'] = substr($arg, strlen('--server='));
    }
}

echo "=== JSON:API HTTP Stress Test ===\n";
echo "Profile: {$profile}\n";
echo "Server: {$config['server_url']}\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n\n";

// Initialize HTTP client
$client = new HttpClient($config['server_url']);

// Check if server is running
echo "Checking server availability...\n";
if (!$client->isServerRunning()) {
    fwrite(STDERR, "❌ ERROR: Server is not running at {$config['server_url']}\n");
    fwrite(STDERR, "Start the server with: php scripts/stress/server.php\n");
    exit(1);
}
echo "✅ Server is running\n\n";

// Initialize metrics
$metrics = [
    'batches' => [],
    'memory_start' => memory_get_usage(true),
    'memory_peak' => 0,
    'time_start' => microtime(true),
    'http_errors' => 0,
];

/**
 * Record batch metrics
 */
function recordBatch(string $name, int $iteration, array &$metrics, float $httpTime = 0): void
{
    $memoryUsage = memory_get_usage(true);
    $memoryPeak = memory_get_peak_usage(true);

    $metrics['batches'][] = [
        'name' => $name,
        'iteration' => $iteration,
        'memory_usage' => $memoryUsage,
        'memory_peak' => $memoryPeak,
        'http_time' => $httpTime,
        'time' => microtime(true),
    ];

    if ($memoryPeak > $metrics['memory_peak']) {
        $metrics['memory_peak'] = $memoryPeak;
    }

    // Progress output every 100 iterations
    if ($iteration % 100 === 0) {
        $memoryMb = round($memoryUsage / 1024 / 1024, 2);
        $peakMb = round($memoryPeak / 1024 / 1024, 2);
        $httpMs = round($httpTime * 1000, 2);
        echo sprintf(
            "[%s] Iteration %d: Memory %.2f MB (Peak: %.2f MB) | HTTP: %.2f ms\n",
            $name,
            $iteration,
            $memoryMb,
            $peakMb,
            $httpMs
        );
    }
}

/**
 * Check for memory leaks
 */
function checkMemoryLeaks(array $metrics, array $config): bool
{
    $batches = $metrics['batches'];
    if (count($batches) < 10) {
        return true;
    }

    $sampleSize = (int) (count($batches) * 0.1);
    $firstBatches = array_slice($batches, 0, $sampleSize);
    $lastBatches = array_slice($batches, -$sampleSize);

    $avgFirst = array_sum(array_column($firstBatches, 'memory_usage')) / count($firstBatches);
    $avgLast = array_sum(array_column($lastBatches, 'memory_usage')) / count($lastBatches);

    $growthMb = ($avgLast - $avgFirst) / 1024 / 1024;

    echo "\n=== Memory Leak Analysis ===\n";
    echo sprintf("Average memory (first 10%%): %.2f MB\n", $avgFirst / 1024 / 1024);
    echo sprintf("Average memory (last 10%%): %.2f MB\n", $avgLast / 1024 / 1024);
    echo sprintf("Growth: %.2f MB\n", $growthMb);

    if ($growthMb > $config['memory_threshold_mb']) {
        echo "❌ MEMORY LEAK DETECTED: Growth exceeds threshold ({$config['memory_threshold_mb']} MB)\n";
        return false;
    }

    echo "✅ No memory leaks detected\n";
    return true;
}

/**
 * Generate report
 */
function generateReport(array $metrics): void
{
    $duration = microtime(true) - $metrics['time_start'];
    $totalBatches = count($metrics['batches']);
    
    $httpTimes = array_column($metrics['batches'], 'http_time');
    $avgHttpTime = count($httpTimes) > 0 ? array_sum($httpTimes) / count($httpTimes) : 0;
    $maxHttpTime = count($httpTimes) > 0 ? max($httpTimes) : 0;

    echo "\n=== Performance Report ===\n";
    echo sprintf("Total batches: %d\n", $totalBatches);
    echo sprintf("Total duration: %.2f seconds\n", $duration);
    echo sprintf("Average time per batch: %.4f seconds\n", $duration / max($totalBatches, 1));
    echo sprintf("Average HTTP time: %.4f seconds\n", $avgHttpTime);
    echo sprintf("Max HTTP time: %.4f seconds\n", $maxHttpTime);
    echo sprintf("Peak memory: %.2f MB\n", $metrics['memory_peak'] / 1024 / 1024);
    echo sprintf("Memory growth: %.2f MB\n", (memory_get_usage(true) - $metrics['memory_start']) / 1024 / 1024);
    echo sprintf("HTTP errors: %d\n", $metrics['http_errors']);
}

// Stress Tests
try {
    // Test 1: GET collections with different include/fields
    if ($profile === 'all' || $profile === 'mem') {
        echo "\n--- Test 1: Collection GET with include/fields ---\n";
        for ($i = 1; $i <= $config['batches']['collections']; $i++) {
            $startTime = microtime(true);
            
            try {
                // Vary parameters for diversity
                $query = [
                    'page' => ['number' => ($i % 10) + 1, 'size' => 25],
                ];
                
                if ($i % 3 === 0) {
                    $query['include'] = 'author';
                }
                if ($i % 5 === 0) {
                    $query['include'] = 'author,tags';
                }
                if ($i % 7 === 0) {
                    $query['fields'] = ['articles' => 'title'];
                }
                
                $response = $client->get('/api/articles', $query);
                
                if ($response['status'] !== 200) {
                    $metrics['http_errors']++;
                }
            } catch (\Throwable $e) {
                $metrics['http_errors']++;
            }
            
            $httpTime = microtime(true) - $startTime;
            recordBatch('collections', $i, $metrics, $httpTime);

            if ($i % $config['gc_interval'] === 0) {
                gc_collect_cycles();
            }
        }
    }

    // Test 2: GET individual resources
    if ($profile === 'all' || $profile === 'mem') {
        echo "\n--- Test 2: Resource GET ---\n";
        for ($i = 1; $i <= $config['batches']['resources']; $i++) {
            $startTime = microtime(true);
            
            try {
                $id = (($i - 1) % 1000) + 1;
                $query = [];
                
                if ($i % 2 === 0) {
                    $query['include'] = 'author,tags';
                }
                
                $response = $client->get("/api/articles/$id", $query);
                
                if ($response['status'] !== 200) {
                    $metrics['http_errors']++;
                }
            } catch (\Throwable $e) {
                $metrics['http_errors']++;
            }
            
            $httpTime = microtime(true) - $startTime;
            recordBatch('resources', $i, $metrics, $httpTime);

            if ($i % $config['gc_interval'] === 0) {
                gc_collect_cycles();
            }
        }
    }

    // Test 3: Related resources
    if ($profile === 'all' || $profile === 'mem') {
        echo "\n--- Test 3: Related resources ---\n";
        for ($i = 1; $i <= $config['batches']['related']; $i++) {
            $startTime = microtime(true);

            try {
                $id = (($i - 1) % 1000) + 1;
                $rel = $i % 2 === 0 ? 'author' : 'tags';

                $response = $client->get("/api/articles/$id/$rel");

                if ($response['status'] !== 200) {
                    $metrics['http_errors']++;
                }
            } catch (\Throwable $e) {
                $metrics['http_errors']++;
            }

            $httpTime = microtime(true) - $startTime;
            recordBatch('related', $i, $metrics, $httpTime);

            if ($i % $config['gc_interval'] === 0) {
                gc_collect_cycles();
            }
        }
    }

    // Test 4: PATCH operations
    if ($profile === 'all') {
        echo "\n--- Test 4: PATCH operations ---\n";
        for ($i = 1; $i <= 300; $i++) {
            $startTime = microtime(true);

            try {
                $id = (($i - 1) % 1000) + 1;
                $body = json_encode([
                    'data' => [
                        'type' => 'articles',
                        'id' => (string) $id,
                        'attributes' => [
                            'title' => "Updated Article $i",
                        ],
                    ],
                ], JSON_THROW_ON_ERROR);

                $response = $client->patch("/api/articles/$id", $body);

                if ($response['status'] !== 200) {
                    $metrics['http_errors']++;
                }
            } catch (\Throwable $e) {
                $metrics['http_errors']++;
            }

            $httpTime = microtime(true) - $startTime;
            recordBatch('patch', $i, $metrics, $httpTime);

            if ($i % $config['gc_interval'] === 0) {
                gc_collect_cycles();
            }
        }
    }

    // Test 5: Atomic Operations
    if ($profile === 'all') {
        echo "\n--- Test 5: Atomic Operations ---\n";
        for ($i = 1; $i <= 200; $i++) {
            $startTime = microtime(true);

            try {
                $lid = "temp-article-$i";
                $body = json_encode([
                    'atomic:operations' => [
                        [
                            'op' => 'add',
                            'href' => '/api/articles',
                            'data' => [
                                'type' => 'articles',
                                'lid' => $lid,
                                'attributes' => [
                                    'title' => "Atomic Article $i",
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR);

                $response = $client->post('/api/operations', $body, [
                    'Content-Type' => 'application/vnd.api+json; ext="https://jsonapi.org/ext/atomic"',
                ]);

                if ($response['status'] !== 200) {
                    $metrics['http_errors']++;
                }
            } catch (\Throwable $e) {
                $metrics['http_errors']++;
            }

            $httpTime = microtime(true) - $startTime;
            recordBatch('atomic', $i, $metrics, $httpTime);

            if ($i % $config['gc_interval'] === 0) {
                gc_collect_cycles();
            }
        }
    }

    // Final checks
    $noLeaks = checkMemoryLeaks($metrics, $config);
    generateReport($metrics);

    // Save report
    $reportPath = __DIR__ . '/../../build/stress-report-http.json';
    @mkdir(dirname($reportPath), 0755, true);
    file_put_contents($reportPath, json_encode($metrics, JSON_PRETTY_PRINT));
    echo "\nReport saved to: {$reportPath}\n";

    exit($noLeaks && $metrics['http_errors'] === 0 ? 0 : 1);
} catch (Throwable $e) {
    echo "\n❌ ERROR: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

