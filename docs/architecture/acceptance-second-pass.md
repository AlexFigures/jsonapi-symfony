# Second acceptance-gap pass

This pass implements transaction scoping, concurrent write preconditions, filter safety, and composite-ID discovery diagnostics. The stable `TransactionManager` interface is unchanged. External acceptance/torture tests were inspected as read-only references and were **not executed**, as requested. Bundle tests are the evidence below; independent external verification remains pending.

## Implemented gaps and regression coverage

| Gap | Root cause | Change | Bundle regression |
| --- | --- | --- | --- |
| CACHE-001 | The required-header error was built with the default status 400 while its exception returned 428. | `ConditionalRequestEvaluator` builds status 428 with the existing `precondition-required` code/title. Serialization is unchanged. | `ConcurrentWritePreconditionsTest::testGuardedHttpPreconditionsAndWildcard` checks HTTP 428, error status, source header, and unchanged SQL row; `WritePreconditionsRegressionTest` covers PATCH and DELETE. |
| TRANSACTION-SECOND-COMMIT | Every registered manager was begun and committed. A second, unrelated commit could fail after the actual write committed. | `ScopedTransactionManagerInterface`, `TransactionScope`, and `DoctrineTransactionBoundaryResolver` enlist only the selected resource manager. `FlushManager` rejects scheduling another manager inside the scope. | `DoctrineAtomicBoundariesTest::testPostgresBatchIgnoresUnrelatedMysqlCommitFault`: a real PostgreSQL Atomic operation succeeds; an independent MySQL manager with an injected commit fault has zero begin/flush/commit calls and its connection remains unopened. |
| TRANSACTION-BOUNDARY | Atomic execution discovered persistence dependencies during operations, after mutations/flushes had started. | `AtomicResourceTypes` collects every validated root/ref and explicit relationship identifier, including lids. `AtomicTransaction` resolves the complete batch before invoking the dispatcher callback. Multiple connections or managers produce HTTP/error status 409, code `unsupported-transaction-boundary`, title `Unsupported transaction boundary`. | `DoctrineAtomicBoundariesTest::testCrossDatabaseAndShardAtomicPreflightPreservesEveryOriginal` uses PostgreSQL + MySQL and independent PostgreSQL shard databases, asserts zero SQL on both connections, and verifies both original rows. Unit tests also reject separate managers sharing one connection. |
| CONSISTENCY-ETAG | The subscriber checked the current representation outside the write transaction. Two writers could both pass the same validator. | Optional `WriteConcurrencyGuardInterface`; Doctrine implements a transaction plus a pessimistic root-row write lock. The subscriber wraps the already-resolved controller at `kernel.controller_arguments`, rebuilds the representation after acquiring protection, then validates and executes the write in that same transaction. | `ConcurrentWritePreconditionsTest::testIndependentConcurrentPatchRequestsRevalidateAfterWaitingForRowLock`: separate PHP processes/ORM managers prime old representations, both demonstrably wait on a held PostgreSQL lock (`pg_stat_activity`), then exactly one receives 200 and one 412. The final SQL row equals the winner's response. |
| QUERY-002 / SCALABILITY-FILTER-BUDGET | Filters were absent from the overall score; depth alone did not bound wide expressions or IN lists. | Iterative `FilterComplexityAnalyzer` measures all AST nodes, depth, operands, and path hops. Absolute limits run before whitelist validation and again after profile query hooks; the weighted overall score also includes filters. | `FilterComplexityLimitsTest` covers all six node kinds, 40-level nesting, 100/500/1000 leaves, IN/NOT IN lists, configured thresholds, disabled limits, reasonable nesting, overall budget, and zero repository calls on rejection. Configuration tests reject negative values. |
| ARCHITECTURE-COMPOSITE-ID | Doctrine resources could advertise one component of a composite identifier and fail ambiguously at runtime. | Optional `ResourceMetadataValidatorInterface`; the built-in Doctrine provider wires `DoctrineIdentifierMetadataValidator` into route discovery. Composite metadata produces a deterministic configuration exception naming the resource/entity and recommending a single API identifier or custom provider. | `DoctrineIdentifierDiscoveryTest` accepts integer, UUID, and natural string IDs and rejects a composite during route discovery without connecting to a database. |

The preexisting `AcceptanceGapsTest::testGeneratedIdAndLidRelationshipAndOperationSnapshots` remains relevant. `DoctrineAtomicBoundariesTest::testGeneratedIdsAndLidsRollbackAfterLaterFailure` additionally creates a generated ID, resolves it through a lid on a second resource, and fails the third operation; neither flushed row survives rollback. Existing deferred unique-constraint, foreign-key, profile, relationship, identifier, and precondition coverage is retained.

## Transaction model

For the built-in Doctrine provider, each Atomic batch has **one EntityManager on one connection**. Independent databases/shards are rejected before operation zero. Separate managers sharing a connection are also rejected: independently flushing multiple units of work would need a separate supported coordination design. A matching DSN does not make two connections one boundary.

Per-operation flushes remain inside the transaction so generated IDs and lids work. There is one outer commit after every operation and result snapshot succeeds. Nested resource writes can flush but never independently commit. Errors roll back and discard scheduled flushes. Scheduling an unexpected manager is rejected as a defensive check for custom processors; complete preflight remains the primary Atomic guard.

CRUD controllers pass their resource data class through `TransactionScope`. Custom transaction implementations that do not implement the optional scoped contract retain their existing behavior. Direct legacy calls to `DoctrineTransactionManager::transactional()` without a resource scope use only the default manager; callers needing a different manager must use `transactionalFor()`. Custom route handlers using that legacy entry point inherit this default-manager rule and cannot flush another manager implicitly.

The transaction runner retains the existing close-on-rollback Doctrine policy. A long-lived integration must reset closed managers before subsequent requests, as it must after other Doctrine transaction failures.

## Concurrency model

With caching/precondition evaluation enabled and the built-in Doctrine provider configured, stock update, delete, and relationship-write controllers use `DoctrineWriteConcurrencyGuard` whenever write conditions need evaluation. The guard acquires `PESSIMISTIC_WRITE` on the addressed root before rebuilding its representation; the request's earlier identity-map reads are discarded before the lock read. This occurs before write preparation, not after pending changes exist. The lock remains held through nested controller flushes and the outer commit. Doctrine requires an active transaction for pessimistic locks; see [Doctrine transaction and concurrency documentation](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/transactions-and-concurrency.html).

Two concurrent conditional writes to the same root, using the same strong validator and actually changing its representation, cannot both accept that old validator: the waiting writer rebuilds the now-current representation and receives 412 without applying its mutation. Matching sequential validators and `If-Match: *` continue to work. The wildcard checks existence and permits successive writes; it intentionally does not compare a version. A successful no-op write that leaves the validator unchanged does not invalidate another matching request.

Protection is per root row, not global. This is not a serializable snapshot of every related resource in a compound document. Writers addressing another root, independent child modifications, custom controllers, and out-of-band SQL need their own concurrency policy if their effects are intended to invalidate a root validator atomically. Database lock-timeout/deadlock errors retain ordinary rollback handling; there is no automatic retry. Custom providers may bind `WriteConcurrencyGuardInterface` to optimistic revisions or CAS; absent that binding, the previous sequential evaluator behavior remains available.

## Filter configuration

```yaml
jsonapi:
    limits:
        filter_max_depth: 8
        filter_max_nodes: 100
        filter_max_operands: 200
```

Each value must be a nonnegative integer; zero disables that individual limit. AST depth starts at one for a leaf. Conjunction, disjunction, and explicit group wrappers each count as a node and depth level. A disjunction containing 100 comparisons has 101 nodes and is rejected at the default limit. Comparisons count every value, including IN/NOT IN list entries; BETWEEN counts two operands; NULL checks have zero operands. Relationship path hops are counted across leaf paths. The added weighted score is `nodes + operands + 2 * pathHops`, in addition to the existing include/fields/sort/page weights. Absolute limits operate even with `complexity_budget: 0`.

The parser retains an early raw nesting guard to prevent pathological recursion while constructing the AST. `QueryParser` then runs the structural limits before whitelist validation, constructs Criteria, executes profile hooks, and applies the limits/overall score again before repository access. A profile that changes criteria is still subject to the limits. These defaults intentionally reject previously unbounded requests; raise or disable individual thresholds explicitly when required.

## Read-path follow-up in the same branch

A reusable fetch-plan/preloader abstraction was implemented after the correctness commit. Root selection uses Doctrine pagination for relationship joins, representation loading batches each graph edge, include budgets are reserved before hydration, and profile counts use grouped queries. No giant graph fetch join is used. `ResourceRepository` remains unchanged.

| Gap | Root cause | Implemented change | Bundle evidence | External result |
| --- | --- | --- | --- | --- |
| PERFORMANCE-NPLUS1 | Relationship linkage and includes initialized proxies/collections per root. | Optional preloader + local identifier/model map, relationship-edge batches and grouped profile counts. | PostgreSQL/MySQL compare 5 and 20 roots for plain, to-one, to-many, nested and sparse representations; SQL count is equal. | Pending separate consumer run. |
| SCALABILITY-JOIN-PAGINATION | Filters/sorts could add to-many joins before LIMIT in the supposedly join-free root phase. | Doctrine paginator with output walkers pages distinct roots before DTO/representation loading. | Duplicate relationship matches still yield 20 unique roots, correct total and stable next-page boundaries on both databases; DTO and explicit aggregate handler covered. | Pending separate consumer run. |
| SCALABILITY-INCLUDE-AMPLIFICATION | The included-count check happened after lazy hydration/serialization. | Distinct target identifier probe, remaining + 1 bound, reservation before body hydration, independent linkage identifier budget. | Excessive include fails before target hydration; bounded SQL probe, shared-target deduplication and state isolation covered on both databases. | Pending separate consumer run. |

These are implemented for native Doctrine representation paths, not yet a claim of all-endpoint 1.0 readiness. Dedicated relationship endpoints and opaque/custom read paths remain release blockers. The [current read-path design, limits, tests and implementation-ready follow-up](read-path-stabilization.md) defines the exact scope and remaining work.

## Non-goals

No distributed transactions/2PC, replication management or independent-GET lag compensation, sharding engine, tenant framework, or global automatic deadlock retry. Existing application manager/replica routing remains the integration point. Unsupported multi-boundary Atomic batches are rejected, not emulated.
