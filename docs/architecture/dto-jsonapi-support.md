# DTO-backed JSON:API resources

This document summarises the ongoing plan for introducing first-class DTO support
on top of the existing entity-centric JSON:API runtime.

## Short verdict

The mapper + request DTO approach remains the default direction provided that:

* the runtime core stays lean while the DTO pipeline is expressed as extensible
  components with sensible defaults;
* collection reads use projections at the DQL/QueryBuilder level (for example,
  `SELECT NEW ViewDto(...)`) to avoid the "entity → DTO" double hydration
  penalty;
* the existing `ChangeSet` stays available as a low-level primitive, yet write
  mappers can apply request DTOs directly without an intermediate copy.

## Strengths to keep

1. **Contract/data separation.** The combination of `dataClass` and `viewClass`
   (formerly `representationClass`) allows API evolution without changing
   persistence schemas. See `src/Resource/Registry/ResourceRegistry.php` and
   `src/Resource/Metadata/ResourceMetadata.php`.
2. **Request DTOs for create/update.** Request DTOs simplify validation, enable
   explicit contracts, and unlock API versioning.
3. **Mappers as contracts.** Read/write mappers remain the key extension point
   for complex transformations or BFF-style integrations.
4. **Backward compatibility.** Identity behaviour continues to work by default
   for resources that do not opt into DTOs.

## Fragile spots and mitigations

1. **Double hydration on reads.** Hydrating entities and then building DTOs is
   expensive for large collections. Use `SELECT NEW` projections or scalar
   hydration strategies, letting repositories decide before execution.
2. **`ChangeSet` as the only bridge.** The universal change set is useful but
   overkill for simple PATCH flows. Allow `WriteMapper` implementations to apply
   request DTOs directly and treat change sets as optional for advanced cases.
3. **Overlapping responsibilities.** Serialisers, change sets, and mappers need
   clear separation to keep DX manageable. The architecture below details the
   boundaries.
4. **Complex relationships.** Explicit dirty-field tracking and relationship
   policies (set/merge/remove) prevent race conditions and redundant updates.
5. **Runtime reflection.** Repeated attribute/metadata reflection hurts latency;
   prefer generated `ResourceDefinition` artefacts cached at container compile
   time.

## Projection semantics and includes

* Collections default to a DTO projection. `ReadMapper` receives data hydrated
  through projections instead of entities.
* Single-resource fetches default to entity mode but may opt into DTO mode.
* Includes must follow the parent strategy: DTO parents require DTO children to
  avoid double hydration.
* Linkage-only responses skip DTO construction entirely.
* Each view field needs a mapping to an SQL expression or alias; the compiler
  pass validates the mapping, exposing which fields support sorting/filtering.
  Missing mappings either fail fast or fall back to entity mode with a dev-time
  warning.

## Minimal supporting architecture

1. **ResourceDefinition** — a compiled description of resource type, data/view
   classes, request DTOs, read strategies, field/relationship mappings, and
   versioning hints. Built during container compilation and cached.
2. **Dedicated read/write contracts.**
   * `ReadMapperInterface::toView(mixed $row, ResourceDefinition $definition, Criteria $context): object`
     accepts entities or projected rows.
   * `WriteMapperInterface::apply(object $entity, object $requestDto, ResourceDefinition $definition, WriteContext $context): void`
     and `instantiate(...)` for creates. Change sets remain optional.
   * The identity mapper is the default.
3. **Read strategies.**
   * `entity`: current behaviour returning hydrated entities.
   * `dto`: DQL `SELECT NEW` or scalar hydration feeding DTO factories.
   * `custom`: escape hatch for specialised read models.
4. **Request DTO resolver.** Hydrates JSON:API documents into DTO classes,
   validates them, and tracks dirty attributes/relationships for PATCH.
5. **Optional ChangeSet.** Change sets are retained for atomic operations and
   tracing but are no longer mandatory for every write path.
6. **Compilation/cache.** A compiler pass collects definitions, mappers, and
   strategies, emitting precomputed delegates. Dev mode adds diagnostics (e.g.
   strategy breakdown, DTO allocation counts, N+1 warnings).

## Dirty tracking and relationship policies

* Request DTOs record changed attributes and relationship policies
  (`set`/`merge`/`remove`).
* `WriteMapper.apply()` honours these flags to avoid unintended updates.
* Repeated PATCH requests with identical payloads must be idempotent; errors are
  returned as structured JSON:API error objects.
* Atomic batches can mix `WriteMapper` and `ChangeSet` operations under a shared
  transaction.

## Versioning

1. **Class-level versions.** Resources bind to versioned view/request DTOs via
   `ResourceDefinition` without altering routes.
2. **Profile negotiation.** `ProfileContext` selects the appropriate DTOs and
   read strategies based on profiles or `Accept` headers.
3. **Filter/sort compatibility.** Field mappings drive validation so that
   removed fields trigger clear errors or mapped replacements.

## Contracts and contexts

* `ReadMapperInterface::toView(...)` receives a `ReadContext` describing sparse
  fieldsets, includes, pagination, sorting, filtering, and the active projection.
* `WriteMapperInterface::instantiate(...)` and `apply(...)` operate with a
  `WriteContext` providing actor metadata, transactional state, and atomic batch
  options.
* `ChangeSet` is passed via `WriteContext` only when needed for logging or
  integration.

## Performance constraints

* No runtime reflection on hot paths; only compiled delegates.
* DTO projection is the default for collections; entity mode is opt-in.
* Preload relationships only when requested via sparse fieldsets/includes.
* Developer tooling flags N+1 issues, tracks DTO allocations, and warns on
  fallbacks.
* Benchmarks compare entity vs DTO strategies on 10k+ rows, guarding
  regressions.
* Missing projection fields trigger explicit errors or a controlled fallback to
  entity mode.

## Developer experience

* Maker commands scaffold resources with DTOs, mappers, tests, and field/relationship
  configuration.
* Error messages stay JSON:API compliant and explain missing field mappings,
  unsupported sort fields, or absent relationship policies.
* Documentation highlights identity, read DTO, and write DTO resources with
  relationship management.
* Feature flags let teams adopt DTO flows incrementally; dev tooling reports the
  chosen read strategy and potential N+1s.

## Compilation and cache strategy

* Compiler passes validate mappings and relationships, generate delegates, and
  cache the resulting definitions.
* Warmup populates an on-disk cache so runtime only performs lookups.
* Diagnostics log misconfigurations before the container warms up.

## Rollout plan

1. **Phase 1 – ResourceDefinition + identity mapper.** Introduce the new object,
   update the registry, and deprecate `ResourceMetadata::$class` as a getter for
   the view class.
2. **Phase 2 – Read DTO projection.** Add read strategies in the Doctrine
   repository, implement `ReadMapper::toView`, and cover filters/sorts in tests.
3. **Phase 3 – Request DTO write path.** Ship DTO resolver, validation, and dirty
   tracking. Implement `WriteMapper::apply/instantiate` without mandatory change
   sets.
4. **Phase 4 – Relationships and atomic operations.** Support
   set/merge/remove policies, ensure PATCH idempotence, and integrate with
   atomic batches.
5. **Phase 5 – Versioning and profiles.** Bind profiles to DTO versions, extend
   maker commands, and document the flow.
6. **Phase 6 – Profiling and guardrails.** Deliver dev inspector tooling,
   microbenchmarks, and CI checks.

## Test matrix

* Collections: filtering/sorting across projected fields, high-volume benchmarks.
* To-many relationships: policy coverage, repeated PATCH idempotence.
* Versioning: a single route with multiple DTO versions and regression checks.
* Memory behaviour: deep includes without leaks.
* Atomic operations: transactional integrity and error propagation.
* DX: maker commands, error messages, feature-flag toggles.
* Fallbacks: predictable behaviour when fields are missing from projections.

## Migration and compatibility

* `ResourceMetadata::$class` is deprecated but continues to expose the view class
  until migration completes.
* Per-resource feature flags allow staged adoption.
* Static analysis rules (PHPStan/Psalm) and Rector recipes help enforce
  `fieldMap` definitions and highlight invalid sort fields.
* Migration docs provide step-by-step guidance for converting a resource to DTO
  mode.

## Next steps

1. Finalise the `ResourceDefinition` contract (view class, request DTOs, read
   projection, field/relationship mappings, version resolver).
2. Prototype phases 1–2 on a real resource, comparing entity vs DTO performance
   on 10k+ rows.
3. Specify `WriteMapper.apply()` semantics and dirty tracking formats.
4. Prepare maker command drafts and a lightweight dev panel showing read
   strategies and potential N+1s.
5. Define fallback rules (error vs entity switch) and document them clearly.
