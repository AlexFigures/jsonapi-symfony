# SortableFields Quick Reference

## Basic Usage

```php
use JsonApi\Symfony\Resource\Attribute\{JsonApiResource, SortableFields};

#[JsonApiResource(type: 'articles')]
#[SortableFields(['title', 'createdAt', 'updatedAt'])]
class Article
{
    // ... entity properties
}
```

## API Request

```bash
# Sort by single field (ascending)
GET /api/articles?sort=title

# Sort by single field (descending)
GET /api/articles?sort=-createdAt

# Sort by multiple fields
GET /api/articles?sort=-createdAt,title
```

## Common Patterns

### Timestamps Only

```php
#[SortableFields(['createdAt', 'updatedAt'])]
```

### Name and Status

```php
#[SortableFields(['name', 'isActive', 'createdAt'])]
```

### E-commerce Product

```php
#[SortableFields(['name', 'price', 'rating', 'createdAt'])]
```

### Blog Article

```php
#[SortableFields(['title', 'publishedAt', 'viewCount', 'createdAt'])]
```



## Error Response

When sorting by a non-whitelisted field:

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

✅ **DO**: Only whitelist indexed fields  
✅ **DO**: Use consistent field names with JSON:API attributes  
✅ **DO**: Include common sorting fields (name, createdAt, etc.)  

❌ **DON'T**: Whitelist large text fields  
❌ **DON'T**: Whitelist sensitive fields  
❌ **DON'T**: Whitelist all fields indiscriminately  

## See Also

- [Full Sorting Configuration Guide](../guide/sorting-configuration.md)
- [Examples](../examples/SortableFieldsExample.php)

