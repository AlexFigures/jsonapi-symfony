# RC consumer contracts: metadata, media and profile options

Scope: `fix/acceptance-gaps`, bundle implementation and tests. The consumer report was read from `example-jsonapi-bundle/docs/bundle-implementation-gaps.md`, which names tested bundle revision `8750b80`. Its assertions define the targets below. The external project was neither changed nor executed. Independent consumer confirmation is still required.

Presence of a similar existing regression was not sufficient evidence to dismiss these findings. New tests reproduced the class collision, relationship policy, media defaults/legacy policy, disabled HEAD and internal DTO failures before implementation.

## Runtime and extension evidence

| Gap | Cause and implementation | Bundle evidence |
| --- | --- | --- |
| VERSION-RESOLVER-CONTEXT | Prior commit `e3aaf4f` supplies negotiated context and separate persistence sources for computed getters absent from a DTO. DTO attributes and stable identity remain representation-owned. | `RcProfileContractTest::testVersionedDtoWithComputedLinkageAcrossHttpReadControllers`: SHOW, INDEX, related collection and related to-one; strict fallback rejection and subsequent non-profile reads. Existing sparse/projection coverage also remains required. |
| RESOURCE-ROUTE-PREFIX | Prior commit `e3aaf4f` applies resource overrides to all generated paths and links. | `JsonApiRouteLoaderTest::testResourcePrefixAppliesToEveryGeneratedRoute`; compiled-kernel `testResourcePrefixControlsHttpRoutesAndRepresentationLinks`. |
| MEDIA-DEFAULT-POLICY | Decoder independently hardcoded JSON:API, while response negotiation kept controllers' hardcoded MIME. Decoder now consults the configured provider; generated responses use the negotiated type/default before cache/profile response processing. Custom/native routes retain their explicit response types. | Compiled-kernel `testConfiguredDefaultMediaAppliesToGeneratedReadsAndWrites`: modern/legacy configuration, GET default, POST/PATCH, explicit JSON:API negotiation, rejected Content-Type/Accept. |
| PROFILE-AUDIT-ATTRIBUTE-FIELDS | The profile constructed its write hook without a resource registry; attribute field lookup was unreachable. Compiler injection also handles application-configured profile services, and lookup uses dataClass. Audit document fields follow the same attribute precedence. | Compiled-kernel `testConfiguredAuditProfileResolvesRenamedAttributeFieldsThroughRegistry`; PostgreSQL `testRenamedAuditAttributeFieldsPersistOnCreateAndUpdate` flushes/clears/reloads timestamps and different actors. |
| RESOURCE-REGISTRY-PROJECTION-COLLISION | Persistence/view aliases overwrote declared resource-class entries. Registration now indexes declared classes first, then unique aliases. | `RcPublicMetadataContractTest::testPrimaryResourceWinsOverProjectionAliasesInEitherOrder`; ambiguous aliases without a primary fail discovery deterministically. |
| RESOURCE-RELATIONSHIP-POLICIES | Resource-level defaults were retained separately but never applied to effective relationship metadata. Registry extraction now applies resource policy only when the relationship has no explicit policy. | `RcPublicMetadataContractTest::testResourceRelationshipPolicyIsDefaultAndExplicitPolicyWins`. |
| RESOURCE-TAG-DISCOVERY | Discovery replaced the registry list with directory results. Explicit/autoconfigured tagged services now merge with discovery, deduplicate the same class and diagnose mismatched/duplicate types. Constructors are not instantiated for discovery. | Compiled-kernel `testTaggedResourceOutsidePathsHasRoutesAndRequiredReadIdentity` uses a tagged class outside resource_paths. |
| DOCS-EXPOSE-ID-CONTRACT | Read/identifier schema requiredness and nullability incorrectly depended on exposeId. Both schemas now require non-null id, matching transport identity. | Same compiled-kernel test checks HTTP identity and both generated schema shapes with exposeId=false. |
| DOCS-METADATA-CONTRACT | Concrete metadata omitted the promised interface. It now implements getType(). Documentation clarifies this is an identity interface; a custom registry must still supply the supported complete ResourceMetadata shape. | `RcPublicMetadataContractTest` checks the default implementation and type value. |
| DX-PUBLIC-SIGNATURE-INTERNAL-DTO | Public signatures exposed internal annotations. RelationshipReadMap, RelationshipReadRequirements and CustomRouteMetadata are supported public values. | `RcPublicMetadataContractTest::testExtensionDtosArePublic`; existing serialization, batch completeness/budget and custom-route tests cover their behavior. |
| CONFIG-HEAD-DISABLED | Configuration never affected execution, and Symfony automatically matches HEAD to GET. Generated routes now carry the HEAD policy, enforced before controller execution even with strict negotiation disabled; OPTIONS removes HEAD. | Compiled-kernel `testHeadDisabledRejectsAutomaticGetMatchingAndOptionsAgrees`: collection, item, related and linkage; GET remains available. |
| PROFILE-REL-COUNT-CONFIG | Hook hardcoded count. It now uses relationship_meta_key for planned and fallback counts; empty keys are invalid. | `RcProfileOptionsContractTest`; PostgreSQL `testRelatedCountPolicySuppressesCountFetchAndDocumentMeta`. |
| PROFILE-REL-COUNT-RELATED-POLICY | Fetch planning and document hook ignored endpoint context. RelatedController marks the request, ProfileContext preserves this context through type/read-map copies, and optional ContextualFetchPlanHookInterface supplies endpoint-aware requirements. | Same tests check both enabled/disabled behavior, absent planned counts when disabled, and cardinality output without an additional count key. |
| PROFILE-SOFT-DELETE-ACTOR-META | Document hook was a placeholder. ResourceMetaHookInterface now reads the application-owned actor from SoftDeletable.deletedByField/config without adding an attribute or assigning the actor. | `RcProfileOptionsContractTest::testSoftDeleteReadsApplicationOwnedActorFieldWithoutPublishingItAsAttribute`. |

## Public decisions made in this pass

- Retain TypedResourcePersister and its tested create/update/delete adapter. Provider-specific persistence, validation and transaction guarantees remain the implementation's responsibility.
- Activate TypedRelationshipReader/TypedRelationshipUpdater through autoconfiguration or `jsonapi.relationship_reader` / `jsonapi.relationship_updater` tags. Dispatch is by source resource type; the first matching service wins in tagged priority order, otherwise the configured/native provider is used. This is an endpoint seam; it does not infer a representation batch plan or replace entity-body relationship processing.
- Keep RelationshipReadMap, RelationshipReadRequirements and CustomRouteMetadata in their existing namespaces as public DTOs. A read map is document/request-local; null means unplanned, an empty linkage means loaded-empty. Required identifiers, models and counts must be complete and bounded by the adapter before hydration.
- Publish DoctrineCollectionQueryProviderInterface and ResourceRepositoryLocator::getRepositoryForType as supported Doctrine/decorator surfaces. A decorator must enforce its own guards and visibility before forwarding; return null when those policies cannot be projected safely. No automatic decorator unwrapping occurs. Generic repository subclasses overriding collection visibility must explicitly supply an equivalent collectionQuery to opt in.
- Remove config-only `dx.*`, `errors.locale`, and Doctrine `enable_query_cache`, `query_cache_pool`, `enable_second_level_cache`, `hydrate_partial_by_fields`, `default_fetch`. These nodes and the `jsonapi.dx` / `jsonapi.errors.locale` parameters are removed before 1.0. Explicit configuration fails as unrecognized options; migration is required. Configure actual ORM caches/fetch metadata and application tooling directly. `head_enabled` and `collection_sort_policy` remain active options.

These specific decisions do not complete the full 1.0 API audit or release freeze.

## Consumer performance and boundaries

The latest consumer report states the tenant-safe query-plan bridge passed all preserved query budgets (8/8 plain, 18/18 to-one, 14/14 to-many, 26/26 nested, 9/9 related at pages 5/20). Those are supplied independent results, not a run performed here. This pass supports the capability used by that bridge and retains safe fallback when unavailable.

Atomic still supports one manager/connection boundary per batch, rejects unsupported boundaries before mutation, allows flushes inside the transaction and commits only after all operations succeed. No distributed transactions, replication management or sharding engine are added. Audit actor assignment and opaque hooks/custom relationship cost remain application responsibilities.

## Validation

Final bundle verification on PHP 8.4.26:

- Complete PHPUnit: **1287 tests, 8438 assertions, 6 skipped**, no failures/errors (6m44s). JUnit: `reports/rc-public-contract-bundle.xml` (local generated artifact).
- PHPStan: no errors.
- Deptrac: zero violations, warnings or errors.
- PHP CS Fixer dry-run: no fixes.
- Composer validation, git diff whitespace check and maintained documentation link checks: passed.

The initial full run caught three old DI/event expectations and a no-path discovery behavior change. Assertions now also check the actual native/configured fallback providers; the no-path/no-tag compiler pass remains a no-op. The complete suite was rerun after correction. A focused 61-test run also verified generated HEAD Allow/OPTIONS agreement, typed relationship endpoints and route loading after the final route-policy adjustment. Reader/updater HTTP regressions now cover both interface autoconfiguration and explicit service tags without autoconfiguration, including every updater method.

External acceptance confirmation remains pending for this patch. The full public API/configuration audit and RC gate are not declared complete.

## Relationship authorization and error type links

The two non-async status placeholders now execute regressions. [RelationshipMutationStatusTest](../../tests/JsonApiStatus/RelationshipMutationStatusTest.php) verifies a generated 403 with matching error status/code/title and no provider or transaction work. [RelationshipAuthorizationTest](../../tests/Functional/Regression/RelationshipAuthorizationTest.php) covers denied and granted to-one/to-many linkage, related GET/HEAD and every mutation, including write preconditions and a configured concurrency guard. Applications supply the public RelationshipAuthorizerInterface policy; resource-body/Atomic/graph-wide policies remain separate.

[ErrorObjectStatusTest](../../tests/JsonApiStatus/ErrorObjectStatusTest.php), [ErrorTypeLinkTest](../../tests/Unit/Http/Error/ErrorTypeLinkTest.php) and [ErrorTypeLinkResponseTest](../../tests/Functional/Regression/ErrorTypeLinkResponseTest.php) cover error type links in exception/fluent responses, optional omission, both links together, correlation/meta enrichment and Atomic pointer rebasing. The OpenAPI ErrorObject schema includes both error link members. Independent consumer confirmation remains separate from these bundle regressions.

Latest bundle verification after these additions and removal of config-only options: full PHPUnit **1,321 tests / 8,636 assertions / 4 skipped**. Only async creation/update/deletion/relationship 202 scenarios remain skipped. PHPStan and Deptrac passed; formatting and local documentation links passed. No external consumer tests were run for this verification.
