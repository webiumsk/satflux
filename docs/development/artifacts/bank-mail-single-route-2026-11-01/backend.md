> Archived review evidence. Private fixture/runner paths below are redacted;
> they are not committed runnable tests. Use the repository commands in README.md.

# One-route bank MIME implementation - backend handoff

Date: 2026-11-01. Author: `/root/wallet_drift_backend`.

Repository `webiumsk/satflux`, branch `fix/bank-mail-single-route`, base `c90caa40e048cdf2b740e0195fd7198bd5209fe7`. Worktree `storage/app/private/worktrees/bank-mail-single-route`. Main owns separate documentation/API/generated/shared-state edits. No author commit, push, merge, publication, deployment, route edit, wallet operation, actual bank message or production access.

Architecture and acceptance source: main-owned `docs/development/BANK_MAIL_SINGLE_ROUTE.md`, independent QA architecture matrix and earlier `<private-bank-single-route-backend>/REPORT.md`. The implementation is frozen for independent security/QA review. It does not self-approve security.

## Completed bounded behavior

The shared `.mime` endpoint now supports both existing recipient-owner policies:

- Controller authenticates Mailgun as before and encrypts optional bounded envelope sender separately from exact original MIME. Jobs retain blank decoded fields. Request-provided mode, owner IDs and bank-proof markers are not copied.
- `BankMimeVerificationService::preparePayload()` resolves the recipient through existing persisted company/store address tokens. Store always uses existing strict verification; invalid Store MIME never falls through to company extraction. Company uses a separate extraction-only Node entrypoint and binary-safe base64 original-byte transport.
- `PreparedBankMimePayload` is a readonly, transient worker result containing the server-resolved Company/Store and decoded payload. It is never attached to the job. Final `BankInboundEmailService::handle()` re-resolves the recipient and compares exact model class and primary key before parsing/import/reporting. Its optional arguments are server-owned values, not flags accepted from HTTP.
- Company decoder uses the existing locked mailparser and Node ICU decoder. It supports tested plain UTF8/ASCII, quoted-printable, base64, Windows-1250/ISO-8859-2 including multipart text, multipart alternatives with plain preference, HTML-only conversion and bounded ignored attachments. It has no DNS or network path and produces `extracted`, never `verified` or a bank domain.
- PHP checks extraction status, scalar fields, byte limits, UTF8 and exact original-byte SHA-256 binding. It copies only required decoded fields. Company result/request proof markers never propagate into automatic Store confirmation.
- Company preserves legacy envelope-sender preference, falling back to MIME From for old jobs without the new optional encrypted field. Store ignores envelope sender for identity and continues requiring its signed original From/To/profile.
- Only the existing forwarding-detection facts leave the company decoder; raw header values do not. Existing configurable forwarded-message rejection remains effective.
- Job ciphertext remains unchanged across processing, retries and failures. Temporary errors get generic messages without previous exceptions or decoded values. Company no-row diagnostics omit decoded sender/subject under server-provided raw privacy context; old parsed/native diagnostics and APIs keep their default behavior.

Company imports retain existing scoped bank-transaction persistence/matching and may mark local business documents Paid. This is legacy company import policy, not bank-origin proof or confirmation that actual funds were received. Strict Store Tatra DKIM/original-account/positive-amount/reference/contract-v2 guards remain separate.

## Authorized compact-date correction

An original compact native-to-MIME duplicate regression failed because `TatraBankEmailParser::parseCompact()` initialized `bookedAt` using `createFromFormat('d.m.Y', ...)`, inheriting processing-time hours/minutes/seconds. Those values enter the existing dedup key, so unchanged messages could produce a second transaction across processing seconds. Main explicitly expanded ownership to the parser and its unit test.

For an explicitly observed compact date only, parsing now uses `!d.m.Y`. Midnight represents date precision; it is not a claimed bank transaction timestamp. The original compact duplicate feature fixture remains and advances the clock two and three seconds between native/first MIME/retry. New units prove a stable observed date across different processing days and preserve the existing no-date processing-time fallback. Classic parsing, bank evidence/reference/credit flags, SEPA credit identity, dedup algorithm, schema and historical rows are unchanged.

Limitations that must remain visible:

- Existing historical compact rows retain old processing times. Replaying them after this change can require manual reconciliation; no migration or historical correction occurred.
- A compact notification without a date still uses the existing processing-time fallback and has no new stable event identity.
- Distinct identical same-day compact events without a bank ID can conservatively collide under the existing company dedup fields. The adapter does not invent bank-issued IDs or new settlement proof.

## Owned frozen files

The exact 12-file SHA-256 manifest is `<private-bank-single-route-backend>/FROZEN_SHA256SUMS`:

1. `app/Http/Controllers/Invoicing/BankInboundWebhookController.php`
2. `app/Services/Invoicing/BankMimeVerificationService.php`
3. `app/Jobs/ProcessBankNotificationEmail.php`
4. `app/Services/Invoicing/BankInboundEmailService.php`
5. `app/Support/Invoicing/PreparedBankMimePayload.php` (new)
6. `runtime/bank-mail-verifier/company-cli.mjs` (new)
7. `runtime/bank-mail-verifier/company-extractor.mjs` (new)
8. `runtime/bank-mail-verifier/test/company-extractor.test.mjs` (new)
9. `tests/Feature/BankCompanyMimeImportTest.php` (new)
10. `tests/Feature/BankMimeConfirmationTest.php`
11. `app/Services/Invoicing/BankImport/TatraBankEmailParser.php` (explicit scope expansion)
12. `tests/Unit/TatraBankEmailParserTest.php` (explicit scope expansion)

Strict Node files are byte-identical to base `c90caa40` (`git diff --exit-code` passes):

```text
bfddc7a1a4c5f641a8682a088fa054dcaccc32051920dcbd3212ef8bf89ac4b0  runtime/bank-mail-verifier/cli.mjs
a0553fb7df2eaec2e82bcaea0257246176bca181678436417a7360160fb8cb06  runtime/bank-mail-verifier/verifier.mjs
```

No author dependency installation, dependency/config/route/schema/provider/UI/billing change or shared coordination-state edit. No task-created Node symlink: existing dependencies are read-only runner mounts prepared by main. Main's other edits were preserved.

## Verification environment

Runner: `sh <private-bank-single-route-tests>/run-php.sh`. Main prepared the isolated worktree, public sample synthetic environment, existing qualified locked vendor/Node dependencies and Node 24 binary. Container has `--network none`; vendor/dependencies are mounted read-only. Ordinary sandbox escalation was used for Docker socket access.

Effective Laravel bootstrap was inspected before PHPUnit: PHP 8.3.31, `testing`, SQLite `:memory:`, array cache/mail/session, synchronous queue, `config_cached=false`. Shared TestCase prevents stray HTTP. New company tests reject any attempted provider call. No production `.env`, merchant credentials, databases, wallets or messages were used.

Final commands/results:

```sh
sh <private-bank-single-route-tests>/run-php.sh php artisan test \
  tests/Feature/BankCompanyMimeImportTest.php \
  tests/Feature/BankMimeConfirmationTest.php \
  tests/Feature/BankInboundEmailTest.php \
  tests/Feature/SepaBmailTest.php \
  tests/Unit/TatraBankEmailParserTest.php \
  tests/Unit/SepaBankEvidenceTest.php
```

**PASS: 144 tests / 521 assertions.** This includes Store synthetic real-DKIM positive/tamper/recipient binding, Company Tatra/SLSP import/match/tenant separation, sender/privacy/forwarding/retries, typed owner reassignment, explicit proof stripping, child digest failure, existing native/parsed Mailgun behavior, strict evidence/reference/provider cases and compact date precision.

```sh
sh <private-bank-single-route-tests>/run-php.sh node --test \
  runtime/bank-mail-verifier/test/company-extractor.test.mjs \
  runtime/bank-mail-verifier/test/verifier.test.mjs
```

**PASS: 42 tests (16 Company + 26 unchanged strict Store), zero skips.** Includes real CLI, exact-byte digest, charsets/encodings, multipart/plain preference, HTML-only conversion, ignored attachments, forwarding facts without private values, invalid root inputs/schema/charsets, raw/body/header/subject limits, inherited >1,000-part and deep-HTML safeguards, maximum decoded-body JSON expansion and unchanged cryptographic Store boundary.

Scoped `php vendor/bin/pint --test` over all nine owned PHP files: **PASS 9 files**. `git diff --check`: **PASS**. Full existing-baseline `php vendor/bin/phpstan analyse --memory-limit=1G --no-progress`: **PASS, [OK] No errors**, level 5 and baseline unchanged.

Earlier failures were not treated as passes: initial Company Node run 13/14 exposed root UTF7 fallback, corrected with explicit root charset policy; first PHP run had a Mockery spy assertion-order error, corrected in tests; expanded PHP run 141 pass/1 failure exposed compact date dedup, corrected only after main scope authorization while retaining the original fixture; the final style run required importing Carbon in the feature test. The 144-test successful run preceded only that equivalent test import-style correction; no application behavior changed afterward. Independent reviewers run the final frozen bytes.

## Remaining supported-input and operational limits

Raw MIME is capped at 256 KiB; decoded Company body at 65,535 UTF8 bytes, subject at 500 bytes and From at 255 bytes. Company header preflight is bounded at 32 KiB, output/capture at 300,000 bytes, CLI input/base64 expansion is bounded, and CLI/PHP deadlines are 13/15 seconds. Locked mailparser provides tested child-count and HTML nesting guards. Attachments remain bounded by raw input and are ignored, never imported or persisted. HTML is decoded locally without browser execution or remote fetches.

This is not a new comprehensive MIME validator. Root unknown/refused charset and malformed root transfer encodings are explicitly rejected. Nested reserved charset and child transfer-encoding handling retains permissive inherited mailparser behavior in the legacy untrusted Company branch; no broad claim that every malformed nested MIME is rejected. Store uses its unchanged strict profile. Larger raw messages with small legacy parsed bodies or multibyte bodies accepted under old character-count limits can exceed the bounded Company MIME profile; unsupported inputs remain unconfirmed rather than silently altering limits.

Main must coordinate consumer deployment and worker reload before changing the existing one EU catch-all rule to `.mime`. Rollback must coordinate old workers and pending encrypted company MIME jobs. No route edit/deployment was performed here. Actual current bank EML remains unavailable after reported404 retrievals; synthetic coverage does not prove actual bank interoperability or confirm the reported payment.

Independent security/QA final reports remain separate gates. No implementation or author-verification blocker is known; final independent acceptance remains pending.
