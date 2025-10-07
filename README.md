# JsonApiBundle

[![CI](https://github.com/AlexFigures/jsonapi-symfony/workflows/CI/badge.svg)](https://github.com/AlexFigures/jsonapi-symfony/actions)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg)](https://phpstan.org/)
[![Production Ready](https://img.shields.io/badge/status-production%20ready-brightgreen.svg)](docs/PRODUCTION_READY.md)
[![Spec Conformance](https://img.shields.io/badge/JSON:API-97.8%25-brightgreen.svg)](docs/conformance/spec-coverage.md)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue.svg)](https://www.php.net/)
[![Symfony Version](https://img.shields.io/badge/symfony-%5E7.1-blue.svg)](https://symfony.com/)

**Production-ready JSON:API 1.1 implementation for Symfony with complete filtering, automatic eager loading, and zero N+1 queries.**


## 🚀 Quick Start

### Installation

```bash
composer require jsonapi/symfony-jsonapi-bundle
```

### Basic Setup

1. **Register the bundle** in `config/bundles.php`:

```php
return [
    JsonApi\Symfony\Bridge\Symfony\Bundle\JsonApiBundle::class => ['all' => true],
];
```

2. **Create configuration** in `config/packages/jsonapi.yaml`:

```yaml
jsonapi:
    route_prefix: '/api'
    pagination:
        default_size: 25
        max_size: 100
```

3. **Define your first resource**:

```php
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\Relationship;

#[JsonApiResource(type: 'articles')]
final class Article
{
    #[Id]
    #[Attribute]
    public string $id;

    #[Attribute]
    public string $title;

    #[Relationship(toMany: true, targetType: 'comments')]
    public array $comments = [];
}
```

4. **Implement data layer** (see [Doctrine Integration Guide](docs/guide/integration-doctrine.md))

5. **Start using your API**:

```bash
# Get all articles
curl http://localhost:8000/api/articles

# Filter, sort, and include relationships (no N+1 queries!)
curl "http://localhost:8000/api/articles?filter[status][eq]=published&sort=-createdAt&include=author,tags"

# Advanced filtering with multiple conditions
curl "http://localhost:8000/api/articles?filter[and][0][status][eq]=published&filter[and][1][viewCount][gte]=100"

# Create new article
curl -X POST \
     -H "Content-Type: application/vnd.api+json" \
     -d '{"data": {"type": "articles", "attributes": {"title": "Hello"}}}' \
     http://localhost:8000/api/articles

# Interactive API documentation
open http://localhost:8000/_jsonapi/docs
```

**📖 [Complete Getting Started Guide →](docs/guide/getting-started.md)**
**🚀 [Production-Ready Features →](docs/PRODUCTION_READY.md)**
**📊 [Interactive API Docs →](docs/guide/swagger-ui.md)**

---

## 📚 Documentation

### For New Users

- **[Production-Ready Features](docs/PRODUCTION_READY.md)** - ⭐ Complete guide to production features
- **[Getting Started Guide](docs/guide/getting-started.md)** - Build your first API in 5 minutes
- **[Swagger UI & OpenAPI](docs/guide/swagger-ui.md)** - Interactive API documentation
- **[Configuration Reference](docs/guide/configuration.md)** - Complete configuration options
- **[Doctrine Integration](docs/guide/integration-doctrine.md)** - Production-ready data layer
- **[Examples & Recipes](docs/guide/examples.md)** - Real-world code examples

### For Advanced Users

- **[Advanced Features](docs/guide/advanced-features.md)** - Profiles, hooks, events, caching
- **[Public API Reference](docs/api/public-api.md)** - Stable API documentation
- **[Troubleshooting Guide](docs/guide/troubleshooting.md)** - Common issues and solutions

### For Contributors

- **[Contributing Guide](CONTRIBUTING.md)** - How to contribute
- **[Architecture Review](docs/architecture/review.md)** - Design and extensibility
- **[BC Policy](docs/api/bc-policy.md)** - Backward compatibility guarantees

**📖 [Complete Documentation Index →](docs/guide/README.md)**

---

## ✨ Features

### Production-Ready Features ⭐

✅ **Complete Filtering System** - All operators (eq, ne, lt, lte, gt, gte, like, in, isnull, between) with SQL injection protection
✅ **Automatic Eager Loading** - Zero N+1 queries with automatic JOINs for includes
✅ **Generic Doctrine Repository** - Works out of the box, no custom code needed
✅ **Relationship Pagination** - Proper pagination for all relationship endpoints
✅ **PostgreSQL Optimized** - Tested and optimized for PostgreSQL

### Core Features

✅ **JSON:API 1.1 Compliance** - 97.8% specification coverage (132/135 requirements)
✅ **Attribute-Driven** - No XML/YAML configuration needed
✅ **Auto-Generated Endpoints** - No controller boilerplate
✅ **Query Parameters** - `include`, `fields`, `sort`, `page`, `filter`
✅ **Relationships** - To-one and to-many with full CRUD
✅ **Write Operations** - POST, PATCH, DELETE with validation
✅ **Atomic Operations** - Batch operations in single transaction
✅ **Interactive Docs** - Swagger UI / Redoc integration

### Read Operations

* `GET /api/{type}` - Collection with pagination, sorting, filtering
* `GET /api/{type}/{id}` - Single resource with sparse fieldsets
* `GET /api/{type}/{id}/relationships/{rel}` - Relationship linkage
* `GET /api/{type}/{id}/{rel}` - Related resources
* Query parsing: `include`, `fields[TYPE]`, `sort`, `page[number]`, `page[size]`
* Pagination with `self`, `first`, `prev`, `next`, `last` links
* Compound documents with `included` array
* Sparse fieldsets for performance optimization

### Write Operations

* `POST /api/{type}` → `201 Created` with Location header
* `PATCH /api/{type}/{id}` → `200 OK` with updated resource
* `DELETE /api/{type}/{id}` → `204 No Content`
* Transactional execution via `TransactionManager`
* Client-generated ID support (configurable per type)
* Strict input validation with detailed error responses
* Relationship modification endpoints (optional)

### Advanced Features

* **Profiles (RFC 6906)** - Extend JSON:API with custom semantics
* **Hooks System** - Intercept and modify request processing
* **Event System** - React to resource changes
* **HTTP Caching** - ETag, Last-Modified, surrogate keys
* **Custom Operators** - Extend filtering capabilities
* **Cache Invalidation** - CDN/reverse proxy support

**📖 [See all features →](docs/guide/advanced-features.md)**

---

## 🔒 Backward Compatibility

JsonApiBundle follows [Semantic Versioning](https://semver.org/):

- **MAJOR** versions may contain breaking changes
- **MINOR** versions add features in a backward-compatible manner
- **PATCH** versions contain bug fixes only

### Public API (Stable)

The following are guaranteed to maintain backward compatibility:

- ✅ **Contract Interfaces** (`src/Contract/`) - Data layer contracts
- ✅ **Resource Attributes** (`src/Resource/Attribute/`) - `#[JsonApiResource]`, `#[Attribute]`, etc.
- ✅ **Configuration Schema** - All `jsonapi:` configuration options

**📖 [Public API Reference →](docs/api/public-api.md)**
**📖 [BC Policy →](docs/api/bc-policy.md)**
**📖 [Upgrade Guide →](docs/api/upgrade-guide.md)**

### Pre-1.0 Notice

⚠️ Versions 0.x may introduce breaking changes in MINOR versions. Pin to exact MINOR version:

```json
{
    "require": {
        "jsonapi/symfony-jsonapi-bundle": "~0.1.0"
    }
}
```

---

## 📖 Interactive API Documentation

The bundle provides automatic OpenAPI 3.1 documentation with interactive UI:

### Access Documentation

**Swagger UI (Interactive):**
```
http://localhost:8000/_jsonapi/docs
```

**OpenAPI Specification (JSON):**
```
http://localhost:8000/_jsonapi/openapi.json
```

### Features

- 🎨 **Two themes**: Swagger UI (default) or Redoc
- 🔍 **Try it out**: Test endpoints directly from browser
- 📖 **Auto-generated**: Reflects all resources and relationships
- 🔒 **Environment-aware**: Disable in production

### Configuration

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    docs:
        generator:
            openapi:
                enabled: true
                title: 'My API'
                version: '1.0.0'
        ui:
            enabled: true
            route: '/_jsonapi/docs'
            theme: 'swagger'  # or 'redoc'
```

**Production:** Disable in `config/packages/prod/jsonapi.yaml`:

```yaml
jsonapi:
    docs:
        ui:
            enabled: false
```

**📖 [Swagger UI Documentation →](docs/features/SWAGGER_UI.md)**

---

## 📊 Example Response

```json
{
  "jsonapi": { "version": "1.1" },
  "links": {
    "self": "http://localhost/api/articles?page[number]=1&page[size]=10",
    "first": "http://localhost/api/articles?page[number]=1&page[size]=10",
    "last": "http://localhost/api/articles?page[number]=3&page[size]=10",
    "next": "http://localhost/api/articles?page[number]=2&page[size]=10"
  },
  "data": [
    {
      "type": "articles",
      "id": "1",
      "attributes": {
        "title": "Getting Started with JSON:API",
        "createdAt": "2025-10-07T10:00:00+00:00"
      },
      "relationships": {
        "author": {
          "links": {
            "self": "http://localhost/api/articles/1/relationships/author",
            "related": "http://localhost/api/articles/1/author"
          },
          "data": { "type": "authors", "id": "1" }
        }
      },
      "links": {
        "self": "http://localhost/api/articles/1"
      }
    }
  ],
  "included": [
    {
      "type": "authors",
      "id": "1",
      "attributes": { "name": "Alice" },
      "links": { "self": "http://localhost/api/authors/1" }
    }
  ],
  "meta": {
    "total": 25,
    "page": 1,
    "size": 10
  }
}
```
