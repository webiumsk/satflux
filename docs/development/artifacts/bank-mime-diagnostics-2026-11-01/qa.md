# Bank MIME diagnostic independent QA

Reviewer: /root/bank_mime_diagnostic_qa, independent of implementation author.
Date: 2026-11-01. Disposition: ACCEPT bounded metadata diagnostic change; no verified product findings or QA blockers. Live bank interoperability and historical payment remain unverified.

## Repository and reviewed source

Repository: webiumsk/satflux. Worktree: bank-mail-single-route, branch fix/bank-mail-single-route. Baseline HEAD: cc3967abfb35f826af91858dff771fa999087a2c. Reviewed uncommitted frozen delta, not a new commit, remote CI result or deployment.

| File | SHA-256 |
|---|---|
| app/Services/Invoicing/BankMimeVerificationService.php | 6c8900b1367ed23072426cd0096ad575c0c04a6db311852f3660fcd38d633087 |
| tests/Feature/BankMimeConfirmationTest.php | 7624d9c8185b92e070985b5eef73c7c42c04a7b6fefd6a334202796def41e9f4 |
| docs/development/SEPA_CONFIRMATION_ISOLATION_RUNBOOK.md | b267ad1677ba58b0d97afa2a377fcf250bc2625772251dc794bb01ae646f3705 |
| runtime/bank-mail-verifier/README.md | c6ed0388f9668ae6aa31a82f5537275080cff0e7325b6e12f9c4477037de1bc7 |

Scope: QA source read-only, /tmp probes and report only. No source/Git/shared-state edits, install, restart, publication, merge, deployment, route change, replay or live payment action. Primary checkout's unrelated dirty work preserved. Instructions read: root/worktree AGENTS, project state/current roadmap and decisions, workflow/registry, qa role, CLAUDE/SECURITY, Cursor rules, integration-testing skill. Graphify tool unavailable; worktree graph absent; main reports stale primary graph lacking current MIME edges. No graph rebuilt or tool installed.

## Environment verified before tests

Inspected existing /tmp/bank-single-route-tests/run-php.sh, phpunit.xml and tests/TestCase.php. Created /tmp/bank-mime-diagnostic-qa/run-php.sh with additional protections: /app source read-only, QA probes read-only, existing .env masked by public .env.example read-only. Existing Node/dependencies reused without installation. New disposable Docker container per command, --network none, user 1000:1000, no attached production database or service. APP_ENV testing, SQLite :memory:, cache/session/mail array, queue sync, logs null, explicit synthetic app/provider keys. bootstrap/cache/config.php absent. Test fixture HTTP prevented stray requests and faked provider outcomes.

Resolved preflight command:

`/tmp/bank-mime-diagnostic-qa/run-php.sh php /tmp/bank-mime-diagnostic-qa/environment.php`

PASS: expected testing, SQLite memory, array/sync/null configuration, synthetic provider and absent config cache. PHP 8.3.31. `/tmp/bank-mime-diagnostic-qa/run-php.sh node --version` returned v24.22.1. Initial default sandbox Docker socket denial was resolved through standard approved escalation; no auto-review rejection or bypass. No production credentials were read or printed.

## Exact test commands and results

1. `/tmp/bank-mime-diagnostic-qa/run-php.sh php vendor/bin/phpunit --do-not-cache-result tests/Feature/BankMimeConfirmationTest.php tests/Feature/BankCompanyMimeImportTest.php tests/Feature/BankInboundEmailTest.php tests/Feature/SepaBmailTest.php tests/Unit/TatraBankEmailParserTest.php tests/Unit/SepaBankEvidenceTest.php`

PASS: 189 tests, 792 assertions, zero failures/skips, 30.692 seconds, PHPUnit 11.5.50 / PHP8.3.31. Includes actual production CLI crypto with test-only synthetic DNS fixture, signed positive parser/report flow, body tamper, recipient binding, encrypted queue payload, Company extraction, SEPA origin/report/retry, parser and evidence regression.

2. `/tmp/bank-mime-diagnostic-qa/run-php.sh php vendor/bin/phpunit --do-not-cache-result /tmp/bank-mime-diagnostic-qa/BankMimeDiagnosticIndependentTest.php`

Final PASS: 40 tests, 312 assertions, zero failures/skips, 3.004 seconds. All 15 runtime invalid reasons; unknown/missing values; attacker/newline/private-marker and invalid-encoding reasons; boolean/integer/float/array/object-shaped values; case/whitespace variants; rejection carrying misleading decoded fields; 7 malformed/transient statuses; preNode invalid UTF8; verifier exception. Exact audit metadata is asserted, owner remains bound, no provider calls/transactions, ciphertext unchanged, transient exceptions sanitized and no additional audit rows.

Two initial runs of this independent temporary harness each reported 40 tests/296 assertions/8 failures because the harness wrongly expected zero audit rows. A first correction captured before address provisioning and still failed. The final correction captures baseline after all fixture setup, including address creation's existing audit row. These were QA harness false failures, not product defects. Only /tmp harness changed; source hashes unchanged. The final assertion still requires no new audit rows on transient/exception paths.

3. `/tmp/bank-mime-diagnostic-qa/run-php.sh node --test runtime/bank-mail-verifier/test/verifier.test.mjs runtime/bank-mail-verifier/test/company-extractor.test.mjs`

PASS: 42 tests, 42 passed, zero failures/cancelled/skipped/todo, 4532.305ms. Crypto, signer/recipient/header binding, MIME/charset limits, company decoding, bounded DNS/transient recovery, CLI input/output privacy and synthetic injection resistance covered.

4. `git diff --check`

PASS. Read-only Python comparison: fixed PHP allowlist equals exactly all 15 current invalid codes emitted by verifier.mjs and cli.mjs; retry codes excluded. Git baseline byte comparisons passed for runtime verifier/CLI, ProcessBankNotificationEmail, BankInboundEmailService, BankStatementImportService, TatraBankEmailParser and TatraBankSepaEvidenceParser.

Aggregate final PHP: 229 tests / 1104 assertions. Node: 42 tests. No source changes during review.

## Verified behavior and file evidence

- BankMimeVerificationService.php:18-23 defines fixed rejection constants; :194-197 uses string check plus strict membership. Unknown/malformed values reduce to unknown, never arbitrary child content.
- :118-119 classifies invalid UTF8 before Node invocation, retaining broad reason unverified_bank_mime. :128-133 preserves strict status handling and retry before diagnostic extraction. :135-153 retains successful-result validation, digest/sender binding and output fields.
- :200-206 adds only verifier_reason to metadata when supplied. Company rejection :211-215 is unchanged. Runtime, job retry/privacy, parser/importer bytes remain baseline-identical.
- New tests assert no provider calls, no imported transactions, exact metadata and retained cipher payload; actual signed/tampered/retarget cases pass.
- Runbook:109-130 and runtime README:30-36 accurately describe HTTP intake versus bank proof, bounded codes, retry, older records and EML limitations. No claim that diagnostics fix unsupported MIME or recover consumed mail.

No severity-ranked product finding, reproduction or mitigation is required because no defect was verified in this bounded delta. Regression protection is the exact-source suites above.

## Compatibility, dependencies and limitations

| Pairing | Evidence |
|---|---|
| New PHP diagnostics + unchanged local Node v24.22.1 runtime | Tested; all emitted permanent invalid codes mapped |
| Unknown future child reason / malformed type | Tested; fixed unknown metadata, strict rejection retained |
| Transient/unknown status or exception | Tested; retry/ciphertext retained, no permanent diagnostic |
| Legacy Company MIME/parsed imports and SEPA report contract v2 | Existing synthetic regression suites pass; policy unchanged |
| Existing audit readers / old rows | Existing broad reason unchanged; no migration; old rows cannot recover a missing subreason |
| Node22 CI / actual deployed Node and workers | Not executed/verified by this QA pass; no new source runtime requirements introduced |
| Real current Tatra EML/signature/layout | Not tested; no original EML available |

Owner evidence reports Mailgun Delivered09:00:30UTC, MIME rejection09:00:32UTC, queue0 and inboundtrue. These establish reported processing timing only. No original EML is available, so signature/layout root cause, historical settlement and live deployment remain unverified. New diagnostics apply to future processed rejects; they do not recover historical reasons or authorize payment.

Skipped as outside bounded delta: full application PHP suite, frontend/browser/locale checks, real provider/package pairing, production configuration/worker checks, and real bank mail. Author-reported Pint/PHPStan passes are not claimed as independent QA executions. Independent security disposition is owned by the security reviewer/main.
