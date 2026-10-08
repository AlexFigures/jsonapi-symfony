# Documentation maintenance

[docs/index.md](../index.md) is the canonical entry point. Topic guides explain the current behavior; the reference defines configuration/support guarantees; API pages define extension and error contracts. Release material is for maintainers and remains outside onboarding.

`make docs-check` discovers root Markdown and all documentation pages automatically and checks local link targets. The configuration reference is generated from the real Symfony tree and checked for drift. Examples must use public APIs and supported named arguments. Runtime code, reviewed API inventory and regression tests are authoritative.

Keep one current document for each topic. Remove obsolete implementation reports and abandoned proposals instead of linking users to historical instructions. Update navigation, migration notes and tests together when behavior changes. Store generated logs/reports outside version control; CI publishes them as artifacts.

Platform claims need exact-revision bundle CI and independent application evidence. Counts are not conformance percentages. Remaining publication/evidence updates belong in the [release checklist](checklist.md), not a parallel TODO plan.
