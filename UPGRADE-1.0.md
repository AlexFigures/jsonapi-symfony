# Upgrade toward 1.0 — draft

This document describes implemented changes on `fix/acceptance-gaps`. It is not a frozen 1.0 migration guide yet. Independent consumer verification, the final PUBLIC/INTERNAL audit and the compatibility matrix remain release gates. No namespace moves or final public API freeze are announced by this pass.

## Atomic transaction boundaries

**Before:** the Doctrine transaction manager began and committed all registered managers. An unrelated second commit could fail after the actual resource's transaction had committed. Atomic batches could discover another database after executing earlier operations.

**After:** an Atomic batch is preflighted completely and requires one Doctrine manager and one connection. Multiple independent connections, databases or shards are rejected with HTTP 409 and `unsupported-transaction-boundary` before any operation executes. Separate managers sharing one connection are also rejected in this initial implementation. Generated-ID/lid workflows can flush inside the same transaction; commit occurs only after every operation succeeds.

**Why:** no silent partial commit and no distributed-transaction promise.

**Migration:** split cross-boundary work into application workflows; do not represent it as one Atomic batch. Route resource classes to the intended manager through ManagerRegistry. The existing `TransactionManager` interface is unchanged. Applications manually wrapping non-default Doctrine writes should use the optional `ScopedTransactionManagerInterface::transactionalFor()` with the complete list of data classes. Legacy unscoped `transactional()` uses the default manager, not every registered manager. Custom providers keep their existing transaction callback path unless they implement scoping.

## Concurrent write preconditions

**Before:** ETag validation preceded the transaction, so two writers could pass the same validator.

**After:** the built-in Doctrine guard begins a scoped transaction, discards previously read managed state, locks the current root row, rebuilds the validator, validates the original header and executes/flushes the write before releasing the lock. A competing stale validator returns 412 without mutation. A required missing `If-Match` consistently returns HTTP 428 and JSON:API `errors[].status: "428"`, with `source.header: If-Match`.

**Why:** validation and persistence must share the concurrency boundary.

**Migration:** retain cache/precondition configuration and use the provided Doctrine guard with the default Doctrine transaction/processor stack. Custom providers can implement `WriteConcurrencyGuardInterface` using revisions/CAS or their own persistence strategy. Wildcard `If-Match: *` keeps existence semantics; it does not promise that only one of several wildcard writers succeeds. No global write serialization or automatic retry is added. Arbitrary out-of-band writes or independently modified related graphs require application concurrency policy.

## Structural filter limits

**Before:** deep/wide filters and large IN/NOT IN operands could reach SQL; filters contributed no overall complexity score.

**After:** AST depth, node count and operand count are independently checked before repository access and again after profile query hooks. Defaults are depth 8, nodes 100 and operands 200. Weighted filter cost is `nodes + operands + 2 * relationship_path_hops` in the existing overall budget.

**Why:** a one-node IN expression can still carry an expensive number of values.

**Migration:** configure `limits.filter_max_depth`, `filter_max_nodes`, `filter_max_operands` and `complexity_budget` deliberately. Nonnegative integer values are required; zero disables an individual guard. Logical groups count as nodes/depth levels, so 100 comparisons under a disjunction use 101 nodes. Invalid operand/type/cardinality diagnostics remain supported.

## Composite identifiers

**Before:** discovery could advertise a composite-ID entity and fail ambiguously later.

**After:** built-in Doctrine route/resource discovery rejects composite identifiers with a configuration exception naming the resource/entity and the unsupported mapping.

**Why:** the built-in URL, relationship, Atomic and identifier conversion model assumes one API identifier.

**Migration:** expose a single integer, UUID or natural string identifier, or use a custom data provider with an explicit identifier strategy. Composite JSON:API ID encoding is not introduced here.

## Root pagination and DTO projections

**Before:** filtering/sorting joins could still paginate joined SQL rows in the custom two-step collection loader, yielding fewer unique roots than requested.

**After:** joined root selection uses Doctrine's paginator with output walkers. Roots are selected before representation relationship queries. DTO scalar projections then load selected IDs and restore root selection order, independently of the names of DTO identifier fields. UUID ID arrays are converted through Doctrine types before binding.

**Why:** page boundaries belong to resources, not joined rows.

**Migration:** retain filters and root/to-one sorting. Review custom handlers that alter SELECT/GROUP BY/HAVING; they need explicit pagination coverage. Do not depend on duplicate joined roots or include fetch joins to initialize ORM collections as a side effect. DTO field-map expressions use the repository's `e` root alias.

## Relationship loading and budgets

**Before:** linkage/includes could initialize one lazy collection per root; included overflow was detected after hydration.

**After:** native Doctrine representation paths use batch identifier queries and included-target loading. Included IDs reserve a distinct-resource budget before hydration. The new `limits.relationship_max_identifiers` defaults to 10000 and bounds loaded relationship identifier entries per document. Zero disables it. Overflow returns a controlled 400 rather than truncating a successful document.

**Why:** final-document size checks alone do not protect query/hydration cost.

**Migration:** use sparse fields and `relationships.linkage_in_resource: when_included` for large graphs; keep `always` only when complete visible linkage is intended and affordable. Identifier linkage no longer initializes PersistentCollection objects; code that requires initialized collections must load them explicitly. Built-in relationship-count profiles use grouped queries. Custom hooks may declare count requirements through `FetchPlanHookInterface`; opaque custom code retains its own cost responsibility. Stock already-flushed write responses use fresh relationship ordering but do not gain a new read-budget rejection after commit.

Dedicated relationship endpoints still have a legacy collection-reading path and remain a separate release blocker. Computed relationships and nested application getters are not automatically made bounded by the optional preloader. See [read-path scope and follow-up](docs/architecture/read-path-stabilization.md).

## Included identity and linkage corrections

**Before:** a primary resource could appear again in `included`, and `never` could suppress linkage needed by an explicit include.

**After:** primary identities are excluded from included output and its budget. Explicit include paths retain required full linkage even with `never`, except when sparse fields exclude the relationship. Shared identities appear once.

**Why:** compound documents require one resource object per identity and connected included resources. See [JSON:API compound documents](https://jsonapi.org/format/#document-compound-documents).

**Migration:** index compound documents by `(type, id)` across primary and included data. Do not expect primary resources a second time in `included`. The `never` policy now means omission of optional linkage, not removal of linkage required by an explicit include.

## Collection-valued sorting policy

**Before:** to-many sort order depended on the SQL join result rather than declared aggregate semantics.

**After:** `performance.doctrine.collection_sort_policy: reject` rejects collection-valued traversal before SQL unless a custom sort handler is registered. The default remains `legacy` for compatibility; it is not a production ordering guarantee.

**Why:** a root with several related values needs an explicit sort definition.

**Migration:** enable `reject` and register handlers defining MIN/MAX or a domain aggregate. The bundle regression suite demonstrates a correlated MIN handler. A future default change before the 1.0 freeze must be announced and independently verified; it has not happened in this pass.

## Pending final migration audit

Before final 1.0, append every approved namespace/interface/service-alias move, constructor/named attribute argument change, configuration default change and removed/deprecated behavior. Review public exceptions/events/hooks/value objects and error code/title/source semantics. Declare only the platform matrix actually exercised in CI. Freeze and RC follow independent consumer confirmation; this draft does not substitute for it.

## Graph scopes and extension read plans

**Before:** Native relationship reads/includes could bypass repository decorators; computed getters and hooks could implicitly initialize whole ORM collections. Deep filters discarded by PHP could turn into unfiltered reads. Standalone write serialization ignored Symfony's YAML metadata; required-DI profiles failed compile-time lookup.

**After:** Graph reads dispatch through the configured repository. Native related pages use SQL membership and target pagination; simple linkage uses identifiers, joined linkage hydrates only its page. Raw depth is checked before parsed-filter lookup. Write serialization uses the configured metadata factory; DI profile requirements are validated after service construction. Resources without SHOW omit their resource self link.

**Why:** Scopes must survive every transport path, and read costs require an explicit provider plan.

**Migration:** Review repository decorators for optional `Criteria::identifiersOnly` results, implement bounded/scoped computed readers, declare hook reads, then opt into `relationships.unplanned_read_policy: reject`. Keep `legacy` only where application-specific getters have an understood cost. Computed endpoints need a custom `TypedRelationshipReader`. Do not rely on a self link for resources that expose no SHOW route. See [graph read contract](docs/architecture/relationship-graph-reads.md).
