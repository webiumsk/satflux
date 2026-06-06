# Business invoicing

User-scoped module for company profiles, customer contacts, and accounting invoices (separate from BTCPay payment invoices in Stores).

## Plan access

- Feature flag: `business_invoicing` on Pro and Enterprise (`SubscriptionPlanSeeder`).
- API: all routes under `/api/invoicing/*` use `EnsurePlanAllowsBusinessInvoicing`.
- Frontend: `plan_features.business_invoicing` on `/api/user`.
- **Company limits:** Pro = 2 companies (`max_companies` on plan). Enterprise = unlimited (`null`). Beta override: `INVOICING_BETA_PRO_MAX_COMPANIES=5` in `.env` (effective limit for Pro testers). `POST /api/invoicing/companies` uses `EnsureCompanyLimit`.

### When a client stops paying

1. **Active subscription** - full Pro/Enterprise access including invoicing.
2. **Grace period** (30 days after `expires_at`, status `active` or `grace`; see `config/pricing.php` `grace_days`) - access continues; user can still view and edit invoicing data.
3. **After grace** (`expired`) - `currentSubscription()` is empty; plan falls back to Free / role. `business_invoicing` is off: all `/api/invoicing/*` and SPA invoicing routes return **403**. **Data is not deleted** (companies, contacts, issued documents remain in DB).
4. **Customer-facing** - public Bitcoin pay links (`GET /pay/i/{payment_token}`) keep working for already-issued invoices; webhooks can still mark documents paid.
5. **Re-subscribe** - access restored immediately; no need to recreate companies.

Existing companies above the Free limit (0) are **not** removed; the user simply cannot open the module until Pro is active again. Creating new companies is blocked when at plan limit (Pro: 2, or beta: 5).

## Subscription billing company

Satflux can auto-issue **paid** invoices for annual plan payments (Pro / Enterprise) into one dedicated company profile:

- Set `SUBSCRIPTION_BILLING_COMPANY_ID` in `.env` to that company's local UUID.
- On `InvoiceSettled` for the subscription BTCPay store, `SubscriptionBillingInvoiceService` upserts a contact for the subscriber, issues an invoice, marks it paid, and emails the PDF.
- EUR line total uses the payment-time rate when the BTCPay invoice is in sats; fiat subscription invoices use the invoice amount directly.
- Each BTCPay payment invoice id maps to at most one business document (renewals create new invoices).

See [SUBSCRIPTION_IMPLEMENTATION.md](SUBSCRIPTION_IMPLEMENTATION.md) for webhook flow and operational setup.

## Bank payment matching

Import bank statements (CSV/CAMT.053), pair credits to issued invoices by variable symbol, and mark documents paid. Optional b-mail inbound for SK banks. See [BANK_PAYMENT_MATCHING.md](BANK_PAYMENT_MATCHING.md).

## Business expenses (náklady)

Record supplier costs (document number, total, dates, attachment). Duplicate workflow for recurring costs. See [BUSINESS_EXPENSES.md](BUSINESS_EXPENSES.md).

## Data retention

Buyer PII on issued documents is frozen in `buyer_snapshot` at issue. Contact delete anonymizes the row when issued documents exist. Scheduled cleanup: see [DATA_RETENTION.md](DATA_RETENTION.md) (`data:retention-run`, `DATA_RETENTION_ENABLED`).

## Data model

- `companies` - merchant legal profile (jurisdiction drives validation and PDF template).
- `stores.company_id` - optional link for BTCPay crypto payments on invoices.
- `company_contacts` - customers / recipients.
- `business_documents` + `business_document_lines` - local accounting documents (`buyer_snapshot` JSON at issue).
- `business_recurring_profiles` + `business_recurring_profile_lines` - recurring invoice schedules (templates with placeholders).
- `company_document_sequences` - configurable number series per document type (name, format pattern, reset period, default flag). Formats use tokens `R`/`M`/`C` (year/month/counter) plus literal prefixes (e.g. `RRRRCCCC`, `DODRRCCC`). Seeded on company create; issuing uses the default series for the document type.

## Jurisdictions

| Code | Bank QR | Notes |
|------|---------|-------|
| `eu_sk`, `eu_cz`, `eu_other` | Pay by Square payload in PDF | Variable symbol, IBAN |
| `us` | Text bank details only | EIN-style fields |

## Payments on issued invoices

1. **Bank (EU):** `PayBySquareGenerator` builds PAY by square string (LZMA via `xz`, package `trinetus/pay-by-square-generator`); QR embedded in PDF (EU template). Server needs `/usr/bin/xz` (`xz-utils` in the PHP Docker image).
2. **Bitcoin (lazy checkout):** when `payment_btc_enabled`, issue assigns a stable `payment_token` (64 chars). PDF QR points to `GET /pay/i/{payment_token}` (public, throttled). That page reuses an open BTCPay checkout when possible; after payment it syncs status from BTCPay API (no new LN invoice). Metadata on BTCPay invoices: `businessDocumentId`, `companyId`, `documentNumber`. Webhooks `InvoiceSettled` / `invoice.settled` and `InvoiceProcessing` / `invoice.processing` mark the document paid; if webhook metadata is missing, satflux loads the invoice from Greenfield API. Opening the pay link or the invoice detail also runs that sync. Requires store owner BTCPay API key and queue worker on `webhooks`.

## API (authenticated, Pro+)

- `GET/POST /api/invoicing/companies`
- `GET/POST/PATCH/DELETE /api/invoicing/companies/{company}/number-series` - číselníky (CRUD + `next_number_preview`)
- `PATCH /api/invoicing/companies/{company}/app-settings`, `email-settings` - application settings tabs
- `PATCH /api/invoicing/companies/{company}/stores` - assign `store_ids`
- Contacts and documents CRUD under `/companies/{company}/...`
- Draft and **issued** invoices can be updated; paid/cancelled are locked for editing until payment is removed
- `POST .../documents/{id}/unmark-paid` - paid → issued (enables edit/cancel)
- `POST .../documents/{id}/cancel` - issued or paid → cancelled (clears payment fields)
- `DELETE .../documents/{id}` - draft/cancelled always (if no bank match); issued/paid only when newest document for the company and no linked child documents
- `POST .../documents/{id}/issue` - assign number and `payment_token` when BTC enabled (no BTCPay call at issue)
- `POST .../documents/{id}/create-final-invoice` - draft invoice from paid proforma (linked via `source_document_id`)
- `GET/POST/PATCH/DELETE /api/invoicing/companies/{company}/recurring-profiles` - pravidelné faktúry (filters: `all`|`active`|`inactive`)
- `POST .../recurring-profiles/from-document/{id}` - create profile from existing invoice
- `POST .../recurring-profiles/{id}/generate` - issue document now (manual run)
- Scheduler: `php artisan invoicing:process-recurring` daily at 06:00
- Issued update: does not pre-create BTCPay; disabling BTC clears `payment_token`
- Public pay: `GET /pay/i/{payment_token}` (web route, no auth)
- `GET /invoicing/companies/{company}/documents/{id}/pdf` - download PDF (web route, session auth; also available under `/api/invoicing/...` for SPA axios)

## Frontend routes

- `/invoicing` - company list
- `/invoicing/companies/new` - create company
- `/invoicing/companies/:id` - dashboard + store linking
- `/invoicing/companies/:id/app` - základné nastavenia, emaily, číselníky (`.../app/series`)
- `/invoicing/companies/:id/contacts`
- `/invoicing/companies/:id/invoices` and `.../invoices/new`, `.../invoices/:documentId`

## Phases (not in MVP)

| Phase | Scope |
|-------|--------|
| 1.1 | Email PDF, manual bank paid (BTC paid sync via webhook implemented) |
| 2 | Credit notes |
| 3 | Proforma, quotes, orders received |
| 4 | Delivery notes (recurring invoices: **implemented** as `recurring-profiles`) |
| 5 | Public invoice link, logo branding, multi-language PDF |

Document types exist in `BusinessDocumentType` enum; MVP UI enables `invoice`, `proforma` (zálohové faktúry), and `quote` (cenové ponuky). Paid proformas can spawn a draft final invoice via `POST .../documents/{id}/create-final-invoice` (`source_document_id` link). Approved quotes can spawn an issued invoice via `POST .../documents/{id}/create-invoice-from-quote`. Quote workflow uses `quote_status` (`pending`, `approved`, `rejected`, `expired`; expiry is also derived when `due_date` is past while still `pending`).
