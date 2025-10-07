# Quick Start

This guide shows how to build a production-ready JSON:API in five minutes with CRUD operations, validation, and relationships.

## Requirements

- PHP 8.2+
- Symfony 7.1+
- Doctrine ORM 3.0+

## Installation

```bash
composer require jsonapi/symfony-jsonapi-bundle
```

## Step 1: Create an Entity

```php
// src/Entity/Article.php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\Relationship;
use JsonApi\Symfony\Resource\Attribute\SerializationGroups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'articles')]
#[JsonApiResource(type: 'articles')]
class Article
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    #[Attribute]
    #[Assert\NotBlank]
    private string $content;

    #[ORM\ManyToOne(targetEntity: Author::class)]
    #[Relationship(targetType: 'authors')]
    private ?Author $author = null;

    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[Relationship(targetType: 'tags', toMany: true)]
    private Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    // Getters and setters...
    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    public function setAuthor(?Author $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
        return $this;
    }

    public function removeTag(Tag $tag): self
    {
        $this->tags->removeElement($tag);
        return $this;
    }
}
```

## Step 2: Wire Services

```yaml
# config/services.yaml
services:
    # Generic Doctrine implementations
    JsonApi\Symfony\Contract\Data\ResourceRepository:
        alias: JsonApi\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository

    JsonApi\Symfony\Contract\Data\ResourcePersister:
        alias: JsonApi\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrinePersister

    JsonApi\Symfony\Contract\Data\RelationshipReader:
        alias: JsonApi\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler

    JsonApi\Symfony\Contract\Data\RelationshipUpdater:
        alias: JsonApi\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler

    JsonApi\Symfony\Contract\Tx\TransactionManager:
        alias: JsonApi\Symfony\Bridge\Doctrine\Transaction\DoctrineTransactionManager
```

## Step 3: Enable Automatic Route Generation

```yaml
# config/routes.yaml
jsonapi_auto:
    resource: .
    type: jsonapi
```

## Step 4: Configure the Bundle (optional)

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    route_prefix: /api
    atomic:
        enabled: false  # Disable Atomic Operations if you don't need them
```

## You're Done! 🎉

You now have a fully featured JSON:API with:

### ✅ CRUD Operations

```bash
# List articles
GET /api/articles

# Create an article
POST /api/articles
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "articles",
    "attributes": {
      "title": "My First Article",
      "content": "This is the content..."
    }
  }
}

# Fetch an article
GET /api/articles/{id}

# Update an article
PATCH /api/articles/{id}
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "articles",
    "id": "{id}",
    "attributes": {
      "title": "Updated Title"
    }
  }
}

# Delete an article
DELETE /api/articles/{id}
```

### ✅ Automatic Validation

```bash
# Attempt to create an article with a short title
POST /api/articles
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "articles",
    "attributes": {
      "title": "AB",  # Too short!
      "content": "Content"
    }
  }
}

# Ответ: 422 Unprocessable Entity
{
  "errors": [
    {
      "status": "422",
      "code": "validation_error",
      "title": "Validation Error",
      "detail": "This value is too short. It should have 3 characters or more.",
      "source": {
        "pointer": "/data/attributes/title"
      }
    }
  ]
}
```

### ✅ Relationships

```bash
# Fetch article author
GET /api/articles/{id}/relationships/author

# Set the author
PATCH /api/articles/{id}/relationships/author
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "authors",
    "id": "author-123"
  }
}

# Add tags
POST /api/articles/{id}/relationships/tags
Content-Type: application/vnd.api+json

{
  "data": [
    { "type": "tags", "id": "tag-1" },
    { "type": "tags", "id": "tag-2" }
  ]
}

# Remove a tag
DELETE /api/articles/{id}/relationships/tags
Content-Type: application/vnd.api+json

{
  "data": [
    { "type": "tags", "id": "tag-1" }
  ]
}

# Retrieve related resources
GET /api/articles/{id}/author
GET /api/articles/{id}/tags
```

### ✅ Pagination

```bash
GET /api/articles?page[number]=1&page[size]=10
```

### ✅ Sorting

```bash
GET /api/articles?sort=-createdAt,title
```

### ✅ Filtering

```bash
GET /api/articles?filter[title]=Symfony
```

### ✅ Sparse Fieldsets

```bash
GET /api/articles?fields[articles]=title,content
```

### ✅ Include

```bash
GET /api/articles?include=author,tags
```

## What's Next?

### Customisation

If you need resource-specific behaviour, implement a dedicated repository or persister:

```php
// src/JsonApi/Repository/ArticleRepository.php
namespace App\JsonApi\Repository;

use JsonApi\Symfony\Contract\Data\TypedResourceRepository;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Query\Slice;

final class ArticleRepository implements TypedResourceRepository
{
    public function supports(string $type): bool
    {
        return $type === 'articles';
    }

    public function findCollection(string $type, Criteria $criteria): Slice
    {
        // Your custom logic
        // e.g. filtering by status, eager loading, etc.
    }

    // ... remaining methods
}
```

```yaml
# config/services.yaml
App\JsonApi\Repository\ArticleRepository:
    tags:
        - { name: 'jsonapi.repository', priority: 10 }

# Generic repository for every other type
JsonApi\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository:
    tags:
        - { name: 'jsonapi.repository', priority: 0 }
```

### Additional Capabilities

- [Automatic route generation](automatic-route-generation.md)
- [Doctrine Integration](doctrine-integration.md)
- [Validation](validation.md)
- [Relationships](relationships.md)
- [Filtering](filtering.md)
- [Pagination](pagination.md)
- [Sorting](sorting.md)
- [Sparse Fieldsets](sparse-fieldsets.md)
- [Include](include.md)
- [Atomic Operations](atomic-operations.md)
- [Caching](caching.md)
- [Profiles](profiles.md)

## Troubleshooting

### Container fails with ServiceNotFoundException

Make sure the Generic Doctrine implementations are registered in `config/services.yaml`.

If you are not using Doctrine, the bundle ships NullObject implementations that work out of the box.

### Validation does not run

Ensure you use `ValidatingDoctrinePersister` instead of `GenericDoctrinePersister`:

```yaml
JsonApi\Symfony\Contract\Data\ResourcePersister:
    alias: JsonApi\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrinePersister
```

### Routes are not generated

1. Confirm `type: jsonapi` is set in `config/routes.yaml`.
2. Clear the cache: `php bin/console cache:clear`.
3. Inspect routes: `php bin/console debug:router | grep jsonapi`.

## Examples

Full application samples are available in the repository:

- [Simple Blog](https://github.com/jsonapi/symfony-jsonapi-bundle/tree/main/examples/blog)
- [E-commerce](https://github.com/jsonapi/symfony-jsonapi-bundle/tree/main/examples/ecommerce)
- [Multi-tenant SaaS](https://github.com/jsonapi/symfony-jsonapi-bundle/tree/main/examples/saas)

## Support

- [GitHub Issues](https://github.com/jsonapi/symfony-jsonapi-bundle/issues)
- [Discussions](https://github.com/jsonapi/symfony-jsonapi-bundle/discussions)
- [Stack Overflow](https://stackoverflow.com/questions/tagged/jsonapi+symfony)
