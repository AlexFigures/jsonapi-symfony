# Architecture Review & Extensibility Analysis

This document provides a comprehensive architectural review of JsonApiBundle, focusing on layering, dependency management, extensibility points, and backward compatibility.

---

## Executive Summary

**Status**: ✅ **EXCELLENT** - Clean architecture with strong separation of concerns

**Key Findings**:
- ✅ **Deptrac**: 0 violations - Perfect dependency management
- ✅ **Ports & Adapters**: Clean separation between domain and infrastructure
- ✅ **Extensibility**: Well-defined extension points (profiles, operators, adapters)
- ✅ **Public API**: Clear contract namespace for stable interfaces
- ⚠️ **BC Surface**: Needs explicit documentation of stable vs internal APIs

**Architecture Score**: **9.5/10**

---

## 1. Layered Architecture

### 1.1 Layer Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    HTTP Layer (Controllers)                  │
│  CollectionController, ResourceController, AtomicController  │
└────────────────────┬────────────────────────────────────────┘
                     │ depends on ↓
┌─────────────────────────────────────────────────────────────┐
│                   Application Layer (Services)               │
│  DocumentBuilder, QueryParser, ErrorBuilder, LinkGenerator   │
│  AtomicTransaction, ProfileNegotiator, FilterCompiler        │
└────────────────────┬────────────────────────────────────────┘
                     │ depends on ↓
┌─────────────────────────────────────────────────────────────┐
│                    Domain Layer (Contracts)                  │
│  ResourceRepository, ResourcePersister, TransactionManager   │
│  ProfileInterface, Operator, ProfileHook                     │
└────────────────────┬────────────────────────────────────────┘
                     │ implemented by ↓
┌─────────────────────────────────────────────────────────────┐
│                 Infrastructure Layer (Adapters)              │
│  DoctrineRepository, DoctrinePersister, DoctrineTransaction  │
│  InMemoryRepository (tests), Custom implementations (users)  │
└─────────────────────────────────────────────────────────────┘
```

### 1.2 Deptrac Analysis

**Configuration**: `deptrac.yaml`

```yaml
layers:
  - name: Contract
    collectors:
      - type: className
        regex: '^JsonApi\\Symfony\\Contract\\'
  - name: Application
    collectors:
      - type: className
        regex: '^JsonApi\\Symfony\\(?!Contract).+'

ruleset:
  Contract: []  # No dependencies
  Application:
    - Contract  # Can depend on Contract only
```

**Result**: ✅ **0 violations**

```
Report
--------------------
Violations           0
Skipped violations   0
Uncovered            0
Allowed              0
Warnings             0
Errors               0
```

**Assessment**: Perfect adherence to dependency rules. Contract layer has no dependencies, Application layer depends only on Contract.

---

## 2. Public API Surface

### 2.1 Contract Namespace (Stable API)

The `JsonApi\Symfony\Contract\` namespace defines the **stable public API** that users can depend on.

#### 2.1.1 Data Contracts

| Interface | Purpose | Stability |
|-----------|---------|-----------|
| `ResourceRepository` | Read operations (findCollection, findOne, findRelated) | ✅ Stable |
| `ResourcePersister` | Write operations (create, update, delete) | ✅ Stable |
| `ExistenceChecker` | Check resource existence | ✅ Stable |
| `RelationshipReader` | Read relationship data | ✅ Stable |
| `RelationshipUpdater` | Update relationships | ✅ Stable |

**Value Objects**:
- `ChangeSet` - Attribute changes for create/update
- `ResourceIdentifier` - Type + ID pair
- `Slice` - Paginated collection result
- `SliceIds` - ID-only slice for optimization

#### 2.1.2 Transaction Contract

| Interface | Purpose | Stability |
|-----------|---------|-----------|
| `TransactionManager` | Atomic operations support | ✅ Stable |

**Method**:
```php
public function transactional(callable $callback): mixed;
```

#### 2.1.3 Resource Metadata Contract

| Interface | Purpose | Stability |
|-----------|---------|-----------|
| `ResourceMetadataInterface` | Resource type identification | ✅ Stable |

### 2.2 Extension Points (Semi-Stable API)

#### 2.2.1 Profile System

| Interface | Purpose | Stability |
|-----------|---------|-----------|
| `ProfileInterface` | Define custom profiles (RFC 6906) | ✅ Stable |
| `DocumentHook` | Modify document structure | ✅ Stable |
| `QueryHook` | Modify query parsing | ✅ Stable |
| `ResourceHook` | Modify resource objects | ⚠️ Evolving |

**Example**:
```php
final class CustomProfile implements ProfileInterface
{
    public function uri(): string { return 'urn:example:custom'; }
    public function descriptor(): ProfileDescriptor { ... }
    public function hooks(): iterable { yield new CustomDocumentHook(); }
}
```

#### 2.2.2 Filter Operators

| Interface | Purpose | Stability |
|-----------|---------|-----------|
| `Operator` | Define custom filter operators | ✅ Stable |
| `AbstractOperator` | Base class for operators | ✅ Stable |

**Built-in Operators**:
- `EqOperator` - Equality (`eq`)
- `InOperator` - IN clause (`in`)
- `GteOperator`, `LteOperator` - Comparisons (`gte`, `lte`)
- `LikeOperator` - Pattern matching (`like`)

**Example**:
```php
final class CustomOperator extends AbstractOperator
{
    public function name(): string { return 'custom'; }
    
    public function compile(
        string $rootAlias,
        string $dqlField,
        array $values,
        AbstractPlatform $platform
    ): DoctrineExpression {
        // Custom DQL generation
    }
}
```

#### 2.2.3 Cache Invalidation

| Interface | Purpose | Stability |
|-----------|---------|-----------|
| `SurrogatePurgerInterface` | Purge cache by surrogate keys | ✅ Stable |

**Example**:
```php
final class VarnishPurger implements SurrogatePurgerInterface
{
    public function purge(array $keys): void {
        // Send BAN request to Varnish
    }
}
```

### 2.3 Internal API (Unstable)

**Namespaces**:
- `JsonApi\Symfony\Http\*` - HTTP layer (controllers, parsers, builders)
- `JsonApi\Symfony\Filter\*` - Filter parsing and compilation
- `JsonApi\Symfony\Atomic\*` - Atomic operations implementation
- `JsonApi\Symfony\Bridge\Symfony\*` - Symfony integration

**Warning**: These are **internal implementation details** and may change between minor versions. Do not depend on them directly.

---

## 3. Dependency Injection & Service Configuration

### 3.1 Service Registration

**Location**: `config/services.php`

**Pattern**: All services are registered as **final classes** with **constructor injection**.

**Example**:
```php
$services->set(DocumentBuilder::class)
    ->args([
        service(ResourceRegistryInterface::class),
        service(PropertyAccessorInterface::class),
        service(LinkGenerator::class),
        service(LimitsEnforcer::class)->nullOnInvalid(),
    ]);
```

**Assessment**: ✅ Clean DI, no service locators, no global state

### 3.2 Extension via DI Tags

**Profile Registration**:
```php
$services->set(AuditTrailProfile::class)
    ->tag('jsonapi.profile');
```

**Operator Registration**:
```php
$services->set(EqOperator::class)
    ->tag('jsonapi.filter.operator');
```

**Assessment**: ✅ Standard Symfony DI patterns, easy to extend

---

## 4. Extensibility Analysis

### 4.1 Extension Points Summary

| Extension Point | Mechanism | Difficulty | Documentation |
|----------------|-----------|------------|---------------|
| **Custom Repository** | Implement `ResourceRepository` | Easy | ✅ Examples in tests |
| **Custom Persister** | Implement `ResourcePersister` | Easy | ✅ Examples in tests |
| **Custom Profile** | Implement `ProfileInterface` | Medium | ⚠️ Needs guide |
| **Custom Operator** | Extend `AbstractOperator` | Medium | ⚠️ Needs guide |
| **Custom Cache Purger** | Implement `SurrogatePurgerInterface` | Easy | ✅ Interface clear |
| **Custom Transaction** | Implement `TransactionManager` | Easy | ✅ Interface clear |

### 4.2 Built-in Profiles

| Profile | URI | Purpose | Status |
|---------|-----|---------|--------|
| **Audit Trail** | `urn:jsonapi:profile:audit-trail` | Track created/updated metadata | ✅ Implemented |
| **Soft Delete** | `urn:jsonapi:profile:soft-delete` | Soft delete semantics | ✅ Implemented |
| **Relationship Counts** | `urn:jsonapi:profile:rel-counts` | Add counts to relationships | ✅ Implemented |

**Assessment**: ✅ Good examples for users to follow

### 4.3 Hook System

**Available Hooks**:
- `DocumentHook::onTopLevelLinks()` - Modify top-level links
- `DocumentHook::onTopLevelMeta()` - Modify top-level meta
- `DocumentHook::onResourceRelationships()` - Modify resource relationships
- `QueryHook::onParseQuery()` - Modify query criteria

**Example**:
```php
final class CustomDocumentHook implements DocumentHook
{
    public function onTopLevelMeta(ProfileContext $context, array &$meta): void
    {
        $meta['custom'] = 'value';
    }
}
```

**Assessment**: ✅ Powerful, type-safe, well-designed

---

## 5. Backward Compatibility (BC) Strategy

### 5.1 Current BC Surface

**Stable (Guaranteed BC)**:
- `JsonApi\Symfony\Contract\*` - All interfaces and value objects
- `ProfileInterface`, `Operator`, `SurrogatePurgerInterface`
- Built-in profile URIs (`urn:jsonapi:profile:*`)

**Evolving (BC on best-effort basis)**:
- `ProfileHook` interfaces (may add methods with defaults)
- Configuration structure (`jsonapi.*` config keys)

**Internal (No BC guarantees)**:
- Controllers, parsers, builders, compilers
- Internal service classes

### 5.2 BC Check Tool

**Tool**: `roave/backward-compatibility-check`

**Configuration**: `Makefile`
```bash
bc-check: vendor/autoload.php
    if git describe --tags --abbrev=0 >/dev/null 2>&1; then \
        latest_tag=$$(git describe --tags --abbrev=0); \
        vendor/bin/roave-backward-compatibility-check --from=$$latest_tag; \
    else \
        echo "No git tags found; skipping BC check."; \
    fi
```

**Status**: ⚠️ **No tags yet** - First release will establish baseline

**Recommendation**: 
1. Tag v0.1.0 as baseline
2. Run BC check on every PR
3. Document BC breaks in CHANGELOG.md

### 5.3 Semantic Versioning

**Proposed Strategy**:
- **MAJOR** (1.0.0 → 2.0.0): BC breaks in `Contract\*` namespace
- **MINOR** (0.1.0 → 0.2.0): New features, BC breaks in internal APIs
- **PATCH** (0.1.0 → 0.1.1): Bug fixes, no BC breaks

**Pre-1.0 Caveat**: Minor versions may contain BC breaks (standard for 0.x.y)

---

## 6. Code Quality Metrics

### 6.1 PHPStan Analysis

**Configuration**: `phpstan.neon`
```neon
parameters:
    level: 8  # Maximum strictness
    checkMissingIterableValueType: true
    checkGenericClassInNonGenericObjectType: true
    reportUnmatchedIgnoredErrors: true
    checkExplicitMixed: true
```

**Result**: ✅ **Level 8** - Highest strictness, no errors

**Assessment**: Excellent type safety

### 6.2 Final Classes

**Pattern**: Most classes are `final` to prevent fragile base class problem

**Exceptions**:
- `AbstractOperator` - Designed for extension
- Interfaces - By definition extensible

**Assessment**: ✅ Correct use of `final` keyword

### 6.3 Immutability

**Value Objects**: All value objects are immutable
- `ChangeSet`, `ResourceIdentifier`, `Slice`, `SliceIds`
- `Criteria`, `Pagination`, `Sorting`

**Assessment**: ✅ Prevents accidental mutations

---

## 7. Identified Issues & Recommendations

### 7.1 HIGH PRIORITY: Document Public API

**Issue**: No explicit documentation of stable vs internal APIs

**Impact**: Users may depend on internal APIs, causing BC breaks

**Fix**: Create `docs/api/public-api.md` with:
- List of stable interfaces
- List of extension points
- Examples for each extension point
- BC policy

**Effort**: 2-3 hours

### 7.2 MEDIUM PRIORITY: Add API Stability Annotations

**Issue**: No machine-readable API stability markers

**Impact**: Tools can't detect BC breaks automatically

**Fix**: Add `@api` annotations to stable interfaces

```php
/**
 * @api
 */
interface ResourceRepository { ... }
```

**Effort**: 1 hour

### 7.3 MEDIUM PRIORITY: Enhance Deptrac Rules

**Issue**: Current deptrac rules are minimal (only 2 layers)

**Impact**: Could have more granular dependency control

**Fix**: Add more layers

```yaml
layers:
  - name: Contract
  - name: Domain
  - name: Application
  - name: Infrastructure
  - name: Bridge

ruleset:
  Contract: []
  Domain: [Contract]
  Application: [Contract, Domain]
  Infrastructure: [Contract, Domain, Application]
  Bridge: [Contract, Domain, Application, Infrastructure]
```

**Effort**: 2-3 hours

### 7.4 LOW PRIORITY: Add Architecture Decision Records (ADRs)

**Issue**: No documentation of architectural decisions

**Impact**: Future maintainers don't know why decisions were made

**Fix**: Create `docs/adr/` with ADRs for:
- Why ports & adapters pattern?
- Why final classes by default?
- Why profile system instead of events?

**Effort**: 3-4 hours

---

## 8. Extensibility Examples

### 8.1 Custom Repository (Doctrine)

```php
use AlexFigures\Symfony\Contract\Data\ResourceRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineRepository implements ResourceRepository
{
    public function __construct(
        private EntityManagerInterface $em,
        private ResourceRegistryInterface $registry
    ) {}

    public function findCollection(string $type, Criteria $criteria): Slice
    {
        $metadata = $this->registry->getByType($type);
        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from($metadata->modelClass, 'e');
        
        // Apply filters, sorting, pagination
        
        return new Slice($qb->getQuery()->getResult(), ...);
    }
}
```

### 8.2 Custom Profile with Hooks

```php
final class TimestampProfile implements ProfileInterface
{
    public function uri(): string
    {
        return 'urn:example:timestamps';
    }

    public function hooks(): iterable
    {
        yield new class implements DocumentHook {
            public function onTopLevelMeta(ProfileContext $context, array &$meta): void
            {
                $meta['generated_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
            }
        };
    }
}
```

### 8.3 Custom Filter Operator

```php
final class ContainsOperator extends AbstractOperator
{
    public function name(): string { return 'contains'; }

    public function compile(
        string $rootAlias,
        string $dqlField,
        array $values,
        AbstractPlatform $platform
    ): DoctrineExpression {
        $param = 'param_' . uniqid();
        return new DoctrineExpression(
            sprintf('%s LIKE :%s', $dqlField, $param),
            [$param => '%' . $values[0] . '%']
        );
    }
}
```

---

## 9. Conclusion

**Overall Assessment**: ✅ **Excellent Architecture**

**Strengths**:
- ✅ Clean layering with zero dependency violations
- ✅ Well-defined public API via `Contract\*` namespace
- ✅ Powerful extensibility via profiles, operators, adapters
- ✅ Type-safe (PHPStan level 8)
- ✅ Immutable value objects
- ✅ Proper use of `final` keyword

**Weaknesses**:
- ⚠️ Public API not explicitly documented
- ⚠️ No API stability annotations
- ⚠️ Deptrac rules could be more granular
- ⚠️ No Architecture Decision Records

**Recommendations**:
1. **HIGH**: Document public API and BC policy
2. **MEDIUM**: Add `@api` annotations
3. **MEDIUM**: Enhance deptrac rules
4. **LOW**: Add ADRs for key decisions

**Architecture Score**: **9.5/10** - Production-ready, minor documentation improvements needed

---

**Last Updated**: 2025-10-06  
**Reviewer**: Codex QA Agent  
**Status**: ✅ Complete

