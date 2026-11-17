# Expense and bank list filters

## Publication handoff - 2026-11-17

The owner authorizes normal Git publication. Reviewed source is committed as
`08e68fefc980ec5e8829078110255c0ef883ed29` on
`fix/expense-attachments-bank-direction`; [PR451](https://github.com/webiumsk/satflux/pull/451)
is open and unmerged against master `18729a26`. All 56 final source hashes remain
`dacf07ba8f8487ec8b70562c14838e9c5cd2d2818db5367bca554f2ce7e871b0`.
The follow-up adds publication records and a reviewed combined changelog fragment
without changing implementation. Refer to PR451 for its latest head, exact-head
CI and automated review; local evidence below is not remote CI or deployment.
No merge, deployment, public changelog release or provider operation occurs.

## Authorized continuation and baseline

The owner requests detailed expense filtering/search like Invoices, with the
same improvement considered for bank movements. Both lists receive the existing
invoice visual/period pattern with domain-specific fields. Work continues in
`fix/expense-attachments-bank-direction`, base `18729a26`, preserving the prior
accepted attachment/direction changes. No external repository or publication
is involved. Source status and deployed behavior remain separate.

## Acceptance and integration contract

- Expenses preserve All/Paid/Unpaid/Overdue and the initial current-year issue
  period, add invoice-style date presets/custom bounds, amount min/max, due-date
  mode/range and literal search across internal/external number, title, VS and
  notes. No supplier/client placeholder is promised without corresponding data.
- Bank movements add booking-date presets/range, resolved income/expense,
  matching state, nonnegative amount magnitude min/max and literal search across
  VS, counterparty, reference and linked document/expense metadata where available.
  The default period remains all time. Original payment proof stays independent.
- Apply predicates before pagination or the existing 50-row bank display cap.
  Totals reflect all matching movements, separately by currency. A dated bank
  account balance snapshot remains explicitly distinct from filtered movement totals.
- Expense totals, pagination, select-all, exports and bulk targets share the same
  applied criteria in local and server modes. Effective changes reset page and
  selection. A captured operation cannot silently expand after filters change.
- Applied and draft advanced criteria are separate. Clear resets both, status,
  period and selection; an empty filtered list offers Clear filters. Search is
  case-insensitive literal text, including literal percent/underscore in SQL.
- Preserve company/tenant scoping, encrypted browser boundaries, existing request
  generation/cancellation guards and reconciliation/sync protocols. No search
  text goes into browser navigation history, localStorage, new application logs
  or new telemetry. Legacy authenticated GET-list query transport stays as in
  Invoices; existing infrastructure access logs may include query strings.
- Desktop uses the existing invoice bar/panel styles; mobile uses the current
  drawer. Associated unique labels, pressed/expanded state, Enter apply, Escape
  close, width-safe period UI and applied-filter counts are required.
- Invalid/reversed dates and nonfinite/negative/reversed amount ranges cannot
  apply. All five locales and user guides stay consistent.

## Ownership

| Task | Role | Exclusive writable scope | Evidence |
| --- | --- | --- | --- |
| Reference audit | designer `filter_ux_audit` | None | Existing invoice/mobile patterns and domain constraints |
| Expense filters | frontend specialist | Expenses page, expense list composable/map, expense service/bulk filter validation and bounded tests | Local/server predicates, pagination, summary, select-all, stale requests |
| Bank filters | backend specialist | CompanyPayments page, bank list composable/map/filter controller/support and bounded tests | Search before cap, resolved direction, totals/currency, sync isolation |
| Shared UI and integration | main | New record filter bar, locale files, guides, reports/state and shared API contract files if needed | Accessible bar/drawer, validation and common checks |
| Independent QA/security | independent reviewers | None | Exact final source freeze, negative isolation and user behavior |

Specialists preserve the other authors' edits and do not commit/push/merge/deploy.
Existing node/Composer manifests and safe disposable PHP runner remain unchanged.

## Shared UI API

`InvoicingRecordFilterBar.vue` is owned by main. Props: `kind` (`expense`/`bank`),
`containerClass`, `statusFilters` (`{id,label}[]`), `activeFilter`, `issuePeriod`
(`IssuePeriodState`), `advancedDraft` (`search`, `amountMin`, `amountMax`, optional
`due`, `dueFrom`, `dueTo`, `direction` string fields), `hasActiveAdvanced`, and
`layout` (`bar`/`drawer`). Events: `filter-change(id)`,
`period-change(IssuePeriodState)`, `advanced-open`,
`advanced-apply(IssuePeriodState)`. The component exposes `applyAdvanced()` for
the outer mobile Apply action, `closeAdvanced()` and `resetDraft()`. Both page
instances reset their component-local draft on Clear/import/company changes,
even when the applied parent period does not change.

Period editing uses a component-local clone; unsubmitted custom fields cannot
change bulk criteria. Desktop period selection commits via `period-change`;
mobile period edits commit with `applyAdvanced`, together with the parent's
advanced draft. Parent handlers copy the supplied period and advanced values
into applied state and refresh once. Named `actions` slot preserves existing
desktop import/new actions. The shared component owns UI range validation and
the parent/backend also validates filter values defensively.

Desktop advanced Apply uses the already committed period, ignoring an unsubmitted
date popup. Mobile Apply commits its pending period together with the advanced
fields. The existing header closes before emitting Apply; the two pages reuse
its existing `openFilterDrawer()` method synchronously when validation returns
false, keeping the error visible without changing other pages' header behavior.

## Implementation decisions

- Expense predicates share strict list/bulk validation. Unicode literal search
  uses PostgreSQL `LOWER`; SQLite registers a fixed deterministic lowercase
  scalar on read/write PDO handles, cached per PDO so active cursors are not
  disturbed. Search values are bound with explicit `ESCAPE '!'` semantics.
- Bank search and resolved direction run in bounded batches of 250, with only
  the requested page retained (at most 100 rows). Linked metadata is narrow and
  company-scoped. Pagination uses stable booking-date/ID order. All matching
  movements contribute to per-currency totals; the actual dated balance is a
  separate same-company query. The email synchronization branch is unchanged.
- User amount bounds are ordinary nonnegative decimals with at most two places,
  limited to exact safe minor units. Stored local/legacy row amounts are treated
  separately so input limits cannot hide existing movements. This changes read
  projection only, not settlement evidence or stored transactions.
- Reject reversed/impossible dates, year zero, unsafe amounts and malformed
  search text. Browser number inputs explicitly preserve draft strings rather
  than Vue's automatic numeric conversion. Search stays literal, including
  percent and underscore characters.
- A pending expense bulk operation captures applied criteria/company/selection.
  The existing financial gate rechecks currentness before its synchronous write
  loop; changing draft fields does not change its target scope.
- Existing OpenAPI scope defers expense/bank lists. No public schema coverage
  expansion is claimed. The existing API contract/inventory check passes without
  generated-artifact changes.

## Verification

Graphify query-first ran in the primary graph and returned mostly unrelated
nodes; the worktree graph is absent. Targeted reads follow without installation
or graph rebuild. All tests use synthetic fixtures and the already verified
disposable PHP target. The prior 30-file source freeze is retained separately;
this continuation will record its new and combined exact hashes and reviews.

## Frozen source and checks

The combined 56-file source/test/locale freeze is
`dacf07ba8f8487ec8b70562c14838e9c5cd2d2818db5367bca554f2ce7e871b0`.
Its 32 changed/new continuation paths have aggregate
`211ef9ea8993b0fa92a9f5f76d0d88c37e33a676515676dd16767f54f6d1a556`.
Both are SHA-256 of compact sorted JSON path/hash maps. The previous 30-file
`b677cde350ac194bd89089339877a13c88f914c6222740bf2348a8f663083dc0`
manifest remains separate. Prior source is preserved except the five expanded
locale files and additional bank-page test assertions.

Author checks on this scope:

- Expense: 49 PHP tests / 278 assertions; 51 JavaScript tests / two suites,
  plus the unchanged 18-case summary suite. Pint passes four PHP files,
  scoped PHPStan has zero errors, and scoped ESLint has no warnings/errors.
- Bank: 119 PHP tests / 612 assertions; 257 JavaScript tests / five suites.
  Pint passes three PHP files and scoped PHPStan has zero errors. Cases include
  cross-company links, filtering before 50 rows, legacy large values, summary,
  request cancellation and unchanged private synchronization/proof assertions.
- Shared bar: nine mounted tests cover string-preserving amount input,
  reversed/invalid bounds, applied versus draft period, mobile reset, unique
  labels, Escape, valid/invalid Unicode search and the native mobile submit
  target. Scoped ESLint passes.

Independent security pre-review additionally passes three financial-gate race
probes and four PHP security cases / 40 assertions. Virtual removal of the
new gate currentness checks reproduces stale mutations. Disposable SQLite
probes verify separate read/write PDOs, active-cursor reuse, Unicode and NULL
semantics. These counts overlap author suites and are not summed as a full run.

The full JavaScript run passes **3,751 tests / 260 files** on the preserved
pre-keyboard-fix freeze
`d74f718a377bf4024b78d157db7a3d5f00d059b5889c85924a6b5e76324c4a49`.
Actual browser QA then identifies missing native Enter submission on mobile:
the drawer form has several inputs and no submit target. The final two-file
correction adds only a drawer-specific submit button and a regression case.
All other 54 source files remain byte-identical. The final bar and both actual
list-page suites pass **125 tests / three files**, including all nine bar cases.
These counts overlap; no second complete 3,751-case run is claimed for this
bounded HTML/test correction.

Final typecheck passes with no new errors against the unchanged 110-error
baseline. Full pre-correction lint has zero errors / 46 inherited warnings;
both corrected paths pass their final zero-warning lint. The final production
build passes with 7,680 modules, as do locale parity and the unchanged API
contract/inventory check. No warning/typecheck ratchet or dependency changes.

Independent `independent_security` accepts both exact freezes after before/after
hash verification. It additionally passes the corrected legacy-large-amount PHP
case (seven assertions) and two selected JavaScript large-value/private-sync
cases; 142 filtered cases are explicitly not counted as executed. No unresolved
security blockers remain. The reviewer additionally verifies that reversing
the exact final form/test additions reproduces the prior accepted hashes.

Independent `independent_qa` accepts the final scope after 54 filter JavaScript
cases, two selected prior bank-colour cases (88 explicitly filtered cases not
executed), and 27 PHP cases / 149 assertions. Fourteen actual browser scenarios
pass in a synthetic component harness using the real filter bar, drawer and
production CSS, including Slovak 320-pixel layout, labels, native desktop/mobile
Enter, Escape/focus, draft Apply/Clear and visible invalid-range feedback. It
exercises the production early-close/reopen sequence; authenticated full-SPA
execution is not claimed. Zero external/API attempts and zero page errors.

Both browser findings are fixed: invalid mobile Apply keeps validation visible,
and mobile search Enter has a native submit target. The browser and fixture
server are closed and synthetic profiles removed after each review run. A final
settled visual check independently measures both mobile drawers at left 0,
right 320 and width 320 pixels, with no overflow. Screenshots use disabled
animations and a clean desktop state; the earlier cropped artifact captured
the opening transition. No application source change was needed. Safe source
manifests, validation summary and synthetic screenshots remain in the matching
ignored private review directory.

## Filter parameter scope

| List | Applied query fields |
| --- | --- |
| Expenses | Existing `filter`, optional fallback `year`; `issue_from`, `issue_to`, `due` (`all`, `overdue`, `custom`), optional `overdue_before` (`Y-m-d`), `due_from`, `due_to`, `amount_min`, `amount_max`, `search` |
| Bank | Existing `match_status`, `source`, `variable_symbol`, `page`, `per_page`; `from`, `to`, `direction` (`credit`, `debit`), `amount_min`, `amount_max`, `search` |

Date bounds are inclusive calendar dates; one-sided ranges are supported.
Custom due mode requires at least one bound. Explicit issue bounds replace the
expense year fallback. Search is trimmed literal text, at most 200 characters,
without NUL/invalid text. Amount inputs allow at most two decimal places and
safe integer minor units, including zero and equal bounds. Input constraints
are separate from stored-row support. No synchronization parameter is added.

## Operational limits

Bank display retains the existing newest-50 cap, while search and totals use
the complete matching result. Resolved-direction and Unicode bank searches scan
all candidate rows in bounded batches; this is a memory bound, not a new latency
guarantee for arbitrarily large histories. Amount summaries retain the existing
floating-point formatting model; no arbitrary-size monetary arithmetic guarantee
is introduced.

Local-first text criteria stay in browser memory and are never forwarded to
bridge synchronization. Legacy server list GET requests retain the existing
transport, so existing infrastructure access logs may include query strings;
this work does not claim deployed log redaction. No search text is newly stored
in navigation URLs, localStorage, application logs or telemetry.

The previous attachment/archive review and its explicit native fresh-target
restore limitation remain attributed to
[the earlier implementation](EXPENSE_ATTACHMENTS_BANK_DIRECTION.md). Filter
validation does not expand archival, settlement or authentication guarantees.

## PR451 review corrections - 2026-11-17

All three owner-supplied CodeRabbit findings were verified against the current
source and retained as valid. Bank scans now use a descending `(booked_at, id)`
keyset cursor in batches of 250 while preserving filtered page slicing. Reopening
the advanced filter resets both browser numeric-validity flags. Expense bulk
operations use concrete criteria captured by the last accepted successful load,
including across midnight; pending/failed loads cannot authorize an old selection.

Independent QA and security reviews found no blockers in the six-file source/test
diff against `0e9ea219`. Validation: 66 targeted JavaScript tests, 9 PHP bank-list
tests with 85 assertions, scoped ESLint/Pint and whitespace checks passed.
Typecheck retains the unchanged 110-error baseline. Publication to the existing
PR451 branch is authorized; merge and deployment remain separate owner gates.
The supplier payment QR feature is separate ongoing work on a dependent branch.

## PR451 second review corrections - 2026-11-17

CodeRabbit review5931726200 examined `8feb1e8a` and reported two valid issues.
The bank variable-symbol predicate now treats `"0"` as a supplied search value,
keeping matching rows and totals consistent. Expense list requests capture an
optional `overdue_before` date for either overdue filter. The successful-load
snapshot carries it unchanged into local bulk targets and server bulk payloads;
failed or superseded loads cannot refresh the snapshot. Both server predicates
validate/use the same cutoff, with the existing current-day fallback for callers
that omit it. No company, selection, status or custom-boundary checks are relaxed.

Independent QA and security accepted the ten source/test blobs with aggregate
`b007c62783a8303c947bf69e7b8c3012c84b9b21657d90391906dde2400a46a9`.
Independent regression runs pass 65 JavaScript tests and 43 PHP tests / 274
assertions. Scoped ESLint, Pint, PHPStan and whitespace checks pass; typecheck
adds no errors against the unchanged 110-error baseline. The new server cutoff
cases failed before the fix and pass afterward.

The owner requested review451 followed by review452. The parent review is complete;
next publish these corrections, verify exact-head CI and synchronize the dependent
branch. The next included CodeRabbit slot remains reserved for PR452. Review of
`8feb1e8a` is not automated approval of this follow-up. PR merges and deployment
require separate applicable approval.
