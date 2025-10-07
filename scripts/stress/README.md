# Stress Testing & Memory Profiling

This directory provides scripts for stress testing and memory profiling the JSON:API Bundle.

## Goals

1. **Detect memory leaks** — confirm that memory usage does not grow monotonically during long runs.
2. **Validate stability** — ensure large volumes of requests complete without crashes.
3. **Profile performance** — surface bottlenecks and slow endpoints.
4. **Exercise real HTTP requests** — cover the full request/response lifecycle through controllers.

## Architecture

### Components

1. **Stress Test Application** (`scripts/stress/app/`)
   - Minimal Symfony application using the JsonApiBundle.
   - In-memory repository seeded with 1000 Articles, 100 Authors, and 500 Tags.
   - Provides every JSON:API endpoint (collection, resource, relationships, atomic).

2. **HTTP Client** (`scripts/stress/http-client.php`)
   - Lightweight cURL-based HTTP client.
  - Supports GET, POST, PATCH, DELETE.
   - Automatically negotiates the JSON:API media type.

3. **Stress Test Runners**
   - `run.php` — legacy simulation test (deprecated).
   - `run-http.php` — HTTP-based stress test (recommended).
   - `memory-stress.php` — extended memory profiling via HTTP.

4. **Server** (`scripts/stress/server.php`)
   - Spins up the PHP built-in server for the stress test application.

## Usage

### Quick Start

```bash
# Terminal 1: start the server
php scripts/stress/server.php

# Terminal 2: run the stress suite
php scripts/stress/run-http.php --profile=all
```

### HTTP-Based Stress Tests (Recommended)

```bash
# 1. Start the server (in a separate terminal)
php scripts/stress/server.php [port]

# 2. Run the stress tests
php scripts/stress/run-http.php --profile=mem
php scripts/stress/run-http.php --profile=perf
php scripts/stress/run-http.php --profile=all

# Use a custom server URL
php scripts/stress/run-http.php --server=http://localhost:9000
```

### Memory Profiling

```bash
# 1. Start the server
php scripts/stress/server.php

# 2. Launch the memory stress test
php scripts/stress/memory-stress.php --profile=standard
php scripts/stress/memory-stress.php --profile=quick      # For CI
php scripts/stress/memory-stress.php --profile=extended   # Deep analysis
php scripts/stress/memory-stress.php --iterations=5000    # Custom run length
```

### Legacy Simulation Tests (Deprecated)

```bash
# Simulation tests without real HTTP requests
php scripts/stress/run.php --profile=mem
php scripts/stress/run.php --profile=perf
php scripts/stress/run.php --profile=all
```

## Test Scenarios

### HTTP-Based Tests (`run-http.php`, `memory-stress.php`)

#### 1. Collection GET with include/fields (1000 iterations)
**Endpoint**: `GET /api/articles?include=author,tags&fields[articles]=title`

Validates:
- No leaks when building documents with `include`.
- Correct sparse fieldset handling.
- Stability of the `DocumentBuilder`.
- Real HTTP response times.
- End-to-end request/response behaviour.

#### 2. Resource GET (500 iterations)
**Endpoint**: `GET /api/articles/{id}?include=author,tags`

Validates:
- Fetching individual resources.
- Relationship includes.
- HTTP caching headers.
- Response latency for single resources.

#### 3. Related Resources (300 iterations)
**Endpoint**: `GET /api/articles/{id}/tags`

Validates:
- Related resources endpoints.
- To-many relationship loading.
- Absence of N+1 queries.
- Correct behaviour of the `LinkageBuilder`.

#### 4. Relationships (200 iterations)
**Endpoint**: `GET /api/articles/{id}/relationships/author`

Validates:
- Relationship endpoints.
- Resource linkage documents.
- Both to-one and to-many relationships.

#### 5. Atomic Operations (100 iterations)
**Endpoint**: `POST /api/operations`

Validates:
- Transaction handling.
- Local ID resolution.
- Leak-free `LidRegistry`.
- JSON:API atomic operations extension.

#### 6. Write Operations (200 iterations)
**Endpoints**: `PATCH /api/articles/{id}`, `DELETE /api/articles/{id}`

Validates:
- PATCH/DELETE execution paths.
- Preconditions (`If-Match`).
- ETag generation and validation.
- Stability under concurrent updates.

### Legacy Simulation Tests (`run.php`)

Legacy tests simulate `Request` objects without performing real HTTP calls.
**Not recommended** for uncovering production performance issues.

## Metrics

Each script collects:

- **memory_usage** — current memory usage (bytes).
- **memory_peak** — peak memory usage.
- **time** — batch execution time.
- **growth** — difference in memory between the first and last 10% of batches.

## Success Criteria

✅ **No memory leaks:**
- Memory growth between the first and last 10% of batches remains below 50 MB.

✅ **Stability:**
- Every batch runs without exceptions.
- No monotonic memory growth.

✅ **Performance:**
- Average time per batch < 100 ms (for simple operations).
- Peak memory < 256 MB.

## Reports

### HTTP-Based Tests

Results are persisted to `build/stress-report-http.json`:

```json
{
  "batches": [
    {
      "name": "collections",
      "iteration": 1,
      "memory_usage": 12345678,
      "memory_peak": 12345678,
      "http_time": 0.0234,
      "time": 1234567890.123
    }
  ],
  "memory_start": 10000000,
  "memory_peak": 15000000,
  "time_start": 1234567890.0,
  "http_errors": 0
}
```

### Legacy Tests

Stored in `build/stress-report.json` (legacy format without `http_time`).

## CI Integration

Add to `.github/workflows/qa.yml`:

```yaml
- name: Stress Tests
  run: make stress
  continue-on-error: true  # Do not block CI when leaks are detected
```

## Profiling with Blackfire

Use Blackfire for detailed profiling:

```bash
blackfire run php scripts/stress/run.php --profile=mem
```

Or Xdebug:

```bash
php -d xdebug.mode=profile scripts/stress/run.php --profile=perf
```

## Troubleshooting

### Error: "Memory limit exceeded"

Increase the memory limit:

```bash
php -d memory_limit=512M scripts/stress/run.php
```

### False positives for leaks

Verify that:
1. The iteration count is sufficient (at least 100).
2. Xdebug is disabled (it skews measurements).
3. GC runs regularly (`gc_interval` in the configuration).

### Slow execution

Reduce the number of batches in `run.php`:

```php
$config = [
    'batches' => [
        'collections' => 100,  // instead of 1000
        'related' => 50,       // instead of 500
        // ...
    ],
];
```

## Dataset

The stress test application relies on an extended `InMemoryRepository` with a dense dataset:

- **1000 Articles** — varied authors and tags.
- **100 Authors** — distributed across articles.
- **500 Tags** — each article carries 2–5 tags.

This setup enables testing of:
- Pagination with large collections.
- Includes with multiple relationships.
- N+1 query detection.
- Memory usage for sizeable response documents.

## Future Improvements

- [x] Integrate with real controllers over HTTP.
- [x] Provide a large dataset for realistic workloads.
- [ ] Add SQL query profiling (Doctrine adapter).
- [ ] Visualise memory graphs.
- [ ] Automate comparisons against a baseline.
- [ ] Integrate with php-meminfo for retention graph analysis.
- [ ] Docker-based stress test environment.
- [ ] Continuous benchmarking in CI.
