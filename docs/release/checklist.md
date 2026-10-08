# 1.0 release gate

No release/tag/publication is performed by this stabilization task. `[x]` records repository preparation, not an unexecuted CI/external result.

- [x] Known runtime correctness gaps closed on the independently tested revision.
- [x] Revision-specific external evidence recorded: bundle `a17ffd40a7d3a1e642a33aaf788427bb6b117fdb`; acceptance 666/666, 6806 assertions; torture 62, 4185 assertions; zero failures/skips, zero open P0/P1/P2 gaps. Production subset 76 and features subset 273 also pass.
- [x] Public candidate classified; optional capabilities and transitive DTOs deliberately public; namespace decisions resolved.
- [x] Active configuration and error contract documented; inert options removed with migration notes.
- [x] Real PHP/Symfony Composer resolution and blocking compatibility matrix established; BC/mutation tools isolated.
- [x] Lowest/current ORM 3 / DBAL 3 and 4 lanes and real PostgreSQL/MySQL integration configured.
- [x] Direct Symfony deprecation gate, strict Composer, tests, PHPStan, style, Deptrac, security, docs and inventory gates configured.
- [x] BC smoke is executable; real `1.0.0` baseline becomes required once it exists.
- [x] Canonical docs, onboarding, upgrade/BC guide and packaging/publication preparation provided.
- [x] Namespace, deprecation cleanup and Rector candidate pass all six full compatibility lanes (1321 tests each). Quality checks, security audits and operational BC smoke pass; exact stages/versions/results are recorded in [local verification](verification.md).
- [ ] Inspect all remote required CI jobs on the exact stabilization SHA; reconcile [local verification](verification.md) and any remaining compatibility failures.
- [ ] Merge reviewed stabilization to main.
- [ ] **External:** verify final stabilization SHA on Symfony 7.4/PHP 8.2 fixture.
- [ ] **External:** verify final stabilization SHA on Symfony 8.1/PHP 8.4 fixture.
- [ ] **External:** verify Symfony 8.2 fixture when stable/relevant; stable 8.2 becomes blocking, development evidence is provisional.
- [ ] Record exact bundle/example SHAs, PHP/Symfony/ORM/DBAL, acceptance/torture results and date for every fixture.
- [ ] Cut `1.0.0-RC1` after platform gates; announce no final stable release yet.
- [ ] **External:** install the RC through Composer in version-pinned fixtures with committed locks; rerun all supported platforms.
- [ ] Create immutable external evidence tags identifying release plus platform (for example `bundle-1.0.0-rc1-sf74`); tags belong to the example repository.
- [ ] Fix RC defects only; repeat affected evidence for changed revisions.
- [ ] Release `1.0.0`; update changelog/date and comparison links, platform status and publication registrations.
- [ ] Verify Packagist metadata and Symfony-hosted documentation rendering; update compatibility wording after stable Symfony 8.2 promotion.
- [ ] Confirm all subsequent 1.x PRs fail BC changes against immutable `1.0.0`, with no continue-on-error.

See [compatibility and external fixture policy](compatibility.md), [support contract](../reference/support-contract.md), [publication](publication.md) and [BC policy](../api/bc-policy.md). Official support is bundle CI **plus** external proof, not Composer allowance alone. Mutation is advisory until its metric represents the intended surface; Rector recommendations are advisory and do not justify broad mechanical rewrites.
