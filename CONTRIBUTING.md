# Contributing

Work on the bundle independently from the example acceptance application. Bundle regressions verify implementation; the consumer runs its own black-box gate against an identified revision. Do not change consumer expectations to hide bundle behavior.

## Setup and checks

Install dependencies with Composer on a compatible development PHP version. The current toolchain uses PHP 8.4; see [compatibility evidence](docs/release/compatibility.md). Use [TESTING](TESTING.md) for Docker database and full-suite commands.

```bash
make test
make stan
make deptrac
vendor/bin/php-cs-fixer fix --dry-run --diff
make docs-check
make api-inventory
```

`make cs-fix` applies formatting; `make rector` applies Rector changes. For review without changes, run `vendor/bin/rector process --dry-run`. Mutation testing is available through `make mutation` with coverage enabled. Run checks appropriate to the change and record actual results, skips and limitations.

Code belongs in focused namespaces under `src`; tests mirror it in Unit, Functional, Integration and Conformance. Use strict types and typed APIs. Add meaningful regression coverage for changed behavior, especially transaction boundaries, concurrent writes, scope and budgets.

Use conventional commits and explain the problem, resulting behavior and validation. Document consumer migrations when changing contracts/defaults. Public/internal classification is still being audited; do not infer completed freeze from a namespace or old annotation.

[Developer path](docs/guide/developer-path.md) · [API audit](docs/release/public-api-audit.md) · [release checklist](docs/release/checklist.md).
