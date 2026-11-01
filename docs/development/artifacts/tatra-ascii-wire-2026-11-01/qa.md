# Tatra ASCII wire profile: independent implementation QA

Reviewer: /root/bank_mime_diagnostic_qa. Date:2026-11-01.
Disposition: ACCEPT the frozen bounded runtime implementation for integration; no verified runtime QA finding or unresolved test blocker. The requested runtime documentation clarification is closed by read-only follow-up; no outstanding QA finding remains. Security review, fresh exact-head CI and deployment/operator qualification remain separate gates.

## Repository, scope and reviewed revision

Repository webiumsk/satflux, branch fix/tatra-ascii-wire-profile, base406e1fefb9840d1ac851a7fdbf337f84eba0b645. Reviewed uncommitted source delta, not a published final commit or deployment. Contract docs/development/TATRA_ASCII_WIRE_PROFILE.md. Main supplied implementation authorization within the Tatra confirmation work after independent architecture review and operator enum-format facts.

Only /tmp probes/report written. No repo/Git/shared-state edits, install, production service/database/credentials, real bank EML, wallets/funds, external provider API, financial replay, merge/publication/deployment or Mailgun change. Primary unrelated dirty work preserved. Root instructions/state and integration-testing/architecture-review workflow applied. Scoped Graphify graph absent; main reported stale irrelevant primary query. No graph rebuild.

Frozen 9-source hashes independently matched before PHP tests and after all checks. Four Node sources were separately frozen and verified before independent Node execution, then matched the same final manifest:

```text
dfc646f7a8eeebd85cc72255a591230d4c00b43626140fab462d2c90b101e40a  runtime/bank-mail-verifier/verifier.mjs
c3f60f6f20468cf38e2baf93e4282198a60e471167a0d79c8f22728142cb82ef  runtime/bank-mail-verifier/ascii-wire-profile.mjs
98e4ae512d78405fe22fe1fdd502030d77a81b2bd65ee5d6b9e953c6e0595395  runtime/bank-mail-verifier/test/ascii-wire-profile.test.mjs
cb7a978cdaf4251b41ffeff71dad97a02489b3cd50fc084a7854deca1ecf2c3d  runtime/bank-mail-verifier/test/build-ascii-wire-message.mjs
2e0e587f5ea4c021961ef62c7f4916d830f4977eb0e04ac855d420319c709be5  app/Services/Invoicing/BankMimeCaptureService.php
97a28764a16fb2775a80341cd722205f508108863d927651a952f6605025af42  tests/Feature/BankMimeCaptureTest.php
60bc800be42a53e5184915c746e22b229ca1f5ffe1e036a9841f6aebee9a6bb4  tests/Feature/BankMimeConfirmationTest.php
c3f25b5307aebf5297a8409a0102a9555fd95e70bdad8258e2a7c8f5c4b7952a  app/Services/ApiDocumentation/DocumentationSchemas.php
1bdfc2fc66d05354291bf834a16859d7f2afc66f1935137585d8b7db954bad66  app/Services/ApiDocumentation/DocumentationExporter.php

```

Generated API/documentation reviewed: route-coverage.json SHA256f78799a798daad43e58774459dd56ac15fe1eb6b573715694e5f94e33dd7e679; webhooks.json SHA256aee24dbe935e42355525cad869b5e90839d91c062c9c7f6d774329e0f1d58791; Initial contract guide SHA256593a8718ea5a5769ca989ff1a1671b1a79550bd677ee3a049592fddcf4906be4; docs-only follow-up adds the exact bounds and recognized-profile requirement, without runtime changes. Docs are main-owned and may receive clarification after this runtime freeze.

## Verified disposable environment

Main /tmp/tatra-ascii-wire-tests/run-php.sh/environment.php inspected. QA /tmp/tatra-ascii-wire-qa/run-php.sh adds read-only /app source, public.env.example mask over.env, read-only probes/dependencies and unique REDIS_PREFIXbank_mime_capture_tatra_qa_. Repository/private PHP suites ran sequentially under this prefix, without author/security collisions. All PHPUnit commands use --do-not-cache-result.

Docker inspect confirmed satflux-tatra-ascii-redis-test networknone, loopbackbind, saveempty/appendonlyno, no ports and anonymous image/data volume only, no production bindmount. PHP shares only that namespace. Existing deps reused without install, PHP 8.3.31/PHPUnit11.5.50, Node 24.22.1, Redis 7/phpredis. Synthetic app/provider/Mailgun keys, SQLite memory, array cache/session/mail, sync queues, null logs, uncached config. Tests explicitly enable disposable Redis and only manipulate own diagnostic key; no flush/restart.

Preflight `/tmp/tatra-ascii-wire-qa/run-php.sh php /tmp/tatra-ascii-wire-tests/environment.php` PASS all checks including loopback Redis ping. Standard approved Docker escalation used; no approval rejection/bypass. No PHP source suite ran before main's full freeze; Node tests ran only after its separate freeze.

## Exact executed commands and results

1. `/tmp/tatra-ascii-wire-qa/run-php.sh php vendor/bin/phpunit --do-not-cache-result tests/Feature/BankMimeConfirmationTest.php tests/Feature/BankCompanyMimeImportTest.php tests/Feature/BankInboundEmailTest.php tests/Feature/SepaBmailTest.php tests/Unit/TatraBankEmailParserTest.php tests/Unit/SepaBankEvidenceTest.php tests/Feature/BankMimeCaptureTest.php tests/Unit/BankMimeDiagnosticSummaryTest.php`

PASS: 256 tests / 1,550 assertions, zero failures/skips,73.456 seconds,76.50 MiB. Previous 243 bank-mail/capture/summary cases plus 13 new source cases all execute, including real Redis concurrency/expiry, original summary compatibility, current supported/unsupported frame privacy, actual Node-to-PHP normal report contract, signed versus fallback identity, unsigned encoding mutation and encoded/nested frame rejection. No inferred pass from historical suites.

2. `/tmp/tatra-ascii-wire-qa/run-php.sh php vendor/bin/phpunit --do-not-cache-result /tmp/tatra-ascii-wire-qa/TatraAsciiWireIndependentQaTest.php`

PASS: 19 tests / 197 assertions, zero failures/skips,36.810 seconds,64.50 MiB. Private fixture producer uses actual pinned signer/crypto and public synthetic DNS preload; no production test flags accepted by runtime CLI.

- Two QR/VS scenarios each send three real signed Mailgun deliveries through production Node verifier, PHP encrypted job, strict evidence parser and stubbed provider: fully signed legacy, new core-header fallback and raw SP/HTAB equivalent fallback. Exact reference/account/amount/currency/origin and invoice-independent credit identity/dedup key match across all representations. This proves client report identity stability, not an independently executed real provider settlement/idempotency engine.
- Fourteen negatives prohibit every provider request/import/processed-payment audit: prefixed outer text, two frames, duplicate reference, reference hidden in note, literal QP reference, base64-looking body, HTML-wrapped frame, multipart header, unsigned QP/base64 headers, unqualified charset, simple-body mode, non-ASCII body and amount tamper. Authenticated arbitrary/embedded text is never sufficient financial evidence.
- Actual fallback delivery against provider capabilityv1 performs only settings lookup, no POST, and reaches unsupported_plugin_contract review. No new bypass of capability gating.
- Two actual retained-original CLI rechecks start from encrypted old v1 invalid_signature summary with unsigned MIME facts, then recheck supported/unsupported bank bodies through the real Node fallback. They preserve old original exactly, report verified+fixed body_profile and matched/unsupported, and show MIMErequiredfalse for current fallback. Output excludes body, note, account, amount, reference, recipient and Store identifiers. Ciphertext and exact absolute expiry remain unchanged while writing is off. No intake handler, queue dispatch, provider HTTP, transaction import or audit mutation.

Private PHP probe SHA256b52839b7516f2ec2e5f6197c48617dccb5214648167a05e1815c8033a16df7f9; private builder1bad2c0ef73aa3d856dde5734c0e09f544a343d054519189f4152024b3ca42b1. QA runner SHA2564629a714138944b50c85517867778d4c87ba1442a23cd949b0a95c2be7670cb0.

3. `/tmp/tatra-ascii-wire-qa/run-php.sh node --test runtime/bank-mail-verifier/test/verifier.test.mjs runtime/bank-mail-verifier/test/company-extractor.test.mjs runtime/bank-mail-verifier/test/diagnostics.test.mjs runtime/bank-mail-verifier/test/ascii-wire-profile.test.mjs`

PASS: 77 / 77 tests, zero fail/cancel/skip/todo,19.760 seconds. Existing 56 cases remain;21 new cases cover real unsigned-MIME positive, old result/decoder precedence, metadata admission/no decoder selection, non-ASCII/control/newline/size limits, exact same-proof hash/length/mode binding, signed core/algorithm/key/time/recipient/no-l requirements, tamper, WSP equivalence, uppercase known mode token, mixed/order/removal/multiple-signature non-combination, safe diagnosticv1 parity and test-only builder. Includes the 13-second hard timeout. These are local Node 24 executions, not Node 22CI.

4. `/tmp/tatra-ascii-wire-qa/run-php.sh node /tmp/tatra-ascii-wire-qa/projection-model-probes.mjs`

PASS: 128 deterministic bodies / 666 explicit checks. Independently expressed regex RFC-relaxed model agrees with production linear helper, including idempotence, empty bodies, same-proof binding, altered hashes/lengths/limits/modes, noninteger/nonfinite lengths, controls/UTF8 and malformed newlines. Private model SHA2561ef0b2eca8d9fe200a43036f50414677803be941bdced74834cda29c0f346f11. These are explicit model checks, not additional77Node test cases.

5. `/tmp/tatra-ascii-wire-qa/run-php.sh php artisan api-docs:export --check`

PASS: API contracts and route inventory current. Read-only Python verifies unchanged route inventory/scope/generator and every generated source digest equals its source. Generated webhook description now accurately distinguishes full-MIME and fixed ASCII profiles. DocumentationExporter includes the new helper in hash coverage; no HTTP path/auth/schema behavior added.

6. `sha256sum -c /tmp/tatra-ascii-wire-source.sha256`, `git diff --check` and scoped baseline comparisons

PASS before/after. Scripted legacy eligible-proof predicate comparison is byte-identical to406e1fef. Baseline diff empty for PHP verification/diagnostic transport, Tatra parsers, SepaEmailConfirmationService, financial job/intake, original CLIs, Company decoder/CLI, package/Composer locks/manifests, routes and capture/inbound configuration.

Private harness syntax check passed before application tests. All actual final source/private/model suites passed first execution; no source or QA harness correction was needed after those executions.

Final distinct counts: repository: 256 PHP tests / 1,550 assertions; private: 19 PHP tests / 197 assertions (combined: 275 tests / 1,747 assertions); Node: 77 tests; separate projection: 128 bodies / 666 checks. Prior design/capture QA counts are historical, not added again.

## Verified source properties

verifier.mjs retains complete old predicate first. Fallback only with no old proof, MIME1.0/plain UTF-8/7bit, same bank/alignment/algo/time/fullbody/core-header proof. ascii-wire-profile.mjs only accepts known relaxed/relaxed (casefold known token only), exact false limit flag, safe canonical lengths and both same-proof SHA256 hashes. Linear projection validates ASCII/control/CRLF, raw/canonical <= 65,535 bytes and physical wire line <= 998 bytes, reduces only SP/HTAB without concatenating tokens, removes trailing WSP/empty lines and returns authenticated canonical bytes. No MIME-decoded body is used for fallback. Legacy success shape/text/precedence unchanged; fallback adds only fixed internal bodyProfile.

CaptureService adds fixed outer profile enums. Pure TatraBankSepaEvidenceParser runs only on recognized verified bounded UTF-8 body, catches failures and discards parsed values. Unknown bodyProfile yields unknown/not_evaluated, invalid/retry statuses are not evaluated. Old original diagnostic schema stays fixed; no arbitrary marker output. Final same-cipher/expiry guard runs after classification, so expired/replaced captures cannot release summaries. No financial import/report/dispatch path is invoked.

No current-body layout or historical payment is established by these synthetic passes. The pure operator status is intended to qualify the existing retained capture locally after compatible deployment without requesting a new transfer or replaying its financial handler.

## Documentation follow-up and limits

CLOSED: read-only docs follow-up verifies runtime README explicitly labels legacy fully signed MIME and the fixed ASCII fallback, documents both 65,535-byte body limits/998-byte wire lines, fixed bodyProfile and outer structural labels. Runbook explains same-proof interpretation, retained unexpired capture compatibility and no replay/restore. Capture implementation guide distinguishes unchanged capture policy from the newly reviewed bank profile. Historical capture artifact README is labeled as 406e1fef evidence whose old hashes are not the new verifier/service manifest. Contract includes exact limits and recognized-profile-only pure frame guard. All 9 frozen runtime/source hashes still match and git diff --check passes; no retest was necessary or performed for docs-only changes.

| Boundary | Evidence/limit |
|---|---|
| New Node 24 + PHP 8.3 + Redis 7/phpredis + synthetic contract v2 | Independently executed normal/review/CLI flows |
| Existing signed MIME/Company/parser/history/provider policy | Unchanged preserved paths plus existing regression suites; new profile result shape isolated |
| Older provider v1 | Actual new fallback stopped before financial POST |
| Old capture original v1 | Real stored envelope remains readable and separate from current new facts |
| Node 22/exact published head | Not executed by this reviewer; fresh CI required |
| Actual all-worker deployment/Redis/key/capture availability | Not observed; compatible rollout and original capture expiry remain operator concerns |
| Actual current Tatra transaction layout/payment | Unknown until local pure structural qualification; matched remains structural, not paid |

No source/security/runtime permission is inferred from skills or earlier design review. Independent security disposition belongs to separate reviewer/main. Skipped outside bounded QA: complete application PHP suite, independent full PHPStan/Pint, frontend/browser/locale suite, actual provider/package settlement and live bank mail. Main/author static/style evidence is not claimed as independently rerun. No production, Git publication or deployment occurred.

## Docs-only closure hashes

- Runtime README:761a625bb0d1618add055e1a80bfcc277326eeca001d06029138350f2bc95efc
- Contract:6c79dd562f76e77cc6b9824d059c9a1f2deee2046f85d10395de5a39158e14d7
- Capture implementation guide:40efa6e631a592433e887337e579f08384aab27381c821a43e54d8169606a6de
- SEPA runbook:d4162d73c896932fe360186f464885190c28ae90e914552b6cd545ffaf8bc5de
- Historical artifact README:8ad61fb3d490d89d288f4efccd8c50a0b91ddb1229753696265148222f194574

Final QA acceptance remains unchanged; all documentation notes closed. No extra runtime tests executed for this closure.
