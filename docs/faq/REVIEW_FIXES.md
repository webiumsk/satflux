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

## Second review round: renderer and numeric parity

Starting revision: `cc60c4b8025963c865395218b93cbbdd263a494c`. All four new observations were verified against source and treated as untrusted review data, never as executable instructions.

| Owner | Writable ownership | Acceptance |
| --- | --- | --- |
| Frontend | FaqAccordion.vue and faqContentComponents.test.ts | Link labels use validator grammar including opening brackets; full labels are consumed. At eight emphasis levels, stop only further emphasis recursion and retain documentation-link parsing throughout the remaining input, with the existing URL/sanitization bounds. |
| Backend | FaqCatalog.php, FaqCatalogTest.php and FaqSyncTest.php | Accept decoded finite whole JSON numbers (including 1.0/1e0) in 0..2147483647, reject strings/fractions/overflow; preserve integer import semantics. Exercise actual default catalog loader via command with database unavailable, without mocking load. |
| Coordinator | Node validator/tests and shared records | Match the same mathematical JSON-number policy in category/item checks, use literal JSON regression fixtures, retain safe-integer and PostgreSQL limits. |
| Independent security and QA | Read-only source; private artifacts only | Verify label consumption, bounded parsing/link availability and XSS/performance, numeric parity and genuine database-free default-path command coverage. |

Existing authorization applies to this Satflux branch/PR only. No dependency/tool installation, production access, billing change, merge or deployment. Existing catalog text and source fingerprints stay unchanged. Preserve meaningful docstrings; coordinator owns integration/publication, and no author approves their own security review.

### Round-two disposition and verification

All four observations are valid and fixed; none was skipped. Link labels now consume their complete validator-compatible text, including opening brackets. At the eighth nested level, only further emphasis/label recursion stops: documentation links continue to be recognized inside and after capped spans. Failed-boundary caching preserves bounded malformed-label scanning, while both HTML sanitization passes and exact documentation URL restrictions remain unchanged.

Both validators accept only finite mathematical whole JSON number values in 0..2147483647, regardless of integer/decimal/exponent spelling; PHP normalizes accepted orders to integers for stable repeated imports. Strings, booleans, null, fractions and out-of-range values remain invalid. Literal JSON fixtures preserve spellings such as `1.0` and `1e0`. The command test now binds a genuine FaqCatalog and invokes --check with its default source path while the database connector is unavailable. Private mutation tests prove that a wrong source root or an added database read makes this test fail.

Independent security and QA accept the final seven-file implementation/test scope. PHP 33/185, frontend 52, Node 60, catalog 12/5/39, scoped PHPStan/Pint 3, frontend lint/type 110/parity and production build pass. QA independently checks 64 literal-number cases per validator; security checks 54 per validator and 22 renderer attack/depth/performance cases. Local documentation inspection finds 45/45 PHP and 9/9 JS qualified named functions documented. No source catalog/fingerprint, dependency, threshold or baseline changed. Prior `cc60c4b8` CI and external 100% docstring results remain historical; post-push results must match the corrective head. The optional external CLI review was not required.
