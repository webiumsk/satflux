# Exact Platba description profile - backend delivery

Repository: Satflux core; branch `fix/tatra-ascii-wire-profile`.
Base: `a29b727aae0751dfe554a4cb86da3738f7c5d375`.
Assigned worktree: `storage/app/private/worktrees/tatra-ascii-wire-profile`.
Author: backend specialist. Main owns independent review, shared documentation/API artifacts and Git publication.

## Completed bounded scope

The only production change adds the third exact literal `Platba` to the existing `(CCINT|CSO)` description alternative in `TatraBankSepaEvidenceParser.php`. The existing anchored numeric grammar, full frame, size/encoding/control checks, greeting, date, positive credit/receiving account, three balances, bounded reference field, tail, identity tuple and identity construction are unchanged. `Platba` remains its own literal in the existing v1 tuple. There is no alias mapping, key/history rewrite, provider contract change, raw retention/capture change or Node production change.

The owner supplied only the missing structural facts; tests use the existing synthetic valid test account, synthetic 12.50 amount, synthetic numeric description and fixed synthetic QR/VS values. The synthetic positive frame uses `Ucet protistrany` without `Informacia pre prijemcu`, plus the already-supported footer, bank URL, note and recommendation labels. No private bank account, transaction reference or actual copied body was used.

Four owned files:

- `app/Services/Invoicing/BankImport/TatraBankSepaEvidenceParser.php`: one regex literal only.
- `tests/Unit/TatraBankSepaEvidenceDiagnosticsTest.php`: synthetic Platba QR/VS frame, fixed golden identity, reference/note/rendering/canonical-number independence, distinct hashes across the three literal labels, case/near-match/numeric-bound negatives and existing reference/tail injection guards. All pre-existing CCINT/CSO golden fixtures remain unchanged.
- `tests/Feature/SepaBmailTest.php`: parameterized real native intake positives for Platba QR/VS, explicit golden report key, existing reference-independent key regression extended to Platba, same-reference/different-label report-payload regression, and existing no-origin/invalid-account/old-provider guards extended with bounded Platba fixtures.
- `tests/Feature/BankMimeConfirmationTest.php`: existing production-Node fully signed MIME and ASCII-wire test parameterized with Platba QR/VS and an aligned whitespace variant; both report the same expected account, amount, currency, reference, verified-origin flag and golden key as native intake. Stub settled/duplicate outcomes verify consumer handling only.

Main's concurrent documentation file was preserved and not edited. No other repository files were modified by this author.

## Frozen SHA256

```text
1e24a3eaf5ef207bed4c0159515cab7a3350256bd120092d5f383bbc1da66991  app/Services/Invoicing/BankImport/TatraBankSepaEvidenceParser.php
9bb63933532427590f2ec05a64b6f612f37b8062cd57ad7dae0829306e387edf  tests/Unit/TatraBankSepaEvidenceDiagnosticsTest.php
cb99e3fb74ba24b043280bdaf17d28b20148a6ce492140bf29184cc839a45575  tests/Feature/SepaBmailTest.php
0227f583448b82271981e1a2ebbfe58ee7733a220183df6471dfe1d3e8f78a55  tests/Feature/BankMimeConfirmationTest.php
```

## Environment and exact validation

Read role, Laravel/payments skills and applicable repository instructions. Graph tool/worktree graph unavailable; existing main graph is stale for these parser edges. Used targeted known-path reads; no rebuild/install.

Reused the existing `/tmp/bank-credit-evidence-backend/run-php-no-redis.sh` without editing its mounts, dependencies or environment. Docker networking is disabled; locked Node/PHP dependencies are read-only mounted; all credentials and provider URLs are synthetic. Effective bootstrap probe confirmed testing mode, no cached configuration, SQLite `:memory:`, array cache/session/mail, sync queue, null logging, capture disabled and synthetic BTCPay URL. No Redis service or production target was required for author checks.

```text
sh /tmp/bank-credit-evidence-backend/run-php-no-redis.sh php artisan test tests/Unit/TatraBankSepaEvidenceDiagnosticsTest.php tests/Feature/SepaBmailTest.php tests/Feature/BankMimeConfirmationTest.php
PASS: 159 tests / 719 assertions / 47.20s; zero skips reported.

sh /tmp/bank-credit-evidence-backend/run-php-no-redis.sh vendor/bin/pint --test app/Services/Invoicing/BankImport/TatraBankSepaEvidenceParser.php tests/Unit/TatraBankSepaEvidenceDiagnosticsTest.php tests/Feature/SepaBmailTest.php tests/Feature/BankMimeConfirmationTest.php
PASS: all four assigned files.

git diff --check
PASS.
```

An additional read-only comparison proved the entire production parser file is byte-identical to `git show a29b727a` after replacing only `(CCINT|CSO|Platba)` back with `(CCINT|CSO)`. No tests failed and no baselines/ratchets changed. Per assignment, main runs full PHPStan/API checks; this author did not duplicate them or run a broad suite.

## Review dependencies and limits

The same-reference cross-label consumer test deliberately demonstrates an unchanged authenticated reference with distinct event hashes. Its provider responses are stubs; it does not prove real provider duplicate/row-lock/confirmed-request behavior. Actual provider replay/concurrency/recovery verification belongs to independently assigned QA, with security responsible for the final trust review. No alias/schema/provider extension was implemented.

Synthetic passing source tests qualify the exact supported structure and preserved guards, not actual invoice settlement or live rollout. No production access, funds test, payment replay, real bank content retention, package operation, commit, push, merge, deployment or external publication occurred. No backend blocker remains; independent review/main integration gates are outstanding.
