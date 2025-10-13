# Migration Guide: From readable/writable to SerializationGroups

## Overview

Starting from version 1.0, the `readable` and `writable` parameters in the `#[Attribute]` attribute are **completely removed**. Instead, you must use the `#[SerializationGroups]` attribute, which provides more flexible and powerful control over serialization and deserialization.

## Why are we doing this?

### Problems with the old approach

1. **Insufficient granularity**: `readable`/`writable` don't allow distinguishing between create (POST) and update (PATCH) operations
2. **Functionality duplication**: Two ways to do the same thing creates confusion
3. **Non-compliance with standards**: Symfony Serializer and API Platform use serialization groups

### Advantages of the new approach

1. ✅ **Granular control**: Separation of `create` and `update` operations
2. ✅ **Following standards**: Compatibility with the Symfony ecosystem
3. ✅ **More powerful**: Support for complex scenarios (write-only fields, create-only fields, etc.)
4. ✅ **Unified approach**: One way to control serialization

## Migration table

| Old syntax | New syntax | Description |
|------------|------------|-------------|
| `#[Attribute(readable: true, writable: true)]` | `#[Attribute]`<br>`#[SerializationGroups(['read', 'write'])]` | Regular field (read and write) |
| `#[Attribute(readable: true, writable: false)]` | `#[Attribute]`<br>`#[SerializationGroups(['read'])]` | Read-only (e.g., timestamps) |
| `#[Attribute(readable: false, writable: true)]` | `#[Attribute]`<br>`#[SerializationGroups(['write'])]` | Write-only (e.g., passwords) |
| `#[Attribute(readable: false, writable: false)]` | Remove attribute | Field not used in API |

## Migration examples

### Example 1: Regular field

**Before:**
```php
use AlexFigures\Symfony\Resource\Attribute\Attribute;

#[Attribute(readable: true, writable: true)]
private string $title;
```

**After:**
```php
use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\SerializationGroups;

#[Attribute]
#[SerializationGroups(['read', 'write'])]
private string $title;
```

### Example 2: Read-only field (timestamp)

**Before:**
```php
#[Attribute(readable: true, writable: false)]
private \DateTimeImmutable $createdAt;
```

**After:**
```php
#[Attribute]
#[SerializationGroups(['read'])]
private \DateTimeImmutable $createdAt;
```

### Example 3: Write-only field (password)

**Before:**
```php
#[Attribute(readable: false, writable: true)]
private string $password;
```

**After:**
```php
#[Attribute]
#[SerializationGroups(['write'])]
private string $password;
```

### Example 4: Complete class migration

**Before:**
```php
use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;

#[JsonApiResource(type: 'users')]
class User
{
    #[Id]
    #[Attribute]
    private string $id;

    #[Attribute(readable: true, writable: true)]
    private string $username;

    #[Attribute(readable: true, writable: true)]
    private string $email;

    #[Attribute(readable: false, writable: true)]
    private string $password;

    #[Attribute(readable: true, writable: false)]
    private \DateTimeImmutable $createdAt;
}
```

**After:**
```php
use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\SerializationGroups;

#[JsonApiResource(type: 'users')]
class User
{
    #[Id]
    #[Attribute]
    #[SerializationGroups(['read'])]
    private string $id;

    #[Attribute]
    #[SerializationGroups(['read', 'write'])]
    private string $username;

    #[Attribute]
    #[SerializationGroups(['read', 'write'])]
    private string $email;

    #[Attribute]
    #[SerializationGroups(['write'])]
    private string $password;

    #[Attribute]
    #[SerializationGroups(['read'])]
    private \DateTimeImmutable $createdAt;
}
```

## New capabilities

### Create-only fields

Fields that can only be set during creation (POST), but cannot be changed during update (PATCH):

```php
#[Attribute]
#[SerializationGroups(['read', 'create'])]
private string $slug;
```

### Update-only fields

Fields that can only be changed during update (PATCH), but cannot be set during creation (POST):

```php
#[Attribute]
#[SerializationGroups(['read', 'update'])]
private string $role = 'user';
```

### Combined scenarios

```php
// Can read, write during creation and update
#[SerializationGroups(['read', 'write'])]
private string $title;

// Can read, write only during creation
#[SerializationGroups(['read', 'create'])]
private string $slug;

// Can read, write only during update
#[SerializationGroups(['read', 'update'])]
private string $status;

// Write only (creation and update)
#[SerializationGroups(['write'])]
private string $password;

// Read only
#[SerializationGroups(['read'])]
private \DateTimeImmutable $createdAt;
```

## Migration automation

You can use the following script to find all uses of the old syntax:

```bash
# Find all files with readable or writable
grep -r "readable:" src/
grep -r "writable:" src/
```

## ⚠️ Breaking Change

The `readable` and `writable` parameters are **completely removed** in version 1.0. Code using these parameters **will not work** and will cause an error:

```
Error: Unknown named parameter $readable
Error: Unknown named parameter $writable
```

You **must** migrate to `#[SerializationGroups]` before upgrading to version 1.0.

## Default behavior

If `#[SerializationGroups]` is not specified, default values are used:

- Attribute is available for reading (equivalent to `read`)
- Attribute is available for writing (equivalent to `write`)

This is equivalent to `#[SerializationGroups(['read', 'write'])]`.

## Frequently Asked Questions

### Do I need to migrate right now?

**Yes!** The `readable` and `writable` parameters are completely removed in version 1.0. Without migration, your code will not work.

### Can I use custom groups?

No, the current version only supports standard groups: `read`, `write`, `create`, `update`.

### How does this affect validation?

Serialization groups are applied **before** validation. If an attribute is excluded due to groups, it will not be validated.

## Additional resources

- [Serialization groups documentation](serialization-groups.md)
- [Usage examples](examples.md)
- [API Reference](../api/attribute.md)

## Support

If you have questions or problems during migration, please:

1. Check the [documentation](serialization-groups.md)
2. Look at [examples](examples.md)
3. Create an issue on GitHub

