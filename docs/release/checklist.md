# 1.0 release checklist

Status: preparation in progress. No release or public API freeze is declared here. Bundle evidence and example-app evidence are collected independently and must identify exact commits.

## Work that can proceed during external verification

- [x] Provide one developer path and remove obsolete setup instructions and duplicate reports.
- [x] Remove unsupported entry-point claims about fixed SQL cost, conformance percentages and broad tested compatibility.
- [x] Document implemented production boundaries and application responsibilities.
- [x] Add maintained-document local link checking and a reproducible PHP API inventory.
- [ ] Complete the [documentation TODO](documentation-todo.md), including executable topic examples.
- [ ] Review the API inventory: named parameters, defaults, optional capabilities and transitive value types.
- [ ] Audit configuration defaults/validation, aliases/tags, commands and error fields.
- [ ] Prepare compatibility jobs from actual Composer resolution; separate development-tool requirements from runtime targets.
- [ ] Keep the migration draft aligned with each accepted contract change.

## Runtime evidence gate

Record bundle commit, example commit, commands, environment, result artifacts and remaining classifications for each run. A historical report or green local suite is not independent evidence. The latest [prefix/DTO/decorator report](../architecture/rc-route-version-decorator-gaps.md) supplements the [eight-gap pass](../architecture/rc-final-eight-gaps.md). Prefix and DTO fixes have new bundle regressions; consumer confirmation and the decorator performance integration remain pending.

| Contract | Bundle evidence to inspect | Independent evidence |
| --- | --- | --- |
| Atomic preflight, rollback and lid flushes | Transaction manager unit tests and Atomic integration suites | Pending latest consumer run |
| Concurrent If-Match | Two-process/two-connection concurrency integration suite | Pending latest consumer run |
| Filter limits, identifiers and composite discovery | AST/parser tests and discovery integration suite | Pending latest consumer run |
| Bounded graph reads and distinct-root pages | PostgreSQL/MySQL ReadPath suites, including cold 5/20 root comparisons | Pending latest consumer run |
| Related/linkage endpoints and scope | ReadPath endpoint/scope/budget regressions | Pending latest consumer run |
| Profiles, DTOs, typed persistence and cache | Functional Regression kernel tests and profile integration suite | Pending latest consumer run |
| Replica/tenant/shard routing and failure recovery | Relevant adapter/transaction regressions | Pending consumer topology/fault scenarios |

External outcomes may be PASS, SUPPORTED LIMITATION, APPLICATION POLICY or INFRASTRUCTURE LIMIT. A red assertion cannot become a limitation without an explicit diagnostic, rationale and tested contract. ATOMIC-VALIDATION-BOUNDARY (409 versus 422, with rollback intact) remains a separately classified behavior to review before freezing error semantics.

## Freeze gate, after runtime confirmation

- [ ] Support contract ties every guarantee to both evidence sources and states limitations.
- [ ] Every audited surface is PUBLIC or INTERNAL; conflicting/unmarked annotations resolved.
- [ ] Final namespace/interface/attribute cleanup and migrations complete.
- [ ] Configuration names, hierarchy, defaults and validation frozen.
- [ ] Error status/code/title/source semantics frozen.
- [ ] Supported PHP/Symfony/Doctrine/database combinations run regularly in CI.
- [ ] Required BC comparison uses the actual frozen baseline; no advisory bypass.
- [ ] UPGRADE-1.0 covers all accepted migrations with Before / After / Why / Migration.
- [ ] Focused guides/examples match the frozen behavior; obsolete material removed and documentation TODOs resolved.

## RC and final

- [ ] Cut an RC only after the above gates; no major feature additions during RC.
- [ ] Run bundle checks and independently verify the consumer against the RC artifact.
- [ ] Resolve RC contract defects and update migrations before final.
- [ ] Confirm packaging contents, changelog, version metadata and release artifacts.
- [ ] Publish 1.0 only after all release gates are satisfied.

Non-goals: distributed transactions, replication management, sharding implementation and a tenant framework. See [production policies](../guide/production-policies.md).
