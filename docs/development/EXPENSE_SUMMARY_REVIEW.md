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

## Subsequent CodeRabbit design review - 2026-11-03

Two new findings follow published `a1b1c32b`, whose four CI jobs pass:
`4452388697` retains an out-of-range page when the year changes, and
`4452388667` permits a concurrent database commit to make summary and page
reads disagree within one response. Both are confirmed.

Use one year-change handler for desktop/mobile: reset page and selection before
loading. Two server-mode regression cases reproduce the old behavior; receiving
author checks pass 80 focused cases, with independent stale-response probes.

Architecture, security and QA agree the backend contract before implementation:

- Use the filtered query's actual connection, streaming decimal summary, paginator
  count/page and array serialization inside the same read scope.
- For an owned PostgreSQL transaction, set Repeatable Read and Read Only before
  data reads. Ordinary Read Committed transactions use statement snapshots and
  cannot provide this guarantee. [PostgreSQL 16 isolation](https://www.postgresql.org/docs/16/transaction-iso.html)
- Reuse a caller's Repeatable Read or Serializable snapshot through a managed
  savepoint. Reject a weaker existing transaction before expense reads; never
  upgrade it or replace the connection. A failed SQL read rolls back only the
  owned savepoint for ordinary statement errors, preserving the caller.
  PostgreSQL serialization failures/deadlocks retain Laravel's outer-transaction
  retry/rollback requirements. Transaction settings remain scoped to the owned
  transaction. [SET TRANSACTION](https://www.postgresql.org/docs/16/sql-set-transaction.html)
- SQLite uses normal deferred transactions; inspect the transaction PDO's
  `read_uncommitted` setting and reject it when enabled. No persistent pragma,
  session setting, immediate write transaction or row lock is introduced.
  [SQLite isolation](https://www.sqlite.org/isolation.html)
- Reject unsupported database drivers explicitly. Normal current request
  middleware does not wrap this endpoint in another transaction. Consistency
  applies to each response, not to later independent pagination requests.

The coordinator owns design/state/publication. Backend owns controller, summary
helper and the new bounded snapshot test; frontend owns year-handler/page tests.
Security and QA review read-only. Deterministic two-connection tests must prove
concurrent insert/update/delete behavior, a later response seeing the new commit,
writer progress, rollback/depth restoration, unchanged session defaults and
nested-caller usability after an actual SQL error. Use a temporary SQLite WAL
file and a fresh internal-network PostgreSQL 16 test database; no production or
default application database is involved. No data schema or payment state changes.

### Snapshot implementation and author validation

The helper takes the actual query builder connection, rather than resolving the
model's potentially changed default. A regression changes the default after
builder construction and verifies that the original connection owns the read.
The controller constructs the complete JSON response inside the snapshot.

The new snapshot corpus passes on both disposable targets: SQLite has 11 cases /
124 assertions and one PostgreSQL-only skip; PostgreSQL 16 has 11 cases / 155
assertions and one SQLite-only skip. Deterministic independent writer commits
exercise insert, update and delete, coherent current responses, fresh subsequent
responses, unchanged defaults and transaction/savepoint cleanup. Overlaying only
the published old controller reproduces all three PostgreSQL mismatches without
changing the live checkout: insert/delete count disagreement and stale-summary /
new-page amount disagreement on update.

Related PHP checks pass 39 cases / 259 assertions; scoped Pint and whitespace
checks pass. Full local PHPStan has no changed-file diagnostics but cannot pass
because the available vendor tree lacks the optional `Dedoc\Scramble\Scramble`
dependency. No dependency, baseline or analysis configuration is changed. Fresh
published-head CI must run full analysis with the locked development dependencies.
Default CI exercises SQLite; the PostgreSQL evidence above comes from the
separate opt-in corpus with `EXPENSE_SNAPSHOT_PG_TESTS=1` and the guarded database
name `satflux_expense_snapshot_test`, not from the normal CI database.

Frontend author checks pass 80 focused cases. Coordinator full typecheck passes
with the same 112 existing diagnostics, no new fingerprints and eight stale
entries; lint passes with the same 48 warnings under the unchanged 52 limit.
All nine changelog fragments validate. No locale or API contract change is needed
for these two repairs.

### Independent final review

The independent security reviewer accepts the frozen five-file implementation /
regression delta over `a1b1c32b` with no blocking findings. Its separate SQLite
run passes 11 cases / 124 assertions with the PostgreSQL-specific skip; its page
suite passes 17 cases. Authentication, tenant filters, summary arithmetic,
configuration, dependencies and CI files remain unchanged. The review explicitly
retains the framework's outer rollback/retry policy for concurrency failures.

The coordinator rechecks all five file hashes after review. The SHA-256 of the
relative-path SHA-256 manifest, sorted by path with `LC_ALL=C sort -k2`, is
`44e4e865a074262e116bf0b61ffde3703e091f6c58e5f9e6772d5840baf88084`.

Independent final QA accepts that same manifest with no blocking findings. It
separately runs the frozen PostgreSQL corpus: 11 cases / 155 assertions pass
with the SQLite-only skip. Its three additional frontend probes cover both
year controls and a delayed old-page response; all pass. The local PostgreSQL
test proves independent writer commits under a bounded lock timeout, coherent
current results and the next response observing the new state. An additional
custom stdin probe was not executed because its approval was aborted; it was
not retried and contributes no evidence. Only the separately authorized standard
PHPUnit corpus supplies the independent PostgreSQL execution evidence.

The five reviewed implementation/test files remain unchanged after acceptance.
Publish on the existing branch, verify fresh exact-head CI and close only the
two confirmed threads. No PR merge, real expense import or deployment is implied.
