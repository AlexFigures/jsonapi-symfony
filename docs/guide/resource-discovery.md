# Resource discovery and generated routes

The bundle scans `jsonapi.resource_paths` for classes with `JsonApiResource`. The default is `%kernel.project_dir%/src/Entity`. Register the bundle and import a `type: jsonapi` route resource as shown in the [developer path](developer-path.md).

The resource's `operations` list controls generated INDEX, SHOW, CREATE, UPDATE and DELETE endpoints. Set it explicitly when exposing a read-only or custom-action model. A model without SHOW need not have an item self-link; documentation must not advertise disabled operations.

Built-in Doctrine metadata discovery rejects composite identifiers before HTTP execution. A single integer, UUID or natural string identifier uses the supported discovery path. Discovery does not replace application authorization.

Inspect application state with `php bin/console debug:router` and `php bin/console debug:config jsonapi`. Clear the application cache after changing resource declarations.

Source: [resource attribute](../../src/Resource/Attribute/JsonApiResource.php), [discovery pass](../../src/Bridge/Symfony/DependencyInjection/Compiler/ResourceDiscoveryPass.php), [operation enum](../../src/Resource/Definition/ResourceOperation.php).

TODO before freeze: complete route naming/custom prefix examples and discovery diagnostics from executable fixtures. See [documentation TODO](../release/documentation-todo.md).
