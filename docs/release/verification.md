# Stabilization evidence

Local verification was performed on 2026-10-08 for the uncommitted candidate on `fix/acceptance-gaps`, based on `b5e5e3ccbc96954e6b66fee40c931dac651ba00a`. It includes the namespace migration, deprecation cleanup and PHP 8.2 Rector changes. This is not immutable release evidence or a remote CI result.

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

After CI exposed incomplete database environment wiring, a follow-up corrected every host DSN to TCP `127.0.0.1`, provided both PostgreSQL variable names and added a database connection preflight. Host-network integration reruns passed 505 tests on PHP 8.2/Symfony 7.4/ORM 3.0/DBAL 3.8 (4981 assertions) and PHP 8.4/Symfony 8.1/ORM 3.7.4/DBAL 4.5 (4978 assertions), with zero errors/skips/risky tests/direct deprecations. The old MySQL `localhost` DSN reproduced a connection failure; the replacement passed all connection checks. Remote CI still needs a rerun on the correction's committed SHA.

## Independent consumer

The separate [example application](https://github.com/AlexFigures/example-jsonapi-bundle) verified bundle `a17ffd40a7d3a1e642a33aaf788427bb6b117fdb`: acceptance 666/666, 6806 assertions; torture 62, 4185 assertions; production subset 76 and features subset 273 pass. There were no failures/skips or open P0/P1/P2 runtime gaps on that revision.

That evidence does not cover later stabilization changes. The example repository was neither modified nor tested during this task. The final committed SHA needs required remote CI and separate platform-specific consumer verification, then the same verification against the RC. Record immutable release/platform evidence according to the [release checklist](checklist.md) and [compatibility policy](compatibility.md).
