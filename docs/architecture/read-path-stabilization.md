# Read-path stabilization toward 1.0

Status: implemented in `fix/acceptance-gaps`, with bundle PostgreSQL/MySQL regression coverage. External acceptance and torture confirmation are a separate pending step. This is not a claim that the 1.0 release gate is complete.

## Implemented architecture

| Class / contract | Responsibility |
| --- | --- |
| `GenericDoctrineRepository` | Apply filters, custom conditions and sort handlers to root selection; page roots before representation relationship loading. |
| `DoctrineRootPaginator` | Use Doctrine's paginator with output walkers when root selection contains joins. Plain roots retain the inexpensive count + page queries. |
| `DoctrineReadProjection` | One scalar DTO projection implementation for root pages and included targets. Root page IDs preserve selection order independently of DTO identifier aliases. |
| `IdentifierParameters` | Convert API identifiers through their Doctrine type before array parameter binding, including UUID objects. |
| `RepresentationFetchPlanner` / `RelationshipFetch` | Derive independent requirements for linkage, included representations, nested paths and profile counts from metadata, sparse fields and linkage policy. These classes contain no Doctrine queries. |
| `RepresentationPreloaderInterface` | Optional provider capability. Existing repository and transaction interfaces remain unchanged. |
| `DoctrineRepresentationPreloader` | Discover identifiers, reserve include budget, batch linkage and count queries, hydrate selected targets, then advance through nested include frontiers. |
| `RelationshipReadMap` | Store identifiers, models and counts for one document build. Distinguish loaded empty relationships from unsupported/unloaded ones. Never install a partial ORM collection. |
| `FetchPlanHookInterface` | Optional count requirements for profile document hooks. Built-in relationship counts use grouped queries instead of per-root collection access. |
| `DocumentBuilder` / `ProfileContext` | Consume the local read map during resource/linkage/include serialization and profile hooks. No read map lives on a shared service. |

### Query strategy

1. Select the root page, applying filter and sort joins to the pagination query. With joins, Doctrine's paginator obtains a distinct root page before entity hydration; pagination no longer limits the joined row stream directly.
2. For a DTO, project only selected root IDs and restore their selection order. An internal root-ID column decouples this from the names of DTO constructor arguments.
3. For each planned relationship edge, query scalar owner/target identifiers for the whole frontier. Source IDs are chunked in batches of 256. Separate sibling collections get separate queries; there is no graph-wide collection fetch join.
4. For an include, first query distinct *new* target IDs, excluding primary resources and identities already reserved in this document. Return at most remaining budget + 1. Reject overflow before loading any bodies for this edge. Sharing one target between many owners consumes one included-resource reservation.
5. Fetch identifier linkage independently, bounded by the document's identifier budget. Detect an extra identifier and reject rather than truncate a successful document. ORM `OrderBy` on the terminal collection is retained; identifiers provide a stable fallback/tiebreaker. PostgreSQL's distinct/order requirements are handled by selecting the ordering fields as scalars.
6. Load included target entities or DTO projections in batches, then plan the next include frontier. Counts requested by profiles use `COUNT(DISTINCT target_id)` grouped by owner.
7. Serialize from the map. Primary resources do not appear a second time in `included`. Explicit includes retain full linkage even with the `never` policy; sparse fields may omit that linkage as permitted by the specification. These requirements follow [JSON:API compound documents](https://jsonapi.org/format/#document-compound-documents).

For bounded pages of 5 and 20 roots, SQL counts are equal for plain linkage, to-one includes, to-many includes, sibling/nested includes and sparse attribute-only fields. Bigger frontiers can require additional chunks; cost depends on graph shape and bounded chunk count. This does not promise a fixed SQL count for arbitrary custom projections or hooks.

Identifier discovery and hydration are separate reads, not a serializable snapshot of the whole graph. A concurrently deleted target is omitted from hydrated included models; newly discovered identities in linkage are reserved before hydration. There is no replication-lag compensation between independent GET requests.

### Limits and linkage policy

```yaml
jsonapi:
    relationships:
        linkage_in_resource: when_included
    limits:
        included_max_resources: 1000
        relationship_max_identifiers: 10000
        filter_max_depth: 8
        filter_max_nodes: 100
        filter_max_operands: 200
        complexity_budget: 200
    performance:
        doctrine:
            collection_sort_policy: reject
```

All numerical limits are nonnegative; zero disables the corresponding guard. The new identifier budget bounds the scalar owner/relationship/target entries loaded for a document, including linkage required for includes. Included resources have a separate distinct-identity budget. The SQL probes bound returned identifiers and hydrated targets, not all database work involved in evaluating arbitrary joins or aggregates; applications still need suitable indexes.

`always` retains linkage for visible relationships and can exhaust the identifier budget on a large graph. `when_included` avoids unsolicited linkage, while still honoring explicit sparse relationship fields. `never` omits optional linkage; explicit include paths still require full linkage unless those fields are excluded through sparse fieldsets. The default `always` is retained for compatibility; omission of optional linkage is itself allowed by JSON:API.

Stock create/update responses identify their already-flushed representation. They use the same relationship snapshot/order as GET, but do not acquire a new read-budget rejection after a successful commit. Unflushed/custom write representations retain the property-access fallback. Mutation limits remain application/resource policy; read limits are not a substitute for validating a write before persistence.

### Collection-valued sorting

A generic `sort=tags.name` has no declared aggregate semantics. `collection_sort_policy: reject` returns 400 with code `collection-sort-unsupported` and `source.parameter: sort` before SQL. Registered sort handlers run first and can define `MIN`, `MAX`, a correlated aggregate or another domain-specific order. The regression suite exercises an explicit correlated `MIN` handler and verifies root page size and total.

The default remains `legacy` to preserve the previously accepted alias sorting behavior. Legacy collection sorting is a compatibility path, not a stable production ordering contract. A switch of the default to `reject` remains a deliberate pre-1.0 freeze decision, requiring migration notes and external validation. To-one and root sorting remain supported with the root ID as a stable tiebreaker.

## Bundle regression coverage

`tests/Integration/ReadPath/DoctrineReadPathTestCase.php` runs on PostgreSQL and MySQL:

- 5/20 root comparisons for plain linkage, to-one/to-many/nested includes and sparse fields;
- collection filter with two matching joined rows per root, correct totals, 20 distinct roots and stable next page;
- strict collection-sort rejection before SQL and a registered aggregate sort;
- early include overflow with a `remaining + 1` SQL limit and zero hydrated target models;
- independent linkage budget without target hydration;
- grouped profile counts without initializing collections;
- DTO root pagination, implicit/explicit projections and UUID ID arrays with a DTO identifier alias;
- an alias traversing a join entity loaded as one batched edge;
- shared-resource include deduplication, repeated builds on one builder, mapped collection order, sparse include linkage and successful write representation;
- required full linkage under `never` when an include is requested.

The real two-connection `ConcurrentWritePreconditionsTest` also uses the preloader with the locking/write pipeline. Configuration and DI tests cover budget validation, policy values and optional service wiring. Existing nested-include tests now select one primary root and assert exactly two other articles, with no duplicate primary identity; they continue checking the actual nested traversal.

## Remaining read-path release blockers

### Dedicated relationship endpoints

`GenericDoctrineRelationshipHandler::getRelatedCollection()` and `getToManyIds()` still have a legacy in-memory collection path. `DocumentBuilder` preloading cannot undo the cost incurred before it receives those models. Do not advertise every relationship endpoint as bounded yet.

Implement `DoctrineRelationshipQueryFactory` to resolve the owner, API alias path, target class, identifier mapping and selected manager once. For related collection reads, build an owner-membership `EXISTS`/target-ID subquery and feed the root target query through the same filter/sort/distinct-root paginator and DTO projection. Avoid loading the owner's whole collection or first materializing all related IDs. For linkage reads, use scalar target-ID selection plus a distinct count, ordered and paginated in SQL. Keep `RelationshipReader` signatures; the existing handler can delegate these native association paths to the factory and retain a documented custom/computed fallback. Test large owners, empty relationships, duplicate paths, UUIDs, filters/sort, stable pagination and counts on both databases.

### Computed relationships, attribute paths and custom hooks

Mapped association paths, including join-entity aliases, are batched. Computed relationships and opaque application hooks retain the original property-access behavior and can still perform arbitrary queries. Nested relationship-backed attribute getters can likewise cause application-specific reads. Automatic cost guarantees cannot cover arbitrary user code.

Extend the optional fetch declaration with explicit identifier/model/scalar requirements when a concrete consumer needs them. Add planned scalar attribute reads to the map and have `DocumentBuilder::buildAttributes()` consume them. For a computed relationship, register a provider-specific batch reader rather than introspecting arbitrary getters. Preserve the generic core: neither a Doctrine query builder nor an EntityManager should appear in its requirements. Add query-count regressions for each supported declaration and diagnostics for requirements a provider cannot satisfy.

### Wider provider/database verification

The current tests cover the installed ORM/DBAL versions and PostgreSQL/MySQL graph shapes. DTO order/filter/UUID coverage is present, but arbitrary custom groupings, inheritance, joined projections and versioned view definitions need additional compatibility coverage before making broader promises. No large fetch join, blanket DISTINCT shortcut, global SQL row cap, distributed transaction or automatic deadlock retry is introduced.

## Bundle verification for this branch

The complete bundle suite passed locally with **1172 tests, 6512 assertions and 6 skips**. PHPStan passed without errors. Architectural dependency checks passed with zero violations after separating pure read-map data from representation planning and internal transaction adapters. The external example application was neither changed nor executed; its independent verification is still pending. CI changes have been validated locally but have not been executed by GitHub Actions in this session.
