# Independent QA: incomplete credit evidence diagnostics

Reviewer: /root/bank_mime_diagnostic_qa. Date: 2026-11-01.
Disposition: ACCEPT frozen source and corrected operator-documentation guard. No verified QA finding or unresolved blocker. This is source QA acceptance, not merge/deployment approval or confirmation of a payment.

## Repository, revision and scope

webiumsk/satflux, fix/tatra-ascii-wire-profile, base12f83c11f326eb3d5c658ab8c18262b149f9db8d. Reviewed uncommitted metadata-only delta. Source read-only; only /tmp probes/report written. No repo/docs/env/dependency/Git changes, install, production database/service/wallet/funds, actual bank message, external API, retention/capture/replay or deployment action. Primary unrelated dirty work preserved. Root/state, QA role and integration-testing workflow applied. Scoped Graphify graph absent; main reports stale primary graph; no rebuild/install.

Frozen source SHA-256 independently matched before and after tests:

| File | SHA-256 |
|---|---|
| app/Services/SepaEmailConfirmationService.php | ef10404dafdc70866db17f613f2b690b65a888888f7c433641bb81af6d5a77af |
| app/Services/Invoicing/BankImport/TatraBankSepaEvidenceParser.php | 155e8fac2621b791225ee83a01c9305972c3bc88bd596ca3653fc5cb918a9243 |
| tests/Unit/TatraBankSepaEvidenceDiagnosticsTest.php | 16d12714dfca868ae2de2d2e486535ff12754fabe31f637ef34944773c6bab80 |
| tests/Feature/SepaCreditEvidenceDiagnosticsTest.php | 33b11850a95fa1d3b8715b228604f9f02f1e5578b541054e1f5df13ec7721324 |

Immutable12f83 parser SHAeb62a9b13eb72699db7ad49ca00bcfcf7a0a5fe0b1270f193f62d91e8def85eb and confirmation service SHA34045bf33ec5c3677dc953742e84541df85650dffd3ac6a3f1fdad1b2b5ddb8a were read with git show into /tmp, changing class names only to load baseline and candidate in the same process. No implementation bodies were changed in those baseline copies.

## Disposable environment verified first

Inspected main /tmp/bank-credit-evidence-tests/run-php.sh. QA variant /tmp/bank-credit-evidence-qa/run-php.sh mounts /app read-only and masks.env with public.env.example read-only, reuses locked vendor/Node dependencies read-only, allows only /tmp probe outputs, uses --pull=never and unique prefixbank_mime_capture_credit_evidence_qa_. No global environment settings changed.

Docker inspection: assigned Redis7 container networknone, bind127.0.0.1, saveempty/AOFoff, no ports, anonymous image/data volume only, no production bindmount. PHP shares only that isolated namespace. Resolved preflight PASS: testing, uncached, SQLite memory, array cache/session/mail, sync queue, null logging, synthetic provider and loopback Redis ping. PHP8.3.31/PHPUnit11.5.50. No flush/restart. Standard approved Docker escalation, no rejected approval or bypass.

## Exact commands and results

1. `/tmp/bank-credit-evidence-qa/run-php.sh php /tmp/bank-credit-evidence-qa/environment.php`

PASS all synthetic/disposable checks above.

2. `/tmp/bank-credit-evidence-qa/run-php.sh php /tmp/bank-credit-evidence-qa/record-baseline.php`

PASS:116 immutable baseline cases,24 accepted/92 rejected. Golden serialized result SHA256d5145b4c345abb42d98a542dfcc831f8a220dbb4622ac15f0fdfa583fbc3f64e. Corpus includes existing supported frame/reference/case/rendering variants, optional tails, zero/negative-zero balance identity, valid numeric/account frames and hostile controls/UTF8/entities/line counts/header/date/DST/account/amount/balance/description/reference/tail/multiple-failure corners.

3. `/tmp/bank-credit-evidence-qa/run-php.sh php vendor/bin/phpunit --do-not-cache-result tests/Feature/BankMimeConfirmationTest.php tests/Feature/BankCompanyMimeImportTest.php tests/Feature/BankInboundEmailTest.php tests/Feature/SepaBmailTest.php tests/Unit/TatraBankEmailParserTest.php tests/Unit/SepaBankEvidenceTest.php tests/Feature/BankMimeCaptureTest.php tests/Unit/BankMimeDiagnosticSummaryTest.php tests/Unit/TatraBankSepaEvidenceDiagnosticsTest.php tests/Feature/SepaCreditEvidenceDiagnosticsTest.php`

PASS:315 tests /1,774 assertions, zero failures/skips,71.305seconds,78.50MiB. Includes all previous256 bank-mail/ASCII-profile/capture cases plus59 new diagnostics cases; disposable Redis concurrency/expiry cases executed, not skipped.

4. `/tmp/bank-credit-evidence-qa/run-php.sh php /tmp/bank-credit-evidence-qa/check-parity.php`

PASS:116 cases /359 explicit checks. Every parse result, exact evidence/identity/null, diagnostic-first-stage and repeated parse after diagnose match the immutable baseline/golden. All ten stage enums exercised: matched24, unsupported_content41, greeting_or_line_count8, credit_header9, credit_datetime5, creditor_or_amount3, balances4, transaction_description5, trusted_reference10, tail7. Case/VS/rendering/account-zero normalization identity remains stable; distinct balances change identity and negative-zero normalization remains unchanged. No broad mutation expansion was added after this agreed matrix passed.

5. `/tmp/bank-credit-evidence-qa/run-php.sh php vendor/bin/phpunit --do-not-cache-result /tmp/bank-credit-evidence-qa/IndependentCreditEvidenceQaTest.php`

PASS:24 tests /233 assertions, zero failures/skips,2.982seconds,60.50MiB. Independent checks compare baseline/current public result and report payload/dedup, exact five eligibility predicates plus informational identity, inferred versus explicit VS, ambiguity and existing non-null empty-E2E candidate semantics. Diagnostic rows cap at three after debit filtering, preserve order and mark truncation correctly. Verified Tatra subdomains and uppercase domain remain supported; other bank/body type/size gates are not evaluated; malformed UTF8 gets the fixed unsupported-content stage.

Review path produces exactly the same one audit INSERT as baseline, no additional DB reads or provider settings/report calls. Nested metadata excludes private DTO/body/subject/address/account/reference/hash/amount/currency/Store values and contains only fixed schema/booleans/stage. Forced diagnostic exception produces only version1/status unavailable with unchanged review and fixed log context. Identity absent and mixed accepted/rejected rows retain exact baseline success/report/dedup/outcome behavior without new diagnostic output. Zero-credit and bad-origin behavior remains unchanged. No HTTP or queue action from diagnostics.

6. `/tmp/bank-credit-evidence-qa/run-php.sh php vendor/bin/phpunit --do-not-cache-result /tmp/bank-credit-evidence-qa/OperatorProjectionQaTest.php`

PASS:1 test /15 assertions, zero skips,2.056seconds. This follows the newly corrected docs guard rather than repeating runtime tests: extracted the actual documented AuditLog query and executed it only against disposable synthetic SQLite fixtures. A newer foreign Store audit, wrong reason, wrong action and Company target are excluded; four matching records return only three, with only time_utc/reason/credit_evidence output and no selected/foreign UUID or wrong-record marker. Operator command itself was never run against production.

7. `/tmp/bank-credit-evidence-qa/run-php.sh php artisan api-docs:export --check`

PASS: API contracts and route inventory current. Generated route-coverage change contains only the two updated service/parser source digests.

8. `git diff --check`, frozen SHA checks and scoped `git diff 12f83c11 --exit-code -- runtime/bank-mail-verifier app/Support/Invoicing/ParsedBankTransaction.php app/Services/Invoicing/BankMimeCaptureService.php app/Services/Invoicing/BankMimeVerificationService.php app/Services/Invoicing/BankInboundEmailService.php app/Services/Invoicing/BankImport/TatraBankEmailParser.php app/Jobs/ProcessBankNotificationEmail.php app/Services/BtcPay/SepaService.php routes config composer.json composer.lock`

PASS. No ingress/capture/ASCII/Node/provider transport/DTO/dependency/config/route change. All executable QA commands passed first run; no candidate or probe correction needed after execution.

Distinct executed results: repository315/1774, independent service24/233, operator projection1/15, separate116-case parity359 checks. Node77 was not rerun: Node runtime/tests/dependencies are byte-identical to12f83, with prior independent77 pass recorded in the ASCII-profile milestone; that historical result is not claimed as this run. Fresh CI will exercise Node on the published head.

## Reviewed behavior and documentation verdict

Parser parse() and diagnose() share the same pure ordered validation; only fixed stage assignments were added. The complete-frame rules, reference case, conservative credit identity and rejection order remain. The confirmation service computes new diagnostics only when the existing eligible set is empty, before provider access. First five bits reproduce existing predicates; identity remains informational. Any diagnostic exception retains the original review. New metadata is attached only to incomplete_credit_evidence; original public result, other review metadata, financial report/filter/identity/dedup/retry remain unchanged.

The early draft's exact-root-only Tatra stage guard was corrected before freeze following independent security review. Frozen code and independent tests now use actual verified-domain/from alignment plus the existing Tatra sender matcher, including permitted subdomains; no financial authorization broadened.

Documentation guard ACCEPT: local Store UUID is entered only server-side, validated as UUID and existing Store, never printed; query filters selected Store target/type, review action and incomplete-credit reason, descending bounded3, projected safe fields only. Contract/runbook clearly distinguish metadata diagnosis from bank values/retention/payment/replay and old rows cannot be backfilled.

Reviewed docs SHA256: runbook9391a72de697c86f6ed3d6cbd6c0075cd7ebcb6c6a96928e8a19a0565b8eb51f; contract12ca1561afce7116d1148e842aa684fd7fd7f466a7a5890569081f1201d1be44; generated route-coverage26418b918d7e708a7b3548dd7a2fdd7ecf039d817f4ffc4da0596693665e6c4a.

## Limits, assumptions and remaining gates

Owner reports20:15:06UTC incomplete_credit_evidence/verifier_reasonnull. At the reviewed baseline branch, that reason follows at least one credit and the sender/origin gate, with zero eligible credits and no provider lookup/report. It does not disclose which predicate failed, selected body profile, actual bank layout, missing QR or settlement. Only Mailgun logs are available and capture is empty. This follow-up cannot reconstruct that consumed original/audit, retain new raw content or force the payment through. No new real transfer/replay is required; future normal notification metadata after compatible deployment provides new facts.

Skipped outside scope: complete application PHP suite, independent full PHPStan/Pint, unchanged Node suite, frontend/browser/locales, actual bank format/provider settlement and production deployment. Main/author/security evidence stays attributed separately. Fresh exact-head CI and independent security acceptance remain separate main-owned gates. No unresolved QA finding; no merge/deployment/publication approval is granted here.
