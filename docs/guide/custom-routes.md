# Custom Routes

The jsonapi-symfony library automatically generates standard CRUD routes for your JSON:API resources. However, sometimes you need custom endpoints that don't fit the standard resource operations. The `#[JsonApiCustomRoute]` attribute allows you to define these custom routes directly on your entity classes or controller classes.

## When to Use Custom Routes

Custom routes are useful for:

- **Resource actions**: Publishing articles, archiving posts, activating users
- **Search endpoints**: Full-text search, filtered queries
- **Bulk operations**: Batch updates, mass deletions
- **Aggregation endpoints**: Statistics, reports, summaries
- **Workflow actions**: Approval processes, state transitions
- **Custom business logic**: Any operation that doesn't fit standard CRUD

## Basic Usage

### On Entity Classes

Define custom routes directly on your entity classes using the `#[JsonApiCustomRoute]` attribute:

```php
<?php

use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\JsonApiCustomRoute;

#[JsonApiResource(type: 'articles')]
#[JsonApiCustomRoute(
    name: 'articles.publish',
    path: '/articles/{id}/publish',
    methods: ['POST'],
    controller: 'App\Controller\PublishArticleController'
)]
#[JsonApiCustomRoute(
    name: 'articles.archive',
    path: '/articles/{id}/archive',
    methods: ['POST'],
    controller: 'App\Controller\ArchiveArticleController',
    requirements: ['id' => '\d+']
)]
class Article
{
    // ... entity properties and methods
}
```

### On Controller Classes

You can also define custom routes on dedicated controller classes:

```php
<?php

use AlexFigures\Symfony\Resource\Attribute\JsonApiCustomRoute;

#[JsonApiCustomRoute(
    name: 'articles.search',
    path: '/articles/search',
    methods: ['GET'],
    resourceType: 'articles',
    controller: 'App\Controller\SearchController::search',
    priority: 10  // High priority to ensure it matches before /articles/{id}
)]
#[JsonApiCustomRoute(
    name: 'articles.trending',
    path: '/articles/trending',
    methods: ['GET'],
    resourceType: 'articles',
    defaults: ['_format' => 'json']
)]
class SearchController
{
    public function search(): Response
    {
        // Search implementation
    }
    
    public function trending(): Response
    {
        // Trending implementation
    }
}
```

## Attribute Parameters

The `#[JsonApiCustomRoute]` attribute supports all standard Symfony route options:

### Required Parameters

- **`name`**: Unique route name (string)
- **`path`**: URL path pattern (string)

### Optional Parameters

- **`methods`**: HTTP methods (array, default: `['GET']`)
- **`controller`**: Controller class or method (string)
- **`resourceType`**: Associated resource type (string)
- **`defaults`**: Default route parameters (array, default: `[]`)
- **`requirements`**: Route parameter requirements (array, default: `[]`)
- **`description`**: Human-readable description (string, default: `null`)
- **`priority`**: Route priority for ordering (int, default: `0`)

### Controller vs ResourceType

You must specify either `controller` or `resourceType` (or both):

- **`controller`**: Specifies the exact controller to handle the route
- **`resourceType`**: When used on a controller class, indicates which resource type this route belongs to

## Practical Examples

### 1. Resource Actions

```php
#[JsonApiResource(type: 'articles')]
#[JsonApiCustomRoute(
    name: 'articles.publish',
    path: '/articles/{id}/publish',
    methods: ['POST'],
    controller: 'App\Controller\Article\PublishController',
    requirements: ['id' => '\d+'],
    description: 'Publish an article'
)]
#[JsonApiCustomRoute(
    name: 'articles.unpublish',
    path: '/articles/{id}/unpublish',
    methods: ['POST'],
    controller: 'App\Controller\Article\UnpublishController',
    requirements: ['id' => '\d+']
)]
class Article
{
    // ...
}
```

### 2. Search and Filtering

```php
#[JsonApiCustomRoute(
    name: 'articles.search',
    path: '/articles/search',
    methods: ['GET', 'POST'],
    resourceType: 'articles',
    description: 'Full-text search for articles',
    priority: 10  // High priority to avoid conflict with /articles/{id}
)]
#[JsonApiCustomRoute(
    name: 'articles.filter',
    path: '/articles/filter/{category}',
    methods: ['GET'],
    resourceType: 'articles',
    requirements: ['category' => '[a-z-]+'],
    priority: 5   // High priority for static path segment
)]
class ArticleSearchController
{
    // ...
}
```

### 3. Bulk Operations

```php
#[JsonApiCustomRoute(
    name: 'articles.bulk.publish',
    path: '/articles/bulk/publish',
    methods: ['POST'],
    resourceType: 'articles',
    description: 'Publish multiple articles at once'
)]
#[JsonApiCustomRoute(
    name: 'articles.bulk.delete',
    path: '/articles/bulk/delete',
    methods: ['DELETE'],
    resourceType: 'articles'
)]
class ArticleBulkController
{
    // ...
}
```

### 4. Statistics and Reports

```php
#[JsonApiCustomRoute(
    name: 'articles.stats',
    path: '/articles/statistics',
    methods: ['GET'],
    resourceType: 'articles',
    defaults: ['_format' => 'json']
)]
#[JsonApiCustomRoute(
    name: 'articles.report',
    path: '/articles/report/{period}',
    methods: ['GET'],
    resourceType: 'articles',
    requirements: ['period' => 'daily|weekly|monthly'],
    defaults: ['period' => 'daily']
)]
class ArticleStatsController
{
    // ...
}
```

## Controller Requirements

The `controller` parameter requirements depend on where you place the attribute:

### When Used on Entity Classes
The `controller` parameter is **required** when placing the attribute on entity classes:

```php
#[JsonApiResource(type: 'articles')]
#[JsonApiCustomRoute(
    name: 'articles.publish',
    path: '/articles/{id}/publish',
    methods: ['POST'],
    controller: 'App\Controller\PublishController::publish' // Required!
)]
class Article
{
    // ...
}
```

### When Used on Controller Classes
When placing the attribute on controller classes, you have two options:

**Option 1: Explicit Controller (Recommended)**
```php
#[JsonApiCustomRoute(
    name: 'articles.search',
    path: '/articles/search',
    methods: ['GET'],
    controller: 'App\Controller\SearchController::search', // Explicit method
    resourceType: 'articles'
)]
class SearchController
{
    public function search(): Response { /* ... */ }
}
```

**Option 2: Invokable Controller**
```php
#[JsonApiCustomRoute(
    name: 'articles.search',
    path: '/articles/search',
    methods: ['GET'],
    resourceType: 'articles'
    // No controller parameter needed for invokable controllers
)]
class SearchController
{
    public function __invoke(): Response { /* ... */ }
}
```

⚠️ **Important**: If you place the attribute on a non-invokable controller class without specifying the `controller` parameter, you'll get a clear error message explaining what to do.

## Route Priority

Route priority is crucial for ensuring your custom routes are matched correctly. The priority system works as follows:

- **Priority > 0**: Routes are added BEFORE auto-generated routes (high priority)
- **Priority ≤ 0**: Routes are added AFTER auto-generated routes (low priority)
- **Default priority**: 0 (low priority)

### Why Priority Matters

Consider this common scenario:

```php
#[JsonApiResource(type: 'articles')]
#[JsonApiCustomRoute(
    name: 'articles.search',
    path: '/articles/search',
    methods: ['GET'],
    controller: 'App\Controller\SearchController::search',
    priority: 10  // HIGH PRIORITY - Essential!
)]
class Article
{
    // ...
}
```

**Without high priority (priority ≤ 0):**
- Auto-generated route: `GET /articles/{id}` (matches first)
- Custom route: `GET /articles/search` (never reached!)
- Request to `/articles/search` → matches `/articles/{id}` with `id="search"`

**With high priority (priority > 0):**
- Custom route: `GET /articles/search` (matches first) ✅
- Auto-generated route: `GET /articles/{id}` (matches other requests)
- Request to `/articles/search` → correctly matches custom route

### Priority Examples

```php
// High priority - added before auto-generated routes
#[JsonApiCustomRoute(
    name: 'articles.search',
    path: '/articles/search',
    methods: ['GET'],
    resourceType: 'articles',
    priority: 10  // Must be > 0 to work correctly
)]

// Low priority - added after auto-generated routes
#[JsonApiCustomRoute(
    name: 'articles.archive',
    path: '/articles/archive',
    methods: ['POST'],
    resourceType: 'articles',
    priority: 0   // Default - safe for non-conflicting paths
)]

// Very high priority - for critical routes
#[JsonApiCustomRoute(
    name: 'articles.trending',
    path: '/articles/trending',
    methods: ['GET'],
    resourceType: 'articles',
    priority: 100  // Highest priority
)]
```

### Best Practices for Priority

1. **Use high priority (> 0) for static paths** that might conflict with `/{id}` patterns
2. **Use default priority (0) for unique paths** that won't conflict
3. **Higher numbers = higher priority** within the same priority group
4. **Common priority values:**
   - `100`: Critical system routes
   - `10`: Standard custom endpoints (search, trending, etc.)
   - `1`: Minor custom endpoints
   - `0`: Default (non-conflicting routes)
   - `-1`: Fallback routes

## Route Name Conventions

### Automatic Transformation

Custom route names that follow the exact pattern `jsonapi.{type}.{action}` will be automatically transformed according to your configured naming convention:

```php
// With kebab-case naming convention:
#[JsonApiCustomRoute(
    name: 'jsonapi.blog_posts.publish',  // Will become 'jsonapi.blog-posts.publish'
    // ...
)]
```

### Preserving Custom Names

Complex route names are preserved exactly as specified:

```php
#[JsonApiCustomRoute(
    name: 'jsonapi.articles.actions.publish',  // Preserved exactly
    // ...
)]
#[JsonApiCustomRoute(
    name: 'custom.articles.special',  // Preserved exactly
    // ...
)]
```

## Best Practices

1. **Use descriptive names**: Make route names self-documenting
2. **Group related routes**: Use consistent naming patterns for related operations
3. **Specify requirements**: Add parameter validation where appropriate
4. **Document your routes**: Use the `description` parameter for complex operations
5. **Consider priority**: Use priority when route order matters
6. **Follow REST principles**: Even custom routes should follow RESTful conventions when possible

## Integration with Standard Routes

Custom routes work alongside the automatically generated JSON:API routes. The library will generate both:

- Standard CRUD routes: `GET /articles`, `POST /articles`, `GET /articles/{id}`, etc.
- Your custom routes: `POST /articles/{id}/publish`, `GET /articles/search`, etc.

All routes respect your configured route prefix and naming convention settings.

## Advanced: Filtering, Sorting, and Pagination in Custom Routes

**New in 0.3.0**: Custom route handlers can leverage the full power of JSON:API query parameters (filtering, sorting, pagination) using the `CriteriaBuilder` API.

### The Problem

When implementing custom routes that return collections, you often need to:
1. Apply custom business logic (e.g., filter by a path parameter like `categoryId`)
2. Support standard JSON:API query parameters (`filter`, `sort`, `page`)
3. Avoid duplicating the filtering/sorting/pagination logic

### The Solution: CriteriaBuilder

The `CriteriaBuilder` provides a fluent API for adding custom filters and conditions to the already-parsed JSON:API query parameters.

#### Basic Example: Adding Custom Filters

```php
<?php

namespace App\CustomRoute;

use AlexFigures\Symfony\CustomRoute\Attribute\NoTransaction;
use AlexFigures\Symfony\CustomRoute\Context\CustomRouteContext;
use AlexFigures\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use AlexFigures\Symfony\CustomRoute\Result\CustomRouteResult;

#[NoTransaction]
final class CategoryArticlesHandler implements CustomRouteHandlerInterface
{
    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        $categoryId = $context->getParam('categoryId');

        // Build criteria with custom condition for categoryId
        // This merges with any filters/sorting/pagination from query string
        $criteria = $context->criteria()
            ->addCustomCondition(function ($qb) use ($categoryId) {
                $qb->andWhere('e.category = :categoryId')
                   ->setParameter('categoryId', $categoryId);
            })
            ->build();

        // Use repository to fetch collection with all criteria applied
        // This automatically handles: filters, sorting, pagination, includes
        $slice = $context->getRepository()->findCollection('articles', $criteria);

        return CustomRouteResult::collection($slice->items, $slice->totalItems);
    }
}
```

**Route Definition:**
```php
#[JsonApiResource(type: 'articles')]
#[JsonApiCustomRoute(
    name: 'categories.articles',
    path: '/categories/{categoryId}/articles',
    methods: ['GET'],
    handler: CategoryArticlesHandler::class,
    resourceType: 'articles'
)]
class Article {}
```

**API Request:**
```http
GET /api/categories/123/articles?filter[status][eq]=published&sort=-createdAt&page[size]=10&page[number]=1
```

This request will:
1. Filter articles by `categoryId=123` (from path)
2. Filter by `status=published` (from query string)
3. Sort by `createdAt` descending
4. Return page 1 with 10 items per page

### CriteriaBuilder API

#### Method: `addFilter(string $field, string $operator, mixed $value)`

Add a simple filter condition. Supports all standard JSON:API operators:

```php
$criteria = $context->criteria()
    ->addFilter('status', 'eq', 'published')
    ->addFilter('views', 'gte', 1000)
    ->addFilter('tags', 'in', ['php', 'symfony'])
    ->build();
```

**Supported operators:**
- `eq` - equals
- `ne` - not equals
- `lt` - less than
- `lte` - less than or equal
- `gt` - greater than
- `gte` - greater than or equal
- `in` - in array
- `nin` - not in array
- `like` - SQL LIKE pattern
- `ilike` - case-insensitive LIKE

#### Method: `addCustomCondition(callable $modifier)`

Add complex conditions using a QueryBuilder modifier callback:

```php
$criteria = $context->criteria()
    ->addCustomCondition(function ($qb) {
        $qb->andWhere('e.publishedAt IS NOT NULL')
           ->andWhere('e.publishedAt <= :now')
           ->setParameter('now', new \DateTimeImmutable());
    })
    ->build();
```

**Use cases for custom conditions:**
- Subqueries
- Complex joins
- Database-specific functions
- OR conditions across multiple fields
- Filtering by associations

#### Method: `build()`

Build the final Criteria with all modifications applied:

```php
$criteria = $context->criteria()
    ->addFilter('status', 'eq', 'published')
    ->addCustomCondition(function ($qb) use ($userId) {
        $qb->andWhere('e.author = :userId')
           ->setParameter('userId', $userId);
    })
    ->build();

$slice = $context->getRepository()->findCollection('articles', $criteria);
```

### Complete Example: Multi-Tenant Articles

```php
<?php

namespace App\CustomRoute;

use AlexFigures\Symfony\CustomRoute\Attribute\NoTransaction;
use AlexFigures\Symfony\CustomRoute\Context\CustomRouteContext;
use AlexFigures\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use AlexFigures\Symfony\CustomRoute\Result\CustomRouteResult;

/**
 * Get articles for a specific tenant with full JSON:API query support.
 */
#[NoTransaction]
final class TenantArticlesHandler implements CustomRouteHandlerInterface
{
    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        $tenantId = $context->getParam('tenantId');

        // Verify tenant exists (business logic)
        // ... tenant validation code ...

        // Build criteria with tenant filter + all query string parameters
        $criteria = $context->criteria()
            ->addCustomCondition(function ($qb) use ($tenantId) {
                // Filter by tenant (from path parameter)
                $qb->andWhere('e.tenant = :tenantId')
                   ->setParameter('tenantId', $tenantId);
            })
            ->build();

        // Fetch collection with automatic:
        // - Filtering (from query string + custom condition)
        // - Sorting (from query string)
        // - Pagination (from query string)
        // - Includes (from query string)
        $slice = $context->getRepository()->findCollection('articles', $criteria);

        return CustomRouteResult::collection($slice->items, $slice->totalItems);
    }
}
```

**Supported API Requests:**

```http
# Basic request
GET /api/tenants/123/articles

# With filtering
GET /api/tenants/123/articles?filter[status][eq]=published

# With sorting
GET /api/tenants/123/articles?sort=-createdAt,title

# With pagination
GET /api/tenants/123/articles?page[size]=20&page[number]=2

# Combined
GET /api/tenants/123/articles?filter[status][eq]=published&sort=-createdAt&page[size]=10&include=author
```

### Benefits

1. **No Code Duplication**: Reuse existing filtering/sorting/pagination logic
2. **Consistent API**: All JSON:API query parameters work the same way
3. **Type Safety**: CriteriaBuilder provides a type-safe API
4. **Flexibility**: Combine standard filters with custom business logic
5. **Performance**: Automatic query optimization and eager loading

### Best Practices

1. **Use `addCustomCondition` for associations**: Filtering by relationships requires special handling
2. **Validate path parameters**: Always verify that path parameters (like `categoryId`) are valid
3. **Use `#[NoTransaction]` for read-only handlers**: Improves performance
4. **Return proper totals**: Always pass `$slice->totalItems` to `CustomRouteResult::collection()`
5. **Document expected query parameters**: Use the `description` parameter in `#[JsonApiCustomRoute]`

### Migration from Direct EntityManager Usage

**Before (manual filtering):**
```php
public function handle(CustomRouteContext $context): CustomRouteResult
{
    $categoryId = $context->getParam('categoryId');

    // Manual query - ignores query string parameters!
    $articles = $this->em->getRepository(Article::class)
        ->findBy(['category' => $categoryId]);

    return CustomRouteResult::collection($articles);
}
```

**After (with CriteriaBuilder):**
```php
public function handle(CustomRouteContext $context): CustomRouteResult
{
    $categoryId = $context->getParam('categoryId');

    // Automatic filtering, sorting, pagination from query string
    $criteria = $context->criteria()
        ->addCustomCondition(fn($qb) => $qb->andWhere('e.category = :cat')->setParameter('cat', $categoryId))
        ->build();

    $slice = $context->getRepository()->findCollection('articles', $criteria);

    return CustomRouteResult::collection($slice->items, $slice->totalItems);
}
```
