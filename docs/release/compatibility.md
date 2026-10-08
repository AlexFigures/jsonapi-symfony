# Platform policy and evidence

One JsonApiBundle 1.x implementation serves all supported Symfony lines. Composer resolution is necessary but does not constitute official support. A platform moves to SUPPORTED only after bundle compatibility CI and the independent version-pinned example application both pass.

| CI target | Dependency policy | Release status |
| --- | --- | --- |
| PHP 8.2 + Symfony 7.4 LTS | Lowest supported and current | Blocking bundle lanes; external platform proof required |
| PHP 8.3 + Symfony 7.4 | Current | Blocking bundle lane; same Symfony application contract |
| PHP 8.4 + Symfony 7.4 | Current | Blocking bundle lane |
| PHP 8.4 + Symfony 8.1 | Current | Blocking bundle lane; external platform proof required |
| PHP 8.4 + Symfony 8.2-dev | Development branch | Visible advisory forward lane until release |
| PHP 8.4 + Symfony 8.2 stable | Current, after release | Automatically blocking; external platform proof required |

Symfony 8.x components require PHP 8.4.1+. Package runtime requirements are PHP `^8.2` and Symfony `^7.4 || ^8.0`; the wider resolvable range does not advertise untested Symfony series. As of 2026-10-08 [Symfony 8.2 is under development](https://symfony.com/releases/8.2); CI checks the published Composer release metadata and promotes its lane after release.

Doctrine is optional. The built-in provider's minimum lane resolves ORM 3.0 / DBAL 3.8; Symfony 7.4 current lanes resolve current 3.x releases, Symfony 8.x current lanes resolve DBAL 4.x. Real PostgreSQL 16 and MySQL 8.0 suites run in every blocking lane. MariaDB 11 is exercised in the existing database suite, not a promise for all MariaDB releases. SQLite tests are not concurrency proof. Symfony 8.1/8.2 lanes explicitly install DBAL 4.3+; current HttpFoundation conflicts with DBAL below 4.3. DBAL 4 support still requires both successful bundle integration and external platform evidence.

The CI artifacts contain exact PHP, Symfony component, ORM and DBAL versions, Composer lock and PHPUnit report. Security-aware lowest resolution may select patched versions above the nominal minimum. ORM 3.0 pulls abandoned transitive `doctrine/cache`: the minimum-API lane reports that notice while blocking security advisories; current lanes retain the default audit policy. Use current maintained dependencies in new applications. Root Composer has no simulated PHP/ext-sodium platform. PHP-8.4-only BC/mutation tools install separately under `tools/`.

Use [the workflow](../../.github/workflows/ci.yml), [compatibility preparer](../../scripts/prepare-compatibility.py) and [local result record](verification.md). Local results do not stand in for unexecuted remote jobs.

## Independent compatibility fixtures

The external repository owns `compat/symfony-7.4-php-8.2`, `compat/symfony-8.1-php-8.4` and `compat/symfony-8.2-php-8.4`. Each fixture is a real application with exact Symfony series, target PHP, released/RC bundle through Composer, committed lock and the same black-box contracts. This repository does not create or modify those branches.

Immutable example evidence tags identify bundle release and platform, for example `bundle-1.0.0-rc1-sf74` / `sf81` / `sf82`, followed by corresponding final-release tags. Each record includes bundle tag/SHA, example tag/SHA, PHP, Symfony, ORM, DBAL, acceptance/torture results and date. Final stabilization SHA evidence precedes RC creation; RC evidence is a separate rerun.

## New Symfony releases

Add a bundle CI lane, run the version-pinned external application, fix compatibility when necessary, then mark supported. Compatible new Symfony lines need not force a bundle code release or a new major. Required changes ship as ordinary 1.x patches/minors while preserving the public contract. Separate Symfony-specific bundle products are not the default architecture.
