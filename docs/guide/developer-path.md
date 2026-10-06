# Developer path

This path describes the current stabilization branch. The 1.0 API freeze and independent acceptance gate remain pending. Begin with one resource and add policies deliberately; application authorization and database topology remain your responsibility.

## 1. Install and register

```bash
composer require alexfigures/symfony-jsonapi-bundle
```

For Doctrine persistence, install/configure Symfony's Doctrine integration and ORM separately. The bundle does not install a database server or configure your connection. Verify platform requirements in your application; the [compatibility page](../release/compatibility.md) distinguishes Composer constraints from tested combinations.

Register the bundle in `config/bundles.php` if it is not already present:

```php
AlexFigures\Symfony\Bridge\Symfony\Bundle\JsonApiBundle::class => ['all' => true],
```

Configure `config/packages/jsonapi.yaml`:

```yaml
jsonapi:
    resource_paths: ['%kernel.project_dir%/src/Entity']
    route_prefix: /api
    data_layer:
        provider: doctrine
    pagination:
        default_size: 25
        max_size: 100
    cache:
        headers:
            public: false
```

Import generated routes in `config/routes.yaml`:

```yaml
jsonapi:
    resource: .
    type: jsonapi
```

This setup marks cached responses private. Choose a shared-cache policy only after reviewing authorization and representation variation.

The Doctrine provider wires its services through the bundle. Manual aliases to old persister implementations are unnecessary. Use [custom data-layer configuration](data-layer-configuration.md) when your persistence differs.

## 2. Expose one resource

A minimal mapped entity in `src/Entity/Article.php`:

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use AlexFigures\Symfony\Resource\Attribute\Attribute as ApiAttribute;
use AlexFigures\Symfony\Resource\Attribute\Id as ApiId;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Definition\ResourceOperation;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[JsonApiResource(
    type: 'articles',
    operations: [ResourceOperation::INDEX, ResourceOperation::SHOW,
        ResourceOperation::CREATE, ResourceOperation::UPDATE, ResourceOperation::DELETE],
)]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiId]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[ApiAttribute]
    #[Assert\NotBlank]
    public string $title = '';

    public function getId(): ?int
    {
        return $this->id;
    }
}
```

Create and apply the application's Doctrine migration using its usual workflow. Then inspect discovery and routes:

```bash
php bin/console cache:clear
php bin/console debug:router
```

A composite Doctrine primary key is unsupported by the built-in provider and fails discovery. Use a single API identifier or a custom provider. See [resource discovery](resource-discovery.md).

## 3. Read and write

```bash
curl --globoff -H 'Accept: application/vnd.api+json' \
  'http://localhost:8000/api/articles?page[number]=1&page[size]=10'

curl -X POST -H 'Accept: application/vnd.api+json' \
  -H 'Content-Type: application/vnd.api+json' \
  --data '{"data":{"type":"articles","attributes":{"title":"First article"}}}' \
  http://localhost:8000/api/articles
```

GET the returned item URL and retain its ETag. With required write preconditions enabled, PATCH/DELETE must send `If-Match` with the item validator. Missing required headers produce 428; stale validators produce 412. The configured Doctrine guard evaluates the current root representation after acquiring a write lock. [Production policies](production-policies.md) explain the protected boundary.

Before making a field queryable, declare it with `FilterableFields` or `SortableFields`. Query whitelists are separate from serialization visibility. Read [filterable fields](filterable-fields.md) and [sorting configuration](sorting-configuration.md), then add `filter`, `sort`, `fields[articles]` and `include` as needed. Offset pagination uses `page[number]` and `page[size]`; cursor pagination is future work.

## 4. Add relationships and visibility

Declare ORM associations and JSON:API `Relationship` metadata with the target type. Related endpoints return resource representations; `/relationships/{name}` returns identifiers. Native Doctrine endpoints page membership in SQL. Select `relationships.linkage_in_resource` explicitly for your API, particularly for large collections.

Apply visibility through the repository/query scope consistently: item and collection reads, included targets, related collections and linkage must agree. Hiding an attribute is not access control. Custom computed relationships need scoped, paginated endpoint readers and bounded representation batch readers. See [relationship scopes and extension costs](../architecture/relationship-graph-reads.md).

## 5. Extend the application

| Extension | Starting point | Application responsibility |
| --- | --- | --- |
| Custom persistence | [Data layer](data-layer-configuration.md), [contracts](../api/public-api.md) | Visibility, validation, persistence and transaction guarantees |
| Typed persister | `TypedResourcePersister` with `supports(type)`, autoconfiguration or `jsonapi.persister` tag | Match only intended types; own non-ORM single-write persistence semantics |
| Custom filter/sort | [Custom handlers](custom-handlers.md) | Bound parameters, logical composition and explicit collection-sort semantics |
| Profiles/hooks | [Advanced features](advanced-features.md) | Type-scoped activation, batch fetch declarations, bounded application SQL |
| DTO representations / input | [Configuration](configuration.md), [migration](../../UPGRADE-1.0.md) | Correct negotiated fields and input validation |
| Custom actions | [Custom routes](custom-routes.md), [response factory](response-factory.md) | Authorization, query parameters and HTTP semantics |

Per-type configuration keys preserve literal JSON:API names such as `feature-memos`. Default profiles from `profiles.per_type` can apply without an explicit Accept profile. Do not assume an activated profile for one type applies to every type in the graph.

## 6. Prepare production

Review [production policies](production-policies.md): filter/include/linkage budgets, strict fallback behavior, concurrency, Atomic and known limitations. Measure cold-cache SQL for your own graph at different page sizes. Native batch guarantees do not cover arbitrary application getters or hooks.

Run application tests independently of bundle regressions. Before adopting 1.0, check the [release checklist](../release/checklist.md) and [migration draft](../../UPGRADE-1.0.md). Generated OpenAPI documents should reflect the operations and limits you actually enable.
