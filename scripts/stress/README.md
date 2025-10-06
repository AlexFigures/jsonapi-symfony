# Stress Testing & Memory Profiling

Этот каталог содержит скрипты для стресс-тестирования и профилирования памяти JSON:API Bundle.

## Цели

1. **Обнаружение утечек памяти** — проверка что память не растёт монотонно при длительной работе
2. **Проверка стабильности** — отсутствие падений при большом количестве запросов
3. **Профилирование производительности** — выявление узких мест

## Использование

### Базовый запуск

```bash
# Все тесты
make stress

# Только тесты памяти
make stress-mem

# Только тесты производительности
make stress-perf
```

### Прямой запуск скрипта

```bash
php scripts/stress/run.php --profile=mem
php scripts/stress/run.php --profile=perf
php scripts/stress/run.php --profile=all
```

## Тестовые сценарии

### 1. Collection GET with include/fields (1000 итераций)
Проверяет:
- Отсутствие утечек при построении документов с `include`
- Корректную работу sparse fieldsets
- Стабильность DocumentBuilder

### 2. Related/Relationships to-many (500 итераций)
Проверяет:
- Обработку to-many relationships
- Отсутствие N+1 запросов
- Корректную работу LinkageBuilder

### 3. Atomic operations with lid (200 итераций)
Проверяет:
- Транзакционность
- Разрешение Local IDs
- Отсутствие утечек в LidRegistry

### 4. PATCH/DELETE with If-Match (300 итераций)
Проверяет:
- Preconditions (412/428)
- ETag генерацию и валидацию
- Стабильность при конкурентных обновлениях

### 5. Filters with large IN/OR (100 итераций)
Проверяет:
- Обработку сложных фильтров
- Лимиты complexity budget
- Отсутствие SQL injection

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

Результаты сохраняются в `build/stress-report.json`:

```json
{
  "batches": [
    {
      "name": "collections",
      "iteration": 1,
      "memory_usage": 12345678,
      "memory_peak": 12345678,
      "time": 1234567890.123
    }
  ],
  "memory_start": 10000000,
  "memory_peak": 15000000,
  "time_start": 1234567890.0
}
```

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

## Дальнейшие улучшения

- [ ] Интеграция с реальными контроллерами (сейчас только симуляция)
- [ ] Добавить профилирование SQL запросов
- [ ] Визуализация графиков памяти
- [ ] Автоматическое сравнение с baseline
- [ ] Интеграция с php-meminfo для анализа графа удержания

