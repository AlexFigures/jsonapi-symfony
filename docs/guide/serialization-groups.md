# Serialization Groups

Serialization groups control when attributes are readable and writable.

> **⚠️ Breaking Change:** Starting with version 1.0, the `readable` and `writable` options on `#[Attribute]` were removed.
> You must use `#[SerializationGroups]` to manage attribute access.
> See the [Migration Guide](migration-serialization-groups.md) for upgrade details.

## Available Groups

- **`read`** – attribute appears in responses (GET, POST, PATCH).
- **`write`** – attribute can be supplied on create or update (POST, PATCH).
- **`create`** – attribute is writable only during creation (POST).
- **`update`** – attribute is writable only during updates (PATCH).

## Usage Examples

### Standard attribute (read & write)

```php
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\SerializationGroups;

#[Attribute]
#[SerializationGroups(['read', 'write'])]
private string $title;
```

**Behaviour:**
- ✅ Included in GET/POST/PATCH responses.
- ✅ Accepts values during POST.
- ✅ Accepts values during PATCH.

### Read-only attribute

```php
#[Attribute]
#[SerializationGroups(['read'])]
private \DateTimeImmutable $createdAt;
```

**Behaviour:**
- ✅ Included in GET/POST/PATCH responses.
- ❌ Ignored on POST.
- ❌ Ignored on PATCH.

**Typical use:** timestamps, computed fields, automatically generated values.

### Write-only attribute

```php
#[Attribute]
#[SerializationGroups(['write'])]
private string $password;
```

**Behaviour:**
- ❌ Not returned in GET/POST/PATCH responses.
- ✅ Accepts values during POST.
- ✅ Accepts values during PATCH.

**Typical use:** passwords, secrets, confidential data.

### Create-only attribute

```php
#[Attribute]
#[SerializationGroups(['read', 'create'])]
private string $slug;
```

**Behaviour:**
- ✅ Included in GET/POST/PATCH responses.
- ✅ Accepts values during POST.
- ❌ Ignored on PATCH.

**Typical use:** slugs or identifiers that must remain immutable.

### Update-only attribute

```php
#[Attribute]
#[SerializationGroups(['read', 'update'])]
private string $role;
```

**Behaviour:**
- ✅ Included in GET/POST/PATCH responses.
- ❌ Ignored on POST.
- ✅ Accepts values during PATCH.

**Typical use:** fields that administrators adjust after creation.

## Full Example

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

    // Standard attribute: readable + writable
    #[ORM\Column]
    #[Attribute]
    #[SerializationGroups(['read', 'write'])]
    private string $username;

    #[ORM\Column]
    #[Attribute]
    #[SerializationGroups(['read', 'write'])]
    private string $email;

    // Password: write-only, never returned
    #[ORM\Column]
    #[Attribute]
    #[SerializationGroups(['write'])]
    private string $password;

    // Slug: set during creation only
    #[ORM\Column(unique: true)]
    #[Attribute]
    #[SerializationGroups(['read', 'create'])]
    private string $slug;

    // Timestamps: read-only
    #[ORM\Column(type: 'datetime_immutable')]
    #[Attribute]
    #[SerializationGroups(['read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Attribute]
    #[SerializationGroups(['read'])]
    private \DateTimeImmutable $updatedAt;

    // Role: updatable only
    #[ORM\Column]
    #[Attribute]
    #[SerializationGroups(['read', 'update'])]
    private string $role = 'user';

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters and setters...
}
```

## Request Examples

### Create a user (POST)

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
      "role": "admin"  # ❌ Ignored (update-only)
    }
  }
}
```

**Response:**

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
      "role": "user"  # Default value
      # ❌ password is omitted (write-only)
    }
  }
}
```

### Update a user (PATCH)

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
      "slug": "new-slug",  # ❌ Ignored (create-only)
      "role": "admin"      # ✅ Applied (update-only)
    }
  }
}
```

**Response:**

```json
{
  "data": {
    "type": "users",
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "attributes": {
      "username": "john_updated",
      "email": "john@example.com",
      "slug": "john-doe",  # Unchanged
      "createdAt": "2024-01-15T10:30:00Z",
      "updatedAt": "2024-01-15T10:35:00Z",
      "role": "admin"  # Updated value
      # ❌ password is omitted (write-only)
    }
  }
}
```

## Combining Groups

You can mix groups to model complex workflows:

```php
// Readable and writable for create + update
#[SerializationGroups(['read', 'write'])]
private string $title;

// Readable, writable only on create
#[SerializationGroups(['read', 'create'])]
private string $slug;

// Readable, writable only on update
#[SerializationGroups(['read', 'update'])]
private string $status;

// Write-only across create and update
#[SerializationGroups(['write'])]
private string $password;

// Read-only
#[SerializationGroups(['read'])]
private \DateTimeImmutable $createdAt;
```

## Without Serialization Groups

If you omit `#[SerializationGroups]`, the attribute defaults to both `read` and `write`:

```php
#[Attribute]
private string $title; // Equivalent to ['read', 'write']
```

Add groups explicitly whenever you need different behaviour:

```php
#[Attribute]
#[SerializationGroups(['read'])]
private \DateTimeImmutable $createdAt;

#[Attribute]
#[SerializationGroups(['write'])]
private string $password;
```

## Symfony Serializer Integration

JSON:API serialization groups are **independent** from Symfony Serializer groups (`#[Groups]`).

Use both attributes when you need to target Symfony's serializer and the JSON:API bundle at the same time:

```php
use Symfony\Component\Serializer\Annotation\Groups;
use JsonApi\Symfony\Resource\Attribute\SerializationGroups;

#[Attribute]
#[SerializationGroups(['read', 'write'])]  // JSON:API access control
#[Groups(['user:read', 'user:write'])]     // Symfony Serializer profiles
private string $username;
```

## Validation

Serialization groups run **before** validation. If an attribute is skipped because of its groups, validators will not execute.

```php
use Symfony\Component\Validator\Constraints as Assert;

#[Attribute]
#[SerializationGroups(['read'])]  // Read-only
#[Assert\NotBlank]  // Validation is skipped because the attribute is never written
private \DateTimeImmutable $createdAt;
```
