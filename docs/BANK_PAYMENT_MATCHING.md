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

Slovak banks accept at most **50 characters** for the notification email address. satflux generates a short per-company address:

- Default format: `pay{token}@payments.satflux.io` (e.g. `payabc123def456@payments.satflux.io`, 36 chars)
- `{token}` is a 12-character `[a-z0-9]` value stored on the company (`bank_inbound_token`)

### Enable on server

```env
BANK_INBOUND_ENABLED=true
BANK_INBOUND_WEBHOOK_SECRET=your-long-random-secret
BANK_INBOUND_DOMAIN=payments.satflux.io
BANK_INBOUND_ADDRESS_PREFIX=pay
BANK_INBOUND_MAX_ADDRESS_LENGTH=50
```

Then `php artisan optimize:clear` and ensure the queue worker processes the `webhooks` queue.

### Production inbound mail (Mailgun)

1. Add domain `payments.satflux.io` in Mailgun with MX records verified.
2. Create an inbound route: recipient matches `.*@payments.satflux.io`, action **Forward** to `POST https://satflux.io/api/webhooks/bank-inbound`.
3. Set `MAILGUN_WEBHOOK_SIGNING_KEY` from Mailgun domain settings (Webhooks signing key). Mailgun POSTs `recipient`, `sender`, `stripped-text`, `timestamp`, `token`, `signature` - satflux normalizes these automatically.
4. Set `BANK_INBOUND_ENABLED=true` and `BANK_INBOUND_DOMAIN=payments.satflux.io`.
5. Merchant copies address from UI (Invoicing → Bank payments → Import tab).
6. Configure the bank to send **movement notifications** directly to that address (not forwarded).

For manual/curl testing without Mailgun, POST JSON with `X-Bank-Inbound-Secret: {BANK_INBOUND_WEBHOOK_SECRET}` (see local smoke test below).

Tatra banka: notification type „obrat na úcte“, **bez šifrovania** if the parser must read VS from the body.

### Local smoke test (no DNS)

Verify the backend without MX records or a mail provider:

1. Set the `.env` values above.
2. Start services: `docker compose up -d queue`
3. Copy the b-mail address from the UI or API:
   `GET /api/invoicing/companies/{company}/bank-transactions/inbound-email`
4. POST a sample Tatra notification:

```bash
curl -sS -X POST http://localhost:8080/api/webhooks/bank-inbound \
  -H 'Content-Type: application/json' \
  -H 'X-Bank-Inbound-Secret: your-long-random-secret' \
  -d '{
    "to": "payabc123def456@payments.satflux.io",
    "from": "notify@tatrabanka.sk",
    "subject": "Obrat na ucte",
    "body": "Obrat na ucte. Suma: 150,50 EUR. VS: 20260042. Protistrana: Client s.r.o. 01.06.2026"
  }'
```

5. Check Invoicing → Bank payments for the imported transaction and auto-match (when a matching issued invoice exists).

## Data retention

Uploaded statement files under `storage/app/private/bank-imports/` are deleted after `BANK_IMPORT_FILE_RETENTION_DAYS` (default 30) by `data:retention-run`.

## Related

- [BUSINESS_INVOICING.md](BUSINESS_INVOICING.md) - invoices, VS, mark paid
- [DATA_RETENTION.md](DATA_RETENTION.md) - global retention job
