# JsonApiBundle

[![CI](https://github.com/AlexFigures/jsonapi-symfony/workflows/CI/badge.svg)](https://github.com/AlexFigures/jsonapi-symfony/actions)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg)](https://phpstan.org/)
[![Spec Conformance](https://img.shields.io/badge/JSON:API-97.8%25-brightgreen.svg)](docs/conformance/spec-coverage.md)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue.svg)](https://www.php.net/)
[![Symfony Version](https://img.shields.io/badge/symfony-%5E7.1-blue.svg)](https://symfony.com/)
[![Packagist](https://img.shields.io/packagist/v/alexfigures/symfony-jsonapi-bundle.svg)](https://packagist.org/packages/alexfigures/symfony-jsonapi-bundle)
[![OpenSSF Scorecard](https://api.securityscorecards.dev/projects/github.com/AlexFigures/jsonapi-symfony/badge)](https://api.securityscorecards.dev/projects/github.com/AlexFigures/jsonapi-symfony)

**Production-ready JSON:API 1.1 implementation for Symfony with complete filtering, automatic eager loading, and zero N+1 queries.**


## 🚀 Quick Start

### Installation

```bash
composer require alexfigures/symfony-jsonapi-bundle
```

**Requirements:**
- PHP 8.3 or higher
- Symfony 7.1 or higher
- Doctrine ORM 3.0+ (optional, for database integration)

### Basic Setup

1. **Register the bundle** in `config/bundles.php`:

```php
return [
    AlexFigures\Symfony\Bridge\Symfony\Bundle\JsonApiBundle::class => ['all' => true],
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
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Relationship;

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
**📊 [Interactive API Docs →](docs/guide/swagger-ui.md)**

## ✅ Compatibility Matrix

| JsonApiBundle | PHP | Symfony | Doctrine ORM |
|---------------|-----|---------|--------------|
| `main` branch | 8.2 · 8.3 · 8.4 | 7.1 · 7.2 · 7.3 | 3.0+ |
| Latest release | 8.2+ | 7.1+ | 3.0+ |

> CI runs the full test suite across PHP 8.2–8.4 with both stable and lowest-dependency sets to guarantee forwards and backwards compatibility inside each supported Symfony minor.

**Tested Databases:**
- PostgreSQL 16+
- MySQL 8.0+
- MariaDB 11+
- SQLite 3.x

---

## 🎯 Key Features

### 🔍 **Advanced Filtering & Querying**
- **Complex Filters** - Support for `eq`, `ne`, `gt`, `gte`, `lt`, `lte`, `in`, `nin`, `like`, `ilike` operators
- **Logical Operators** - Combine filters with `and`, `or`, `not` for complex queries
- **Relationship Filtering** - Filter by nested relationship fields: `filter[author.name]=John`
- **Whitelist-based Security** - Only explicitly allowed fields can be filtered using `#[FilterableFields]`

```bash
# Complex filtering example
curl "api/articles?filter[and][0][status][eq]=published&filter[and][1][or][0][viewCount][gte]=100&filter[and][1][or][1][featured][eq]=true"
```

### 🔗 **Smart Relationship Handling**
- **Zero N+1 Queries** - Automatic eager loading with optimized JOINs
- **Deep Includes** - Include nested relationships: `include=author.company,tags`
- **Relationship Linking Policies** - Control how relationships are validated (`VERIFY`, `ALLOW_ORPHANS`)
- **Path Aliases** - Expose clean API paths that map to complex Doctrine relationships

```php
#[Relationship(
    toMany: true,
    targetType: 'tags',
    propertyPath: 'articleTags.tag',  // Clean API: specialTags → complex path
    linkingPolicy: RelationshipLinkingPolicy::VERIFY
)]
private Collection $specialTags;
```

### 📊 **Flexible Sorting & Pagination**
- **Multi-field Sorting** - Sort by multiple fields: `sort=title,-createdAt,author.name`
- **Relationship Sorting** - Sort by nested relationship fields
- **Cursor & Offset Pagination** - Both pagination strategies supported
- **Configurable Limits** - Set default and maximum page sizes

### 🛡️ **Production-Ready Security**
- **Strict Denormalization** - Reject unknown fields by default (`ALLOW_EXTRA_ATTRIBUTES=false`)
- **Field Whitelisting** - Explicit control over filterable/sortable fields
- **Validation Integration** - Full Symfony Validator integration
- **Error Collection** - Collect and return all validation errors at once

### 🚀 **Developer Experience**
- **Attribute-Based Configuration** - No YAML/XML, everything in PHP attributes
- **Auto-Generated OpenAPI** - Interactive Swagger UI documentation
- **Custom Route Support** - Define custom endpoints with automatic JSON:API formatting
- **Response Factory** - Fluent API for building JSON:API responses in custom controllers

```php
#[JsonApiResource(type: 'articles')]
#[FilterableFields(['title', new FilterableField('author', inherit: true)])]
#[SortableFields(['title', 'createdAt', new SortableField('author', inherit: true)])]
final class Article
{
    #[Id] #[Attribute] public string $id;
    #[Attribute] public string $title;
    #[Relationship(targetType: 'authors')] public Author $author;
}
```

### ⚡ **Performance Optimizations**
- **Automatic Query Optimization** - Smart JOINs and SELECT field limiting
- **Batch Operations** - Efficient bulk create/update/delete
- **Caching Support** - HTTP caching headers and response caching
- **Database Agnostic** - Works with PostgreSQL, MySQL, MariaDB, SQLite

### 🔧 **Extensibility**
- **Custom Handlers** - Build complex business logic with automatic transaction management
- **Event System** - Hook into request/response lifecycle
- **Custom Serialization** - Override default serialization behavior
- **Middleware Support** - Standard Symfony middleware integration

---

## 📚 Documentation

### For New Users

- **[Getting Started Guide](docs/guide/getting-started.md)** - Build your first API in 5 minutes
- **[Swagger UI & OpenAPI](docs/guide/swagger-ui.md)** - Interactive API documentation
- **[Configuration Reference](docs/guide/configuration.md)** - Complete configuration options
- **[Doctrine Integration](docs/guide/integration-doctrine.md)** - Production-ready data layer
- **[Examples & Recipes](docs/guide/examples.md)** - Real-world code examples
- **[Custom Routes](docs/guide/custom-routes.md)** - Define custom endpoints with attributes
- **[Response Factory](docs/guide/response-factory.md)** - Build JSON:API responses in custom controllers

### For Advanced Users

- **[Path Aliases](docs/guide/path-aliases.md)** - Expose clean API paths that map to complex Doctrine relationships
- **[Advanced Features](docs/guide/advanced-features.md)** - Profiles, hooks, events, caching
- **[Custom Handlers](docs/guide/custom-handlers.md)** - Handler-based custom routes with automatic transaction management
- **[Public API Reference](docs/api/public-api.md)** - Stable API documentation
- **[Troubleshooting Guide](docs/guide/troubleshooting.md)** - Common issues and solutions

### For Contributors

- **[Contributing Guide](CONTRIBUTING.md)** - How to contribute
- **[Testing Guide](TESTING.md)** - Running tests (unit, functional, integration with Docker)
- **[Architecture Review](docs/architecture/review.md)** - Design and extensibility
- **[BC Policy](docs/api/bc-policy.md)** - Backward compatibility guarantees

**📖 [Complete Documentation Index →](docs/guide/README.md)**

---

## 🆕 Recent Updates

### New Features

- **🆕 Path Aliases** - Expose clean API paths that map to complex Doctrine relationships using `propertyPath` parameter
  ```php
  #[Relationship(
      toMany: true,
      targetType: 'tags',
      propertyPath: 'articleTags.tag'  // API: specialTags → Doctrine: articleTags.tag
  )]
  private Collection $specialTags;
  ```
- **Custom Route Handlers** - Build custom endpoints with automatic transaction management and JSON:API response formatting ([docs](docs/guide/custom-handlers.md))
- **Response Factory** - Fluent API for building JSON:API responses in custom controllers ([docs](docs/guide/response-factory.md))
- **Criteria Builder** - Add custom filters and conditions to JSON:API queries in custom route handlers ([docs](docs/guide/custom-routes.md#advanced-filtering-sorting-and-pagination-in-custom-routes))
- **Custom Route Attributes** - Define custom endpoints using `#[JsonApiCustomRoute]` attribute ([docs](docs/guide/custom-routes.md))
- **Media Type Configuration** - Configure different media types for different endpoints (e.g., docs, sandbox)
- **Docker-based Integration Tests** - Run integration tests against real databases using Docker ([docs](TESTING.md))

### Testing Improvements

- **Docker Test Environment** - Integration tests now run in Docker with PostgreSQL, MySQL, and MariaDB
- **Conformance Tests** - Snapshot-based tests ensure JSON:API specification compliance
- **Mutation Testing** - Infection configured with 70% MSI threshold
- **Quality Gates** - PHPStan level 8, Deptrac architecture rules, BC checks

Run tests with:
```bash
make test              # Unit and functional tests
make docker-test       # Integration tests in Docker
make qa-full          # Full QA suite (tests, static analysis, mutation testing)
```

See [TESTING.md](TESTING.md) for complete testing documentation.

---

## ✨ Features

### Production-Ready Features ⭐

✅ **Complete Filtering System** - All operators (eq, ne, lt, lte, gt, gte, like, in, isnull, between) with SQL injection protection
✅ **Automatic Eager Loading** - Zero N+1 queries with automatic JOINs for includes
✅ **Generic Doctrine Repository** - Works out of the box, no custom code needed
✅ **Relationship Pagination** - Proper pagination for all relationship endpoints
✅ **PostgreSQL Optimized** - Tested and optimized for PostgreSQL
✅ **Custom Route Handlers** - Build custom endpoints with automatic transaction management and JSON:API formatting

### Core Features

✅ **JSON:API 1.1 Compliance** - 97.8% specification coverage (132/135 requirements)
✅ **Attribute-Driven** - No XML/YAML configuration needed
✅ **Auto-Generated Endpoints** - No controller boilerplate
✅ **Configurable Route Naming** - Choose between snake_case and kebab-case
✅ **Custom Route Attributes** - Define custom endpoints with PHP attributes
✅ **Query Parameters** - `include`, `fields`, `sort`, `page`, `filter`
✅ **Relationships** - To-one and to-many with full CRUD
✅ **Write Operations** - POST, PATCH, DELETE with validation
✅ **Atomic Operations** - Batch operations in single transaction
✅ **Interactive Docs** - Swagger UI / Redoc integration
✅ **Response Factory** - Build JSON:API responses in custom controllers

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
* **Media Type Configuration** - Configure different media types for different endpoints
* **Criteria Builder** - Add custom filters and conditions to JSON:API queries in custom handlers

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

**📖 [Swagger UI Documentation →](docs/guide/swagger-ui.md)**

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

---

## 🛠️ Development & Testing

### Quick Commands

```bash
# Install dependencies
composer install
# or
make install

# Run tests
make test              # Unit and functional tests (no Docker required)
make docker-test       # Integration tests with real databases
make test-all          # All test suites

# Code quality
make stan              # PHPStan static analysis (level 8)
make cs-fix            # Fix code style (PSR-12)
make rector            # Automated refactoring
make mutation          # Mutation testing (70% MSI threshold)
make deptrac           # Architecture rules validation
make bc-check          # Backward compatibility check

# Full QA pipeline
make qa-full           # Run all quality checks
```

See [TESTING.md](TESTING.md) for detailed testing documentation.

---

## 🤝 Community & Governance

- 📮 **Need help?** Read our [Support guide](SUPPORT.md) for documentation links, discussion forums, and escalation paths.
- 📋 **Contributions welcome!** See the [CONTRIBUTING.md](CONTRIBUTING.md) for coding standards and workflow.
- ❤️ **Be excellent to each other.** Participation is governed by the [Code of Conduct](CODE_OF_CONDUCT.md).
- 🛡 **Report vulnerabilities privately.** Follow the steps in [SECURITY.md](SECURITY.md).
- 🧭 **Stay up to date.** Watch [Discussions](https://github.com/AlexFigures/jsonapi-symfony/discussions) and subscribe to release drafts for roadmap updates.
