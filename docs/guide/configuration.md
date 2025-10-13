# Configuration Reference

**Version**: 0.1.0  
**Last Updated**: 2025-10-07

---

## Table of Contents

1. [Overview](#overview)
2. [Full Configuration Example](#full-configuration-example)
3. [Configuration Options](#configuration-options)
4. [Environment-Specific Configuration](#environment-specific-configuration)
5. [Advanced Configuration](#advanced-configuration)

---

## Overview

JsonApiBundle is configured via the `jsonapi` key in your Symfony configuration files. All configuration options have sensible defaults, so you only need to specify what you want to change.

**Default configuration file location:**
```
config/packages/jsonapi.yaml
```

---

## Full Configuration Example

Here's a complete configuration example with all available options:

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    # Content negotiation
    strict_content_negotiation: true
    media_types:
        default:
            request:
                allowed:
                    - 'application/vnd.api+json'
            response:
                default: 'application/vnd.api+json'
        channels:
            docs:
                scope:
                    path_prefix: '^/_jsonapi/docs'
                request:
                    allowed: ['*']
                response:
                    default: 'text/html'
                    negotiable:
                        - 'text/html'
            sandbox:
                scope:
                    path_prefix: '^/sandbox'
                request:
                    allowed:
                        - 'application/json'
                        - 'multipart/form-data'
                response:
                    default: 'application/json'
                    negotiable:
                        - 'application/json'
                        - 'text/html'
    
    # Routing
    route_prefix: '/api'
    
    # Pagination
    pagination:
        default_size: 25
        max_size: 100
    
    # Sorting
    sorting:
        whitelist:
            articles: ['title', 'createdAt', 'updatedAt']
            authors: ['name', 'email']
            tags: ['name']
    
    # Write operations
    write:
        allow_relationship_writes: false
        client_generated_ids:
            articles: false
            authors: true
            tags: false
    
    # Filtering
    filtering:
        enabled: true
        max_depth: 3
        operators:
            - 'eq'
            - 'neq'
            - 'like'
            - 'gt'
            - 'gte'
            - 'lt'
            - 'lte'
    
    # Profiles (RFC 6906)
    profiles:
        enabled_by_default: []
        per_type:
            articles: ['urn:jsonapi:profile:soft-delete']
        negotiation:
            strict: false
    
    # Caching
    cache:
        enabled: true
        etag:
            enabled: true
            weak_for_collections: true
        last_modified:
            enabled: true
        surrogate_keys:
            enabled: true
            prefix: 'jsonapi'
    
    # Complexity limits (DoS protection)
    limits:
        max_include_depth: 3
        max_fields_per_type: 50
        max_sort_fields: 5
        max_filter_depth: 3
    
    # Documentation
    docs:
        generator:
            openapi:
                enabled: true
                title: 'My API'
                version: '1.0.0'
                description: 'JSON:API compliant REST API'
                servers:
                    - 'https://api.example.com'
                    - 'https://staging-api.example.com'
        ui:
            enabled: true
            route: '/_jsonapi/docs'
            spec_url: '/_jsonapi/openapi.json'
            theme: 'swagger'  # 'swagger' or 'redoc'
    
    # Debug mode
    debug:
        expose_debug_meta: false  # Never enable in production!
```

---

## Configuration Options

### Content Negotiation

#### `strict_content_negotiation`

**Type:** `boolean`  
**Default:** `true`

When enabled, enforces strict JSON:API media type negotiation:
- Requires `Content-Type: application/vnd.api+json` for write requests
- Requires `Accept: application/vnd.api+json` for all requests
- Rejects requests with media type parameters (except `ext` and `profile`)

```yaml
jsonapi:
    strict_content_negotiation: true
```

**Recommendation:** Keep enabled for spec compliance.

---

#### `media_types`

**Type:** `map`
**Default:** JSON:API only (see below)

Controls how incoming and outgoing media types are negotiated. The configuration is split into two parts:

- `default` — policy applied when no channel matches the request.
- `channels` — named overrides matched by `path_prefix`, `route_name`, or controller attributes.

Each policy contains separate rules for request and response headers:

```yaml
jsonapi:
    media_types:
        default:
            request:
                allowed:
                    - 'application/vnd.api+json'
            response:
                default: 'application/vnd.api+json'
                negotiable: []  # defaults to the same value as "default"
        channels:
            docs:
                scope:
                    path_prefix: '^/_jsonapi/docs'
                request:
                    allowed: ['*']  # allow any Content-Type (read-only)
                response:
                    default: 'text/html'
                    negotiable:
                        - 'text/html'
            sandbox:
                scope:
                    path_prefix: '^/sandbox'
                request:
                    allowed:
                        - 'application/json'
                        - 'multipart/form-data'
                response:
                    default: 'application/json'
                    negotiable:
                        - 'application/json'
                        - 'text/html'
```

- `request.allowed` — list of normalized media types for the `Content-Type` header. Use `'*'` to disable validation.
- `response.default` — media type automatically applied to outgoing responses when none is set.
- `response.negotiable` — optional list of values accepted from the `Accept` header. When omitted, only `response.default` is allowed.
- `scope` — optional matchers for routing; values are treated as regular expressions.

> **Legacy option:** the scalar `media_type` key is still accepted and automatically populates `media_types.default`. It is deprecated and will be removed in a future release.

---

### Routing

#### `route_prefix`

**Type:** `string`  
**Default:** `'/api'`

The URL prefix for all JSON:API endpoints.

```yaml
jsonapi:
    route_prefix: '/api/v1'
```

**Generated routes:**
- `GET /api/v1/articles` - Collection
- `GET /api/v1/articles/{id}` - Single resource
- `POST /api/v1/articles` - Create
- `PATCH /api/v1/articles/{id}` - Update
- `DELETE /api/v1/articles/{id}` - Delete

---

### Pagination

#### `pagination.default_size`

**Type:** `integer`  
**Default:** `25`

Default number of items per page when `page[size]` is not specified.

```yaml
jsonapi:
    pagination:
        default_size: 25
```

---

#### `pagination.max_size`

**Type:** `integer`  
**Default:** `100`

Maximum allowed page size. Prevents DoS attacks via large page sizes.

```yaml
jsonapi:
    pagination:
        max_size: 100
```

**Example request:**
```
GET /api/articles?page[size]=50&page[number]=2
```

---

### Sorting

#### `sorting.whitelist`

**Type:** `array<string, list<string>>`  
**Default:** `[]`

Whitelist of sortable fields per resource type. Only whitelisted fields can be used in `sort` parameter.

```yaml
jsonapi:
    sorting:
        whitelist:
            articles: ['title', 'createdAt', 'updatedAt']
            authors: ['name', 'email']
```

**Example request:**
```
GET /api/articles?sort=-createdAt,title
```

**Security note:** Always whitelist sort fields to prevent SQL injection and performance issues.

---

### Write Operations

#### `write.allow_relationship_writes`

**Type:** `boolean`
**Default:** `false`

Enable relationship writes in resource creation/update requests and dedicated relationship modification endpoints:

**When enabled:**
- Relationships can be included in `POST /api/articles` and `PATCH /api/articles/1` requests
- Dedicated relationship endpoints are available:
  - `PATCH /api/articles/1/relationships/tags` - Replace relationship
  - `POST /api/articles/1/relationships/tags` - Add to relationship
  - `DELETE /api/articles/1/relationships/tags` - Remove from relationship

```yaml
jsonapi:
    write:
        allow_relationship_writes: true
```

**Example with relationships in resource creation:**
```json
POST /api/articles
{
  "data": {
    "type": "articles",
    "attributes": {
      "title": "My Article"
    },
    "relationships": {
      "author": {
        "data": { "type": "authors", "id": "123" }
      },
      "tags": {
        "data": [
          { "type": "tags", "id": "1" },
          { "type": "tags", "id": "2" }
        ]
      }
    }
  }
}
```

---

#### `write.client_generated_ids`

**Type:** `array<string, boolean>`  
**Default:** `[]`

Configure which resource types allow client-generated IDs in POST requests.

```yaml
jsonapi:
    write:
        client_generated_ids:
            articles: false  # Server generates IDs
            authors: true    # Client can provide IDs
```

**When enabled:**
```json
POST /api/authors
{
  "data": {
    "type": "authors",
    "id": "custom-uuid-here",
    "attributes": { "name": "Alice" }
  }
}
```

**When disabled:** Returns `403 Forbidden` if client provides ID.

---

### Relationships

#### `relationships.linkage_in_resource`

**Type:** `enum('never', 'when_included', 'always')`
**Default:** `always`

Controls when to include relationship linkage data (`data` field with resource identifiers) in resource responses.

**Values:**
- `never` - Never include `data` in relationships (only `links`)
- `when_included` - Include `data` only when the relationship is in the `?include` query parameter
- `always` - Always include `data` (recommended for JSON:API spec compliance)

```yaml
jsonapi:
    relationships:
        linkage_in_resource: always
```

**Example response with `always`:**
```json
{
  "data": {
    "type": "articles",
    "id": "1",
    "relationships": {
      "author": {
        "links": {
          "self": "/api/articles/1/relationships/author",
          "related": "/api/articles/1/author"
        },
        "data": { "type": "authors", "id": "123" }
      },
      "tags": {
        "links": {
          "self": "/api/articles/1/relationships/tags",
          "related": "/api/articles/1/tags"
        },
        "data": [
          { "type": "tags", "id": "1" },
          { "type": "tags", "id": "2" }
        ]
      }
    }
  }
}
```

**Example response with `never`:**
```json
{
  "data": {
    "type": "articles",
    "id": "1",
    "relationships": {
      "author": {
        "links": {
          "self": "/api/articles/1/relationships/author",
          "related": "/api/articles/1/author"
        }
      }
    }
  }
}
```

**Performance note:** Using `when_included` or `never` can reduce response size for resources with many relationships, but may require additional requests to fetch relationship data.

---

#### `relationships.write_response`

**Type:** `enum('linkage', '204')`
**Default:** `linkage`

Controls the response format for relationship write operations (`PATCH/POST/DELETE /api/{type}/{id}/relationships/{rel}`).

**Values:**
- `linkage` - Return `200 OK` with relationship linkage data
- `204` - Return `204 No Content` (no response body)

```yaml
jsonapi:
    relationships:
        write_response: linkage
```

---

### Filtering

#### `filtering.enabled`

**Type:** `boolean`  
**Default:** `true`

Enable filter query parameter support.

```yaml
jsonapi:
    filtering:
        enabled: true
```

---

#### `filtering.max_depth`

**Type:** `integer`  
**Default:** `3`

Maximum nesting depth for filter expressions (DoS protection).

```yaml
jsonapi:
    filtering:
        max_depth: 3
```

---

#### `filtering.operators`

**Type:** `list<string>`  
**Default:** `['eq', 'neq', 'like', 'gt', 'gte', 'lt', 'lte']`

Available filter operators.

```yaml
jsonapi:
    filtering:
        operators:
            - 'eq'    # Equal
            - 'neq'   # Not equal
            - 'like'  # SQL LIKE
            - 'gt'    # Greater than
            - 'gte'   # Greater than or equal
            - 'lt'    # Less than
            - 'lte'   # Less than or equal
```

---

### Profiles

#### `profiles.enabled_by_default`

**Type:** `list<string>`  
**Default:** `[]`

Profiles enabled for all resource types by default.

```yaml
jsonapi:
    profiles:
        enabled_by_default:
            - 'urn:jsonapi:profile:soft-delete'
```

---

#### `profiles.per_type`

**Type:** `array<string, list<string>>`  
**Default:** `[]`

Profiles enabled for specific resource types.

```yaml
jsonapi:
    profiles:
        per_type:
            articles: ['urn:jsonapi:profile:soft-delete']
            authors: ['urn:example:audit-trail']
```

---

#### `profiles.negotiation.strict`

**Type:** `boolean`  
**Default:** `false`

When enabled, rejects requests with unsupported profiles.

```yaml
jsonapi:
    profiles:
        negotiation:
            strict: true
```

---

### Caching

#### `cache.enabled`

**Type:** `boolean`  
**Default:** `true`

Enable HTTP caching support (ETags, Last-Modified, surrogate keys).

```yaml
jsonapi:
    cache:
        enabled: true
```

---

#### `cache.etag.enabled`

**Type:** `boolean`  
**Default:** `true`

Generate ETag headers for responses.

```yaml
jsonapi:
    cache:
        etag:
            enabled: true
            weak_for_collections: true  # Use weak ETags for collections
```

---

#### `cache.last_modified.enabled`

**Type:** `boolean`  
**Default:** `true`

Generate Last-Modified headers for responses.

```yaml
jsonapi:
    cache:
        last_modified:
            enabled: true
```

---

#### `cache.surrogate_keys.enabled`

**Type:** `boolean`  
**Default:** `true`

Generate surrogate keys for cache invalidation (Varnish, Fastly, etc.).

```yaml
jsonapi:
    cache:
        surrogate_keys:
            enabled: true
            prefix: 'jsonapi'  # Prefix for surrogate keys
```

---

### Complexity Limits

#### `limits.max_include_depth`

**Type:** `integer`  
**Default:** `3`

Maximum depth for `include` parameter (DoS protection).

```yaml
jsonapi:
    limits:
        max_include_depth: 3
```

**Example:** `include=author.articles.comments` (depth = 3)

---

#### `limits.max_fields_per_type`

**Type:** `integer`  
**Default:** `50`

Maximum number of fields in `fields[type]` parameter.

```yaml
jsonapi:
    limits:
        max_fields_per_type: 50
```

---

#### `limits.max_sort_fields`

**Type:** `integer`  
**Default:** `5`

Maximum number of fields in `sort` parameter.

```yaml
jsonapi:
    limits:
        max_sort_fields: 5
```

---

#### `limits.max_filter_depth`

**Type:** `integer`  
**Default:** `3`

Maximum nesting depth for filter expressions.

```yaml
jsonapi:
    limits:
        max_filter_depth: 3
```

---

### Documentation

#### `docs.generator.openapi.enabled`

**Type:** `boolean`  
**Default:** `true`

Enable OpenAPI 3.1 specification generation.

```yaml
jsonapi:
    docs:
        generator:
            openapi:
                enabled: true
                title: 'My API'
                version: '1.0.0'
                description: 'API description'
                servers:
                    - 'https://api.example.com'
```

---

#### `docs.ui.enabled`

**Type:** `boolean`  
**Default:** `true`

Enable interactive documentation UI (Swagger UI / Redoc).

```yaml
jsonapi:
    docs:
        ui:
            enabled: true
            route: '/_jsonapi/docs'
            spec_url: '/_jsonapi/openapi.json'
            theme: 'swagger'  # 'swagger' or 'redoc'
```

---

### Debug

#### `debug.expose_debug_meta`

**Type:** `boolean`  
**Default:** `false`

**⚠️ NEVER ENABLE IN PRODUCTION!**

Exposes debug information in response `meta`:
- Query execution time
- Memory usage
- SQL queries (if applicable)

```yaml
jsonapi:
    debug:
        expose_debug_meta: '%kernel.debug%'  # Only in dev
```

---

## Environment-Specific Configuration

### Development

```yaml
# config/packages/dev/jsonapi.yaml
jsonapi:
    debug:
        expose_debug_meta: true
    docs:
        ui:
            enabled: true
            theme: 'swagger'
```

### Production

```yaml
# config/packages/prod/jsonapi.yaml
jsonapi:
    debug:
        expose_debug_meta: false  # CRITICAL!
    docs:
        generator:
            openapi:
                enabled: false
        ui:
            enabled: false
    cache:
        enabled: true
```

### Testing

```yaml
# config/packages/test/jsonapi.yaml
jsonapi:
    cache:
        enabled: false  # Disable caching in tests
    docs:
        ui:
            enabled: false
```

---

## Advanced Configuration

### Custom Service Registration

Register custom implementations:

```yaml
# config/services.yaml
services:
    # Custom repository
    App\JsonApi\ArticleRepository:
        tags:
            - { name: 'jsonapi.repository', type: 'articles' }
    
    # Custom persister
    App\JsonApi\ArticlePersister:
        tags:
            - { name: 'jsonapi.persister', type: 'articles' }
    
    # Custom profile
    App\JsonApi\Profile\AuditTrailProfile:
        tags:
            - { name: 'jsonapi.profile' }
    
    # Custom filter operator
    App\JsonApi\Filter\CustomOperator:
        tags:
            - { name: 'jsonapi.filter.operator' }
```

---

## See Also

- [Getting Started Guide](getting-started.md)
- [Doctrine Integration](integration-doctrine.md)
- [Advanced Features](advanced-features.md)
- [Public API Reference](../api/public-api.md)

---

**Last Updated**: 2025-10-07

