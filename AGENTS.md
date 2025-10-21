# Repository Guidelines

## Project Structure & Module Organization
- Application code lives in `src/` under the `AlexFigures\Symfony` namespace; group new logic beside its aggregate or in focused subnamespaces (e.g., `src/Query/`).
- Tests mirror sources in `tests/Unit`, `tests/Functional`, `tests/Integration`, and `tests/Conformance`; keep fixtures adjacent to the suites that consume them.
- Shared configuration resides in `config/`, contributor docs in `docs/`, CI assets in `build/`, and helper tooling in `scripts/`. Stub doubles belong in `stubs/`.

## Build, Test, and Development Commands
- Run `composer install` or `make install` once to hydrate dependencies.
- Use `make test` for the default PHPUnit suites; `make test-all` runs the full matrix.
- Bring up integration dependencies with `make docker-up`; chain setup and teardown via `make docker-test`.
- Static analysis and automation gates: `make stan` (PHPStan level 8), `make rector`, and `make cs-fix --dry-run`. Apply formatting with `make cs-fix`.
- Deep QA options include `make mutation` (>=70% MSI) and `make qa-full` for the comprehensive pipeline.

## Coding Style & Naming Conventions
- All PHP files begin with `declare(strict_types=1);` and follow PSR-12 (4 spaces, one class per file, typed properties and parameters).
- Default classes to `final`; interfaces end with `Interface`, traits with `Trait`, and service IDs use their FQCN.
- HTTP resources expose `kebab-case`, while PHP properties and methods remain camelCase. Run `vendor/bin/php-cs-fixer fix` before pushing.

## Testing Guidelines
- PHPUnit underpins the suite; name tests after the class under test (`FooServiceTest.php`).
- Integration tests that boot Symfony or databases belong in `tests/Functional` or `tests/Integration`; use `docker-compose.test.yml` DSNs.
- Maintain coverage to satisfy Infection’s 70% mutation threshold and reuse builders in `tests/Fixtures` when available.

## Commit & Pull Request Guidelines
- Follow the `<type>: <summary>` conventional commits style (e.g., `feat: add invoice serializer`); keep summaries imperative and under 72 characters.
- Each commit should pair code with tests or docs. Reference related issues and capture breaking changes explicitly.
- PRs needs a concise problem statement, evidence of `make test` (or relevant suite) output, and any required screenshots or docs updates.

## Security & Configuration Tips
- Keep `.env` files out of version control; rely on Symfony configuration in `config/` and Docker secrets for sensitive values.
- Re-run `make install` when dependencies change and verify local `.env` matches the expected DSNs before executing integration suites.
