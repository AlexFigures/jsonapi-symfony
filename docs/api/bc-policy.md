# Backward compatibility policy

The bundle is preparing for 1.0. Public API, configuration, attributes and platform support are not yet frozen. Pre-1.0 changes may require migration; pin the release line your application has verified and read [UPGRADE-1.0](../../UPGRADE-1.0.md).

## Proposed 1.x policy

Once an audited baseline is released, public contracts include the explicitly classified PHP extension points and values, named attribute arguments, documented service aliases/tags, configuration semantics, commands and stable HTTP error fields. Internal implementations are outside this promise. Namespace membership alone does not decide stability.

Changes that break consumers require a major release. This includes adding methods to an implemented public interface, changing its parameter list even with optional arguments, removing or renaming symbols, changing named constructor arguments, and altering documented defaults or error semantics. Optional new capabilities should use separate interfaces so existing implementations continue working.

New interfaces, opt-in features and compatible implementation improvements may ship in minor releases. Bug fixes may ship in patches when they restore the documented contract; tighter input validation or changed limits need explicit compatibility review. Error `detail` is descriptive; HTTP status, error status/code, title semantics and source members require contract review.

Deprecations should identify the replacement and migration in documentation before removal in a subsequent major release. Security corrections require release notes that explain any necessary application change.

## Tooling and baseline

The current `make bc-check` compares the nearest available tag and CI permits failure. It is advisory pre-freeze evidence, not an enforced 1.0 gate. A missing tag skips that check. After freeze, use the explicit released baseline, fetch sufficient Git history and make the comparison required in CI. Signature tooling does not cover configuration/HTTP behavior; independent acceptance remains necessary.

Track classification and remaining decisions in the [API audit](../release/public-api-audit.md) and [release checklist](../release/checklist.md).
