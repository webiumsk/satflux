# FAQ automation validation

Implementation branch: `feat/faq-automation`, based on owned master `f7118b10f2cbde07f15edc68bc154ceb2a94718d`. All implementation evidence is local; it does not establish production deployment, database import or publication.

## Independent acceptance

Independent security and QA accepted the final bounded implementation, content and checks. Security reviewed 23 source paths; QA additionally exercised the actual twelve-question catalog against an isolated database. The Node slug coercion issue found during review was corrected and regression-tested. Required PHPStan corrections removed nine obsolete FAQ baseline entries; no suppressions or thresholds were added.

- Catalog: 12 questions, four categories, 60 complete language variants, 75 safe local documentation links, 39 matching source fingerprints.
- Focused implementation PHP: 22 tests, 115 assertions passed.
- Independent actual-catalog PHP: four tests, 36 assertions passed. Checks include 12 drafts/four categories, repeated byte-idempotency, preservation, manual conflict atomicity, actor rejection and migration rollback/reapplication behavior.
- Frontend FAQ/editor: 22 regression, adversarial and accessibility tests passed.
- Offline validator: eight direct Node tests passed; actual catalog validation passed. The environment's `node --test` summary groups the successful test file; direct invocation confirms eight individual cases execute.
- Actual Artisan `--check` passed with an unavailable database connection, proving catalog validation does not require a database.
- Full frontend rerun: 2,231 tests / 205 files passed with two workers. The first unrestricted run had seven failures under contention, including timeouts in unrelated suites; the complete reduced-concurrency rerun passed without increasing timeouts or changing tests.
- Full ESLint: zero errors, 48 warnings within the existing 52-warning limit. Typecheck passed with the unchanged 110-error baseline. Locale parity passed with existing informational extra Slovak keys. Production frontend build passed with the existing bundle-size warning.
- Scoped final PHPStan and Pint (11 changed PHP files) passed. PHP source and test dependencies exactly matched the existing lockfile.

## Broader PHP regression attempt

The complete PHP-only, network-disabled run executed 3,169 tests / 15,409 assertions and reported 27 errors, 11 failures, 58 skips and one PHPUnit deprecation. It is not a clean full-suite pass. The 38 unsuccessful outcomes map to six DNS-dependent e-mail validation cases and 32 bank MIME cases requiring a compatible Node 22 executable and verifier dependencies not provisioned in the initial container. These tests are outside the FAQ changes. The same six e-mail failures were reproduced on an untouched private snapshot of base `f7118b10` under the same network-disabled settings. A targeted replay of all 32 bank cases against byte-verified current PHP sources passed 32 tests / 324 assertions after mounting an existing Node 22 executable and 62 lock-matching verifier packages read-only. Its existing PHPUnit deprecation remains disclosed. No packages were installed and no application validation or external-network guard was weakened. This targeted correction is not a second clean whole-suite run.

## Test isolation and limits

PHP verification uses PHP 8.3.31, SQLite in memory, no network, read-only application/dependencies, ephemeral cache/storage and synthetic settings. No production environment, merchant credentials, real wallets or funds were used. No dependency or browser installation occurred.

Legacy Czech text renders through `cz` when canonical `cs` is empty. Existing public search still queries canonical Czech and English; legacy-only Czech search terms may not match until canonical text is saved. Newly imported questions include `cs` and are unaffected. This inherited query limitation is outside the locale preservation change.

Independent PostgreSQL concurrency processes and real-browser verification were not executed; Chromium was unavailable. Transaction locks/unique constraints were reviewed and rollback/stale-preview races tested in SQLite. Source checks detect changed repository files, not provider changes absent from documentation. Some documentation links retain the existing English fallback for missing translated articles.

The primary checkout's unrelated changes remain preserved. Graphify query-first was used against the primary graph; the feature worktree has no graph. No graph installation or rebuild was performed. Historical free-PRO restrictions in agent instructions differ from conditional user documentation; this implementation changes neither billing nor promotion configuration and refers users to current in-app conditions.
