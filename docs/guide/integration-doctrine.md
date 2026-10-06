# Doctrine integration

Configure Doctrine in the application, register the bundle and use the [developer path](developer-path.md) to expose a mapped resource. The bundle's Doctrine provider is selected by default; consumers install their own Doctrine integration and configure connections/migrations.

Resource metadata distinguishes API fields from persistence fields. Integer, UUID and natural string IDs are supported by the single-identifier path. Composite identifiers produce a discovery diagnostic. Serializer write groups, including application YAML metadata, constrain writable fields; API input DTOs require their configured validation.

ManagerRegistry may route different data classes to different managers. Single-resource writes scope the selected manager. Atomic requires one supported manager/connection boundary and rejects independent connections before mutation. Separate managers sharing a connection are currently unsupported. [Production policies](production-policies.md) describe concurrency and rollback.

Native reads select distinct root pages before batched relationship representation loading. A subclass changing `findCollection()` scope does not automatically expose a native query projection. Preserve that scope when opting into the internal query capability, or use the scoped fallback. Do not implement every to-many relationship as one graph-wide fetch join.

TODO before freeze: complete application recipes for primary/replica routing, manager selection, custom processors, UUID DBAL types and DTO mapping. Each must cite bundle coverage and independently verified consumer behavior; no replication/sharding framework is provided.
