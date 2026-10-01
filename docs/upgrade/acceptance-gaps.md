# Installing the acceptance gap branch

Use `alexfigures/symfony-jsonapi-bundle:dev-fix/acceptance-gaps` after the branch is available in your configured VCS repository. Keep the application's original tests and assertions. Synchronize only gap markers/reports after external verification.

## Public behavior corrections

- Cache validators change because GET/HEAD/write comparisons now share a representation key. Obtain fresh ETags after upgrading; failed write preconditions no longer persist changes.
- Empty JSON objects remain objects. Send `attributes: {}` and `relationships: {}` for empty objects; `[]` is rejected. Malformed linkage is rejected before property access.
- Unknown relationships return 400, wrong linkage types return 409, required null linkage is rejected before SQL. Unique conflicts return 409; restricted FK deletes return 422.
- Atomic infers add/update targets, rejects malformed/partial hrefs and dual id/lid identifiers, enforces resource operations and client-ID policy, and emits `{}` for empty results. Results reflect each operation's completed state.
- Both `ne` and `neq` normalize to the `neq` AST operator. Empty IN matches nothing; empty NOT IN matches everything. Between requires exactly two scalar operands. Filter groups are bounded by new `jsonapi.limits.filter_max_depth` (default 8, minimum 1).
- Nonunique sorting gains an implicit ascending mapped-ID tiebreaker. Client-visible criteria remain unchanged.
- Requested empty inclusion emits `included: []`. Standalone linkage documents gain `links.related`; to-one relationship OPTIONS excludes POST and DELETE.
- Per-type default document profiles and generic query/write hooks now run during ordinary/Atomic Doctrine processing.

## API compatibility

No Contract interface, attribute constructor or existing configuration option was removed. New internal service constructor parameters are optional. Existing HTTP exception subclasses and constructor signatures are retained; pointer rebasing adds an internal error replacement method. `InputDocumentValidator.validateAndExtract()` adds an optional lid-validation flag. Stateless internal parser/cache services use readonly state.

See the [gap report](../conformance/acceptance-gap-report.md) for verified behavior and remaining limitations. External conformance completion is pending application testing.
