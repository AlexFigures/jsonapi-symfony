# Public extension examples

Use application autowiring/autoconfiguration and public contracts. The complete interface table below links to small tested implementations or contract regressions; signatures in source are authoritative. Value objects and attributes are documented alongside the capability that consumes them. Internal constructors are service wiring, not implementation requirements.

## Typed persistence and endpoints

A typed implementation retains all methods of its base contract and adds type dispatch:

```php
public function supports(string $type): bool
{
    return $type === 'articles';
}
```

Implement `TypedResourceRepository`, `TypedResourcePersister`, `TypedRelationshipReader` or `TypedRelationshipUpdater` as appropriate. Persister dispatch uses `create/update/delete`; processor dispatch uses `processCreate/processUpdate/processDelete`. Do not interchange these method sets. Reuse the full examples in [typed fixtures](../../tests/Functional/Regression/Fixtures) and [typed relationship handler](../../tests/Functional/Regression/RcTypedRelationshipHandler.php).

```yaml
# config/services.yaml
services:
    App\JsonApi\ArticlePersister:
        autowire: true
        tags: [{ name: jsonapi.persister, priority: 100 }]
    App\JsonApi\ArticleRelationships:
        autowire: true
        tags:
            - { name: jsonapi.relationship_reader, priority: 100 }
            - { name: jsonapi.relationship_updater, priority: 100 }
```

A tagged endpoint service implements the corresponding typed interface. Matching uses source type; unmatched types keep the configured provider. Scope, hooks, validation and bounded fetching remain the custom provider's responsibility. `data_layer.*` aliases support a single fallback. [Typed dispatch HTTP regressions](../../tests/Functional/Regression/RcContainerContractTest.php) cover autoconfiguration and explicit tags.

## Representation batching and decorators

```php
public function read(
    RelationshipReadRequirements $requirements,
    Criteria $criteria,
    Request $request,
): RelationshipReadMap {
    $result = new RelationshipReadMap();
    // Fetch all ownerIds in a bounded scoped batch. Probe at most remaining + 1.
    // Null budget means unlimited; zero means exhausted. Never silently truncate.
    foreach ($this->boundedMembership($requirements, $criteria, $request) as $ownerId => $targets) {
        $result->put($requirements->ownerType, (string) $ownerId, $requirements->relationship, $targets);
    }
    return $result;
}
```

A `RelationshipBatchReaderInterface` also implements `supports(type, relationship)` and is tagged `jsonapi.relationship_batch_reader` (or autoconfigured). Fill models/counts when requested. Native fixtures demonstrate correct reservation, overflow and scope in [read-path tests](../../tests/Integration/ReadPath/DoctrineReadPathTestCase.php).

A repository decorator must implement optional `DoctrineCollectionQueryProviderInterface` only when it can forward a complete scoped query after its guards. Returning null selects safe fallback; blindly exposing an inner unscoped query is invalid. [Repository locator/capability examples](../../tests/Integration/ReadPath) cover opaque versus capability-preserving decorators. Batch representation reading and standalone endpoint reading are separate interfaces.

## Profiles and hooks

Implement `ProfileInterface::uri/descriptor/hooks/requirements`, register a service with `jsonapi.profile`, and inject normal constructor dependencies. Return hook services from `hooks()`; implement only relevant optional hook interfaces. A write hook can deny before persistence:

```php
public function onBeforeUpdate(ProfileContext $context, string $type, string $id, ChangeSet $changeSet): void
{
    if (!$this->policy->mayUpdate($type, $id)) {
        throw new ForbiddenException('Update is not permitted.');
    }
}
```

[Injected profile fixture](../../tests/Unit/Profile/Fixtures/InjectedProfile.php) demonstrates DI. [Profile regression suite](../../tests/Integration/Profile) demonstrates query/read/write/relationship/document hooks, defaults/per-type activation and metadata. Fetch-plan, resource-meta and filter-parameter provider hooks are optional capabilities. Hook SQL is synchronous and application-owned; declare batch requirements instead of issuing one query per root.

## Query operators and handlers

```yaml
services:
    App\JsonApi\StartsWithOperator:
        autowire: true
        tags: [jsonapi.filter.operator]
    App\JsonApi\SearchFilter:
        autowire: true
        tags: [jsonapi.filter.handler]
    App\JsonApi\AttachmentMinSort:
        autowire: true
        tags: [jsonapi.sort.handler]
```

A custom `Operator` supplies name, field support, normalization and a `DoctrineExpression` with bound parameters. This existing operator interface is deliberately Doctrine-specific; generic providers implement their own compilation. Filter/sort handlers implement supports/handle/getPriority. Filters operate as isolated logical leaves so `OR` alternatives survive. Collection sorts must define MIN/MAX/domain aggregate semantics. [Compiler regressions](../../tests/Unit/Filter/Handler) and [read-path tests](../../tests/Integration/ReadPath) include concrete parameterized examples.

## Authorization, response, metadata and routes

Bind `RelationshipAuthorizerInterface` to a side-effect-free application policy; `isGranted(Request, type, id, relationship, RelationshipOperation)` controls endpoint read/replace/add/remove. A denied endpoint stops before provider mutation. See [authorization fixture](../../tests/Functional/Regression/RcRelationshipAuthorizer.php).

Inject `JsonApiResponseFactory`; call the documented immutable response/error builders. Use `ResourceMetadataInterface` for identity consumers and full `ResourceMetadata` for registry integration. Version/read/write mappers preserve identity while selecting views or processing validated DTOs. [ResponseFactory](../guide/response-factory.md), [custom routes](../guide/custom-routes.md), [discovery](../guide/resource-discovery.md) and [advanced profiles/DTOs](../guide/advanced-features.md) provide the corresponding examples.

## Transactions, concurrency, caches and events

Implement `TransactionManager::transactional()` for the base transaction seam. Scoped managers add complete data-class preflight through `transactionalFor`; resource-write managers add a per-resource boundary. They are optional interfaces, not required new methods on base implementers. Custom guards implement the published callback boundary and refresh-before-validation semantics. [Concurrency regressions](../../tests/Integration/Concurrency) and [Atomic regressions](../../tests/Integration/Atomic) show independent connections and rollback.

Decorate/bind `EtagGeneratorInterface`, `MediaTypePolicyInterface` and `SurrogatePurgerInterface` only when their application policy is needed. Preserve media variation and version-validator behavior. Change events are application notifications, not an outbox/after-commit guarantee. See the contract examples below and [production policies](../guide/production-policies.md).

## Every reviewed PUBLIC interface

Each row points to the declaration and a maintained example/regression. These links make the implementable surface explicit; supporting public enums/DTOs/attributes remain listed in the [manifest](public-api-manifest.json).

| Interface | Example / regression |
| --- | --- |
| [DoctrineCollectionQueryProviderInterface](../../src/Bridge/Doctrine/Query/DoctrineCollectionQueryProviderInterface.php) | [ScopedRepositoryDecorator.php](../../tests/Integration/ReadPath/ScopedRepositoryDecorator.php) |
| [ExistenceChecker](../../src/Contract/Data/ExistenceChecker.php) | [InMemoryExistenceChecker.php](../../tests/Fixtures/InMemory/InMemoryExistenceChecker.php) |
| [RelationshipBatchReaderInterface](../../src/Contract/Data/RelationshipBatchReaderInterface.php) | [DoctrineReadPathTestCase.php](../../tests/Integration/ReadPath/DoctrineReadPathTestCase.php) |
| [RelationshipReader](../../src/Contract/Data/RelationshipReader.php) | [InMemoryRelationshipReader.php](../../tests/Fixtures/InMemory/InMemoryRelationshipReader.php) |
| [RelationshipUpdater](../../src/Contract/Data/RelationshipUpdater.php) | [InMemoryRelationshipUpdater.php](../../tests/Fixtures/InMemory/InMemoryRelationshipUpdater.php) |
| [RepresentationPreloaderInterface](../../src/Contract/Data/RepresentationPreloaderInterface.php) | [DataLayerConfigurationTest.php](../../tests/Unit/Bridge/DataLayerConfigurationTest.php) |
| [ResourcePersister](../../src/Contract/Data/ResourcePersister.php) | [contract usage](public-api.md) |
| [ResourceProcessor](../../src/Contract/Data/ResourceProcessor.php) | [InMemoryPersister.php](../../tests/Fixtures/InMemory/InMemoryPersister.php) |
| [ResourceRepository](../../src/Contract/Data/ResourceRepository.php) | [InMemoryRepository.php](../../tests/Fixtures/InMemory/InMemoryRepository.php) |
| [TypedRelationshipReader](../../src/Contract/Data/TypedRelationshipReader.php) | [RcTypedRelationshipHandler.php](../../tests/Functional/Regression/RcTypedRelationshipHandler.php) |
| [TypedRelationshipUpdater](../../src/Contract/Data/TypedRelationshipUpdater.php) | [RcTypedRelationshipHandler.php](../../tests/Functional/Regression/RcTypedRelationshipHandler.php) |
| [TypedResourcePersister](../../src/Contract/Data/TypedResourcePersister.php) | [RcTypedPersister.php](../../tests/Functional/Regression/Fixtures/RcTypedPersister.php) |
| [TypedResourceRepository](../../src/Contract/Data/TypedResourceRepository.php) | [RcTypedRepository.php](../../tests/Functional/Regression/Fixtures/RcTypedRepository.php) |
| [WriteConcurrencyGuardInterface](../../src/Contract/Data/WriteConcurrencyGuardInterface.php) | [RcRelationshipTransaction.php](../../tests/Functional/Regression/RcRelationshipTransaction.php) |
| [ResourceMetadataInterface](../../src/Contract/Resource/ResourceMetadataInterface.php) | [RcPublicMetadataContractTest.php](../../tests/Unit/Regression/RcPublicMetadataContractTest.php) |
| [ResourceWriteTransactionManagerInterface](../../src/Contract/Tx/ResourceWriteTransactionManagerInterface.php) | [contract usage](public-api.md) |
| [ScopedTransactionManagerInterface](../../src/Contract/Tx/ScopedTransactionManagerInterface.php) | [contract usage](public-api.md) |
| [TransactionManager](../../src/Contract/Tx/TransactionManager.php) | [InMemoryTransactionManager.php](../../tests/Fixtures/InMemory/InMemoryTransactionManager.php) |
| [CustomRouteHandlerInterface](../../src/CustomRoute/Handler/CustomRouteHandlerInterface.php) | [FailingHandler.php](../../tests/Fixtures/CustomRoute/FailingHandler.php) |
| [Node](../../src/Filter/Ast/Node.php) | [contract usage](public-api.md) |
| [FilterHandlerInterface](../../src/Filter/Handler/FilterHandlerInterface.php) | [RcProfileContractTest.php](../../tests/Integration/Profile/RcProfileContractTest.php) |
| [SortHandlerInterface](../../src/Filter/Handler/SortHandlerInterface.php) | [DoctrineReadPathTestCase.php](../../tests/Integration/ReadPath/DoctrineReadPathTestCase.php) |
| [Operator](../../src/Filter/Operator/Operator.php) | [WritePreconditionsHarness.php](../../tests/Integration/Fixtures/Concurrency/WritePreconditionsHarness.php) |
| [RelationshipAuthorizerInterface](../../src/Http/Authorization/RelationshipAuthorizerInterface.php) | [RcRelationshipAuthorizer.php](../../tests/Functional/Regression/RcRelationshipAuthorizer.php) |
| [EtagGeneratorInterface](../../src/Http/Cache/EtagGeneratorInterface.php) | [contract usage](public-api.md) |
| [MediaTypePolicyProviderInterface](../../src/Http/Negotiation/MediaTypePolicyProviderInterface.php) | [ContentNegotiationStatusTest.php](../../tests/JsonApiStatus/ContentNegotiationStatusTest.php) |
| [SurrogatePurgerInterface](../../src/Invalidation/SurrogatePurgerInterface.php) | [contract usage](public-api.md) |
| [ContextualFetchPlanHookInterface](../../src/Profile/Hook/ContextualFetchPlanHookInterface.php) | [contract usage](public-api.md) |
| [DocumentHook](../../src/Profile/Hook/DocumentHook.php) | [ProfileContextTest.php](../../tests/Unit/Profile/ProfileContextTest.php) |
| [FetchPlanHookInterface](../../src/Profile/Hook/FetchPlanHookInterface.php) | [contract usage](public-api.md) |
| [FilterParameterProviderInterface](../../src/Profile/Hook/FilterParameterProviderInterface.php) | [contract usage](public-api.md) |
| [QueryHook](../../src/Profile/Hook/QueryHook.php) | [ProfileContextTest.php](../../tests/Unit/Profile/ProfileContextTest.php) |
| [ReadHook](../../src/Profile/Hook/ReadHook.php) | [ProfileContextTest.php](../../tests/Unit/Profile/ProfileContextTest.php) |
| [RelationshipFetchRequirementsHookInterface](../../src/Profile/Hook/RelationshipFetchRequirementsHookInterface.php) | [DoctrineReadPathTestCase.php](../../tests/Integration/ReadPath/DoctrineReadPathTestCase.php) |
| [RelationshipHook](../../src/Profile/Hook/RelationshipHook.php) | [ProfileContextTest.php](../../tests/Unit/Profile/ProfileContextTest.php) |
| [ResourceMetaHookInterface](../../src/Profile/Hook/ResourceMetaHookInterface.php) | [RcProfileContractTest.php](../../tests/Integration/Profile/RcProfileContractTest.php) |
| [WriteHook](../../src/Profile/Hook/WriteHook.php) | [ProfileContextTest.php](../../tests/Unit/Profile/ProfileContextTest.php) |
| [ProfileInterface](../../src/Profile/ProfileInterface.php) | [InjectedProfile.php](../../tests/Unit/Profile/Fixtures/InjectedProfile.php) |
| [VersionResolverInterface](../../src/Resource/Definition/VersionResolverInterface.php) | [RcProfileContractTest.php](../../tests/Integration/Profile/RcProfileContractTest.php) |
| [ReadMapperInterface](../../src/Resource/Mapper/ReadMapperInterface.php) | [ArticleReadMapper.php](../../tests/Integration/Fixtures/Mapper/ArticleReadMapper.php) |
| [WriteMapperInterface](../../src/Resource/Mapper/WriteMapperInterface.php) | [ArticleWriteMapper.php](../../tests/Integration/Fixtures/Mapper/ArticleWriteMapper.php) |
| [CustomRouteRegistryInterface](../../src/Resource/Registry/CustomRouteRegistryInterface.php) | [JsonApiRouteLoaderTest.php](../../tests/Unit/Bridge/JsonApiRouteLoaderTest.php) |
| [ResourceRegistryInterface](../../src/Resource/Registry/ResourceRegistryInterface.php) | [OpenApiControllerTest.php](../../tests/Functional/Docs/OpenApiControllerTest.php) |
