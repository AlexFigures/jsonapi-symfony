# 🧪 Руководство по тестированию

## Быстрый старт

```bash
# 1. Установить зависимости
composer install

# 2. Запустить юнит и функциональные тесты (без БД)
make test

# 3. Запустить интеграционные тесты с Docker
make docker-test
```

## Типы тестов

### 1. Юнит-тесты (Unit)

Тесты отдельных классов и методов без внешних зависимостей.

```bash
make test-unit
```

**Расположение:** `tests/Unit/`

### 2. Функциональные тесты (Functional)

Тесты с in-memory реализациями, без реальной БД.

```bash
make test-functional
```

**Расположение:** `tests/Functional/`

### 3. Интеграционные тесты (Integration)

Тесты с реальными БД (PostgreSQL, MySQL, MariaDB, SQLite).

```bash
# С Docker (рекомендуется)
make docker-test

# Локально (требует установленных БД)
make test-integration
```

**Расположение:** `tests/Integration/`

### 4. Тесты соответствия спецификации (Conformance)

Snapshot-тесты для проверки соответствия JSON:API спецификации.

```bash
vendor/bin/phpunit --testsuite=Conformance
```

**Расположение:** `tests/Conformance/`

## Docker-окружение

### Запуск

```bash
# Запустить все контейнеры
make docker-up

# Проверить статус
docker-compose -f docker-compose.test.yml ps

# Посмотреть логи
docker-compose -f docker-compose.test.yml logs -f
```

### Доступные БД

После `make docker-up` доступны:

- **PostgreSQL**: `localhost:5432`
- **MySQL**: `localhost:3306`
- **MariaDB**: `localhost:3307`

### Запуск тестов в Docker

```bash
# Все интеграционные тесты
docker-compose -f docker-compose.test.yml exec php vendor/bin/phpunit --testsuite=Integration

# Только PostgreSQL
docker-compose -f docker-compose.test.yml exec php vendor/bin/phpunit tests/Integration/PostgreSQL/

# Конкретный тест
docker-compose -f docker-compose.test.yml exec php vendor/bin/phpunit tests/Integration/PostgreSQL/GenericRepositoryTest.php
```

### Отладка в Docker

```bash
# Открыть shell в PHP-контейнере
make docker-shell

# Внутри контейнера:
php -v
composer --version
vendor/bin/phpunit --version

# Подключиться к PostgreSQL
docker-compose -f docker-compose.test.yml exec postgres psql -U jsonapi -d jsonapi_test
```

### Остановка

```bash
# Остановить и удалить контейнеры + volumes
make docker-down

# Только остановить (сохранить данные)
docker-compose -f docker-compose.test.yml stop
```

## Локальное тестирование (без Docker)

### Требования

- PHP 8.2+
- PostgreSQL 16+ (опционально)
- MySQL 8.0+ (опционально)
- MariaDB 11+ (опционально)

### Настройка

1. Установить БД локально
2. Создать базу данных `jsonapi_test`
3. Настроить переменные окружения в `phpunit.xml.dist`

```xml
<php>
    <env name="DATABASE_URL_POSTGRES" value="postgresql://user:pass@localhost:5432/jsonapi_test"/>
    <env name="DATABASE_URL_MYSQL" value="mysql://user:pass@localhost:3306/jsonapi_test"/>
</php>
```

4. Запустить тесты:

```bash
make test-integration
```

## Проверка качества кода

### Все проверки

```bash
make qa-full
```

Включает:
- PHPUnit (тесты)
- PHPStan (статический анализ)
- Infection (mutation testing)
- Deptrac (архитектурные правила)
- BC Check (обратная совместимость)

### Отдельные проверки

```bash
# Статический анализ
make stan

# Code style
make cs-fix

# Рефакторинг
make rector

# Mutation testing
make mutation

# Архитектурные правила
make deptrac

# Обратная совместимость
make bc-check
```

## Coverage

```bash
# Генерация HTML-отчета
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html build/coverage

# Открыть отчет
open build/coverage/index.html
```

## Отладка тестов

### Запуск одного теста

```bash
vendor/bin/phpunit tests/Integration/PostgreSQL/GenericRepositoryTest.php::testFindCollectionReturnsAllArticles
```

### Вывод отладочной информации

```php
public function testSomething(): void
{
    dump($this->em->getConnection()->getDatabasePlatform()->getName());
    var_dump($article);
    
    // Или используйте PHPUnit assertions
    self::assertSame('expected', 'actual');
}
```

### Остановка на первой ошибке

```bash
vendor/bin/phpunit --stop-on-failure
```

### Фильтрация тестов

```bash
# По имени
vendor/bin/phpunit --filter testCreate

# По группе
vendor/bin/phpunit --group integration
```

## CI/CD

Тесты автоматически запускаются в GitHub Actions при каждом push и PR.

См. `.github/workflows/ci.yml`

## Troubleshooting

### Проблема: "Connection refused" при запуске Docker-тестов

**Решение:**
```bash
# Убедитесь, что контейнеры запущены
docker-compose -f docker-compose.test.yml ps

# Проверьте логи
docker-compose -f docker-compose.test.yml logs postgres

# Подождите, пока БД будут готовы
make docker-up
sleep 10
```

### Проблема: "Table already exists"

**Решение:**
```bash
# Пересоздайте контейнеры
make docker-down
make docker-up
```

### Проблема: Тесты падают с ошибками памяти

**Решение:**
```bash
# Увеличьте memory_limit
php -d memory_limit=512M vendor/bin/phpunit
```

### Проблема: Медленные тесты

**Решение:**
```bash
# Запускайте только нужные тесты
vendor/bin/phpunit --testsuite=Unit

# Или используйте фильтры
vendor/bin/phpunit --filter Repository
```

## Полезные команды

```bash
# Список всех тестов
vendor/bin/phpunit --list-tests

# Список test suites
vendor/bin/phpunit --list-suites

# Запуск с подробным выводом
vendor/bin/phpunit --verbose

# Запуск с отладочной информацией
vendor/bin/phpunit --debug
```

## Дополнительная информация

- [PHPUnit Documentation](https://phpunit.de/)
- [Doctrine ORM Testing](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/testing.html)
- [Docker Compose Documentation](https://docs.docker.com/compose/)

