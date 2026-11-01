# Independent private bank MIME capture QA

Reviewer: /root/bank_mime_diagnostic_qa. Date: 2026-11-01.
Disposition: ACCEPT frozen bounded implementation for main integration. No verified QA source finding or unresolved test blocker. This is independent QA acceptance only; security review, fresh exact-head CI and separately approved deployment/activation remain separate gates.

## Repository, authorization and exact review scope

Repository webiumsk/satflux, branch feat/bank-mime-private-capture, source worktree bank-mime-private-capture, base HEAD037a6fa7e739597b6c54afb4b02d37a277dd3cc5. Reviewed uncommitted implementation, not a new remote commit or deployment. Main supplied owner implementation approval and design hashba1177a5867ddae9773499091ffd540cdd51be921cfe85b3ccb28d19b669b905. Approval does not itself activate content retention.

Reviewed source read-only; /tmp probes/report only. Primary unrelated dirty work preserved. No source/Git/shared-state edits, dependency install, production service restart/migration, real bank mail/upload, credentials, wallet/funds, provider calls, publication, merge, route change or deployment. Root instructions/state, role and integration-testing/architecture-review skills applied. Graphify unavailable in this worktree (graph absent); primary graph reported stale without current MIME edges. No graph/tool rebuild/install.

Frozen15file manifest independently verified before PHP tests and after all PHP/Node tests:

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
0a1ea5b71bd8903af8983253abd8203bfbf4f38ffd83c3dab70107c0f25c99da  runtime/bank-mail-verifier/verifier.mjs
8ec493e19e5400037cca56ce59a5e9f84b86b0da265a56b14163890848fa348c  runtime/bank-mail-verifier/diagnostic-cli.mjs
0dca44ff6b17cf9b3f2020041d84e45e102b4f6c8a43be73b0db1d5c6614f858  runtime/bank-mail-verifier/test/diagnostics.test.mjs
65b82c3bc4bc6ce45a49515d312a1acb851f573253cc1440aaa65fb3905d2287  app/Services/ApiDocumentation/DocumentationExporter.php
dae73989e85468120b900efc906adfef7fe86400c0ef98a7a587787224da8597  .env.example
3408a4ec48f3cf3bb8cf687810b0d38a27ff78399f29d5bb22c2713a7010fd54  .github/workflows/ci.yml

```

Additional documents/artifact inspected:

- Frozen design proposal SHA256ba1177a5867ddae9773499091ffd540cdd51be921cfe85b3ccb28d19b669b905.
- Implementation guide SHA25644f617ec1415a6c673a1e81c6d1349cc84691be218af7ac3a7fe9806572032c0; includes Redis7+ requirement, all-worker compatibility, default-off, backup acknowledgement, no transfer prerequisite and no runtime-pass claim from preparation.
- Generated docs/api/route-coverage.json SHA256465fac13d05978a84fb3e9751ff66d65eccc8a46f9c700e39a4073fd33c248ca; additions/updates are current source hashes only, routes/scope/generator unchanged from baseline.

## Disposable environment and safety preflight

Inspected main runner and copied a stricter /tmp/bank-mime-capture-qa/run-php.sh: /app read-only, public.env.example masks existing.env read-only, probes and existing locked vendor/Node dependencies read-only. PHPUnit uses --do-not-cache-result. Dedicated REDIS_PREFIXbank_mime_capture_qa_ avoids author/security namespaces; repository capture tests and independent probes ran sequentially within this prefix. No flush/restart operation.

Docker inspection of the assigned disposable Redis: networknone, bind127.0.0.1, saveempty, appendonlyno, no published ports, anonymous image /data volume only and no production bindmount. PHP test containers share only this namespace. Test DB is SQLite memory; Redis DB1 contains only synthetic QA records. Configuration cache absent.

Exact preflight command:

`/tmp/bank-mime-capture-qa/run-php.sh php /tmp/bank-mime-capture-tests/environment.php`

PASS: testing, uncached, SQLitememory, cachearray, queuesync, sessionarray, mailarray, lognull, syntheticprovider, Redisloopback/ping. PHP8.3.31/PHPUnit11.5.50, existing Node24.22.1, Redis7 with phpredis. Public.env.example defaults to phpredis; existing dependencies have no Predis package. Predis-specific fallback is source-reviewed but not runtime-qualified, no package installed. Standard approved Docker escalation used; no auto-review rejection or sandbox bypass.

## Exact commands and executed results

1. `/tmp/bank-mime-capture-qa/run-php.sh php vendor/bin/phpunit --do-not-cache-result tests/Feature/BankMimeConfirmationTest.php tests/Feature/BankCompanyMimeImportTest.php tests/Feature/BankInboundEmailTest.php tests/Feature/SepaBmailTest.php tests/Unit/TatraBankEmailParserTest.php tests/Unit/SepaBankEvidenceTest.php tests/Feature/BankMimeCaptureTest.php tests/Unit/BankMimeDiagnosticSummaryTest.php`

PASS243tests/1267assertions, zero failures/skips,40.702seconds,76.50MiB. Includes prior189bankmail cases plus54new capture/summary cases. Capture fixture explicitly enabled with disposable Redis gate, no skipped concurrency cases. Covers default-off, existing owner/window/ack validation, Redis clock, one global slot, exact expiry, eviction/stalegeneration, current owner binding, ciphertext/schema/hash/TTL validation, real natural expiry, numeric size boundaries, child errors and timeouts, sanitized summary validation, read-only/no-financial CLI, atomic arm/capture races, strict fallback/retry, actual diagnostic CLI positive/tampered path.

2. `/tmp/bank-mime-capture-qa/run-php.sh php vendor/bin/phpunit --do-not-cache-result /tmp/bank-mime-capture-qa/BankMimeCaptureIndependentQaTest.php`

Final PASS15tests/285assertions, zero failures/skips,6.389seconds,60.50MiB. One initial attempt stopped before executing tests because private QA helperput() conflicted with inherited Laravel TestCase::put(). Renamed only /tmp helper to putSlot(); no product source change or product failure.

Independent checks:

- Six forked processes blocked on explicit start barriers then competed against real Redis. Exactly1captured and5not_armed; loser retries and re-arm attempt preserved winner ciphertext and absolute expiry.
- Real100ms slot deadline,200ms test delay: expiry between token read/capture never creates a record; expiry during slow recheck suppresses both original/current summaries. No TTL simulation by production clocks.
- Deletion, disable/reenable, fresh service instance and stale token never re-arm; new explicit activation changes generation and rejects old token.
- Exact262144-byte payload plus binary/CRLF/NUL/non-UTF8 service-boundary fixture retained byte-exact under complete-envelope encryption, <=1MiB encrypted record. Binary fixture tests storage fidelity only; strict ingress UTF8 rejection is separately preserved, not bypassed.
- Authenticated malformed version/generation/owner type/clock/deadline/extra fields fail closed; unexpired invalid slots cannot be overwritten by arm. Different Store/recipient and transferred owner cannot capture/read.
- Invalid Redis connection results unavailable/null without additional transactions/audits, jobs or HTTP; existing encrypted slot intact.
- Real Node crypto with synthetic public DNS preload: valid signed message while ARMED retains valid proof without capture/audit; tampered body captures exact original received bytes and stays review-only. Operator Artisan diagnosis twice while writing disabled emits only original/recheck safe facts, never body/recipient/Store/generation/digest; ciphertext and expiry unchanged. Financial service mocked to prohibit handle, no queue dispatch/HTTP/imported transaction/sepa.bmail_processed audit, total audit count unchanged by command.

Probe SHA2566ad03eb45b2a41bc11ec787ba99543a094de21286cb6f8b525ccd264af7d9eb8. QA runner SHA2564cbfb10e6a1e7636817db34d71699acd4088d7cddcb4783644e29ebb6e2218c1.

3. `/tmp/bank-mime-capture-qa/run-php.sh node --test runtime/bank-mail-verifier/test/verifier.test.mjs runtime/bank-mail-verifier/test/company-extractor.test.mjs runtime/bank-mail-verifier/test/diagnostics.test.mjs`

PASS56/56, zero fail/cancel/skip/todo,23.817seconds. Frozen Node hashes matched before execution and final15file recheck. Includes real crypto, verify/diagnose equivalence, eligible proof versus missing signed headers/time/algorithm/signer failures, malformed/null facts, bounded5signatures, hostile/oversized input, attacker DNS denial, Company decoding, temporary DNS and13second transport timeout. Node24 local result is not Node22CI evidence.

4. `/tmp/bank-mime-capture-qa/run-php.sh php artisan api-docs:export --check`

PASS: API contracts and route inventory are current. Additional read-only Python comparisons confirmed unchanged route inventory/scope/generator and every generated source hash equals its current file.

5. `sha256sum -c /tmp/bank-mime-capture-source.sha256` and `git diff --check`

PASS before/after frozen-source tests. Baseline diff verification passed for ProcessBankNotificationEmail, BankInboundEmailService, BankStatementImportService, TatraBankEmailParser, TatraBankSepaEvidenceParser, strict CLI, Company CLI/extractor, runtime package/lock, composer files, routes and config/bank_inbound.php. No trust/financial parser/dependency/route modification inferred from unchanged bytes.

Final distinct executed counts: repository243PHP/1267assertions; private15PHP/285assertions; Node56. Combined PHP258tests/1552assertions. Earlier diagnostic QA counts are historical and not added again.

## Source and integration evidence

BankMimeCaptureService.php:20-41 uses Redis TIME and absolute PXAT with NX arming and ciphertext compare-and-swap existing-slot transition, preserving expiry and rejecting missing/replaced state. :46-136 bounds owner/window/raw/encrypted size, fixed summary eligibility, recipient ownership, fresh generation and disabled writes. :152-179 diagnoses without intake/replay and rechecks liveness after a child run. :188-202 validates recipient and encrypts whole envelope; :236-266 validates schema/time/exact physical expiry/content digest/summary/owner binding.

BankMimeDiagnosticRunner.php enforces exact diagnostic/envelope schemas, hash/outcome binding, fixed reason/verdict/header names, <=5signatures, boolean/null facts and bounded15second/300000byte transport. BankMimeVerificationService.php:122-160 selects diagnostics only when enabled+ARMED for the server-resolved owner, retains strict fallback, terminal status handling, review and capture only invalid_signature. Existing verified sender/digest/output checks remain after integration.

Node evaluate() finalizes the same eligible-proof/retry outcome before optional safe projection; verify() shares it and diagnostics expose only fixed fields. Original/private internal envelope is never the operator output. Arm command:12-40 requires explicit backup-retention acknowledgement, configured enable, existing local UUID/window and prints fixed JSON. Status/diagnose commands are CLI-only and emit safe results; no HTTP route added.

Main changes reviewed: env example only commented default-off options; CI adds ephemeral Redis7 service/disposable gate/prefix and redis extension alongside existing pcntl, enabling all capture tests; API exporter adds4new relevant source hash entries, and generated route inventory stays unchanged. Documentation accurately separates approved implementation from activation, explains all workers/Redis7/time limits/backup residuals, and keeps original bank EML requirement explicit.

## Compatibility, assumptions and limits

| Pairing/boundary | Evidence and limit |
|---|---|
| New PHP + Node24 + Redis7/phpredis | Independently executed, synthetic fixtures only |
| Default-off/unarmed old intake behavior | Existing189regressions plus enabled-path tests pass; baseline financial/Company/parser bytes unchanged |
| Diagnostic PHP/Node version mismatch/schema error | Fixed-schema failures sanitize/fallback; no financial handler invoked by operator command |
| Node22 CI and final commit | Not executed by this reviewer; fresh exact-head CI still required |
| Redis<7 | Unsupported capture backend; required Redis7 documented, arm capability guard fails unavailable; no production version inferred |
| Every deployed worker / key compatibility | Deployment prerequisite only; not observed in this review |
| Production backup residual deletion | No guarantee from TTL; existing retention/key handling requires acknowledgement; no backup settings altered |
| Actual current bank MIME/signature/layout | Unknown without private original; synthetic tests cannot establish historical rejection root cause or settlement |

No new transfer is required and no original consumed message is recovered. Captured mail remains untrusted; capture can miss a normal notification or be consumed by a different untrusted eligible message. A new successful diagnostic is not payment authorization. Production activation/real retention, real provider/plugin pairing and key/worker configuration are not approved or tested here.

Skipped as outside this bounded independent QA run: complete application PHP suite, frontend/browser/locale suite, full independent PHPStan/Pint, Node22CI and actual deployed Redis/worker/original bank mail. Author-reported static/style evidence is not claimed as QA execution. Independent security acceptance belongs to the separate reviewer/main. No unresolved source finding was verified; future deployment evidence remains a separate prerequisite, not a passed check.
