# Naming Convention Configuration - Clarification

## Overview

The `jsonapi.routing.naming_convention` configuration option controls **route names** (internal Symfony route identifiers), **NOT** the URL paths themselves.

## What Does `naming_convention` Affect?

### ✅ Affects: Route Names (Internal Identifiers)

The `naming_convention` setting transforms how Symfony route names are generated:

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    routing:
        naming_convention: 'kebab-case'  # or 'snake_case' (default)
```

**With `snake_case` (default):**
- Route name: `jsonapi.blog_posts.index`
- Route name: `jsonapi.user_profiles.show`

**With `kebab-case`:**
- Route name: `jsonapi.blog-posts.index`
- Route name: `jsonapi.user-profiles.show`

### ❌ Does NOT Affect: URL Paths

URL paths are **always** determined by the resource type as defined in the entity metadata:

```php
#[JsonApiResource(type: 'blog_posts')]  // URL: /api/blog_posts
class BlogPost {}

#[JsonApiResource(type: 'blog-posts')]  // URL: /api/blog-posts
class BlogPost {}
```

## Real-World Example

Given this entity:

```php
#[JsonApiResource(type: 'category_synonyms')]
class CategorySynonym {
    // ...
}
```

### With `naming_convention: 'kebab-case'`

```bash
$ bin/console debug:router | grep category
jsonapi.category-synonyms.index      GET    /api/category_synonyms
jsonapi.category-synonyms.create     POST   /api/category_synonyms
jsonapi.category-synonyms.show       GET    /api/category_synonyms/{id}
```

**Notice:**
- Route **names** use kebab-case: `jsonapi.category-synonyms.index`
- URL **paths** use the original resource type: `/api/category_synonyms`

### With `naming_convention: 'snake_case'` (default)

```bash
$ bin/console debug:router | grep category
jsonapi.category_synonyms.index      GET    /api/category_synonyms
jsonapi.category_synonyms.create     POST   /api/category_synonyms
jsonapi.category_synonyms.show       GET    /api/category_synonyms/{id}
```

**Notice:**
- Route **names** use snake_case: `jsonapi.category_synonyms.index`
- URL **paths** still use the original resource type: `/api/category_synonyms`

## Why This Design?

1. **JSON:API Specification Compliance**: The JSON:API spec requires that resource types in URLs match the `type` field in JSON documents. Changing URL paths based on a routing convention would break this requirement.

2. **Backward Compatibility**: Existing APIs with established URL structures can adopt different route naming conventions without breaking client integrations.

3. **Flexibility**: Teams can choose route naming conventions that match their internal standards while maintaining stable public APIs.

## How to Control URL Paths

To change URL paths, modify the resource type in the entity metadata:

```php
// URLs will use snake_case
#[JsonApiResource(type: 'blog_posts')]
class BlogPost {}

// URLs will use kebab-case
#[JsonApiResource(type: 'blog-posts')]
class BlogPost {}

// URLs will use camelCase
#[JsonApiResource(type: 'blogPosts')]
class BlogPost {}
```

## OpenAPI Documentation

The OpenAPI specification generator correctly reflects this behavior:

- **Paths** in the OpenAPI spec use the resource type as-is from entity metadata
- **Operation IDs** are generated using StudlyCase transformation of the resource type
- **Tags** use the original resource type format

Example OpenAPI output for `type: 'blog-posts'`:

```json
{
  "paths": {
    "/api/blog-posts": {
      "get": {
        "operationId": "listBlogPosts",
        "tags": ["blog-posts"]
      }
    }
  }
}
```

## Testing

The test suite includes comprehensive coverage:

1. **Unit Tests** (`tests/Unit/Docs/OpenApi/OpenApiSpecGeneratorTest.php`):
   - `testHandlesKebabCaseResourceTypes()` - Verifies kebab-case resource types
   - `testHandlesSnakeCaseResourceTypes()` - Verifies snake_case resource types

2. **Functional Tests** (`tests/Functional/Docs/OpenApiControllerTest.php`):
   - `testCustomRoutesPreserveResourceTypeFormat()` - Verifies custom routes preserve resource type format

All tests confirm that URL paths use the resource type format, independent of the `naming_convention` configuration.

## Migration Considerations

If you're migrating from `snake_case` to `kebab-case` route names:

1. **Update route references in your code:**
   ```php
   // Before
   $this->generateUrl('jsonapi.blog_posts.index');
   
   // After
   $this->generateUrl('jsonapi.blog-posts.index');
   ```

2. **Your API URLs remain unchanged** - no client-side changes needed

3. **Update any route-based ACL or security rules** that reference route names

## Summary

| Aspect | Controlled By | Example |
|--------|---------------|---------|
| **Route Names** | `jsonapi.routing.naming_convention` | `jsonapi.blog-posts.index` |
| **URL Paths** | Resource type in entity metadata | `/api/blog_posts` |
| **OpenAPI Paths** | Resource type in entity metadata | `/api/blog_posts` |
| **OpenAPI Operation IDs** | Generated from resource type | `listBlogPosts` |
| **OpenAPI Tags** | Resource type in entity metadata | `blog_posts` |

**Key Takeaway**: The `naming_convention` config is purely for internal Symfony routing convenience and does not affect your public API structure.

