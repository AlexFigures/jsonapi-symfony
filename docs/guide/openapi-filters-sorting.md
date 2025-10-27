# Filter and Sort Parameters in OpenAPI Documentation

The JSON:API bundle automatically generates OpenAPI documentation for filter and sort parameters based on your resource configuration.

## Overview

When you define `#[FilterableFields]` and `#[SortableFields]` attributes on your resources, the OpenAPI specification generator automatically creates query parameter documentation for:

- **Filtering**: All allowed fields and operators
- **Sorting**: All sortable fields with examples
- **Pagination**: Standard `page[number]` and `page[size]` parameters
- **Sparse Fieldsets**: Field selection via `fields[type]` parameter
- **Includes**: Relationship inclusion via `include` parameter

## Example Resource Configuration

```php
<?php

namespace App\Entity;

use AlexFigures\Symfony\Resource\Attribute\FilterableField;
use AlexFigures\Symfony\Resource\Attribute\FilterableFields;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\SortableFields;

#[JsonApiResource(type: 'articles')]
#[FilterableFields([
    new FilterableField('title', operators: ['eq', 'like']),
    new FilterableField('status', operators: ['eq', 'in']),
    new FilterableField('createdAt', operators: ['gt', 'gte', 'lt', 'lte']),
    new FilterableField('viewCount', operators: ['gt', 'gte', 'lt', 'lte', 'eq']),
])]
#[SortableFields(['title', 'createdAt', 'viewCount'])]
class Article
{
    // ... entity properties
}
```

## Generated OpenAPI Parameters

For the above configuration, the following query parameters will be automatically documented in OpenAPI:

### Pagination Parameters

```yaml
- name: page[number]
  in: query
  description: Page number for pagination
  required: false
  schema:
    type: integer
    minimum: 1
    default: 1

- name: page[size]
  in: query
  description: Number of items per page
  required: false
  schema:
    type: integer
    minimum: 1
    maximum: 100
    default: 20
```

### Filter Parameters

Each filterable field generates parameters for all its allowed operators:

```yaml
- name: filter[title][eq]
  in: query
  description: Filter by title (equals)
  required: false
  schema:
    type: string

- name: filter[title][like]
  in: query
  description: Filter by title (pattern match, use * as wildcard)
  required: false
  schema:
    type: string
    example: "*search*"

- name: filter[status][eq]
  in: query
  description: Filter by status (equals)
  required: false
  schema:
    type: string

- name: filter[status][in]
  in: query
  description: Filter by status (value in list, comma-separated)
  required: false
  schema:
    type: string
    example: "published,draft,archived"

- name: filter[createdAt][gt]
  in: query
  description: Filter by createdAt (greater than)
  required: false
  schema:
    type: string

- name: filter[createdAt][gte]
  in: query
  description: Filter by createdAt (greater than or equal)
  required: false
  schema:
    type: string

# ... and so on for all operators
```

### Sort Parameter

```yaml
- name: sort
  in: query
  description: "Sort order. Prefix with `-` for descending. Allowed fields: title, createdAt, viewCount"
  required: false
  schema:
    type: string
  example: "-createdAt"
```

### Sparse Fieldsets Parameter

```yaml
- name: fields[articles]
  in: query
  description: Comma-separated list of fields to include in the response
  required: false
  schema:
    type: string
```

### Include Parameter (Relationships)

If your resource has relationships, an `include` parameter is automatically generated:

```yaml
- name: include
  in: query
  description: "Comma-separated list of relationships to include. Available: author, comments, tags"
  required: false
  schema:
    type: string
  example: "author"
```

## Custom Filter Handlers

When a field uses a custom filter handler, it's indicated in the parameter description:

```php
#[FilterableFields([
    new FilterableField('content', customHandler: 'app.filter.fulltext_search'),
])]
```

Generates:

```yaml
- name: filter[content][eq]
  in: query
  description: Filter by content (equals) [custom handler]
  required: false
  schema:
    type: string
```

## Operator Descriptions

The following operators are supported and documented:

| Operator | Description | Example Value |
|----------|-------------|---------------|
| `eq` | Equals | `published` |
| `ne` | Not equals | `draft` |
| `gt` | Greater than | `2024-01-01` |
| `gte` | Greater than or equal | `100` |
| `lt` | Less than | `2024-12-31` |
| `lte` | Less than or equal | `1000` |
| `like` | Pattern match (case-sensitive) | `*search*` |
| `ilike` | Pattern match (case-insensitive) | `*search*` |
| `in` | Value in list (comma-separated) | `value1,value2,value3` |
| `nin` | Value not in list (comma-separated) | `value1,value2` |
| `null` | Is null (use 1 for true, 0 for false) | `1` |
| `nnull` | Is not null (use 1 for true, 0 for false) | `1` |

## Filter Inheritance

When using filter inheritance from relationships, the inherited filters are also documented:

```php
// Author resource
#[FilterableFields([
    new FilterableField('name', operators: ['eq', 'like']),
    new FilterableField('email', operators: ['eq']),
])]
class Author { }

// Article resource
#[FilterableFields([
    'title',
    new FilterableField('author', inherit: true),
])]
class Article { }
```

This will generate parameters for:
- `filter[title][eq]`, `filter[title][ne]`, etc. (all default operators)
- `filter[author.name][eq]`, `filter[author.name][like]`
- `filter[author.email][eq]`

## Viewing the Documentation

After configuring your resources, the filter and sort parameters will appear in:

- **Swagger UI**: `/api/docs` - Interactive documentation with "Try it out" functionality
- **OpenAPI JSON**: `/api/docs.json` - Machine-readable specification

## Best Practices

1. **Be Explicit**: Define only the operators you actually support for each field
2. **Security**: Use whitelists to prevent filtering on sensitive or unindexed fields
3. **Performance**: Only allow filtering on indexed database columns
4. **Documentation**: The generated OpenAPI docs help API consumers understand available filters
5. **Validation**: The bundle automatically validates filter parameters against your configuration

## Example API Request

Based on the configuration above, here's a valid API request:

```http
GET /api/articles?filter[status][eq]=published&filter[createdAt][gte]=2024-01-01&sort=-createdAt&page[size]=10&include=author
```

This request:
- Filters articles with status "published"
- Created on or after 2024-01-01
- Sorted by creation date (newest first)
- Returns 10 items per page
- Includes the author relationship

All these parameters are automatically documented in your OpenAPI specification!

