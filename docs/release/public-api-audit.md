# Public API audit preparation

Inventory can begin while runtime verification proceeds. Classification and cleanup decisions remain open until the runtime contract is confirmed; existing annotations do not constitute a completed freeze.

Generate the current source inventory:

```bash
make api-inventory
```

The dependency-free scanner writes `reports/public-api-inventory.json`. CI publishes it as an artifact. It lists source symbols, kinds, paths, explicit annotation occurrences and public method names. This is a lexical inventory, not a PHP signature/BC checker: method-level annotations may coexist with class-level annotations, inherited/promoted members and named/default arguments still need review. Unmarked entries are REVIEW, never automatically INTERNAL.

## Review groups

| Surface | Review | Status |
| --- | --- | --- |
| Data and transaction contracts | Existing implementers, optional capabilities, generic providers, Atomic versus single write | Pending |
| Attributes and enums | Constructor argument names/order/defaults, enum cases, operations/projections, future extension | Pending |
| Values and metadata | Mutability, serialization, types used by public signatures, constructor defaults | Pending |
| Profiles/hooks | DI lifecycle, type scope, activation/defaults, fetch declarations, resource meta | Pending |
| Filters/sorts/relationships | AST exposure, handler composition, aggregate semantics, scope and batch budgets | Pending |
| Repository decorator query capability | Current internal Doctrine query projection, outer scope/guards, explicit forwarding and portable extension decisions | Pending |
| Cache strategies | Version source, missing validators, representation variation, precondition extension points | Pending |
| Exceptions/events | Public throwable/event types, inheritance, properties and dispatch behavior | Pending |
| Symfony aliases/tags | Intended service replacement points, FQCN/tag names, autoconfiguration and priority | Pending |
| Configuration | Defaults, normalization, zero/unlimited semantics, deprecated nodes, per-type keys | Pending |
| Console commands | Registration, arguments/options, exit codes and documented output semantics | Pending |
| HTTP errors | Status agreement, stable code/title semantics and source fields; descriptive detail | Pending |

For each symbol or non-PHP surface, record PUBLIC or INTERNAL, the rationale, extension example/tests and required migration. Resolve conflicting annotations. Check all transitive types of a public signature; marking an interface PUBLIC while leaving its required value classes unclassified is incomplete.

Use separate optional interfaces to add capabilities to implemented contracts in 1.x. Adding a required method, or an optional parameter to a method consumers implement, can break implementations. Prefer reviewed extension points over exposing an entire bridge namespace. Preserve existing documented use until the cleanup and migration decision is explicit.

## Freeze outputs

- Reviewed inventory and explicit annotations agree.
- Namespace moves and deprecated/removed symbols are listed in UPGRADE-1.0.
- Configuration, aliases, commands and error contract have corresponding tests.
- Required BC tooling compares the actual frozen release baseline.
- Independent acceptance still protects behavior and configuration beyond signature checking.

The [extension index](../api/public-api.md) is the developer-facing starting point. The [release checklist](checklist.md) tracks the gate; this audit does not change runtime code or mark new capabilities stable yet.
