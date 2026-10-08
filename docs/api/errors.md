# Stable error contract

For an identified failure condition, 1.x preserves HTTP status, corresponding string `errors[].status`, published `ErrorCodes` values, title meaning and the applicable `source.pointer`, `source.parameter` or `source.header`. Titles describe the error category; exact punctuation/localized wording is not a machine interface. `errors[].detail` is descriptive and is not byte-for-byte stable. Debug metadata and correlation identifiers are not stable payload values.

| Condition | Status | Observable source / code |
| --- | --- | --- |
| Malformed JSON / query syntax | 400 | `invalid-json` or query parameter diagnostic |
| Unacceptable response / request media | 406 / 415 | negotiation diagnostic |
| Missing resource | 404 | `resource-not-found` |
| Relationship authorization denial | 403 | `forbidden`, relationship pointer on writes |
| Input validation | 422 | `validation-error`, offending data pointer |
| Database uniqueness conflict | 409 | `conflict`, data/attribute pointer when identifiable |
| Foreign key / other mapped constraint validation | 422 | `validation-error`, relationship/data pointer |
| Atomic unsupported connection boundary | 409 | `unsupported-transaction-boundary`, operation pointer |
| Conflict / optimistic concurrency | 409 | conflict diagnostic |
| Missing required If-Match | 428 | `precondition-required`, `source.header: If-Match` |
| Stale If-Match | 412 | `precondition-failed`, `source.header: If-Match` |
| Query/include/linkage budget overflow | 400 | parameter or budget diagnostic, no successful truncation |

Atomic structural errors and operation validation follow the Atomic parser/handler contract; some operation conflicts are 409 even when ordinary input validation is 422. Rollback is independent of error category. Do not interpret every 409 as a transaction failure.

Applications may build `ErrorObject` / `ErrorBuilder` responses and supply an optional `errors[].links.type` documentation link. Document-level links are separate. Custom providers must preserve their advertised errors and transaction semantics. See [response factory](../guide/response-factory.md), [ErrorCodes](../../src/Http/Error/ErrorCodes.php) and bundle [status regressions](../../tests/JsonApiStatus).
