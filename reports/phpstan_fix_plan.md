# PHPStan Remediation Plan

## Run context
- Command: `vendor/bin/phpstan analyse --memory-limit=1G`
- Result: 184 reported errors across Doctrine bridge, resource metadata/registry, and relationship handling layers.
- Environment prerequisite: raise PHP memory limit above default 128M for future runs (e.g., via `phpstan.neon` or CLI flag).

## Prioritized roadmap

### 1. Guarantee resource metadata exposes strict class-strings (critical)
- **Problem**: `ResourceMetadata::$dataClass`, `$viewClass`, and registry hydration currently surface plain strings. PHPStan flags every downstream Doctrine call (`ManagerRegistry::getManagerForClass`, `EntityManagerInterface::find`, `ReflectionClass` constructor) as potentially receiving arbitrary strings.
- **Impact**: Runtime risk when misconfigured resources map to non-existent classes; blocks large portion of subsequent static analysis in Doctrine services.
- **Actions**:
  1. Update `ResourceMetadata` properties and constructor signatures to use `class-string`/`class-string|null` types and validate inputs when building metadata in `ResourceRegistry`.
  2. Adjust registry extraction (`ResourceRegistry`, `CustomRouteRegistry`) to perform upfront assertions/casts so metadata consumers receive guaranteed class-strings.
  3. Extend metadata tests (or add new ones) covering invalid configuration handling.

### 2. Harden Doctrine instantiator flow (critical)
- **Problem**: `SerializerEntityInstantiator::instantiate()` feeds untyped class names into `ReflectionClass` and returns `array{entity: mixed,...}`.
- **Impact**: Potential fatal errors when serializer resolves to non-object; prevents PHPStan from trusting entity objects elsewhere.
- **Actions**:
  1. Accept `class-string` for `$entityClass`, enforce via PHPDoc + runtime assertions.
  2. Refine return structure to always deliver `object` by validating serializer output and throwing when it fails.
  3. Simplify nullsafe operator usage flagged by PHPStan (`?->` before `??`).

### 3. Type-safe Doctrine processors (high)
- **Problem**: `GenericDoctrineProcessor` and `ValidatingDoctrineProcessor` call `EntityManagerInterface::find()` with plain strings; template type `T` unresolved.
- **Impact**: Static analysis cannot ensure loaded entities are objects, propagating `mixed` across relationship handling.
- **Actions**:
  1. Propagate `class-string` from metadata when resolving target entity classes.
  2. Narrow processor return/variable types (e.g., typed DTOs or generics) and add guards when Doctrine returns `null`.

### 4. Relationship handler/refresolver cleanup (high)
- **Problem**: `GenericDoctrineRelationshipHandler` and `RelationshipResolver` operate on `mixed` resource targets, causing cascade of `mixed` → `object`/`string` violations, incorrect list types, and inaccurate Doctrine metadata generics.
- **Actions**:
  1. Introduce dedicated DTO/interfaces to represent resolved relationship targets with known element types.
  2. Replace `mixed` collections with typed arrays (`list<object>`, `list<ResourceIdentifier>`), adding normalization/validation where data originates.
  3. Add Doctrine metadata generic annotations (`ClassMetadata<object>`) and adjust helper method signatures to preserve type information.
  4. Ensure methods returning entities (`getRelatedResource`, `resolveTarget`) either always return `object` (throw when missing) or declare nullable semantics consistently.

### 5. Resource metadata normalization contexts (medium)
- **Problem**: `ResourceMetadata::getNormalizationGroups()`/`getDenormalizationGroups()` return `mixed` arrays; list semantics lost.
- **Actions**:
  1. Validate incoming context arrays and filter non-string values.
  2. Guarantee the returned value is `list<string>` (e.g., reindex with `array_values`).
  3. Update phpdoc/native types accordingly.

### 6. Custom route registry validation (medium)
- **Problem**: `CustomRouteRegistry` pipes raw config arrays into `CustomRouteMetadata` without type filtering, yielding dozens of `mixed` constructor arguments.
- **Actions**:
  1. Sanitize configuration arrays before instantiating metadata (cast scalars, default missing keys, validate arrays of strings).
  2. Consider introducing a dedicated normalizer/DTO for route metadata that enforces schema.

### 7. Array shape clarifications & list hygiene (low)
- **Problem**: Several helpers trigger "array might not be a list" and "no value type specified" warnings.
- **Actions**:
  1. Audit helper methods that build arrays (e.g., `normalizeTargetIds`, `WriteContext` constructor) and replace `array` with `list<T>`/`array<string,T>` depending on semantics.
  2. Add explicit generics or reindex arrays via `array_values()` when order-only lists are required.
  3. Extend PHPDoc for iterative parameters/return values to include value types.

### 8. Final verification (after fixes)
- Ensure composer scripts or CI configuration run PHPStan with `--memory-limit=1G` (or adjust `phpstan.neon`) to avoid worker crashes.
- Re-run `vendor/bin/phpstan analyse` and address any residual low-level notices.

## Suggested sequencing
1. Finish metadata hardening (Section 1) to unblock Doctrine-heavy services.
2. Refactor instantiator & processors (Sections 2–4) to eliminate `mixed` entity flows.
3. Apply configuration sanitization and array/list cleanups (Sections 5–7).
4. Iterate on PHPStan until zero errors remain.
