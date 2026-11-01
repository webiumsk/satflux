# Private bank MIME capture - backend delivery

Repository: Satflux core. Branch: `feat/bank-mime-private-capture`.
Base revision: `037a6fa7e739597b6c54afb4b02d37a277dd3cc5`.
Authorization: owner-approved implementation of frozen design `ba1177a5`; feature default OFF. No activation, deployment or real-content capture authorization is implied.
Implementation author: assigned backend specialist. Independent QA/security reviews remain separate and are coordinated by main.

## Completed scope and contract

Nine assigned PHP files only:

1. `app/Services/Invoicing/BankMimeCaptureService.php`: one global slot under the selected existing Redis connection. Explicit operator arming creates an authenticated-encrypted ARMED envelope with a fresh random generation, existing local Store UUID/current user binding, schema and a Redis-clock-derived absolute expiry of at most 120 minutes. Redis 7 capability is checked before arming writes. The message path can only CAS the exact existing ARMED ciphertext to CAPTURED, requiring the same physical/encrypted deadline; SET XX PXAT preserves exact absolute expiry. Missing/evicted/expired/replaced slots fail closed. No event creates/rearms a key. Concurrent losers cannot overwrite.
2. `app/Support/Invoicing/BankMimeDiagnosticRunner.php`: stdin-only private diagnostic CLI transport, 15-second process timeout, 300000-byte combined stdout/stderr cap, cleared process buffers, sanitized errors without previous exceptions, strict envelope/status/summary validation and exact original SHA256 binding. At most five signatures, six fixed header names and bool/null facts. Unknown enum strings become fixed `unknown`; malformed structure is rejected.
3. `app/Services/Invoicing/BankMimeVerificationService.php`: off/unarmed path retains existing strict CLI. Selected ARMED Store uses the diagnostic CLI verification result once. Diagnostic/cache/schema failure falls back to existing strict CLI. Only existing invalid/invalid_signature decisions may capture; capture errors never alter verification, review or retry decisions. Existing audit metadata remains unchanged.
4. `config/bank_mail_capture.php`: `BANK_MAIL_CAPTURE_ENABLED=false`, `BANK_MAIL_CAPTURE_REDIS_CONNECTION=cache` defaults.
5. `app/Console/Commands/BankMailCaptureArmCommand.php`: operator-only `bank-mail:capture-arm {store} --minutes=120 --acknowledge-backup-retention`, existing local Store UUID, explicit acknowledgement, 1-120 minute bound and fixed JSON status only.
6. `app/Console/Commands/BankMailCaptureStatusCommand.php`: fixed JSON state/writing-enabled output, no owner/capture identifiers.
7. `app/Console/Commands/BankMailCaptureDiagnoseCommand.php`: CLI-only read-only diagnostic analysis, separately labeled original/recheck validated summaries. Reading is allowed while writing is disabled. No raw exporter, intake/import job, payment/provider call or financial replay.
8. `tests/Feature/BankMimeCaptureTest.php`: 31 feature scenarios against explicitly opted-in disposable real Redis, including forked two-process arming/capture races, exact expiry, eviction/generation replacement, owner selection/reassignment, disable/read behavior, schema/encryption/hash binding, plaintext and post-encryption caps, natural/restored expiry, PHP clock skew, final recheck expiry, actual subprocess output privacy, default strict-path compatibility and actual Node positive/tampered verification.
9. `tests/Unit/BankMimeDiagnosticSummaryTest.php`: 23 cases covering strict shape, malformed fields, unknown enums, null/false distinction, five-signature bound, exact six-header allowlist, hash/outcome binding and sanitized exceptions.

The complete encrypted CAPTURED envelope binds the original Base64-encoded MIME bytes, actual recipient, selected Store/current user, random activation generation, armed/captured timestamps, absolute expiry, schema, original SHA256 and original safe summary. Raw MIME is bounded to 256KiB; the complete serialized encrypted string is bounded to 1MiB. Decryption validates the schema, digest, current recipient-owner binding and exact physical deadline. Status/read/diagnosis do not touch TTL. Diagnosis rechecks the same unexpired captured ciphertext after subprocess execution before emitting summaries.

Main/Node authors own all runtime JavaScript, docs, env example, CI and API documentation artifacts; those changes were preserved and not edited by this agent. No payment/parser/dedupe/billing/provider/route/production configuration changes were authored.

## Frozen source SHA256

```text
8d604d7f4ce08a654bedc44f00146d986812865891392975b245891a58dfe3c9  app/Services/Invoicing/BankMimeCaptureService.php
a132bae601302829d5a332b564af7a20790f774f5f7e2bb39b722a7614388c3d  app/Support/Invoicing/BankMimeDiagnosticRunner.php
14d531430c0039e78b39f2f50617b7296e49bc180136c0dc2762526e442337e2  app/Services/Invoicing/BankMimeVerificationService.php
107dab598f3acd319e42c751ef1a5d41e0c091c047b518720ee1d8ec8d6f35f5  config/bank_mail_capture.php
398e4047cd0213233e29f4207640c70700c9659ac8a31a047e459ad9eae0d47d  app/Console/Commands/BankMailCaptureArmCommand.php
59a0273de28196ea0928980ff1c3d8738986a6673a8e081cb66ad45ee20722e2  app/Console/Commands/BankMailCaptureStatusCommand.php
8fbb499f761ad644722d08c62d827c6a3d036311b91b0cb25b39119a2007a65c  app/Console/Commands/BankMailCaptureDiagnoseCommand.php
f95c5f5fd9904caaf5428622296006947f21fec88198b9074138307c1e1f5984  tests/Feature/BankMimeCaptureTest.php
3f8d395c88d5b586448013f0290d227986a8effac917950abe37cc97d7618b1d  tests/Unit/BankMimeDiagnosticSummaryTest.php
```

## Verified test environment

Inspected main's `/tmp/bank-mime-capture-tests/run-php.sh` and ran an effective Laravel bootstrap probe before PHPUnit. Verified: environment testing; configuration uncached; SQLite `:memory:`; array cache/session/mail; sync queue; null logging; synthetic BTCPay URL. Redis uses loopback 127.0.0.1, cache DB1, dedicated `bank_mime_capture_test_` prefix; PING passed; pcntl_fork available. Redis lives in the separately created disposable network-none/no-persistence fixture; the test PHP container shares only that loopback network namespace. Existing locked dependencies are read-only mounted. Only the dedicated capture key under the verified prefix is removed by tests; no flush commands.

Explicit test opt-in is `BANK_MAIL_CAPTURE_TEST_REDIS=1`; tests additionally assert the testing/in-memory/loopback/DB1/dedicated-prefix conditions before any Redis key mutation. Outside an opted-in disposable fixture those feature cases skip. The final run executed every case with zero skips. Existing tests' REDIS_CLIENT=array environment is overridden only within this class's application config, with a fresh manager and connection; existing shared test files were not changed.

## Exact final commands and results

```text
sh /tmp/bank-mime-capture-tests/run-php.sh php artisan test tests/Unit/BankMimeDiagnosticSummaryTest.php tests/Feature/BankMimeCaptureTest.php tests/Feature/BankMimeConfirmationTest.php tests/Feature/BankCompanyMimeImportTest.php
PASS: 128 tests, 917 assertions, 36.08s; zero skipped tests reported.
Breakdown: 23 summary unit, 31 capture feature, 47 existing Store MIME, 27 existing Company MIME.

sh /tmp/bank-mime-capture-tests/run-php.sh vendor/bin/phpstan analyse --no-progress
PASS: no errors, existing phpstan.neon.dist and baseline unchanged.

sh /tmp/bank-mime-capture-tests/run-php.sh vendor/bin/pint --test app/Services/Invoicing/BankMimeCaptureService.php app/Support/Invoicing/BankMimeDiagnosticRunner.php app/Services/Invoicing/BankMimeVerificationService.php config/bank_mail_capture.php app/Console/Commands/BankMailCaptureArmCommand.php app/Console/Commands/BankMailCaptureStatusCommand.php app/Console/Commands/BankMailCaptureDiagnoseCommand.php tests/Feature/BankMimeCaptureTest.php tests/Unit/BankMimeDiagnosticSummaryTest.php
PASS: all nine assigned PHP files.

git diff --check
PASS.
```

Earlier checks and corrections are not final passes: first new-suite run had 48 passed/1 failed because the size-limit test's Crypt partial mock did not preserve initialized decryption state. The test now delegates decryption to the original real encrypter while mocking only oversized encryption output. First PHPStan run flagged Redis extension/magic-method typing, a pure-config narrowing and the already-string required CLI argument; explicit PhpRedis/other-connection dispatch and appropriate config impurity fixed them without weakening the baseline. During a combined run, main removed a task-created dependency symlink, disrupting company child-process imports; main identified the test setup mistake. The stabilized isolated Company rerun passed 27/180 and the final combined run passed all 128/917. No product behavior was changed to accommodate the mount problem.

## Dependencies, review and limits

Redis 7+ and its scripting/absolute-expiry commands are required; unsupported Redis cannot arm a slot. Real concurrency tests require pcntl. Existing Node runtime/dependency lock and the separately authored diagnostic CLI are used; no dependency installation occurred. Main owns CI/runtime qualification, API artifact generation, independent QA/security decisions and all publication.

Graphify tool/worktree graph unavailable; the previous graph was known stale for MIME edges. Used targeted assigned-path reads; no silent graph rebuild/install.

This feature cannot recover prior consumed MIME or establish actual current-bank signature/template compatibility. Existing Redis persistence/backup residual retention is separate from live TTL and must be acknowledged before activation. Feature remains OFF by default. No production database, wallet, funds, credentials or real bank message was accessed. No Git writes, commit, push, merge, package publication, deployment, Mailgun change or activation was performed.

No backend implementation blockers remain; independent review and main integration gates are outstanding.
