# Stabilization evidence

Local verification on 2026-10-08 covered the namespace migration, deprecation cleanup and PHP 8.2 Rector changes committed as `b3bd899`. The CI database correction was committed as `773b163`; both are included in merged main `96a1530f3155ddf001b7d1e48fd33e375c382d85`. Local results and independent consumer evidence are recorded separately below.

## Bundle compatibility

Each lane resolved dependencies using its real PHP executable and isolated Composer installation, with separate PostgreSQL/MySQL/MariaDB fixtures. Full PHPUnit suites used fail-on-deprecation and fail-on-risky.

| PHP | Symfony Framework / HttpFoundation / Serializer | ORM | DBAL | Policy | Result |
| --- | --- | --- | --- | --- | --- |
| 8.2.34 | 7.4.0 / 7.4.13 / 7.4.0 | 3.0.0 | 3.8.0 | Security-aware lowest | PASS: 1321 tests, 8635 assertions |
| 8.2.34 | 7.4.20 / 7.4.20 / 7.4.20 | 3.7.4 | 3.10.6 | Current | PASS: 1321 tests, 8635 assertions |
| 8.3.35 | 7.4.20 / 7.4.20 / 7.4.20 | 3.7.4 | 3.10.6 | Current | PASS: 1321 tests, 8635 assertions |
| 8.4.26 | 7.4.20 / 7.4.20 / 7.4.20 | 3.7.4 | 3.10.6 | Current | PASS: 1321 tests, 8632 assertions |
| 8.4.26 | 8.1.8 / 8.1.8 / 8.1.8 | 3.7.4 | 4.5.0 | Current | PASS: 1321 tests, 8632 assertions |
| 8.4.26 | 8.2.x-dev / 8.2.x-dev / 8.2.x-dev | 3.7.4 | 4.5.0 | Forward development | PASS: 1321 tests, 8632 assertions |

Every lane had zero failures, errors, risky tests and direct deprecations, with four explicit internal async non-goal skips. Symfony 8.2 FrameworkBundle reference: `dae5920b457f42f67c28ed38650530de2f1b0fc4`. Development evidence remains provisional. The final OpenAPI null-coalescing adjustment passed its documentation unit suite on all six lanes (9 tests, 37 assertions each).

Strict Composer validation, security audit, PHP syntax, PHPStan with DBAL 3 and 4, deprecation scanning, CS, Deptrac, configuration drift and API inventory passed. Roave 8.23 identical-snapshot smoke passed; the stable 1.0.0 BC baseline does not exist yet. Rector dry-run was clean. Mutation testing was not run. Generated local reports were removed during repository cleanup; reproducible commands, version locks and test artifacts belong in CI evidence, not the source documentation tree.

After CI exposed incomplete database environment wiring, a follow-up corrected every host DSN to TCP `127.0.0.1`, provided both PostgreSQL variable names and added a database connection preflight. Host-network integration reruns passed 505 tests on PHP 8.2/Symfony 7.4/ORM 3.0/DBAL 3.8 (4981 assertions) and PHP 8.4/Symfony 8.1/ORM 3.7.4/DBAL 4.5 (4978 assertions), with zero errors/skips/risky tests/direct deprecations. The old MySQL `localhost` DSN reproduced a connection failure; the replacement passed all connection checks. The correction was committed as `773b163` and included in merged main `96a1530f3155ddf001b7d1e48fd33e375c382d85`.

## Independent consumer — merged implementation

The release owner confirmed on 2026-10-08 that external verification passed across all runtime/platform targets. The verified merged bundle revision is `96a1530f3155ddf001b7d1e48fd33e375c382d85` (PR #67). The example repository was read only to reconcile existing evidence; its tests/files were not changed during this documentation update.

| Recorded environment | ORM / DBAL / DoctrineBundle | Acceptance | Production | Features | Torture |
| --- | --- | ---: | ---: | ---: | ---: |
| PHP 8.2.34 / Symfony 7.4.20 | 3.7.4 / 4.5.0 / 2.19.1 | 683 PASS | 82 PASS | 280 PASS | 62 PASS |
| PHP 8.4.26 / Symfony 8.1.8 | 3.7.4 / 4.5.0 / 3.3.2 | 683 PASS | 82 PASS | 280 PASS | 62 PASS |

The records have zero skips, unexpected failures and open gaps. Both use executable-contract digest `73e5f2e934984189714e71f96df8608ece8e6ffdcf87db869d76ded3d333ed1b`. The release-gate summary records 7127 acceptance and 4185 torture assertions.

Source records are [sf74 evidence](https://github.com/AlexFigures/example-jsonapi-bundle/blob/8c9545e3daf925b44e5f9c55a22644147dc273d7/docs/compatibility-evidence/sf74.json) and [sf81 evidence](https://github.com/AlexFigures/example-jsonapi-bundle/blob/8c9545e3daf925b44e5f9c55a22644147dc273d7/docs/compatibility-evidence/sf81.json). They identify example snapshots `e2dc660b9013951090014372e65fe20cfc7912e8` and `0fb1e3da2aecb707fda5085d017937b7c13f703b`, respectively, and explicitly mark those snapshots dirty. This is passing stabilization evidence; immutable release proof also needs committed source/locks and package-specific tags.

The owner confirmed the remaining compatibility targets, including Symfony 8.2-dev, passed. No per-target versions/counts are inferred from the stable-platform records; archive that target's own machine-readable evidence during release preparation. A development run does not certify a future stable Symfony 8.2 package.

Earlier proof for bundle `a17ffd40a7d3a1e642a33aaf788427bb6b117fdb` (666 acceptance / 62 torture) is superseded for current readiness by the merged-revision evidence above. The release owner confirmed the RC tag already exists and that final publication follows merging this documentation/release branch. No additional RC cycle is planned. The recorded dev-main runs remain exact-revision stabilization evidence; verify installation of the final published package and archive immutable final-platform evidence according to [the release checklist](checklist.md) and [compatibility policy](compatibility.md).
