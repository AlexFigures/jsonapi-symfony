# CreateResourceController Integration Tests

## Overview

This directory contains integration tests for the `CreateResourceController` that validate JSON:API specification compliance using **real PostgreSQL database connectivity**.

The test suite treats the controller as a **black box** and validates:
- JSON:API request/response format compliance
- HTTP status codes and headers
- Resource creation with various relationship types
- Data persistence in PostgreSQL
- Error handling and validation

## Test Coverage

### Resource Creation Tests

1. **Simple Resource (No Relationships)** - `testCreateSimpleResourceWithNoRelationships()`
   - Entity: `Tag`
   - Validates basic resource creation
   - Verifies 201 Created status, Location header, response structure
   - Confirms data persistence in PostgreSQL

2. **To-One Relationship** - `testCreateResourceWithToOneRelationship()`
   - Entity: `Article` with `Author`
   - Tests `belongsTo` relationship
   - Validates foreign key is set correctly

3. **Many-to-Many Relationship** - `testCreateResourceWithManyToManyRelationships()`
   - Entity: `Article` with multiple `Tags`
   - Tests join table population
   - Verifies multiple relationship items

4. **Self-Referencing Relationship** - `testCreateSelfReferencingResource()`
   - Entity: `Category` with parent `Category`
   - Tests hierarchical/tree structures
   - Validates parent-child relationships

5. **Client-Generated IDs** - `testClientGeneratedIdAllowed()`
   - Entity: `Author` (configured to allow client IDs)
   - Validates client-provided IDs work when allowed

### Error Handling Tests

6. **Missing Content-Type** - `testErrorMissingContentType()`
   - Validates 415 Unsupported Media Type
   - Tests Content-Type header validation

7. **Malformed JSON** - `testErrorMalformedJson()`
   - Validates 400 Bad Request for invalid JSON
   - Tests JSON parsing error handling

8. **Missing Data Member** - `testErrorMissingDataMember()`
   - Validates 400 Bad Request when `data` member is missing
   - Tests JSON:API document structure validation

9. **Type Mismatch** - `testErrorTypeMismatch()`
   - Validates 409 Conflict when resource type doesn't match endpoint
   - Tests type consistency validation

10. **Client ID Not Allowed** - `testErrorClientIdNotAllowed()`
    - Validates 403 Forbidden when client provides ID for disallowed resource type
    - Tests client ID permission enforcement

11. **Unknown Resource Type** - `testErrorUnknownResourceType()`
    - Validates 404 Not Found for non-existent resource types
    - Tests resource type registry validation

## Test Fixtures

The tests use the following Doctrine entities from `tests/Integration/Fixtures/Entity/`:

- **Tag** - Simple entity with no relationships
- **Author** - Entity with one-to-many relationship to Articles
- **Article** - Entity with:
  - To-one relationship to Author
  - Many-to-many relationship to Tags
- **Category** - Self-referencing entity with parent/children relationships

## Prerequisites

### Docker and Docker Compose

**All tests run inside Docker containers.** The project uses `docker-compose.test.yml` which provides:
- PostgreSQL 16 (primary test database)
- MySQL 8.0 (compatibility testing)
- MariaDB 11 (compatibility testing)
- PHP container with all dependencies

No local PHP or database installation is required.

## Running the Tests

> **Important:** All commands must be run via Docker Compose.

### Quick Start

```bash
# 1. Start all services (PostgreSQL, MySQL, MariaDB, PHP)
docker compose -f docker-compose.test.yml up -d

# 2. Wait for databases to be ready (health checks)
sleep 10

# 3. Run the integration tests
docker compose -f docker-compose.test.yml exec -T php vendor/bin/phpunit --testsuite=Integration

# 4. Stop and clean up
docker compose -f docker-compose.test.yml down -v
```

### Using Make Commands

The project provides convenient Make targets:

```bash
# Start services
make docker-up

# Run integration tests
make test-integration

# Run all tests and clean up
make docker-test

# Stop services
make docker-down
```

### Run Only CreateResourceController Tests

```bash
# Start services first
docker compose -f docker-compose.test.yml up -d

# Run specific test file
docker compose -f docker-compose.test.yml exec -T php \
  vendor/bin/phpunit tests/Integration/Http/Controller/CreateResourceControllerTest.php
```

### Run Specific Test Method

```bash
docker compose -f docker-compose.test.yml exec -T php \
  vendor/bin/phpunit \
  --filter testCreateSimpleResourceWithNoRelationships \
  tests/Integration/Http/Controller/CreateResourceControllerTest.php
```

### Run with Coverage

```bash
docker compose -f docker-compose.test.yml exec -T php \
  vendor/bin/phpunit \
  --testsuite=Integration \
  --coverage-html coverage/
```

### Interactive Shell (for debugging)

```bash
# Get a shell inside the PHP container
docker compose -f docker-compose.test.yml exec php bash

# Then run tests interactively
vendor/bin/phpunit tests/Integration/Http/Controller/CreateResourceControllerTest.php
```

## Environment Configuration

The Docker environment automatically configures database connections via environment variables in `docker-compose.test.yml`:

```yaml
environment:
  DATABASE_URL_POSTGRES: "postgresql://jsonapi:secret@postgres:5432/jsonapi_test?serverVersion=16&charset=utf8"
  DATABASE_URL_MYSQL: "mysql://jsonapi:secret@mysql:3306/jsonapi_test?serverVersion=8.0"
  DATABASE_URL_MARIADB: "mysql://jsonapi:secret@mariadb:3306/jsonapi_test?serverVersion=mariadb-11.0.0"
```

Note: Inside Docker, database hostnames are service names (`postgres`, `mysql`, `mariadb`), not `localhost`.

## Test Architecture

### Black Box Testing Approach

The tests treat the controller as a **black box**:
- No mocking of internal dependencies
- Real Doctrine ORM with PostgreSQL
- Real HTTP Request/Response objects
- Validates only public API behavior

### Test Structure

Each test follows this pattern:

1. **Arrange** - Set up test data (create related entities if needed)
2. **Act** - Create JSON:API request and invoke controller
3. **Assert** - Validate:
   - HTTP status code
   - Response headers (Content-Type, Location)
   - Response body structure (JSON:API compliance)
   - Data persistence in PostgreSQL

### Database Isolation

- Each test runs in a clean database state
- `setUp()` creates fresh schema
- `tearDown()` clears all data
- Tests are independent and can run in any order

## JSON:API Specification Compliance

The tests validate compliance with [JSON:API v1.1](https://jsonapi.org/format/):

### Request Format
- Content-Type: `application/vnd.api+json`
- Document structure: `{ "data": { "type": "...", "attributes": {...}, "relationships": {...} } }`

### Response Format (201 Created)
- Content-Type: `application/vnd.api+json`
- Location header with resource URL
- Document structure:
  ```json
  {
    "data": {
      "type": "articles",
      "id": "...",
      "attributes": {...},
      "relationships": {...},
      "links": {
        "self": "http://localhost/api/articles/..."
      }
    }
  }
  ```

### Error Format (4xx/5xx)
- Content-Type: `application/vnd.api+json`
- Document structure:
  ```json
  {
    "errors": [
      {
        "status": "400",
        "title": "Bad Request",
        "detail": "...",
        "source": {
          "pointer": "/data/attributes/..."
        }
      }
    ]
  }
  ```

## Troubleshooting

### Services Not Starting

If `docker compose up` fails:

```bash
# Check service status
docker compose -f docker-compose.test.yml ps

# View logs
docker compose -f docker-compose.test.yml logs postgres
docker compose -f docker-compose.test.yml logs php

# Rebuild containers
docker compose -f docker-compose.test.yml build --no-cache
docker compose -f docker-compose.test.yml up -d
```

### PostgreSQL Connection Issues

If tests fail with connection errors:

1. Verify PostgreSQL is running and healthy:
   ```bash
   docker compose -f docker-compose.test.yml ps postgres
   # Should show "healthy" status
   ```

2. Check PostgreSQL logs:
   ```bash
   docker compose -f docker-compose.test.yml logs postgres
   ```

3. Test connection from PHP container:
   ```bash
   docker compose -f docker-compose.test.yml exec php \
     psql -h postgres -U jsonapi -d jsonapi_test
   # Password: secret
   ```

4. Verify environment variable inside container:
   ```bash
   docker compose -f docker-compose.test.yml exec php \
     printenv DATABASE_URL_POSTGRES
   ```

### Schema Issues

If tests fail with "table does not exist" errors:

- The test automatically creates schema in `setUp()`
- Check Doctrine entity mappings in `tests/Integration/Fixtures/Entity/`
- Verify all entities are registered in `DoctrineIntegrationTestCase::setUp()`
- Try recreating the database:
  ```bash
  docker compose -f docker-compose.test.yml down -v
  docker compose -f docker-compose.test.yml up -d
  ```

### Port Conflicts

If ports are already in use on your host:

1. Edit `docker-compose.test.yml` to change port mappings:
   ```yaml
   ports:
     - "5433:5432"  # Change host port from 5432 to 5433
   ```

2. Restart containers:
   ```bash
   docker compose -f docker-compose.test.yml down
   docker compose -f docker-compose.test.yml up -d
   ```

Note: Port conflicts only affect host access. Tests run inside Docker and use internal networking.

### Permission Issues

If you get permission errors:

```bash
# Fix file permissions
docker compose -f docker-compose.test.yml exec php chown -R $(id -u):$(id -g) /app

# Or run as root
docker compose -f docker-compose.test.yml exec -u root php [command]
```

### Composer Dependencies

If tests fail due to missing dependencies:

```bash
# Install dependencies inside container
docker compose -f docker-compose.test.yml exec php composer install

# Or rebuild the container
docker compose -f docker-compose.test.yml build php
```

## Extending the Tests

To add new test cases:

1. Add test method to `CreateResourceControllerTest`
2. Follow naming convention: `test[Feature][Scenario]()`
3. Add PHPDoc with description and validation points
4. Use `createJsonApiRequest()` helper for requests
5. Use `decode()` helper for response validation
6. Clear database state if needed: `$this->em->clear()`

Example:

```php
/**
 * Test description.
 * 
 * Validates:
 * - Point 1
 * - Point 2
 */
public function testNewFeature(): void
{
    $payload = [
        'data' => [
            'type' => 'articles',
            'attributes' => ['title' => 'Test'],
        ],
    ];

    $request = $this->createJsonApiRequest('POST', '/api/articles', $payload);
    $response = ($this->controller)($request, 'articles');

    self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    // ... more assertions
}
```

## Related Documentation

- [JSON:API Specification](https://jsonapi.org/format/)
- [Project Testing Guide](../../../../TESTING.md)
- [Doctrine ORM Documentation](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)

