# Business expenses (Náklady)

MVP 0.1 for recording supplier costs without line items or supplier master data.

## Data model

Table `business_expenses` (per company):

| Field | Purpose |
|-------|---------|
| `internal_number` | Satflux sequence (`expense`, default format `EXPYYYYNNNN`) |
| `external_number` | Supplier invoice / receipt number |
| `title` | Short label |
| `variable_symbol`, `constant_symbol`, `specific_symbol` | Payment symbols (SK bank) |
| `issue_date`, `delivery_date`, `due_date` | Dates |
| `total`, `currency` | Amount |
| `internal_note` | Private note |
| `status` | `recorded`, `paid`, `cancelled` |
| Attachments | One or more PDF/image/XML files in `business_expense_attachments` (stored under `companies/{id}/expenses/{id}/`). Legacy columns on `business_expenses` mirror the first file for backward compatibility. |

## API (auth + `business_invoicing` + company ownership)

- `GET/POST /api/invoicing/companies/{company}/expenses`
- `GET/PATCH/DELETE .../expenses/{expense}`
- `POST .../duplicate` - copies title, symbols, currency, note; new internal number; today dates; `total` = 0; no attachment
- `POST .../mark-paid`, `POST .../unmark-paid`
- `POST .../attachment` - add file (does not replace existing)
- `GET .../attachment` - download first attachment
- `GET/DELETE .../attachments/{attachment}` - download or remove a specific file
- `GET .../history` - audit log

Query params: `filter` (`paid`|`unpaid`|`overdue`), `year`, `issue_from`, `issue_to`.

## UI

Routes under `/invoicing/companies/:companyId/expenses` - list, new, show, edit.

Workflow: duplicate recurring expense, fill total and external number, upload attachment, mark paid.

**Delete:** UI cancel sets `status = cancelled` and `cancelled_at`. The expense disappears from the list; attachments stay until the retention job hard-deletes the row and files (see [DATA_RETENTION.md](DATA_RETENTION.md), `DATA_RETENTION_CANCELLED_EXPENSES_DAYS`, default 90 days).

## ISDOC import (0.2, SuperFaktúra-style)

**Dependency:** embedded ISDOC in PDF requires `smalot/pdfparser` (declared in root `composer.json`). After deploy run `composer install` so PDF detection works; without it uploads succeed but `has_isdoc` is always false.

1. User uploads attachment (PDF / `.isdoc` / XML) on new or edit expense form (or detail).
2. `POST .../expenses/detect-isdoc` - checks for readable ISDOC (does not consume quota).
3. If found, UI asks whether to extract (modal).
4. On confirm, `POST .../expenses/extract` - fills draft fields; counts against free allowance.

**Quota:** `INVOICING_EXPENSE_ISDOC_FREE_LIMIT` (default **20**) per user. Enterprise: **unlimited** (`expense_isdoc_extract_unlimited`). After free tier: **purchased credits** from packs.

**Packs (EUR incl. VAT, SuperFaktúra-aligned)** in `config/invoicing.php`:

| Doklady | Cena |
|--------|------|
| 25 | 11,07 € |
| 50 | 18,45 € |
| 100 | 28,29 € |
| 500 | 92,25 € |

**Payment:** one-time **BTCPay invoice** on `SUBSCRIPTION_STORE_ID` (`POST .../expenses/isdoc-packs/purchase`). Webhook metadata `purpose=expense_isdoc_pack` credits balance on `InvoiceSettled`.

- `GET .../expenses/isdoc-extract-quota` - free + purchased + packs list
- `POST .../expenses/isdoc-packs/purchase` - `{ credits: 25|50|100|500 }` → `checkoutLink`

Parser: [`BusinessExpenseIsdocImportService`](../app/Services/Invoicing/BusinessExpenseIsdocImportService.php). Quota: [`BusinessExpenseIsdocQuotaService`](../app/Services/Invoicing/BusinessExpenseIsdocQuotaService.php). Packs: [`BusinessExpenseIsdocPackService`](../app/Services/Invoicing/BusinessExpenseIsdocPackService.php).

## Later

- Bulk extract on list
- Bank debit matching (0.3)
- Unified accounting export
