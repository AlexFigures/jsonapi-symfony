# Entity Constructor Support

## Overview

The JSON:API Symfony bundle now supports entities with constructors that require parameters, similar to how API Platform handles entity instantiation. This feature is powered by the `EntityInstantiator` service, which intelligently analyzes constructor parameters and maps them from the incoming JSON:API request.

## The Problem

Doctrine's `ClassMetadata::newInstance()` method does not invoke the entity constructor. This causes issues when:

1. Your entity constructor requires parameters
2. Your entity initializes properties (like UUIDs) in the constructor
3. Your entity uses Value Object patterns with immutable properties

### Example Problem

```php
#[ORM\Entity]
#[JsonApiResource(type: 'countries')]
class Country
{
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $uuid;

    public function __construct(
        string $nameEn,
        string $nameRu,
        bool $isActive = true,
    ) {
        $this->uuid = Uuid::v7();  // ← Never called!
        $this->nameEn = $nameEn;
        $this->nameRu = $nameRu;
        $this->isActive = $isActive;
    }
}
```

When creating this entity via the API, you would get:
```
SQLSTATE[23502]: Not null violation: null value in column "uuid"
```

## The Solution

The `EntityInstantiator` service:

1. **Analyzes** the constructor using reflection
2. **Extracts** parameter values from the JSON:API `ChangeSet`
3. **Generates** default values for missing parameters
4. **Invokes** the constructor with the correct parameters
5. **Returns** remaining attributes to be set via setters

## How It Works

### Step 1: Constructor Analysis

```php
public function __construct(
    string $nameEn,        // ← Required parameter
    string $nameRu,        // ← Required parameter
    bool $isActive = true, // ← Optional parameter with default
) {
    $this->uuid = Uuid::v7();
    // ...
}
```

### Step 2: Parameter Mapping

The instantiator maps JSON:API attributes to constructor parameters:

**JSON:API Request:**
```json
{
  "data": {
    "type": "countries",
    "attributes": {
      "nameEn": "Germany",
      "nameRu": "Germaniya",
      "isActive": true
    }
  }
}
```

**Parameter Mapping:**
- `nameEn` → constructor parameter `$nameEn`
- `nameRu` → constructor parameter `$nameRu`
- `isActive` → constructor parameter `$isActive`

### Step 3: Entity Creation

```php
// EntityInstantiator calls:
$entity = new Country(
    nameEn: 'Germany',
    nameRu: 'Germaniya',
    isActive: true
);

// Now $entity->uuid is set to Uuid::v7()!
```

## Supported Features

### 1. Required Parameters

```php
public function __construct(
    string $name,  // ← Must be provided in JSON:API request
) {}
```

### 2. Optional Parameters with Defaults

```php
public function __construct(
    string $name,
    bool $isActive = true,  // ← Uses default if not provided
) {}
```

### 3. Nullable Parameters

```php
public function __construct(
    string $name,
    ?string $description = null,  // ← Can be null
) {}
```

### 4. Type-Based Default Generation

If a required parameter is missing from the request, the instantiator generates a default value based on the type:

| Type | Default Value |
|------|---------------|
| `string` | `''` (empty string) |
| `int` | `0` |
| `float` | `0.0` |
| `bool` | `false` |
| `array` | `[]` |
| `ArrayCollection` | `new ArrayCollection()` |
| `Uuid` | `Uuid::v7()` |
| nullable | `null` |

### 5. Property Path Mapping

The instantiator respects `propertyPath` from attribute metadata:

```php
#[JsonApiAttribute(readable: true, writable: true, propertyPath: 'name_en')]
private string $nameEn;
```

## SerializationGroups Support

The instantiator fully supports `SerializationGroups` attribute:

```php
#[JsonApiResource(type: 'users')]
class User
{
    #[Attribute]
    #[SerializationGroups(['read', 'write'])]
    private string $username;

    #[Attribute]
    #[SerializationGroups(['write'])]  // Only for write, never returned
    private string $password;

    #[Attribute]
    #[SerializationGroups(['read', 'create'])]  // Can only be set on creation
    private string $slug;

    #[Attribute]
    #[SerializationGroups(['read', 'update'])]  // Can only be set on update
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct(
        string $username,
        string $password,
        string $slug,
    ) {
        $this->uuid = Uuid::v7();
        $this->username = $username;
        $this->password = password_hash($password, PASSWORD_DEFAULT);
        $this->slug = $slug;
        $this->createdAt = new \DateTimeImmutable();
    }
}
```

### How It Works

**On CREATE (POST):**
- Attributes with `write` or `create` groups are passed to constructor
- Attributes with only `update` group are ignored

**On UPDATE (PATCH):**
- Attributes with `write` or `update` groups are applied
- Attributes with only `create` group are ignored

This ensures that:
- Passwords are never returned in responses
- Slugs can only be set during creation
- Update timestamps are only set during updates

## Configuration

The `SerializerEntityInstantiator` is automatically registered and used by:

- `ValidatingDoctrinePersister`
- `GenericDoctrinePersister`

No additional configuration is required!

## Best Practices

### 1. Use Constructor for Invariants

```php
public function __construct(
    string $nameEn,
    string $nameRu,
) {
    if ($nameEn === '') {
        throw new \InvalidArgumentException('Name cannot be empty');
    }
    
    $this->uuid = Uuid::v7();
    $this->nameEn = $nameEn;
    $this->nameRu = $nameRu;
    $this->createdAt = new \DateTimeImmutable();
}
```

### 2. Use Setters for Mutable Properties

```php
public function __construct(
    string $nameEn,
    string $nameRu,
) {
    // Immutable properties set in constructor
    $this->uuid = Uuid::v7();
    $this->nameEn = $nameEn;
    $this->nameRu = $nameRu;
}

public function setIsActive(bool $isActive): void
{
    // Mutable property set via setter
    $this->isActive = $isActive;
}
```

### 3. Initialize Collections in Constructor

```php
public function __construct(
    string $name,
) {
    $this->name = $name;
    $this->brands = new ArrayCollection();  // ← Always initialize
    $this->manufacturers = new ArrayCollection();
}
```

### 4. Generate UUIDs in Constructor

```php
public function __construct(
    string $name,
) {
    $this->uuid = Uuid::v7();  // ← Generated automatically
    $this->name = $name;
}
```

## Comparison with API Platform

This implementation is inspired by API Platform's approach:

| Feature | API Platform | This Bundle |
|---------|-------------|-------------|
| Constructor parameter mapping | ✅ | ✅ |
| Type-based default generation | ✅ | ✅ |
| Property path mapping | ✅ | ✅ |
| Doctrine integration | ✅ | ✅ |
| Validation integration | ✅ | ✅ |

## Migration Guide

### From Constructor-less Entities

**Before:**
```php
class Country
{
    private ?Uuid $uuid = null;
    private ?string $nameEn = null;
    
    public function __construct() {}
    
    #[ORM\PrePersist]
    public function generateUuid(): void
    {
        if ($this->uuid === null) {
            $this->uuid = Uuid::v7();
        }
    }
}
```

**After:**
```php
class Country
{
    private Uuid $uuid;
    private string $nameEn;
    
    public function __construct(string $nameEn)
    {
        $this->uuid = Uuid::v7();
        $this->nameEn = $nameEn;
    }
}
```

### From API Platform

No changes needed! Your existing entities with constructors will work out of the box.

## Troubleshooting

### Issue: "Cannot create an instance"

**Cause:** Constructor requires parameters that are not provided in the JSON:API request.

**Solution:** Either:
1. Make the parameter optional with a default value
2. Make the parameter nullable
3. Include the attribute in the JSON:API request

### Issue: "UUID is still null"

**Cause:** The UUID field is not being initialized in the constructor.

**Solution:** Add UUID generation to the constructor:
```php
public function __construct(...)
{
    $this->uuid = Uuid::v7();
    // ...
}
```

## Advanced Usage

### Custom Instantiation Logic

If you need custom instantiation logic, you can extend `EntityInstantiator`:

```php
final class CustomEntityInstantiator extends EntityInstantiator
{
    protected function generateDefaultValue(ReflectionParameter $parameter): mixed
    {
        // Custom logic here
        return parent::generateDefaultValue($parameter);
    }
}
```

Then register it in `config/services.php`:

```php
$services
    ->set(CustomEntityInstantiator::class)
    ->args([service('doctrine')])
;

$services
    ->set(ValidatingDoctrinePersister::class)
    ->args([
        // ...
        service(CustomEntityInstantiator::class),
    ])
;
```

## See Also

- [Doctrine Lifecycle Callbacks](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/events.html#lifecycle-callbacks)
- [Symfony Serializer Constructor Arguments](https://symfony.com/doc/current/serializer.html#handling-constructor-arguments)
- [API Platform Entity Instantiation](https://api-platform.com/docs/core/serialization/)
