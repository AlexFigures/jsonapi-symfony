# Advanced Features Guide

**Version**: 0.1.0  
**Last Updated**: 2025-10-07

---

## Table of Contents

1. [Profiles (RFC 6906)](#profiles-rfc-6906)
2. [Hooks System](#hooks-system)
3. [Event System](#event-system)
4. [HTTP Caching](#http-caching)
5. [Atomic Operations](#atomic-operations)
6. [Custom Filter Operators](#custom-filter-operators)
7. [Cache Invalidation](#cache-invalidation)

---

## Profiles (RFC 6906)

Profiles allow you to extend JSON:API with custom semantics while maintaining spec compliance.

### What are Profiles?

Profiles are identified by URIs and can modify:
- Query parsing behavior
- Document structure
- Resource serialization
- Write operations

### Creating a Custom Profile

```php
// src/JsonApi/Profile/AuditTrailProfile.php
namespace App\JsonApi\Profile;

use JsonApi\Symfony\Profile\Descriptor\ProfileDescriptor;
use JsonApi\Symfony\Profile\Hook\DocumentHook;
use JsonApi\Symfony\Profile\Hook\WriteHook;
use JsonApi\Symfony\Profile\ProfileContext;
use JsonApi\Symfony\Profile\ProfileInterface;
use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use Symfony\Component\HttpFoundation\Request;

class AuditTrailProfile implements ProfileInterface
{
    public const URI = 'urn:example:audit-trail';

    public function uri(): string
    {
        return self::URI;
    }

    public function descriptor(): ProfileDescriptor
    {
        return new ProfileDescriptor(
            uri: self::URI,
            name: 'Audit Trail',
            version: '1.0',
            documentation: 'https://example.com/docs/audit-trail',
            description: 'Adds audit trail metadata to all resources',
            capabilities: ['document-meta', 'write-hooks']
        );
    }

    public function hooks(): iterable
    {
        yield new AuditTrailDocumentHook();
        yield new AuditTrailWriteHook();
    }
}

// Document hook to add audit metadata
class AuditTrailDocumentHook implements DocumentHook
{
    public function onTopLevelLinks(ProfileContext $context, array &$links, Request $request): void
    {
        // No changes to links
    }

    public function onResourceRelationships(
        ProfileContext $context,
        ResourceMetadata $metadata,
        array &$relationshipsPayload,
        object $model
    ): void {
        // No changes to relationships
    }

    public function onTopLevelMeta(ProfileContext $context, array &$meta): void
    {
        // Add audit information to response meta
        $meta['audit'] = [
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'profile' => self::URI,
        ];
    }
}

// Write hook to log changes
class AuditTrailWriteHook implements WriteHook
{
    public function __construct(
        private AuditLogger $logger,
    ) {}

    public function onBeforeCreate(ProfileContext $context, string $type, ChangeSet $changeSet): void
    {
        $this->logger->log('create', $type, $changeSet->attributes);
    }

    public function onBeforeUpdate(ProfileContext $context, string $type, string $id, ChangeSet $changeSet): void
    {
        $this->logger->log('update', $type, $changeSet->attributes, $id);
    }

    public function onBeforeDelete(ProfileContext $context, string $type, string $id): void
    {
        $this->logger->log('delete', $type, [], $id);
    }
}
```

### Register the Profile

```yaml
# config/services.yaml
services:
    App\JsonApi\Profile\AuditTrailProfile:
        tags:
            - { name: 'jsonapi.profile' }
```

### Enable the Profile

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    profiles:
        enabled_by_default:
            - 'urn:example:audit-trail'
        per_type:
            articles: ['urn:example:audit-trail']
```

### Using Profiles in Requests

Clients can request profiles via the `profile` media type parameter:

```bash
curl -H "Accept: application/vnd.api+json; profile=\"urn:example:audit-trail\"" \
     http://localhost:8000/api/articles
```

---

## Hooks System

Hooks allow you to intercept and modify various stages of request processing.

### Available Hook Types

#### 1. DocumentHook

Modify document structure:

```php
use JsonApi\Symfony\Profile\Hook\DocumentHook;

class CustomDocumentHook implements DocumentHook
{
    public function onTopLevelLinks(ProfileContext $context, array &$links, Request $request): void
    {
        // Add custom links
        $links['custom'] = 'https://example.com/custom';
    }

    public function onResourceRelationships(
        ProfileContext $context,
        ResourceMetadata $metadata,
        array &$relationshipsPayload,
        object $model
    ): void {
        // Modify relationship data
    }

    public function onTopLevelMeta(ProfileContext $context, array &$meta): void
    {
        // Add custom metadata
        $meta['custom_field'] = 'custom_value';
    }
}
```

#### 2. QueryHook

Modify query parsing:

```php
use JsonApi\Symfony\Profile\Hook\QueryHook;
use JsonApi\Symfony\Query\Criteria;

class SoftDeleteQueryHook implements QueryHook
{
    public function onParseQuery(ProfileContext $context, Request $request, Criteria $criteria): void
    {
        // Add filter to exclude soft-deleted items
        // This would integrate with your filter system
    }
}
```

#### 3. ReadHook

Intercept read operations:

```php
use JsonApi\Symfony\Profile\Hook\ReadHook;

class CacheWarmingReadHook implements ReadHook
{
    public function onBeforeFindCollection(ProfileContext $context, string $type, Criteria $criteria): void
    {
        // Warm up cache before fetching collection
    }

    public function onBeforeFindOne(ProfileContext $context, string $type, string $id, Criteria $criteria): void
    {
        // Warm up cache before fetching single resource
    }
}
```

#### 4. WriteHook

Intercept write operations:

```php
use JsonApi\Symfony\Profile\Hook\WriteHook;

class ValidationWriteHook implements WriteHook
{
    public function onBeforeCreate(ProfileContext $context, string $type, ChangeSet $changeSet): void
    {
        // Custom validation before create
    }

    public function onBeforeUpdate(ProfileContext $context, string $type, string $id, ChangeSet $changeSet): void
    {
        // Custom validation before update
    }

    public function onBeforeDelete(ProfileContext $context, string $type, string $id): void
    {
        // Check if deletion is allowed
    }
}
```

#### 5. RelationshipHook

Intercept relationship operations:

```php
use JsonApi\Symfony\Profile\Hook\RelationshipHook;

class RelationshipValidationHook implements RelationshipHook
{
    public function onBeforeRelationshipRead(
        ProfileContext $context,
        string $type,
        string $id,
        string $relationship
    ): void {
        // Validate relationship access
    }
}
```

---

## Event System

The bundle dispatches Symfony events for resource changes.

### Available Events

#### ResourceChangedEvent

Dispatched after resource create/update/delete:

```php
use JsonApi\Symfony\Events\ResourceChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ResourceChangeSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ResourceChangedEvent::class => 'onResourceChanged',
        ];
    }

    public function onResourceChanged(ResourceChangedEvent $event): void
    {
        // $event->type - Resource type (e.g., 'articles')
        // $event->id - Resource ID
        // $event->operation - 'create', 'update', or 'delete'
        
        match ($event->operation) {
            'create' => $this->handleCreate($event->type, $event->id),
            'update' => $this->handleUpdate($event->type, $event->id),
            'delete' => $this->handleDelete($event->type, $event->id),
        };
    }

    private function handleCreate(string $type, string $id): void
    {
        // Send notification, invalidate cache, etc.
    }

    private function handleUpdate(string $type, string $id): void
    {
        // Update search index, invalidate cache, etc.
    }

    private function handleDelete(string $type, string $id): void
    {
        // Clean up related data, invalidate cache, etc.
    }
}
```

#### RelationshipChangedEvent

Dispatched after relationship modifications:

```php
use JsonApi\Symfony\Events\RelationshipChangedEvent;

class RelationshipChangeSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            RelationshipChangedEvent::class => 'onRelationshipChanged',
        ];
    }

    public function onRelationshipChanged(RelationshipChangedEvent $event): void
    {
        // $event->type - Resource type
        // $event->id - Resource ID
        // $event->relationship - Relationship name
        // $event->operation - 'replace', 'add', or 'remove'
        
        // Invalidate cache, update denormalized data, etc.
    }
}
```

### Register Event Subscribers

```yaml
# config/services.yaml
services:
    App\EventSubscriber\ResourceChangeSubscriber:
        tags:
            - { name: 'kernel.event_subscriber' }
```

---

## HTTP Caching

The bundle provides comprehensive HTTP caching support.

### ETag Support

ETags are automatically generated for responses:

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    cache:
        etag:
            enabled: true
            weak_for_collections: true  # Use weak ETags for collections
```

**How it works:**
- Strong ETags for single resources
- Weak ETags for collections (optional)
- Automatic `If-None-Match` handling
- Returns `304 Not Modified` when appropriate

### Last-Modified Support

```yaml
jsonapi:
    cache:
        last_modified:
            enabled: true
```

**Requirements:**
- Your entities must have a `updatedAt` or `modifiedAt` property
- The bundle automatically extracts this from resources

### Conditional Requests

The bundle supports HTTP preconditions:

**If-Match (for updates):**
```bash
curl -X PATCH \
     -H "If-Match: \"abc123\"" \
     -H "Content-Type: application/vnd.api+json" \
     -d '{"data": {...}}' \
     http://localhost:8000/api/articles/1
```

Returns `412 Precondition Failed` if ETag doesn't match.

**If-Unmodified-Since (for updates):**
```bash
curl -X PATCH \
     -H "If-Unmodified-Since: Wed, 21 Oct 2015 07:28:00 GMT" \
     http://localhost:8000/api/articles/1
```

### Surrogate Keys

For CDN/reverse proxy cache invalidation:

```yaml
jsonapi:
    cache:
        surrogate_keys:
            enabled: true
            prefix: 'jsonapi'
```

**Generated headers:**
```
Surrogate-Key: jsonapi:articles jsonapi:articles:1
```

---

## Atomic Operations

Execute multiple operations in a single transaction.

### Enable Atomic Operations

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    atomic:
        enabled: true
        max_operations: 10  # Limit for DoS protection
```

### Example Request

```bash
curl -X POST \
     -H "Content-Type: application/vnd.api+json; ext=\"https://jsonapi.org/ext/atomic\"" \
     -H "Accept: application/vnd.api+json; ext=\"https://jsonapi.org/ext/atomic\"" \
     -d '{
       "atomic:operations": [
         {
           "op": "add",
           "data": {
             "type": "articles",
             "attributes": {
               "title": "First Article"
             }
           }
         },
         {
           "op": "add",
           "data": {
             "type": "articles",
             "attributes": {
               "title": "Second Article"
             }
           }
         }
       ]
     }' \
     http://localhost:8000/api/operations
```

### Local IDs (LID)

Reference resources created in the same request:

```json
{
  "atomic:operations": [
    {
      "op": "add",
      "data": {
        "type": "authors",
        "lid": "author-1",
        "attributes": {
          "name": "Alice"
        }
      }
    },
    {
      "op": "add",
      "data": {
        "type": "articles",
        "attributes": {
          "title": "Article by Alice"
        },
        "relationships": {
          "author": {
            "data": {
              "type": "authors",
              "lid": "author-1"
            }
          }
        }
      }
    }
  ]
}
```

---

## Custom Filter Operators

Extend the filtering system with custom operators.

### Create Custom Operator

```php
// src/JsonApi/Filter/BetweenOperator.php
namespace App\JsonApi\Filter;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use JsonApi\Symfony\Filter\Operator\AbstractOperator;
use JsonApi\Symfony\Filter\Operator\DoctrineExpression;

class BetweenOperator extends AbstractOperator
{
    public function name(): string
    {
        return 'between';
    }

    public function normalizeValues(mixed $raw): array
    {
        // Expect comma-separated values: "10,20"
        if (is_string($raw)) {
            return explode(',', $raw);
        }
        return is_array($raw) ? $raw : [$raw];
    }

    public function compile(
        string $rootAlias,
        string $dqlField,
        array $values,
        AbstractPlatform $platform,
    ): DoctrineExpression {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('Between operator requires exactly 2 values');
        }

        return new DoctrineExpression(
            dql: "{$dqlField} BETWEEN :min AND :max",
            parameters: ['min' => $values[0], 'max' => $values[1]]
        );
    }
}
```

### Register the Operator

```yaml
# config/services.yaml
services:
    App\JsonApi\Filter\BetweenOperator:
        tags:
            - { name: 'jsonapi.filter.operator' }
```

### Use in Requests

```bash
curl "http://localhost:8000/api/articles?filter[price][between]=10,20"
```

---

## Cache Invalidation

Implement cache invalidation for CDNs and reverse proxies.

### Implement SurrogatePurgerInterface

```php
// src/JsonApi/Cache/VarnishPurger.php
namespace App\JsonApi\Cache;

use JsonApi\Symfony\Invalidation\SurrogatePurgerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class VarnishPurger implements SurrogatePurgerInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $varnishUrl,
    ) {}

    public function purge(array $keys): void
    {
        if (empty($keys)) {
            return;
        }

        // Send PURGE request to Varnish
        $this->httpClient->request('PURGE', $this->varnishUrl, [
            'headers' => [
                'X-Surrogate-Key' => implode(' ', $keys),
            ],
        ]);
    }
}
```

### Register the Purger

```yaml
# config/services.yaml
services:
    App\JsonApi\Cache\VarnishPurger:
        arguments:
            $varnishUrl: '%env(VARNISH_URL)%'
        tags:
            - { name: 'jsonapi.surrogate_purger' }
```

### Automatic Invalidation

The bundle automatically invalidates cache on resource changes:

```php
// After update, these keys are purged:
// - jsonapi:articles
// - jsonapi:articles:1
```

---

## See Also

- [Getting Started Guide](getting-started.md)
- [Configuration Reference](configuration.md)
- [Doctrine Integration](integration-doctrine.md)
- [Public API Reference](../api/public-api.md)

---

**Last Updated**: 2025-10-07

