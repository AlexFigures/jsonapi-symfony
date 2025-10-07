# Группы сериализации

Группы сериализации позволяют контролировать, когда атрибуты доступны для чтения и записи.

## Доступные группы

- **`read`** - атрибут включается в ответ (GET, POST, PATCH)
- **`write`** - атрибут может быть изменён при создании и обновлении (POST, PATCH)
- **`create`** - атрибут может быть установлен только при создании (POST)
- **`update`** - атрибут может быть изменён только при обновлении (PATCH)

## Примеры использования

### Обычный атрибут (чтение и запись)

```php
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\SerializationGroups;

#[Attribute]
#[SerializationGroups(['read', 'write'])]
private string $title;
```

**Поведение:**
- ✅ Возвращается в GET/POST/PATCH ответах
- ✅ Может быть установлен при POST
- ✅ Может быть изменён при PATCH

### Только для чтения (read-only)

```php
#[Attribute]
#[SerializationGroups(['read'])]
private \DateTimeImmutable $createdAt;
```

**Поведение:**
- ✅ Возвращается в GET/POST/PATCH ответах
- ❌ Игнорируется при POST
- ❌ Игнорируется при PATCH

**Использование:** timestamps, вычисляемые поля, автоматически генерируемые значения.

### Только для записи (write-only)

```php
#[Attribute]
#[SerializationGroups(['write'])]
private string $password;
```

**Поведение:**
- ❌ НЕ возвращается в GET/POST/PATCH ответах
- ✅ Может быть установлен при POST
- ✅ Может быть изменён при PATCH

**Использование:** пароли, секретные ключи, конфиденциальные данные.

### Только при создании (create-only)

```php
#[Attribute]
#[SerializationGroups(['read', 'create'])]
private string $slug;
```

**Поведение:**
- ✅ Возвращается в GET/POST/PATCH ответах
- ✅ Может быть установлен при POST
- ❌ Игнорируется при PATCH

**Использование:** slug, уникальные идентификаторы, которые нельзя изменить после создания.

### Только при обновлении (update-only)

```php
#[Attribute]
#[SerializationGroups(['read', 'update'])]
private string $role;
```

**Поведение:**
- ✅ Возвращается в GET/POST/PATCH ответах
- ❌ Игнорируется при POST
- ✅ Может быть изменён при PATCH

**Использование:** поля, которые должны быть установлены администратором после создания.

## Полный пример

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\SerializationGroups;

#[ORM\Entity]
#[JsonApiResource(type: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    #[Id]
    #[Attribute]
    #[SerializationGroups(['read'])]
    private string $id;

    // Обычный атрибут: чтение и запись
    #[ORM\Column]
    #[Attribute]
    #[SerializationGroups(['read', 'write'])]
    private string $username;

    #[ORM\Column]
    #[Attribute]
    #[SerializationGroups(['read', 'write'])]
    private string $email;

    // Пароль: только запись, никогда не возвращается
    #[ORM\Column]
    #[Attribute]
    #[SerializationGroups(['write'])]
    private string $password;

    // Slug: можно установить только при создании
    #[ORM\Column(unique: true)]
    #[Attribute]
    #[SerializationGroups(['read', 'create'])]
    private string $slug;

    // Timestamps: только чтение
    #[ORM\Column(type: 'datetime_immutable')]
    #[Attribute]
    #[SerializationGroups(['read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Attribute]
    #[SerializationGroups(['read'])]
    private \DateTimeImmutable $updatedAt;

    // Role: можно изменить только при обновлении
    #[ORM\Column]
    #[Attribute]
    #[SerializationGroups(['read', 'update'])]
    private string $role = 'user';

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Геттеры и сеттеры...
}
```

## Примеры запросов

### Создание пользователя (POST)

```bash
POST /api/users
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "users",
    "attributes": {
      "username": "john_doe",
      "email": "john@example.com",
      "password": "secret123",
      "slug": "john-doe",
      "role": "admin"  # ❌ Будет проигнорировано (update-only)
    }
  }
}
```

**Ответ:**

```json
{
  "data": {
    "type": "users",
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "attributes": {
      "username": "john_doe",
      "email": "john@example.com",
      "slug": "john-doe",
      "createdAt": "2024-01-15T10:30:00Z",
      "updatedAt": "2024-01-15T10:30:00Z",
      "role": "user"  # Дефолтное значение
      # ❌ password не возвращается (write-only)
    }
  }
}
```

### Обновление пользователя (PATCH)

```bash
PATCH /api/users/550e8400-e29b-41d4-a716-446655440000
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "users",
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "attributes": {
      "username": "john_updated",
      "password": "newsecret456",
      "slug": "new-slug",  # ❌ Будет проигнорировано (create-only)
      "role": "admin"      # ✅ Будет применено (update-only)
    }
  }
}
```

**Ответ:**

```json
{
  "data": {
    "type": "users",
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "attributes": {
      "username": "john_updated",
      "email": "john@example.com",
      "slug": "john-doe",  # Не изменился
      "createdAt": "2024-01-15T10:30:00Z",
      "updatedAt": "2024-01-15T10:35:00Z",
      "role": "admin"  # Изменился
      # ❌ password не возвращается (write-only)
    }
  }
}
```

## Комбинирование групп

Вы можете комбинировать группы для более сложных сценариев:

```php
// Можно читать и писать при создании и обновлении
#[SerializationGroups(['read', 'write'])]
private string $title;

// Можно читать, писать только при создании
#[SerializationGroups(['read', 'create'])]
private string $slug;

// Можно читать, писать только при обновлении
#[SerializationGroups(['read', 'update'])]
private string $status;

// Только запись (создание и обновление)
#[SerializationGroups(['write'])]
private string $password;

// Только чтение
#[SerializationGroups(['read'])]
private \DateTimeImmutable $createdAt;
```

## Без групп сериализации

Если вы не указываете `#[SerializationGroups]`, используются дефолтные значения из `#[Attribute]`:

```php
// Эквивалентно #[SerializationGroups(['read', 'write'])]
#[Attribute]
private string $title;

// Только чтение
#[Attribute(readable: true, writable: false)]
private \DateTimeImmutable $createdAt;

// Только запись
#[Attribute(readable: false, writable: true)]
private string $password;
```

**Рекомендация:** Используйте `#[SerializationGroups]` для более явного контроля и лучшей читаемости кода.

## Интеграция с Symfony Serializer

Группы сериализации JSON:API Bundle **не связаны** с группами Symfony Serializer (`#[Groups]`).

Если вы используете Symfony Serializer для других целей, вы можете использовать оба атрибута одновременно:

```php
use Symfony\Component\Serializer\Annotation\Groups;
use JsonApi\Symfony\Resource\Attribute\SerializationGroups;

#[Attribute]
#[SerializationGroups(['read', 'write'])]  // Для JSON:API
#[Groups(['user:read', 'user:write'])]     // Для Symfony Serializer
private string $username;
```

## Валидация

Группы сериализации работают **до** валидации. Если атрибут игнорируется из-за групп, он не будет валидироваться.

```php
#[Attribute]
#[SerializationGroups(['read'])]  // Только чтение
#[Assert\NotBlank]  // Валидация не сработает, т.к. атрибут не записывается
private \DateTimeImmutable $createdAt;
```

## Best Practices

### 1. Всегда используйте `write-only` для паролей

```php
#[Attribute]
#[SerializationGroups(['write'])]
private string $password;
```

### 2. Используйте `read-only` для timestamps

```php
#[Attribute]
#[SerializationGroups(['read'])]
private \DateTimeImmutable $createdAt;

#[Attribute]
#[SerializationGroups(['read'])]
private \DateTimeImmutable $updatedAt;
```

### 3. Используйте `create-only` для неизменяемых идентификаторов

```php
#[Attribute]
#[SerializationGroups(['read', 'create'])]
private string $slug;
```

### 4. Используйте `update-only` для административных полей

```php
#[Attribute]
#[SerializationGroups(['read', 'update'])]
private string $status;  // Только админ может изменить после создания
```

### 5. Явно указывайте группы для всех атрибутов

Это делает код более понятным и предсказуемым:

```php
// ✅ Хорошо
#[Attribute]
#[SerializationGroups(['read', 'write'])]
private string $title;

// ❌ Плохо (неявное поведение)
#[Attribute]
private string $title;
```

## Troubleshooting

### Атрибут не записывается

Проверьте, что группа `write`, `create` или `update` указана:

```php
// ❌ Не будет записываться
#[SerializationGroups(['read'])]

// ✅ Будет записываться
#[SerializationGroups(['read', 'write'])]
```

### Атрибут возвращается в ответе, хотя не должен

Проверьте, что группа `read` НЕ указана:

```php
// ❌ Будет возвращаться
#[SerializationGroups(['read', 'write'])]

// ✅ Не будет возвращаться
#[SerializationGroups(['write'])]
```

### Атрибут игнорируется при создании, но работает при обновлении

Проверьте, что используется группа `write` или `create`, а не `update`:

```php
// ❌ Только при обновлении
#[SerializationGroups(['read', 'update'])]

// ✅ При создании и обновлении
#[SerializationGroups(['read', 'write'])]

// ✅ Только при создании
#[SerializationGroups(['read', 'create'])]
```

## См. также

- [Validation](validation.md)
- [Doctrine Integration](doctrine-integration.md)
- [Attributes](attributes.md)

