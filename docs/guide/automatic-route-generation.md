# Автоматическая генерация роутов

JSON:API Bundle автоматически генерирует стандартные CRUD роуты для всех зарегистрированных ресурсов.

## Быстрый старт

### 1. Создайте Entity с атрибутами

```php
// src/Entity/Article.php
use Doctrine\ORM\Mapping as ORM;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\Relationship;

#[ORM\Entity]
#[JsonApiResource(type: 'articles')]
class Article
{
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    #[Id]
    #[Attribute]
    private string $id;
    
    #[ORM\Column]
    #[Attribute]
    private string $title;
    
    #[ORM\ManyToOne(targetEntity: Author::class)]
    #[Relationship(targetType: 'authors')]
    private ?Author $author = null;
    
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[Relationship(targetType: 'tags', toMany: true)]
    private Collection $tags;
    
    // Геттеры/сеттеры...
}
```

### 2. Включите автоматическую генерацию роутов

```yaml
# config/routes.yaml
jsonapi_auto:
    resource: .
    type: jsonapi
```

### 3. Готово!

Автоматически доступны следующие роуты:

#### CRUD операции

```
GET    /api/articles           - список статей
POST   /api/articles           - создание статьи
GET    /api/articles/{id}      - получение статьи
PATCH  /api/articles/{id}      - обновление статьи
DELETE /api/articles/{id}      - удаление статьи
```

#### Relationship операции

```
# To-One relationship (author)
GET    /api/articles/{id}/relationships/author
PATCH  /api/articles/{id}/relationships/author

# To-Many relationship (tags)
GET    /api/articles/{id}/relationships/tags
PATCH  /api/articles/{id}/relationships/tags
POST   /api/articles/{id}/relationships/tags
DELETE /api/articles/{id}/relationships/tags

# Related resources
GET    /api/articles/{id}/author
GET    /api/articles/{id}/tags
```

## Конфигурация

### Изменение префикса роутов

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    route_prefix: /api/v1
```

Теперь роуты будут доступны по адресу `/api/v1/articles`.

### Отключение relationship роутов

Если вы не используете relationships, можно отключить генерацию этих роутов:

```php
// config/services.yaml
JsonApi\Symfony\Bridge\Symfony\Routing\JsonApiRouteLoader:
    arguments:
        $enableRelationshipRoutes: false
```

## Именование роутов

Автоматически сгенерированные роуты имеют следующие имена:

### CRUD роуты

- `jsonapi.{type}.index` - список ресурсов
- `jsonapi.{type}.create` - создание ресурса
- `jsonapi.{type}.show` - получение ресурса
- `jsonapi.{type}.update` - обновление ресурса
- `jsonapi.{type}.delete` - удаление ресурса

### Relationship роуты

- `jsonapi.{type}.relationships.{relationship}.show` - получение relationship
- `jsonapi.{type}.relationships.{relationship}.update` - обновление relationship
- `jsonapi.{type}.relationships.{relationship}.add` - добавление в to-many relationship
- `jsonapi.{type}.relationships.{relationship}.remove` - удаление из to-many relationship

### Related resource роуты

- `jsonapi.{type}.related.{relationship}` - получение связанных ресурсов

### Примеры

```php
// Генерация URL в контроллере
$url = $this->generateUrl('jsonapi.articles.show', ['id' => '123']);
// /api/articles/123

$url = $this->generateUrl('jsonapi.articles.relationships.author.show', ['id' => '123']);
// /api/articles/123/relationships/author

$url = $this->generateUrl('jsonapi.articles.related.tags', ['id' => '123']);
// /api/articles/123/tags
```

## Кастомизация роутов

### Добавление дополнительных роутов

Вы можете добавить дополнительные роуты вручную:

```yaml
# config/routes.yaml
jsonapi_auto:
    resource: .
    type: jsonapi

# Дополнительные кастомные роуты
article_publish:
    path: /api/articles/{id}/publish
    controller: App\Controller\PublishArticleController
    methods: [POST]
```

### Переопределение стандартных роутов

Если вам нужно переопределить стандартный роут, создайте его **до** автоматической генерации:

```yaml
# config/routes.yaml

# Кастомный роут для создания статей
article_create_custom:
    path: /api/articles
    controller: App\Controller\CustomCreateArticleController
    methods: [POST]

# Автоматическая генерация (пропустит уже существующие роуты)
jsonapi_auto:
    resource: .
    type: jsonapi
```

**Важно:** Symfony использует первый подходящий роут, поэтому порядок имеет значение!

### Отключение автоматической генерации для конкретного ресурса

Если вы хотите полностью контролировать роуты для конкретного ресурса, не используйте `#[JsonApiResource]` атрибут или создайте роуты вручную:

```yaml
# config/routes.yaml

# Ручные роуты для products
product_index:
    path: /api/products
    controller: App\Controller\Product\IndexController
    methods: [GET]

product_create:
    path: /api/products
    controller: App\Controller\Product\CreateController
    methods: [POST]

# ... остальные роуты

# Автоматическая генерация для всех остальных ресурсов
jsonapi_auto:
    resource: .
    type: jsonapi
```

## Требования к ID

По умолчанию, параметр `{id}` принимает любые значения (`.+` regex).

Это позволяет использовать:
- UUID: `550e8400-e29b-41d4-a716-446655440000`
- Числа: `123`
- Строки: `my-article-slug`

Если вам нужно ограничить формат ID, переопределите роут:

```yaml
# config/routes.yaml

# Только UUID
article_show_uuid:
    path: /api/articles/{id}
    controller: JsonApi\Symfony\Http\Controller\ShowController
    defaults:
        type: articles
    requirements:
        id: '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'
    methods: [GET]

# Автоматическая генерация для остальных
jsonapi_auto:
    resource: .
    type: jsonapi
```

## Отладка роутов

Посмотреть все сгенерированные роуты:

```bash
php bin/console debug:router | grep jsonapi
```

Посмотреть детали конкретного роута:

```bash
php bin/console debug:router jsonapi.articles.show
```

## Примеры использования

### Минимальный setup

```php
// src/Entity/Product.php
#[ORM\Entity]
#[JsonApiResource(type: 'products')]
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    #[Id]
    #[Attribute]
    private string $id;
    
    #[ORM\Column]
    #[Attribute]
    private string $name;
}
```

```yaml
# config/routes.yaml
jsonapi_auto:
    resource: .
    type: jsonapi
```

```yaml
# config/services.yaml
JsonApi\Symfony\Contract\Data\ResourceRepository:
    alias: JsonApi\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository

JsonApi\Symfony\Contract\Data\ResourcePersister:
    alias: JsonApi\Symfony\Bridge\Doctrine\Persister\GenericDoctrinePersister
```

**Готово!** Все CRUD операции работают:

```bash
# Список продуктов
curl http://localhost/api/products

# Создание продукта
curl -X POST http://localhost/api/products \
  -H "Content-Type: application/vnd.api+json" \
  -d '{"data":{"type":"products","attributes":{"name":"Laptop"}}}'

# Получение продукта
curl http://localhost/api/products/123

# Обновление продукта
curl -X PATCH http://localhost/api/products/123 \
  -H "Content-Type: application/vnd.api+json" \
  -d '{"data":{"type":"products","id":"123","attributes":{"name":"Gaming Laptop"}}}'

# Удаление продукта
curl -X DELETE http://localhost/api/products/123
```

## Troubleshooting

### Роуты не генерируются

1. Проверьте, что `#[JsonApiResource]` атрибут присутствует на Entity
2. Проверьте, что Entity зарегистрирована в ResourceRegistry
3. Проверьте, что `type: jsonapi` указан в routes.yaml
4. Очистите кеш: `php bin/console cache:clear`

### Конфликт роутов

Если вы видите ошибку "Route already exists", это значит, что роут с таким именем уже зарегистрирован.

Решение:
1. Переименуйте ваш кастомный роут
2. Или измените порядок загрузки роутов в routes.yaml

### 404 Not Found

1. Проверьте, что роут существует: `php bin/console debug:router`
2. Проверьте, что контроллер зарегистрирован как сервис
3. Проверьте, что ResourceRepository и ResourcePersister зарегистрированы

## См. также

- [Doctrine Integration](doctrine-integration.md)
- [Validation](validation.md)
- [Relationships](relationships.md)

