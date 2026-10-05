# Support contract 1.0 — draft and release gate

This draft separates implemented, bundle-tested behavior from independently verified support. It does not publish 1.0, freeze public APIs or relabel failing consumer tests as limitations.

| Area | Implemented contract | Evidence / pending gate |
| --- | --- | --- |
| Persistence | Built-in Doctrine provider resolves the manager for the resource class. Unrelated managers do not participate in scoped writes. | Unit and real database regressions; external multi-manager/tenant/replica reconfirmation remains separate. |
| Atomic | One manager/connection boundary; the complete batch is preflighted. Cross-boundary operations return 409 before mutation. Flushes may occur for generated IDs/lids; one commit follows successful completion of every operation. | Real PostgreSQL/MySQL/shard preflight, unrelated commit-fault and rollback regressions. Separate managers sharing one connection are intentionally unsupported. |
| Concurrent If-Match | Protect the root row, rebuild its representation after acquiring the lock, evaluate the original validator and mutate/flush/commit inside that boundary. A stale competing PATCH makes no mutation. | Independent processes/connections wait on the same database lock; exactly one changed write succeeds and one gets 412. Wildcard retains existence semantics. Custom persistence requires its own guard. |
| Filters | Absolute AST depth/nodes/operands and weighted budget enforced before SQL. Large IN lists count every operand. | Parser/AST/configuration/query regression coverage; zero repository execution on rejection. |
| Identifiers | Built-in single integer, UUID and natural string IDs. Composite Doctrine IDs fail during discovery with a configuration diagnostic. | Discovery, UUID persistence and DTO array-binding regressions. Custom encodings/providers require application tests. |
| Native Doctrine representations | Root pagination before batched relationship reads; mapped to-one/to-many/alias linkage, includes and built-in counts have graph/chunk-dependent SQL shape. Included identities are reserved before hydration; scalar identifiers have a separate budget. | PostgreSQL/MySQL page-size comparisons, distinct roots/counts/pages, DTOs, aliases, budgets and profile counts. Independent torture remains pending. |
| Generic/custom read paths | Optional preloader preserves the existing fallback. Computed getters, opaque hooks and custom providers do not acquire automatic cost guarantees. | Application policy or a registered batch capability; these declarations need explicit coverage. |
| Relationship endpoints | Current legacy collection path is not yet a bounded-query guarantee. | Release blocker: reuse root pagination for related reads and scalar SQL pagination/count for linkage. |
| To-many sorting | Strict policy rejects traversal without a handler defining aggregate semantics. Legacy behavior is retained by default for compatibility. | Strict pre-SQL rejection and explicit MIN handler tested. Decide the final default during freeze with migration/external evidence. |

## Error surface

Stabilize HTTP status plus each corresponding JSON:API `status`; stable `code`, title semantics and source member are part of the eventual contract. Details remain descriptive. Current explicit diagnostics include `precondition-required` (428), `unsupported-transaction-boundary` (409), `included-resources-limit` (400), `relationship-identifiers-limit` (400) and `collection-sort-unsupported` (400). Existing stale precondition diagnostics retain their previous code/source behavior.

## Platform evidence and CI

The local Docker toolchain uses PHP 8.4 and the installed Symfony 7.4, Doctrine ORM 3.7 / DBAL 3.10 dependencies. PostgreSQL 16 and MySQL 8.0 execute the new read regressions; the full bundle integration environment also contains MariaDB and SQLite. These are evidence for this checkout, not a declaration that every Composer-allowed version has been verified.

CI now includes the integration databases and the real concurrent regression suite, and `make test` includes conformance/status suites. The current PHP CI matrix is still 8.4. Composer permits PHP 8.2 / Symfony 7.1, while the latest development BC tool requires PHP 8.4; isolate that toolchain and exercise the intended minimum/latest combinations before freezing a supported matrix. Add ORM/DBAL variants only with executable platform coverage. A green local run cannot stand in for a remote CI result.

## Remaining freeze work

After the external runtime gate, audit every interface, attribute, enum, value object, exception, event, hook, alias, configuration node and console command as PUBLIC or INTERNAL. Check named constructor arguments and extension return/parameter types as well as class names. Resolve namespace duplication and accidental implementation exposure before marking the API frozen. Optional new capabilities in this branch remain subject to that review.

Finish `UPGRADE-1.0.md` against the final API/config decisions. Replace the current advisory BC check with a required comparison against the actual frozen baseline once it exists; do not compare a moving pre-1.0 tag and pretend that is a 1.x guarantee. Run an RC without adding major features; fix contract defects before final.

## Non-goals

No distributed transactions or 2PC, replication management/independent-GET lag compensation, sharding implementation, tenant framework or global deadlock retry. Applications own topology, indexes, authorization policy, custom data sources and concurrency outside the protected resource boundary.
