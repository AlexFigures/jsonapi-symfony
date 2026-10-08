# Production policies and boundaries

These policies define the 1.0 behavior confirmed by the bundle suites and independent consumer verification. Evidence is revision-specific; installation of the published final package is verified separately. [Support contract](../reference/support-contract.md) maps the implementation to evidence.

## Atomic and single writes

The built-in Doctrine Atomic adapter preflights the complete batch before operation zero. It requires one Doctrine manager and its connection. Independent connections, shards and separate managers sharing a connection are rejected with 409 before mutation. Related resource types must resolve to that same supported boundary.

Flushes may occur inside the transaction to obtain generated IDs for subsequent lid references. There is one commit after all operations succeed; a later failure rolls back earlier flushes. Unrelated managers are never enlisted. No distributed transaction or 2PC is provided.

An unmapped resource handled by a matching typed persister may execute a single write using the persister's own persistence semantics. This does not make it eligible for Doctrine Atomic execution. Custom transaction providers must explicitly implement and verify the guarantees they advertise.

## Concurrent write preconditions

For the configured Doctrine guard, the root row is locked inside the write transaction. The current representation is rebuilt after lock acquisition and the original `If-Match` is evaluated before mutation. Two writers presenting the same validator cannot both change the root while retaining that validator: a competing changed write gets 412. `If-Match: *` keeps existence semantics.

This boundary protects the current root. Application SQL, other resources and changes to a related resource performed outside that boundary require their own concurrency policy. Non-Doctrine providers must implement equivalent CAS, optimistic revision or locking behavior; a no-op guard cannot provide the concurrent-write guarantee.

With `cache.etag.strategy: version`, applications supply `X-Resource-Version`. No hash validator is substituted when that value is absent. Write validation must obtain the current version inside the protected boundary.

## Bounded query and representation work

The native Doctrine read path selects distinct roots before representation hydration, then loads required relationships in batches. The fetch plan considers sparse fields, linkage, includes and declared profile needs. Query count depends on graph shape and batch chunks rather than one query per root; it is not a universal fixed SQL count.

Repository visibility remains part of native edge queries. A subclass overriding `findCollection()` does not automatically opt into the base query projection; custom projection must preserve its complete visibility semantics. Otherwise the bounded scoped fallback applies. Repository decorators must explicitly preserve the query capability with the same outer policy to retain native absolute SQL budgets; opaque wrappers use the safe, more expensive fallback.

To-many sort paths have ambiguous ordering unless a handler defines aggregate semantics. Review the strict collection-sort policy before exposing them. The 1.0 default is `collection_sort_policy: legacy`. Set `reject` and provide explicit aggregate handlers when deterministic collection sorting is required.

A starting production policy, deliberately stricter than some current defaults:

```yaml
jsonapi:
    relationships:
        linkage_in_resource: when_included
        unplanned_read_policy: reject
    limits:
        filter_max_depth: 8
        filter_max_nodes: 100
        filter_max_operands: 200
        include_max_depth: 3
        include_max_paths: 20
        included_max_resources: 250
        relationship_max_identifiers: 1000
        complexity_budget: 200
```

Structural filter depth/nodes/operands and weighted complexity are checked before repository execution. IN/NOT IN values count individually. Zero disables the corresponding configurable limit; do not disable budgets without an application-level replacement.

Included identities are budgeted before hydration. Linkage has a separate identifier budget, including standalone endpoints. Overflow returns a controlled error; valid documents are not silently truncated. Native related collections page in SQL, while joined linkage hydrates only its selected page.

`always` linkage may require identifiers for every visible relationship in a resource document. `when_included` limits linkage to included relationships; `never` avoids resource-document linkage. These policies do not remove standalone relationship endpoints or replace endpoint authorization.

## Custom/computed relationships and hooks

Computed relationships should implement bounded batch reading through `RelationshipBatchReaderInterface`; endpoint readers must also enforce target scope and pagination. Hooks that need identifiers, models or counts should declare fetch requirements through the relevant optional hook interfaces. The strict `reject` policy detects unplanned relationship access instead of triggering a lazy full-collection fallback.

A negotiated DTO may omit persistence computed getters. In legacy mode, the Doctrine preloader batch-loads the selected persistence owners as separate getter sources; it keeps DTO attribute values and honors readable DTO getters. Strict rejection and explicit batch readers remain the production alternatives.

Legacy getters and undeclared application SQL have no automatic query-cost guarantee. The bundle cannot bound an arbitrary callback that loads an entire graph internally. Applications must supply batch loaders, honor budgets and test their own query shape. See [extension contracts](../api/public-api.md) and [relationship graph design](relationship-loading.md).

## Application and infrastructure responsibilities

Applications own authorization, tenant scope, indexes, database routing, custom persistence, external services and topology. The bundle integrates with ManagerRegistry routing and primary/replica setups; it does not implement replication management, replica-lag compensation across independent GETs, sharding, a tenant framework or global deadlock retry.

Unsupported composite Doctrine identifiers fail during discovery. Integer, UUID and natural string IDs use the single-identifier path. Audit/cache/profile semantics and validation should be tested at the application's HTTP boundary before enabling them in production.

## Relationship endpoint authorization

Bind `AlexFigures\JsonApi\Http\Authorization\RelationshipAuthorizerInterface` to an application service. Its `isGranted(Request $request, string $type, string $id, string $relationship, RelationshipOperation $operation): bool` receives the source resource identity, relationship name, request and operation. The application can delegate to its own voters or permission service; the bundle does not require Symfony Security.

```yaml
# config/services.yaml
services:
    App\JsonApi\RelationshipAuthorizer: ~
    AlexFigures\JsonApi\Http\Authorization\RelationshipAuthorizerInterface:
        alias: App\JsonApi\RelationshipAuthorizer
```

The public enum distinguishes `READ_LINKAGE` (GET/HEAD of `/relationships/{rel}`), `READ_RELATED` (GET/HEAD of `/{rel}`), `REPLACE` (PATCH), `ADD` (POST), and `REMOVE` (DELETE). A false result produces a JSON:API 403 with code `forbidden` and title `Forbidden`. Denied writes never call the updater, acquire concurrency protection, open the operation's transaction or emit a relationship-changed event. Authorization runs before write validator reads, including requests missing If-Match. Successful writes may evaluate the policy again inside concurrency protection; policies must be side-effect free and must not mutate state.

This policy covers standalone generated relationship endpoints, including typed providers. Resource-body relationship changes, Atomic Operations, collection linkage and included resources retain their existing provider/profile authorization responsibilities. It is not a graph-wide visibility filter or an automatic tenant policy. The internal validator representation read after an authorized write does not require a separate `READ_LINKAGE` grant. Applications may permit mutation without permitting a standalone linkage GET.

Without an application authorizer, endpoint behavior is unchanged. Use generated routes and the public authorizer alias; internal controller/checker construction is not a public integration contract.
