# Upgrade Guide

**Version**: 0.1.0  
**Last Updated**: 2025-10-07

---

## Table of Contents

1. [Overview](#overview)
2. [Pre-1.0 Upgrades](#pre-10-upgrades)
3. [Upgrade Checklist](#upgrade-checklist)
4. [Version-Specific Guides](#version-specific-guides)
5. [Breaking Changes](#breaking-changes)
6. [Deprecations](#deprecations)

---

## Overview

This guide helps you upgrade JsonApiBundle between versions. We follow [Semantic Versioning](https://semver.org/), which means:

- **MAJOR** versions (1.0.0 → 2.0.0) may contain breaking changes
- **MINOR** versions (1.0.0 → 1.1.0) add features in a backward-compatible manner
- **PATCH** versions (1.0.0 → 1.0.1) contain bug fixes only

---

## Pre-1.0 Upgrades

**⚠️ Important:** Versions 0.x may introduce breaking changes in MINOR versions.

**Recommendation:** Pin to exact MINOR version in `composer.json`:

```json
{
    "require": {
        "jsonapi/symfony-jsonapi-bundle": "~0.1.0"
    }
}
```

This allows PATCH updates (0.1.0 → 0.1.1) but prevents MINOR updates (0.1.0 → 0.2.0).

---

## Upgrade Checklist

Before upgrading to any new version:

### 1. Backup Your Code

```bash
git commit -am "Backup before upgrade"
git tag pre-upgrade-$(date +%Y%m%d)
```

### 2. Review CHANGELOG

Check [CHANGELOG.md](../../CHANGELOG.md) for:
- Breaking changes
- New features
- Deprecations
- Bug fixes

### 3. Check Backward Compatibility

Run BC check if available:

```bash
make bc-check
```

Or manually:

```bash
vendor/bin/roave-backward-compatibility-check --from=v0.1.0
```

### 4. Update Dependencies

```bash
composer update jsonapi/symfony-jsonapi-bundle
```

### 5. Clear Cache

```bash
php bin/console cache:clear
```

### 6. Run Tests

```bash
php bin/console test
```

### 7. Check Deprecation Warnings

Enable deprecation logging:

```yaml
# config/packages/dev/monolog.yaml
monolog:
    handlers:
        deprecation:
            type: stream
            path: "%kernel.logs_dir%/deprecations.log"
            level: info
            channels: ["deprecation"]
```

Check logs:

```bash
tail -f var/log/deprecations.log
```

---

## Version-Specific Guides

### Upgrading to 1.0.0 (Future)

**Status:** Not yet released

When 1.0.0 is released, this section will contain:
- Migration steps from 0.x
- Breaking changes
- New features
- Deprecated APIs

**Preparation:**
1. Fix all deprecation warnings in 0.x
2. Review [BC Policy](bc-policy.md)
3. Update custom implementations to use public API only

---

### New Routing Features (0.1.x)

**Status:** Available now

**New Features:**
- Configurable route naming conventions (snake_case vs kebab-case)
- Custom route attributes for defining custom endpoints

**Migration Guide:**
- [Routing Features Upgrade Guide](../migration/routing-features-upgrade.md)

**Backward Compatibility:**
- ✅ Fully backward compatible
- ✅ No breaking changes
- ✅ Optional features

---

### Upgrading from 0.1.x to 0.2.x (Future)

**Status:** Not yet released

**Expected Breaking Changes:**
- TBD

**Migration Steps:**
- TBD

---

## Breaking Changes

### Current Version (0.1.0)

No breaking changes yet - this is the initial release.

---

### Future Breaking Changes

Breaking changes will be documented here when they occur.

**Example format:**

#### Removed: `OldInterface`

**Removed in:** 2.0.0  
**Deprecated in:** 1.5.0

**Before:**
```php
use AlexFigures\Symfony\OldInterface;

class MyRepository implements OldInterface
{
    public function oldMethod(): void { }
}
```

**After:**
```php
use AlexFigures\Symfony\NewInterface;

class MyRepository implements NewInterface
{
    public function newMethod(): void { }
}
```

**Migration:**
1. Replace `OldInterface` with `NewInterface`
2. Rename `oldMethod()` to `newMethod()`
3. Update method signatures if needed

---

## Deprecations

### Current Deprecations

No deprecations in current version (0.1.0).

---

### How Deprecations Work

When we deprecate an API:

1. **Deprecation Notice** - Added in MINOR version
   - `@deprecated` tag in PHPDoc
   - Runtime warning via `trigger_deprecation()`
   - Documentation updated

2. **Deprecation Period** - At least one MAJOR version
   - Deprecated in 1.5.0 → Removed in 2.0.0 (earliest)

3. **Removal** - In next MAJOR version
   - Deprecated API removed
   - Migration guide provided

**Example:**

```php
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
```

---

## Automated Migration Tools

### Rector Rules (Future)

We plan to provide Rector rules for automated migrations:

```bash
composer require --dev rector/rector
```

```php
// rector.php
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src'])
    ->withSets([
        \AlexFigures\Symfony\Rector\JsonApiSetList::UPGRADE_10,
    ]);
```

Run migration:

```bash
vendor/bin/rector process
```

---

## Upgrade Support

### Getting Help

If you encounter issues during upgrade:

1. **Check this guide** - Most common issues are documented
2. **Search issues** - [GitHub Issues](https://github.com/AlexFigures/jsonapi-symfony/issues)
3. **Ask in discussions** - [GitHub Discussions](https://github.com/AlexFigures/jsonapi-symfony/discussions)
4. **Report bugs** - [New Issue](https://github.com/AlexFigures/jsonapi-symfony/issues/new)

### Professional Support

For commercial support, contact the maintainers.

---

## Best Practices

### 1. Stay Updated

Subscribe to releases:
- Watch the repository on GitHub
- Enable release notifications
- Follow the changelog

### 2. Test Before Upgrading

Always test upgrades in a development environment:

```bash
# Create test branch
git checkout -b test-upgrade

# Upgrade
composer update jsonapi/symfony-jsonapi-bundle

# Run tests
php bin/console test

# If successful, merge to main
git checkout main
git merge test-upgrade
```

### 3. Upgrade Incrementally

Don't skip versions:
- ❌ 0.1.0 → 0.3.0 (skip 0.2.0)
- ✅ 0.1.0 → 0.2.0 → 0.3.0

This ensures you don't miss important migration steps.

### 4. Fix Deprecations Early

Don't wait until the deprecated API is removed:

```bash
# Check for deprecations
grep -r "@deprecated" vendor/jsonapi/symfony-jsonapi-bundle/src/

# Fix them immediately
```

### 5. Pin Dependencies in Production

Use exact versions in production:

```json
{
    "require": {
        "jsonapi/symfony-jsonapi-bundle": "0.1.0"
    }
}
```

Update only after testing:

```bash
# Test in dev
composer update jsonapi/symfony-jsonapi-bundle

# If successful, update composer.lock in production
git add composer.lock
git commit -m "Update JsonApiBundle to 0.1.1"
```

---

## Version History

### 0.1.0 (2025-10-07)

**Initial Release**

**Features:**
- JSON:API 1.1 compliance
- Attribute-driven resource metadata
- Read endpoints (GET collection, GET resource)
- Write endpoints (POST, PATCH, DELETE)
- Relationship endpoints
- Query parameter parsing (include, fields, sort, page)
- Pagination with links
- Sparse fieldsets
- Compound documents
- Atomic operations
- Profile support (RFC 6906)
- HTTP caching (ETag, Last-Modified)
- OpenAPI 3.1 documentation
- Swagger UI / Redoc integration

**Public API:**
- All interfaces in `src/Contract/`
- All attributes in `src/Resource/Attribute/`
- Configuration schema

**Known Limitations:**
- Filter operators not fully implemented
- Cursor-based pagination not supported
- Some edge cases in spec compliance

---

## See Also

- [Backward Compatibility Policy](bc-policy.md) - BC guarantees
- [Public API Reference](public-api.md) - Stable API documentation
- [CHANGELOG.md](../../CHANGELOG.md) - Detailed version history
- [Semantic Versioning](https://semver.org/) - Versioning specification

---

**Last Updated**: 2025-10-07  
**Maintainer**: JsonApiBundle Team

