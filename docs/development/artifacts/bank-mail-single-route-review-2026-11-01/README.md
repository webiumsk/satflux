# Single-route review follow-up - 2026-11-01

Base: `c90caa40`. Initial candidate: `12e00fa4`. PR414 remains a draft until
the revised source passes its separate publication and exact-head CI gates.
All three CodeRabbit findings were verified against source and addressed.

| Finding | Final disposition |
| --- | --- |
| Optional sender vetoes otherwise valid MIME | Drop unusable optional metadata at intake. Company falls back to MIME From; usable envelope sender retains precedence. Real strict Store proof remains required. |
| Midnight normalization suppresses distinct same-day compact credits | Withdraw optional parser/date-unit change. Parser, its unit tests, dedupe and importer are byte-identical to base. Use classic explicit bank timestamp for transport retry qualification; prove compact non-loss independently. |
| Unqualified node version may inspect wrong binary | Check effective Laravel `bank_inbound.node_binary`, including override/cache, and execute that selected binary with `--version`. |

Company compact date-only and undated notifications retain the inherited
processing-time/retry ambiguity. This adapter does not solve that financial event
identity problem, invent bank transaction IDs, reconcile history or silently
collapse distinct same-day compact notifications. A broader repair needs its own
evidence and design. This supersedes the initial candidate's normalization.

| Gate | Result and attribution |
| --- | --- |
| Author | [Follow-up](backend.md): 155 repository PHP cases / 623 assertions, targeted 14 / 116, scoped Pint and full unchanged-baseline PHPStan pass |
| Independent QA | [Review](qa.md), `/root/wallet_bmail_qa`: 155 repository plus 7 private cases pass 162 / 677; separate initial-collision/reversal proof 2 / 16 |
| Independent security | [Review](security.md), `/root/wallet_bmail_security`: 40 repository plus 10 private cases pass 50 / 334; accepted without blockers |
| Main | API export/check, API-schema Pint, OpenAPI validators, locale parity and exact hash/diff checks pass |

The [11-file source manifest](source.sha256) records the 10 author-owned files
plus main API schema; [8 preserved files](preserved.sha256) match the base.
The 42 Node cases and 12 private security probes previously passed on byte-identical
runtime files; they were not rerun locally for this PHP-only follow-up. Initial
remote CI used Node22 and passed 2,333 PHP, 1,094 frontend and 20 Playwright cases
(3 existing skips). These initial results do not qualify the revised published
head. Fresh exact-head CI must pass separately.

Private reviewer fixtures are evidence, not additional committed tests. Author
and reviewers used synthetic isolated configuration, SQLite memory, array caches,
existing read-only locked dependencies and disabled network. No production data,
bank message, wallet, credential or transfer was accessed by agents.

Public reproduction commands in an explicitly disposable environment:

```sh
sha256sum -c docs/development/artifacts/bank-mail-single-route-review-2026-11-01/source.sha256
sha256sum -c docs/development/artifacts/bank-mail-single-route-review-2026-11-01/preserved.sha256
php artisan test tests/Feature/BankCompanyMimeImportTest.php tests/Feature/BankMimeConfirmationTest.php tests/Feature/BankInboundEmailTest.php tests/Feature/SepaBmailTest.php tests/Unit/TatraBankEmailParserTest.php tests/Unit/SepaBankEvidenceTest.php
npm test --prefix runtime/bank-mail-verifier
php vendor/bin/phpstan analyse --memory-limit=1G --no-progress
php artisan api-docs:export --check
node --test scripts/api-docs/validate.test.mjs
node scripts/api-docs/validate.mjs
npm run test:locale-parity
git diff --check
```

No merge, deployment or Mailgun route edit occurred. Worker-first rollout and
rollback remain as specified in the [runbook](../../SEPA_CONFIRMATION_ISOLATION_RUNBOOK.md).
Original bank MIME retrieval failed404; current bank signing/layout and the old
payment remain unverified. Source repair does not imply settlement.
