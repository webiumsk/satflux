# Business expenses (Náklady)

MVP 0.1 for recording supplier costs without line items or supplier master data.

## Data model

Table `business_expenses` (per company):

| Field | Purpose |
|-------|---------|
| `internal_number` | Satflux sequence (`expense`, default format `NRRRRCCCC`) |
| `external_number` | Supplier invoice / receipt number |
| `title` | Short label |
| `variable_symbol`, `constant_symbol`, `specific_symbol` | Payment symbols (SK bank) |
| `issue_date`, `delivery_date`, `due_date` | Dates |
| `total`, `currency` | Amount |
| `internal_note` | Private note |
| `status` | `recorded`, `paid`, `cancelled` |
| Attachment | PDF/image/XML stored under `companies/{id}/expenses/{id}/` |

## API (auth + `business_invoicing` + company ownership)

- `GET/POST /api/invoicing/companies/{company}/expenses`
- `GET/PATCH/DELETE .../expenses/{expense}`
- `POST .../duplicate` - copies title, symbols, currency, note; new internal number; today dates; `total` = 0; no attachment
- `POST .../mark-paid`, `POST .../unmark-paid`
- `POST/GET .../attachment`
- `GET .../history` - audit log

Query params: `filter` (`paid`|`unpaid`|`overdue`), `year`, `issue_from`, `issue_to`.

## UI

Routes under `/invoicing/companies/:companyId/expenses` - list, new, show, edit.

Workflow: duplicate recurring expense, fill total and external number, upload attachment, mark paid.

## Later (accounting export)

ISDOC import, bank debit matching, unified export - see accounting export plan.
