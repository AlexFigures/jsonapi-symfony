# Memory & Performance Review

## Methodology
- Static analysis of hot paths (`DocumentBuilder`, query parsing, relationship mutation controllers) for lingering static caches or per-request globals.
- Review of available tooling (phpbench, custom stress scripts) in the repository.
- Spot audit of cache/precondition utilities to ensure deterministic cleanup.

## Observations
- `DocumentBuilder` constructs per-request arrays and does not stash state in static properties; include traversal operates on local `$included` and `$visited` sets that are discarded after each request.【F:src/Http/Document/DocumentBuilder.php†L43-L130】
- No repository-level `clear()` hooks are present; in-memory fixtures back functional tests so long-running Doctrine unit-of-work stress paths are untested.【F:tests/Functional/CollectionGetTest.php†L12-L27】【F:tests/Functional/ResourceGetTest.php†L12-L51】
- There is no bundled stress harness (`scripts/stress` absent) or phpbench profiles; Makefile lacks targets for sustained load verification.
- Conditional request evaluator and headers applier are unit-tested but not tied into load measurements; missing data on ETag/If-* impact under concurrency.【F:tests/Unit/Http/Cache/ConditionalRequestEvaluatorTest.php†L18-L116】

## Gaps
1. **Stress workloads** – Required batched GET/relationship/atomic/patch sequences are not automated; memory regressions would go unnoticed.
2. **Database adapter coverage** – Current tests rely on in-memory repositories; Doctrine-backed adapters may retain managed entities unless `clear()` is invoked per batch.
3. **Profiling baselines** – No Blackfire/Xdebug captures committed; makes regression gating impossible.
4. **Include explosion controls** – `LimitsEnforcer` hooks exist but there is no benchmark proving truncated include sets bound CPU/memory.

## Recommendations
- Implement `scripts/stress/run.php` exercising the mandated request matrix with `memory_get_usage(true)` snapshots and meminfo diffs; feed into CI artifact uploads.
- Integrate phpbench micro-benchmarks for document building (varying include depth, sparse fieldsets) and parsing (sort/include/fields) to detect algorithmic regressions.
- When Doctrine adapters land, ensure transaction manager clears unit of work and releases references after each atomic batch.
- Capture baseline Blackfire profiles for collection GET with deep include and for atomic operations; store summary SVGs under `docs/reliability/` for comparison in future stages.
- Enforce `LimitsEnforcer` budget checks via stress tests toggling `include_depth`, `fields_max_total`, and complexity limits to guarantee errors vs. truncation behave deterministically.
