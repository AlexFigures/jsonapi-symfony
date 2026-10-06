# RC follow-up: resource prefixes, DTO relationships and decorator cost

Scope: bundle implementation/tests only, on `fix/acceptance-gaps`. The external application's source was inspected read-only to understand its contract. Its tests were not modified or run. Independent consumer confirmation remains pending.

## RESOURCE-ROUTE-PREFIX

`ResourceMetadata` already retained `JsonApiResource.routePrefix`, but `JsonApiRouteLoader` ignored it and used the global prefix. Generated resource CRUD, OPTIONS, linkage and related routes now use the resource override, falling back to the global value only for null. Trailing slashes are normalized; an empty/root override supports root-level paths.

Bundle evidence: `JsonApiRouteLoaderTest::testResourcePrefixAppliesToEveryGeneratedRoute` checks all generated paths. `RcContainerContractTest::testResourcePrefixControlsHttpRoutesAndRepresentationLinks` boots the compiled kernel: the override item/collection URL responds, item/pagination links use the override, the former global item URL returns 404, and another resource retains its global URL.

## VERSION-RESOLVER-CONTEXT

The earlier fix handled missing DTO attributes. Its regression used the DocumentBuilder default linkage policy, which did not evaluate every relationship getter. Under `linkage_in_resource=always`, an entity-declared computed relationship getter was read from a DTO that intentionally had no such getter. This produced `NoSuchPropertyException` in ordinary SHOW, INDEX and related representations.

The native preloader now batch-hydrates persistence owners for this legacy fallback, using only already selected owner IDs and chunks of at most 256. Sources are request-local and distinct from representation models in RelationshipReadMap. Attribute serialization continues using the negotiated DTO. Existing readable DTO getters take precedence over persistence sources. Native mapped linkage continues using planned scalar results. The strict `unplanned_read_policy=reject` branch still rejects unplanned computed access; a registered batch reader still takes precedence over legacy fallback.

This does not give arbitrary legacy getters an automatic SQL-cost guarantee. Getter code can perform application SQL or initialize collections. A bounded batch reader is the production path for such relationships.

Bundle evidence: `RcProfileContractTest::testVersionedDtoWithComputedLinkageAcrossHttpReadControllers` reproduced the exception before the fix, then covers SHOW, INDEX, related collection and related to-one with ordinary fields and computed/native linkage. It asserts DTO attribute values, stable identifiers, missing persistence attributes, a separate persistence source, strict rejection and subsequent entity fallback without the profile.

## PERFORMANCE-NPLUS1 remains open in the consumer

The latest consumer report demonstrates excess absolute query cost; it does not demonstrate linear growth between pages of 5 and 20. Read-only inspection found that the torture application decorates ResourceRepository with TenantRepository. That decorator implements ResourceRepository but does not expose the optional DoctrineCollectionQueryProviderInterface. The outer repository must remain authoritative for checks/scope. Automatically extracting or calling its inner provider would be unsafe for decorators that also restrict visibility.

The bundle therefore uses its scoped fallback: repository count/page discovery plus relationship queries. This preserves visibility and bounded loading but adds SQL per graph edge. Native query planning cannot be inferred through an opaque wrapper.

`ScopedRepositoryDecorator` and `DoctrineReadPathTestCase::testDecoratorQueryPlanningPreservesScopeAndGraphBudgets` test both modes on PostgreSQL and MySQL. A tag hidden by the decorator is absent from linkage and includes in both modes. Query counts are identical at page sizes 5 and 20:

| Bundle fixture graph | Explicit scoped query planning | Scoped fallback |
| --- | ---: | ---: |
| Plain collection | 8 | 20 |
| To-one include | 14 | 34 |
| To-many include | 10 | 22 |
| Nested includes | 18 | 50 |
| Sparse attributes | 2 | 2 |

These are measurements of bundle fixtures, not fresh external metrics. The query-planning mode satisfies the existing absolute budgets; fallback remains more expensive. No budget assertion was weakened.

### Required integration step

A decorator opting into the current internal Doctrine query capability must implement its complete read policy in `collectionQuery` as well as ordinary reads. For a guard-only decorator, run the same guard before forwarding; for a visibility decorator, also apply the same criteria/predicate. If policy is applied after fetching rows and cannot be represented in the query, return null and retain the safe fallback.

When the inner service is ResourceRepositoryLocator, choose its per-type provider through `getRepositoryForType(type)` before testing that provider's capability. Forward only after the outer policy has been applied. Nested decorators must each opt in. Do not assume the inner service is always GenericDoctrineRepository, and do not ignore an overridden findCollection scope.

The complete scope-preserving example is the bundle fixture [ScopedRepositoryDecorator](../../tests/Integration/ReadPath/ScopedRepositoryDecorator.php). This interface is currently internal and needs an explicit extension-contract decision before the API freeze. Consumer integration and its five performance scenarios must be independently rechecked; this patch does not declare PERFORMANCE-NPLUS1 resolved.

## Validation

Targeted prefix/version tests: 3 tests, 97 assertions. Decorator scope/cost tests: 10 tests, 1128 assertions on PostgreSQL/MySQL. Full bundle suite: **1268 tests, 8224 assertions, 6 skipped**, no failures/errors, PHP 8.4.26. Generated JUnit artifact: `reports/rc-route-version-decorator.xml` (ignored local report). PHPStan: no errors; Deptrac: zero violations/warnings/errors; PHP CS Fixer: no remaining fixes. Maintained documentation links and the full tracked Markdown link-target check pass. External tests were not run.
