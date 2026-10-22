# Wallet attestation probe monitoring

## Scope and acceptance contract

Fix the automatic wallet-attestation invoice inheriting the BTCPay store's
customer-invoice monitoring duration. Work from Satflux `fe0ace6e` on a separate
`fix/wallet-probe-monitoring` branch. Tickets work remains separate. No production
access, state mutation, configuration changes or deployment is authorized.

- New probes retain their existing 100-sat amount, Lightning-only methods and
  one-minute checkout expiration. Explicitly request one minute of additional
  monitoring, rather than inheriting the store's default (normally one day).
- Probes are real payable invoices, not intended to be paid or published.
  Archival neither cancels their Lightning invoice nor ends monitoring.
- Preserve the entire wallet replacement guard, owner credential scope, email
  confirmation, lock, uncertain-update journal and Cashu safety checks.
- Never exempt an invoice using metadata, amount, archive status or store age.
  Existing probes retain their original deadline. Processing and unknown states
  still block regardless of the new probe policy.
- Test a stateful fake that retains archived probes in invoice listing, both
  sides of the actual monitoring deadline, legacy probes, spoofed metadata,
  payment processing, malformed responses, failures and pagination.

## Independent work and evidence

| Role | Ownership | Boundary and acceptance |
| --- | --- | --- |
| Main | Service change, regression tests, this contract/runbook and project state | No core/plugin/production changes; source and test integration |
| Backend | Read-only trace of probe creation, invoice listing and pinned host | Exact file evidence; no source writes or publication |
| Security | Independent read-only design and final diff review | No author approval; preserve all payment and tenant guards |
| QA | Independent read-only behavior review and isolated tests | Synthetic database and HTTP only; no Git/source writes |

Source diagnosis: `PayeeAttestationService::canaryPayee()` requests one-minute
expiration but previously omitted `monitoringMinutes`. `WalletConfigIntegrityService`
creates this probe when learning a wallet baseline, including a newly connected
store. `archiveQuietly()` only archives it. `WalletConnectionService::assertWalletCanChange()`
correctly includes archived invoices and uses their actual monitoring deadlines.

Pinned BTCPay 2.4.5 source (`5d0745cae6be5d8210459e38813f317673aa97b8`):
`Data/StoreBlob.cs` defaults monitoring to one day;
`Controllers/UIInvoiceController.cs` sets monitoring expiration to checkout
expiration plus explicit monitoring or the store default. The Greenfield archive
endpoint only toggles archival. This confirms a source-level mismatch, not which
invoice currently blocks the reported production store. Graphify was unavailable;
targeted source inspection was used without rebuilding or installing tools.

## Read-only operator diagnosis

Execute these steps only as the owner of the affected store in the BTCPay UI.
No terminal command, API key, database access or production write is needed.

1. Open that store's **Invoices** page and include **archived invoices** in the
   filter. Purpose: find automatically archived wallet checks that ordinary
   invoice lists hide. Expected: possibly a small invoice whose description is
   `Satflux wallet check (not payable)`; the historic description is not a safety
   guarantee. A matching description is a hypothesis, not trusted provenance.
2. Open invoice details without changing anything. Inspect status, creation,
   checkout expiration and monitoring expiration. Purpose: distinguish a short
   checkout from its longer monitoring window. If the UI omits the monitoring
   timestamp, report that omission; do not export a full invoice or use raw API
   dumps. Expected under the old default: approximately 24 hours plus one minute
   from creation. A different store setting can produce a different deadline.
3. Share only invoice count, statuses and UTC deadlines, and whether the invoice
   appears to be a wallet check. Redact store/invoice IDs, checkout URLs, payment
   requests/BOLT11, addresses, tokens, customer details and other merchant data.
   Do not send complete screenshots containing those values.

Interpretation: a monitored New/Expired/Invalid invoice can explain the hold;
Processing always blocks, even after its deadline. A finished deadline alone
does not prove all other invoices or Cashu assets are clear. If no matching
invoice exists, preserve the failure and investigate the actual invoice response
offline using a separately reviewed redacted summary. Do not disable the guard.

## Recovery, compatibility and rollback

For existing invoices, wait for the actual monitoring deadline and retry only
when payment states are settled/known and no other guard blocks. Archiving,
downloading, deleting a local row or changing invoice metadata is not a release
mechanism. Do not mark invoices paid, shorten global monitoring or alter wallets
to bypass the hold. This repository fix cannot retroactively shorten a deadline.

The explicit checkout option is supported by the inspected 2.4.5 host; no plugin,
core, schema or environment-variable change is required. Revert the service change
to restore inherited monitoring for future probes; already created invoices retain
their original BTCPay deadlines in either direction. Do not delete their evidence.

Before deployment acceptance, validate direct Lightning and LNURL probe expiry
in an isolated representative provider setup. A provider can return BOLT11 with
an expiry beyond checkout; one minute of monitoring is a bounded policy for
unpublished diagnostic probes, not proof of cancellation or guaranteed settlement
safety. No real funds or production wallet are test targets.

## Validation results

Both focused regressions failed before the service change, exposing the omitted
monitoring option and inherited day-long deadline. With the fix, the main isolated
run passes **151 PHP tests / 768 assertions** across `WalletProbeMonitoringTest`,
`PayeeAttestationTest`, `WalletConfigIntegrityTest`, `WalletConnectionTest`,
`Btcpay245WalletCompatibilityTest`, `BtcPayInvoiceListSafetyTest`,
`CashuWalletUpdateSafetyTest` and `Bolt11Test`. Documentation tests pass separately:
**8 tests / 26 assertions**. Changed-file Pint and full-project PHPStan pass.
The test discovery emits an existing PHPUnit doc-comment metadata deprecation in
`RaffleApiTest`; it is unrelated to this change and does not fail the run.

Independent QA passes **24 tests / 150 assertions**, verifies all 167 installed
package versions and source references against the unchanged lockfile, and accepts
the five locale articles. The copied test vendor initially contained one older
Symfony package; it was aligned to the locked reference through an offline Composer
install from its official cached archive before final checks. No dependency change
is part of the repository diff. Tests use a network-disabled disposable PHP container,
SQLite in memory, synthetic keys and stubbed HTTP, with no cached app configuration.

Independent security reports no source blockers in the bounded future-probe policy.
Reviewed service/test source digest:
`4505e84b69cb37c4385f128f25b652822b84b5f8415aadc966473c4dae2dc767`.
It is SHA-256 of lexically sorted lines `relative-path + space + file-SHA256 + LF`
for `app/Services/WalletSecurity/PayeeAttestationService.php`,
`tests/Feature/PayeeAttestationTest.php` and `tests/Feature/WalletProbeMonitoringTest.php`.

The deadline regression proves read-only guard release after the monitoring window;
it does not execute a complete post-deadline provider wallet replacement. Exact-host
behavior was inspected in source, not exercised against a live BTCPay instance.
Representative provider expiry and end-to-end replacement remain staging gates.
No production behavior has been verified and the reported store has not been
unblocked by this investigation. Publication, merge and deployment are separate
approval steps; no release is performed here.
