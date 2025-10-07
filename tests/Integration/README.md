# Integration Tests with Doctrine ORM

This directory contains integration tests for Doctrine implementations of JSON:API contracts.

## 🎯 Purpose

Testing generic classes for working with Doctrine ORM:
- `GenericDoctrineRepository` - universal repository
- `GenericDoctrinePersister` - universal persister
- `DoctrineTransactionManager` - transaction manager
- `GenericDoctrineRelationshipHandler` - relationship handler (TODO)

## 🐳 Running Tests

### Option 1: With Docker (recommended)

```bash
# Run all integration tests
make docker-test

# Or manually:
# 1. Start Docker environment
make docker-up

# 2. Run tests
docker-compose -f docker-compose.test.yml exec php vendor/bin/phpunit --testsuite=Integration

# 3. Stop Docker environment
make docker-down
```

### Option 2: Locally (requires installed databases)

```bash
# Install PostgreSQL, MySQL, MariaDB locally
# Configure environment variables in phpunit.xml.dist

# Run integration tests
make test-integration

# Or for specific DBMS:
vendor/bin/phpunit tests/Integration/PostgreSQL/
vendor/bin/phpunit tests/Integration/MySQL/
vendor/bin/phpunit tests/Integration/MariaDB/
```

## 📁 Structure

```
tests/Integration/
├── README.md                          # This file
├── docker/                            # Docker configuration
│   ├── Dockerfile                     # PHP with database extensions
│   └── postgres/
│       └── init.sql                   # PostgreSQL initialization
├── Fixtures/
│   └── Entity/                        # Test Doctrine entities
│       ├── Article.php
│       ├── Author.php
│       └── Tag.php
├── DoctrineIntegrationTestCase.php   # Base class for tests
├── PostgreSQL/                        # Tests for PostgreSQL
│   ├── GenericRepositoryTest.php
│   ├── GenericPersisterTest.php
│   └── TransactionTest.php
├── MySQL/                             # Tests for MySQL (TODO)
├── MariaDB/                           # Tests for MariaDB (TODO)
└── SQLite/                            # Tests for SQLite (TODO)
```

## 🧪 Test Coverage

### Current Status

- ✅ GenericDoctrineRepository
  - ✅ findCollection with pagination
  - ✅ findCollection with sorting
  - ✅ findOne
  - ⏳ findRelated (TODO)

- ✅ GenericDoctrinePersister
  - ✅ create with client ID
  - ✅ create with auto-generated ID
  - ✅ update
  - ✅ delete
  - ✅ timestamps (createdAt, updatedAt)
  - ✅ ID conflicts
  - ✅ error handling

- ✅ DoctrineTransactionManager
  - ✅ commit on success
  - ✅ rollback on exception
  - ✅ nested transactions

- ⏳ GenericDoctrineRelationshipHandler (TODO)
  - ⏳ getToOneId
  - ⏳ getToManyIds
  - ⏳ replaceToOne
  - ⏳ replaceToMany
  - ⏳ addToMany
  - ⏳ removeFromToMany

### Target Metrics

- Line Coverage: ≥ 90%
- Branch Coverage: ≥ 85%
- Mutation Score: ≥ 70%

## 🔧 Database Configuration

### PostgreSQL

```bash
Host: localhost
Port: 5432
Database: jsonapi_test
User: jsonapi
Password: secret
```

### MySQL

```bash
Host: localhost
Port: 3306
Database: jsonapi_test
User: jsonapi
Password: secret
```

### MariaDB

```bash
Host: localhost
Port: 3307
Database: jsonapi_test
User: jsonapi
Password: secret
```

### SQLite

```bash
In-memory: sqlite:///:memory:
```

## 📝 Writing New Tests

### Test Template

```php
<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\PostgreSQL;

use JsonApi\Symfony\Tests\Integration\DoctrineIntegrationTestCase;

final class MyTest extends DoctrineIntegrationTestCase
{
    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_POSTGRES']
            ?? 'postgresql://jsonapi:secret@localhost:5432/jsonapi_test?serverVersion=16&charset=utf8';
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    public function testSomething(): void
    {
        // Populate database with test data
        $this->seedDatabase();

        // Execute test
        // ...

        // Verify result
        self::assertSame('expected', 'actual');
    }
}
```

### Available Methods

- `$this->em` - EntityManager
- `$this->registry` - ResourceRegistry
- `$this->repository` - GenericDoctrineRepository
- `$this->persister` - GenericDoctrinePersister
- `$this->transactionManager` - DoctrineTransactionManager
- `$this->accessor` - PropertyAccessor
- `$this->seedDatabase()` - populate database with test data
- `$this->clearDatabase()` - clear database

## 🚀 Next Steps

1. ✅ Create basic infrastructure (Docker, base classes)
2. ✅ Implement GenericDoctrineRepository
3. ✅ Implement GenericDoctrinePersister
4. ✅ Implement DoctrineTransactionManager
5. ⏳ Implement GenericDoctrineRelationshipHandler
6. ⏳ Port tests to MySQL
7. ⏳ Port tests to MariaDB
8. ⏳ Port tests to SQLite
9. ⏳ Add filtering tests
10. ⏳ Add performance tests

## 📚 Additional Information

- [Doctrine ORM Documentation](https://www.doctrine-project.org/projects/orm.html)
- [JSON:API Specification](https://jsonapi.org/)
- [PHPUnit Documentation](https://phpunit.de/)

