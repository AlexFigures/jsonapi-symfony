# Doctrine integration

Configure Doctrine in the application, register the bundle and use the [developer path](developer-path.md) to expose a mapped resource. The bundle's Doctrine provider is selected by default; consumers install their own Doctrine integration and configure connections/migrations.

Resource metadata distinguishes API fields from persistence fields. Integer, UUID and natural string IDs are supported by the single-identifier path. Composite identifiers produce a discovery diagnostic. Serializer write groups, including application YAML metadata, constrain writable fields; API input DTOs require their configured validation.

ManagerRegistry may route different data classes to different managers. Single-resource writes scope the selected manager. Atomic requires one supported manager/connection boundary and rejects independent connections before mutation. Separate managers sharing a connection are currently unsupported. [Production policies](production-policies.md) describe concurrency and rollback.

Native reads select distinct root pages before batched relationship representation loading. A subclass changing `findCollection()` scope does not automatically expose a native query projection. Preserve that scope when opting into the supported DoctrineCollectionQueryProviderInterface capability, or use the scoped fallback. Do not implement every to-many relationship as one graph-wide fetch join.

## Manager and replica responsibility

Use normal DoctrineBundle entity-manager mappings or an application ManagerRegistry decorator. The bundle calls getManagerForClass for each resource's persistence class; it does not build a tenant/sharding engine. [Atomic boundary tests](../../tests/Integration/Atomic/DoctrineAtomicBoundariesTest.php) show unrelated managers are not enlisted and independent connections reject before mutation. The recorded external baseline also covers application primary/replica and shard-aware routing; later platform fixtures must reconfirm it.

On PHP 8.4/Symfony 8, current ORM requires native lazy objects because Symfony removed LazyGhostTrait. Configure this through the supported DoctrineBundle version/native lazy option; manually built EntityManagers use ORM Configuration::enableNativeLazyObjects(true). Bundle fixture setup is a test example, not automatic application topology configuration.

Symfony 7.4 lanes use DBAL 3.8+; Symfony 8.1/8.2 lanes use DBAL 4.3+ because current HttpFoundation excludes older DBAL. Register UUID types through application Doctrine integration; JSON:API still transports one stable string ID. Input DTO validation and Serializer groups, including YAML metadata, run before persistence. Custom processors should use the documented processor contract, not internal WriteListener services.

A repository decorator may implement [DoctrineCollectionQueryProviderInterface](../../src/Bridge/Doctrine/Query/DoctrineCollectionQueryProviderInterface.php). Its `collectionQuery(type, criteria)` must enforce the same request guards and full visibility as ordinary collection reads, without executing SQL. When its inner service is [ResourceRepositoryLocator](../../src/Bridge/Symfony/Locator/ResourceRepositoryLocator.php), select the per-type provider through `getRepositoryForType(type)` before forwarding optional capabilities. Return null when the outer policy cannot safely be expressed in the query. Every decorator in the chain must opt in; the bundle does not bypass opaque wrappers.
