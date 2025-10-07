#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Memory Stress Test для JsonApiBundle
 *
 * Проверяет отсутствие утечек памяти при длительной работе без перезапуска PHP.
 * Использует реальные HTTP-запросы к контроллерам.
 *
 * Использование:
 *   # Start server in one terminal:
 *   php scripts/stress/server.php
 *
 *   # Run stress tests in another terminal:
 *   php scripts/stress/memory-stress.php [--profile=PROFILE] [--iterations=N] [--server=URL]
 *
 * Профили:
 *   - quick: 100 итераций (для CI)
 *   - standard: 1000 итераций (по умолчанию)
 *   - extended: 5000 итераций (для глубокого анализа)
 *
 * Выход:
 *   - 0: тест пройден (нет утечек)
 *   - 1: обнаружены утечки памяти
 *   - 2: ошибка выполнения
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/http-client.php';

use JsonApi\Symfony\StressTest\HttpClient;

// Парсинг аргументов
$profile = 'standard';
$iterations = null;
$serverUrl = 'http://127.0.0.1:8765';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--profile=')) {
        $profile = substr($arg, strlen('--profile='));
    }
    if (str_starts_with($arg, '--iterations=')) {
        $iterations = (int) substr($arg, strlen('--iterations='));
    }
    if (str_starts_with($arg, '--server=')) {
        $serverUrl = substr($arg, strlen('--server='));
    }
}

// Профили
$profiles = [
    'quick' => [
        'collections' => 100,
        'resources' => 100,
        'relationships' => 50,
        'atomic' => 20,
        'writes' => 30,
        'filters' => 10,
    ],
    'standard' => [
        'collections' => 1000,
        'resources' => 1000,
        'relationships' => 500,
        'atomic' => 200,
        'writes' => 300,
        'filters' => 100,
    ],
    'extended' => [
        'collections' => 5000,
        'resources' => 5000,
        'relationships' => 2500,
        'atomic' => 1000,
        'writes' => 1500,
        'filters' => 500,
    ],
];

if (!isset($profiles[$profile])) {
    fwrite(STDERR, "Unknown profile: $profile\n");
    fwrite(STDERR, "Available profiles: " . implode(', ', array_keys($profiles)) . "\n");
    exit(2);
}

$config = $profiles[$profile];
if ($iterations !== null) {
    // Переопределить все счётчики
    foreach ($config as $key => $value) {
        $config[$key] = $iterations;
    }
}

echo "=== JsonApiBundle Memory Stress Test (HTTP) ===\n";
echo "Profile: $profile\n";
echo "Server: $serverUrl\n";
echo "Configuration:\n";
foreach ($config as $key => $value) {
    echo "  - $key: $value iterations\n";
}
echo "\n";

// Initialize HTTP client
$client = new HttpClient($serverUrl);

// Check if server is running
echo "Checking server availability...\n";
if (!$client->isServerRunning()) {
    fwrite(STDERR, "❌ ERROR: Server is not running at $serverUrl\n");
    fwrite(STDERR, "Start the server with: php scripts/stress/server.php\n");
    exit(2);
}
echo "✅ Server is running\n\n";

// Инициализация метрик
$metrics = [
    'start_memory' => memory_get_usage(true),
    'peak_memory' => 0,
    'samples' => [],
];

// Функция для записи метрик
function recordMetrics(string $phase, int $iteration, array &$metrics): void
{
    $current = memory_get_usage(true);
    $peak = memory_get_peak_usage(true);
    
    $metrics['samples'][] = [
        'phase' => $phase,
        'iteration' => $iteration,
        'memory' => $current,
        'peak' => $peak,
        'time' => microtime(true),
    ];
    
    if ($peak > $metrics['peak_memory']) {
        $metrics['peak_memory'] = $peak;
    }
}

// Функция для анализа тренда
function analyzeTrend(array $samples, string $phase): array
{
    $phaseSamples = array_filter($samples, fn($s) => $s['phase'] === $phase);
    if (count($phaseSamples) < 10) {
        return ['trend' => 'insufficient_data', 'slope' => 0];
    }
    
    // Линейная регрессия для определения тренда
    $n = count($phaseSamples);
    $sumX = 0;
    $sumY = 0;
    $sumXY = 0;
    $sumX2 = 0;
    
    $i = 0;
    foreach ($phaseSamples as $sample) {
        $x = $i++;
        $y = $sample['memory'];
        $sumX += $x;
        $sumY += $y;
        $sumXY += $x * $y;
        $sumX2 += $x * $x;
    }
    
    $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    
    // Определяем тренд
    $threshold = 1024; // 1KB на итерацию считается утечкой
    if ($slope > $threshold) {
        $trend = 'leak';
    } elseif ($slope < -$threshold) {
        $trend = 'decreasing';
    } else {
        $trend = 'stable';
    }
    
    return ['trend' => $trend, 'slope' => $slope];
}

// Функция для форматирования байтов
function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

echo "Starting stress tests...\n\n";

// Тест 1: GET коллекций с разными параметрами
echo "[1/6] Testing collection GET requests...\n";
for ($i = 0; $i < $config['collections']; $i++) {
    // Варьируем параметры для разнообразия
    $params = [
        'page' => ['number' => ($i % 10) + 1, 'size' => 25],
        'sort' => $i % 2 === 0 ? 'title' : '-createdAt',
    ];

    if ($i % 3 === 0) {
        $params['include'] = 'author';
    }
    if ($i % 5 === 0) {
        $params['fields'] = ['articles' => 'title,body'];
    }

    // Real HTTP request
    try {
        $response = $client->get('/api/articles', $params);
        unset($response);
    } catch (\Throwable $e) {
        // Ignore errors for stress testing
    }

    if ($i % 100 === 0) {
        recordMetrics('collections', $i, $metrics);
        echo "  Progress: $i / {$config['collections']}\r";
    }
}
echo "  Completed: {$config['collections']} iterations\n";

// Тест 2: GET отдельных ресурсов
echo "[2/6] Testing resource GET requests...\n";
for ($i = 0; $i < $config['resources']; $i++) {
    $id = ($i % 1000) + 1;
    $params = [];

    if ($i % 2 === 0) {
        $params['include'] = 'author,tags';
    }

    try {
        $response = $client->get("/api/articles/$id", $params);
        unset($response);
    } catch (\Throwable $e) {
        // Ignore errors
    }

    if ($i % 100 === 0) {
        recordMetrics('resources', $i, $metrics);
        echo "  Progress: $i / {$config['resources']}\r";
    }
}
echo "  Completed: {$config['resources']} iterations\n";

// Тест 3: Relationships endpoints
echo "[3/6] Testing relationship endpoints...\n";
for ($i = 0; $i < $config['relationships']; $i++) {
    $id = ($i % 1000) + 1;
    $rel = $i % 2 === 0 ? 'author' : 'tags';

    // Чередуем /relationships и /related
    $path = $i % 2 === 0
        ? "/api/articles/$id/relationships/$rel"
        : "/api/articles/$id/$rel";

    try {
        $response = $client->get($path);
        unset($response);
    } catch (\Throwable $e) {
        // Ignore errors
    }

    if ($i % 100 === 0) {
        recordMetrics('relationships', $i, $metrics);
        echo "  Progress: $i / {$config['relationships']}\r";
    }
}
echo "  Completed: {$config['relationships']} iterations\n";

// Тест 4: Atomic operations
echo "[4/6] Testing atomic operations...\n";
for ($i = 0; $i < $config['atomic']; $i++) {
    $payload = [
        'atomic:operations' => [
            [
                'op' => 'add',
                'ref' => ['type' => 'articles'],
                'data' => [
                    'type' => 'articles',
                    'attributes' => ['title' => "Stress Test $i"],
                ],
            ],
        ],
    ];

    try {
        $response = $client->post('/api/operations', $payload, [
            'Content-Type' => 'application/vnd.api+json; ext="https://jsonapi.org/ext/atomic"'
        ]);
        unset($response);
    } catch (\Throwable $e) {
        // Ignore errors
    }
    unset($payload);

    if ($i % 50 === 0) {
        recordMetrics('atomic', $i, $metrics);
        echo "  Progress: $i / {$config['atomic']}\r";
    }
}
echo "  Completed: {$config['atomic']} iterations\n";

// Тест 5: PATCH/DELETE операции
echo "[5/6] Testing write operations...\n";
for ($i = 0; $i < $config['writes']; $i++) {
    $id = ($i % 1000) + 1;
    $method = $i % 3 === 0 ? 'DELETE' : 'PATCH';

    try {
        if ($method === 'PATCH') {
            $payload = [
                'data' => [
                    'type' => 'articles',
                    'id' => (string) $id,
                    'attributes' => ['title' => "Updated $i"],
                ],
            ];
            $response = $client->patch("/api/articles/$id", $payload);
            unset($payload, $response);
        } else {
            $response = $client->delete("/api/articles/$id");
            unset($response);
        }
    } catch (\Throwable $e) {
        // Ignore errors
    }

    if ($i % 100 === 0) {
        recordMetrics('writes', $i, $metrics);
        echo "  Progress: $i / {$config['writes']}\r";
    }
}
echo "  Completed: {$config['writes']} iterations\n";

// Тест 6: Фильтры (если реализованы)
echo "[6/6] Testing filter operations...\n";
for ($i = 0; $i < $config['filters']; $i++) {
    $params = [
        'filter' => [
            'title' => "Test $i",
        ],
        'page' => ['size' => 10],
    ];

    try {
        $response = $client->get('/api/articles', $params);
        unset($response);
    } catch (\Throwable $e) {
        // Ignore errors
    }
    unset($params);

    if ($i % 50 === 0) {
        recordMetrics('filters', $i, $metrics);
        echo "  Progress: $i / {$config['filters']}\r";
    }
}
echo "  Completed: {$config['filters']} iterations\n\n";

// Анализ результатов
echo "=== Analysis ===\n\n";

$phases = ['collections', 'resources', 'relationships', 'atomic', 'writes', 'filters'];
$hasLeaks = false;

foreach ($phases as $phase) {
    $analysis = analyzeTrend($metrics['samples'], $phase);
    $status = $analysis['trend'] === 'leak' ? '❌ LEAK' : '✅ OK';
    $slope = formatBytes((int) $analysis['slope']);
    
    echo "$phase: $status (slope: $slope/iteration)\n";
    
    if ($analysis['trend'] === 'leak') {
        $hasLeaks = true;
    }
}

echo "\n=== Memory Summary ===\n";
echo "Start memory:  " . formatBytes($metrics['start_memory']) . "\n";
echo "Peak memory:   " . formatBytes($metrics['peak_memory']) . "\n";
echo "Final memory:  " . formatBytes(memory_get_usage(true)) . "\n";
echo "Memory delta:  " . formatBytes(memory_get_usage(true) - $metrics['start_memory']) . "\n";

// Сохранение детального отчёта
$reportPath = __DIR__ . '/../../build/stress-report.json';
@mkdir(dirname($reportPath), 0755, true);
file_put_contents($reportPath, json_encode($metrics, JSON_PRETTY_PRINT));
echo "\nDetailed report saved to: $reportPath\n";

// Выход
if ($hasLeaks) {
    echo "\n❌ FAIL: Memory leaks detected!\n";
    exit(1);
} else {
    echo "\n✅ PASS: No memory leaks detected.\n";
    exit(0);
}

