# Automatic Route Generation

The JSON:API Bundle automatically produces CRUD routes for every registered resource.

## Quick Start

### 1. Create an entity with attributes

```php
// src/Entity/Article.php
use Doctrine\ORM\Mapping as ORM;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\Relationship;

#[ORM\Entity]
#[JsonApiResource(type: 'articles')]
class Article
{
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    #[Id]
    #[Attribute]
    private string $id;
    
    #[ORM\Column]
    #[Attribute]
    private string $title;
    
    #[ORM\ManyToOne(targetEntity: Author::class)]
    #[Relationship(targetType: 'authors')]
    private ?Author $author = null;
    
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[Relationship(targetType: 'tags', toMany: true)]
    private Collection $tags;
    
    // Getters/setters...
}
```

### 2. Enable automatic route generation

```yaml
# config/routes.yaml
jsonapi_auto:
    resource: .
    type: jsonapi
```

### 3. Done!

You immediately get the following routes:

#### CRUD routes

```
GET    /api/articles           - list articles
POST   /api/articles           - create article
GET    /api/articles/{id}      - fetch article
PATCH  /api/articles/{id}      - update article
DELETE /api/articles/{id}      - delete article
```

#### Relationship routes

```
# To-One relationship (author)
GET    /api/articles/{id}/relationships/author
PATCH  /api/articles/{id}/relationships/author

# To-Many relationship (tags)
GET    /api/articles/{id}/relationships/tags
PATCH  /api/articles/{id}/relationships/tags
POST   /api/articles/{id}/relationships/tags
DELETE /api/articles/{id}/relationships/tags

# Related resources
GET    /api/articles/{id}/author
GET    /api/articles/{id}/tags
```

## Configuration

### Change the route prefix

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    route_prefix: /api/v1
```

Routes are now available under `/api/v1/articles`.

### Disable relationship routes

If you do not use relationships, disable their generation:

```php
// config/services.yaml
JsonApi\Symfony\Bridge\Symfony\Routing\JsonApiRouteLoader:
    arguments:
        $enableRelationshipRoutes: false
```

## Route Naming

Generated routes follow these naming conventions:

### CRUD routes

- `jsonapi.{type}.index` - list resources
- `jsonapi.{type}.create` - create resource
- `jsonapi.{type}.show` - fetch resource
- `jsonapi.{type}.update` - update resource
- `jsonapi.{type}.delete` - delete resource

### Relationship routes

- `jsonapi.{type}.relationships.{relationship}.show` - fetch relationship
- `jsonapi.{type}.relationships.{relationship}.update` - update relationship
- `jsonapi.{type}.relationships.{relationship}.add` - add to to-many relationship
- `jsonapi.{type}.relationships.{relationship}.remove` - remove from to-many relationship

### Related resource routes

- `jsonapi.{type}.related.{relationship}` - fetch related resources

### Examples

```php
// Generate URLs in a controller
$url = $this->generateUrl('jsonapi.articles.show', ['id' => '123']);
// /api/articles/123

$url = $this->generateUrl('jsonapi.articles.relationships.author.show', ['id' => '123']);
// /api/articles/123/relationships/author

$url = $this->generateUrl('jsonapi.articles.related.tags', ['id' => '123']);
// /api/articles/123/tags
```

## Route Customisation

### Add extra routes

You can always declare routes manually:

```yaml
# config/routes.yaml
jsonapi_auto:
    resource: .
    type: jsonapi

# Additional custom routes
article_publish:
    path: /api/articles/{id}/publish
    controller: App\Controller\PublishArticleController
    methods: [POST]
```

### Override built-in routes

Define the custom route **before** the automatic block to override it:

```yaml
# config/routes.yaml

# Custom route for creating articles
article_create_custom:
    path: /api/articles
    controller: App\Controller\CustomCreateArticleController
    methods: [POST]

# Automatic generation (skips already defined entries)
jsonapi_auto:
    resource: .
    type: jsonapi
```

**Important:** Symfony uses the first matching route, so order matters.

### Disable automatic routes for a specific resource

If you prefer full manual control, skip the `#[JsonApiResource]` attribute or define routes by hand:

```yaml
# config/routes.yaml

# Manual routes for products
product_index:
    path: /api/products
    controller: App\Controller\Product\IndexController
    methods: [GET]

product_create:
    path: /api/products
    controller: App\Controller\Product\CreateController
    methods: [POST]

# ... remaining routes

# Automatic generation for every other resource
jsonapi_auto:
    resource: .
    type: jsonapi
```

## ID Requirements

By default the `{id}` placeholder accepts any value except forward slashes (`[^/]+` regex).

This allows:
- UUID: `550e8400-e29b-41d4-a716-446655440000`
- Numbers: `123`
- Strings: `my-article-slug`

The regex excludes forward slashes to prevent conflicts with relationship routes like `/api/articles/{id}/relationships/author`.

Restrict the format by redefining the route:

```yaml
# config/routes.yaml

# UUID only
article_show_uuid:
    path: /api/articles/{id}
    controller: JsonApi\Symfony\Http\Controller\ShowController
    defaults:
        type: articles
    requirements:
        id: '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'
    methods: [GET]

# Automatic generation for everything else
jsonapi_auto:
    resource: .
    type: jsonapi
```

## Route Debugging

List every generated route:

```bash
php bin/console debug:router | grep jsonapi
```

Inspect a specific route:

```bash
php bin/console debug:router jsonapi.articles.show
```

## Usage Examples

### Minimal setup

```php
// src/Entity/Product.php
#[ORM\Entity]
#[JsonApiResource(type: 'products')]
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    #[Id]
    #[Attribute]
    private string $id;
    
    #[ORM\Column]
    #[Attribute]
    private string $name;
}
```

```yaml
# config/routes.yaml
jsonapi_auto:
    resource: .
    type: jsonapi
```

```yaml
# config/services.yaml
JsonApi\Symfony\Contract\Data\ResourceRepository:
    alias: JsonApi\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository

JsonApi\Symfony\Contract\Data\ResourcePersister:
    alias: JsonApi\Symfony\Bridge\Doctrine\Persister\GenericDoctrinePersister
```

**Done!** All CRUD operations are live:

```bash
# List products
curl http://localhost/api/products

# Create a product
curl -X POST http://localhost/api/products \
  -H "Content-Type: application/vnd.api+json" \
  -d '{"data":{"type":"products","attributes":{"name":"Laptop"}}}'

# Fetch a product
curl http://localhost/api/products/123

# Update a product
curl -X PATCH http://localhost/api/products/123 \
  -H "Content-Type: application/vnd.api+json" \
  -d '{"data":{"type":"products","id":"123","attributes":{"name":"Gaming Laptop"}}}'

# Delete a product
curl -X DELETE http://localhost/api/products/123
```

## Troubleshooting

### Routes are missing

1. Verify the `#[JsonApiResource]` attribute is present on the entity.
2. Check that the entity is registered with the `ResourceRegistry`.
3. Confirm `type: jsonapi` is configured in `routes.yaml`.
4. Clear the cache: `php bin/console cache:clear`.

### Route conflicts

The error "Route already exists" means another route with the same name was registered.

Fix it by:
1. Renaming your custom route, or
2. Reordering route definitions in `routes.yaml`.

### 404 Not Found

1. Confirm the route exists: `php bin/console debug:router`.
2. Ensure the controller is registered as a service.
3. Make sure the `ResourceRepository` and `ResourcePersister` services are wired.

## See Also

- [Route Naming Conventions](routing-naming-conventions.md) - Configure snake_case vs kebab-case route names
- [Custom Routes](custom-routes.md) - Define custom endpoints beyond standard CRUD
- [Doctrine Integration](doctrine-integration.md)
- [Validation](validation.md)
- [Relationships](relationships.md)
