# Интеграционные тесты с Doctrine ORM

Этот каталог содержит интеграционные тесты для Doctrine-реализаций JSON:API контрактов.

## 🎯 Цель

Тестирование генерик-классов для работы с Doctrine ORM:
- `GenericDoctrineRepository` - универсальный репозиторий
- `GenericDoctrinePersister` - универсальный персистер
- `DoctrineTransactionManager` - менеджер транзакций
- `GenericDoctrineRelationshipHandler` - обработчик связей (TODO)

## 🐳 Запуск тестов

### Вариант 1: С Docker (рекомендуется)

```bash
# Запустить все интеграционные тесты
make docker-test

# Или вручную:
# 1. Запустить Docker-окружение
make docker-up

# 2. Запустить тесты
docker-compose -f docker-compose.test.yml exec php vendor/bin/phpunit --testsuite=Integration

# 3. Остановить Docker-окружение
make docker-down
```

### Вариант 2: Локально (требует установленных БД)

```bash
# Установить PostgreSQL, MySQL, MariaDB локально
# Настроить переменные окружения в phpunit.xml.dist

# Запустить интеграционные тесты
make test-integration

# Или для конкретной СУБД:
vendor/bin/phpunit tests/Integration/PostgreSQL/
vendor/bin/phpunit tests/Integration/MySQL/
vendor/bin/phpunit tests/Integration/MariaDB/
```

## 📁 Структура

```
tests/Integration/
├── README.md                          # Этот файл
├── docker/                            # Docker-конфигурация
│   ├── Dockerfile                     # PHP с расширениями для БД
│   └── postgres/
│       └── init.sql                   # Инициализация PostgreSQL
├── Fixtures/
│   └── Entity/                        # Тестовые Doctrine-сущности
│       ├── Article.php
│       ├── Author.php
│       └── Tag.php
├── DoctrineIntegrationTestCase.php   # Базовый класс для тестов
├── PostgreSQL/                        # Тесты для PostgreSQL
│   ├── GenericRepositoryTest.php
│   ├── GenericPersisterTest.php
│   └── TransactionTest.php
├── MySQL/                             # Тесты для MySQL (TODO)
├── MariaDB/                           # Тесты для MariaDB (TODO)
└── SQLite/                            # Тесты для SQLite (TODO)
```

## 🧪 Покрытие тестами

### Текущее состояние

- ✅ GenericDoctrineRepository
  - ✅ findCollection с пагинацией
  - ✅ findCollection с сортировкой
  - ✅ findOne
  - ⏳ findRelated (TODO)

- ✅ GenericDoctrinePersister
  - ✅ create с client ID
  - ✅ create с auto-generated ID
  - ✅ update
  - ✅ delete
  - ✅ timestamps (createdAt, updatedAt)
  - ✅ конфликты ID
  - ✅ обработка ошибок

- ✅ DoctrineTransactionManager
  - ✅ commit при успехе
  - ✅ rollback при исключении
  - ✅ вложенные транзакции

- ⏳ GenericDoctrineRelationshipHandler (TODO)
  - ⏳ getToOneId
  - ⏳ getToManyIds
  - ⏳ replaceToOne
  - ⏳ replaceToMany
  - ⏳ addToMany
  - ⏳ removeFromToMany

### Целевые метрики

- Line Coverage: ≥ 90%
- Branch Coverage: ≥ 85%
- Mutation Score: ≥ 70%

## 🔧 Конфигурация БД

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

## 📝 Написание новых тестов

### Шаблон теста

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
        // Заполняем БД тестовыми данными
        $this->seedDatabase();

        // Выполняем тест
        // ...

        // Проверяем результат
        self::assertSame('expected', 'actual');
    }
}
```

### Доступные методы

- `$this->em` - EntityManager
- `$this->registry` - ResourceRegistry
- `$this->repository` - GenericDoctrineRepository
- `$this->persister` - GenericDoctrinePersister
- `$this->transactionManager` - DoctrineTransactionManager
- `$this->accessor` - PropertyAccessor
- `$this->seedDatabase()` - заполнить БД тестовыми данными
- `$this->clearDatabase()` - очистить БД

## 🚀 Следующие шаги

1. ✅ Создать базовую инфраструктуру (Docker, базовые классы)
2. ✅ Реализовать GenericDoctrineRepository
3. ✅ Реализовать GenericDoctrinePersister
4. ✅ Реализовать DoctrineTransactionManager
5. ⏳ Реализовать GenericDoctrineRelationshipHandler
6. ⏳ Портировать тесты на MySQL
7. ⏳ Портировать тесты на MariaDB
8. ⏳ Портировать тесты на SQLite
9. ⏳ Добавить тесты для фильтрации
10. ⏳ Добавить тесты для производительности

## 📚 Дополнительная информация

- [Doctrine ORM Documentation](https://www.doctrine-project.org/projects/orm.html)
- [JSON:API Specification](https://jsonapi.org/)
- [PHPUnit Documentation](https://phpunit.de/)

