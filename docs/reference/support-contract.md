# Support contract 1.0

This is the reviewed 1.0 support contract. The merged implementation has passed independent verification; publication and package-specific evidence remain separate. Publication is controlled by the [release gate](../release/checklist.md).

| Area | Bundle guarantee | Application / infrastructure responsibility |
| --- | --- | --- |
| Platforms | One 1.x line; verified PHP 8.2+/Symfony 7.4 and PHP 8.4.1+/Symfony 8.1 targets; Symfony 8.2-dev is forward-tested | Use a verified lock and supported dependency/platform combination |
| Persistence | Optional built-in Doctrine ORM 3 / DBAL 3.8+ or 4.3+ provider; multiple managers routed by resource | Configure routing, topology, database availability and schema/indexes |
| Databases | Real PostgreSQL 16 / MySQL 8.0 regression lanes; MariaDB 11 exercised | Production isolation/lock settings and server operation |
| IDs | Single integer, UUID and natural string; stable protocol identity across views; composite Doctrine IDs fail discovery | Custom encodings need a custom provider and tests |
| Atomic | Complete preflight, one manager/connection boundary; independent databases/shards rejected with 409 before mutation | No distributed atomicity; separate managers sharing a connection are also unsupported |
| Atomic flush/commit | Generated-ID/lid flushes may occur inside the same transaction; commit only after all operations succeed; later failure rolls back earlier flushes | Custom transaction providers must expose an equivalent scoped boundary |
| Concurrent If-Match | Doctrine locks the current root, refreshes its representation, evaluates the original validator and writes within the transaction; stale concurrent loser gets 412 without mutation | Custom providers need their own guard/CAS; external writers and related-row concurrency must honor application policy |
| Queries | Absolute filter depth/node/operand limits and weighted complexity before SQL; whitelist validation; distinct-root pagination | Indexes, custom handler semantics and domain-specific query cost |
| Sorting | Root tie-breaker and explicit strict policy for collection-valued paths | Default `legacy` retained; choose `reject` plus aggregate handlers for deterministic to-many semantics |
| Relationships/includes | Native root page, scoped batched to-one/to-many/nested reads; SQL endpoint membership/pagination; budget overflow stops before unbounded hydration | Custom getters/hooks/providers must declare requirements, batch and honor scopes/budgets |
| Budgets | Independent include, linkage, page, fields and filter limits; controlled errors, no silent successful truncation | Configure nonzero production limits; SQL chunk/graph shape still affects cost |
| Profiles/hooks | DI services; negotiated/default/per-type activation and optional hooks; stable identity across representations | Hooks remain synchronous; application SQL/side effects require bounded implementations |
| HTTP/errors | JSON:API negotiation, configured media channels, cache validators and documented stable status/code/source semantics | Authorization policy, cache privacy/variation and purge infrastructure |
| Custom providers | Public repository/processor/typed/transaction/relationship contracts and optional capabilities | Visibility, persistence, validation, concurrency and safe query plans |
| Compatibility | PUBLIC symbols/config/tags/error contract maintained through 1.x; internal machinery may evolve | Follow [BC policy](../api/bc-policy.md) and migration guide |

The independent consumer verified merged bundle `96a1530f3155ddf001b7d1e48fd33e375c382d85`. Recorded Symfony 7.4 and 8.1 runs passed 683 acceptance, 82 production, 280 features and 62 torture tests with no failures/skips or open runtime gaps; the release owner also confirmed all compatibility targets passed. See [verification](../release/verification.md) for provenance and forward-platform status. Final-package installation receives its own exact-version verification.

No distributed transactions/2PC, replication management, replica-lag compensation, sharding engine, tenant framework, global automatic deadlock retry, async mutation framework or cursor pagination are promised. Applications own topology, permissions, custom providers and operational infrastructure. See [production policies](../guide/production-policies.md), [compatibility evidence](../release/compatibility.md) and [error contract](../api/errors.md).
