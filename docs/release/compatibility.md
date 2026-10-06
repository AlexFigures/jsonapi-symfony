# Compatibility evidence before 1.0

This page records repository configuration, not a claim that every allowed combination passes. Update it from Composer resolution and CI evidence before freezing support.

| Layer | Current configuration | Verified support decision |
| --- | --- | --- |
| Runtime PHP | `composer.json` allows `^8.2` | Minimum not yet validated as a release target |
| Symfony components | Runtime constraints `^7.1`; development cache/var-exporter `^7.3` / `^7.1` respectively | Exact minimum/latest combinations pending |
| Development platform | Composer platform PHP 8.4.12; CI PHP 8.4 | One configured PHP lane; remote result must be inspected |
| Doctrine ORM / DBAL | Development requirements `^3.0` / `^3.8`; built-in provider is optional to consumers | Minimum/current pairings pending |
| PostgreSQL | Docker image 16 | Included in bundle integration CI |
| MySQL | Docker image 8.0 | Included in bundle integration CI |
| MariaDB | Docker image 11 | Available in integration environment; inspect suite/platform coverage separately |
| SQLite | Used by some tests | Not a substitute for PostgreSQL/MySQL concurrency evidence |

Sources: [Composer constraints](../../composer.json), [CI workflow](../../.github/workflows/ci.yml), [database environment](../../docker-compose.test.yml).

## Matrix preparation

1. Choose intended runtime minimum/current targets after resolving dependencies. Do not infer versions from an old README badge.
2. Isolate newer development tools, particularly BC tooling, so they do not prevent installing the intended runtime minimum. Review Composer's platform override; resolution pretending to use PHP 8.4 is not a PHP 8.2 test.
3. Run minimum and current dependency combinations with relevant PHP/Symfony versions. Inspect deprecations and test skips as well as failures.
4. Exercise declared ORM/DBAL combinations against real PostgreSQL/MySQL paths, including locks, pagination, UUID conversion and rollback.
5. Record CI evidence for each combination, then declare only regularly exercised targets. Verify independent consumer behavior separately.

No dependency constraints or new supported platform versions are declared by this documentation pass. New release support requires executable CI evidence; removing a declared 1.x target later requires compatibility review.
