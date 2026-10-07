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

## Extension execution and effective configuration

**Before:** Registered custom operators, operation input DTOs, read/relationship hooks and several profile/configuration options were accepted as configuration but ignored or rejected at runtime. Documentation routes and schemas could advertise behavior different from the configured bundle.

**After:** Registered operators pass the filter parser; custom handlers retain application query parameters; built-in Doctrine reads and relationship mutations invoke their profile hooks. Create/update input DTOs are validated and passed to `WriteMapperInterface`, and reads choose their representation using the current negotiated profile context. Soft-delete visibility, flags, boolean strategy and soft/hard deletion are effective. Standalone linkage obeys identifier budgets, generated routes emit configured surrogate keys and Atomic parsing enforces the lid switch. Documentation operations, inherited fields, write groups, pagination and examples reflect their source contracts. The JSON Schema route and profile-validation command are registered.

**Why:** Public extension points and configuration must have executable effects before they can be frozen for 1.0.

**Migration:** Review input DTO defaults and write mappers, because these now execute; invalid input can return 422 before entity mutation. Audit hook restrictions that previously did not run. If physical removal is required while the soft-delete profile is active, explicitly set `profiles.soft_delete.delete_semantics: hard`; the configured default `soft` is now enforced. Remove non-JSON:API query parameters from ordinary CRUD requests; custom handlers retain them. Keep explicit linkage pages within `relationship_max_identifiers`. Validate profiles through `jsonapi:validate-profiles`. Negotiation now follows router/controller discovery, so routing errors can precede 406/415 errors. See [RC extension/configuration regression report](docs/architecture/rc-extension-gaps.md) for exact boundaries and independent verification status.

## Final RC consumer gaps

**Before:** resource-type configuration keys containing hyphens could be normalized to underscores; per-type default profiles and some field overrides silently missed their resources. Typed persister tags did not reach write controllers. Version DTO reads could require sparse fields to avoid missing persistence properties. Custom filter handlers could escape OR composition. Cache strategy/collection settings and audit resource metadata did not consistently take effect.

**After:** resource-type maps retain their exact names. Typed persisters dispatch through the processor locator; non-ORM single writes do not open unrelated Doctrine transactions. Atomic still rejects undeclared/non-ORM boundaries before mutation. DTO projections serialize their readable registered fields. Filter handler predicates and joins remain within their AST leaf. Version ETags use the application header without hash fallback; disabled collection maxima omit synthesized Last-Modified. Audit metadata uses `data.meta.audit`, respecting `expose_in_meta` even with profile constructor DI.

**Why:** configuration and documented extension points must have the same semantics in a compiled application as in isolated tests. Scope safety and read budgets must hold together.

**Migration:** use actual kebab-case resource names in `profiles.per_type`, `cache.last_modified.per_type` and `write.client_generated_ids`. Keep legacy typed persisters tagged `jsonapi.persister`, or enable interface autoconfiguration; unhandled types use the configured/default processor. A non-ORM persister owns single-write persistence and hooks; configure a suitable transaction manager for custom Atomic. Existing TransactionManager signatures are unchanged; ResourceWriteTransactionManagerInterface is an optional capability. Filter handlers must supply a WHERE predicate, with optional joins; use repository/query hooks for pagination, sorting or projections. Applications using version validators must supply `X-Resource-Version` for the current representation. Existing Doctrine subclasses overriding `findCollection` retain their scoped fallback automatically. To opt into native query projection, explicitly override `collectionQuery` with the same visibility rules; returning null also requests the fallback.

The native representation preloader now embeds the collection scope predicate in its bounded relationship queries instead of executing separate visible-ID count/page queries. It keeps independent include/linkage budgets and distinct-root pagination. See [the final eight-gap report](docs/architecture/rc-final-eight-gaps.md) for tests, SQL budgets and provider limitations. External consumer confirmation remains a separate release gate.

## Resource prefixes and DTO relationship sources

**Before:** resource routePrefix was retained in metadata but ignored by generated routes. A negotiated DTO missing persistence computed getters could fail under always-linkage.

**After:** generated routes and their links use the resource prefix override. Legacy DTO computed reads use separately batched persistence owners while attributes remain DTO-backed; strict unplanned-read rejection is unchanged.

**Why:** route declarations must match published URLs and choosing a representation must not require entity-only getters on that DTO.

**Migration:** use the declared resource URL and update clients relying on the accidental global URL. Use explicit computed batch readers for predictable production costs. Repository decorators should preserve their full scope when opting into Doctrine query planning; an opaque wrapper retains scoped fallback and its extra SQL cost. See the [follow-up report](docs/architecture/rc-route-version-decorator-gaps.md).

## Metadata, media and profile contracts

**Before:** generated writes rejected configured JSON MIME; response defaults, HEAD and count options were inert. Projection registration could replace a primary class lookup, tagged resources were omitted, and audit attributes were not consulted by the built-in profile service.

**After:** generated endpoints apply request/response policy; `head_enabled=false` rejects HEAD and removes it from OPTIONS. Count keys and related-endpoint suppression apply to fetch planning and output. Attribute audit fields are resolved using dataClass, with application-provided actors. Soft-delete actor metadata reads the configured application-owned field. Tagged resource services compose with discovery. Declared primary resource classes win over unique data/view aliases, independently of registration order; ambiguous aliases without a primary produce a configuration error. Read schemas always require non-null protocol id even with exposeId=false.

**Why:** public configuration and extension contracts must agree with generated transport behavior.

**Migration:** review previously inert settings before deploying. For shared data/view classes register a primary resource or use distinct classes; select projections explicitly by type. Register typed endpoint readers/updaters through autoconfiguration or their relationship tags; custom implementations own their scope/hooks and bounded fetching. Keep batch readers separate from endpoint readers. Public RelationshipReadMap, RelationshipReadRequirements, CustomRouteMetadata and the Doctrine query-plan capability retain their namespaces. Decorators must explicitly preserve all guards/scope in collectionQuery or return null.

## Deprecated config-only options

**Before:** `dx.*`, `errors.locale` and several Doctrine performance options parsed without changing runtime behavior.

**After:** explicit use emits deprecation diagnostics. Parsing/defaults remain compatible, but these settings are not supported features: `enable_query_cache`, `query_cache_pool`, `enable_second_level_cache`, `hydrate_partial_by_fields`, `default_fetch`.

**Why:** accepted configuration must not imply an unimplemented production guarantee.

**Migration:** remove those nodes. Configure Doctrine caches/fetch behavior in the application, and supply application localization/tooling directly. Keep the implemented `performance.head_enabled` and `performance.doctrine.collection_sort_policy` options as needed.
