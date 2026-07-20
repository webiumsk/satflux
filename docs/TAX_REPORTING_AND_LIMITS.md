# VAT reporting and registration-limit tracking

Part of P4. Two user-facing capabilities for the local-first invoicing module:

1. A **VAT summary report** - VAT by rate for a chosen period, with CSV and
   print-to-PDF export.
2. A **VAT-registration turnover limit** the user configures per company, with
   a proactive alert as current-year turnover approaches it.

Everything here is computed in the browser from the local-first Evolu store -
there is no server round-trip and it works offline. This matches where the
invoicing data actually lives: amounts are the source of truth in Evolu (see
`resources/js/evolu/documentCrud.ts` `calcDocumentTotals`), and the server PHP
calculators are only a mirror.

## Solution

### VAT summary report

- Route: `invoicing-vat-report` (`companies/:companyId/vat-report`), a Tools
  sub-section. Page: `resources/js/pages/invoicing/VatReport.vue`.
- Period selector reuses the existing `useInvoicingIssuePeriod` presets
  (this/last month, quarter, year); defaults to the current year.
- Shows, per currency (currencies are never summed together): document count,
  gross turnover, and a per-rate table (net base / VAT / gross) with a totals
  row.
- Exports:
  - **CSV** - `vatSummaryToCsv` / `vatSummaryCsvBlob` (RFC-4180, UTF-8 BOM,
    CRLF), downloaded via the existing `downloadCsvBlob`. Locale-independent
    (fixed 2-decimal), suitable for accounting import.
  - **PDF** - client-side print: the report is rendered into an isolated hidden
    iframe and `window.print()`ed, so the app chrome stays out of the output
    and no global print CSS leaks into the rest of the SPA. "Save as PDF" in the
    browser's print dialog produces the file. No new dependency, works offline.

### VAT-registration limit

- New local company field `vatTurnoverLimit` (Evolu column), edited in the VAT
  block of `CompanySettingsForm.vue`. `0` disables the feature.
- `TaxLimitAlert.vue` computes the company's **current-year net turnover** in
  its default currency and compares it to the limit:
  - `>= 80%` - amber "approaching" warning,
  - `>= 95%` - red "critical" warning,
  - `>= 100%` - red "exceeded".
  - below 80% (or no limit) it renders nothing.
- Shown in the company profile settings, next to the limit field.

## Implementation

| Concern | File |
|---|---|
| Aggregation + limit maths (pure, tested) | `resources/js/evolu/vatReport.ts` |
| Report data loading (Evolu docs + lines) | `resources/js/composables/useVatReport.ts` |
| Report page | `resources/js/pages/invoicing/VatReport.vue` |
| Limit alert | `resources/js/components/invoicing/TaxLimitAlert.vue` |
| Limit field storage | `evolu/schema.ts` (`vatTurnoverLimit`), `companyMap.ts`, `companyUpdate.ts`, `companyInsert.ts` |
| Limit field UI | `resources/js/components/invoicing/CompanySettingsForm.vue` |
| Route / nav | `router/invoicingRoutes.ts`, `composables/useInvoicingLayout.ts`, `components/invoicing/InvoicingPageShell.vue` (`isToolsArea`) |
| i18n | `resources/js/locales/{en,sk,es,cs,de}.json` (`vat_report_*`, `vat_turnover_limit_*`, `vat_limit_alert_*`, `settings_nav_vat_report`) |

### Determinism and correctness

`buildVatSummary(documents, lines, {from, to}, opts)`:

- **Grand totals** (turnover / net base / output VAT) come straight from the
  stored scalar document fields (`total` / `subtotal` / `taxTotal`), already
  reconciled at save time.
- **Per-rate breakdown** is re-derived from each stored line's gross
  (`lineTotal`) and rate (`taxRate`): `net = gross / (1 + rate/100)`. Because
  `calcDocumentTotals` scales every `lineTotal` and the `subtotal`/`taxTotal` by
  the same document-discount ratio, the per-rate bases sum back **exactly** to
  the stored `subtotal` and the per-rate VAT to the stored `taxTotal`.
- **Credit notes** are stored as positive amounts with no sign; they are
  subtracted (they reduce turnover and output VAT).
- Only `issued` and `paid` documents of type `invoice` / `credit_note` count by
  default (configurable via `opts`). Drafts, quotes and proformas are excluded.
- Amounts are grouped by document currency; there is no FX conversion.

`vatLimitProgress(turnover, limit)` returns the percentage and a level
(`ok` / `approaching` / `critical` / `exceeded`), or `null` when no positive
limit is set.

### Storage note (local-first vs server)

`vatTurnoverLimit` is stored **only in Evolu**. It is a pure local calculation
input, and in local-first mode the server "bridge" company only receives
identity fields - a Laravel column would stay empty. No migration was added.

## Configurable VAT rates - decision

The spec asked for configurable VAT rate values. In practice this is **not
needed for the report and would be counter-productive**:

- The report is inherently rate-agnostic: it aggregates whatever rates actually
  appear on documents, so it already adapts to any country or legislation
  change without configuration.
- VAT rates per jurisdiction live in `resources/js/config/jurisdictionRules.ts`,
  a browser mirror of `app/Support/Invoicing/JurisdictionRules.php` kept in sync
  by tests. Making rates freely user-editable would break that
  single-source-of-truth contract.
- Per-company flexibility already exists via the company `vatRateDefault`
  override.

So VAT rates remain sourced from jurisdiction rules + the per-company default;
full user-level rate configuration is intentionally out of scope. Adding a new
jurisdiction or changing a statutory rate is a one-line edit in both
`JurisdictionRules` files.

## Testing

- `resources/js/__tests__/vatReport.test.ts` - `buildVatSummary` (single/multi
  rate, document-discount reconciliation, credit-note subtraction,
  multi-currency, date-range and status/type filters, empty period),
  `vatSummaryToCsv`, `vatLimitProgress` thresholds, `turnoverForCurrency`.
- `resources/js/__tests__/vatReportPage.test.ts` - the report page renders
  per-rate rows / totals and the empty state.
- `resources/js/__tests__/taxLimitAlert.test.ts` - below-threshold, no-limit,
  80% amber, 95% red, exceeded.

Run: `npm run test`.

## Usage

1. Open the invoicing area, pick a company, go to **Tools -> VAT report**.
2. Choose a period (month / quarter / year). The table updates instantly.
3. **Export CSV** for accounting, or **Print / Save as PDF** for a clean
   printable document.
4. To enable limit tracking: **Tools -> Profile**, VAT block, set your annual
   VAT turnover limit (0 disables). As current-year turnover reaches 80% / 95% /
   100% of it, a warning appears on the profile page.

## Known limitations

- Multi-currency reports are grouped, never converted - there is no FX rate
  source in the invoicing path.
- The limit alert measures **net** turnover in the company default currency;
  turnover invoiced in other currencies is not converted into it.
- PDF is browser print output (via an isolated iframe), not a branded
  server-rendered template. A server-rendered `pdf.vat-summary` (following the
  `ReportController::pdf` pattern) is a possible future enhancement.
- The limit alert is surfaced in the invoicing profile; showing it on the main
  dashboard as well is a possible follow-up.
