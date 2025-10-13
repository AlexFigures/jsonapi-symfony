# Sorting Configuration

This guide explains how to configure which fields are allowed for sorting in JSON:API requests.

## Overview

The JSON:API specification allows clients to request sorted results using the `sort` query parameter:

```
GET /api/articles?sort=-createdAt,title
```

For security and performance reasons, you should explicitly whitelist which fields can be used for sorting. This prevents:

- **Information disclosure** through timing attacks
- **Performance issues** from sorting on unindexed columns
- **Exposure of internal field names** that shouldn't be public

## Configuration Method

Sortable fields are configured using PHP attributes directly on entity classes:

Use the `#[SortableFields]` attribute on your entity class:

```php
<?php

namespace App\Entity;

use AlexFigures\Symfony\Resource\Attribute\{JsonApiResource, Id, Attribute, SortableFields};

#[JsonApiResource(type: 'articles')]
#[SortableFields(['title', 'createdAt', 'updatedAt', 'viewCount'])]
class Article
{
    #[Id]
    #[Attribute]
    public string $id;

    #[Attribute]
    public string $title;

    #[Attribute(writable: false)]
    public \DateTimeImmutable $createdAt;

    #[Attribute(writable: false)]
    public \DateTimeImmutable $updatedAt;

    #[Attribute]
    public int $viewCount;
}
```

**Benefits:**
- ✅ Co-located with entity definition
- ✅ Type-safe and IDE-friendly
- ✅ No external configuration files needed
- ✅ Easier to maintain and refactor
- ✅ Follows modern PHP best practices

## Example

```php
<?php

namespace App\Entity;

use AlexFigures\Symfony\Resource\Attribute\{JsonApiResource, SortableFields};

#[JsonApiResource(type: 'categories')]
#[SortableFields(['name', 'slug', 'sortOrder', 'createdAt', 'updatedAt', 'depth'])]
class Category
{
    // ... entity properties
}

#[JsonApiResource(type: 'brands')]
#[SortableFields(['name', 'isActive', 'createdAt', 'updatedAt'])]
class Brand
{
    // ... entity properties
}

#[JsonApiResource(type: 'manufacturers')]
#[SortableFields(['name', 'isActive', 'year', 'legalEntity', 'createdAt', 'updatedAt'])]
class Manufacturer
{
    // ... entity properties
}
```

## Usage Examples

### Basic Sorting

```bash
# Sort by title ascending
GET /api/articles?sort=title

# Sort by createdAt descending
GET /api/articles?sort=-createdAt

# Sort by multiple fields
GET /api/articles?sort=-createdAt,title
```

### Error Handling

If a client tries to sort by a field that's not in the whitelist:

```bash
GET /api/articles?sort=internalScore
```

Response:

```json
{
  "errors": [
    {
      "status": "400",
      "title": "Bad Request",
      "detail": "Sort field 'internalScore' is not allowed for resource type 'articles'."
    }
  ]
}
```

## Best Practices

### 1. Only Whitelist Indexed Fields

Only allow sorting on fields that have database indexes:

```php
#[SortableFields(['createdAt', 'updatedAt', 'status'])]  // ✅ All indexed
```

Avoid:

```php
#[SortableFields(['description', 'content'])]  // ❌ Large text fields, not indexed
```

### 2. Use Consistent Field Names

Use the same field names as your JSON:API attributes:

```php
#[JsonApiResource(type: 'articles')]
#[SortableFields(['createdAt', 'updatedAt'])]  // ✅ Matches attribute names
class Article
{
    #[Attribute(name: 'createdAt')]
    public \DateTimeImmutable $createdAt;
}
```

### 3. Consider Common Use Cases

Include fields that users commonly sort by:

```php
#[SortableFields([
    'name',        // Alphabetical sorting
    'createdAt',   // Chronological sorting
    'updatedAt',   // Recently modified
    'sortOrder',   // Custom ordering
])]
```

### 4. Document Sortable Fields

Add comments to explain why certain fields are sortable:

```php
#[JsonApiResource(type: 'products')]
#[SortableFields([
    'name',        // Alphabetical product listing
    'price',       // Price comparison
    'rating',      // Best-rated products
    'createdAt',   // New arrivals
])]
class Product
{
    // ...
}
```

## Security Considerations

### Prevent Timing Attacks

Never allow sorting on sensitive fields that could reveal information through timing:

```php
// ❌ BAD - Could reveal user existence through timing
#[SortableFields(['email', 'username'])]

// ✅ GOOD - Only allow sorting on non-sensitive fields
#[SortableFields(['displayName', 'createdAt'])]
```

### Limit Sortable Fields

Don't expose all fields for sorting. Only whitelist what's necessary:

```php
// ❌ BAD - Too permissive
#[SortableFields(['id', 'name', 'email', 'password', 'apiKey', 'internalScore'])]

// ✅ GOOD - Minimal and safe
#[SortableFields(['name', 'createdAt'])]
```

## Troubleshooting

### Sorting Not Working

**Problem:** Sorting parameter is ignored or returns an error.

**Solution:** Check that:
1. The field is listed in `SortableFields` attribute
2. The field name matches the JSON:API attribute name (not the PHP property name)
3. The entity has the `#[JsonApiResource]` attribute

### Performance Issues

**Problem:** Sorting is slow.

**Solution:**
1. Ensure sorted fields have database indexes
2. Remove large text fields from sortable fields
3. Consider adding composite indexes for common sort combinations

### Migration Issues

**Problem:** After migrating from YAML to attributes, sorting stopped working.

**Solution:**
1. Verify the `SortableFields` attribute is present on the entity
2. Check that field names match exactly (case-sensitive)
3. Clear the Symfony cache: `php bin/console cache:clear`

## API Reference

### SortableFields Attribute

**Namespace:** `AlexFigures\Symfony\Resource\Attribute\SortableFields`

**Target:** Class

**Parameters:**
- `fields` (array): List of field names that can be used for sorting

**Example:**

```php
#[SortableFields(['name', 'createdAt', 'updatedAt'])]
```

## See Also

- [Resource Configuration](./resource-configuration.md)
- [Query Parameters](./query-parameters.md)
- [Security Best Practices](../security/best-practices.md)

