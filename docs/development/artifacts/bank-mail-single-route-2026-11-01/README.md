# Single-route bank-mail evidence - 2026-11-01

Repository: `webiumsk/satflux`. Base: `c90caa40e048cdf2b740e0195fd7198bd5209fe7`.
Branch: `fix/bank-mail-single-route`. This records reviewed source readiness, not
live bank interoperability, actual payment, merge, route changes or deployment.

The [design](../../BANK_MAIL_SINGLE_ROUTE.md) defines the one-route compatibility
repair, separate company/store policies, ownership and recovery boundaries.
The existing strict Store verifier and its dependency pins are byte-identical.

| Gate | Evidence | Result |
| --- | --- | --- |
| Author implementation | [Backend handoff](backend.md) | 144 committed PHP cases / 521 assertions, 42 Node cases, scoped Pint and full existing-baseline PHPStan pass |
| Independent QA | [QA review](qa.md), reviewer `/root/wallet_bmail_qa` | Same 144 committed cases plus 7 private controls: 151 PHP / 583 assertions; 42 Node, zero skips; accepted |
| Independent security | [Security review](security.md), reviewer `/root/wallet_bmail_security` | 10 private PHP / 61 assertions, 42 Node and 12 private probes pass; accepted without blockers |
| Main integration | Generated API export/check, three OpenAPI validators, validator unit, locale parity, scoped API-schema Pint, diff/hash checks | Pass; no ratchet or dependency change |

Private reviewer fixtures are one-off evidence and are not additional committed
tests. Their counts are not summed as unique cases with repeated author suites.
The archived reports preserve earlier failed attempts and corrections. Author
and reviewers used synthetic fixtures with existing locked dependencies, isolated
PHP 8.3.31, SQLite memory, array caches/mail/session and no container network. No
production credentials, database, bank message, wallet or real transfer was used.
Local nested runtime tests used existing Node24.22.1 mounted into the disposable
PHP image; that old image's default Node20.20.2 is not the declared runtime and was
not used for MIME qualification. The source Dockerfile and CI target Node22;
exact-head CI must establish that runtime separately. No image was rebuilt or
runtime installed by this task.

The [source manifest](source.sha256) pins the 12 author-owned files and main-owned
API schema. The [preserved manifest](preserved.sha256) records unchanged strict
CLI/verifier and package files. Reproduce public checks in a disposable environment:

```sh
sha256sum -c docs/development/artifacts/bank-mail-single-route-2026-11-01/source.sha256
sha256sum -c docs/development/artifacts/bank-mail-single-route-2026-11-01/preserved.sha256
php artisan test tests/Feature/BankCompanyMimeImportTest.php tests/Feature/BankMimeConfirmationTest.php tests/Feature/BankInboundEmailTest.php tests/Feature/SepaBmailTest.php tests/Unit/TatraBankEmailParserTest.php tests/Unit/SepaBankEvidenceTest.php
npm test --prefix runtime/bank-mail-verifier
php vendor/bin/phpstan analyse --memory-limit=1G --no-progress
php artisan api-docs:export --check
node --test scripts/api-docs/validate.test.mjs
node scripts/api-docs/validate.mjs
npm run test:locale-parity
git diff --check
```

The original event used parsed intake and correlated with bank-origin rejection.
Original MIME retrieval returned404 on both endpoints. No actual current Tatra
format/signature qualification or historical payment confirmation follows from
these tests. Company decoding is bounded legacy importing, not strict bank proof.
Its inherited nested MIME behavior and compact date/history/dedup limitations are
documented in the design and runbook. Only an approved worker-first rollout can
permit changing the existing EU destination to `.mime`; no such action occurred.

Exact published-head remote CI and CodeRabbit remain separate checks after source
publication. No implementation author approved their own independent review.
