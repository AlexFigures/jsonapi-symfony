# Extension contracts before the 1.0 freeze

This is an index of current extension points, not a frozen 1.0 declaration. Source signatures are authoritative during stabilization. `@api` and `@internal` annotations are inputs to the [public API audit](../release/public-api-audit.md); unmarked code is not automatically classified by namespace.

## Persistence

| Contract | Purpose |
| --- | --- |
| [ResourceRepository](../../src/Contract/Data/ResourceRepository.php) | Item, collection and related resource reads with Criteria |
| [TypedResourceRepository](../../src/Contract/Data/TypedResourceRepository.php) | Select a repository by resource type |
| [ResourceProcessor](../../src/Contract/Data/ResourceProcessor.php) | Create, update and delete processing |
| [ResourcePersister](../../src/Contract/Data/ResourcePersister.php), [TypedResourcePersister](../../src/Contract/Data/TypedResourcePersister.php) | Existing persister API and type dispatch adapter |
| [RelationshipReader](../../src/Contract/Data/RelationshipReader.php), [RelationshipUpdater](../../src/Contract/Data/RelationshipUpdater.php) | Endpoint reads and relationship mutations |
| [ExistenceChecker](../../src/Contract/Data/ExistenceChecker.php) | Existence probes |
| [ChangeSet](../../src/Contract/Data/ChangeSet.php), [ResourceIdentifier](../../src/Contract/Data/ResourceIdentifier.php), [Slice](../../src/Contract/Data/Slice.php), [SliceIds](../../src/Contract/Data/SliceIds.php) | Write changes, identifiers and pagination values |

Use [data-layer configuration](../guide/data-layer-configuration.md) to configure a provider. Typed persister services are selected by `supports(type)`; the legacy adapter does not automatically give an unmapped provider Doctrine transactions. A custom processor/provider must preserve its own validation, persistence and concurrency semantics.

## Relationship authorization

[RelationshipAuthorizerInterface](../../src/Http/Authorization/RelationshipAuthorizerInterface.php) and [RelationshipOperation](../../src/Http/Authorization/RelationshipOperation.php) are public endpoint extension points. Bind the interface to an application service to authorize linkage reads, related reads, replace, add and remove independently. See [production policies](../guide/production-policies.md#relationship-endpoint-authorization) for scope and execution order. Policies must be side-effect free; no configured authorizer preserves existing access behavior.

## Transactions and concurrency

[TransactionManager](../../src/Contract/Tx/TransactionManager.php) remains the base contract. Optional [ScopedTransactionManagerInterface](../../src/Contract/Tx/ScopedTransactionManagerInterface.php) and [ResourceWriteTransactionManagerInterface](../../src/Contract/Tx/ResourceWriteTransactionManagerInterface.php) distinguish batch scope from a single write. [WriteConcurrencyGuardInterface](../../src/Contract/Data/WriteConcurrencyGuardInterface.php) supplies persistence-specific protection.

See [production policies](../guide/production-policies.md) for the one-boundary Atomic model, generated-ID flushes and concurrent If-Match. Implementing a transaction interface alone does not prove cross-database atomicity.

## Bounded representation reads

[RepresentationPreloaderInterface](../../src/Contract/Data/RepresentationPreloaderInterface.php) and [RelationshipBatchReaderInterface](../../src/Contract/Data/RelationshipBatchReaderInterface.php) provide optional representation planning and computed batching. [RelationshipReadRequirements](../../src/Query/Fetch/RelationshipReadRequirements.php) contains the batch reader's resource needs and remaining budgets; the reader must honor scope and bound work before hydration.

[DoctrineCollectionQueryProviderInterface](../../src/Bridge/Doctrine/Query/DoctrineCollectionQueryProviderInterface.php) is a supported Doctrine capability for complete collection visibility queries. Decorators must run their guards and apply their scopes before forwarding; return null when a safe projection is unavailable. [ResourceRepositoryLocator::getRepositoryForType](../../src/Bridge/Symfony/Locator/ResourceRepositoryLocator.php) selects the authoritative per-type provider. Inheriting GenericDoctrineRepository while overriding collection visibility does not automatically opt into query planning.

## Profiles and hooks

[ProfileInterface](../../src/Profile/ProfileInterface.php) profiles are services and may use constructor DI. Their hooks receive a type-scoped context. Existing ReadHook, WriteHook, RelationshipHook, QueryHook and DocumentHook interfaces live in [Profile/Hook](../../src/Profile/Hook).

Optional [FetchPlanHookInterface](../../src/Profile/Hook/FetchPlanHookInterface.php), [RelationshipFetchRequirementsHookInterface](../../src/Profile/Hook/RelationshipFetchRequirementsHookInterface.php) and [ResourceMetaHookInterface](../../src/Profile/Hook/ResourceMetaHookInterface.php) declare additional fetch or resource metadata needs. These additions require explicit freeze review along with the types in their signatures.

## Resource declarations and queries

Resource attributes live in [Resource/Attribute](../../src/Resource/Attribute); operation/projection enums live in [Resource/Definition](../../src/Resource/Definition). Named attribute arguments, defaults and enum values are part of the proposed consumer surface. [Custom handlers](../guide/custom-handlers.md) extend filters and sorts; filter handlers must preserve logical composition and collection sorts must define aggregate semantics.

Configuration keys, service aliases/tags, console commands and HTTP error codes are additional contracts even when no PHP interface is involved. The [audit checklist](../release/public-api-audit.md) covers them. Migration decisions belong in [UPGRADE-1.0](../../UPGRADE-1.0.md).

## RC contract decisions

TypedRelationshipReader and TypedRelationshipUpdater now dispatch endpoint operations through `supports(sourceType)` via autoconfiguration or the `jsonapi.relationship_reader` / `jsonapi.relationship_updater` tags. The configured provider is the fallback. These do not automatically execute profile hooks for a custom provider or batch-load a computed representation; implementations must supply those semantics themselves.

RelationshipReadMap, RelationshipReadRequirements and CustomRouteMetadata are public extension DTOs in their current namespaces. ResourceMetadata implements ResourceMetadataInterface::getType; this small identity interface is not a substitute for the complete ResourceMetadata expected by a custom registry.

See the [contract regression evidence](../architecture/rc-public-contract-gaps.md) for removed configuration options, typed dispatch and independently pending consumer confirmation.
