# Responses from application controllers

The application-facing [JsonApiResponseFactory](../../src/Http/Response/JsonApiResponseFactory.php) constructs JSON:API responses. It is distinct from the controller-support factory under `Http/Controller/Support`; the latter is implementation plumbing and should not be copied into application integrations without audit.

Build representations using the request's effective resource definition and profile context. Versioned DTOs may expose fewer attributes than persistence entities; sparse fieldsets and normal representations must both respect that definition. A custom model without SHOW must not require a generated item link.

Use [custom routes](custom-routes.md) for application endpoint declarations and [production policies](production-policies.md) for read budgets. Formatting a model does not make arbitrary relationship getters bounded.

TODO before freeze: audit both factory APIs and publish a tested application example for resource, collection, metadata, errors and disabled item links. Resolve their public/internal boundary before the namespace freeze.

## Error type links

`ErrorBuilder::create()`, `fromPointer()`, `fromParameter()` and `fromHeader()` accept the optional named argument `typeLink`. `ErrorObject::withTypeLink()` adds, replaces or removes it. This serializes as `errors[].links.type`, a URI describing the class of errors; `aboutLink` continues to describe a particular occurrence. Both can coexist, and absent links are omitted. Error enrichment and Atomic source rebasing preserve both links.

For custom controllers using JsonApiResponseFactory:

```php
return $jsonApi->error(403, 'Access denied.')
    ->withCode('forbidden')
    ->withTypeLink('https://example.org/problems/forbidden')
    ->build();
```

`withTypeLink()` also applies to each validation error. `withLinks()` retains its existing document-level behavior; it does not assign error-object links. The bundle never fetches the link target.
