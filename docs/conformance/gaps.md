# Test Gap Remediation Plan

| Gap | Priority | Proposed coverage | Owner/PR |
| --- | --- | --- | --- |
| Profile negotiation request-side conformance (Accept `profile`, 406 on unsupported profiles, controller integration) | High | Add functional tests exercising resource/collection controllers with `profile` query/header negotiation, verifying context propagation and opt-out toggles. | TODO (open issue) |
| Profile hook influence on serialization | High | Build fake profile altering document builder output; assert changes in responses and proper `profile` parameter echo. | TODO |
| Atomic operations transactionality and failure rollback | High | Mutation tests covering mixed success/failure, lid usage, `atomic:results` error member, and repository rollback hooks. | TODO |
| Cache validator emission across controllers | Medium | Functional tests verifying ETag/Last-Modified presence for collection/resource/related endpoints across include/fields combinations. | TODO |
| Preconditions on relationship and atomic writes | Medium | Cover 412/428 flows on relationship PATCH/DELETE and operations endpoint when If-Match missing or mismatched. | TODO |
| Surrogate key header publication and invalidation | Medium | Add integration tests around `JsonApi\Symfony\Invalidation` events ensuring `Surrogate-Key` headers and purge listeners fire. | TODO |
| Conformance snapshots for canonical documents | Medium | Generate JSON fixtures for standard scenarios and assert via JSONPath to guard regressions. | TODO |
| Negative negotiation for relationships and atomic routes | Low | Add 406/415 tests on `/relationships` and `/operations` endpoints to ensure consistent errors. | TODO |
| Property-based query parser fuzzing | Low | Introduce Eris/pytest randomised tests for include/fields/sort/page combinations; assert stability and invariants. | TODO |
