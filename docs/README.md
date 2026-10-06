# Documentation

The application entry point is the [developer path](guide/developer-path.md). It connects setup, normal API use and extension choices without requiring implementation reports.

## Application documentation

- [Developer path](guide/developer-path.md): install, expose a resource, query and write it.
- [Guide index](guide/README.md): focused references and recipes.
- [Production policies](guide/production-policies.md): transactions, concurrency, bounded reads and application responsibilities.
- [Configuration](guide/configuration.md): current defaults and pending reference sections.
- [Extension contracts](api/public-api.md): current contracts and candidate public surface.
- [Upgrade to 1.0](../UPGRADE-1.0.md): migration draft, updated as decisions become final.

## Release and contribution

- [Release checklist](release/checklist.md): what can proceed now and what depends on external evidence.
- [Compatibility evidence](release/compatibility.md): Composer constraints, actual CI jobs and pending matrix work.
- [API audit](release/public-api-audit.md): inventory generation and freeze review.
- [Documentation maintenance](release/documentation-policy.md) and [documentation TODO](release/documentation-todo.md).
- [Testing](../TESTING.md), [contributing](../CONTRIBUTING.md) and [conformance evidence preparation](conformance/spec-coverage.md).

## Implementation evidence

These documents explain implementation and regression coverage; they do not certify a release:

- [Support contract draft](architecture/support-contract-1.0-draft.md).
- [Read-path stabilization](architecture/read-path-stabilization.md).
- [Relationship scopes and extension costs](architecture/relationship-graph-reads.md).
- [Latest eight consumer gaps](architecture/rc-final-eight-gaps.md).
- [Earlier extension gaps](architecture/rc-extension-gaps.md).
- [Correctness pass](architecture/acceptance-second-pass.md).
- [Roadmap](architecture/stable-1.0-roadmap.md).

Obsolete guides, sample implementations and historical integration reports have been removed. Current unfinished topics are tracked as TODOs. Independent example-app verification remains outside this repository's development run.
