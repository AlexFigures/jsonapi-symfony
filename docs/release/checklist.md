# 1.0 release checklist

The implementation is merged and externally verified, and the RC tag already exists (confirmed by the release owner). Merge the final documentation/release branch, then publish `1.0.0`; no new RC cycle is planned. Evidence is recorded in [verification](verification.md).

## Completed preparation

- [x] Known runtime correctness gaps resolved; no open P0/P1/P2 gaps in the verified consumer.
- [x] PR #67 merged to main as `96a1530f3155ddf001b7d1e48fd33e375c382d85`.
- [x] Public API, attribute arguments, configuration and error semantics reviewed; namespace decisions and migration guide complete.
- [x] PHP/Symfony compatibility matrix, Doctrine minimum/current lanes and PostgreSQL/MySQL/MariaDB integration established.
- [x] Composer, tests, direct deprecations, PHPStan, CS, Deptrac, audit, documentation and API inventory gates established.
- [x] All six local compatibility lanes passed 1321 tests each; CI database transport correction passed 505 integration tests on each of its minimum/current lanes.
- [x] Independent stabilization verification passed Symfony 7.4/PHP 8.2 and Symfony 8.1/PHP 8.4; recorded results are 683 acceptance, 82 production, 280 features and 62 torture PASS on each.
- [x] Release owner confirmed external verification across all compatibility targets, including the Symfony 8.2-dev forward target. Development evidence does not declare a stable 8.2 release supported.
- [x] Canonical developer guide, configuration/support references and publication metadata prepared.
- [x] BC tooling operational; immutable `1.0.0` becomes the required baseline when released.
- [x] Existing RC tag published, as confirmed by the release owner.

## Final publication

- [ ] Confirm required CI is green on the final documentation/release commit and retain its run links.
- [ ] Archive exact per-platform example SHAs, committed locks and reports; stabilization snapshots marked dirty are diagnostic proof rather than immutable package evidence.
- [ ] Inspect the final Composer distribution: runtime/configuration and user documentation included; development artifacts excluded.
- [ ] Merge the final documentation/release branch to main.
- [ ] Publish `1.0.0`; set the changelog date/comparison links and verify Packagist metadata.
- [ ] **External:** verify installation of the published final package and record committed locks/reports and immutable final-release evidence tags, such as `bundle-1.0.0-sf74` and `bundle-1.0.0-sf81`.
- [ ] Verify Symfony-hosted documentation rendering/registration.
- [ ] Confirm future 1.x PRs enforce BC against immutable `1.0.0` without continue-on-error.

Promote Symfony 8.2 to a supported stable target only after its stable release passes both required bundle CI and published-package consumer verification. See [compatibility](compatibility.md), [support contract](../reference/support-contract.md), [publication](publication.md) and [BC policy](../api/bc-policy.md). Mutation remains advisory; no runtime feature expansion belongs in this release phase.
