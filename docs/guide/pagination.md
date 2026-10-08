# Pagination

Generated collections and native to-many relationship endpoints use offset pagination:

```text
GET /api/articles?page[number]=2&page[size]=20
GET /api/articles/1/comments?page[number]=2&page[size]=20
GET /api/articles/1/relationships/comments?page[number]=2&page[size]=20
```

Both values are integers greater than zero. The default page number is 1; `pagination.default_size` defaults to 25 and `pagination.max_size` to 100. Excessive sizes are rejected, not silently clamped. The independent `limits.page_max_size` guard also applies; zero disables that complexity guard, not the pagination parser's positive-size/max-size validation.

```yaml
jsonapi:
    pagination:
        default_size: 25
        max_size: 100
    limits:
        page_max_size: 100
```

Collection documents expose pagination links and `meta.total`, `meta.page`, `meta.size`. Follow the returned links to retain filters, sort, includes and sparse fields. Related endpoint links remain on the owner's relationship URL.

The Doctrine provider selects distinct root resources before representation hydration. Filtering through a to-many relationship must not consume several page slots for the same root; includes must not alter the selected roots. A stable identifier tie-breaker is added to sorting. See [sorting](sorting-configuration.md) for collection-valued sort semantics and database null ordering.

An ordinary GET does not promise a snapshot across independently requested pages while other transactions insert/delete resources. Custom providers must implement equivalent page/count semantics and honor repository visibility. Cursor/keyset pagination is outside the 1.0 contract.

Continue with [includes and sparse fields](representations.md) and [production budgets](production-policies.md).
