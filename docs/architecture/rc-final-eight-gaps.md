# RC: final eight consumer gaps

This pass changes only `jsonapi-symfony` on `fix/acceptance-gaps`. The external example application is not modified or executed. Bundle regressions establish the implementation contract; independent consumer verification remains required before declaring the reported external gaps resolved.

> Follow-up consumer findings: computed DTO relationships under always-linkage and opaque repository-decorator query cost were not covered by this initial pass. See the [follow-up report](rc-route-version-decorator-gaps.md); external gap resolution remains independently pending.

## Changes and executable evidence

| Gap | Root cause and implementation | Bundle regression |
| --- | --- | --- |
| PERFORMANCE-NPLUS1 | The representation preloader separately queried visible target IDs, including count and pagination, for each graph edge. `DoctrineCollectionQueryProviderInterface` exposes the native repository's complete collection predicate without executing SQL. The preloader embeds it in every bounded edge query. The repository locator selects the actual provider before using this capability; custom repositories without it retain their scoped fallback. | `DoctrineReadPathTestCase`: six native root relationships, four relationships on included owners, cold EntityManagers at page sizes 5 and 20, plain/to-one/to-many/nested shapes; related endpoint representation; target ReadHook scope; PostgreSQL and MySQL. |
| VERSION-RESOLVER-CONTEXT | Query projection already resolved the negotiated version, but the document builder attempted to read every persistence attribute from a smaller DTO. For an explicitly selected DTO projection, available readable DTO fields constrain the metadata-owned API attributes. API field names take precedence over entity property aliases for DTO reads. The builder never changes shared resource metadata. | `RcProfileContractTest::testVersionResolverReceivesCurrentRequestContextForBothReads`: ordinary item/collection, sparse fields, negotiated DTO values and subsequent entity fallback. |
| DX-TYPED-PERSISTER-DISPATCH | Write controllers consume ResourceProcessor; documented `jsonapi.persister` services were disconnected. `ResourceProcessorLocator` adapts TypedResourcePersister, preserving create/update/delete and fallback processors. Doctrine single-resource writes allow a registered non-ORM typed persister to own its persistence without enlisting an unrelated manager. Atomic retains strict preflight. | `RcContainerContractTest`: generated POST/PATCH/DELETE under both custom and Doctrine provider modes; `DoctrineTransactionManagerTest`: unrelated manager untouched and non-ORM Atomic rejected before callback. |
| FILTER-HANDLER-LOGICAL-COMPOSITION | Handlers previously added global WHERE clauses and were skipped during AST compilation. Each handler now produces its own AST leaf via an isolated root-ID subquery. Its INNER JOIN cannot remove a root accepted by an alternative OR branch. Parameters and aliases are renamed using the Doctrine lexer; literals and same-named fields are preserved. | `RcProfileContractTest::testCustomSearchRetainsBooleanAstCompositionAndIndependentParameters`: OR, nested AND/OR, repeated parameter names, item reads and INNER JOIN alternative without an author; `DqlRewriterTest`. |
| PROFILE-DEFAULT-WRITE | Symfony normalized resource-type map keys from kebab-case to underscores, so per_type lookup could not find the resource. Resource-type keys now remain literal in profiles.per_type, cache.last_modified.per_type and write.client_generated_ids. Per-type response profile headers are emitted without making that profile global. Explicit profile disabling also applies to per-type defaults. | `ConfigurationTest::testResourceTypeKeysKeepTheirPublicKebabCaseSpelling`; compiled-kernel audit default/constructor DI test; persisted Doctrine create/audit test without explicit negotiation. |
| PROFILE-AUDIT-META | AuditTrailDocumentHook was a placeholder and DocumentHook had no resource-meta callback. Optional ResourceMetaHookInterface adds resource-level metadata without changing DocumentHook. Audit reads configured scalar/date fields and emits data.meta.audit when enabled. A compiler method call merges bundle configuration with explicit AuditTrailProfile service settings, preserving constructor DI. | `RcProfileContractTest::testPerTypeAuditHooksWithoutNegotiationPersistAndExposeResourceMeta`; `RcContainerContractTest::testPerTypeAuditDefaultsKeepConstructorDiAndBundleConfiguration` verifies expose_in_meta=false survives a service override. |
| CACHE-VERSION-STRATEGY | EtagGeneratorInterface always aliased HashEtagGenerator. The extension now chooses VersionEtagGenerator for strategy=version. A missing application version yields no generated ETag, without a hash fallback. | `RcContainerContractTest::testVersionStrategyUsesHeaderAndDoesNotFallBackToHash`. |
| CACHE-COLLECTION-LAST-MODIFIED | The resolver always computed a maximum or current time. Collection documents now mark their response shape, and the resolver returns null for disabled collection maxima. Explicit Last-Modified headers and item validators remain supported. | `LastModifiedResolverTest`; `RcContainerContractTest::testCollectionLastModifiedDisabledInCompiledContainer`. |

## Query guarantees

The native Doctrine fetch plan still loads a bounded root page, scalar relationship linkage and separate included models. It does not fetch join multiple collections. Visibility predicates include parsed graph query hooks, read hooks, filter ASTs and custom conditions, and are applied to identifier probes, linkage and counts. Include and identifier limits still run before unbounded hydration.

Cold-page regression budgets are at most 12 statements for ordinary collections, 18 for to-one includes, 24 for to-many includes, 32 for nested includes and 15 for related collection representations. Sparse attribute-only pages retain the two-query shape in these fixtures. Increasing page size from 5 to 20 must preserve the query count for the tested graph. These are regression budgets, not a universal promise for every application's graph or hook. Batch chunking also contributes to cost for sufficiently large graphs.

A custom repository that overrides visibility through findCollection inherits a safe fallback: the base collectionQuery returns null unless the subclass explicitly overrides that capability too. An optimized subclass must provide equivalent visibility in collectionQuery. Providers may return null when they cannot safely project all visibility predicates. Composition without this capability also retains the fallback. Custom/computed relationship readers and document hooks retain the bounded-loading requirements documented in the relationship graph pass.

## Provider and transaction guarantees

`TransactionManager` and `ResourceProcessor` signatures remain unchanged. `ResourceWriteTransactionManagerInterface` is an optional single-resource CRUD capability. A legacy non-ORM typed persister owns its single-write validation, hooks and persistence guarantees. Registering a persister does not grant Doctrine transaction protection to another persistence system.

Atomic does not use this single-write escape path. The built-in Doctrine provider requires one ORM manager/connection boundary; non-ORM resources without a declared Doctrine boundary produce 409 before the first mutation, including a one-type Atomic batch. Independent managers/connections remain rejected. Per-operation flushes may occur inside the transaction; commit occurs only after all operations succeed. Custom applications needing Atomic must configure an appropriate transaction manager for their provider.

## Migration and extension details

- Use the actual JSON:API type spelling for keyed configuration, including hyphens. Underscored aliases that accidentally depended on Symfony normalization no longer match a kebab-case resource.
- A DTO projection can expose a subset of registered API attributes. It cannot implicitly expose unregistered DTO fields or relax sparse-field validation. Entity projections retain strict property reads.
- Filter handlers supply a WHERE predicate and may join relationships within that predicate's subquery. They are not collection pagination/sort or projection hooks. Prefer repository/query hooks for those operations.
- `data.meta.audit` contains configured available `createdAt`, `updatedAt`, `createdBy`, `updatedBy`; date values use ISO 8601. `expose_in_meta=false` suppresses it. No relationship traversal is added by audit metadata.
- With version ETags, the application supplies `X-Resource-Version`; no value means no bundle-generated ETag. Applications enabling write preconditions with this strategy must also supply a current representation version for write validation.
- Explicit application Last-Modified headers take precedence over automatic collection maximum configuration.

## Validation

Bundle QA on 2026-10-06:

- Full PHPUnit: **1255 tests, 6999 assertions, 6 skipped**, no failures or errors; PHP 8.4.26. JUnit artifact: `reports/rc-final-eight-bundle.xml` (local generated report).
- PostgreSQL/MySQL read-path and profile regressions: 78 tests/1652 assertions before the additional subclass scope test; the added test passed on both platforms (2 tests/16 assertions), and all are included in the final full suite.
- HTTP/cache/transaction targeted suite: 33 tests/80 assertions; DI fallback regressions: 11 tests/52 assertions.
- PHPStan: no errors. Deptrac: no violations, warnings or errors. PHP CS Fixer dry-run: no fixes. Composer validation: valid.
- Rector full dry-run: no analysis errors, 193 files with proposed modernization changes, including 17 touched by this patch. These proposals were reviewed and are not applied as a bulk RC-gap change.

 External acceptance/torture execution is intentionally deferred to its independent repository. `ATOMIC-VALIDATION-BOUNDARY` (409 rather than 422 for the classified validation case) remains unchanged. This patch adds no distributed transactions, replication management, sharding engine or automatic retries.
