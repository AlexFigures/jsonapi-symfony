# Extension and configuration gaps before RC

This pass stays on `fix/acceptance-gaps`. It changes the bundle and its internal tests only. The external `example-jsonapi-bundle` consumer is neither edited nor executed; its reported failures define the regression targets. Independent acceptance confirmation remains a release gate.

## Executable coverage

The main regression suites are:

- `tests/Unit/Regression/RcExtensionContractTest.php`: parser, custom action criteria, identifier budgets, surrogate keys, Atomic configuration, OpenAPI and JSON Schema contracts.
- `tests/Functional/Regression/RcContainerContractTest.php`: a compiled Symfony bundle container, required constructor DI, command registration, router/controller event ordering, documentation MIME types and controller annotation discovery.
- `tests/Integration/Profile/RcProfileContractTest.php`: actual PostgreSQL repository/processor execution, profile hooks, input DTO validation, representation selection and soft deletion.
- `tests/Unit/Profile/ProfileConstructorDiTest.php`: invalid requirements and unknown profile URIs still fail after DI construction.

| Gap | Cause and implementation | Bundle regression |
| --- | --- | --- |
| `EXTENSIBILITY-PROFILE-DI` | Compiler validation cannot instantiate required-DI profiles itself. Existing deferred validation is preserved and extended to configured/autowired/factory definitions, so optional constructors also use their actual configuration. The registry validates the constructed services. | Compiled bundle DI profile and validation command; existing required-field/unknown-URI tests. |
| `EXTENSIBILITY-CUSTOM-OPERATOR` | Parser hardcoded built-in names. Its optional operator registry accepts registered operators, validates raw operands, normalizes custom values and retains field/operator whitelist and AST limits. | Registered `starts_with` through parser and whitelist. |
| `CUSTOM-ACTION-QUERY-PARAMETER` | Custom context passed application parameters into the generic strict parser. It now parses a duplicate containing JSON:API query members; the handler keeps the original request and application parameters. | `q` retained, pagination parsed; ordinary CRUD still rejects `q`. |
| `PROFILE-READ-HOOK` | Built-in Doctrine repository never called read hooks. It now clones criteria, runs collection/item hooks before SQL, and applies item filters as well as custom conditions. | Collection and item excluded; caller criteria unchanged. |
| `PROFILE-RELATIONSHIP-HOOK` | Standalone updater and resource relationship resolver never dispatched relationship hooks. Both dispatch before altering the relationship. | Replace/add/remove to-many, replace to-one and resource-body relationship replacement denied with original persisted linkage retained. |
| `WRITE-REQUEST-DTO` | Doctrine processor ignored `writeRequests`. `DoctrineWriteRequestMapper` chooses create/update input, denormalizes and validates before mapping to a managed entity, then uses `WriteMapperInterface`. Entity validation and relationship processing remain in the processor. | Invalid POST persists nothing; invalid PATCH does not alter managed entity; valid input persists via mapper. |
| `VERSION-RESOLVER-CONTEXT` | Repository used `getDefinition()` with an empty context. Both collection and item reads now pass the current type-specific negotiated context. | Alternate DTO projection and JSON document; context removal restores normal entity representation. |
| `FILTER-PUBLIC-NULL-NAMES` | Parser supported only `isnull` although public metadata documented `null`/`nnull`. All aliases now produce `NullCheck`; false operands invert the condition. | Five alias/boolean combinations. |
| `RELATIONSHIP-BUDGET-ENDPOINT` | Standalone linkage did not receive the identifier limit. `LinkageBuilder` checks page linkage and probes at most budget + 1 when the requested size exceeds the budget. It rejects overflow without truncation. | Oversized reader called once with bounded probe; controlled 400. |
| `CACHE-SURROGATE-ROUTES` | Key builder matched obsolete generic route names and missed generated `rel` attributes. Keys now derive from resource type/ID/relationship route attributes. | Generated relationship and collection routes produce configured keys. |
| `ATOMIC-LID-CONFIG` | Atomic parser ignored the configured local-ID switch. It rejects local IDs in refs, resource objects and nested relationship identifiers before dispatch. Attributes and meta are not recursively treated as identifiers. | Disabled root, ref and nested linkage lids with precise operation pointers. |
| `MEDIA-CHANNEL-ROUTING` | Request priority 512 preceded routing and controller attribute discovery. Negotiation now runs on controller priority -16, after the media-channel subscriber. | Real requests select route-name and method-attribute channels. |
| `PROFILE-SOFT-VISIBILITY` | Query hook read obsolete config only. Canonical `default_visibility` now selects exclude/include/only. | PostgreSQL row visibility for each mode. |
| `PROFILE-SOFT-DELETE-SEMANTICS` | Delete hook was informational and processor always removed the entity. Active built-in soft-delete profile now replaces removal with a field update when configured; hard deletion remains explicit. | Flush/clear/reload proves retained timestamp or physical removal. |
| `PROFILE-SOFT-QUERY-FLAGS` | Profile flags entered the resource whitelist before the hook. Optional `FilterParameterProviderInterface` declares owned filter flags, which are excluded from the AST only for active query hooks. Original request values remain available and soft flags require booleans. | Configured flag consumed when active, rejected when inactive; malformed flag rejected. |
| `PROFILE-SOFT-BOOLEAN` | Query always used null predicates. Boolean strategy uses typed boolean comparisons; deletion stores `true`. | PostgreSQL selects only false rows and subsequently hides a soft-deleted row. |
| `CONFIG-JSON-SCHEMA` | Config had no corresponding route/controller. The loader registers the configured endpoint, backed by shared representation definitions, independently of OpenAPI enablement. References and nullability are converted to JSON Schema 2020-12. | Actual native-MIME route; schema definitions/reference rewriting with OpenAPI disabled. |
| `DX-PROFILE-COMMAND` | Command class was never registered. Its tagged service supports reflection validation when there is no default ORM manager. | Framework console finds and executes `jsonapi:validate-profiles`. |
| `DOCS-OPERATIONS` | Generator always emitted all CRUD methods. Paths now follow `allowedOperations`, including relationship reads/writes, and empty paths are omitted. | Read-only and custom-only resources omit disabled operations. |
| `DOCS-NEGOTIATION` | Documentation inherited the JSON:API-only policy. Generated docs routes declare their native MIME type; explicit application channels take precedence. JSON documents also accept `application/json`. | Kernel OpenAPI native/JSON, HTML UI and JSON Schema requests. |
| `DOCS-INHERITANCE` | Generator documented only local whitelist entries. It expands inherited fields within the same depth and exclusion rules as runtime whitelists. | Inherited author/tag filters and author sorts; excluded email absent. |
| `DOCS-WRITABLE-SCHEMA` | Schema had no write-group knowledge. Generator consults the Symfony serializer metadata factory for resource data-class fields and write groups, including YAML metadata when configured. | Server-owned timestamp is `readOnly`; existing YAML write-metadata regressions remain required. |
| `DOCS-PAGINATION-CONFIG` | Generator used literal 20/100. The service receives effective `PaginationConfig`; standalone construction retains legacy defaults. | Configured 5/20 values appear in the public parameters. |
| `DOCS-ENDPOINT-EXAMPLES` | Examples were ignored, and controller collection handled only `Class::method` strings. The collector also supports array/invokable controllers; examples attach to request-body media or response media when there is no request body. | Actual router/controller discovery exposes example summary/value/description in OpenAPI. |

## Runtime boundaries

### Hooks and representations

The built-in Doctrine repository and processors now execute the covered read/write/relationship hooks. Custom repositories/processors remain responsible for interpreting profile conditions and integrating their own persistence operations. A Doctrine query-builder condition is not a portable authorization rule for unrelated persistence engines. Hook SQL and side effects still require an application cost policy; this pass does not infer or automatically batch opaque callback work.

Read hooks receive a copy of criteria for each repository invocation. Conditions should describe deterministic query restrictions, rather than depend on the count of SQL statements Doctrine's paginator executes. Representation resolution uses request-local context, including collection, item and already-planned included resources; it does not store a selected version in shared metadata.

Input DTOs are independent of entity serializer groups. Their declared fields and validator constraints define the configured input contract; entity groups still apply to the ordinary entity input path. The mapper handles DTO-to-model transformation; the default mapper copies public initialized DTO properties through PropertyAccessor. Use a custom mapper for nested/domain transformations and constructor-dependent entities. An update DTO with initialized defaults may apply those defaults: use a custom mapper/presence model when PATCH omission must be preserved. JSON:API resource attribute validation still precedes DTO mapping.

### Soft deletion

Canonical configuration is:

```yaml
jsonapi:
    profiles:
        soft_delete:
            field: deletedAt
            strategy: timestamp # timestamp | boolean
            default_visibility: exclude # exclude | include | only
            delete_semantics: soft # soft | hard
            query_flags:
                with_deleted: withDeleted
                only_deleted: onlyDeleted
```

Deletion changes only when the profile is active for the resource, whether negotiated or enabled by default/per type. Existing `WriteHook` checks run first. Soft deletion then writes a DateTimeImmutable timestamp or `true` and uses the operation's existing transaction/flush boundary. An inactive profile uses ordinary physical deletion. For boolean mode, configure a boolean field; the reflected requirements follow the strategy.

Explicit `only_deleted=true` selects only deleted rows; otherwise `with_deleted=true` includes all rows; otherwise configured default visibility applies. Flags live under `filter[...]`. Only active profiles consume their flags, and malformed operands are rejected. Standalone hook construction retains the old `withTrashedParam`/`onlyTrashedParam` and `deletedAtField` aliases. An explicitly named non-default `SoftDeletable.deletedAtField` takes precedence over the global field.

### Endpoint costs and HTTP policy

Standalone linkage obeys the same configured `relationship_max_identifiers` budget. A requested page larger than the budget is rejected if a bounded probe detects a larger relationship; an explicitly bounded page within the budget remains available. Original page number/size are never silently changed. The native reader already performs SQL pagination; arbitrary custom readers must honor the passed pagination themselves.

Negotiation occurs after router/controller discovery and before the controller runs. Router errors can therefore precede negotiation errors. `onKernelRequest()` remains callable for existing direct integrations, but it is no longer the subscribed kernel event. Explicit media channels override documentation defaults; custom channels should accept the media types their controller actually returns.

Configured surrogate keys use generated route resource attributes. Atomic `lid=false` is an input-parser restriction and cannot weaken transaction preflight. Custom-provider applications no longer register the Doctrine relationship serializer/flush subscriber implicitly, and their fallback relationship services use their actual namespaces.

## Verification and release status

Final internal verification on PHP 8.4.26:

- Complete PHPUnit suite: **1237 tests, 6877 assertions, 6 skipped**, no failures/errors (7m04s). The JUnit artifact is `reports/rc-extension-gaps-bundle.xml`.
- PHPStan: no errors.
- Deptrac: no violations, warnings or errors.
- PHP CS Fixer dry-run: no changes; the final representation regression was checked again after its sparse-fieldset adjustment.
- Composer validation and `git diff --check`: passed.
- Rector dry-run on changed PHP files: proposes modernization in 19 files. These proposals were not applied; Rector is not reported as a clean gate.

This adds 42 regression cases over the preceding 1195-test bundle suite. FrameworkBundle is a dev dependency used to exercise the real container/router/event/console integration rather than manually construct services.

External acceptance/torture verification is deliberately pending. `ATOMIC-VALIDATION-BOUNDARY` remains the separately classified 409-versus-422 limitation; this pass does not change it. No distributed transactions, replication management, sharding engine or tenant framework are introduced. These fixes do not constitute a public API/configuration freeze or an RC release.
