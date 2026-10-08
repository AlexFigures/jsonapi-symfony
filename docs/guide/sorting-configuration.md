# Sorting

Declare allowed fields with `SortableFields` / `SortableField`. `sort=title,-createdAt` requests ascending title then descending creation time, when both paths are allowed. Offset pagination uses `page[number]` and `page[size]` with configured default/maximum sizes.

Native Doctrine collection pagination selects distinct roots before representation hydration. Tests must verify unique root count, total count and stable page boundaries when filtering/sorting through associations. Includes must not change the selected root page.

Sorting through a to-many association has no single natural value. A custom handler should define MIN, MAX or other aggregate semantics explicitly. `performance.doctrine.collection_sort_policy: reject` rejects unsupported collection traversal; `legacy` preserves existing behavior during stabilization. The candidate retains this default explicitly.

Source: [sort declaration](../../src/Resource/Attribute/SortableFields.php), [sort handler contract](../../src/Filter/Handler/SortHandlerInterface.php).

Page parameters, defaults and links are described in [pagination](pagination.md).

## Stable page selection

```php
use AlexFigures\JsonApi\Resource\Attribute\SortableFields;
use AlexFigures\JsonApi\Resource\Attribute\SortableField;

#[SortableFields(['title', 'createdAt', new SortableField('author', inherit: true)])]
```

```text
GET /api/articles?sort=title,-createdAt&page[number]=2&page[size]=20
```

The identifier is added as an ascending tie-breaker when not already explicitly sorted. Native root selection/counting handles duplicate joined rows before hydration. Null placement follows the database platform; cross-platform identical null ordering is not promised. Custom handlers must define any required null/aggregate ordering and preserve the root tie-breaker.

The 1.0 candidate deliberately retains `collection_sort_policy: legacy` rather than silently changing the default. Set `reject` in production and use an explicit correlated aggregate handler for a path such as attachments.name. [Read-path regressions](../../tests/Integration/ReadPath/DoctrineReadPathTestCase.php) contain a MIN example plus PostgreSQL/MySQL count and page-boundary checks. Cursor/keyset pagination is outside 1.0.
