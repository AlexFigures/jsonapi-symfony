# 🧪 Testing Guide

## Quick Start

```bash
# 1. Install dependencies
composer install

# 2. Run unit and functional tests (no DB required)
make test

# 3. Execute integration tests inside Docker
make docker-test
```

## Test Types

### 1. Unit Tests

Verify individual classes and methods without external dependencies.

```bash
make test-unit
```

**Location:** `tests/Unit/`

### 2. Functional Tests

Use in-memory implementations without hitting a real database.

```bash
make test-functional
```

**Location:** `tests/Functional/`

### 3. Integration Tests

Run against real databases (PostgreSQL, MySQL, MariaDB, SQLite).

```bash
# With Docker (recommended)
make docker-test

# Locally (requires databases installed)
make test-integration
```

**Location:** `tests/Integration/`

### 4. Conformance Tests

Snapshot suite that ensures compliance with the JSON:API specification.

```bash
vendor/bin/phpunit --testsuite=Conformance
```

**Location:** `tests/Conformance/`

## Docker Environment

### Start Up

```bash
# Start every container
make docker-up

# Check status
docker-compose -f docker-compose.test.yml ps

# Tail logs
docker-compose -f docker-compose.test.yml logs -f
```

### Available Databases

After `make docker-up` you have access to:

- **PostgreSQL**: `localhost:5432`
- **MySQL**: `localhost:3306`
- **MariaDB**: `localhost:3307`

### Running Tests Inside Docker

```bash
# Entire integration suite
docker-compose -f docker-compose.test.yml exec php vendor/bin/phpunit --testsuite=Integration

# PostgreSQL-only tests
docker-compose -f docker-compose.test.yml exec php vendor/bin/phpunit tests/Integration/PostgreSQL/

# Single test
docker-compose -f docker-compose.test.yml exec php vendor/bin/phpunit tests/Integration/PostgreSQL/GenericRepositoryTest.php
```

### Debugging in Docker

```bash
# Open a shell in the PHP container
make docker-shell

# Inside the container:
php -v
composer --version
vendor/bin/phpunit --version

# Connect to PostgreSQL
docker-compose -f docker-compose.test.yml exec postgres psql -U jsonapi -d jsonapi_test
```

### Shutdown

```bash
# Stop and remove containers plus volumes
make docker-down

# Stop only (keep data)
docker-compose -f docker-compose.test.yml stop
```

## Local Testing (without Docker)

### Requirements

- PHP 8.2+
- PostgreSQL 16+ (optional)
- MySQL 8.0+ (optional)
- MariaDB 11+ (optional)

### Setup

1. Install the databases locally.
2. Create a `jsonapi_test` database.
3. Configure environment variables in `phpunit.xml.dist`.

```xml
<php>
    <env name="DATABASE_URL_POSTGRES" value="postgresql://user:pass@localhost:5432/jsonapi_test"/>
    <env name="DATABASE_URL_MYSQL" value="mysql://user:pass@localhost:3306/jsonapi_test"/>
</php>
```

4. Run the tests:

```bash
make test-integration
```

## Quality Checks

### Full QA Suite

```bash
make qa-full
```

Includes:
- PHPUnit (tests)
- PHPStan (static analysis)
- Infection (mutation testing)
- Deptrac (architecture rules)
- BC Check (backward compatibility)

### Individual Checks

```bash
# Static analysis
make stan

# Code style
make cs-fix

# Refactoring
make rector

# Mutation testing
make mutation

# Architecture rules
make deptrac

# Backward compatibility
make bc-check
```

## Coverage

```bash
# Generate HTML report
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html build/coverage

# Open report
open build/coverage/index.html
```

## Test Debugging

### Run a Single Test

```bash
vendor/bin/phpunit tests/Integration/PostgreSQL/GenericRepositoryTest.php::testFindCollectionReturnsAllArticles
```

### Print Debug Information

```php
public function testSomething(): void
{
    dump($this->em->getConnection()->getDatabasePlatform()->getName());
    var_dump($article);
    
    // Or use PHPUnit assertions
    self::assertSame('expected', 'actual');
}
```

### Stop on First Failure

```bash
vendor/bin/phpunit --stop-on-failure
```

### Filter Tests

```bash
# By name
vendor/bin/phpunit --filter testCreate

# By group
vendor/bin/phpunit --group integration
```

## CI/CD

GitHub Actions executes the test suite on every push and pull request.

See `.github/workflows/ci.yml`.

## Troubleshooting

### Issue: "Connection refused" when running Docker tests

**Fix:**
```bash
# Ensure containers are running
docker-compose -f docker-compose.test.yml ps

# Inspect logs
docker-compose -f docker-compose.test.yml logs postgres

# Wait until databases are ready
make docker-up
sleep 10
```

### Issue: "Table already exists"

**Fix:**
```bash
# Recreate containers
make docker-down
make docker-up
```

### Issue: Tests run out of memory

**Fix:**
```bash
# Increase memory_limit
php -d memory_limit=512M vendor/bin/phpunit
```

### Issue: Tests are slow

**Fix:**
```bash
# Run only the tests you need
vendor/bin/phpunit --testsuite=Unit

# Or apply filters
vendor/bin/phpunit --filter Repository
```

## Useful Commands

```bash
# List every test
vendor/bin/phpunit --list-tests

# List suites
vendor/bin/phpunit --list-suites

# Run with verbose output
vendor/bin/phpunit --verbose

# Run with debug information
vendor/bin/phpunit --debug
```

## Additional Resources

- [PHPUnit Documentation](https://phpunit.de/)
- [Doctrine ORM Testing](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/testing.html)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
