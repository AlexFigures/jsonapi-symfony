# Routing Features Upgrade Guide

This guide helps you upgrade to the new routing features introduced in jsonapi-symfony: configurable route naming conventions and custom route attributes.

## Overview of New Features

### 1. Route Naming Convention Configuration

You can now configure whether automatically generated routes use `snake_case` (default) or `kebab-case` naming:

```yaml
jsonapi:
    routing:
        naming_convention: 'kebab-case'  # or 'snake_case' (default)
```

### 2. Custom Route Attributes

You can now define custom routes using PHP attributes on entity or controller classes:

```php
#[JsonApiCustomRoute(
    name: 'articles.publish',
    path: '/articles/{id}/publish',
    methods: ['POST'],
    controller: 'App\Controller\PublishController'
)]
```

## Backward Compatibility

**Both features are fully backward compatible:**

- ✅ No configuration changes required
- ✅ Existing route names continue to work
- ✅ No breaking changes to existing APIs
- ✅ Optional features that can be adopted gradually

## Migration Scenarios

### Scenario 1: No Changes Needed

If you're happy with the current `snake_case` route naming and don't need custom routes, **no action is required**. Your application will continue to work exactly as before.

### Scenario 2: Switch to Kebab-Case Naming

If you want to adopt `kebab-case` route naming for new consistency:

#### Step 1: Update Configuration

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    routing:
        naming_convention: 'kebab-case'
```

#### Step 2: Update Route References

Find and update any hardcoded route names in your codebase:

```php
// Before (snake_case)
$url = $this->generateUrl('jsonapi.blog_posts.index');
$url = $this->generateUrl('jsonapi.user_profiles.show', ['id' => $id]);

// After (kebab-case)
$url = $this->generateUrl('jsonapi.blog-posts.index');
$url = $this->generateUrl('jsonapi.user-profiles.show', ['id' => $id]);
```

#### Step 3: Update Frontend/Client Code

Update any JavaScript, templates, or API client code that references route names:

```javascript
// Before
fetch('/api/jsonapi.blog_posts.index')

// After  
fetch('/api/jsonapi.blog-posts.index')
```

#### Step 4: Test Thoroughly

- Run your test suite
- Test all API endpoints
- Verify frontend applications still work
- Check any documentation or API contracts

### Scenario 3: Add Custom Routes

If you want to add custom endpoints beyond standard CRUD operations:

#### Step 1: Identify Custom Endpoints

Determine which custom operations you need:
- Resource actions (publish, archive, activate)
- Search endpoints
- Bulk operations
- Statistics or reports
- Workflow actions

#### Step 2: Choose Implementation Approach

**Option A: On Entity Classes**
```php
#[JsonApiResource(type: 'articles')]
#[JsonApiCustomRoute(
    name: 'articles.publish',
    path: '/articles/{id}/publish',
    methods: ['POST'],
    controller: 'App\Controller\PublishController'
)]
class Article
{
    // ...
}
```

**Option B: On Controller Classes**
```php
#[JsonApiCustomRoute(
    name: 'articles.search',
    path: '/articles/search',
    methods: ['GET'],
    resourceType: 'articles'
)]
class SearchController
{
    // ...
}
```

#### Step 3: Implement Controllers

Create the controller classes to handle your custom routes:

```php
class PublishController
{
    public function __invoke(string $id): Response
    {
        // Implementation
    }
}
```

#### Step 4: Test Custom Routes

Verify your custom routes work correctly:

```bash
# Check routes are registered
php bin/console debug:router | grep articles

# Test the endpoints
curl -X POST /api/articles/123/publish
```

## Common Migration Issues

### Issue 1: Route Name Conflicts

**Problem**: Custom route names conflict with generated routes.

**Solution**: Use descriptive, unique names for custom routes:

```php
// Avoid
#[JsonApiCustomRoute(name: 'articles.show', ...)]

// Prefer
#[JsonApiCustomRoute(name: 'articles.publish', ...)]
#[JsonApiCustomRoute(name: 'articles.actions.archive', ...)]
```

### Issue 2: Complex Route Names Not Transformed

**Problem**: Complex custom route names aren't transformed by naming convention.

**Explanation**: This is intentional. Only canonical `jsonapi.{type}.{action}` patterns are transformed:

```php
// Will be transformed with kebab-case
#[JsonApiCustomRoute(name: 'jsonapi.blog_posts.publish', ...)]
// Becomes: 'jsonapi.blog-posts.publish'

// Will NOT be transformed (preserved exactly)
#[JsonApiCustomRoute(name: 'jsonapi.blog_posts.actions.publish', ...)]
// Stays: 'jsonapi.blog_posts.actions.publish'
```

### Issue 3: Missing Controller Services

**Problem**: Custom route controllers not found.

**Solution**: Ensure controllers are registered as services:

```yaml
# config/services.yaml
services:
    App\Controller\:
        resource: '../src/Controller/'
        tags: ['controller.service_arguments']
```

## Testing Your Migration

### 1. Route Generation Test

```bash
# List all routes to verify naming
php bin/console debug:router | grep jsonapi

# Test specific routes
php bin/console router:match /api/articles
php bin/console router:match /api/articles/123/publish
```

### 2. API Testing

```bash
# Test standard endpoints
curl -X GET /api/articles
curl -X GET /api/articles/123

# Test custom endpoints
curl -X POST /api/articles/123/publish
curl -X GET /api/articles/search?q=test
```

### 3. Integration Tests

Update your integration tests to use the new route names:

```php
public function testArticleIndex(): void
{
    // Before
    $this->client->request('GET', $this->router->generate('jsonapi.blog_posts.index'));
    
    // After (with kebab-case)
    $this->client->request('GET', $this->router->generate('jsonapi.blog-posts.index'));
}
```

## Rollback Plan

If you need to rollback the changes:

### Rollback Naming Convention

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    routing:
        naming_convention: 'snake_case'  # Back to default
```

### Remove Custom Routes

Simply remove the `#[JsonApiCustomRoute]` attributes from your classes. The routes will no longer be generated.

## Best Practices

1. **Gradual Migration**: Adopt features incrementally rather than all at once
2. **Test Thoroughly**: Always test route changes in a staging environment first
3. **Document Changes**: Update your API documentation when changing route names
4. **Consistent Naming**: Choose one naming convention and stick with it
5. **Meaningful Names**: Use descriptive names for custom routes

## Getting Help

If you encounter issues during migration:

1. Check the [Troubleshooting Guide](../guide/troubleshooting.md)
2. Review the [Route Naming Conventions](../guide/routing-naming-conventions.md) guide
3. Read the [Custom Routes](../guide/custom-routes.md) documentation
4. Search [GitHub Issues](https://github.com/AlexFigures/jsonapi-symfony/issues)
5. Ask in [GitHub Discussions](https://github.com/AlexFigures/jsonapi-symfony/discussions)
