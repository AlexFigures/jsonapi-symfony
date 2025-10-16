# ExistenceChecker Guide

**Version**: 0.4.0  
**Last Updated**: 2025-10-16

---

## Table of Contents

1. [Overview](#overview)
2. [Why ExistenceChecker?](#why-existencechecker)
3. [Automatic Configuration](#automatic-configuration)
4. [Manual Configuration](#manual-configuration)
5. [Custom Implementation](#custom-implementation)
6. [Troubleshooting](#troubleshooting)

---

## Overview

The `ExistenceChecker` interface is used to verify if a JSON:API resource exists without loading it into memory. This is essential for:

- **Relationship validation**: Checking if related resources exist before creating relationships
- **Performance**: Avoiding full entity hydration when only existence matters
- **Error handling**: Providing accurate 404 responses for missing resources

---

## Why ExistenceChecker?

When you modify relationships using JSON:API endpoints like:

```http
PATCH /api/articles/123/relationships/author
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "authors",
    "id": "456"
  }
}
```

The bundle needs to verify that:
1. The article with ID `123` exists
2. The author with ID `456` exists

Without an `ExistenceChecker`, you'll get this error:

```json
{
  "errors": [{
    "status": "501",
    "title": "Not Implemented",
    "detail": "Existence checking is not implemented for type \"authors\". Please provide your own implementation of ExistenceChecker."
  }]
}
```

---

## Automatic Configuration

**Since version 0.4.0**, the bundle automatically provides a Doctrine implementation when using the Doctrine data layer.

### Configuration

In your `config/packages/jsonapi.yaml`:

```yaml
jsonapi:
    data_layer:
        provider: doctrine  # This is the default
```

That's it! The `DoctrineExistenceChecker` is automatically registered and used.

### What It Does

The `DoctrineExistenceChecker`:
- Uses efficient `COUNT(*)` queries
- Avoids loading entities into memory
- Works with all ID types (string, int, UUID)
- Handles unknown resource types gracefully

---

## Manual Configuration

If you need to override the default implementation:

```yaml
# config/services.yaml
services:
    # Override the default ExistenceChecker
    AlexFigures\Symfony\Contract\Data\ExistenceChecker:
        class: App\JsonApi\MyCustomExistenceChecker
        arguments:
            - '@doctrine.orm.entity_manager'
            - '@AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface'
```

---

## Custom Implementation

### Example: Redis-Backed Existence Checker

For high-performance scenarios, you might cache existence checks in Redis:

```php
// src/JsonApi/ExistenceChecker/CachedExistenceChecker.php
namespace App\JsonApi\ExistenceChecker;

use AlexFigures\Symfony\Contract\Data\ExistenceChecker;
use Psr\Cache\CacheItemPoolInterface;

final class CachedExistenceChecker implements ExistenceChecker
{
    public function __construct(
        private readonly ExistenceChecker $inner,
        private readonly CacheItemPoolInterface $cache,
        private readonly int $ttl = 60,
    ) {
    }

    public function exists(string $type, string $id): bool
    {
        $cacheKey = sprintf('resource_exists_%s_%s', $type, $id);
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return (bool) $item->get();
        }

        $exists = $this->inner->exists($type, $id);

        $item->set($exists);
        $item->expiresAfter($this->ttl);
        $this->cache->save($item);

        return $exists;
    }
}
```

Register it:

```yaml
# config/services.yaml
services:
    App\JsonApi\ExistenceChecker\CachedExistenceChecker:
        decorates: AlexFigures\Symfony\Contract\Data\ExistenceChecker
        arguments:
            $inner: '@.inner'
            $cache: '@cache.app'
            $ttl: 300  # 5 minutes
```

### Example: Multi-Tenant Existence Checker

For multi-tenant applications:

```php
// src/JsonApi/ExistenceChecker/TenantAwareExistenceChecker.php
namespace App\JsonApi\ExistenceChecker;

use AlexFigures\Symfony\Contract\Data\ExistenceChecker;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;

final class TenantAwareExistenceChecker implements ExistenceChecker
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ResourceRegistryInterface $registry,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function exists(string $type, string $id): bool
    {
        if (!$this->registry->hasType($type)) {
            return false;
        }

        $metadata = $this->registry->getByType($type);
        $entityClass = $metadata->dataClass;
        $classMetadata = $this->em->getClassMetadata($entityClass);
        $identifierField = $classMetadata->getSingleIdentifierFieldName();

        $qb = $this->em->createQueryBuilder();
        $qb->select('COUNT(e.' . $identifierField . ')')
            ->from($entityClass, 'e')
            ->where('e.' . $identifierField . ' = :id')
            ->andWhere('e.tenantId = :tenantId')  // Add tenant filter
            ->setParameter('id', $id)
            ->setParameter('tenantId', $this->tenantContext->getCurrentTenantId());

        $count = (int) $qb->getQuery()->getSingleScalarResult();

        return $count > 0;
    }
}
```

---

## Troubleshooting

### Error: "Existence checking is not implemented"

**Cause**: You're using `data_layer.provider: custom` or an older version of the bundle.

**Solution**: Either:
1. Upgrade to version 0.4.0+ and use `data_layer.provider: doctrine`
2. Implement your own `ExistenceChecker` (see examples above)

### Performance Issues

**Symptom**: Slow relationship operations

**Solutions**:
1. Add database indexes on ID columns
2. Use the cached implementation (see example above)
3. Consider using database-level existence checks

### Multi-Database Setup

If you have multiple entity managers:

```yaml
# config/services.yaml
services:
    app.existence_checker.main:
        class: AlexFigures\Symfony\Bridge\Doctrine\ExistenceChecker\DoctrineExistenceChecker
        arguments:
            - '@doctrine.orm.main_entity_manager'
            - '@AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface'

    app.existence_checker.analytics:
        class: AlexFigures\Symfony\Bridge\Doctrine\ExistenceChecker\DoctrineExistenceChecker
        arguments:
            - '@doctrine.orm.analytics_entity_manager'
            - '@AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface'

    # Use a composite checker
    AlexFigures\Symfony\Contract\Data\ExistenceChecker:
        class: App\JsonApi\ExistenceChecker\CompositeExistenceChecker
        arguments:
            - '@app.existence_checker.main'
            - '@app.existence_checker.analytics'
```

---

## See Also

- [Doctrine Integration Guide](integration-doctrine.md)
- [Relationship Operations](relationships.md)
- [Public API Reference](../api/public-api.md#existencechecker)

