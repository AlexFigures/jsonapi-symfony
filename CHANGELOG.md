# Changelog

All notable user-facing changes are recorded here. Versions follow SemVer after the 1.0.0 baseline. No 1.0 release is declared by this file.

## Unreleased — 1.0 stabilization

### Added

- Reviewed PUBLIC/INTERNAL candidate contract, stable configuration/error semantics and optional extension capabilities.
- Real PHP 8.2/8.3/8.4, Symfony 7.4/8.1 and forward Symfony 8.2 compatibility lanes; lowest/current ORM 3.x / DBAL 3.x and 4.x with database integration.
- Operational isolated BC tooling, direct deprecation gates, canonical onboarding/reference and publication preparation.

### Changed

- Public root namespace to `AlexFigures\JsonApi` and Bundle to `AlexFigures\JsonApi\JsonApiBundle`; no legacy aliases.
- Symfony constraints to `^7.4 || ^8.0`; real PHP dependency resolution without a simulated development platform.
- Single-boundary Atomic preflight, guarded concurrent If-Match, bounded graph reads/filter limits, distinct-root pagination and explicit unsupported identifier diagnostics from the acceptance-gap work.
- Typed persistence/relationship dispatch, profiles, media policy, metadata and HTTP contracts hardened by regressions.
- Modern Doctrine offset pagination, compiler-log profile warnings and one legacy-media deprecation; supported persister contracts are no longer incorrectly described as deprecated.
- PHP 8.2-compatible Rector cleanup, including explicit readonly final types and modern callable syntax; public constructor argument names and defaults are retained.

### Removed

- Obsolete internal `ChangeSetFactory::fromAttributes()` alias.
- Inactive pre-1.0 configuration: `dx.*`, `errors.locale`, five Doctrine performance toggles and inert `release.*`.
- Root dependency on PHP-8.4-only BC/mutation tools; these remain isolated development tools.

Migration instructions are in [UPGRADE-1.0](UPGRADE-1.0.md). Final 1.0.0 release notes/date will be added only when release gates pass.
