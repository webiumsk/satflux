> Final follow-up review evidence. Redacted private fixture/runner paths are
> not committed runnable tests; use the public commands in README.md.

# PR414 sender follow-up and final scope

Date: 2026-11-01. Author: `/root/wallet_drift_backend`.

Repository `webiumsk/satflux`, worktree `bank-mail-single-route`, branch `fix/bank-mail-single-route`, reviewed public head `12e00fa4ff97fc8be2e01de6061ef4c13dbedf07`. Current follow-up is uncommitted and only main owns publication. Main's documentation/API/evidence edits are separate and preserved. No production, bank messages, wallets, credentials, external API, route configuration or deployment was accessed or changed.

## Sender correction

Optional Mailgun envelope sender previously participated in required request validation and could return422 before valid signed Store MIME was processed. Removed that optional validation gate. The controller now encrypts sender metadata only if it is a string of at most255 UTF8 bytes, nonempty after trim and free of CR/LF/NUL. Unusable optional metadata is omitted rather than rejecting the raw message. Original recipient/MIME authentication and size checks remain unchanged.

Company extraction falls back to MIME From when the optional encrypted metadata is absent. A usable but unsupported envelope sender remains authoritative under legacy sender preference; it cannot silently fall back to a supported MIME bank From. Store identity continues to come exclusively from its existing signed MIME proof. No worker, decoder, proof or tenant policy changed in this follow-up.

New regressions cover array/integer/Boolean, oversized ASCII, oversized multibyte, CRLF, NUL, invalid UTF8, null and empty sender values. For every variant, Company dispatch omits encrypted envelope metadata and imports through MIME From. The actual signed Store CLI/parser/report path also succeeds for every variant. A valid unsupported envelope sender leaves the Company business document unmodified, preserving legacy priority.

## Deliberate compact-date scope reduction

Main and independent security/QA reassessed the optional `!d.m.Y` compact-date change after a concrete collision reproduction: it could cause distinct same-day compact notifications without bank event IDs to share the existing dedup key. Historical acceptance of the first implementation was not product approval for that loss risk.

At main's explicit instruction, `TatraBankEmailParser.php` and `TatraBankEmailParserTest.php` are restored byte-for-byte to original base `c90caa40`. No global dedup change, migration, historical-row rewrite, reconciliation mechanism or fabricated bank identity was introduced. Existing compact imports retain their processing-time defaults and known retry limitation; no stable compact replay guarantee is claimed.

Transport/native-to-MIME dedup is verified using a classic notification with an explicit bank booking timestamp while processing clocks advance. A separate regression proves that two same-day compact messages with the same amount/reference processed at different clocks both persist under the existing policy. This verifies final transport compatibility without silently changing event identity semantics.

The strict Store Node CLI and verifier also remain byte-identical to base `c90caa40`. Verified with:

```sh
git diff --exit-code c90caa40 -- \
  app/Services/Invoicing/BankImport/TatraBankEmailParser.php \
  tests/Unit/TatraBankEmailParserTest.php \
  runtime/bank-mail-verifier/cli.mjs \
  runtime/bank-mail-verifier/verifier.mjs
```

## Frozen review target

Final ten-file manifest: `<private-bank-single-route-backend>/FOLLOWUP_SHA256SUMS`. This replaces the historical twelve-file implementation review scope. Initial manifest remains preserved for historical PR414 evidence; it is not the final current target.

Only follow-up writable files changed:

- `app/Http/Controllers/Invoicing/BankInboundWebhookController.php`
- `tests/Feature/BankCompanyMimeImportTest.php`
- `tests/Feature/BankMimeConfirmationTest.php`
- The two parser files restored to base under main's explicit scope decision.

All seven other original MIME implementation files retain their previously reviewed bytes. Main-owned docs/API/generated/shared-state changes are outside backend ownership. No Git actions were performed.

## Verification

Rechecked existing runner `sh <private-bank-single-route-tests>/run-php.sh`: offline Docker `--network none`, qualified read-only vendor and Node dependencies, synthetic sample configuration. Effective Laravel bootstrap again resolves `testing`, SQLite `:memory:`, array cache/mail, sync queue, no config cache. No production environment file was read.

Targeted command:

```sh
sh <private-bank-single-route-tests>/run-php.sh php artisan test \
  tests/Feature/BankCompanyMimeImportTest.php \
  tests/Feature/BankMimeConfirmationTest.php \
  --filter='unusable_optional|usable_unsupported|same_day_compact|parsed_then_mime'
```

**PASS14 tests /116 assertions.** Includes actual synthetic signed Store verification/reporting, not a verification stub.

Scoped Pint over the three sender follow-up PHP files: **PASS3 files**. Full existing level5/baseline `php vendor/bin/phpstan analyse --memory-limit=1G --no-progress`: **PASS, [OK] No errors**. `git diff --check`: **PASS**.

Affected six-suite PHP command and final result:

```sh
sh <private-bank-single-route-tests>/run-php.sh php artisan test \
  tests/Feature/BankCompanyMimeImportTest.php \
  tests/Feature/BankMimeConfirmationTest.php \
  tests/Feature/BankInboundEmailTest.php \
  tests/Feature/SepaBmailTest.php \
  tests/Unit/TatraBankEmailParserTest.php \
  tests/Unit/SepaBankEvidenceTest.php
```

**PASS155 tests /623 assertions.** All final ten-file manifest hashes reverified after execution. Node source/tests are unchanged from the preceding independently passing42-test execution, so that result remains attributed to the earlier run, not claimed rerun here.

Current synthetic results do not prove actual current bank signing/layout interoperability or confirm receipt of the reported payment. Independent final security/QA review of the ten-file manifest remains a separate gate. No author approval, merge or deployment.
