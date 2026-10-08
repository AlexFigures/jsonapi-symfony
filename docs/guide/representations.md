# Includes and sparse fieldsets

```text
GET /api/articles?include=author,comments.author&fields[articles]=title,author,comments&fields[authors]=name
```

`include` selects relationship paths and adds deduplicated resources to `included`. Sparse fields control serialized attributes and relationships by resource type. Include declarations, linkage policy, sparse fields and active profile requirements all contribute to the native representation fetch plan.

Doctrine loads the selected root page and required relationships in bounded batches, not one fetch join for every to-many association. Nested includes load by graph level; query count depends on graph/chunk shape. Included-resource identities are budgeted before hydration; identifier and included-resource budgets are separate. See [production limits](production-policies.md).

A profile/version resolver can choose a different view while the resource's protocol identity remains stable. Custom getters, hooks and providers must declare batch requirements and honor scopes/budgets; arbitrary application SQL has no automatic bounded-cost guarantee. Use `relationships.unplanned_read_policy: reject` when undeclared relationship reads must fail rather than fall back to legacy getters.
