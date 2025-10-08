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

use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\JsonApiCustomRoute;

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

use JsonApi\Symfony\Resource\Attribute\JsonApiCustomRoute;

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
