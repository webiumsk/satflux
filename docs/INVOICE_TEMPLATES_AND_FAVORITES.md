# Invoice templates (and favorites)

Part of P4. Lets users save a frequently used invoice as a **template** and
prefill a new invoice from it (everything except number and date). All
local-first (Evolu), works offline.

## Favorites - intentionally not a separate feature

The brief also asked for "favorite line items". The audit found this is
**already covered by stock items** (`companyStockItem`): a stock item carries
name / description / unit / sale price, the invoice line editor's
`StockLineSuggestField` already offers them as quick-insert while typing, and
there is even an `excludeFromSuggester` flag. Building a parallel "favorites"
table + suggester would duplicate that. Decision (with the maintainer): **skip
a dedicated favorites feature**; use stock items for reusable lines. If a
curated favorites list is wanted later, the lightest path is a filtered view
over non-inventory stock items - not a new table.

## Templates - solution

- **Storage:** a new Evolu table `invoiceTemplate` (`id, companyId, name,
  payloadJson`). `payloadJson` is a JSON snapshot of the document payload
  (header + lines) with the number and dates stripped. Stored in the 256 KB
  JSON column type already used for issued-document snapshots. Local-first,
  E2EE-synced, and included in the invoicing backup snapshot.
- **Save as template:** on the invoice form, "Save as template" prompts for a
  name and snapshots the current form via `buildTemplateSnapshot(payload())`.
- **Apply template:** on a new/draft invoice, a "Load template" dropdown lists
  the company's templates; picking one calls `applyDocument(...)` with the
  snapshot shaped as a fresh draft - status `draft`, empty number and dates -
  so the form prefills everything except number/date (the number preview and
  today's date are assigned as for any new draft).

## Implementation

| Concern | File |
|---|---|
| Snapshot shaping (pure, tested) | `resources/js/evolu/invoiceTemplate.ts` |
| Evolu table | `resources/js/evolu/schema.ts` (`invoiceTemplate`), query in `resources/js/evolu/client.ts` (`allInvoiceTemplatesQuery`) |
| CRUD | `resources/js/evolu/invoiceTemplateCrud.ts` (`saveLocalInvoiceTemplate`, `deleteLocalInvoiceTemplate`) |
| Composable | `resources/js/composables/useInvoicingInvoiceTemplates.ts` (list / save / remove / refresh) |
| UI (save + apply) | `resources/js/pages/invoicing/InvoiceForm.vue` |
| Backup inclusion | `resources/js/evolu/invoicingSnapshot.ts` (+ fingerprint / server-prepare) |
| i18n | `resources/js/locales/{en,sk,es,cs,de}.json` (`template_*`) |

`buildTemplateSnapshot(payload)` drops the volatile fields
(`issue_date`, `due_date`, `delivery_date`, `variable_symbol`) and stamps a
version. `templateToDraftDocument(snapshot)` returns the object `applyDocument`
consumes for a fresh draft (status `draft`, empty number/dates). The snapshot
shape deliberately matches `useInvoiceDocument.payload()` output, which is also
the shape `applyDocument` reads - so no field remapping is needed.

## Testing

- `resources/js/__tests__/invoiceTemplate.test.ts` - `buildTemplateSnapshot`
  drops volatile fields and does not mutate the source, serialize/parse
  round-trip, `parseTemplateSnapshot` rejects malformed/legacy JSON and
  defaults the version, and `templateToDraftDocument` forces an empty
  number/dates draft while preserving content.

Run: `npm run test -- invoiceTemplate`.

## Usage

1. Build an invoice you want to reuse, then click **Save as template** and give
   it a name.
2. On a new invoice (or a draft), pick it from the **Load template** dropdown -
   the form fills in from the template; enter/confirm the number and dates and
   save as usual.

## Known limitations

- Template management is minimal: save and apply are in the invoice form;
  `deleteLocalInvoiceTemplate` exists and is used by the composable, but there
  is no dedicated "manage templates" list UI yet (a possible follow-up).
- Applying a template overwrites the current draft's fields; the dropdown is
  therefore only shown on new/draft invoices, not on issued/locked ones.
- A template stores a contact/store reference if the source invoice had one; if
  that contact/store is later deleted, applying the template leaves the field
  empty (no dangling reference is created).
