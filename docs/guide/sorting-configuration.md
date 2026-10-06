# Sorting and pagination

Declare allowed fields with `SortableFields` / `SortableField`. `sort=title,-createdAt` requests ascending title then descending creation time, when both paths are allowed. Offset pagination uses `page[number]` and `page[size]` with configured default/maximum sizes.

Native Doctrine collection pagination selects distinct roots before representation hydration. Tests must verify unique root count, total count and stable page boundaries when filtering/sorting through associations. Includes must not change the selected root page.

Sorting through a to-many association has no single natural value. A custom handler should define MIN, MAX or other aggregate semantics explicitly. `performance.doctrine.collection_sort_policy: reject` rejects unsupported collection traversal; `legacy` preserves existing behavior during stabilization. The final default needs a documented freeze decision.

Source: [sort declaration](../../src/Resource/Attribute/SortableFields.php), [sort handler contract](../../src/Filter/Handler/SortHandlerInterface.php).

TODO before freeze: finish null ordering, identifier tie-breaker and custom aggregate examples against PostgreSQL/MySQL. Cursor/keyset pagination is future work, not a current feature claim.
