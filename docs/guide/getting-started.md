# Getting Started with JsonApiBundle

**Version**: 0.1.0  
**Last Updated**: 2025-10-07

---

## Table of Contents

1. [Introduction](#introduction)
2. [Prerequisites](#prerequisites)
3. [Installation](#installation)
4. [Quick Start](#quick-start)
5. [Your First Resource](#your-first-resource)
6. [Testing Your API](#testing-your-api)
7. [Next Steps](#next-steps)

---

## Introduction

JsonApiBundle is a **JSON:API 1.1 compliant** bundle for Symfony 7+ that provides a complete solution for building modern REST APIs. It handles:

- ✅ **Automatic endpoint generation** - No need to write controllers
- ✅ **Query parameter parsing** - `include`, `fields`, `sort`, `page`
- ✅ **Relationship handling** - To-one and to-many relationships
- ✅ **Write operations** - POST, PATCH, DELETE with validation
- ✅ **Atomic operations** - Batch operations in a single request
- ✅ **Interactive documentation** - Swagger UI / Redoc integration
- ✅ **Caching & ETags** - HTTP caching with preconditions
- ✅ **Extensibility** - Profiles, hooks, and custom operators

---

## Prerequisites

Before you begin, ensure you have:

- **PHP 8.2 or higher**
- **Symfony 7.1 or higher**
- **Composer** installed
- Basic understanding of:
  - Symfony framework
  - JSON:API specification (helpful but not required)
  - REST API concepts

---

## Installation

### Step 1: Install the Bundle

```bash
composer require jsonapi/symfony-jsonapi-bundle
```

### Step 2: Register the Bundle

If you're using Symfony Flex, the bundle is registered automatically. Otherwise, add it manually:

```php
// config/bundles.php
return [
    // ... other bundles
    JsonApi\Symfony\Bridge\Symfony\Bundle\JsonApiBundle::class => ['all' => true],
];
```

### Step 3: Create Configuration File

Create a configuration file for the bundle:

```bash
touch config/packages/jsonapi.yaml
```

Add basic configuration:

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    strict_content_negotiation: true
    media_type: 'application/vnd.api+json'
    route_prefix: '/api'
    pagination:
        default_size: 25
        max_size: 100
```

---

## Quick Start

Let's build a simple blog API with articles and authors in 5 minutes!

### Step 1: Create Your Entity/Model

Create a simple `Article` class:

```php
// src/Entity/Article.php
namespace App\Entity;

use DateTimeImmutable;
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\Relationship;

#[JsonApiResource(type: 'articles')]
class Article
{
    #[Id]
    #[Attribute]
    public string $id;

    #[Attribute]
    public string $title;

    #[Attribute(writable: false)]
    public DateTimeImmutable $createdAt;

    #[Relationship(targetType: 'authors')]
    public ?Author $author = null;

    public function __construct(string $id, string $title)
    {
        $this->id = $id;
        $this->title = $title;
        $this->createdAt = new DateTimeImmutable();
    }
}
```

Create an `Author` class:

```php
// src/Entity/Author.php
namespace App\Entity;

use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;

#[JsonApiResource(type: 'authors')]
class Author
{
    #[Id]
    #[Attribute]
    public string $id;

    #[Attribute]
    public string $name;

    #[Attribute]
    public string $email;

    public function __construct(string $id, string $name, string $email)
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
    }
}
```

### Step 2: Register Resources

Register your resources in the service container:

```yaml
# config/services.yaml
services:
    # ... existing services

    App\Entity\Article:
        tags:
            - { name: 'jsonapi.resource', type: 'articles' }

    App\Entity\Author:
        tags:
            - { name: 'jsonapi.resource', type: 'authors' }
```

### Step 3: Implement Data Layer

The bundle requires you to implement data layer contracts. For this tutorial, we'll use a simple in-memory repository:

```php
// src/Repository/ArticleRepository.php
namespace App\Repository;

use App\Entity\Article;
use JsonApi\Symfony\Contract\Data\ResourceRepository;
use JsonApi\Symfony\Contract\Data\Slice;
use JsonApi\Symfony\Query\Criteria;

class ArticleRepository implements ResourceRepository
{
    private array $articles = [];

    public function __construct()
    {
        // Seed with sample data
        $this->articles = [
            new Article('1', 'Getting Started with JSON:API'),
            new Article('2', 'Advanced Symfony Techniques'),
            new Article('3', 'Building REST APIs'),
        ];
    }

    public function findCollection(string $type, Criteria $criteria): Slice
    {
        $offset = ($criteria->pagination->number - 1) * $criteria->pagination->size;
        $items = array_slice($this->articles, $offset, $criteria->pagination->size);

        return new Slice(
            items: $items,
            pageNumber: $criteria->pagination->number,
            pageSize: $criteria->pagination->size,
            totalItems: count($this->articles),
        );
    }

    public function findOne(string $type, string $id, Criteria $criteria): ?object
    {
        foreach ($this->articles as $article) {
            if ($article->id === $id) {
                return $article;
            }
        }
        return null;
    }

    public function findRelated(string $type, string $relationship, array $identifiers): iterable
    {
        // For this simple example, return empty array
        return [];
    }
}
```

Register the repository:

```yaml
# config/services.yaml
services:
    App\Repository\ArticleRepository:
        tags:
            - { name: 'jsonapi.repository', type: 'articles' }
```

### Step 4: Test Your API

Start the Symfony development server:

```bash
symfony server:start
```

Or use PHP's built-in server:

```bash
php -S localhost:8000 -t public
```

Now test your endpoints:

**Get all articles:**
```bash
curl http://localhost:8000/api/articles
```

**Get a single article:**
```bash
curl http://localhost:8000/api/articles/1
```

**Use sparse fieldsets:**
```bash
curl "http://localhost:8000/api/articles?fields[articles]=title"
```

**Pagination:**
```bash
curl "http://localhost:8000/api/articles?page[number]=1&page[size]=10"
```

---

## Your First Resource

Let's understand what we just created:

### Resource Attributes

The `#[JsonApiResource]` attribute marks a class as a JSON:API resource:

```php
#[JsonApiResource(
    type: 'articles',              // Resource type in URLs
    routePrefix: '/api',           // Optional: override global prefix
    description: 'Blog articles',  // Optional: for documentation
    exposeId: true                 // Optional: include ID in attributes
)]
class Article { }
```

### ID Attribute

Every resource needs an identifier:

```php
#[Id]
#[Attribute]  // Also expose as attribute
public string $id;
```

### Attributes

Mark properties as JSON:API attributes:

```php
#[Attribute(
    name: 'title',        // Optional: custom name
    readable: true,       // Can be read via GET
    writable: true        // Can be written via POST/PATCH
)]
public string $title;

#[Attribute(writable: false)]  // Read-only
public DateTimeImmutable $createdAt;
```

### Relationships

Define relationships to other resources:

```php
#[Relationship(
    toMany: false,              // To-one relationship
    targetType: 'authors',      // Target resource type
    inverse: 'articles'         // Optional: inverse relationship
)]
public ?Author $author = null;

#[Relationship(toMany: true, targetType: 'comments')]
public array $comments = [];
```

---

## Testing Your API

### Using cURL

**GET collection:**
```bash
curl -H "Accept: application/vnd.api+json" \
     http://localhost:8000/api/articles
```

**GET single resource:**
```bash
curl -H "Accept: application/vnd.api+json" \
     http://localhost:8000/api/articles/1
```

**POST (create):**
```bash
curl -X POST \
     -H "Content-Type: application/vnd.api+json" \
     -H "Accept: application/vnd.api+json" \
     -d '{
       "data": {
         "type": "articles",
         "attributes": {
           "title": "My New Article"
         }
       }
     }' \
     http://localhost:8000/api/articles
```

### Using Swagger UI

The bundle includes interactive API documentation:

1. Navigate to `http://localhost:8000/_jsonapi/docs`
2. Explore your API endpoints
3. Try out requests directly from the browser

---

## Next Steps

Congratulations! You've created your first JSON:API with JsonApiBundle. 🎉

### Learn More

- **[Configuration Guide](configuration.md)** - Complete configuration reference
- **[Doctrine Integration](integration-doctrine.md)** - Use with Doctrine ORM
- **[Advanced Features](advanced-features.md)** - Profiles, hooks, events
- **[Public API Reference](../api/public-api.md)** - Complete API documentation

### Common Next Steps

1. **Add Write Operations** - Implement `ResourcePersister` for POST/PATCH/DELETE
2. **Integrate with Doctrine** - Use real database instead of in-memory
3. **Add Validation** - Use Symfony Validator for input validation
4. **Enable Caching** - Configure HTTP caching and ETags
5. **Add Authentication** - Secure your API with Symfony Security

### Need Help?

- 📖 [Troubleshooting Guide](troubleshooting.md)
- 💬 [GitHub Discussions](https://github.com/AlexFigures/jsonapi-symfony/discussions)
- 🐛 [Report Issues](https://github.com/AlexFigures/jsonapi-symfony/issues)
- 📚 [JSON:API Specification](https://jsonapi.org/format/1.1/)

---

**Happy coding!** 🚀

