# Быстрый старт

Этот гайд покажет, как за 5 минут создать полноценный JSON:API с CRUD операциями, валидацией и relationships.

## Требования

- PHP 8.2+
- Symfony 7.1+
- Doctrine ORM 3.0+

## Установка

```bash
composer require jsonapi/symfony-jsonapi-bundle
```

## Шаг 1: Создайте Entity

```php
// src/Entity/Article.php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\Relationship;
use JsonApi\Symfony\Resource\Attribute\SerializationGroups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'articles')]
#[JsonApiResource(type: 'articles')]
class Article
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    #[Attribute]
    #[Assert\NotBlank]
    private string $content;

    #[ORM\ManyToOne(targetEntity: Author::class)]
    #[Relationship(targetType: 'authors')]
    private ?Author $author = null;

    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[Relationship(targetType: 'tags', toMany: true)]
    private Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    // Геттеры и сеттеры...
    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    public function setAuthor(?Author $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
        return $this;
    }

    public function removeTag(Tag $tag): self
    {
        $this->tags->removeElement($tag);
        return $this;
    }
}
```

## Шаг 2: Настройте сервисы

```yaml
# config/services.yaml
services:
    # Generic Doctrine реализации
    JsonApi\Symfony\Contract\Data\ResourceRepository:
        alias: JsonApi\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository

    JsonApi\Symfony\Contract\Data\ResourcePersister:
        alias: JsonApi\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrinePersister

    JsonApi\Symfony\Contract\Data\RelationshipReader:
        alias: JsonApi\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler

    JsonApi\Symfony\Contract\Data\RelationshipUpdater:
        alias: JsonApi\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler

    JsonApi\Symfony\Contract\Tx\TransactionManager:
        alias: JsonApi\Symfony\Bridge\Doctrine\Transaction\DoctrineTransactionManager
```

## Шаг 3: Включите автоматическую генерацию роутов

```yaml
# config/routes.yaml
jsonapi_auto:
    resource: .
    type: jsonapi
```

## Шаг 4: Настройте бандл (опционально)

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    route_prefix: /api
    atomic:
        enabled: false  # Отключаем Atomic Operations если не нужны
```

## Готово! 🎉

Теперь у вас есть полноценный JSON:API с:

### ✅ CRUD операциями

```bash
# Список статей
GET /api/articles

# Создание статьи
POST /api/articles
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "articles",
    "attributes": {
      "title": "My First Article",
      "content": "This is the content..."
    }
  }
}

# Получение статьи
GET /api/articles/{id}

# Обновление статьи
PATCH /api/articles/{id}
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "articles",
    "id": "{id}",
    "attributes": {
      "title": "Updated Title"
    }
  }
}

# Удаление статьи
DELETE /api/articles/{id}
```

### ✅ Автоматической валидацией

```bash
# Попытка создать статью с коротким заголовком
POST /api/articles
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "articles",
    "attributes": {
      "title": "AB",  # Слишком короткий!
      "content": "Content"
    }
  }
}

# Ответ: 422 Unprocessable Entity
{
  "errors": [
    {
      "status": "422",
      "code": "validation_error",
      "title": "Validation Error",
      "detail": "This value is too short. It should have 3 characters or more.",
      "source": {
        "pointer": "/data/attributes/title"
      }
    }
  ]
}
```

### ✅ Relationships

```bash
# Получение автора статьи
GET /api/articles/{id}/relationships/author

# Установка автора
PATCH /api/articles/{id}/relationships/author
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "authors",
    "id": "author-123"
  }
}

# Добавление тегов
POST /api/articles/{id}/relationships/tags
Content-Type: application/vnd.api+json

{
  "data": [
    { "type": "tags", "id": "tag-1" },
    { "type": "tags", "id": "tag-2" }
  ]
}

# Удаление тега
DELETE /api/articles/{id}/relationships/tags
Content-Type: application/vnd.api+json

{
  "data": [
    { "type": "tags", "id": "tag-1" }
  ]
}

# Получение связанных ресурсов
GET /api/articles/{id}/author
GET /api/articles/{id}/tags
```

### ✅ Пагинацией

```bash
GET /api/articles?page[number]=1&page[size]=10
```

### ✅ Сортировкой

```bash
GET /api/articles?sort=-createdAt,title
```

### ✅ Фильтрацией

```bash
GET /api/articles?filter[title]=Symfony
```

### ✅ Sparse Fieldsets

```bash
GET /api/articles?fields[articles]=title,content
```

### ✅ Include

```bash
GET /api/articles?include=author,tags
```

## Что дальше?

### Кастомизация

Если вам нужна специфичная логика для конкретного ресурса, создайте свой Repository/Persister:

```php
// src/JsonApi/Repository/ArticleRepository.php
namespace App\JsonApi\Repository;

use JsonApi\Symfony\Contract\Data\TypedResourceRepository;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Query\Slice;

final class ArticleRepository implements TypedResourceRepository
{
    public function supports(string $type): bool
    {
        return $type === 'articles';
    }

    public function findCollection(string $type, Criteria $criteria): Slice
    {
        // Ваша специфичная логика
        // Например, фильтрация по статусу, eager loading и т.д.
    }

    // ... остальные методы
}
```

```yaml
# config/services.yaml
App\JsonApi\Repository\ArticleRepository:
    tags:
        - { name: 'jsonapi.repository', priority: 10 }

# Generic repository для всех остальных типов
JsonApi\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository:
    tags:
        - { name: 'jsonapi.repository', priority: 0 }
```

### Дополнительные возможности

- [Автоматическая генерация роутов](automatic-route-generation.md)
- [Doctrine Integration](doctrine-integration.md)
- [Валидация](validation.md)
- [Relationships](relationships.md)
- [Фильтрация](filtering.md)
- [Пагинация](pagination.md)
- [Сортировка](sorting.md)
- [Sparse Fieldsets](sparse-fieldsets.md)
- [Include](include.md)
- [Atomic Operations](atomic-operations.md)
- [Caching](caching.md)
- [Profiles](profiles.md)

## Troubleshooting

### Контейнер падает с ServiceNotFoundException

Убедитесь, что вы зарегистрировали Generic Doctrine реализации в `config/services.yaml`.

Если вы не используете Doctrine, бандл предоставляет NullObject реализации, которые работают "из коробки".

### Валидация не работает

Убедитесь, что вы используете `ValidatingDoctrinePersister` вместо `GenericDoctrinePersister`:

```yaml
JsonApi\Symfony\Contract\Data\ResourcePersister:
    alias: JsonApi\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrinePersister
```

### Роуты не генерируются

1. Проверьте, что `type: jsonapi` указан в `config/routes.yaml`
2. Очистите кеш: `php bin/console cache:clear`
3. Проверьте роуты: `php bin/console debug:router | grep jsonapi`

## Примеры

Полные примеры приложений доступны в репозитории:

- [Simple Blog](https://github.com/jsonapi/symfony-jsonapi-bundle/tree/main/examples/blog)
- [E-commerce](https://github.com/jsonapi/symfony-jsonapi-bundle/tree/main/examples/ecommerce)
- [Multi-tenant SaaS](https://github.com/jsonapi/symfony-jsonapi-bundle/tree/main/examples/saas)

## Поддержка

- [GitHub Issues](https://github.com/jsonapi/symfony-jsonapi-bundle/issues)
- [Discussions](https://github.com/jsonapi/symfony-jsonapi-bundle/discussions)
- [Stack Overflow](https://stackoverflow.com/questions/tagged/jsonapi+symfony)

