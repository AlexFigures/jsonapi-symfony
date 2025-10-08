# Route Naming Conventions

The jsonapi-symfony library automatically generates route names for your JSON:API resources. By default, these routes use `snake_case` naming, but you can configure the library to use `kebab-case` instead.

## Configuration

Add the routing configuration to your `config/packages/jsonapi.yaml` file:

```yaml
jsonapi:
    routing:
        naming_convention: 'kebab-case'  # Options: 'snake_case' (default) or 'kebab-case'
```

## Route Name Examples

### Snake Case (Default)

When using the default `snake_case` convention:

```yaml
jsonapi:
    routing:
        naming_convention: 'snake_case'  # This is the default
```

Generated route names:
- `jsonapi.blog_posts.index` - Collection endpoint
- `jsonapi.blog_posts.show` - Individual resource endpoint
- `jsonapi.blog_posts.create` - Create resource endpoint
- `jsonapi.blog_posts.update` - Update resource endpoint
- `jsonapi.blog_posts.delete` - Delete resource endpoint
- `jsonapi.blog_posts.relationships.author.show` - Relationship endpoint
- `jsonapi.blog_posts.relationships.author.update` - Relationship update endpoint

### Kebab Case

When using `kebab-case` convention:

```yaml
jsonapi:
    routing:
        naming_convention: 'kebab-case'
```

Generated route names:
- `jsonapi.blog-posts.index` - Collection endpoint
- `jsonapi.blog-posts.show` - Individual resource endpoint
- `jsonapi.blog-posts.create` - Create resource endpoint
- `jsonapi.blog-posts.update` - Update resource endpoint
- `jsonapi.blog-posts.delete` - Delete resource endpoint
- `jsonapi.blog-posts.relationships.author.show` - Relationship endpoint
- `jsonapi.blog-posts.relationships.author.update` - Relationship update endpoint

## Backward Compatibility

This feature is fully backward compatible:

- **Default behavior unchanged**: If you don't specify a `naming_convention`, the library continues to use `snake_case`
- **Existing routes preserved**: All existing route names in your application will continue to work
- **No breaking changes**: You can upgrade to this version without any code changes

## Use Cases

### When to Use Snake Case

Snake case is the traditional naming convention and is recommended when:

- You're working with an existing application that already uses snake_case route names
- Your team prefers consistency with PHP naming conventions (variables, methods)
- You want to maintain the historical default behavior

### When to Use Kebab Case

Kebab case is recommended when:

- You're building a new application and prefer URL-friendly naming
- Your frontend applications or API consumers prefer kebab-case identifiers
- You want consistency with modern web standards and REST API conventions
- You're building a public API where route names might be exposed to external consumers

## Route Name Transformation

The library intelligently transforms resource type names based on the configured convention:

| Resource Type | Snake Case Route | Kebab Case Route |
|---------------|------------------|------------------|
| `blogPosts` | `jsonapi.blog_posts.index` | `jsonapi.blog-posts.index` |
| `userProfiles` | `jsonapi.user_profiles.show` | `jsonapi.user-profiles.show` |
| `productCategories` | `jsonapi.product_categories.create` | `jsonapi.product-categories.create` |

## Migration Guide

If you want to migrate from `snake_case` to `kebab-case`:

1. **Update your configuration**:
   ```yaml
   jsonapi:
       routing:
           naming_convention: 'kebab-case'
   ```

2. **Update route references in your code**:
   ```php
   // Before (snake_case)
   $this->generateUrl('jsonapi.blog_posts.index');
   
   // After (kebab-case)
   $this->generateUrl('jsonapi.blog-posts.index');
   ```

3. **Update any hardcoded route names** in templates, JavaScript, or other parts of your application

4. **Test thoroughly** to ensure all route references have been updated

## Custom Routes

Note that this naming convention only affects automatically generated routes for JSON:API resources. Custom routes defined using the `#[JsonApiCustomRoute]` attribute preserve their exact names as specified. See the [Custom Routes Guide](custom-routes.md) for more information.
