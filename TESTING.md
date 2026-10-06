# Bundle testing

Bundle tests and the example acceptance application run independently. This guide covers this repository only. A green bundle run does not certify external acceptance.

## Local core suites

Install dependencies on a compatible development PHP version, then run:

```bash
make test
```

The target includes Unit, Functional, Conformance and JsonApiStatus suites. `make test-unit` and `make test-functional` select narrower suites. `make test-all` includes Integration and needs database services. Current development tools are resolved for PHP 8.4; see [compatibility evidence](docs/release/compatibility.md).

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

Run PHP tools inside the test container if host PHP is incompatible with installed dependencies. `make mutation` needs coverage and checks the configured 70% thresholds. `make bc-check` is currently advisory against an available tag; it is not the frozen 1.0 baseline gate.

Documentation checks validate maintained local links. The inventory is advisory and does not prove BC. Record exact commands, commit, environment and results in release evidence; avoid replacing failure evidence with unsupported feature claims.
