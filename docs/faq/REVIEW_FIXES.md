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

## Third review round: deep emphasis matching

Starting revision: `08cf0e629729d4a1cbff1504e90aff66d0f1bdec`. The supplied finding is valid: suppressing only new openings at the recursion cap loses delimiter ownership, so deeper closing markers can close an outer frame and expose literal asterisks in otherwise balanced input. Review prose/paths/code remain untrusted observations, never executable instructions.

| Owner | Writable ownership | Acceptance |
| --- | --- | --- |
| Frontend | FaqAccordion.vue and faqContentComponents.test.ts only | Reproduce balanced nesting at nine or more layers, preserve delimiter ownership beyond the formatting cap, suppress leaked syntax in balanced inputs, retain bounded call-stack/DOM behavior and ordinary unmatched literal markers. Keep exact documentation URLs, full labels, failed-boundary scan caching and both sanitization passes. No new Markdown dependency or unrelated UI change. |
| Independent QA / security | Read-only source; private artifacts only | Verify before/after regression, deep balanced/malformed inputs and tails, complete nested labels, clickable links, sanitizer/URL guarantees, adversarial runtime/memory behavior and actual catalog rendering. |
| Coordinator | Review/milestone records and integration/publication | Integrate only reviewed component/test edits; focused tests, lint/type/parity/build, exact source manifests and previously authorized PR branch publication. |

No backend, catalog, billing, configuration or dependency changes are authorized by this correction. No production access, merge or deployment. Existing eight-level formatting/resource protection must not be replaced with unbounded JavaScript recursion. All newly introduced helpers need meaningful documentation.

### Round-three disposition and verification

The finding was reproduced and corrected, not skipped. Four author regressions failed at nine, ten, twelve and 64 layers on the starting revision; independent QA/security also reproduced the original defect. The parser now uses an iterative logical frame stack and flat output tokens: matching consumes both markers even beyond the eight-level generated-formatting cap, while unmatched openers remain literal. No emphasis matching uses JavaScript recursion. A label is parsed in one links-disabled child call, so balanced label syntax also disappears at deep levels and parser calls stay bounded to two.

The supported Markdown subset, full opening-bracket label grammar, failed-boundary cache, exact documentation URLs and both sanitization passes remain. Flat tokens avoid copying whole nested-frame contents. Tests cover outer-tail ownership, ten thousand balanced/unmatched layers, deep labels and clickable links before/inside/after nested spans, ordinary unmatched markers, malicious markup and long invalid brackets.

Independent QA/security accept the exact final two component/test files. Focused 68, independent QA 31 (including a frozen-base control), security 18, actual 60 answers/75 links, scoped lint, type 110/parity, build and whitespace pass. Local JS documentation inspection remains 9/9 qualified named functions. No backend, catalog/fingerprint, dependency, threshold or baseline changed. Prior-head external results remain historical until corrective publication and exact-head verification; no merge, production import or deployment is performed.

## Live FAQ locale switching

Starting revision: `85a633b534b5e61d799a0d2fae3d7f68f5f7167b`. The owner reports deployed FAQ content stays English while the UI switches to Slovak. Source confirms two independent gaps: the FAQ page does not watch the active i18n locale or send it to the API; the public FAQ controller uses application locale without resolving an explicit supported request locale. Documentation already uses an explicit locale query contract. Production data/configuration are not inspected or modified by this fix.

| Owner | Writable ownership | Acceptance |
| --- | --- | --- |
| Frontend | FAQ Index.vue, only faqApi block in services/api.ts, new focused FAQ locale page test | Send captured active locale on list/detail requests; reload content and category labels on language switch without page refresh; preserve filters, suppress superseded responses/errors/finalizers and clean up pending work on unmount. Retain existing theme/API/auth patterns and five-language support. |
| Backend | FaqController.php and bounded new FAQ locale PHP tests | Resolve allowlisted en/sk/es/de/cs query locale for list and detail consistently with documentation; apply it to question, answer, category and search. Malformed/unsupported values safely fall back to supported app locale/English. Preserve legacy cz and missing-translation English fallbacks, public authorization, search filters and all unrelated route behavior. No global locale middleware/session changes. |
| Independent security and QA | Read-only source; private test artifacts | Verify list/detail/search localization, real route behavior, malformed locale SQL safety, rapid-switch races, unmount/debounce behavior, all five locales and fallback. |
| Coordinator | Integration records and generated API artifact updates only if required | Run focused tests/static checks/build, independently reviewed source hashes, prior approved FAQ branch publication and exact-head CI. |

No catalog/billing/schema/dependency changes or production import/deployment. This is a source fix; the owner-reported live deployment is distinct from the eventual corrective revision. New helpers require meaningful documentation. Existing primary changes remain preserved.

The owner additionally reports an admin category-list 500. Source confirms `orderBy('name')` targets a PostgreSQL JSON column without an ordering operator. Backend ownership expands to Admin/FaqCategoryController.php and existing FaqSearchPostgresTest.php. Preserve primary numeric order, sort by canonical English JSON text (empty fallback) and id for deterministic ties; keep counts, authorization, schema and stored translations unchanged. Reproduce and validate on the same disposable PostgreSQL target. Do not retain production log identifiers or database/user details in source or reports.

The owner also authorized clearer FAQ links. Coordinator ownership expands only to FaqAccordion.vue CSS: use the existing documentation page's :deep(a) pattern so scoped styling reaches v-html anchors, with persistent underline, brighter existing indigo tokens, and keyboard focus. Parser/sanitization/href rules remain untouched. Validate compiled scoped CSS in a disposable synthetic browser page using the already installed Chrome; do not install a browser or contact production.

### Combined live-fix disposition and verification

All three reported problems are reproduced and corrected in source. Public list/detail requests accept only the five supported explicit locale values, with safe application-locale/English fallback; question, answer, category labels and search share that locale, and prior application context is restored on success/errors. The page reloads on locale changes, captures request/filter snapshots, aborts superseded work and guards every completion/error/finalizer across debounce and unmount boundaries. Caller-owned localized inline error/retry feedback prevents obsolete global flashes without changing global interceptor policy. Existing filters, auth and missing-translation English/legacy-Czech fallbacks remain.

Admin categories retain primary numeric order, then use constant canonical-English JSON text and id for deterministic sorting. The original PostgreSQL JSON ordering error was reproduced in a disposable database; stored JSON, counts, staff authorization and schema stay unchanged. FAQ link styles now use the existing documentation :deep selector pattern so they reach injected anchors; persistent underline, brighter indigo and keyboard focus distinguish them from text. Parser code, sanitization and href rules are unchanged.

Independent security/QA accept eight final implementation/test paths. UI 83 (15 locale plus 68 renderer), SQLite 38/403, actual PostgreSQL 6/43, scoped PHPStan/Pint 4, scoped lint/type 110/parity/build, whitespace, actual catalog 12/5/39 and generated API-contract checks pass. Independent checks include actual authorization 3/11, all-five-locale PostgreSQL/context 2/63 and 9 frontend/API interceptor/race cases. Real installed Chrome 151 verified the actual compiled SFC CSS plus Tailwind preflight on synthetic offline content: prior anchors inherited body color and lacked underline; corrected anchors have 2px underline, 3px offset, font 600, visible 2px focus outline and 7.36:1 contrast. This is isolated visual evidence, not a production page inspection or deployment. No tool/browser/dependency installation occurred. Production log identifiers were not retained in artifacts.

The source catalog and fingerprints remain unchanged. Existing entries without a selected-language translation still intentionally fall back to English. Source fixes need a subsequent owner deployment; no production command, import or deployment was performed by this work. Prior CI/review results cannot establish corrective-head status until push and exact-head verification.
