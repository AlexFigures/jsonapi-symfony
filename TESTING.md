# Bundle testing

Bundle tests and the example acceptance application run independently. This guide covers this repository only. A green bundle run does not certify external acceptance.

## Local core suites

Install dependencies on a compatible development PHP version, then run:

```bash
make test
```

The target includes Unit, Functional, Conformance and JsonApiStatus suites. `make test-unit` and `make test-functional` select narrower suites. `make test-all` includes Integration and needs database services. Core dependencies resolve on real PHP 8.2+. BC/mutation tools install separately on PHP 8.4; see [compatibility evidence](docs/release/compatibility.md).

## Real database and concurrency suites

Start the repository's own PostgreSQL, MySQL, MariaDB and PHP environment:

```bash
make docker-up
```

Run the full bundle suite inside its PHP container, including the PostgreSQL DSN consumed by the concurrency regressions:

```bash
docker compose -f docker-compose.test.yml exec -T \
  -e DATABASE_URL_PGSQL=postgresql://jsonapi:secret@postgres:5432/jsonapi_test \
  php vendor/bin/phpunit --log-junit reports/bundle.xml
```

For only integration tests, append `--testsuite=Integration`; for read budgets, select `tests/Integration/ReadPath`. The cold-cache read tests compare root page sizes and check scope, pagination, linkage/includes and profile cost. Concurrent write tests use independent processes/connections, not one EntityManager pretending to race.

Stop services when no longer needed. `make docker-down` removes the test volumes, so use it only when those test databases can be discarded. Generated JUnit files are ignored local artifacts; CI uploads its integration results.

## Analysis and documentation

```bash
make stan
make deptrac
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/rector process --dry-run
make docs-check
make api-inventory
```

Run PHP tools inside the test container if host PHP is incompatible with installed dependencies. `make mutation` needs coverage and is advisory/nightly: its current scope excludes database integrations, so no arbitrary historic 70% release gate applies. `make bc-check` becomes blocking against the actual `1.0.0` tag; before that tag, the required BC smoke proves operational tooling, not a SemVer guarantee.

Documentation checks validate maintained local links. The inventory rejects unclassified/stale symbols and mismatched source annotations; it does not replace signature BC checking. Record exact commands, commit, environment and results in release evidence; avoid replacing failure evidence with unsupported feature claims.


## Platform lanes

Run Composer resolution in an isolated checkout with `python3 scripts/prepare-compatibility.py 7.4` (or `8.1`, `8.2-dev`), then `composer update --with-all-dependencies`; add `--prefer-lowest` for the PHP 8.2 minimum lane. Do not pin root Composer platform to a pretend PHP version. The helper uses DBAL 3 for Symfony 7.4 and DBAL 4.3+ for Symfony 8.x.

Run `php scripts/resolved-versions.php` and `vendor/bin/phpunit --fail-on-deprecation --display-deprecations --fail-on-risky` for exact dependency evidence and zero direct Symfony deprecations. PHPUnit source filtering ignores unrelated transitive deprecations. Four async-status scaffolding tests remain explicitly skipped: async mutation is outside the 1.0 contract. No other skip is expected.

Host-run integration tests use TCP addresses (`127.0.0.1`), with PostgreSQL on 5432, MySQL on 3306 and MariaDB on 3307. `localhost` is unsuitable for PDO MySQL because it selects a Unix socket instead of the published container port. CI explicitly provides both PostgreSQL variable names (`DATABASE_URL_POSTGRES`, `DATABASE_URL_PGSQL`) and the MySQL/MariaDB/SQLite URLs, then runs `php scripts/check-test-databases.php` before PHPUnit. The check fails immediately for missing variables or unavailable connections. Container runs use service hostnames and internal ports from Compose instead.

Local container lanes can use `docker-compose.compat.yml`, `COMPAT_PHP`, `COMPAT_WORKSPACE` and distinct `COMPAT_DATABASE` names after creating those test databases. Never run concurrent schema-mutating lanes against the same database. SQL observation uses DBAL logging middleware shared by DBAL 3/4; the test suite no longer depends on removed SQLLogger APIs.

Check generated configuration with `php scripts/configuration-reference.php --check` and execute BC smoke with `sh scripts/bc-smoke.sh`. Remote required jobs and independent platform evidence must be checked separately on the final SHA.
