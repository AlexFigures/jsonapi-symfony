# Conformance evidence preparation

This page is a starting map from behavior areas to executable bundle tests. It does not assert a complete requirement count, coverage percentage or independent specification certification. Final requirement-by-requirement review remains TODO before RC.

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

TODO: map each supported specification/extension requirement to an exact test, expected error/source semantics and independent consumer evidence. Distinguish specification conformance from application policy, production limits and infrastructure behavior. A test count is not a conformance percentage.

[Documentation TODO](../release/documentation-todo.md) · [release checklist](../release/checklist.md).
