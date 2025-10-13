# Data Layer Configuration

## Overview

The JSON:API Symfony bundle provides automatic configuration of data layer services. You no longer need to manually create service aliases in `services.yaml` - the bundle handles this automatically based on your configuration.

## Default Configuration (Doctrine)

By default, the bundle uses Doctrine ORM implementations:

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    data_layer:
        provider: doctrine  # Default
```

This automatically configures:
- `ResourceRepository` → `GenericDoctrineRepository`
- `ResourcePersister` → `ValidatingDoctrinePersister`
- `RelationshipReader` → `GenericDoctrineRelationshipHandler`
- `TransactionManager` → `DoctrineTransactionManager`

**No manual service configuration needed!**

## Custom Implementation

If you want to use custom implementations instead of Doctrine:

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    data_layer:
        provider: custom
        repository: App\JsonApi\Repository\MyCustomRepository
        persister: App\JsonApi\Persister\MyCustomPersister
        relationship_reader: App\JsonApi\Relationship\MyCustomRelationshipReader
        transaction_manager: App\JsonApi\Transaction\MyCustomTransactionManager
```

## Migration from Manual Configuration

### Before (Manual - ❌ Don't do this anymore)

```yaml
# config/services.yaml
services:
    # Manual service registration - NO LONGER NEEDED!
    AlexFigures\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository:
        arguments:
            $em: '@doctrine.orm.default_entity_manager'
            $registry: '@AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface'
            $filterCompiler: '@AlexFigures\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler'

    AlexFigures\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrinePersister:
        arguments:
            $em: '@doctrine.orm.default_entity_manager'
            $registry: '@AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface'
            $accessor: '@Symfony\Component\PropertyAccess\PropertyAccessorInterface'
            $validator: '@validator'

    AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler:
        arguments:
            $em: '@doctrine.orm.default_entity_manager'
            $registry: '@AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface'

    AlexFigures\Symfony\Bridge\Doctrine\Transaction\DoctrineTransactionManager:
        arguments:
            $em: '@doctrine.orm.default_entity_manager'

    # Manual aliases - NO LONGER NEEDED!
    AlexFigures\Symfony\Contract\Data\ResourceRepository:
        alias: AlexFigures\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository

    AlexFigures\Symfony\Contract\Data\ResourcePersister:
        alias: AlexFigures\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrinePersister

    AlexFigures\Symfony\Contract\Data\RelationshipReader:
        alias: AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler

    AlexFigures\Symfony\Contract\Tx\TransactionManager:
        alias: AlexFigures\Symfony\Bridge\Doctrine\Transaction\DoctrineTransactionManager
```

### After (Automatic - ✅ Recommended)

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    data_layer:
        provider: doctrine  # This is the default, can be omitted
```

**That's it!** The bundle automatically:
1. Registers all Doctrine implementation services
2. Configures all necessary aliases
3. Wires all dependencies correctly

## Use Cases

### Use Case 1: Default Doctrine Setup

**Scenario**: You're using Doctrine ORM and want the standard setup.

**Configuration**:
```yaml
# config/packages/jsonapi.yaml
jsonapi:
    route_prefix: '/api'
    # data_layer.provider defaults to 'doctrine'
```

**Result**: All Doctrine implementations are automatically configured.

### Use Case 2: Custom Repository with Doctrine Persister

**Scenario**: You want a custom repository but keep Doctrine for persistence.

**Configuration**:
```yaml
# config/packages/jsonapi.yaml
jsonapi:
    data_layer:
        provider: custom
        repository: App\JsonApi\Repository\CachedRepository
        persister: AlexFigures\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrinePersister
        relationship_reader: AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler
        transaction_manager: AlexFigures\Symfony\Bridge\Doctrine\Transaction\DoctrineTransactionManager
```

### Use Case 3: Completely Custom Implementation

**Scenario**: You're not using Doctrine at all (e.g., using MongoDB, Elasticsearch, or custom storage).

**Configuration**:
```yaml
# config/packages/jsonapi.yaml
jsonapi:
    data_layer:
        provider: custom
        repository: App\JsonApi\MongoDB\MongoRepository
        persister: App\JsonApi\MongoDB\MongoPersister
        relationship_reader: App\JsonApi\MongoDB\MongoRelationshipReader
        transaction_manager: App\JsonApi\MongoDB\MongoTransactionManager
```

**Implementation**:
```php
// src/JsonApi/MongoDB/MongoRepository.php
namespace App\JsonApi\MongoDB;

use AlexFigures\Symfony\Contract\Data\ResourceRepository;
use AlexFigures\Symfony\Contract\Data\Slice;
use AlexFigures\Symfony\Query\Criteria;

final class MongoRepository implements ResourceRepository
{
    public function __construct(
        private MongoClient $client,
    ) {}

    public function findAll(string $type, Criteria $criteria): Slice
    {
        // Your MongoDB implementation
    }

    public function findOne(string $type, string $id): ?object
    {
        // Your MongoDB implementation
    }
}
```

## Configuration Reference

### `data_layer.provider`

**Type**: `string`  
**Default**: `doctrine`  
**Allowed values**: `doctrine`, `custom`

Determines which data layer implementation to use:
- `doctrine`: Use built-in Doctrine ORM implementations
- `custom`: Use custom service IDs specified in other options

### `data_layer.repository`

**Type**: `string|null`  
**Default**: `null`  
**Required when**: `provider: custom`

Service ID of your custom `ResourceRepository` implementation.

**Example**:
```yaml
data_layer:
    provider: custom
    repository: App\JsonApi\Repository\MyRepository
```

### `data_layer.persister`

**Type**: `string|null`  
**Default**: `null`  
**Required when**: `provider: custom`

Service ID of your custom `ResourcePersister` implementation.

**Example**:
```yaml
data_layer:
    provider: custom
    persister: App\JsonApi\Persister\MyPersister
```

### `data_layer.relationship_reader`

**Type**: `string|null`  
**Default**: `null`  
**Required when**: `provider: custom`

Service ID of your custom `RelationshipReader` implementation.

**Example**:
```yaml
data_layer:
    provider: custom
    relationship_reader: App\JsonApi\Relationship\MyRelationshipReader
```

### `data_layer.transaction_manager`

**Type**: `string|null`  
**Default**: `null`  
**Required when**: `provider: custom`

Service ID of your custom `TransactionManager` implementation.

**Example**:
```yaml
data_layer:
    provider: custom
    transaction_manager: App\JsonApi\Transaction\MyTransactionManager
```

## Contracts to Implement

When using `provider: custom`, you need to implement these contracts:

### ResourceRepository

```php
namespace AlexFigures\Symfony\Contract\Data;

interface ResourceRepository
{
    public function findAll(string $type, Criteria $criteria): Slice;
    public function findOne(string $type, string $id): ?object;
}
```

### ResourcePersister

```php
namespace AlexFigures\Symfony\Contract\Data;

interface ResourcePersister
{
    public function create(string $type, object $resource): void;
    public function update(string $type, object $resource): void;
    public function delete(string $type, object $resource): void;
}
```

### RelationshipReader

```php
namespace AlexFigures\Symfony\Contract\Data;

interface RelationshipReader
{
    public function readToOne(object $resource, string $relationshipName): ?object;
    public function readToMany(object $resource, string $relationshipName): iterable;
}
```

### TransactionManager

```php
namespace AlexFigures\Symfony\Contract\Tx;

interface TransactionManager
{
    public function begin(): void;
    public function commit(): void;
    public function rollback(): void;
}
```

## Best Practices

### 1. Use Doctrine by Default

If you're using Doctrine ORM, stick with the default configuration:

```yaml
jsonapi:
    data_layer:
        provider: doctrine
```

### 2. Only Customize What You Need

If you only need to customize one component, you can mix Doctrine and custom:

```yaml
jsonapi:
    data_layer:
        provider: custom
        repository: App\JsonApi\CachedRepository  # Custom
        # Use Doctrine for the rest
        persister: AlexFigures\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrinePersister
        relationship_reader: AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler
        transaction_manager: AlexFigures\Symfony\Bridge\Doctrine\Transaction\DoctrineTransactionManager
```

### 3. Register Custom Services

Make sure your custom services are registered in the container:

```yaml
# config/services.yaml
services:
    App\JsonApi\Repository\CachedRepository:
        arguments:
            $cache: '@cache.app'
            $innerRepository: '@AlexFigures\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository'
```

## Troubleshooting

### Error: "Service not found"

**Problem**: `Service "App\JsonApi\Repository\MyRepository" not found`

**Solution**: Make sure your custom service is registered in `services.yaml`:

```yaml
services:
    App\JsonApi\Repository\MyRepository:
        # ... configuration
```

### Error: "Class does not implement interface"

**Problem**: `Class "App\JsonApi\Repository\MyRepository" must implement "ResourceRepository"`

**Solution**: Ensure your class implements the correct contract:

```php
use AlexFigures\Symfony\Contract\Data\ResourceRepository;

class MyRepository implements ResourceRepository
{
    // ...
}
```

## Summary

- ✅ **Default**: Doctrine implementations are configured automatically
- ✅ **No boilerplate**: No need to create service aliases manually
- ✅ **Flexible**: Easy to switch to custom implementations
- ✅ **Type-safe**: Configuration is validated at container compile time
- ✅ **Convention over configuration**: Works out of the box with sensible defaults

