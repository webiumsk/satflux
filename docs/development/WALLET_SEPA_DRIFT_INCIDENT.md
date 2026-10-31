# SEPA activation false wallet drift - 2026-11-01

## Evidence and scope

The owner reported deployment of the merged consumer/provider changes, a wallet
security alert after enabling SEPA, and a pending EUR 1 QR payment. GitHub confirms
consumer PR #412 merged as `9c329264f79679d85b85094632503c4aa3fa74bc`
and provider PR #45 as `9c6d7b3057684d30b737ad66425cdbb6c43eb4f6`.
Actual production revisions remain unverified independently.

The supplied application excerpt records a SEPA_INSTANT-only added-method drift
at 22:50:02 on October 31 and `unverified_bank_origin` at 22:50:46. This proves
processing reached the bank-origin review guard for a parsed credit; it does not
establish a bank-authenticated credit or identify the exact payment by itself.
Earlier 21:59 database/Redis DNS errors do not prove that later processing failed.
No merchant identifiers, raw logs, session data or bank message are retained here.

The crypto monitor treated the SEPA activation Boolean as a receiving wallet.
Bank beneficiary settings use the plugin's separate SEPA settings endpoint.
This integration regression produced a misleading wallet security warning.

## Acceptance contract and ownership

| Owner | Scope | Evidence |
| --- | --- | --- |
| `/root/wallet_drift_backend` | Service and feature test below only | Focused tests, Pint, full existing-baseline PHPStan |
| `/root/wallet_bmail_security` | Independent read-only security review | Accepted exact frozen two-file patch, 45 boundary assertions and 23 feature tests |
| `/root/wallet_bmail_qa` | Independent read-only QA; private synthetic fixtures | 38 tests / 323 assertions including legacy recovery and source diagnostic controls |
| Main | Integration, this record, project state and Git publication | Scoped diff, reviewed hashes; original dirty checkout preserved |

Ignore exactly case-insensitive `SEPA_INSTANT`, preserving the existing LNURL
exclusion. Apply the rule to both fresh snapshots and historical fingerprints
without changing the stored baseline. Continue monitoring BTC Lightning/on-chain,
CASHU, CASHUMELT, unknown methods and SEPA lookalikes. Concurrent genuine crypto
changes must remain drift. Bank confirmation/account/reference guards are unchanged.

The existing merchant-authenticated fresh read and store wallet lock remain
required to resolve an incident. API errors, missing credentials or lock contention
cannot clear it. Successful matching verification retains incident history and
does not replace the baseline, reconnect the wallet or learn a new payee.

There are no UI, notifier, locale, schema, plugin or package changes. Existing UI
uses the backend incident state; no frontend-only hiding is introduced.

## Reviewed source and tests

Base: `9c329264f79679d85b85094632503c4aa3fa74bc`.

| File | SHA-256 |
| --- | --- |
| `app/Services/WalletSecurity/WalletConfigIntegrityService.php` | `eb30c48fabb428a3d84da57737d9791a6669809e4f2ce7111efd90b50abad245` |
| `tests/Feature/WalletConfigIntegrityTest.php` | `37fcf16912052d3ea00ef4cd76734cdbfe6a9f40c4192a8059dfbdc68e0d64a8` |

Reproduce the committed regressions in a disposable test environment:

```sh
php vendor/bin/phpunit --do-not-cache-result tests/Feature/WalletConfigIntegrityTest.php tests/Feature/WalletCashuFallbackIntegrityTest.php
php vendor/bin/pint --test app/Services/WalletSecurity/WalletConfigIntegrityService.php tests/Feature/WalletConfigIntegrityTest.php
php vendor/bin/phpstan analyse --memory-limit=1G --no-progress
git diff --check
```

Author results: wallet 23 / 213, Cashu fallback 12 / 34, Pint 2 files and full PHPStan
pass with unchanged baseline. Independent QA: the same suites plus 3 private
incident/control fixtures pass 38 / 323. Those private fixture counts are review
evidence, not additional committed tests. Independent security reproduced the
old SEPA-only false positive, passed 45 pure boundary assertions, then independently
passed the wallet suite 23 / 213 and existing Node verifier 26 / 26 with zero skips.
The existing parsed-Mailgun origin regression was also rerun after the supplied
log: 1 test / 2 assertions passed, with no provider HTTP call. Both reviewers
accepted the frozen patch without blockers; no self-security approval. Existing unrelated PHPUnit doc-comment deprecation remains unsuppressed.

Execution used existing PHP 8.3.31 / PHPUnit 11.5.50, locked vendor 168/168 packages,
SQLite memory, synthetic configuration, faked HTTP/notifications and disabled
container networking. No production database, wallet, bank transfer or merchant
credential was used. Existing Graphify queries provided orientation; the worktree
has no graph and none was installed or rebuilt. No frontend validation is claimed
for a source change that contains no frontend delta. Remote CI is a separate gate.

## Recovery after an approved deployment

Use the existing normal verifier/scheduler or scoped admin Verify now action.
A successful fresh read resolves a SEPA-only incident when every monitored crypto
fingerprint still matches. If a real crypto difference exists, investigation
remains required. Do not accept a new baseline merely to dismiss this alert.
Historical notifications/audits remain historical records. This change does not
confirm bank payments or verify bank beneficiary settings.

No merge, deployment, production verification command, rebaseline or manual paid
operation was performed by this repair. No companion package rebuild is needed.

## Separate bank-mail incident

The origin-review log narrows investigation to missing bank-origin proof. In
current source, parsed-only Mailgun intake deliberately sets the verified-domain
marker to null. Successful original-MIME verification sets and checks it before
parsing, while invalid MIME stops earlier with a different review reason.
The likely route/configuration mismatch still needs the active Mailgun route and
actual deployed revision; the provided excerpt alone cannot prove that setting.

Use separate store original-MIME and company parsed routes as described in the
[ingress runbook](SEPA_CONFIRMATION_ISOLATION_RUNBOOK.md#trusted-bank-mail-ingress).
Mailgun's [HTTP forwarding contract](https://documentation.mailgun.com/docs/mailgun/user-manual/receive-forward-store/receive-http)
documents that a destination ending in `mime` supplies `body-mime`.
Do not change a shared catch-all blindly. Intake HTTP 200 does not mean settled and is
not automatically retried; fixing a route does not recover an already consumed
parsed delivery. Preserve original MIME for any separately authorized recovery.

No actual current bank message, receipt event or deployed route is available.
The strict historical parser's interoperability remains unqualified. If original
delivery cannot be recovered, inspect the bank receipt privately through existing
authorized review; do not make another real transfer just for debugging.

Independent reviewers also found existing diagnostic gaps: zero parsed store
credits return without audit, and invalid MIME reasons collapse to one generic
code. They are documented follow-up candidates; no observability or parser
implementation is bundled into this wallet repair. Never bypass origin checks,
set a verified marker manually, or mark paid from a scanned QR alone.
