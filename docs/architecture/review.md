# Architecture & Extensibility Review

## Layering & Dependencies
- HTTP controllers depend exclusively on ports (`ResourcePersister`, `TransactionManager`, `ResourceRegistryInterface`) and infrastructure helpers (`DocumentBuilder`, `LinkGenerator`).【F:src/Http/Controller/CreateResourceController.php†L34-L94】 This keeps transport logic decoupled from persistence.
- Domain contracts reside under `JsonApi\\Symfony\\Contract`, documenting the public surface for adapters and policies.【F:src/Contract/Data/ResourcePersister.php†L1-L26】【F:src/Contract/Tx/TransactionManager.php†L1-L17】 However, classes lack `#[AsPublic]`/PHPDoc markers clarifying BC status.
- Document construction is encapsulated in `Http\Document\DocumentBuilder`, avoiding direct repository access and operating on immutable metadata snapshots.【F:src/Http/Document/DocumentBuilder.php†L43-L165】

## Extension Points
- Ports exist for persistence (`ResourcePersister`, `ResourceRepository`, `RelationshipUpdater`), querying (`Slice`, `Criteria`), and transactions, enabling adapters for Doctrine/HTTP/etc. Yet DI tags or cookbook documentation for registering custom operators/profiles are missing from the bundle configuration.
- Profile negotiation surfaces `ProfileRegistry` and `ProfileNegotiator`, and the subscriber mutates responses post-controller.【F:tests/Functional/Profile/ProfileNegotiationSubscriberTest.php†L23-L74】 No guidance for hooking serialization transformations beyond `ProfileContext`.
- Filter/operator architecture is present but lacks public tagging instructions (no README coverage or service tags in config/). Need to expose canonical extension documentation.

## Quality Gates
- Repository has phpstan/infection tooling declared in composer but no CI workflows or deptrac/backward-compatibility checks committed. Branch lacks `deptrac.yaml` and `roave-backward-compatibility-check` baseline, leaving architectural rules unenforced.
- Mutation score targets are unspecified; without `infection.json` thresholds, MSI regressions would pass unnoticed.

## Risks & Recommendations
1. **Public API definition** – Add a `docs/architecture/public-api.md` enumerating stable namespaces and mark service definitions with Symfony's `#[AsAlias]` or PHPDoc `@experimental` where needed. Introduce automated BC checks in CI.
2. **Dependency boundaries** – Author a `deptrac.yaml` capturing layers (Contracts → Application → Infrastructure → Symfony Bridge) and gate via CI; document expected dependencies for DocumentBuilder, filters, and controllers.
3. **Extensibility guides** – Provide cookbook entries for registering custom filters/operators/profiles, with DI tag references, and ensure services meant for extension are non-final (conversely, mark sensitive internals `final`). Review `src/Filter` classes for appropriate `final` usage.
4. **Configuration ergonomics** – Generate default `config/services.php`/`packages/jsonapi.yaml` stubs covering feature flags (limits, cache, profiles) to make extension points discoverable.
5. **Event & invalidation contracts** – Formalize `Invalidation` events as part of the public API and demonstrate hooking surrogate key purgers once implemented.
