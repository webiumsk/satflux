# Bank MIME diagnostic metadata - backend delivery

Repository: Satflux core, branch `fix/bank-mail-single-route`, base HEAD `cc3967abfb35f826af91858dff771fa999087a2c`.
Author/reviewer role: backend implementation author; independent security approval belongs to the assigned security reviewer.

## Approved contract and completed scope

Metadata-only diagnostics on Store verification rejection. Existing `reason=unverified_bank_mime` remains compatible. A new `verifier_reason` contains only one of the 15 permanent rejection strings already emitted by the existing Node verifier/CLI; missing, unknown, non-string, newline-bearing, oversized or otherwise non-exact values map to `unknown`. Pre-process invalid UTF-8 retains the original permanent rejection behavior and adds the trusted fixed value `invalid_utf8`.

No verification acceptance, retry classification, Store/Company owner selection, company importing, Node runtime, parsing, deduplication, payment/provider request, route or configuration logic changed. MIME size and verified-content binding review behavior remain unchanged.

Owned writes only:

- `app/Services/Invoicing/BankMimeVerificationService.php`: fixed allowlist at lines 17-23, local UTF-8 diagnostic at 118-119, invalid-status diagnostic at 132-133, strict mapping/audit metadata at 194-206.
- `tests/Feature/BankMimeConfirmationTest.php`: all supported permanent reasons, malformed/unknown reasons, privacy with supported code and arbitrary extra fields, newline and >500KB values, no provider calls, no financial rows, missing reason, local UTF-8, strict malformed statuses with unchanged ciphertext and audit count, actual crypto tamper/recipient diagnostics.

Main concurrently owns the runbook and runtime README changes. Those files were neither edited nor reverted by this agent.

Frozen SHA256:

```text
6c8900b1367ed23072426cd0096ad575c0c04a6db311852f3660fcd38d633087  app/Services/Invoicing/BankMimeVerificationService.php
7624d9c8185b92e070985b5eef73c7c42c04a7b6fefd6a334202796def41e9f4  tests/Feature/BankMimeConfirmationTest.php
```

## Environment evidence

Inspected existing `/tmp/bank-single-route-tests/run-php.sh` before execution. It uses the existing `satflux-php:latest` image, Docker `--network none`, SQLite `:memory:`, synthetic application/provider keys, array cache/session/mail, sync queue, null logging, mounted existing dependencies and a synthetic DNS preload for signed MIME fixtures. No production database, wallet, credentials or bank message was used.

Effective Laravel bootstrap probe returned: environment `testing`; configuration cached `false`; connection `sqlite`; database `:memory:`; cache `array`; queue `sync`; session `array`; mail `array`; logging `null`; synthetic BTCPay URL comparison `true`.

Initial default Docker socket access was denied; the normal sandbox escalation approved the same synthetic runner. No approval bypass or external network access occurred.

## Exact checks and results

Commands ran from the assigned worktree using the inspected synthetic runner:

```text
sh /tmp/bank-single-route-tests/run-php.sh php artisan test tests/Feature/BankMimeConfirmationTest.php tests/Feature/BankCompanyMimeImportTest.php
PASS: 74 tests, 442 assertions, 31.05s; no skipped tests reported.

sh /tmp/bank-single-route-tests/run-php.sh vendor/bin/phpstan analyse --no-progress
PASS: existing phpstan.neon.dist, level 5, unchanged existing baseline; no errors.

sh /tmp/bank-single-route-tests/run-php.sh vendor/bin/pint --test app/Services/Invoicing/BankMimeVerificationService.php tests/Feature/BankMimeConfirmationTest.php
PASS: 2 files.

git diff --check
PASS.
```

The first targeted MIME test run returned 39 passed and 8 failed because the new retry test incorrectly asserted that the entire audit table was empty; Store creation already creates an unrelated audit event. The assertion was corrected to compare the existing audit count before/after the retry. All 47 MIME tests and all 27 unchanged neighboring Company tests then passed. The service did not change in response to this test correction.

## Dependencies, assumptions and remaining limits

Graphify tool is unavailable in this session and the worktree graph is absent. Main supplied a prior query with stale/missing MIME edges; targeted known-path reads were used. No graph rebuild or tool install was attempted.

The allowlist follows the exact current runtime permanent rejection codes; future codes safely become `unknown` until separately reviewed. Reasons cannot recover the historical subtype of an existing generic audit record. This change diagnoses future rejection and does not establish current-bank template, encoding or signature interoperability or authorize a payment confirmation.

No implementation blockers remain. Main owns independent QA/security reconciliation, shared state and all Git publication. No commit, push, merge, tag, publication, deployment, route edit or production data access was performed.
