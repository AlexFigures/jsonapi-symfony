# Путь к стабильной 1.0

Цель: независимо подтверждённое поведение bundle, явно определённые production limits и публичные контракты, которые можно заморозить под SemVer. Реализация и внешняя проверка проходят в разных репозиториях и отдельными прогонами. Green bundle tests не означают автоматически green external acceptance.

## Текущий этап

Ветка `fix/acceptance-gaps` закрывает correctness и определяет ограничения Atomic, concurrent If-Match, filters и identifiers. [Отчёт второго pass и план read-path](acceptance-second-pass.md) описывает изменения и regression coverage. Независимая внешняя проверка остаётся отдельным этапом. Здесь не объявляются готовность 1.0, поддержка новых версий платформы или завершённый API freeze.

## Этапы и gates

| Этап | Работа | Gate |
| --- | --- | --- |
| 1. Correctness | Atomic, write preconditions, negotiation, relationships, Doctrine errors, query validation, UUID, profiles, HTTP/cache semantics. | Bundle regression suite; затем независимый normal acceptance consumer. |
| 2. Production boundaries | One transactional connection boundary per Atomic request; multi-boundary rejection before mutation. Built-in Doctrine initially requires one manager as well. | Explicit diagnostics, docs, bundle tests, external confirmation. No 2PC/cross-database atomicity. |
| 3. Read/fetch plan | Root selection, fields, linkage, includes, profiles, filters, sorts, pagination; batch to-one/to-many and nested frontiers. | For bounded pages, query growth follows graph shape/chunk count rather than root count; compare 5 and 20 resources. No giant graph JOIN. |
| 4. Root pagination | Distinct roots before representation hydration. Resolve to-many sorting policy explicitly. | Correct unique page size, no duplicate/missing roots, stable page boundaries, correct counts on PostgreSQL/MySQL and DTO paths. |
| 5. Amplification | Include reservation before hydration; structural filter limits before SQL; explicit linkage policy/cost. | Stop at overflow without loading the full graph. Large IN lists count operands. Never silently truncate valid linkage/documents. |
| 6. External production contract | Separately update/run `example-jsonapi-bundle`. | Normal acceptance plus Atomic boundaries, concurrent writes, query budgets, distinct pagination, includes/filters, replicas, tenant/shard routing, failure rollback. |
| 7. Support Contract 1.0 | Describe supported persistence, topology integration, Atomic/read/ID semantics and explicit non-goals. | Each claim maps to executable bundle coverage and independent consumer evidence. |
| 8. Stabilization/API freeze | Final BC-breaking cleanup, namespace audit, PUBLIC/INTERNAL classification, extension points, configuration, attributes and errors. | Runtime contract already independently confirmed; audited public contracts permit 1.x evolution. |
| 9. Compatibility matrix | PHP, Symfony, ORM, DBAL, Doctrine bridge, PostgreSQL, MySQL. | Promise only combinations that run regularly in CI; derive exact versions from Composer/CI validation before RC. |
| 10. Upgrade path | `UPGRADE-1.0.md`: namespace/API/config/attribute removals and changes, Atomic limits, linkage/read/pagination behavior. | Each change has Before / After / Why / Migration; no hidden requirements. |
| 11. BC tooling | Public API comparison from the release baseline; retain behavioral/config protection externally. | CI catches class/method/interface/constructor/enum/attribute drift. |
| 12. Documentation sync | Installation, resources/CRUD, relationships, query options, errors, Atomic, caching, profiles, topology, providers, performance/limits and known limitations. | Docs describe the executable frozen contract and match the example consumer. |
| 13. RC | Release `1.0.0-rc.1`; fix bugs, docs, compatibility and accidental API problems. | No major feature additions during RC; repair flawed contracts before final even if RC BC is needed. |

The next implementation branch handles N+1, pagination, include amplification and linkage cost together through the explicit fetch plan. Filter protection is already part of this correctness pass. External torture expectations remain unchanged during bundle development.

## Independent result classification

External results use `PASS`, `SUPPORTED LIMITATION`, `APPLICATION POLICY`, or `INFRASTRUCTURE LIMIT`. A declared limitation must have a deterministic diagnostic and evidence; a red assertion is not relabelled to hide a bundle defect. There must be no undefined behavior, silent partial commits, unbounded amplification, or accidental server errors for unsupported configuration/query shapes.

## Support contract and freeze audit

Persistence claims include the built-in Doctrine ORM provider, multiple managers, primary/replica integration, and application-level tenant/shard routing only after verification. Atomic guarantees one boundary per request with rejection before mutation. Reads guarantee bounded relationship loading/includes/filters and distinct-root pagination after the dedicated branch is confirmed. IDs include integer, UUID and natural string; unsupported composites fail at discovery.

Audit interfaces, attributes, enums, value objects, exceptions, events, hooks, aliases, configuration and commands as PUBLIC or INTERNAL. Examine `ResourceRepository`, `ResourceProcessor`, `TransactionManager`, filter/sort/relationship handlers, profiles/hooks, mapping and cache strategies for extensibility throughout 1.x. Review historical naming, duplicate concepts and namespace boundaries before freezing. Configuration and named attribute constructor arguments are public API too.

Stabilize HTTP status, `error.code`, title semantics and `source.pointer`/`source.parameter`/`source.header`. Error `detail` remains descriptive rather than an exact stable string. Public exception classification, alias names, config defaults and validation require migration notes when changed.

## Final release gate

- Independent normal acceptance is green; Atomic, concurrency and rollback semantics are confirmed.
- Structural N+1 gaps are closed; distinct-root pagination and amplification budgets are enforced.
- Multiple managers, replica integration and tenant/shard consumer routing are verified; unsupported boundaries are explicit.
- Public API and namespaces are audited; INTERNAL APIs are marked; config, attributes and errors are frozen.
- The support matrix is declared and exercised in CI; BC validation is enabled against the release baseline.
- `UPGRADE-1.0.md`, docs and the example consumer agree with the tested runtime contract.
- RC feedback is resolved before `1.0.0`.

Non-goals remain distributed transactions, replication management/lag compensation, a sharding engine, a tenant framework and cross-database Atomic.

## После 1.0

Backward-compatible 1.x development may add cursor/keyset pagination, relationship pagination, filter dialects, profiles/extensions, cache invalidation, read providers, authorization hooks, OpenAPI and observability improvements. Breaking changes accumulate for 2.0.

For JSON:API versions/extensions, keep an explicit conformance matrix: requirement → bundle test → external acceptance → docs. Do not claim features before executable verification.

For a new Symfony/PHP/Doctrine release: add CI coverage, run bundle checks, run the separate external subset, resolve deprecations/compatibility issues, then declare support. Review ORM pagination/metadata/transaction APIs, DBAL platform and UUID behavior, and Symfony Doctrine bridge independently. Removing a declared 1.x PHP/platform compatibility target requires the appropriate major-version policy. LTS retirement follows an announced support policy.
