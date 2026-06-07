# Bank payment matching

Match incoming bank transfers to business invoices and update document status to **paid**.

## Channels

| Channel | Status | Notes |
|---------|--------|-------|
| **CSV / CAMT.053 import** | Implemented | UI: Invoicing → Bank payments → Import |
| **Manual pairing** | Implemented | Unmatched list → Assign to invoice |
| **B-mail (bank email)** | Optional | `BANK_INBOUND_ENABLED` + webhook; TB/SLSP parsers |
| **Bank API (Finbricks-style)** | Not implemented | Future Enterprise option |

## Matching rules

1. Only **credit** (incoming) transactions are auto-matched.
2. **Variable symbol** on the bank line must equal the invoice `variable_symbol` (or digits-only invoice number).
3. **Amount** must match amount due within `BANK_MATCH_AMOUNT_TOLERANCE` (default 0.01).
4. **Currency** must match the document currency.

Partial payments keep status `issued` and set `amount_paid` below total until fully paid.

## API (Pro+, company-scoped)

- `GET /api/invoicing/companies/{company}/bank-transactions?match_status=unmatched`
- `POST /api/invoicing/companies/{company}/bank-transactions/import` (multipart `file`, optional `format`: `csv`|`camt053`)
- `POST .../bank-transactions/{id}/match` body: `business_document_id`
- `POST .../bank-transactions/batches/{batch}/auto-match`
- `GET .../bank-transactions/inbound-email` - b-mail address for the company

## B-mail setup (phase 2)

1. Enable `BANK_INBOUND_ENABLED=true` and set `BANK_INBOUND_WEBHOOK_SECRET`.
2. Point Postmark/Mailgun inbound to `POST /api/webhooks/bank-inbound` with JSON: `to`, `from`, `subject`, `body`, optional `headers`.
3. Merchant copies address from UI (`pay+{company_uuid}@payments.satflux.io` by default).
4. Configure the bank to send **movement notifications** directly to that address (not forwarded).

Tatra banka: notification type „obrat na úcte“, **bez šifrovania** if the parser must read VS from the body.

## Data retention

Uploaded statement files under `storage/app/private/bank-imports/` are deleted after `BANK_IMPORT_FILE_RETENTION_DAYS` (default 30) by `data:retention-run`.

## Related

- [BUSINESS_INVOICING.md](BUSINESS_INVOICING.md) - invoices, VS, mark paid
- [DATA_RETENTION.md](DATA_RETENTION.md) - global retention job
