# Custom Filter and Sort Handlers

Custom handlers allow you to implement complex filtering and sorting logic that goes beyond simple field comparisons. This is useful for full-text search, geospatial queries, relevance scoring, and other advanced use cases.

## Table of Contents

- [Filter Handlers](#filter-handlers)
- [Sort Handlers](#sort-handlers)
- [Service Registration](#service-registration)
- [Real-World Examples](#real-world-examples)
- [Best Practices](#best-practices)
- [Testing Custom Handlers](#testing-custom-handlers)

## Filter Handlers

### FilterHandlerInterface

Custom filter handlers must implement the `FilterHandlerInterface`:

```php
<?php

namespace JsonApi\Symfony\Filter\Handler;

interface FilterHandlerInterface
{
    /**
     * Check if this handler supports the given field and operator.
     */
    public function supports(string $field, string $operator): bool;

    /**
     * Handle the filter by modifying the query builder.
     */
    public function handle(string $field, string $operator, array $values, object $queryBuilder): void;

    /**
     * Get the priority of this handler (higher = more important).
     */
    public function getPriority(): int;
}
```

### Example: Full-Text Search Filter

```php
<?php

namespace App\Filter;

use Doctrine\ORM\QueryBuilder;
use JsonApi\Symfony\Filter\Handler\FilterHandlerInterface;

final class FullTextSearchFilter implements FilterHandlerInterface
{
    public function supports(string $field, string $operator): bool
    {
        return $field === 'search' && $operator === 'eq';
    }

    public function handle(string $field, string $operator, array $values, object $queryBuilder): void
    {
        if (!$queryBuilder instanceof QueryBuilder) {
            throw new \InvalidArgumentException('Expected Doctrine QueryBuilder');
        }

        $searchTerm = $values[0] ?? '';
        if ($searchTerm === '') {
            return;
        }

        $rootAliases = $queryBuilder->getRootAliases();
        $rootAlias = $rootAliases[0] ?? 'e';

        // Search across multiple fields
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->like("$rootAlias.title", ':searchTerm'),
                $queryBuilder->expr()->like("$rootAlias.content", ':searchTerm'),
                $queryBuilder->expr()->like("$rootAlias.summary", ':searchTerm')
            )
        );

        $queryBuilder->setParameter('searchTerm', '%' . $searchTerm . '%');
    }

    public function getPriority(): int
    {
        return 0;
    }
}
```

**Usage in Resource:**

```php
#[JsonApiResource(type: 'articles')]
#[FilterableFields([
    new FilterableField('search', customHandler: FullTextSearchFilter::class),
    new FilterableField('status', operators: ['eq']),
])]
class Article {}
```

**API Request:**
```http
GET /api/articles?filter[search][eq]=symfony
```

### Example: Geospatial Distance Filter

```php
<?php

namespace App\Filter;

use Doctrine\ORM\QueryBuilder;
use JsonApi\Symfony\Filter\Handler\FilterHandlerInterface;

final class GeospatialDistanceFilter implements FilterHandlerInterface
{
    public function supports(string $field, string $operator): bool
    {
        return $field === 'distance' && in_array($operator, ['lte', 'lt'], true);
    }

    public function handle(string $field, string $operator, array $values, object $queryBuilder): void
    {
        if (!$queryBuilder instanceof QueryBuilder) {
            throw new \InvalidArgumentException('Expected Doctrine QueryBuilder');
        }

        $coordinates = $values[0] ?? '';
        if ($coordinates === '') {
            return;
        }

        // Parse coordinates: "latitude,longitude,radius"
        $parts = explode(',', $coordinates);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Distance filter requires format: latitude,longitude,radius');
        }

        [$latitude, $longitude, $radius] = array_map('floatval', $parts);

        $rootAliases = $queryBuilder->getRootAliases();
        $rootAlias = $rootAliases[0] ?? 'e';

        // Haversine formula for calculating distance
        $distanceFormula = sprintf(
            '(6371 * ACOS(COS(RADIANS(:lat)) * COS(RADIANS(%s.latitude)) * COS(RADIANS(%s.longitude) - RADIANS(:lng)) + SIN(RADIANS(:lat)) * SIN(RADIANS(%s.latitude))))',
            $rootAlias, $rootAlias, $rootAlias
        );

        $comparison = $operator === 'lte' ? '<=' : '<';
        $queryBuilder->andWhere("$distanceFormula $comparison :radius");

        $queryBuilder->setParameter('lat', $latitude);
        $queryBuilder->setParameter('lng', $longitude);
        $queryBuilder->setParameter('radius', $radius);
    }

    public function getPriority(): int
    {
        return 0;
    }
}
```

**API Request:**
```http
GET /api/locations?filter[distance][lte]=50.123,14.456,10
```

## Sort Handlers

### SortHandlerInterface

Custom sort handlers must implement the `SortHandlerInterface`:

```php
<?php

namespace JsonApi\Symfony\Filter\Handler;

interface SortHandlerInterface
{
    /**
     * Check if this handler supports the given field.
     */
    public function supports(string $field): bool;

    /**
     * Handle the sort by modifying the query builder.
     */
    public function handle(string $field, bool $descending, object $queryBuilder): void;

    /**
     * Get the priority of this handler (higher = more important).
     */
    public function getPriority(): int;
}
```

### Example: Relevance-Based Sorting

```php
<?php

namespace App\Sort;

use Doctrine\ORM\QueryBuilder;
use JsonApi\Symfony\Filter\Handler\SortHandlerInterface;

final class RelevanceSorter implements SortHandlerInterface
{
    public function supports(string $field): bool
    {
        return $field === 'relevance';
    }

    public function handle(string $field, bool $descending, object $queryBuilder): void
    {
        if (!$queryBuilder instanceof QueryBuilder) {
            throw new \InvalidArgumentException('Expected Doctrine QueryBuilder');
        }

        $rootAliases = $queryBuilder->getRootAliases();
        $rootAlias = $rootAliases[0] ?? 'e';

        $direction = $descending ? 'DESC' : 'ASC';

        // Calculate relevance score based on:
        // - View count (70% weight)
        // - Recency (30% weight, newer articles get higher score)
        $relevanceFormula = sprintf(
            '(%s.viewCount * 0.7 + (DATEDIFF(CURRENT_DATE(), %s.createdAt) * -0.3))',
            $rootAlias, $rootAlias
        );

        $queryBuilder->addSelect("($relevanceFormula) AS HIDDEN relevance_score");
        $queryBuilder->addOrderBy('relevance_score', $direction);
    }

    public function getPriority(): int
    {
        return 0;
    }
}
```

**API Request:**
```http
GET /api/articles?sort=-relevance
```

## Service Registration

### Automatic Registration

Register your custom handlers using Symfony's service auto-configuration:

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

### Manual Registration

For more control, register handlers individually:

```yaml
# config/services.yaml
services:
    App\Filter\FullTextSearchFilter:
        tags: ['jsonapi.filter.handler']
    
    App\Filter\GeospatialDistanceFilter:
        tags: ['jsonapi.filter.handler']
    
    App\Sort\RelevanceSorter:
        tags: ['jsonapi.sort.handler']
```

### PHP Configuration

You can also register handlers in PHP:

```php
// config/services.php
use App\Filter\FullTextSearchFilter;
use App\Sort\RelevanceSorter;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->set(FullTextSearchFilter::class)
        ->tag('jsonapi.filter.handler');
    
    $services->set(RelevanceSorter::class)
        ->tag('jsonapi.sort.handler');
};
```

## Real-World Examples

### E-commerce Product Search

```php
#[JsonApiResource(type: 'products')]
#[FilterableFields([
    new FilterableField('search', customHandler: ProductSearchFilter::class),
    new FilterableField('price_range', customHandler: PriceRangeFilter::class),
    new FilterableField('category', operators: ['eq', 'in']),
    new FilterableField('inStock', operators: ['eq']),
])]
#[SortableFields(['name', 'price', 'popularity', 'relevance'])]
class Product {}
```

```http
# Search with filters and custom sorting
GET /api/products?filter[search][eq]=laptop&filter[price_range][lte]=500,1500&sort=-relevance
```

### Content Management System

```php
#[JsonApiResource(type: 'articles')]
#[FilterableFields([
    new FilterableField('search', customHandler: FullTextSearchFilter::class),
    new FilterableField('tags', customHandler: TagsFilter::class),
    new FilterableField('author', operators: ['eq']),
    new FilterableField('publishedAt', operators: ['gte', 'lte']),
])]
#[SortableFields(['title', 'publishedAt', 'viewCount', 'relevance'])]
class Article {}
```

## Best Practices

### 1. Validate Input

Always validate and sanitize input in your handlers:

```php
public function handle(string $field, string $operator, array $values, object $queryBuilder): void
{
    $searchTerm = $values[0] ?? '';
    
    // Validate input
    if (strlen($searchTerm) < 3) {
        throw new \InvalidArgumentException('Search term must be at least 3 characters');
    }
    
    // Sanitize input
    $searchTerm = trim($searchTerm);
    $searchTerm = preg_replace('/[^\w\s-]/', '', $searchTerm);
    
    // ... rest of implementation
}
```

### 2. Use Parameterized Queries

Always use parameterized queries to prevent SQL injection:

```php
// ✅ Good - parameterized
$queryBuilder->andWhere("$rootAlias.title LIKE :searchTerm");
$queryBuilder->setParameter('searchTerm', '%' . $searchTerm . '%');

// ❌ Bad - string concatenation
$queryBuilder->andWhere("$rootAlias.title LIKE '%" . $searchTerm . "%'");
```

### 3. Handle Edge Cases

Consider edge cases and error conditions:

```php
public function handle(string $field, string $operator, array $values, object $queryBuilder): void
{
    if (!$queryBuilder instanceof QueryBuilder) {
        throw new \InvalidArgumentException('Expected Doctrine QueryBuilder');
    }
    
    if (empty($values)) {
        return; // No values to filter on
    }
    
    $searchTerm = $values[0] ?? '';
    if ($searchTerm === '') {
        return; // Empty search term
    }
    
    // ... implementation
}
```

### 4. Consider Performance

Be mindful of database performance:

```php
// ✅ Good - uses indexes
$queryBuilder->andWhere("$rootAlias.status = :status");

// ❌ Potentially slow - full table scan
$queryBuilder->andWhere("LOWER($rootAlias.description) LIKE :term");

// ✅ Better - with proper indexing strategy
$queryBuilder->andWhere("$rootAlias.searchVector @@ plainto_tsquery(:term)"); // PostgreSQL full-text
```

### 5. Use Priority for Conflicts

Use priority to resolve conflicts when multiple handlers support the same field:

```php
final class AdvancedSearchFilter implements FilterHandlerInterface
{
    public function getPriority(): int
    {
        return 10; // Higher priority than basic search
    }
    
    // ... implementation
}
```

## Testing Custom Handlers

### Unit Testing

```php
<?php

namespace App\Tests\Filter;

use App\Filter\FullTextSearchFilter;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class FullTextSearchFilterTest extends TestCase
{
    public function testSupports(): void
    {
        $filter = new FullTextSearchFilter();
        
        $this->assertTrue($filter->supports('search', 'eq'));
        $this->assertFalse($filter->supports('title', 'eq'));
        $this->assertFalse($filter->supports('search', 'like'));
    }

    public function testHandle(): void
    {
        $filter = new FullTextSearchFilter();
        $queryBuilder = $this->createMock(QueryBuilder::class);
        
        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with($this->stringContains('title'));
            
        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('searchTerm', '%symfony%');
        
        $filter->handle('search', 'eq', ['symfony'], $queryBuilder);
    }
}
```

### Integration Testing

```php
public function testCustomFilterIntegration(): void
{
    $response = $this->get('/api/articles?filter[search][eq]=symfony');
    
    $this->assertResponseIsSuccessful();
    $data = json_decode($response->getContent(), true);
    
    // Verify that results contain the search term
    foreach ($data['data'] as $article) {
        $title = $article['attributes']['title'];
        $this->assertStringContainsStringIgnoringCase('symfony', $title);
    }
}
```

## Related Documentation

- [FilterableFields Configuration](filterable-fields.md)
- [Examples Directory](../examples/README.md)
- [Security Checklist](../security/checklist.md)
