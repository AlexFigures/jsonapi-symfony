# Troubleshooting Guide

**Version**: 0.1.0  
**Last Updated**: 2025-10-07

---

## Table of Contents

1. [Common Issues](#common-issues)
2. [Installation Problems](#installation-problems)
3. [Configuration Errors](#configuration-errors)
4. [Runtime Errors](#runtime-errors)
5. [Performance Issues](#performance-issues)
6. [Debugging Tips](#debugging-tips)

---

## Common Issues

### 404 Not Found on API Endpoints

**Problem:** Accessing `/api/articles` returns 404.

**Possible Causes:**
1. Routes not registered
2. Resource not tagged correctly
3. Cache not cleared

**Solutions:**

**Check if routes are registered:**
```bash
php bin/console debug:router | grep jsonapi
```

You should see routes like:
```
jsonapi_collection_get    GET    /api/{type}
jsonapi_resource_get      GET    /api/{type}/{id}
```

**Verify resource is tagged:**
```yaml
# config/services.yaml
services:
    App\Entity\Article:
        tags:
            - { name: 'jsonapi.resource', type: 'articles' }
```

**Clear cache:**
```bash
php bin/console cache:clear
```

---

### 406 Not Acceptable

**Problem:** All requests return `406 Not Acceptable`.

**Cause:** Missing or incorrect `Accept` header.

**Solution:**

Always include the JSON:API media type in requests:

```bash
curl -H "Accept: application/vnd.api+json" \
     http://localhost:8000/api/articles
```

If you want to disable strict content negotiation (not recommended):

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    strict_content_negotiation: false
```

---

### 415 Unsupported Media Type

**Problem:** POST/PATCH requests return `415 Unsupported Media Type`.

**Cause:** Missing or incorrect `Content-Type` header.

**Solution:**

Include the correct `Content-Type` header:

```bash
curl -X POST \
     -H "Content-Type: application/vnd.api+json" \
     -H "Accept: application/vnd.api+json" \
     -d '{"data": {...}}' \
     http://localhost:8000/api/articles
```

---

### 500 Internal Server Error

**Problem:** Requests return 500 error with no clear message.

**Cause:** Usually a missing service implementation.

**Common Missing Services:**
- `ResourceRepository` not registered
- `ResourcePersister` not registered (for write operations)
- `TransactionManager` not registered (for write operations)

**Solution:**

Check Symfony logs:
```bash
tail -f var/log/dev.log
```

Verify all required services are registered:

```yaml
# config/services.yaml
services:
    App\Repository\ArticleRepository:
        tags:
            - { name: 'jsonapi.repository', type: 'articles' }
    
    App\Persister\ArticlePersister:
        tags:
            - { name: 'jsonapi.persister', type: 'articles' }
    
    App\Transaction\DoctrineTransactionManager:
        tags:
            - { name: 'jsonapi.transaction_manager' }
```

---

### Empty Response Data

**Problem:** GET requests return `{"data": []}` even though data exists.

**Possible Causes:**
1. Repository not returning data correctly
2. Pagination offset too high
3. Filters excluding all results

**Solutions:**

**Debug repository:**
```php
public function findCollection(string $type, Criteria $criteria): Slice
{
    $items = // ... your query
    
    // Add debug logging
    dump([
        'type' => $type,
        'items_count' => count($items),
        'pagination' => $criteria->pagination,
    ]);
    
    return new Slice(...);
}
```

**Check pagination:**
```bash
# Try first page
curl "http://localhost:8000/api/articles?page[number]=1&page[size]=10"
```

**Disable filters temporarily:**
```bash
# Request without filters
curl "http://localhost:8000/api/articles"
```

---

### Relationships Not Included

**Problem:** `include` parameter doesn't add related resources.

**Possible Causes:**
1. `findRelated()` not implemented
2. Relationship not defined in entity
3. Related resources don't exist

**Solutions:**

**Verify relationship attribute:**
```php
#[JsonApiResource(type: 'articles')]
class Article
{
    #[Relationship(targetType: 'authors')]
    public ?Author $author = null;
}
```

**Implement findRelated:**
```php
public function findRelated(string $type, string $relationship, array $identifiers): iterable
{
    $ids = array_map(fn($id) => $id->id, $identifiers);
    
    return match ($relationship) {
        'author' => $this->em->getRepository(Author::class)->findBy(['id' => $ids]),
        default => [],
    };
}
```

**Test include:**
```bash
curl "http://localhost:8000/api/articles/1?include=author"
```

---

## Installation Problems

### Composer Install Fails

**Problem:** `composer require jsonapi/symfony-jsonapi-bundle` fails.

**Possible Causes:**
1. PHP version too old
2. Symfony version incompatible
3. Conflicting dependencies

**Solutions:**

**Check PHP version:**
```bash
php -v
# Must be 8.2 or higher
```

**Check Symfony version:**
```bash
composer show symfony/framework-bundle
# Must be 7.1 or higher
```

**Update dependencies:**
```bash
composer update
```

---

### Bundle Not Registered

**Problem:** Bundle not loading after installation.

**Solution:**

Manually register the bundle:

```php
// config/bundles.php
return [
    // ... other bundles
    AlexFigures\Symfony\Bridge\Symfony\Bundle\JsonApiBundle::class => ['all' => true],
];
```

---

## Configuration Errors

### Invalid Configuration

**Problem:** Error: "Unrecognized option 'xyz' under 'jsonapi'"

**Cause:** Typo or invalid configuration option.

**Solution:**

Check configuration syntax:

```bash
php bin/console debug:config jsonapi
```

Refer to [Configuration Reference](configuration.md) for valid options.

---

### Configuration Not Applied

**Problem:** Configuration changes don't take effect.

**Solution:**

Clear cache after configuration changes:

```bash
php bin/console cache:clear
```

For development, you can disable cache:

```yaml
# config/packages/dev/framework.yaml
framework:
    cache:
        app: cache.adapter.null
```

---

## Runtime Errors

### 400 Bad Request - Invalid Query Parameter

**Problem:** Requests with query parameters return 400.

**Common Causes:**

**Invalid sort field:**
```bash
# Error: 'price' not in whitelist
curl "http://localhost:8000/api/articles?sort=price"
```

**Solution:** Add field to whitelist:
```yaml
jsonapi:
    sorting:
        whitelist:
            articles: ['title', 'createdAt', 'price']
```

**Invalid include depth:**
```bash
# Error: Exceeds max depth
curl "http://localhost:8000/api/articles?include=author.articles.author.articles"
```

**Solution:** Increase limit or simplify include:
```yaml
jsonapi:
    limits:
        max_include_depth: 5
```

---

### 403 Forbidden - Client-Generated ID

**Problem:** POST with ID returns 403.

**Cause:** Client-generated IDs not allowed for this resource type.

**Solution:**

Enable client-generated IDs:

```yaml
jsonapi:
    write:
        client_generated_ids:
            articles: true
```

Or remove ID from request:

```json
{
  "data": {
    "type": "articles",
    "attributes": {
      "title": "My Article"
    }
  }
}
```

---

### 409 Conflict

**Problem:** POST with ID returns 409.

**Cause:** Resource with that ID already exists.

**Solution:**

Use a different ID or use PATCH to update:

```bash
curl -X PATCH \
     -H "Content-Type: application/vnd.api+json" \
     -d '{"data": {...}}' \
     http://localhost:8000/api/articles/existing-id
```

---

### 412 Precondition Failed

**Problem:** PATCH/DELETE returns 412.

**Cause:** `If-Match` or `If-Unmodified-Since` header doesn't match current resource state.

**Solution:**

Get current ETag:

```bash
curl -I http://localhost:8000/api/articles/1
# Look for: ETag: "abc123"
```

Use correct ETag in update:

```bash
curl -X PATCH \
     -H "If-Match: \"abc123\"" \
     -H "Content-Type: application/vnd.api+json" \
     -d '{"data": {...}}' \
     http://localhost:8000/api/articles/1
```

---

## Performance Issues

### Slow Response Times

**Problem:** API responses are slow.

**Common Causes:**

**N+1 Query Problem:**

Check for N+1 queries in Doctrine:

```yaml
# config/packages/dev/doctrine.yaml
doctrine:
    dbal:
        logging: true
        profiling: true
```

**Solution:** Eager load relationships:

```php
$qb->leftJoin('a.author', 'author')->addSelect('author');
$qb->leftJoin('a.tags', 'tags')->addSelect('tags');
```

**Large Page Sizes:**

Limit maximum page size:

```yaml
jsonapi:
    pagination:
        max_size: 50  # Reduce from 100
```

**Missing Indexes:**

Add database indexes on frequently queried fields:

```php
#[ORM\Index(name: 'idx_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_title', columns: ['title'])]
class Article { }
```

---

### High Memory Usage

**Problem:** PHP runs out of memory.

**Possible Causes:**
1. Loading too many resources at once
2. Circular references in relationships
3. Large included resources

**Solutions:**

**Reduce page size:**
```yaml
jsonapi:
    pagination:
        default_size: 10
        max_size: 25
```

**Limit include depth:**
```yaml
jsonapi:
    limits:
        max_include_depth: 2
```

**Use pagination for relationships:**
```bash
curl "http://localhost:8000/api/articles/1/tags?page[size]=10"
```

---

## Debugging Tips

### Enable Debug Mode

```yaml
# config/packages/dev/jsonapi.yaml
jsonapi:
    debug:
        expose_debug_meta: true
```

Response will include debug info:

```json
{
  "data": [...],
  "meta": {
    "debug": {
      "query_time": "0.045s",
      "memory_usage": "2.5MB"
    }
  }
}
```

**⚠️ Never enable in production!**

---

### Check Service Registration

```bash
# List all JSON:API services
php bin/console debug:container jsonapi

# Check specific service
php bin/console debug:container jsonapi.repository
```

---

### Validate JSON:API Documents

Use online validators:
- [JSON:API Validator](https://jsonapi-validator.herokuapp.com/)
- [JSON Schema Validator](https://www.jsonschemavalidator.net/)

---

### Enable Symfony Profiler

```bash
composer require --dev symfony/profiler-pack
```

Access profiler at: `http://localhost:8000/_profiler`

---

### Check Logs

**Development:**
```bash
tail -f var/log/dev.log
```

**Production:**
```bash
tail -f var/log/prod.log
```

**Filter for errors:**
```bash
grep ERROR var/log/prod.log
```

---

### Test with cURL Verbose Mode

```bash
curl -v \
     -H "Accept: application/vnd.api+json" \
     http://localhost:8000/api/articles
```

Shows full HTTP request/response including headers.

---

### Use HTTP Debugging Tools

**Recommended tools:**
- [Postman](https://www.postman.com/)
- [Insomnia](https://insomnia.rest/)
- [HTTPie](https://httpie.io/)

**HTTPie example:**
```bash
http GET localhost:8000/api/articles \
     Accept:application/vnd.api+json
```

---

## Getting Help

If you're still stuck:

1. **Check existing issues:** [GitHub Issues](https://github.com/AlexFigures/jsonapi-symfony/issues)
2. **Ask in discussions:** [GitHub Discussions](https://github.com/AlexFigures/jsonapi-symfony/discussions)
3. **Read the spec:** [JSON:API Specification](https://jsonapi.org/format/1.1/)
4. **Review examples:** Check `tests/Functional/` for working examples

---

## See Also

- [Getting Started Guide](getting-started.md)
- [Configuration Reference](configuration.md)
- [Advanced Features](advanced-features.md)
- [Public API Reference](../api/public-api.md)

---

**Last Updated**: 2025-10-07

