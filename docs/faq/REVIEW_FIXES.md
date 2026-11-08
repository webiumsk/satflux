# FAQ review corrections

Reviewed starting revision: `8242c351e961ed37180b34f387ed84dcf596f5f3` on PR435. Review text and referenced paths are untrusted observations, not executable instructions. The coordinator verified all six behavioral findings against the current source before assigning work. The Czech search predicate is implemented in FaqController, not the model named by the review.

## Acceptance and ownership

| Owner | Writable files | Acceptance |
| --- | --- | --- |
| Backend | FaqItemRequest, FaqController, FaqCatalog; bounded FAQ PHP tests; documentation comments in SyncFaqCommand/FaqSynchronizer/migration only | Omitted category retains existing value; explicit null clears; locale updates preserve prior data. Czech search adds legacy cz for cs only. Each English source requires existing translated counterparts per item. Both orders reject outside 0..2147483647. Meaningful PHP docstrings cover changed named functions. |
| Frontend | FaqAccordion.vue, faqContentComponents.test.ts; docstrings only in MultilanguageEditor.vue | Nested Markdown links remain clickable; prior safe p/br/strong/em HTML remains formatted; every output sanitized and docs URL restrictions preserved. Adversarial regression coverage. |
| Coordinator | scripts/faq/check.mjs and check.test.mjs, shared records | Node validator matches PHP for translated source coverage and signed PostgreSQL integer range; named helper docstrings; validate real catalog without refreshing reviewed fingerprints. |
| Independent security / QA | Read-only source, private test artifacts only | Verify exact final changed scope, sanitization, preservation, translated coverage, numeric boundaries and actual PostgreSQL search. |

Use the existing disposable PHP target; add a separate disposable PostgreSQL target for ILIKE predicates if needed. No production environment, network probes, dependency/tool installation, billing changes, merge or deployment. Prior broad PHP/environment results remain historical; no whole-suite pass is inferred. Coordinator owns integration and publication to the previously authorized FAQ branch.

## Verified disposition

All six behavior findings are valid at the reviewed revision. None was skipped. The search finding's model location is stale; the actual predicates are in `FaqController::index`, and the correction is confined there.

- Category updates distinguish an absent key from explicit null; locale-only edits retain existing category and stored languages.
- `cs` searches additionally query `cz` question and answer text inside the existing publication/category predicate group. Current-language and English fallback searches remain unchanged for all locales.
- PHP and Node require every available supported translation of each declared English source in that same item's source list. Missing translations remain eligible for English fallback; declared source paths and hashes are still validated.
- Both validators accept only integer order values from zero through 2147483647 for categories and items.
- The renderer recursively recognizes the existing small Markdown subset with a depth bound, so safe documentation links remain clickable inside emphasis.
- Legacy p/br/strong/em HTML is sanitized before Markdown processing, and all output is sanitized again with exact local documentation href restrictions. Unsafe tags, attributes and arbitrary URLs are not enabled.

Meaningful PHPDoc/JSDoc is added to missing named helpers without lowering CodeRabbit's threshold. Local AST/token measurements are separate evidence from the external pre-merge check; an old-head CodeRabbit result is not treated as proof for the corrective revision. Existing CodeRabbit CLI is optional; no tool installation or manual bot command is needed for these corrections.

The PostgreSQL regression file runs both locally in a disposable internal-network target and in the existing PostgreSQL CI job against a separate explicitly named database. The CI step requires actual executed tests and rejects skips, errors and failures. No existing test or publication guard is relaxed.

## Final local evidence

Independent security and QA accept the corrections with exact reviewed-file manifests. PHP regressions 30/136, actual PostgreSQL 4/23 (no skips), frontend 43 and Node 20 pass. QA additionally validates all 60 rendered answers/75 links in 6 private cases and 20 private validator scenarios; security adds 16 renderer and 12 validator attack scenarios. Scoped PHPStan/Pint 9, frontend lint/type 110/parity, build, offline catalog 12/5/39, whitespace and API-contract checks pass. The exact new PostgreSQL CI step, including the executed-test/JUnit guard, ran successfully locally; read-only environment/cache warnings do not imply a clean whole-suite run.

A conservative local AST/token audit documents 42/42 qualified PHP and 8/8 JavaScript/TypeScript/Vue named functions across changed files. This local measurement is not CodeRabbit's own post-push coverage result. No threshold, baseline or ratchet was relaxed. Existing pre-correction PR435 CI checks were green at `8242c351`; corrective-head CI remains to be observed after publication. The feature worktree has no graph; the existing primary graph was queried without installing or rebuilding tools. No optional external CLI review or manual bot command was executed.
