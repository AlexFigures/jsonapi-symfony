# Acceptance gap iteration — bundle verification

Branch: `fix/acceptance-gaps`. External baseline: `5458778adc87386ffff9c07f009c25906e0971db`.

Per the updated task, implementation and reproduction are confined to this repository. The example application was not modified or run against the completed branch. Stable/gap/full external acceptance counts are **pending**, not green. MUST items remain externally unverified until both bundle and external tests pass.

`fixed` below means the original scenario has a passing bundle regression. `partially fixed` records a remaining verification or neighboring behavior limitation. Detailed root causes, previous coverage and regression paths for every gap are in [acceptance-gap-results.json](acceptance-gap-results.json).

## Regression suites

- **A** — [tests/Integration/Atomic/AcceptanceGapsTest.php](../../tests/Integration/Atomic/AcceptanceGapsTest.php) (PostgreSQL)
- **P** — [tests/Integration/Atomic/WritePreconditionsRegressionTest.php](../../tests/Integration/Atomic/WritePreconditionsRegressionTest.php) (PostgreSQL)
- **Q** — [tests/Integration/Atomic/QueryAcceptanceRegressionTest.php](../../tests/Integration/Atomic/QueryAcceptanceRegressionTest.php) (PostgreSQL)
- **I** — [tests/Integration/Atomic/IdentifierAcceptanceRegressionTest.php](../../tests/Integration/Atomic/IdentifierAcceptanceRegressionTest.php) (PostgreSQL)
- **R** — [tests/Integration/Atomic/RelationshipAcceptanceRegressionTest.php](../../tests/Integration/Atomic/RelationshipAcceptanceRegressionTest.php) (PostgreSQL)
- **F** — [tests/Integration/Atomic/ProfileAcceptanceRegressionTest.php](../../tests/Integration/Atomic/ProfileAcceptanceRegressionTest.php) (PostgreSQL)
- **H** — [tests/Functional/AcceptanceWriteContractTest.php](../../tests/Functional/AcceptanceWriteContractTest.php)
- **N** — [tests/Unit/Http/Negotiation/AcceptanceNegotiationTest.php](../../tests/Unit/Http/Negotiation/AcceptanceNegotiationTest.php)
- **D** — [tests/Unit/Bridge/DataLayerConfigurationTest.php](../../tests/Unit/Bridge/DataLayerConfigurationTest.php)

## Gap summary

Every external acceptance result is pending branch installation. No external assertions or markers were changed.

| Gap | Status in bundle | Changed subsystem | Regression | External acceptance |
| --- | --- | --- | --- | --- |
| CACHE-001 | fixed | Cache/controller boundary | P | pending |
| CACHE-002 | fixed | Cache/document metadata | P | pending |
| ATOMIC-001 | fixed | Atomic target validation | A | pending |
| ATOMIC-002 | fixed | Atomic target validation | A | pending |
| ATOMIC-003 | fixed | Shared identifier validation | H | pending |
| ATOMIC-004 | fixed | Atomic/Doctrine flush lifecycle | A | pending |
| ATOMIC-005 | fixed | Atomic URI target resolution | H | pending |
| ATOMIC-006 | fixed | Atomic URI target resolution | H | pending |
| ATOMIC-007 | fixed | Atomic result encoding | A | pending |
| ATOMIC-008 | fixed | Atomic result snapshots | A | pending |
| ATOMIC-009 | fixed | Resource operation policy | H | pending |
| ATOMIC-010 | fixed | Atomic write configuration | I | pending |
| ATOMIC-011 | partially fixed | Error pointer rebasing | H | pending |
| ATOMIC-012 | fixed | Media negotiation/DI | N,D | pending |
| ATOMIC-013 | fixed | Media negotiation | N | pending |
| CONTENT-NEGOTIATION-001 | fixed | Media candidate selection | N | pending |
| CONTENT-NEGOTIATION-002 | fixed | HTTP Accept metadata | N | pending |
| RELATIONSHIP-001 | fixed | Relationship resolution | R | pending |
| RELATIONSHIP-002 | fixed | Shared linkage validation | H | pending |
| RELATIONSHIP-003 | fixed | Input whitelist | H | pending |
| RELATIONSHIP-004 | fixed | Doctrine association nullability | R | pending |
| RELATIONSHIP-005 | fixed | Relationship document links | H | pending |
| ERROR-001 | fixed | Query error titles | Q | pending |
| ERROR-002 | fixed | Linkage document boundary | H | pending |
| WRITE-001 | fixed | JSON structural decoding | H | pending |
| INCLUDE-001 | fixed | Document generation | H | pending |
| FILTER-001 | fixed | Filter AST/operator naming | Q | pending |
| FILTER-002 | fixed | Filter parser/compiler | Q | pending |
| FILTER-003 | fixed | Filter parser/compiler | Q | pending |
| FILTER-004 | partially fixed | Doctrine ILIKE platform detection | Q | pending |
| FILTER-005 | fixed | Set predicates | Q | pending |
| ALIAS-001 | fixed | Metadata field resolution | Q | pending |
| QUERY-001 | fixed | Pagination boundary | Q | pending |
| QUERY-002 | fixed | Filter boundary/limits | Q | pending |
| HTTP-001 | fixed | Representation validators | H,P | pending |
| HTTP-002 | fixed | Controller DI/Allow | H,D | pending |
| DOCTRINE-001 | fixed | Flush/transaction error boundary | A | pending |
| DOCTRINE-002 | fixed | Flush/transaction error boundary | A | pending |
| UUID-001 | fixed | Mapped identifier conversion | I | pending |
| UUID-002 | fixed | Mapped identifier conversion | I | pending |
| PROFILE-001 | fixed | Per-resource profile context | F | pending |
| PROFILE-002 | partially fixed | Profile query lifecycle | F | pending |
| PROFILE-003 | fixed | Profile write lifecycle | F | pending |
| SORT-001 | fixed | Doctrine pagination ordering | Q | pending |

## Architectural changes

- Media headers use `ParsedMediaType` and Symfony `HeaderUtils`, including quoted values, ext lists, Accept quality and independent candidates. Ordinary decoding, strict negotiation, profiles and Atomic share parsing.
- Atomic validates full targets and resource operation policy, infers canonical targets, resolves generated IDs inside the transaction, and snapshots each result before later mutations.
- Write preconditions execute at `kernel.controller` using the current representation. Reads are skipped when no enabled condition is supplied or required. Internal representation methods avoid requiring SHOW permission for an allowed write. Doctrine's identity map reuses loaded models.
- Shared identifier/cardinality validation precedes resource and Atomic relationship resolution. Non-null ORM join columns are checked before mutation. VERIFY linkage reports the public `/data/relationships/.../data/id` pointer; the existing REFERENCE policy remains deliberately deferred.
- Doctrine mapped types convert external identifiers before assignment/query/reference construction. Constraint mapping surrounds scheduled flush and transaction completion, with native driver conversion through its public exception converter. Unknown failures are rethrown.
- Public attribute aliases are resolved consistently for root filter and sort paths. Filters gain Between/NullCheck/empty-set semantics and a configured depth boundary. Sorting appends ID ASC internally.
- HEAD retains GET representation bytes internally for hashing; no private representation header is emitted. Last-Modified uses configured model fields. OPTIONS is a controller service and respects to-one cardinality.
- Per-type profile contexts feed document/query/write hooks; server hook changes are applied before validation and flush.

## BC and upgrade notes

See [acceptance-gaps.md](../upgrade/acceptance-gaps.md). No Contract interface or resource attribute constructor was changed. New constructor dependencies are optional; internal DI registrations supply them. `InputDocumentValidator` gains an optional `allowLid` argument for Atomic validation. Existing exception subclasses are preserved during rebasing.

## Remaining limitations and newly discovered cases

1. **Commit-level Atomic pointer attribution.** Expected: a deferred database constraint should identify the responsible operation. Actual: 409 and full rollback are tested, and the error pointer identifies the valid `/atomic:operations` batch member rather than a specific operation, because the exception occurs after all operations. Root cause: transaction completion has no reliable offending-operation context. Next change: track constraint/entity/operation provenance through transaction completion, then rebase its public pointer accurately.
2. **Profile control query flags.** Expected: built-in `withTrashed`/`onlyTrashed` controls should be interpreted by profile hooks. Actual: ordinary filter whitelist validation occurs before hooks; undeclared controls can be rejected. Root cause: hooks cannot declare/consume their query parameters at the boundary. Next change: generic profile query-parameter declaration/preprocessing with regression coverage. Default soft-delete collection/item filtering passes.
3. **Nested target attribute aliases.** Expected: a relationship query path resolves aliases on the target metadata too. Actual: the current metadata resolver maps only the root segment; a target alias can remain in DQL. Root cause: field resolution lacks target registry traversal. Next change: a registry-aware shared resolver for filter and sort paths. Root scalar aliases pass.
4. **Related profile read coverage.** Collection and item SQL hooks are tested; related reads have implementation support but still need a dedicated PostgreSQL fixture exercising related collection pagination plus hook filtering. This is why PROFILE-002 remains partial.
5. **DBAL 4 matrix.** The PostgreSQL ILIKE regression passes with the declared DBAL 3 dev dependency. Platform detection avoids `getName()` and uses an API available in DBAL 4; no DBAL 4 dependency matrix was executed here. FILTER-004 remains partial until that matrix or external project verification.
6. **Last-Modified fallback.** Configured timestamps are used and tested. Resources without a readable timestamp retain the existing wall-clock fallback; collections only consider selected models, not removed rows. A durable collection version source is a separate capability.

## Retained skips

Six existing unsupported scenarios remain: async POST/PATCH/DELETE/relationship mutation (202), per-relationship authorization (403), and optional error `links.type`. Stale skips for resource operation restrictions and configured error `links.about` were replaced by real passing assertions.

## Quality results

- Full PHPUnit matrix: **1098 cases, 5072 assertions, 0 failures/errors, 6 skips**, with no PHPUnit/Symfony deprecations or warnings. Integration includes PostgreSQL first and the configured MySQL/MariaDB compatibility suites.
- Unit/Functional/Conformance/JsonApiStatus: 697 cases (6 skips); Integration: 401 cases (no skips). External stable/gap/full: **not run**, pending the user's branch installation.
- PHPStan, coding standards, Deptrac, Composer validation and Composer audit: passing. CS Fixer emits its existing tool-level PHP-version advisory (runtime PHP 8.4 versus composer minimum 8.2), not a source-code warning.
- Rector dry-run: **188 files suggested**, matching the baseline 188; no new suggested files after cleanup. The additional Rector gate is therefore not green globally; it is outside the current CI gate.
- Mutation gate: the first complete run produced MSI 35.45% (target 70%), covered MSI 62%, 3535 uncovered mutants, 1700 escaped covered mutants and 2 mutant process errors. Final run: **8137 mutants, 2895 killed, 3531 uncovered, 1709 escaped, 2 mutant process errors, MSI 35.6%, covered MSI 62%**; the 70% gate fails. This configuration measures Unit/Functional only, excluding the new PostgreSQL integration regressions; thresholds/exclusions were not weakened.
- Roave BC comparison (with and without dev dependencies) is **blocked by an existing source-layout issue**: `DoctrineExpression` is declared inside `src/Filter/Operator/Operator.php`, so BetterReflection cannot locate it by its PSR-4 class path in the baseline. No successful automated BC result is claimed. Contract signatures and attribute constructors were checked in the diff; new internal dependencies are optional.

Rector and mutation are additional Makefile tools. Their repository-wide remediation remains visible; this iteration does not claim a fully green comprehensive QA pipeline.
