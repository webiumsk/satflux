# CashuMelt unknown funding status: read-only operator runbook

## Purpose and restrictions

Collect a minimal, redacted state summary when funding-quote lookup returns HTTP
400. That response is not evidence of non-payment or successful payout. Use this
runbook before asking for live production information. Repository analysis has no
production access and cannot establish the current balance or payment outcome.

Do not pay a test invoice, press Retry, activate checkout methods, call LNURL-pay
callbacks, change mint/address, rotate keys, export proofs, mark paid or delete
rows during diagnosis. Retry and recovery can change state and are separate
operator-approved actions. Re-enable with unchanged settings restores availability;
it does not resolve a funding quote or release wallet-change guards.

## UI evidence

Where: BTCPay's Plugins screen and the affected store's CashuMelt settings/payment
history. Purpose: record installed plugin version, UTC observation time, configured
payment-method names, and only enabled/disabled flags and the displayed recovery
phase/status. Expected: possibly an unresolved funding-verification row with HTTP
400 and a settings save refused by the outstanding-assets guard in 1.4.0.7.

Interpretation: an unsupported-backend banner can describe the old outbound
classifier, not the actual mint-to-Lightning payout outcome. A pending row and a
failed lookup do not prove available funds, a lost payment or a completed payout.
Redact store/invoice/quote IDs, wallet addresses, customer details, payment links,
BOLT11, tokens and private service URLs before sharing screenshots. Do not share
complete settings pages, raw logs or API JSON.

## Existing authorized PostgreSQL session

Where: an operator's already-authorized `psql` session for the BTCPay plugin
database. Do not create accounts, grant privileges or display a connection string
to execute this. If such access is unavailable, report that limitation and retain
UI-only evidence. The operator supplies the store identifier and displayed amount
locally through prompts; do not send those identifiers back. Use a fresh session
with no existing transaction or unrelated work to avoid rolling back that work.

Purpose: inspect stored state and presence of recovery evidence without revealing
its content. The transaction is explicitly read-only, has short timeouts, emits
at most 20 matching rows, and ends with ROLLBACK. It does not contact the mint.

```sql
\set ON_ERROR_STOP on
\prompt 'Store ID (keep local): ' cashu_store_id
\prompt 'Displayed amount in sat (keep local): ' cashu_amount
BEGIN TRANSACTION READ ONLY;
SET LOCAL statement_timeout = '5s';
SET LOCAL lock_timeout = '1s';
SHOW transaction_read_only;

SELECT "Enabled", "Unit",
       (NULLIF("MintUrl", '') IS NOT NULL) AS has_mint,
       (NULLIF("LightningAddress", '') IS NOT NULL) AS has_payout_address
FROM "BTCPayServer.Plugins.CashuMelt"."CashuMeltStoreSettings"
WHERE "StoreId" = :'cashu_store_id';

SELECT "AmountSats", "Unit", "State", "SettlementState",
       "CreatedAt", "PaidAt", "SettledAt", "QuoteExpiresAt", "LastCheckedAt",
       "RetryCount", "NeedsManualReview",
       ("SettingsSnapshotJson" IS NOT NULL) AS has_snapshot,
       ("MintQuoteId" IS NOT NULL) AS has_mint_quote,
       ("MintedProofsJson" IS NOT NULL) AS has_proofs,
       ("MintIssuanceJson" IS NOT NULL) AS has_issuance,
       ("BlankOutputsJson" IS NOT NULL) AS has_change_outputs,
       ("MeltQuoteId" IS NOT NULL) AS has_payout_quote,
       ("ForwardBolt11" IS NOT NULL) AS has_forward_invoice
FROM "BTCPayServer.Plugins.CashuMelt"."CashuMeltPaymentRequests"
WHERE "StoreId" = :'cashu_store_id'
  AND "AmountSats" = :'cashu_amount'::bigint
ORDER BY "CreatedAt" DESC
LIMIT 20;

ROLLBACK;
```

Expected output: `transaction_read_only` is `on`, followed by zero/one settings row
and a small set of state/timestamp/boolean
rows. No rows can mean a different database, store, amount or no matching record;
it is not proof of no payment. Missing schema/column means stop and report the
schema/version mismatch; do not run migrations as diagnosis. If a query errors,
issue only `ROLLBACK;` before leaving the session. Never substitute `SELECT *`.

Interpretation:

- `has_snapshot` and `has_mint_quote` identify whether original evidence exists;
  they do not prove that evidence is valid or that funds can be recovered.
- `has_proofs`, issuance, change outputs or a payout quote require preserving
  database and Data Protection backups before separately approved recovery.
- Missing `PaidAt`/`SettledAt`, an expired timestamp or absent proofs does not
  establish that funding never happened. Pending acceptance and mint state can
  be unknown. HTTP 400 alone cannot release a wallet hold.
- A successful native save of unchanged enabled state in the new version should
  not alter payment rows or original snapshots. Re-enabling also does not make
  Cashu visible in new checkout when any Lightning wallet remains configured.

Sensitive output: amounts and exact timestamps are commercial data. Share only
matching-row count, states, rounded timestamps, retry/manual-review flags and
presence booleans needed for diagnosis. Do not share store identifiers or prompt
input transcripts. Never select/decrypt/share snapshot JSON, proofs, quote IDs,
BOLT11, raw settlement errors/references, API keys or Data Protection keys.

## Minimum next evidence

Version plus the above redacted stored-state summary distinguishes recorded
recovery material from the absence of recorded material. Absence does not prove
that a quote was unissued or unpaid. Determining whether payment reached
the mint or receiver can still require original mint/payer/receiver evidence from
their actual authorized operator. No one should send payment secrets to Satflux
support. If this evidence is unavailable, record the outcome as unknown and retain
the safety hold. This runbook is not an instruction to retry, cancel or transfer.
