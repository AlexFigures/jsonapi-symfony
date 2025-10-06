# Security & Hardening Checklist

| Control | Status | Evidence | Notes |
| --- | --- | --- | --- |
| Strict media-type negotiation (406/415) | ✅ | Functional negotiation tests assert 406/415 responses and Vary headers.【F:tests/Functional/ContentNegotiationTest.php†L28-L63】【F:tests/Functional/Errors/NegotiationErrorsTest.php†L17-L58】 | Extend coverage to relationships/atomic endpoints. |
| Input document validation (type/id/attributes) | ✅ | `InputDocumentValidator` rejects unknown attributes, read-only writes, and id/type mismatches.【F:src/Http/Write/InputDocumentValidator.php†L29-L125】【F:tests/Functional/ResourceWriteTest.php†L104-L210】 | Relationship write blocking enforced when disabled. |
| Error response hygiene | ✅ | Negotiation and validation errors mapped to JSON:API structure with header pointers; request id forwarded.【F:tests/Functional/Errors/NegotiationErrorsTest.php†L17-L58】【F:tests/Functional/Errors/ValidationErrorsTest.php†L21-L79】 | Ensure production stack hides internal traces (currently manual). |
| Query complexity & DoS limits | ⚠️ | `LimitsEnforcer` invoked in document builder but no automated tests toggling budgets.【F:src/Http/Document/DocumentBuilder.php†L72-L79】 | Add tests to assert enforcement of include depth, fields total, clause counts. |
| Parameterized persistence | ⚠️ | Persistence delegated to `ResourcePersister`/`ResourceRepository` interfaces; repository implementations not audited.【F:src/Contract/Data/ResourceRepository.php†L1-L35】 | Provide Doctrine adapter examples using prepared statements/QueryBuilder. |
| Preconditions & cache validators | ⚠️ | Conditional evaluator enforces If-Match/If-None-Match semantics in isolation.【F:tests/Unit/Http/Cache/ConditionalRequestEvaluatorTest.php†L18-L116】 | Need integration coverage for 428/412 responses on controllers and atomic endpoint. |
| Atomic operations safety | ⚠️ | Operations validated for media type and op code; transactionality and lid collision handling untested.【F:tests/Functional/Atomic/AtomicOperationsTest.php†L15-L163】 | Add rollback tests and lid conflict handling to prevent partial writes. |
| Surrogate key / cache purge | ❌ | No implementation or tests for Surrogate-Key headers or invalidation events. | Implement once event pipeline ready. |
| Logging & request correlation | ⚠️ | Request ID header asserted in negotiation error test but global strategy undocumented.【F:tests/Functional/Errors/NegotiationErrorsTest.php†L33-L38】 | Document header propagation and avoid leaking sensitive metadata. |
