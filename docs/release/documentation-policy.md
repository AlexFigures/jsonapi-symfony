# Documentation maintenance

Current application documentation starts at the developer path. Old guides and duplicate reports are removed rather than exposed as an alternate onboarding path. Incomplete topics have explicit TODOs in their current pages and the [documentation work list](documentation-todo.md).

## Automated checks

```bash
make docs-check
make api-inventory
```

The documentation manifest lives in `scripts/maintained-docs.txt`. Link checking validates local inline Markdown destinations, including source links, on its current pages. It skips fenced examples and external URLs; fragment IDs, reference-style links, remote content and example execution are outside its scope. A passing link check is not proof that an example works.

Validate configuration examples with the real Symfony Configuration processor. Validate PHP snippets for syntax, then add executable fixtures before presenting them as complete application recipes. Documentation changes that alter promised behavior need corresponding runtime evidence and migration review.

## Evidence and local artifacts

Recent architecture/gap reports are internal implementation evidence while independent consumer verification is pending. Keep their exact results and limitations distinguishable from current support guarantees. Consolidate durable requirements into the guides after confirmation; stale reports can then be removed.

JUnit, inventories, analysis output and logs belong in ignored local files or CI artifacts. Do not remove unrelated untracked notes, diagrams or reports while cleaning tracked documentation. They may contain unpublished working material.

## Final synchronization

Once runtime evidence and the API/configuration audit are complete, finalize support guarantees, compatibility evidence and the migration guide. Replace provisional language only when its gate has evidence. [Release checklist](checklist.md) tracks that work; documentation preparation alone does not make 1.0 ready.
