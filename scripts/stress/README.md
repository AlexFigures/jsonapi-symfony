# Stress Testing & Memory Profiling

Этот каталог содержит скрипты для стресс-тестирования и профилирования памяти JSON:API Bundle.

## Цели

1. **Обнаружение утечек памяти** — проверка что память не растёт монотонно при длительной работе
2. **Проверка стабильности** — отсутствие падений при большом количестве запросов
3. **Профилирование производительности** — выявление узких мест
4. **Реальные HTTP-запросы** — тестирование полного цикла request-response через контроллеры

## Архитектура

### Компоненты

1. **Stress Test Application** (`scripts/stress/app/`)
   - Минимальное Symfony приложение с JsonApiBundle
   - In-memory репозиторий с большим датасетом (1000 Articles, 100 Authors, 500 Tags)
   - Все JSON:API endpoints (collection, resource, relationships, atomic)

2. **HTTP Client** (`scripts/stress/http-client.php`)
   - Простой cURL-based HTTP клиент
   - Поддержка GET, POST, PATCH, DELETE
   - Автоматическая обработка JSON:API Content-Type

3. **Stress Test Runners**
   - `run.php` — оригинальный симуляционный тест (deprecated)
   - `run-http.php` — HTTP-based стресс-тест (рекомендуется)
   - `memory-stress.php` — расширенное профилирование памяти с HTTP

4. **Server** (`scripts/stress/server.php`)
   - Запуск PHP built-in server для stress test app

## Использование

### Быстрый старт

```bash
# Терминал 1: Запустить сервер
php scripts/stress/server.php

# Терминал 2: Запустить стресс-тесты
php scripts/stress/run-http.php --profile=all
```

### HTTP-Based Stress Tests (Рекомендуется)

```bash
# 1. Запустить сервер (в отдельном терминале)
php scripts/stress/server.php [port]

# 2. Запустить стресс-тесты
php scripts/stress/run-http.php --profile=mem
php scripts/stress/run-http.php --profile=perf
php scripts/stress/run-http.php --profile=all

# С кастомным URL сервера
php scripts/stress/run-http.php --server=http://localhost:9000
```

### Memory Profiling

```bash
# 1. Запустить сервер
php scripts/stress/server.php

# 2. Запустить memory stress test
php scripts/stress/memory-stress.php --profile=standard
php scripts/stress/memory-stress.php --profile=quick      # Для CI
php scripts/stress/memory-stress.php --profile=extended   # Глубокий анализ
php scripts/stress/memory-stress.php --iterations=5000    # Кастомное количество
```

### Legacy Simulation Tests (Deprecated)

```bash
# Старые симуляционные тесты (не используют реальные HTTP-запросы)
php scripts/stress/run.php --profile=mem
php scripts/stress/run.php --profile=perf
php scripts/stress/run.php --profile=all
```

## Тестовые сценарии

### HTTP-Based Tests (run-http.php, memory-stress.php)

#### 1. Collection GET with include/fields (1000 итераций)
**Endpoint**: `GET /api/articles?include=author,tags&fields[articles]=title`

Проверяет:
- Отсутствие утечек при построении документов с `include`
- Корректную работу sparse fieldsets
- Стабильность DocumentBuilder
- Реальные HTTP response times
- Полный цикл request-response

#### 2. Resource GET (500 итераций)
**Endpoint**: `GET /api/articles/{id}?include=author,tags`

Проверяет:
- Получение отдельных ресурсов
- Include relationships
- HTTP caching headers
- Response time для single resource

#### 3. Related Resources (300 итераций)
**Endpoint**: `GET /api/articles/{id}/tags`

Проверяет:
- Related resources endpoint
- To-many relationships
- Отсутствие N+1 запросов
- Корректную работу LinkageBuilder

#### 4. Relationships (200 итераций)
**Endpoint**: `GET /api/articles/{id}/relationships/author`

Проверяет:
- Relationships endpoint
- Resource linkage
- To-one и to-many relationships

#### 5. Atomic Operations (100 итераций)
**Endpoint**: `POST /api/operations`

Проверяет:
- Транзакционность
- Разрешение Local IDs
- Отсутствие утечек в LidRegistry
- Atomic operations extension

#### 6. Write Operations (200 итераций)
**Endpoints**: `PATCH /api/articles/{id}`, `DELETE /api/articles/{id}`

Проверяет:
- PATCH/DELETE операции
- Preconditions (If-Match)
- ETag генерацию и валидацию
- Стабильность при конкурентных обновлениях

### Legacy Simulation Tests (run.php)

Старые тесты используют симуляцию Request объектов без реальных HTTP-запросов.
**Не рекомендуется** для обнаружения реальных проблем производительности.

## Метрики

Скрипт собирает следующие метрики:

- **memory_usage** — текущее использование памяти (в байтах)
- **memory_peak** — пиковое использование памяти
- **time** — время выполнения батча
- **growth** — рост памяти между первыми 10% и последними 10% батчей

## Критерии успеха

✅ **Нет утечек памяти:**
- Рост памяти между первыми и последними 10% батчей < 50 MB

✅ **Стабильность:**
- Все батчи выполняются без исключений
- Нет монотонного роста памяти

✅ **Производительность:**
- Среднее время на батч < 100ms (для простых операций)
- Пиковая память < 256 MB

## Отчёты

### HTTP-Based Tests

Результаты сохраняются в `build/stress-report-http.json`:

```json
{
  "batches": [
    {
      "name": "collections",
      "iteration": 1,
      "memory_usage": 12345678,
      "memory_peak": 12345678,
      "http_time": 0.0234,
      "time": 1234567890.123
    }
  ],
  "memory_start": 10000000,
  "memory_peak": 15000000,
  "time_start": 1234567890.0,
  "http_errors": 0
}
```

### Legacy Tests

Результаты сохраняются в `build/stress-report.json` (старый формат без http_time)

## Интеграция с CI

Добавьте в `.github/workflows/qa.yml`:

```yaml
- name: Stress Tests
  run: make stress
  continue-on-error: true  # Не блокировать CI при обнаружении утечек
```

## Профилирование с Blackfire

Для детального профилирования используйте Blackfire:

```bash
blackfire run php scripts/stress/run.php --profile=mem
```

Или XDebug:

```bash
php -d xdebug.mode=profile scripts/stress/run.php --profile=perf
```

## Troubleshooting

### Ошибка "Memory limit exceeded"

Увеличьте лимит памяти:

```bash
php -d memory_limit=512M scripts/stress/run.php
```

### Ложные срабатывания утечек

Проверьте:
1. Достаточно ли итераций (минимум 100)
2. Не включён ли XDebug (отключите для точных измерений)
3. Запускается ли GC регулярно (см. `gc_interval` в конфиге)

### Медленное выполнение

Уменьшите количество батчей в `run.php`:

```php
$config = [
    'batches' => [
        'collections' => 100,  // вместо 1000
        'related' => 50,       // вместо 500
        // ...
    ],
];
```

## Dataset

Stress test application использует расширенный InMemoryRepository с большим датасетом:

- **1000 Articles** — с разными авторами и тегами
- **100 Authors** — распределены по статьям
- **500 Tags** — каждая статья имеет 2-5 тегов

Это позволяет тестировать:
- Pagination с большими коллекциями
- Include с множественными relationships
- N+1 query detection
- Memory usage при больших response documents

## Дальнейшие улучшения

- [x] Интеграция с реальными контроллерами через HTTP
- [x] Большой датасет для реалистичного тестирования
- [ ] Добавить профилирование SQL запросов (для Doctrine adapter)
- [ ] Визуализация графиков памяти
- [ ] Автоматическое сравнение с baseline
- [ ] Интеграция с php-meminfo для анализа графа удержания
- [ ] Docker-based stress test environment
- [ ] Continuous benchmarking в CI

