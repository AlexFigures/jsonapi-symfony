# Custom Handlers Examples

This directory contains example implementations of custom filter and sort handlers for the jsonapi-symfony library.

## Filter Handlers

### FullTextSearchFilter.php
Demonstrates how to implement a custom filter that searches across multiple fields using a single "search" parameter.

**Features:**
- Searches across title, content, and summary fields
- Uses LIKE operator with wildcards
- Supports only the 'eq' operator for the 'search' field

**Usage:**
```php
#[FilterableFields([
    new FilterableField('search', customHandler: FullTextSearchFilter::class),
])]
```

**API Request:**
```
GET /api/articles?filter[search][eq]=symfony
```

### GeospatialDistanceFilter.php
Shows how to implement location-based filtering using latitude and longitude coordinates.

**Features:**
- Calculates distance using the Haversine formula
- Supports 'lte' and 'lt' operators for radius filtering
- Expects coordinates in format: "latitude,longitude,radius"

**Usage:**
```php
#[FilterableFields([
    new FilterableField('distance', customHandler: GeospatialDistanceFilter::class),
])]
```

**API Request:**
```
GET /api/locations?filter[distance][lte]=50.123,14.456,10
```

## Sort Handlers

### RelevanceSorter.php
Demonstrates complex sorting logic that combines multiple factors to calculate relevance.

**Features:**
- Combines view count (70% weight) and recency (30% weight)
- Newer articles get higher relevance scores
- Uses SQL expressions for calculation

**Usage:**
```php
#[SortableFields(['relevance'])]
```

**API Request:**
```
GET /api/articles?sort=-relevance
```

## Complete Example

### CustomHandlersExample.php
A complete entity example showing how to combine custom handlers with standard filtering and sorting.

**Features:**
- Multiple custom filter handlers
- Mix of custom and standard operators
- Custom sort handler alongside standard sorting
- Real-world usage patterns

## Service Registration

To use these custom handlers in your application, register them in your service configuration:

```yaml
# config/services.yaml
services:
    # Custom filter handlers
    App\Filter\FullTextSearchFilter:
        tags: ['jsonapi.filter.handler']
    
    App\Filter\GeospatialDistanceFilter:
        tags: ['jsonapi.filter.handler']
    
    # Custom sort handlers  
    App\Sort\RelevanceSorter:
        tags: ['jsonapi.sort.handler']
```

Or use auto-configuration:

```yaml
# config/services.yaml
services:
    # Auto-register all filter handlers
    App\Filter\:
        resource: '../src/Filter/*Filter.php'
        tags: ['jsonapi.filter.handler']

    # Auto-register all sort handlers
    App\Sort\:
        resource: '../src/Sort/*Sorter.php'
        tags: ['jsonapi.sort.handler']
```

## Best Practices

1. **Validation**: Always validate input parameters in your handlers
2. **Performance**: Consider the performance impact of complex calculations
3. **Security**: Sanitize user input to prevent SQL injection
4. **Documentation**: Document the expected input format and behavior
5. **Testing**: Write unit tests for your custom handlers
6. **Priority**: Use the `getPriority()` method to control handler precedence
