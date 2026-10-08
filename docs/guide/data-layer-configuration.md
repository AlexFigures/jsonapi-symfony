# Data-layer configuration

The built-in provider is `jsonapi.data_layer.provider: doctrine`. With an application Doctrine ManagerRegistry, the bundle wires repository, processor, relationship and transaction services. Managers are selected by resource data class; writes do not enlist every registered manager.

For a custom data source, configure explicit contract implementations:

```yaml
jsonapi:
    data_layer:
        provider: custom
        repository: App\JsonApi\Repository
        processor: App\JsonApi\Processor
        relationship_reader: App\JsonApi\RelationshipReader
        transaction_manager: App\JsonApi\TransactionManager
```

The IDs must refer to application services implementing the corresponding contracts in the [extension index](../api/public-api.md). Missing custom services use fallback implementations; a null fallback is not a persistence implementation.

Typed repositories and persisters select resource types through `supports(type)`. Typed persisters are autoconfigured or tagged `jsonapi.persister`. A matching persister takes precedence over the configured processor fallback; unrelated types keep that fallback. Custom non-ORM writes own validation, persistence and concurrency semantics. They are not automatically supported in Doctrine Atomic batches.

Typed endpoint readers/updaters implement `TypedRelationshipReader` / `TypedRelationshipUpdater` and select the source resource type through `supports(type)`. Autoconfiguration applies the `jsonapi.relationship_reader` / `jsonapi.relationship_updater` tags. Tagged priority controls the first matching service; unmatched types retain the configured/native fallback. This endpoint dispatch does not automatically run application hooks or infer a representation fetch plan.

For computed relationships, supply a bounded `RelationshipBatchReaderInterface` implementation for representation reads and a scoped paginated reader for endpoints. Generic providers cannot inherit Doctrine SQL-cost guarantees without equivalent bounded work.

[Doctrine integration](integration-doctrine.md) · [production boundaries](production-policies.md).

See [public extension examples](../api/extension-examples.md) for registration and tested implementations.
