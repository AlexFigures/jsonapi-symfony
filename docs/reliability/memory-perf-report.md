# Memory & Performance Audit Report

This document provides a comprehensive analysis of memory usage, performance characteristics, and stability of JsonApiBundle under stress conditions.

---

## Executive Summary

**Status**: ⚠️ **PARTIAL** - Infrastructure exists, needs real controller integration

**Key Findings**:
- ✅ Stress test infrastructure is in place (`scripts/stress/`)
- ⚠️ Tests currently use simulation instead of real controllers
- ✅ Memory leak detection methodology is sound
- ✅ No obvious memory leaks in codebase architecture
- 🔍 Needs real-world stress testing with actual HTTP requests

**Recommendations**:
1. **HIGH**: Integrate real controllers into stress tests
2. **MEDIUM**: Add SQL query profiling
3. **MEDIUM**: Add N+1 query detection
4. **LOW**: Add Blackfire/XDebug integration examples

---

## 1. Methodology

### 1.1 Test Scenarios

The stress test suite (`scripts/stress/run.php`) covers:

| Scenario | Iterations | Purpose |
|----------|-----------|---------|
| **Collection GET** with include/fields | 1000 | Document building, include resolution, sparse fieldsets |
| **Related/Relationships** to-many | 500 | Relationship handling, linkage building |
| **Atomic Operations** with LID | 200 | Transaction handling, LID resolution |
| **PATCH/DELETE** with If-Match | 300 | Preconditions, ETag validation |
| **Filters** with large IN/OR | 100 | Complex query parsing, SQL generation |

### 1.2 Metrics Collected

- **memory_get_usage(true)** - Real memory usage (includes PHP internal allocations)
- **memory_get_peak_usage(true)** - Peak memory during execution
- **Execution time** - Per-batch timing
- **Memory growth** - Comparison between first 10% and last 10% of batches

### 1.3 Success Criteria

✅ **No Memory Leaks**:
- Memory growth between first and last 10% of batches < 50 MB
- No monotonic memory growth trend

✅ **Stability**:
- All batches execute without exceptions
- No crashes or fatal errors

✅ **Performance**:
- Average time per batch < 100ms (simple operations)
- Peak memory < 256 MB

---

## 2. Current Implementation Analysis

### 2.1 Stress Test Infrastructure

**Location**: `scripts/stress/`

**Files**:
- `run.php` - Main stress test runner (249 lines)
- `memory-stress.php` - Extended memory profiling (361 lines)
- `README.md` - Documentation

**Status**: ✅ Well-structured, ⚠️ Needs real controller integration

### 2.2 Memory Leak Detection Algorithm

```php
// Compare first 10% vs last 10% of batches
$sampleSize = (int) (count($batches) * 0.1);
$firstBatches = array_slice($batches, 0, $sampleSize);
$lastBatches = array_slice($batches, -$sampleSize);

$avgFirst = array_sum(array_column($firstBatches, 'memory_usage')) / count($firstBatches);
$avgLast = array_sum(array_column($lastBatches, 'memory_usage')) / count($lastBatches);

$growthMb = ($avgLast - $avgFirst) / 1024 / 1024;

if ($growthMb > $config['memory_threshold_mb']) {
    // LEAK DETECTED
}
```

**Assessment**: ✅ Sound methodology, industry-standard approach

### 2.3 Garbage Collection Strategy

```php
if ($i % $config['gc_interval'] === 0) {
    gc_collect_cycles();
}
```

**Assessment**: ✅ Periodic GC prevents false positives from delayed collection

---

## 3. Architectural Analysis for Memory Safety

### 3.1 DocumentBuilder

**Location**: `src/Http/Document/DocumentBuilder.php`

**Potential Leak Points**:
- ❌ **No static caches** - Good, no global state
- ✅ **$visited array** for deduplication - Cleared per request
- ✅ **PropertyAccessor** - Injected dependency, no internal state accumulation

**Code Review**:
```php
public function buildCollection(string $type, array $models, Criteria $criteria, Slice $slice, Request $request): array
{
    $data = [];
    $included = [];
    $visited = []; // ✅ Request-scoped, no leak
    
    foreach ($models as $model) {
        $data[] = $this->buildResourceObject($type, $model, $criteria, $context);
        if ($includeTree !== []) {
            $this->gatherIncluded($type, $model, $includeTree, $criteria, $included, $visited, $context);
        }
    }
    // ✅ Arrays are freed when method returns
}
```

**Assessment**: ✅ No memory leaks expected

### 3.2 QueryParser & FilterParser

**Location**: `src/Http/Request/QueryParser.php`, `src/Filter/Parser/FilterParser.php`

**Potential Leak Points**:
- ✅ **Stateless parsing** - No internal caches
- ✅ **AST nodes** - Short-lived, freed after compilation

**Assessment**: ✅ No memory leaks expected

### 3.3 Atomic Operations & LID Registry

**Location**: `src/Atomic/Execution/`

**Potential Leak Points**:
- ⚠️ **LID Registry** - Could accumulate if not cleared per request

**Code Review Needed**:
```php
// Need to verify LID registry is request-scoped
// If it's a service, it must clear state after each atomic operation
```

**Recommendation**: 🔍 **Review LID registry lifecycle** - Ensure it's cleared after each atomic operation

### 3.4 Cache & ETag Generation

**Location**: `src/Http/Cache/`

**Potential Leak Points**:
- ✅ **CacheKeyBuilder** - Stateless
- ✅ **HeadersApplier** - Stateless
- ✅ **No LRU caches** - Good, no unbounded growth

**Assessment**: ✅ No memory leaks expected

---

## 4. N+1 Query Analysis

### 4.1 Include Resolution

**Location**: `DocumentBuilder::gatherIncluded()`

**Potential N+1**:
```php
private function gatherIncluded(string $type, object $model, array $includeTree, ...): void
{
    foreach ($includeTree as $relationshipName => $nestedTree) {
        $relationship = $metadata->getRelationship($relationshipName);
        
        // ⚠️ POTENTIAL N+1: Fetching related resources one-by-one
        $related = $this->accessor->getValue($model, $relationship->propertyPath);
        
        if (is_iterable($related)) {
            foreach ($related as $relatedModel) {
                // Recursive include
            }
        }
    }
}
```

**Assessment**: ⚠️ **Potential N+1** - Depends on repository implementation

**Recommendation**: 
- ✅ If using Doctrine with `fetch="EAGER"` or batch loading - OK
- ❌ If using lazy loading without batch fetching - N+1 risk
- 🔍 **Add SQL query counter to stress tests** to detect N+1

### 4.2 Relationship Endpoints

**Location**: `RelatedController`, `RelationshipGetController`

**Assessment**: 🔍 Needs profiling with real database

---

## 5. Stress Test Results (Simulated)

**Note**: Current tests use simulation, not real controllers. Results are **indicative only**.

### 5.1 Expected Results (Based on Architecture)

| Scenario | Expected Memory | Expected Time | N+1 Risk |
|----------|----------------|---------------|----------|
| Collection GET (1000×) | < 30 MB growth | < 50ms avg | ⚠️ Medium (if lazy loading) |
| Related to-many (500×) | < 20 MB growth | < 30ms avg | ⚠️ Medium |
| Atomic ops (200×) | < 15 MB growth | < 100ms avg | ✅ Low (transactional) |
| PATCH/DELETE (300×) | < 10 MB growth | < 40ms avg | ✅ Low |
| Filters (100×) | < 5 MB growth | < 60ms avg | ✅ Low (parameterized) |

### 5.2 Actual Results

**Status**: ❌ **Not Available** - Tests need real controller integration

**To Run**:
```bash
make stress-mem
```

**Expected Output**:
```
=== Memory Leak Analysis ===
Average memory (first 10%): 12.34 MB
Average memory (last 10%): 15.67 MB
Growth: 3.33 MB
✅ No memory leaks detected
```

---

## 6. Identified Issues & Recommendations

### 6.1 HIGH PRIORITY: Integrate Real Controllers

**Issue**: Stress tests currently use simulation (commented-out controller calls)

**Impact**: Cannot validate real-world memory behavior

**Fix**:
```php
// scripts/stress/run.php (lines 150-158)
// BEFORE (simulation):
// $request = Request::create('/api/articles?include=' . implode(',', $includes));
// $response = $controller($request, 'articles');

// AFTER (real):
$testCase = new JsonApiTestCase();
$controller = $testCase->collectionController();
$request = Request::create('/api/articles?include=author,tags&fields[articles]=title,body');
$response = $controller($request, 'articles');
unset($response); // Free memory
```

**Effort**: 2-3 hours

**PR**: TBD

### 6.2 MEDIUM PRIORITY: Add SQL Query Profiling

**Issue**: No visibility into N+1 queries

**Impact**: Cannot detect performance regressions

**Fix**: Add Doctrine SQL logger to stress tests

```php
use Doctrine\DBAL\Logging\DebugStack;

$sqlLogger = new DebugStack();
$entityManager->getConnection()->getConfiguration()->setSQLLogger($sqlLogger);

// Run stress test

echo "Total queries: " . count($sqlLogger->queries) . "\n";
foreach ($sqlLogger->queries as $query) {
    echo $query['sql'] . "\n";
}
```

**Effort**: 1-2 hours

### 6.3 MEDIUM PRIORITY: Add Memory Graph Visualization

**Issue**: Text output is hard to analyze for trends

**Impact**: Harder to spot gradual memory growth

**Fix**: Generate SVG/PNG graphs using GD or Chart.js

```php
// Generate memory usage graph
$data = array_column($metrics['batches'], 'memory_usage');
$chart = new MemoryChart($data);
$chart->save('build/memory-graph.svg');
```

**Effort**: 3-4 hours

### 6.4 LOW PRIORITY: Blackfire Integration

**Issue**: No deep profiling for hotspots

**Impact**: Cannot optimize performance bottlenecks

**Fix**: Add Blackfire examples to README

```bash
blackfire run php scripts/stress/run.php --profile=mem
```

**Effort**: 1 hour (documentation only)

---

## 7. Profiling Recommendations

### 7.1 XDebug Profiling

```bash
php -d xdebug.mode=profile \
    -d xdebug.output_dir=/tmp/xdebug \
    scripts/stress/run.php --profile=perf
```

Analyze with:
- **KCachegrind** (Linux)
- **QCachegrind** (macOS/Windows)
- **Webgrind** (Web-based)

### 7.2 Blackfire Profiling

```bash
blackfire run php scripts/stress/run.php --profile=mem
```

Focus on:
- **Inclusive time** - Total time including children
- **Exclusive time** - Time in function itself
- **Memory allocations** - Where memory is allocated

### 7.3 php-meminfo (Memory Graph Analysis)

```bash
pecl install meminfo
php -d extension=meminfo.so scripts/stress/run.php
```

Analyze with:
```bash
meminfo_dump(fopen('/tmp/memory.dump', 'w'));
```

---

## 8. Continuous Monitoring

### 8.1 CI Integration

Add to `.github/workflows/qa.yml`:

```yaml
- name: Memory Stress Test
  run: |
    make stress-mem
    if [ $? -ne 0 ]; then
      echo "::warning::Memory leak detected"
    fi
  continue-on-error: true
```

### 8.2 Baseline Comparison

Store baseline metrics:

```bash
# First run (baseline)
make stress-mem > build/baseline-memory.txt

# Subsequent runs (compare)
make stress-mem > build/current-memory.txt
diff build/baseline-memory.txt build/current-memory.txt
```

### 8.3 Alerting

Set up alerts for:
- Memory growth > 50 MB
- Peak memory > 256 MB
- Average batch time > 100ms

---

## 9. Performance Benchmarks

### 9.1 Target Metrics

| Operation | Target Time | Target Memory |
|-----------|-------------|---------------|
| GET collection (10 items) | < 10ms | < 5 MB |
| GET resource with include (depth 2) | < 15ms | < 3 MB |
| POST create resource | < 20ms | < 2 MB |
| PATCH update resource | < 15ms | < 2 MB |
| DELETE resource | < 10ms | < 1 MB |
| Atomic operations (5 ops) | < 50ms | < 5 MB |

### 9.2 Scalability Targets

| Scenario | Target |
|----------|--------|
| Concurrent requests | 100 req/s |
| Collection size | 1000 items (paginated) |
| Include depth | 5 levels |
| Included resources | 100 items |
| Filter complexity | 20 clauses |

---

## 10. Action Plan

### Phase 1: Immediate (Week 1)
- [ ] **HIGH**: Integrate real controllers into stress tests
- [ ] **HIGH**: Run stress tests and collect baseline metrics
- [ ] **MEDIUM**: Add SQL query profiling

### Phase 2: Short-term (Month 1)
- [ ] **MEDIUM**: Add memory graph visualization
- [ ] **MEDIUM**: Review LID registry lifecycle
- [ ] **LOW**: Add Blackfire integration examples

### Phase 3: Ongoing
- [ ] Run stress tests before each release
- [ ] Monitor memory usage in production
- [ ] Update baselines quarterly

---

## 11. Conclusion

**Overall Assessment**: ⚠️ **Good Foundation, Needs Real Testing**

**Strengths**:
- ✅ Solid stress test infrastructure
- ✅ Sound memory leak detection methodology
- ✅ Clean architecture with no obvious leak points
- ✅ Stateless components (parsers, builders)

**Weaknesses**:
- ❌ Stress tests use simulation, not real controllers
- ⚠️ No SQL query profiling (N+1 risk)
- ⚠️ LID registry lifecycle needs review

**Next Steps**:
1. Integrate real controllers into stress tests (HIGH priority)
2. Run full stress test suite and collect metrics
3. Add SQL query profiling to detect N+1
4. Review and optimize any identified bottlenecks

**Estimated Effort**: 1-2 days for full implementation

---

**Last Updated**: 2025-10-06  
**Reviewer**: Codex QA Agent  
**Status**: ⚠️ Partial - Needs real controller integration

