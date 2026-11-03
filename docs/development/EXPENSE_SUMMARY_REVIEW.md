# Expense list summary and import review follow-up

## Accepted implementation scope - 2026-11-03

The owner requests a bottom-of-list expense summary, using SuperFaktura as a
layout reference, and remediation of current CodeRabbit comments on PR #426.
Work remains on `feat/superfaktura-expense-import`, baseline `c9eb45a3`, in its
retained worktree. Existing primary edits and external repositories are preserved.
No real expense import, schema/payment-record change, merge or deployment follows.

The owner selected the sum of documents not marked paid for this task. Imported
partial payments currently live in notes, so the label is **Unpaid expense total**.
This bounded UI change does not infer an exact remaining balance from note text
or introduce a separate partial-payment ledger.

## Contract

The footer shows all expenses matching the current company, year/date and status
filters, across every page. Selection does not affect totals. Cancelled expenses
remain excluded by the existing list filter. Currency groups remain separate;
no conversion or mixed-currency grand total is invented.

The existing authenticated expense paginator adds:

```json
{
  "summary": {
    "count": 2,
    "currencies": [
      {"currency": "EUR", "count": 2, "total": "30.30", "unpaid_total": "20.20"}
    ]
  }
}
```

Existing paginator fields and data are retained. Both amounts are fixed-scale
decimal strings. Only `recorded` documents contribute to unpaid totals; payment
dates and free-text metadata are not evidence for partial deductions. Backend
arithmetic uses exact decimal addition; local arithmetic uses integer cents.
Local-first data stays encrypted in the browser and is not uploaded for totals.
Older server responses without a summary hide the footer rather than displaying
a page-only total as the full filtered total.

Use an existing-style bordered card below the table/pagination, right-aligned on
desktop and full width on mobile. A labelled section and definition lists expose
the count once and total/unpaid amounts for each currency. Hide stale amounts
during loading, failures, empty results and company changes. Latest-request and
captured-company guards prevent older responses from replacing newer results.
All five UI locales and expense-import guides change together.

## Independent bounded assignments

| Owner | Writable files | Evidence |
| --- | --- | --- |
| Backend | Expense Excel service/field aliases/import tests; expense controller, new list-summary support and summary API tests | CSV text/formula preservation, fractional-mask regression, company/filter/currency/precision API checks |
| Frontend | Shared spreadsheet parser/tests; expense page, new summary component/helper/tests, five locale files | Actual XLSX regressions, all-page/local/server/race/precision/component cases |
| Coordinator | API response typing, five user guides, integration/generated artifacts and shared state | Contract, ratchets/build/schema checks, scoped publication |
| Designer | None | Existing invoice-footer pattern, mobile/accessibility and accurate labels |
| Security and QA | None | Independent frozen-source review and isolated synthetic validation |

## CodeRabbit findings to validate

- `4448902503`: preserve plus-prefixed and long numeric text in CSV through a
  per-reader string binder, without changing the global formula protections.
- `4448902510`: zero-mask identifier formatting must not round fractional values
  on the server.
- `4448902523`: dashed VAT/phone/postal-code aliases must match the local importer.
- `4448902532`: local numeric formatting requires an integer and multiple zeros;
  plain `0` must not round expense amounts.

Tests use synthetic files, fake browser services and a verified isolated
SQLite-memory/network-disabled PHP target. No supplied workbook or screenshot
values become fixtures. Graphify supplied no useful expense edges; the feature
worktree has no graph, and none was rebuilt or installed. Current test/review
results and final frozen revisions will be recorded before publication.

## Receiving-session reconciliation - 2026-11-03

The owner supplied the cross-session handoff for this retained feature worktree.
All 27 live file hashes and all 27 immutable backup hashes match the supplied
manifest; its tracked patch hash also matches. The live changed-path set matches
the manifest exactly, with no staged or unexpected files. Published PR #426 and
local HEAD remain `c9eb45a3`. The receiving coordinator adopts this candidate
without restoring snapshots or attributing later changes to the original session.

Open gates are the five changed fingerprints for existing Evolu diagnostics and
the final browser/independent QA pass. Prior reported test/security results are
historical evidence, not new receiving-session approval.

The frontend specialist owns bounded type repairs in `sanitizeStoreReferences.ts`,
`ExpenseForm.vue` and `ExpenseShow.vue`, plus the summary types/helper if needed.
Use established app-row conversions and remove unnecessary Promise casts; preserve
runtime handling, schema and the unchanged typecheck baseline. The backend
specialist owns the six assigned PHP source/test files above and verifies the
aggregate/import contract on disposable infrastructure. Security reviews only;
the coordinator owns UI integration, shared state, final QA and Git publication.
No concurrent writes to these ownership sets are assigned.

## Receiving-session fixes and frozen-source review

The five new diagnostic fingerprints exposed existing unsafe Evolu boundaries
whose displayed property order changed. Remove unnecessary Promise-array casts
from the store-reference sanitizer, and use the established identity-preserving
`toAppRows<EvoluExpenseRow>` boundary in expense form/detail. Explicitly check
the optional company link before access. The lightweight company query still
omits `linkedStoreId`; its company-link cleanup remains a documented pre-existing
no-op, with query selection unchanged. No typecheck baseline or schema is changed.
Full typecheck passes with 112 existing diagnostics, no new fingerprints and eight
stale baseline entries. The existing gate self-tests pass.

Independent security found SEC-EXP-1: an invalid historical three-character
currency can make Intl currency formatting throw, including on an off-page row.
The receiving frontend fix preserves exact decimal values with a localized
decimal/literal-currency fallback. Valid codes keep currency formatting; currency
groups remain separate and Vue escapes the literal text. Three new regressions
cover malformed codes, large values, negative cents and escaped rendering.

The backend specialist retains all candidate runtime code and adds a regression
for negative recorded, paid and excluded cancelled adjustments. Fresh validation:
39 PHP cases / 259 assertions, six-file Pint and full PHPStan pass in the verified
network-disabled SQLite-memory environment. One existing PHPUnit discovery
deprecation remains. Frontend focused checks pass 61 cases across four files.
Full lint passes with 48 existing warnings under the unchanged ratchet; all five
locale sets and the generated API contract inventory pass.

Independent receiving-session security accepts the final 18 production files,
including the new type boundaries and resolved currency finding. Its sorted
source SHA-256 manifest is
`8ef82904ed314a56bd610b92a2ad23b78eb6e2598e7021fdeeb087050333f120`.
It independently passes 79 frontend cases, 26 PHP cases / 193 assertions and 23
additional synthetic assertions, including malformed off-page currencies.
Final receiving-session QA accepts the same frozen production manifest: 33
independent frontend cases, 35 PHP cases / 247 assertions and four synthetic
browser scenarios pass. Desktop cards remain compact and right-aligned; mobile
cards fit the viewport, with zero overflow, clipped amounts, external requests
or page errors. The isolated fixture must run from the feature worktree so
Tailwind sees its content; an initial primary-directory fixture failure was
corrected without product changes.

Coordinator full frontend validation now passes 1,171 cases across 149 files.
Production build, locale parity, full lint, the existing typecheck gate tests,
generated API contracts and whitespace checks pass. Existing bundle warnings
and the documented lint/typecheck baselines remain unchanged. The original
27-file recovery snapshot and its patch still verify against the original
manifest; later receiving-session changes are independently reviewed above.

Publication remains on PR #426. The combined user-facing changelog entry uses
that PR's canonical URL. Verify the final commit and exact-head CI after push;
no original workbook, merchant values, real expense import, PR merge or
deployment belongs to this follow-up.
