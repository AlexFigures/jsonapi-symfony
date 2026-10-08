# Resource discovery and generated routes

The bundle scans `jsonapi.resource_paths` for classes with `JsonApiResource`. The default is `%kernel.project_dir%/src/Entity`. Register the bundle and import a `type: jsonapi` route resource as shown in the [developer path](developer-path.md).

The resource's `operations` list controls generated INDEX, SHOW, CREATE, UPDATE and DELETE endpoints. Set it explicitly when exposing a read-only or custom-action model. A model without SHOW need not have an item self-link; documentation must not advertise disabled operations.

Built-in Doctrine metadata discovery rejects composite identifiers before HTTP execution. A single integer, UUID or natural string identifier uses the supported discovery path. Discovery does not replace application authorization.

Inspect application state with `php bin/console debug:router` and `php bin/console debug:config jsonapi`. Clear the application cache after changing resource declarations.

Source: [resource attribute](../../src/Resource/Attribute/JsonApiResource.php), [discovery pass](../../src/Bridge/Symfony/DependencyInjection/Compiler/ResourceDiscoveryPass.php), [operation enum](../../src/Resource/Definition/ResourceOperation.php).

`JsonApiResource(routePrefix: '/reference')` overrides the global prefix for that resource's generated endpoints and document links. Null inherits the global prefix; an empty prefix puts the type directly below the URL root.

Resources outside resource_paths can also be registered as services:

```yaml
services:
    App\Api\ReferenceResource:
        tags: ['jsonapi.resource']
```

The class must carry `JsonApiResource`. Autoconfigured resource services receive this tag automatically. Discovery uses class metadata and does not call the service constructor. The same class found in both a directory and a tag is registered once; conflicting resource types fail discovery.

`ResourceRegistry::getByClass()` prefers an explicitly declared resource class over projection data/view aliases, independently of discovery order. A unique alias resolves to its resource. Multiple aliases without a primary declaration produce a configuration exception; register a primary resource or use distinct classes, then select projections by resource type.

`exposeId=false` controls the synthetic id entry in sparse fieldset validation. Every transported read resource/identifier still has its JSON:API id, and its read schema requires a non-null id.

Generated route names follow `jsonapi.{type}.index`, `.show`, `.create`, `.update`, `.delete` for enabled operations. Relationship and custom routes are visible through `debug:router`; do not construct names for disabled operations. Resource `routePrefix: '/reference'` overrides the global prefix for routes and generated links.

Discovery diagnoses duplicate/ambiguous registrations, unsupported composite Doctrine IDs, invalid relationship/profile requirements and invalid declarations during container/route discovery. Constructor DI profiles are instantiated before their requirements are validated. [Discovery regressions](../../tests/Integration/Discovery) and [container contracts](../../tests/Functional/Regression/RcContainerContractTest.php) cover deterministic failure timing.
