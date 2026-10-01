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

## Dedicated read-path implementation plan

No collection fetching, sorting semantics, include traversal, or serializer behavior is changed by this patch. PERFORMANCE-NPLUS1, SCALABILITY-JOIN-PAGINATION, and SCALABILITY-INCLUDE-AMPLIFICATION remain open until the following shared design is implemented and independently tested.

### Findings

`DocumentBuilder::buildRelationships()` resolves each emitted linkage through property access; `resolveRelationshipLinkage()` iterates to-many collections, and `buildIdentifier()` reads the related API ID. `gatherIncluded()` traverses and serializes related entities recursively before the final included-count check. These accesses can initialize Doctrine proxies and collections per root. `GenericDoctrineRepository::applyEagerLoading()` only sees include paths, whereas the document may emit relationship linkage because of `linkage_in_resource: always`, sparse fields, or profile hooks. This explains the page-size-dependent SQL pattern reported by torture and the inexpensive sparse representation without relationships. The existing `Resource\Relationship\RelationshipResolver` primarily handles persistence/relationship mutation; it should not become the read-plan implementation.

`findCollectionWithTwoStepLoading()` still applies filters and sorting joins to its first query, limits joined rows, and only then deduplicates through ORM hydration. Its second query can join several collections together. Neither stage guarantees a full page of distinct roots or bounded hydration. The plain collection path has the same joined-row pagination problem even without includes.

### Core model and contracts

Add `Query\RepresentationFetchPlan`, `Query\RelationshipFetchPlan`, and `Query\RepresentationFetchPlanner`. Build one immutable plan after profile query hooks, using ResourceMetadata, resolved property-path aliases, include prefixes, sparse fieldsets per type, and the relationship-linkage policy. Each graph edge records source/target types, API relationship name, persistence path, cardinality, requested target fields, child plan, and separate requirements for linkage identifiers, full included representations, and profile aggregates. Preserve the current full-linkage rules and sparse-fieldset exceptions. Do not fetch hidden relationships merely because an include is present elsewhere in the graph.

Add optional `Contract\Data\RepresentationPreloaderInterface` and `Contract\Data\RelationshipBatchReaderInterface`. Keep `ResourceRepository` signatures intact; use a representation-loading coordinator between the query/controller and document builder. A provider can implement batching or retain the existing resolver fallback. The core plan contains no ORM classes or SQL aliases.

Add `Http\Document\RepresentationContext` holding a request-local `RelationshipReadMap`, primary-resource identities, an included-resource identity set, and an `IncludeBudget`. Key cached relationship values by `(source type, source API ID, relationship, profile/query scope)`; retain the distinction between not loaded, loaded empty, linkage-only, and full entities/views. Never store partially loaded Doctrine collections as complete. Keep type+ID in identities to preserve UUID, inheritance/discriminators, and resource aliases. Reset this context per request, including repeated requests in the same worker.

`DocumentBuilder` consumes the read map for linkage/includes before falling back to property access. Required profile aggregates are passed explicitly, rather than implemented as arbitrary hidden queries inside document hooks. Add an optional profile fetch-requirements hook; existing hooks continue to run, but opaque custom hooks cannot be promised bounded SQL without a declared fetch requirement.

### Root pagination and sorting

Add `Bridge\Doctrine\Query\DoctrineRootPaginator` and extract root filtering/sorting construction from `GenericDoctrineRepository`. Build a root-entity query with filters, custom conditions, profile/tenant constraints, supported sorts, and a stable single-ID tiebreaker. Do not add representation fetch joins at this stage.

Use Doctrine's collection-aware `Paginator($query, fetchJoinCollection: true)` with output walkers for queries that can introduce to-many joins, including joins from filters and custom handlers. `count($paginator)` supplies the distinct root count; iteration gives the selected distinct root page. Extract the selected identifiers using Doctrine metadata and retain their order. For a proven root/to-one-only query, the direct limited path can remain as an optimization. Do not disable DISTINCT based only on include paths.

Doctrine documents the distinct-count, limited-ID, and selected-root fetch strategy for collection pagination; see [Doctrine pagination documentation](https://www.doctrine-project.org/projects/doctrine-orm/en/3.6/tutorials/pagination.html). The installed ORM 3.7 implementation deprecates `Paginator` in favor of `OffsetPaginator`; keep the choice behind `DoctrineRootPaginator` so the declared ORM support matrix can use the supported implementation without changing core contracts. The output walkers additionally preserve ORDER BY expressions in subqueries and use row-number/grouping strategies where supported. Prefer these maintained walkers to custom blind DISTINCT rewrites.

For DTO resources, select root entities/identifiers for pagination independently from the DTO projection. Hydrate the projection only for the selected IDs, preserve that exact order in the returned Slice, and keep the existing read mapper. Reapply tenant/profile visibility constraints consistently in identifier selection, count, hydration, and related loads. Typed ID parameters must use the mapping's DBAL type, not stringified UUID arrays.

Test both PostgreSQL and MySQL with duplicates from relationship filters, multiple joined predicates, all root/to-one sort directions, stable pagination across pages, empty pages, distinct counts, custom handlers, UUID IDs, DTO projections, and aliases. Execute SQL assertions against both engines before choosing output-walker optimization flags.

A path such as `sort=attachments.name` has no generic single value per root. Recommended public policy for the dedicated branch: reject sorts crossing a collection unless a registered custom sort handler declares a scalar aggregate/order key. Handlers may explicitly select MIN, MAX, or another documented aggregate, and their result must still include a root-ID tiebreaker. Do not assign accidental join-order semantics or silently choose MIN. This rejection is a BC change and needs release notes/migration guidance plus an opt-in policy during a transition release. Existing to-many sort behavior stays unchanged in this patch.

### Batched relationship loading

Add `Bridge\Doctrine\Query\DoctrineRepresentationPreloader` and `Bridge\Doctrine\Relationship\DoctrineRelationshipBatchReader`. Hydrate the selected root page with bounded to-one joins where they cannot multiply rows. For linkage-only to-one relationships, use foreign-key identifiers directly when the API identifier is the mapped key; otherwise batch-load the API-ID mapping.

Load each required to-many relationship separately for all source IDs. Query `(owner ID, related API ID)` for linkage-only edges, retaining declared relationship ordering. For full representations, discover related IDs, then load distinct targets in a separate typed-ID batch. A one-to-many query can constrain the mapped owning FK; a many-to-many query selects join-table owner/target mappings. Resolve inverse sides, inheritance, property-path aliases through join entities, and custom relationships through provider adapters. Do not select several sibling collections in one Cartesian fetch join, and do not mutate partially initialized PersistentCollections in the read unit of work.

Traverse nested include frontiers breadth-first, grouping source IDs by `(manager, source type, relationship, effective target plan)`. Batch each edge once per frontier/chunk and deduplicate targets by type+API ID. Union child requirements when multiple paths reach one target. Track expanded `(identity, plan node)` pairs so cycles terminate without dropping a deeper requested path. Exclude primary identities from `included`, and preserve full-linkage reachability and sparse-field exceptions. Profiles requesting counts use one grouped aggregate per required edge instead of per-owner COUNT calls.

For an ordinary bounded page, SQL count depends on graph edges and nesting levels, not the number of roots. Parameter limits can require fixed-size chunks; explicitly assert the expected chunk-dependent upper bound rather than claiming a constant for arbitrarily large pages.

### Include amplification budget

`IncludeBudget` tracks distinct included identities, excluding primary resources, across all frontiers. Before hydrating target objects, the batch reader discovers distinct target IDs with an upper bound of `remaining + 1`. Discover across the whole frontier, not separately per owner with that allowance; reject the first excess distinct target immediately. Avoid loading bodies, sibling collections, or deeper nodes until the identifier reservation succeeds. Use bounded chunks/streaming identifier discovery and stop the database result as soon as excess is proven. SQL row limits are local to this safety probe; they never truncate a successful document silently.

Keep linkage and included budgets separate: complete to-many linkage may validly need more identifiers than the included-object allowance. Use dedicated configurable linkage/edge budgets if this must also be bounded; reject an excessive full-linkage representation explicitly rather than truncating it. Shared targets, duplicates across owners, cycles, and primary resources must not consume included budget twice. Counting an enormous hydrated collection after serialization is no longer the primary guard. DTO mapping and profile hooks receive only the reserved, bounded set of targets.

### Delivery and verification

Implement the fetch-plan/read-map abstraction first with serializer compatibility snapshots and request-state reset tests. Then implement distinct-root pagination with collection-sort policy behind an explicit migration configuration. Add Doctrine identifier batching and profile aggregate declarations; finally switch include traversal to reservation before hydration. Run all existing bundle suites and the untouched example acceptance/torture contracts on that separate branch.

Require page-size comparisons (5 versus 20 roots) for plain full-linkage documents, to-one/to-many/nested includes, multiple sibling collections, sparse fields, all linkage policies, DTOs, profiles, aliases, cyclic graphs, tenant/shard routing, and UUID IDs. Assert selected distinct root count/order, graph-shaped query bounds, bounded rows/hydrated objects before include rejection, and unchanged JSON:API documents. Do not weaken the open torture tests.

## Non-goals

No distributed transaction/2PC, replication or lag management, sharding engine, tenant framework, or global deadlock retries. ManagerRegistry continues to route resources and requests. Database topology remains the application's responsibility. Full composite JSON:API identifier support is deferred. Read-path batching, distinct pagination, and prefetch include budgets are a dedicated next phase, not included in this patch.
