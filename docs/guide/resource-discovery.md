# Resource Discovery

## Overview

The JSON:API Symfony bundle automatically discovers resources by scanning configured directories for classes with the `#[JsonApiResource]` attribute. This eliminates the need for manual service registration and follows the same pattern as API Platform and Doctrine ORM.

## How It Works

### Automatic Discovery

At container compile time, the bundle:

1. Scans directories specified in `resource_paths` configuration
2. Finds all PHP classes with the `#[JsonApiResource]` attribute
3. Registers them in the `ResourceRegistry`
4. Generates routes automatically

This means you only need to:
- Add the `#[JsonApiResource]` attribute to your class
- Ensure the class is in a configured directory

**No manual service registration required!**

## Configuration

### Default Configuration

By default, the bundle scans the `src/Entity` directory:

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    route_prefix: '/api'
    # resource_paths defaults to ['%kernel.project_dir%/src/Entity']
```

### Custom Paths

You can configure multiple directories to scan:

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    route_prefix: '/api'
    resource_paths:
        - '%kernel.project_dir%/src/Entity'
        - '%kernel.project_dir%/src/PIM/Entity'
        - '%kernel.project_dir%/src/Domain/Model'
```

## Example

### 1. Define Your Resource

```php
// src/Entity/Product.php
namespace App\Entity;

use AlexFigures\Symfony\Resource\Attribute\{JsonApiResource, Id, Attribute};

#[JsonApiResource(type: 'products')]
class Product
{
    #[Id]
    #[Attribute]
    public string $id;

    #[Attribute]
    public string $name;

    #[Attribute]
    public float $price;
}
```

### 2. That's It!

The resource is automatically discovered and registered. Routes are generated:

- `GET /api/products` - List products
- `GET /api/products/{id}` - Get a product
- `POST /api/products` - Create a product
- `PATCH /api/products/{id}` - Update a product
- `DELETE /api/products/{id}` - Delete a product

## Comparison with Other Approaches

### ✅ Automatic Discovery (Recommended)

```php
#[JsonApiResource(type: 'products')]
class Product { /* ... */ }
```

**Pros:**
- No boilerplate code
- Works with Doctrine entities (non-service classes)
- Follows Symfony conventions
- Similar to API Platform and Doctrine

**Cons:**
- Requires directory scanning at compile time (minimal performance impact)

### ❌ Manual Service Registration (Not Recommended)

```yaml
# config/services.yaml
App\Entity\Product:
    tags:
        - { name: 'jsonapi.resource', type: 'products' }
```

**Pros:**
- Explicit control over which resources are registered

**Cons:**
- Requires manual registration for each resource
- Boilerplate code
- Doesn't work with Doctrine entities (they shouldn't be services)
- Violates Symfony best practices

## How It Compares to Other Bundles

### API Platform

```php
use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Product { /* ... */ }
```

API Platform uses a similar approach with `CompilerPass` to discover resources.

### Doctrine ORM

```php
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Product { /* ... */ }
```

Doctrine scans configured directories for entities with `#[ORM\Entity]` attribute.

### JSON:API Symfony Bundle

```php
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;

#[JsonApiResource(type: 'products')]
class Product { /* ... */ }
```

Our bundle follows the same pattern for consistency and developer experience.

## Technical Details

### ResourceDiscoveryPass

The `ResourceDiscoveryPass` compiler pass:

1. Reads `jsonapi.resource_paths` parameter
2. Uses Symfony Finder to scan directories
3. Extracts class names from PHP files
4. Checks for `#[JsonApiResource]` attribute using Reflection
5. Builds a map of `type => class-string`
6. Updates `ResourceRegistry` with discovered resources

### Performance

- **Compile time**: Scanning happens once during container compilation
- **Runtime**: Zero overhead - resources are cached in the compiled container
- **Development**: Container is rebuilt automatically when you add new resources

### Cache Clearing

When you add a new resource, clear the cache:

```bash
php bin/console cache:clear
```

In development mode (`APP_ENV=dev`), the cache is automatically cleared when files change.

## Troubleshooting

### Resource Not Found

**Problem**: `Unknown resource type "products"`

**Solutions**:

1. **Check the attribute**:
   ```php
   #[JsonApiResource(type: 'products')]  // ✅ Correct
   #[JsonApiResource('products')]        // ❌ Wrong - use named parameter
   ```

2. **Check the directory**:
   ```yaml
   jsonapi:
       resource_paths:
           - '%kernel.project_dir%/src/Entity'  # Make sure your class is here
   ```

3. **Clear the cache**:
   ```bash
   php bin/console cache:clear
   ```

4. **Check the class namespace**:
   ```php
   namespace App\Entity;  // Must match the directory structure
   ```

### Duplicate Resource Type

**Problem**: `Duplicate resource type "products" found in classes App\Entity\Product and App\PIM\Entity\Product`

**Solution**: Each resource type must be unique. Use different types:

```php
#[JsonApiResource(type: 'products')]
class Product { /* ... */ }

#[JsonApiResource(type: 'pim-products')]
class PimProduct { /* ... */ }
```

### Directory Not Scanned

**Problem**: Resources in a custom directory are not discovered

**Solution**: Add the directory to `resource_paths`:

```yaml
jsonapi:
    resource_paths:
        - '%kernel.project_dir%/src/Entity'
        - '%kernel.project_dir%/src/CustomDir'  # Add your directory
```

## Best Practices

### 1. Use Standard Directories

Keep resources in standard locations:
- `src/Entity` for Doctrine entities
- `src/Domain/Model` for DDD models

### 2. One Resource Per File

Each file should contain exactly one resource class.

### 3. Consistent Naming

Use consistent naming for resource types:
- Plural: `products`, `categories`, `users`
- Lowercase: `products` not `Products`
- Kebab-case for compound words: `product-categories`

### 4. Namespace Organization

Organize resources by domain:

```
src/
  Entity/
    Product.php
    Category.php
  PIM/
    Entity/
      Attribute.php
      AttributeGroup.php
```

Configure paths:

```yaml
jsonapi:
    resource_paths:
        - '%kernel.project_dir%/src/Entity'
        - '%kernel.project_dir%/src/PIM/Entity'
```

## Migration from Manual Registration

If you're migrating from manual service registration:

### Before (Manual)

```yaml
# config/services.yaml
services:
    App\Entity\Product:
        tags:
            - { name: 'jsonapi.resource', type: 'products' }
    
    App\Entity\Category:
        tags:
            - { name: 'jsonapi.resource', type: 'categories' }
```

### After (Automatic)

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    resource_paths:
        - '%kernel.project_dir%/src/Entity'
```

Remove the manual registrations from `services.yaml` - they're no longer needed!

## Summary

- ✅ Resources are discovered automatically
- ✅ No manual service registration required
- ✅ Works with Doctrine entities
- ✅ Follows Symfony and API Platform conventions
- ✅ Zero runtime overhead
- ✅ Developer-friendly

