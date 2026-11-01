> Final follow-up review evidence. Redacted private fixture/runner paths are
> not committed runnable tests; use the public commands in README.md.

# PR414 compact-date follow-up QA

Reviewer: `/root/wallet_bmail_qa`, 2026-11-01.
Reviewed reported head: `12e00fa4ff97fc8be2e01de6061ef4c13dbedf07`.
Read-only application scope; private fixtures/report only. No Git/source/database production writes or network bank/provider calls.

## Verified major finding and recommendation

The previous optional `!d.m.Y` correction changed `booked_at`, an existing `BankTransactionDedupe` input, to midnight for every compact credit on the same date. Counterparty name is not part of that hash. Two different payer notifications with equal amount, currency, direction, VS/reference and absent transaction ID can therefore collide and silently skip the second import. This is a functional data-loss regression in the optional date change, independent of the one-route adapter.

The earlier QA suite proved stable retries across clocks but did not test distinct credits losing discrimination. Its earlier acceptance must not be read as disproving this subsequently verified finding.

Recommend the bounded reversal agreed by main/security: restore the compact parser and its two optional unit additions to base `c90caa40` bytes; retain original dedup/import/history policy. Exercise stable native/MIME retries with a classic message that actually supplies bank booking time, advancing the processing clock. Do not freeze the clock to claim compact idempotence, invent bank IDs, change dedup policy or migrate history within this adapter repair.

## Independent exact-snapshot proof

Private `NormalizedCompactDateParserQa.php` is extracted from `git show 12e00fa4:app/Services/Invoicing/BankImport/TatraBankEmailParser.php`; `LegacyCompactDateParserQa.php` from `c90caa40`. Only class declarations are renamed to load both exact parser implementations side by side. Production code is not edited. `CompactDateCollisionProofTest.php` uses the real unchanged `BankTransactionDedupe` and `BankStatementImportService::persistRows` on synthetic SQLite memory.

Two inputs have different Payer Alpha/Beta counterparties, the same date/amount/VS/reference, no bank-issued transaction ID, and processing clocks 12:00:01 / 12:00:05. A third input replays the first notification at 12:00:09.

| Parser snapshot | Distinct payer keys | Distinct notifications imported/skipped | Later identical replay imported |
| --- | --- | --- | --- |
| 12e00fa4 normalized midnight | Equal | 1 imported, 1 skipped |0 |
| c90caa40 prior processing-time behavior | Different | 2 imported, 0 skipped |1 |

Exact command:

```sh
sh <private-bank-single-route-tests>/run-php.sh php vendor/bin/phpunit --do-not-cache-result <private-bank-single-route-qa>/CompactDateCollisionProofTest.php
```

**PASS: 2 tests / 16 assertions, exit 0, 1.542 s.** This is a proof of the collision and the old retry limitation, not acceptance of the normalized implementation. The runner retains network-none, synthetic environment, SQLite memory and readonly existing dependencies from the verified main-prepared environment.

## Confirmed test changes for revised scope

Main confirmed the reversal before QA updated its private seven-case suite. The old seventh normalization-success case is replaced with an explicit two-distinct-credit non-loss case and an assertion of the retained clock-dependent compact replay limitation. The earlier mixed-company stable retry case now uses an actual classic bank booking timestamp; its passing outcome will not be represented as compact date-only idempotence. No existing failure was suppressed by clock freezing.

## Final revised-source QA

Main/backend explicitly froze 10 owned files in `FOLLOWUP_SHA256SUMS`; QA copied the identical manifest to `<private-bank-single-route-qa>/REVIEWED_FOLLOWUP_SHA256SUMS`. Reviewed revision is `12e00fa4` plus this frozen follow-up delta, not the uncorrected head alone. All 10 hashes pass before and after execution.

The compact parser and its unit test are byte-identical to base `c90caa40`; `git diff --exit-code` confirms their reversal. Strict Store CLI/verifier and nested package/lock remain byte-identical to that base. All Company Node implementation/test hashes remain unchanged from the earlier independently executed 42-case passing Node suite; it was not rerun for this PHP/test-only change and its provenance remains explicit.

QA re-inspected the isolated runner and actual resolved flags before execution: `testing`, SQLite `:memory:`, array cache/mail/session, sync queue, null log, no cached configuration, network-none container with synthetic environment and readonly existing dependencies. No production target or merchant data was used.

Exact final command:

```sh
sh <private-bank-single-route-tests>/run-php.sh php vendor/bin/phpunit --do-not-cache-result \
  <private-bank-single-route-qa>/SingleRouteIndependentTest.php \
  tests/Feature/BankCompanyMimeImportTest.php \
  tests/Feature/BankMimeConfirmationTest.php \
  tests/Feature/BankInboundEmailTest.php \
  tests/Feature/SepaBmailTest.php \
  tests/Unit/TatraBankEmailParserTest.php \
  tests/Unit/SepaBankEvidenceTest.php
```

**PASS: 162 tests / 677 assertions, exit 0, 48.028 s, 72.50 MB.** This contains 155 repository cases plus 7 updated private cases. No test failure, skip or clock-freezing workaround was suppressed in this final run. The separate exact-snapshot collision proof above passes 2/16 and is not added as unique current-source coverage.

Validated final behavior includes raw Company Tatra/SLSP imports, HTML/legacy charset/multipart compatibility, BCC tenant authority, encrypted privacy and both owner-race cases; classic explicit bank-time dedup across advanced clocks; two distinct compact credits retained under the unchanged legacy policy; and explicit re-import of a compact retry at another processing second. New repository cases also prove 10 invalid optional sender variants are omitted for Company MIME-From fallback and do not veto valid Store DKIM proof, while a usable unsupported envelope sender retains legacy precedence. No Store proof rule is relaxed.

`git diff --check`: PASS. All 10 frozen hashes: PASS after tests. Base-preserved parser/unit/strict runtime/package comparisons: PASS. Author reports targeted 14/116, full affected 155/623, Pint 3 files and unchanged-baseline PHPStan pass; these static/author counts are attributed and not claimed as QA reruns.

## Acceptance and remaining limitations

**No blocking QA finding remains in the 10 frozen author files. Accepted for source integration at `REVIEWED_FOLLOWUP_SHA256SUMS`.** The major normalization collision is addressed by restoring base behavior, not by approving a new event identity/dedup policy.

The former 151/583 acceptance and 12-file manifest are historical. The earlier optional normalized-date recommendation was incomplete; the new proof and reversal supersede it. Private reviewer fixtures remain temporary evidence, not additional committed tests.

Retained limits: compact date-only or missing-date notifications depend on processing time and can be re-imported on retry; events whose existing dedup fields/time coincide can still collide. There is no new bank event ID, historical rewrite, migration or claim of cross-historical idempotence. Company parsing remains bounded legacy import/matching, not authenticated bank credit proof. Actual current EML, deployed revisions/workers, active route and real payment settlement remain unverified. Existing Node 24 local results do not claim exact Node 22 CI qualification; fresh published-head CI remains main's gate. Coordinated worker-first rollout and rollback are still required before any route change.

QA made no application/repository/Git writes. Only private fixtures/snapshots/reports under the assigned temporary directory were changed.

Final private fixture SHA-256: `98a9f2f8de3f2da6a6796d9e07fe6ea8761403a4410f2a11d6bdb589b0e130a2`; comparison fixture SHA-256: `e16ac2caa5609fc0a26e2c1ee16946644eb609b9dcdce5136238e62710c63b91`.
