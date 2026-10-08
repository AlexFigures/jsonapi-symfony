# Backward compatibility policy

The reviewed [manifest](public-api-manifest.json) and source `@api` annotations define the 1.0 public contract. `@internal` types and members are implementation details. Constructor named arguments of public attributes, public value objects, interface signatures, documented service tags/aliases, active configuration and the [error contract](errors.md) form the intended 1.x compatibility surface.

Required methods are not added to existing application-implemented interfaces during 1.x. New behavior uses optional capability interfaces. Doctrine/Symfony-specific capabilities remain explicitly tied to their supported dependencies. Internal implementation replacement is allowed while observable public behavior remains compatible.

`make api-inventory` fails unclassified or stale source entries. It is an inventory check, not a signature checker. Isolated Roave tooling runs an identical-snapshot smoke comparison during stabilization. There is no stable SemVer baseline before 1.0.0; an arbitrary moving 0.x tag is not used as proof.

Once the immutable `1.0.0` tag exists, `make bc-check` compares that baseline to each 1.x PR and fails on incompatibility. Full Git history/tags and PHP 8.4 are required. Explicit `BC_BASELINE`/`BC_TARGET` can compare RC snapshots, but those are stabilization checks rather than the final 1.x promise. BC checks are not continue-on-error after the baseline exists.

Roave does not prove configuration, named argument usage, service wiring or HTTP behavior. Bundle regressions and the independent consumer cover those surfaces. Dropping a declared platform or altering required behavior needs a major-version decision. Migration from pre-1.0 is in [UPGRADE-1.0](../../UPGRADE-1.0.md).
