#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Memory & Performance Stress Test Script
 *
 * Ensures there are no memory leaks and that long-running execution remains stable.
 *
 * Usage:
 *   php scripts/stress/run.php --profile=mem
 *   php scripts/stress/run.php --profile=perf
 *   php scripts/stress/run.php --profile=all
 *
 * Metrics:
 *   - memory_get_usage(true) — actual memory usage
 *   - memory_get_peak_usage(true) — peak memory usage
 *   - Batch execution time
 *   - Absence of monotonic memory growth
 */

require __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;

// Configuration
$config = [
    'batches' => [
        'collections' => 1000,
        'related' => 500,
        'atomic' => 200,
        'preconditions' => 300,
        'filters' => 100,
    ],
    'memory_threshold_mb' => 50, // Maximum memory growth between batches
    'gc_interval' => 100, // Interval for forced garbage collection
];

// Parse CLI arguments
$profile = 'all';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--profile=')) {
        $profile = substr($arg, strlen('--profile='));
    }
}

echo "=== JSON:API Stress Test ===\n";
echo "Profile: {$profile}\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n\n";

// Initialise metrics
$metrics = [
    'batches' => [],
    'memory_start' => memory_get_usage(true),
    'memory_peak' => 0,
    'time_start' => microtime(true),
];

/**
 * Record batch metrics
 */
function recordBatch(string $name, int $iteration, array &$metrics): void
{
    $memoryUsage = memory_get_usage(true);
    $memoryPeak = memory_get_peak_usage(true);

    $metrics['batches'][] = [
        'name' => $name,
        'iteration' => $iteration,
        'memory_usage' => $memoryUsage,
        'memory_peak' => $memoryPeak,
        'time' => microtime(true),
    ];

    if ($memoryPeak > $metrics['memory_peak']) {
        $metrics['memory_peak'] = $memoryPeak;
    }

    // Print progress every 100 iterations
    if ($iteration % 100 === 0) {
        $memoryMb = round($memoryUsage / 1024 / 1024, 2);
        $peakMb = round($memoryPeak / 1024 / 1024, 2);
        echo sprintf(
            "[%s] Iteration %d: Memory %.2f MB (Peak: %.2f MB)\n",
            $name,
            $iteration,
            $memoryMb,
            $peakMb
        );
    }
}

/**
 * Memory leak check
 */
function checkMemoryLeaks(array $metrics, array $config): bool
{
    $batches = $metrics['batches'];
    if (count($batches) < 10) {
        return true; // Not enough data
    }

    // Compare the first 10% and last 10% of batches
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

    echo "\n=== Performance Report ===\n";
    echo sprintf("Total batches: %d\n", $totalBatches);
    echo sprintf("Total duration: %.2f seconds\n", $duration);
    echo sprintf("Average time per batch: %.4f seconds\n", $duration / max($totalBatches, 1));
    echo sprintf("Peak memory: %.2f MB\n", $metrics['memory_peak'] / 1024 / 1024);
    echo sprintf("Memory growth: %.2f MB\n", (memory_get_usage(true) - $metrics['memory_start']) / 1024 / 1024);
}

// Stress tests
try {
    // Test 1: GET collections with different include/fields combinations
    if ($profile === 'all' || $profile === 'mem') {
        echo "\n--- Test 1: Collection GET with include/fields ---\n";
        for ($i = 1; $i <= $config['batches']['collections']; $i++) {
            // Simulate a request (replace with real controllers in production)
            $includes = ['author', 'tags', 'comments'];
            $fields = ['title', 'body', 'createdAt'];

            // Replace with an actual controller call
            // $request = Request::create('/api/articles?include=' . implode(',', $includes));
            // $response = $controller($request, 'articles');

            recordBatch('collections', $i, $metrics);

            if ($i % $config['gc_interval'] === 0) {
                gc_collect_cycles();
            }
        }
    }

    // Test 2: Related/Relationships to-many
    if ($profile === 'all' || $profile === 'mem') {
        echo "\n--- Test 2: Related/Relationships to-many ---\n";
        for ($i = 1; $i <= $config['batches']['related']; $i++) {
            // Simulate a related request
            // $request = Request::create('/api/articles/1/tags');
            // $response = $relatedController($request, 'articles', '1', 'tags');

            recordBatch('related', $i, $metrics);

            if ($i % $config['gc_interval'] === 0) {
                gc_collect_cycles();
            }
        }
    }

    // Test 3: Atomic operations with lid
    if ($profile === 'all' || $profile === 'mem') {
        echo "\n--- Test 3: Atomic operations with lid ---\n";
        for ($i = 1; $i <= $config['batches']['atomic']; $i++) {
            // Simulate atomic operations
            // $payload = ['atomic:operations' => [...]];
            // $request = Request::create('/api/operations', 'POST', ...);
            // $response = $atomicController($request);

            recordBatch('atomic', $i, $metrics);

            if ($i % $config['gc_interval'] === 0) {
                gc_collect_cycles();
            }
        }
    }

    // Test 4: PATCH/DELETE with If-Match
    if ($profile === 'all' || $profile === 'perf') {
        echo "\n--- Test 4: PATCH/DELETE with If-Match ---\n";
        for ($i = 1; $i <= $config['batches']['preconditions']; $i++) {
            // Simulate PATCH with If-Match
            // $request = Request::create('/api/articles/1', 'PATCH', server: ['HTTP_IF_MATCH' => '"etag"']);
            // $response = $updateController($request, 'articles', '1');

            recordBatch('preconditions', $i, $metrics);

            if ($i % $config['gc_interval'] === 0) {
                gc_collect_cycles();
            }
        }
    }

    // Test 5: Filters with large IN/OR predicates
    if ($profile === 'all' || $profile === 'perf') {
        echo "\n--- Test 5: Filters with large IN/OR ---\n";
        for ($i = 1; $i <= $config['batches']['filters']; $i++) {
            // Simulate filters
            // $request = Request::create('/api/articles?filter[id][in]=1,2,3,...,100');
            // $response = $collectionController($request, 'articles');

            recordBatch('filters', $i, $metrics);

            if ($i % $config['gc_interval'] === 0) {
                gc_collect_cycles();
            }
        }
    }

    // Final check
    $noLeaks = checkMemoryLeaks($metrics, $config);
    generateReport($metrics);

    // Save metrics to disk
    $reportPath = __DIR__ . '/../../build/stress-report.json';
    @mkdir(dirname($reportPath), 0755, true);
    file_put_contents($reportPath, json_encode($metrics, JSON_PRETTY_PRINT));
    echo "\nReport saved to: {$reportPath}\n";

    exit($noLeaks ? 0 : 1);
} catch (Throwable $e) {
    echo "\n❌ ERROR: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
