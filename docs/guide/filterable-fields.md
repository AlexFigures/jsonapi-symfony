# FilterableFields Configuration

The `#[FilterableFields]` attribute provides a secure, whitelist-based approach to configuring which fields can be filtered in your JSON:API resources. This ensures that only explicitly allowed fields and operators can be used in filter queries.

## Table of Contents

- [Basic Usage](#basic-usage)
- [Operator Restrictions](#operator-restrictions)
- [Custom Filter Handlers](#custom-filter-handlers)
- [Security Considerations](#security-considerations)
- [API Examples](#api-examples)
- [Best Practices](#best-practices)

## Basic Usage

### Simple Whitelist

The simplest way to use `FilterableFields` is to provide a list of field names that can be filtered:

```php
<?php

use JsonApi\Symfony\Resource\Attribute\{JsonApiResource, FilterableFields};

#[JsonApiResource(type: 'articles')]
#[FilterableFields(['title', 'status', 'createdAt', 'authorId'])]
class Article
{
    // All standard operators are allowed for the specified fields
}
```

**API Usage:**
```http
GET /api/articles?filter[title][like]=symfony
GET /api/articles?filter[status][eq]=published
GET /api/articles?filter[createdAt][gte]=2024-01-01
```

### No Configuration = No Filtering

If you don't add the `#[FilterableFields]` attribute to your resource, **all filtering will be rejected**:

```php
#[JsonApiResource(type: 'users')]
class User
{
    // No FilterableFields attribute = filtering not allowed
}
```

```http
GET /api/users?filter[email][eq]=test@example.com
// Returns: 400 Bad Request - "Filtering not allowed"
```

## Operator Restrictions

You can restrict which operators are allowed for each field using the `FilterableField` class:

```php
<?php

use JsonApi\Symfony\Resource\Attribute\{JsonApiResource, FilterableFields, FilterableField};

#[JsonApiResource(type: 'products')]
#[FilterableFields([
    new FilterableField('name', operators: ['eq', 'like']),
    new FilterableField('price', operators: ['eq', 'gt', 'gte', 'lt', 'lte']),
    new FilterableField('category', operators: ['eq', 'in']),
    new FilterableField('inStock', operators: ['eq']),
    'sku', // All operators allowed
])]
class Product
{
    // Mixed configuration: some fields with restricted operators, others with all operators
}
```

**API Usage:**
```http
# ✅ Allowed
GET /api/products?filter[name][like]=phone
GET /api/products?filter[price][gte]=100&filter[price][lte]=500
GET /api/products?filter[category][in]=electronics,gadgets

# ❌ Not allowed
GET /api/products?filter[name][gt]=phone        # 'gt' not allowed for 'name'
GET /api/products?filter[inStock][like]=true    # 'like' not allowed for 'inStock'
```

### Available Operators

The following operators are available by default:

- `eq` - Equal
- `ne` - Not equal  
- `gt` - Greater than
- `gte` - Greater than or equal
- `lt` - Less than
- `lte` - Less than or equal
- `like` - SQL LIKE pattern matching
- `in` - Value in list
- `nin` - Value not in list
- `null` - Is null
- `nnull` - Is not null

## Custom Filter Handlers

For complex filtering logic, you can create custom filter handlers:

```php
<?php

use JsonApi\Symfony\Resource\Attribute\{JsonApiResource, FilterableFields, FilterableField};

#[JsonApiResource(type: 'articles')]
#[FilterableFields([
    new FilterableField('search', customHandler: FullTextSearchFilter::class),
    new FilterableField('distance', customHandler: GeospatialDistanceFilter::class),
    new FilterableField('status', operators: ['eq', 'in']),
])]
class Article
{
    // Custom handlers for complex filtering logic
}
```

See the [Custom Handlers Examples](../examples/README.md) for implementation details.

## Security Considerations

### Whitelist-Only Approach

The `FilterableFields` system uses a **whitelist-only** approach for security:

1. **No attribute = No filtering**: Resources without `#[FilterableFields]` reject all filter attempts
2. **Explicit field allowlist**: Only fields explicitly listed can be filtered
3. **Operator restrictions**: You can limit which operators work with each field
4. **No wildcards**: There's no way to "allow all fields" - you must be explicit

### Preventing Information Disclosure

```php
#[JsonApiResource(type: 'users')]
#[FilterableFields([
    'username',     // Safe to filter
    'email',        // Safe to filter  
    'isActive',     // Safe to filter
    // 'password' - NEVER include sensitive fields!
    // 'apiKey'   - NEVER include sensitive fields!
])]
class User
{
    // Only safe, non-sensitive fields are filterable
}
```

## API Examples

### Basic Filtering

```http
# Single field filter
GET /api/articles?filter[status][eq]=published

# Multiple filters (AND logic)
GET /api/articles?filter[status][eq]=published&filter[viewCount][gte]=100

# Range filtering
GET /api/articles?filter[createdAt][gte]=2024-01-01&filter[createdAt][lte]=2024-12-31

# List filtering
GET /api/articles?filter[category][in]=tech,science,programming
```

### Complex Filtering with JSON:API Syntax

```http
# Using JSON:API filter syntax
GET /api/articles?filter[and][0][status][eq]=published&filter[and][1][viewCount][gte]=100

# OR logic (if supported by your filter parser)
GET /api/articles?filter[or][0][status][eq]=draft&filter[or][1][status][eq]=published
```

### Combining with Sorting and Pagination

```http
GET /api/articles?filter[status][eq]=published&sort=-createdAt&page[number]=1&page[size]=10
```

## Best Practices

### 1. Start Restrictive

Begin with minimal filtering and add fields as needed:

```php
#[FilterableFields(['id', 'status'])] // Start minimal
class Article
{
    // Add more fields over time as requirements become clear
}
```

### 2. Use Operator Restrictions

Don't allow all operators unless necessary:

```php
#[FilterableFields([
    new FilterableField('email', operators: ['eq']),        // Only exact matches
    new FilterableField('createdAt', operators: ['gte', 'lte']), // Only ranges
    new FilterableField('tags', operators: ['in']),         // Only list matching
])]
```

### 3. Document Your Filtering

Include filtering capabilities in your API documentation:

```php
/**
 * Article resource with filtering support.
 * 
 * Filterable fields:
 * - title: eq, like
 * - status: eq, in  
 * - createdAt: gte, lte
 * - viewCount: eq, gt, gte, lt, lte
 */
#[JsonApiResource(type: 'articles')]
#[FilterableFields([
    new FilterableField('title', operators: ['eq', 'like']),
    new FilterableField('status', operators: ['eq', 'in']),
    new FilterableField('createdAt', operators: ['gte', 'lte']),
    new FilterableField('viewCount', operators: ['eq', 'gt', 'gte', 'lt', 'lte']),
])]
class Article {}
```

### 4. Consider Performance

Be mindful of database performance when allowing certain filters:

```php
#[FilterableFields([
    'id',           // ✅ Fast - primary key
    'status',       // ✅ Fast - indexed enum
    'createdAt',    // ✅ Fast - indexed timestamp
    // 'content',   // ❌ Slow - large text field without index
])]
```

### 5. Test Your Filters

Always test your filtering configuration:

```php
// In your tests
public function testArticleFiltering(): void
{
    $response = $this->get('/api/articles?filter[status][eq]=published');
    $this->assertResponseIsSuccessful();
    
    $response = $this->get('/api/articles?filter[secretField][eq]=value');
    $this->assertResponseStatusCodeSame(400); // Should be rejected
}
```

## Related Documentation

- [SortableFields Configuration](sorting-configuration.md)
- [Custom Handlers Examples](../examples/README.md)
- [Security Checklist](../security/checklist.md)
