# Conformance evidence map

This page is a starting map from behavior areas to executable bundle tests. It does not assert a complete requirement count, coverage percentage or independent specification certification. This maintained map distinguishes specification requirements from optional extensions and application policies; it does not assert exhaustive certification.

| Area | Bundle evidence |
| --- | --- |
| Document/media/error behavior | [Conformance suite](../../tests/Conformance), [HTTP status suite](../../tests/JsonApiStatus) |
| Query validation and filter safety | [Query functional tests](../../tests/Functional/Query), [filter parser](../../tests/Unit/Filter/Parser/FilterParserTest.php) |
| Relationship and input errors | [Functional error suite](../../tests/Functional/Errors) |
| Atomic operation and lid behavior | [Atomic functional tests](../../tests/Functional/Atomic), [integration tests](../../tests/Integration/Atomic) |
| Concurrent preconditions | [Concurrent integration test](../../tests/Integration/Concurrency/ConcurrentWritePreconditionsTest.php) |
| Identifier discovery | [Doctrine identifier discovery](../../tests/Integration/Discovery/DoctrineIdentifierDiscoveryTest.php) |
| Graph reads and distinct pagination | [ReadPath integration suite](../../tests/Integration/ReadPath) |
| Profiles, versions and extension contracts | [Regression kernel tests](../../tests/Functional/Regression), [profile integration](../../tests/Integration/Profile) |

JSON:API document/error/media/linkage identity requirements are exercised by Conformance and status snapshots; Atomic is an optional official extension; profiles/version DTOs and query/load budgets are bundle capabilities. Authorization/tenant visibility is application policy; server lock/replica failure is infrastructure behavior. Stable error/source expectations are in [the error contract](../api/errors.md).

Independent consumer evidence applies to merged bundle `96a1530f3155ddf001b7d1e48fd33e375c382d85`: recorded Symfony 7.4/8.1 runs pass 683 acceptance and 62 torture tests with zero failures/skips. [Verification](../release/verification.md) records platform provenance and the owner-confirmed broader compatibility result. Final-package publication retains its own installation gates in [the release checklist](../release/checklist.md). Expand individual requirement mappings with regression and consumer references as coverage evolves; a test count is not a conformance percentage.

[release checklist](../release/checklist.md).
