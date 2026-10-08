# Publication preparation

Packagist should register `alexfigures/symfony-jsonapi-bundle` from this GitHub repository and follow immutable SemVer tags. `type: symfony-bundle`, runtime PSR-4, MIT license, support links and the `dev-main: 1.x-dev` alias are package metadata, not a stable-release declaration. No `version` is hardcoded in Composer.

The new root Bundle follows Flex’s generated-recipe heuristic and is automatically activatable. Consider a contrib recipe only if the maintainers decide that a standard route import/config should be generated; such a recipe needs independent fresh-application verification.

`.symfony.bundle.yaml` points at the existing `main` branch and the RST publication landing page under `docs/symfony`. It does not invent a nonexistent stable branch. Register the bundle documentation with Symfony and verify rendering externally. After an actual maintained `1.x` branch exists, add it and set its current/maintained branch deliberately. The Markdown developer manual remains canonical; the RST entry links to it.

Composer distributions exclude tests, CI, local tools/reports, analysis configs and contributor scripts. Runtime `src/`/`config/` and useful documentation remain included. Inspect the final Git archive after committing and before tagging; export-ignore cannot prove uncommitted files are in a release artifact.

Publication, evidence tags, branch creation and RC/final tags are separate owner-controlled steps in [the release gate](checklist.md).
