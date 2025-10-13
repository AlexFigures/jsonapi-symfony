# Backward Compatibility Policy

**Version**: 0.1.0  
**Status**: Active  
**Last Updated**: 2025-10-07

---

## Overview

JsonApiBundle follows [Semantic Versioning 2.0.0](https://semver.org/) to provide predictable backward compatibility guarantees. This document defines what constitutes the public API, what changes are considered breaking, and how deprecations are handled.

---

## Semantic Versioning

Given a version number `MAJOR.MINOR.PATCH`, we increment:

- **MAJOR** version when making incompatible API changes (breaking changes)
- **MINOR** version when adding functionality in a backward compatible manner
- **PATCH** version when making backward compatible bug fixes

**Example**: `1.2.3` → `1.2.4` (patch), `1.3.0` (minor), `2.0.0` (major)

---

## What is Public API?

The **public API** consists of:

### ✅ Stable - Backward Compatibility Guaranteed

1. **Contract Interfaces** (`src/Contract/`)
   - All interfaces in `AlexFigures\Symfony\Contract\Data\*`
   - All interfaces in `AlexFigures\Symfony\Contract\Resource\*`
   - All interfaces in `AlexFigures\Symfony\Contract\Tx\*`
   - All classes marked with `@api` tag in PHPDoc

2. **Resource Attributes** (`src/Resource/Attribute/`)
   - `JsonApiResource` - Resource type declaration
   - `Id` - Resource identifier marker
   - `Attribute` - Resource attribute marker
   - `Relationship` - Resource relationship marker

3. **Configuration Schema** (`Configuration.php`)
   - All configuration options under `jsonapi:` key
   - Configuration structure and defaults
   - Validation rules

4. **Data Transfer Objects** (DTOs)
   - `ChangeSet` - Attribute changes for write operations
   - `ResourceIdentifier` - Resource type + ID pair
   - `Slice` - Paginated collection of resources
   - `SliceIds` - Paginated collection of IDs

5. **Exception Classes** (when documented as public)
   - Exception class names
   - Exception inheritance hierarchy
   - Public exception properties

### ❌ NOT Public API - May Change Without Notice

1. **HTTP Controllers** (`src/Http/Controller/`)
   - Internal implementation details
   - May change between minor versions

2. **Symfony Bridge** (`src/Bridge/Symfony/`)
   - Internal Symfony integration
   - May change between minor versions

3. **Internal Services**
   - Any class not in `Contract\` namespace
   - Any class not marked with `@api` tag
   - Private/protected methods of public classes

4. **Test Utilities**
   - Classes in `tests/` directory
   - Test fixtures and helpers

---

## Breaking Changes (MAJOR Version)

The following changes require a **MAJOR** version bump:

### Interface Changes

- ❌ Removing a public interface
- ❌ Removing a method from a public interface
- ❌ Adding a required method to a public interface (without default implementation)
- ❌ Changing method signature (parameters, return type)
- ❌ Changing method visibility (public → protected/private)
- ❌ Renaming a method

**Example**:
```php
// BEFORE (v1.0.0)
interface ResourceRepository
{
    public function findOne(string $type, string $id): ?object;
}

// AFTER (v2.0.0) - BREAKING CHANGE
interface ResourceRepository
{
    // ❌ Changed signature - requires MAJOR bump
    public function findOne(string $type, string $id, array $options = []): ?object;
}
```

### Class Changes

- ❌ Removing a public class marked with `@api`
- ❌ Changing class constructor signature (for classes users extend)
- ❌ Removing public properties from DTOs
- ❌ Changing property types in DTOs

**Example**:
```php
// BEFORE (v1.0.0)
final class Slice
{
    public function __construct(
        public array $items,
        public int $pageNumber,
    ) {}
}

// AFTER (v2.0.0) - BREAKING CHANGE
final class Slice
{
    public function __construct(
        public array $items,
        public int $pageNumber,
        public int $pageSize,  // ❌ New required parameter - BREAKING
    ) {}
}
```

### Configuration Changes

- ❌ Removing a configuration option
- ❌ Changing configuration option type
- ❌ Changing default behavior (without opt-in)
- ❌ Making optional configuration required

**Example**:
```yaml
# BEFORE (v1.0.0)
jsonapi:
    pagination:
        default_size: 25

# AFTER (v2.0.0) - BREAKING CHANGE
jsonapi:
    pagination:
        # ❌ Removed 'default_size' - BREAKING
        page_size: 25
```

### Behavior Changes

- ❌ Changing exception types thrown by public methods
- ❌ Changing return value semantics
- ❌ Changing validation rules (stricter)

---

## New Features (MINOR Version)

The following changes are allowed in **MINOR** versions:

### Interface Additions

- ✅ Adding a new public interface
- ✅ Adding a new method with default implementation (PHP 8.0+ traits)
- ✅ Adding optional parameters to existing methods

**Example**:
```php
// BEFORE (v1.0.0)
interface ResourceRepository
{
    public function findOne(string $type, string $id): ?object;
}

// AFTER (v1.1.0) - NON-BREAKING
interface ResourceRepository
{
    public function findOne(string $type, string $id): ?object;
    
    // ✅ New method - OK in MINOR version
    public function findMany(string $type, array $ids): iterable;
}
```

### Class Additions

- ✅ Adding new public classes
- ✅ Adding new public methods to existing classes
- ✅ Adding optional constructor parameters (with defaults)

### Configuration Additions

- ✅ Adding new configuration options (with defaults)
- ✅ Adding new optional parameters
- ✅ Adding new validation rules (less strict)

**Example**:
```yaml
# BEFORE (v1.0.0)
jsonapi:
    pagination:
        default_size: 25

# AFTER (v1.1.0) - NON-BREAKING
jsonapi:
    pagination:
        default_size: 25
        max_size: 100  # ✅ New optional config - OK
```

### Behavior Additions

- ✅ Adding new features (opt-in)
- ✅ Improving performance
- ✅ Adding new exception types (for new features)

---

## Bug Fixes (PATCH Version)

The following changes are allowed in **PATCH** versions:

- ✅ Fixing incorrect behavior to match documentation
- ✅ Fixing security vulnerabilities
- ✅ Performance improvements (without API changes)
- ✅ Documentation updates
- ✅ Internal refactoring (no public API changes)
- ✅ Fixing typos in error messages

**Example**:
```php
// BEFORE (v1.0.0) - BUG
public function findOne(string $type, string $id): ?object
{
    // Bug: doesn't apply sparse fieldsets
    return $this->repository->find($id);
}

// AFTER (v1.0.1) - BUG FIX
public function findOne(string $type, string $id): ?object
{
    // ✅ Fixed to apply sparse fieldsets - OK in PATCH
    return $this->repository->find($id, $this->criteria->fields);
}
```

---

## Deprecation Policy

### Deprecation Process

1. **Mark as deprecated** in MINOR version
   - Add `@deprecated` tag to PHPDoc
   - Add deprecation notice in CHANGELOG
   - Provide migration path in documentation

2. **Keep deprecated API** for at least one MAJOR version
   - Deprecated in v1.5.0 → Removed in v2.0.0 (earliest)

3. **Remove deprecated API** in next MAJOR version
   - Document removal in UPGRADE guide
   - Provide automated migration tools (when possible)

### Deprecation Example

```php
// v1.5.0 - Deprecate old method
/**
 * @deprecated since 1.5.0, use findOne() instead. Will be removed in 2.0.0.
 */
public function find(string $id): ?object
{
    trigger_deprecation(
        'jsonapi/symfony-jsonapi-bundle',
        '1.5.0',
        'Method "%s" is deprecated, use "findOne()" instead.',
        __METHOD__
    );
    
    return $this->findOne('articles', $id);
}

// v2.0.0 - Remove deprecated method
// ❌ Method removed entirely
```

---

## Pre-1.0 Releases (0.x.y)

**Special Rules for 0.x versions**:

- ⚠️ **No BC guarantees** between MINOR versions (0.1 → 0.2 may break)
- ✅ **BC maintained** between PATCH versions (0.1.0 → 0.1.1)
- 🎯 **Goal**: Stabilize API for 1.0.0 release

**Recommendation**: Pin to exact MINOR version in `composer.json`:

```json
{
    "require": {
        "jsonapi/symfony-jsonapi-bundle": "~0.1.0"
    }
}
```

---

## Version 1.0.0 Stability Promise

Once version **1.0.0** is released:

- ✅ Full semantic versioning applies
- ✅ BC guaranteed within same MAJOR version
- ✅ Deprecation policy enforced
- ✅ Public API frozen (additions only)

---

## Upgrade Path for Breaking Changes

When introducing breaking changes in a MAJOR version, we provide:

1. **Upgrade Guide** (`docs/api/upgrade-guide.md`)
   - Step-by-step migration instructions
   - Code examples (before/after)
   - Automated migration scripts (when possible)

2. **Deprecation Warnings** (in previous MINOR version)
   - Runtime warnings for deprecated APIs
   - Clear migration path in error messages

3. **Changelog** (`CHANGELOG.md`)
   - Detailed list of breaking changes
   - Rationale for each change
   - Links to relevant issues/PRs

---

## Examples of BC Breaks vs Non-Breaks

### ❌ Breaking Changes

```php
// 1. Removing method
interface ResourceRepository
{
    // ❌ REMOVED - BREAKING
    // public function find(string $id): ?object;
}

// 2. Changing signature
interface ResourcePersister
{
    // ❌ Changed return type - BREAKING
    public function create(string $type, ChangeSet $changes): string; // was: object
}

// 3. Removing configuration
jsonapi:
    # ❌ REMOVED - BREAKING
    # strict_content_negotiation: true
```

### ✅ Non-Breaking Changes

```php
// 1. Adding optional parameter
interface ResourceRepository
{
    // ✅ Added optional param - OK
    public function findOne(string $type, string $id, ?Criteria $criteria = null): ?object;
}

// 2. Adding new method
interface ResourceRepository
{
    public function findOne(string $type, string $id): ?object;
    
    // ✅ New method - OK
    public function count(string $type): int;
}

// 3. Adding configuration option
jsonapi:
    pagination:
        default_size: 25
        max_size: 100  # ✅ New option - OK
```

---

## Checking for BC Breaks

We use [Roave Backward Compatibility Check](https://github.com/Roave/BackwardCompatibilityCheck) in CI:

```bash
make bc-check
# or
vendor/bin/roave-backward-compatibility-check --from=v1.0.0
```

This tool automatically detects:
- Interface changes
- Class signature changes
- Method removals
- Property type changes

---

## Questions?

If you're unsure whether a change is breaking:

1. Check this document
2. Run `make bc-check` against previous version
3. Ask in GitHub Discussions
4. Open an issue for clarification

---

## See Also

- [Public API Reference](public-api.md) - Complete API documentation
- [Upgrade Guide](upgrade-guide.md) - Migration between versions
- [Semantic Versioning 2.0.0](https://semver.org/) - Official spec

---

**Last Updated**: 2025-10-07  
**Maintainer**: JsonApiBundle Team

