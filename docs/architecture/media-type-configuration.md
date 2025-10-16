# Расширение управления медиа-типами

## Контекст

В данный момент пакет жестко привязан к `application/vnd.api+json`, что отражено как в [конфигурации расширения](../../src/Bridge/Symfony/DependencyInjection/Configuration.php) и параметрах контейнера, так и в проверках `ContentNegotiationSubscriber` и `MediaTypeNegotiator`. Это обеспечивает строгую совместимость с JSON:API, но не оставляет пространства для интеграции дополнительных сценариев (отладочные sandbox-роуты, REST-документация, вспомогательные API, которые принимают `multipart/form-data` и т. п.).

## Цели

- Разрешить задавать несколько наборов медиа-типов, каждый из которых связан с конкретной зоной (основное API, sandbox, документация).
- Сохранить строгую проверку по умолчанию (`application/vnd.api+json`) для основной зоны.
- Сделать расширение полностью управляемым из настроек без необходимости модифицировать контроллеры.
- Предусмотреть расширение на уровне маршрутов (по префиксу или имени) и/или кастомных атрибутов.

## Требования

1. **Конфигурация**
   - Новая секция `jsonapi.media_types` с ключами `default`, `channels`.
   - Канал описывается типом запроса/ответа:
     ```yaml
     jsonapi:
       media_types:
         default:
           request:
             allowed:
               - 'application/vnd.api+json'
           response:
             default: 'application/vnd.api+json'
         channels:
           main:
             scope:
               path_prefix: '^/api/'
             request:
               allowed:
                 - 'application/vnd.api+json'
             response:
               default: 'application/vnd.api+json'
           sandbox:
             scope:
               path_prefix: '^/sandbox'
             request:
               allowed:
                 - 'application/json'
                 - 'multipart/form-data'
             response:
               default: 'application/json'
               negotiable:
                 - 'application/json'
                 - 'text/html'
           docs:
             scope:
               route_name: '^docs_'
             request:
               allowed: ['*']  # любые (чтение)
             response:
               default: 'text/html'
     ```
   - `scope` определяет, к каким запросам применяется канал:
     - `path_prefix` (регулярное выражение для пути)
     - `route_name` (регулярное выражение для имени маршрута)
     - возможность навесить теги на маршруты через атрибут `jsonapi_media_channel`.
   - Если запрос не попадает ни под один канал, используется `default`.

2. **Валидация**
   - Для каждого канала список `allowed` может содержать символ `*`, означающий «нет ограничений».
   - Поддержка отдельных ограничений для `Content-Type` (запрос) и `Accept` (ответ).
   - Секция `response.negotiable` определяет список медиа-типов, которые можно возвращать (для `Accept`), `default` — медиатип по умолчанию.

3. **Расширяемость**
   - Публичный интерфейс `MediaTypePolicyProviderInterface`, который возвращает структуру настроек для текущего запроса.
   - Внедрение по умолчанию читает настройки из контейнера и сопоставляет с запросом.
   - Позволяет заменить провайдер пользовательской реализацией (например, использовать базу данных).

## Предлагаемые изменения

### 1. Конфигурация и контейнер

- Расширить дерево конфигурации `JsonApiExtension`:
  - Добавить `media_types` (arrayNode) с описанной схемой, включая значения по умолчанию.
  - Сгенерировать параметр `jsonapi.media_types` вместо `jsonapi.media_type`.
- Обновить документацию (`docs/guide/configuration.md`) с примерами настройки каналов.

### 2. Слой медиа-политики

- Добавить класс `ChannelScopeMatcher` для сопоставления запроса с каналом по пути, имени маршрута и атрибутам.
- Добавить реализацию `ConfigMediaTypePolicyProvider`:
  - В конструкторе получает массив настроек каналов.
  - В методе `getPolicy(Request $request)` возвращает структуру:
    ```php
    final class MediaTypePolicy
    {
        public function __construct(
            public readonly array $allowedRequestTypes,
            public readonly array $allowedResponseTypes,
            public readonly string $defaultResponseType,
        ) {}
    }
    ```
  - Если канал не найден, возвращается политика `default`.

### 3. Интеграция с существующей проверкой

- Обновить `ContentNegotiationSubscriber`:
  - Инжектить `MediaTypePolicyProviderInterface`.
  - Проверять `Content-Type` запроса на принадлежность к `allowedRequestTypes` (если не `*`).
  - Для ответа использовать `allowedResponseTypes`/`defaultResponseType` при формировании заголовка.
  - Сохранять флаг «строгий JSON:API» для каналов, где `allowedRequestTypes` содержит только `application/vnd.api+json`.
- Обновить `MediaTypeNegotiator` для Atomic Operations, чтобы он опирался на ту же политику.

### 4. API для маршрутов и контроллеров

- Добавить атрибут `#[MediaChannel('sandbox')]`, который можно навешивать на контроллеры/действия. Скопировать значение в атрибут запроса.
- В `Routing` (или в обработчике события `ControllerArguments`) отмечать маршрут выбранным каналом, чтобы `MediaTypePolicyProvider` мог его использовать.

### 5. Обратная совместимость

- Если пользователь продолжает использовать старый ключ `media_type`, он автоматически конвертируется в `media_types.default.response.default` и `request.allowed`.
- Добавить предупреждение (deprecation) в логах при использовании устаревшего ключа.
- Написать тесты для обоих сценариев.

### 6. Тестирование

- Unit-тесты для `ChannelScopeMatcher` (по пути, маршруту, атрибутам).
- Unit-тесты для `ConfigMediaTypePolicyProvider` (выбор канала, wildcard, значения по умолчанию).
- Функциональные тесты для `ContentNegotiationSubscriber`, подтверждающие корректное разрешение:
  - Основное API (JSON:API только).
  - Sandbox с `multipart/form-data`.
  - Docs с `text/html`.

## Этапы внедрения

1. **Подготовка (MVP)**
   - Добавить новую конфигурацию, политику и провайдер.
   - Привести `ContentNegotiationSubscriber` к использованию политики.
   - Обеспечить backward compatibility с существующей настройкой.

2. **Расширение**
   - Добавить атрибуты/теги для привязки каналов к маршрутам.
   - Расширить документацию (гайд, примеры).

3. **Полировка**
   - Обновить `docs/security/checklist.md` и другие разделы с упоминанием строгой проверки.
   - Добавить рецепты в `docs/guide/advanced-features.md` (пример `multipart/form-data`).

Такой подход даст гибкую систему контроля медиа-типов без необходимости модификации каждого контроллера и сохранит строгие гарантии JSON:API для основной зоны.
