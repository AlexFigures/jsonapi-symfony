# Responses from application controllers

The application-facing [JsonApiResponseFactory](../../src/Http/Response/JsonApiResponseFactory.php) constructs JSON:API responses. It is distinct from the controller-support factory under `Http/Controller/Support`; the latter is implementation plumbing and should not be copied into application integrations without audit.

Build representations using the request's effective resource definition and profile context. Versioned DTOs may expose fewer attributes than persistence entities; sparse fieldsets and normal representations must both respect that definition. A custom model without SHOW must not require a generated item link.

Use [custom routes](custom-routes.md) for application endpoint declarations and [production policies](production-policies.md) for read budgets. Formatting a model does not make arbitrary relationship getters bounded.

TODO before freeze: audit both factory APIs and publish a tested application example for resource, collection, metadata, errors and disabled item links. Resolve their public/internal boundary before the namespace freeze.
