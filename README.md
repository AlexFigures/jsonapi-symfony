# JsonApiBundle

[![CI](https://github.com/AlexFigures/jsonapi-symfony/workflows/CI/badge.svg)](https://github.com/AlexFigures/jsonapi-symfony/actions)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg)](https://phpstan.org/)
[![Mutation Score](https://img.shields.io/badge/MSI-38.74%25-red.svg)](docs/reliability/mutation-testing-report.md)
[![Coverage](https://img.shields.io/badge/coverage-69.31%25-yellow.svg)](docs/reliability/mutation-testing-report.md)
[![Spec Conformance](https://img.shields.io/badge/JSON:API-65%25-yellow.svg)](docs/conformance/spec-coverage.md)
[![Quality](https://img.shields.io/badge/quality-61%25-yellow.svg)](docs/QA_AUDIT_REPORT.md)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue.svg)](https://www.php.net/)
[![Symfony Version](https://img.shields.io/badge/symfony-%5E7.1-blue.svg)](https://symfony.com/)


## Installation

```bash
composer require jsonapi/symfony-jsonapi-bundle
```

For local development against this repository:

```bash
git clone https://github.com/your-org/jsonapi-symfony.git
cd jsonapi-symfony
composer install
```

## Usage

Register the bundle in your Symfony application's `config/bundles.php`:

```php
return [
    JsonApi\Symfony\Bridge\Symfony\Bundle\JsonApiBundle::class => ['all' => true],
];
```

Configure the bundle (defaults shown):

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    strict_content_negotiation: true
    media_type: 'application/vnd.api+json'
    route_prefix: '/api'
    pagination:
        default_size: 25
        max_size: 100
    sorting:
        whitelist:
            articles: ['title', 'createdAt']
            authors: ['name']
    write:
        allow_relationship_writes: false
        client_generated_ids:
            articles: false
            authors: true
    docs:
        generator:
            openapi:
                enabled: true
                title: 'My API'
                version: '1.0.0'
                servers:
                    - 'https://api.example.com'
        ui:
            enabled: true
            route: '/_jsonapi/docs'
            spec_url: '/_jsonapi/openapi.json'
            theme: 'swagger'  # or 'redoc'
```

Declare your first resource:

```php
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\Relationship;

#[JsonApiResource(type: 'articles')]
final class Article
{
    #[Id]
    public string $id;

    #[Attribute]
    public string $title;

    #[Relationship(toMany: true)]
    public array $comments = [];
}
```

Stage 2 ships fully functional read endpoints and foundational writes:

* Attribute-driven metadata registry with automatic discovery of attributes and relationships.
* `GET /api/{type}` and `GET /api/{type}/{id}` controllers with JSON:API 1.1 compliant documents.
* Relationship endpoints for `GET /api/{type}/{id}/relationships/{relationship}` and
  `GET /api/{type}/{id}/{relationship}` with linkage validation, configurable responses, and
  metadata support.
* Query parsing for `include`, `fields[TYPE]`, `sort`, `page[number]`, and `page[size]` with robust validation.
* Pagination helpers generating `self`, `first`, `prev`, `next`, and `last` links that retain other query parameters.
* Document builder producing `data`, `included`, `links`, `meta`, and `jsonapi.version` for any combination of sparse fieldsets and includes.
* In-memory repository and sample fixtures (Article, Author, Tag) for functional testing.
* `POST /api/{type}`, `PATCH /api/{type}/{id}`, and `DELETE /api/{type}/{id}` controllers with ChangeSet-based write ports, transactional execution, and client-generated ID support.

## Writes

Stage 2 adds denormalisation of JSON:API resource documents into a `ChangeSet` consumed by the `ResourcePersister` port. Controllers wrap each write in a `TransactionManager::transactional()` call to guarantee atomicity and return the appropriate JSON:API responses:

* `POST /api/{type}` ⇒ `201 Created` with the newly created resource document and a `Location` header pointing at its `self` link.
* `PATCH /api/{type}/{id}` ⇒ `200 OK` with the updated resource document.
* `DELETE /api/{type}/{id}` ⇒ `204 No Content`.

Input documents are validated strictly:

* `data.type` and `data.id` must match the endpoint.
* Only attributes declared with `#[Attribute(writable: true)]` are accepted; attempts to write read-only or unknown attributes result in `400 Bad Request`.
* Relationship writes are rejected by default (`allow_relationship_writes: false`).
* Client-generated IDs are controlled per type through `write.client_generated_ids`. When disabled the bundle throws `403 Forbidden`; when enabled conflicts surface as `409 Conflict`.

An in-memory `ResourcePersister` backs the functional test suite, using UUIDv4 server-generated identifiers when the client does not supply an ID.

## Public API & Backward Compatibility

JsonApiBundle follows [Semantic Versioning](https://semver.org/). The public API consists of:

- **Contract Interfaces** (`src/Contract/`) - Stable, BC guaranteed
- **Resource Attributes** (`src/Resource/Attribute/`) - Stable, BC guaranteed
- **Configuration Schema** - Stable, BC guaranteed

See [Public API Reference](docs/api/public-api.md) for complete documentation.

### Backward Compatibility Policy

- **MAJOR** versions may contain breaking changes
- **MINOR** versions add new features in a backward compatible manner
- **PATCH** versions contain bug fixes only

See [BC Policy](docs/api/bc-policy.md) for detailed information.

### Upgrading

When upgrading between versions, consult the [Upgrade Guide](docs/api/upgrade-guide.md) for migration instructions.

**Pre-1.0 Notice**: Versions 0.x may introduce breaking changes in MINOR versions. Pin to exact MINOR version in `composer.json`:

```json
{
    "require": {
        "jsonapi/symfony-jsonapi-bundle": "~0.1.0"
    }
}
```

---

## API Documentation

The bundle provides automatic OpenAPI 3.1 documentation generation and interactive UI:

### OpenAPI Specification
Access the machine-readable OpenAPI spec at:
```
GET /_jsonapi/openapi.json
```

### Interactive Documentation UI
Access the interactive Swagger UI or Redoc at:
```
GET /_jsonapi/docs
```

**Features**:
- 🎨 **Two themes**: Swagger UI (default) or Redoc
- 🔍 **Try it out**: Test API endpoints directly from the browser
- 📖 **Auto-generated**: Reflects all registered resources and their attributes/relationships
- 🔒 **Configurable**: Enable/disable per environment

**Configuration**:
```yaml
jsonapi:
    docs:
        ui:
            enabled: true              # Enable/disable UI
            route: '/_jsonapi/docs'    # UI route
            spec_url: '/_jsonapi/openapi.json'  # OpenAPI spec URL
            theme: 'swagger'           # 'swagger' or 'redoc'
```

**Production tip**: Disable in production by setting `enabled: false` in `config/packages/prod/jsonapi.yaml`.

Example response:

```json
{
  "jsonapi": { "version": "1.1" },
  "links": {
    "self": "https://api.example.test/api/articles?page[number]=2&page[size]=5",
    "first": "https://api.example.test/api/articles?page[number]=1&page[size]=5",
    "prev": "https://api.example.test/api/articles?page[number]=1&page[size]=5",
    "next": "https://api.example.test/api/articles?page[number]=3&page[size]=5",
    "last": "https://api.example.test/api/articles?page[number]=3&page[size]=5"
  },
  "data": [
    {
      "type": "articles",
      "id": "42",
      "attributes": {
        "title": "Hello JSON:API",
        "createdAt": "2024-06-01T12:00:00+00:00"
      },
      "links": {
        "self": "https://api.example.test/api/articles/42"
      }
    }
  ],
  "included": [
    {
      "type": "authors",
      "id": "7",
      "attributes": { "name": "Alice" },
      "links": {
        "self": "https://api.example.test/api/authors/7"
      }
    }
  ],
  "meta": {
    "total": 123,
    "page": 2,
    "size": 5
  }
}
```
