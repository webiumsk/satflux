# Expense payment review and duplicate warnings

## Authorization and baseline

On 2026-11-05 the Product Owner authorized implementation after the recommendation
to start with matching outgoing payments to existing expenses and warning about
possible duplicate expenses. This bounded implementation covers those two features.
The independent relay and QR payment ideas remain proposals. No release or deployment
is authorized by this implementation request.

Base: `cb55aab0`, freshly fetched `origin/master`, including merged PR427 and PR428.
Work is isolated on `feat/expense-reconciliation`; unrelated primary checkout work
is preserved. Only the owned Satflux repository is modified.

## Acceptance criteria and contracts

- Preserve the existing automatic bank-email reconciliation and origin safety gates.
- Suggest existing expenses only for eligible outgoing transactions with an exact
  positive full amount, explicit equal currency and a strong variable-symbol or
  document-reference match. An unverified email direction remains ineligible.
- Require explicit confirmation. Revalidate current transaction, expense, company,
  owner and links before writes. Reject conflicting or stale links; retrying the
  same successful association is idempotent.
- Reuse server transactions/locks and local-first reconciliation/mutation receipts.
  Do not use client flags as authorization or bypass shared-owner/read-only checks.
- Unlinking removes only the relationship and preserves the expense's payment
  status, matching existing behavior. Explain this in the UI; do not infer a prior
  payment state that was never recorded.
- Show advisory duplicate candidates from equal external number/amount/currency,
  or normalized title/issue date/amount/currency. Exclude the current expense,
  cancelled/deleted rows and other companies/owners. Never merge, delete or block
  saving solely because a candidate exists.
- Current expenses have no dedicated supplier/contact/IBAN association. Do not
  claim supplier matching or add that separate product scope implicitly.
- Compute local-first suggestions on the device; do not send local expense data
  to Laravel to detect duplicates. Support legacy server invoicing as well.
- Preserve EN/SK/ES/DE/CS parity, responsive controls, keyboard access and visible
  loading/error/retry states. Do not raise lint or typecheck ratchets.

API contracts, under `/api/invoicing/companies/{company}`:

| Method/path | Input | Response |
| --- | --- | --- |
| GET `bank-transactions/{transaction}/expense-suggestions` | None | `{data: [{expense, reason}]}` |
| POST `bank-transactions/{transaction}/match-expense` | `{expense_id}` | `{data: {expense, transaction}}` |
| POST `expenses/duplicate-suggestions` | `{external_number?, title?, issue_date?, total, currency, exclude_id?}` | `{data: [{expense, reason}]}` |

Expense candidate fields: `id`, `internal_number`, `external_number`, `title`,
`total`, `currency`, `issue_date`, `status`; payment candidates also expose
`variable_symbol`. Payment reasons: `variable_symbol`, `payment_reference`.
Duplicate reasons: `external_number`, `title_date_amount`. Bound and deterministically
order candidate results. Existing unmatch endpoint retains link-only semantics.

The existing mapped spreadsheet preview also returns `duplicate_warnings`, a list
of at most twenty `{row, expense, reason, in_file}` warnings against company expenses
and earlier valid rows in the same file. Both local and server modes calculate
this in their existing data boundary, using indexed comparisons and bounded memory.
Warnings never modify import skip/conflict rules or financial records.

## Ownership and dependencies

| Task | Actual role | Writable scope | Dependencies / gate |
| --- | --- | --- | --- |
| Backend | backend | Invoicing PHP controllers/services/requests, relevant models/routes and PHP tests | Confirmed contract; disposable test target |
| Frontend | frontend | Feature TS/Vue, five locale JSON files and frontend tests | Confirmed contract; existing dependency reuse |
| Security | security | None | Independent design and final source review |
| QA | qa | None | Independent final behavior and negative-case validation |
| Coordination | main | This note, user documentation, generated API contracts and shared state | Integrate final author/reviewer evidence |

No specialist may commit, push, merge, publish, deploy, install dependencies or
write to other repositories. Security and QA cannot approve their own implementation.

## Validation plan

Use synthetic fixtures and isolated SQLite/container infrastructure. Cover amount,
currency, direction evidence, company/owner isolation, stale candidates, duplicate
confirmation/retry, conflicting links and unlink payment-state preservation.
Duplicate detection must cover edit self-exclusion, cancelled/deleted rows, number
and title/date reasons, normalization and cross-company isolation. Verify actual
form/table interactions alongside meaningful domain tests. Run relevant existing
PHP/Vitest suites, lint, typecheck, locale parity, API generation checks and build.
No production databases, wallets, merchant credentials or live relays are used.

## Implementation review evidence

Implemented local-first and legacy server suggestions/confirmation, paid-preserving
unlinking, form duplicate warnings and mapped spreadsheet warnings. Existing
automatic bank-email behavior is retained. User documentation and UI copy cover
EN/SK/ES/DE/CS. No schema migration or supplier/contact model expansion is included.

The new matching action uses the movement's recorded direction rather than legacy
display guesses. Explicit credit, null and unverified evidence do not fall back;
only older payloads without the field retain display fallback. Global display
heuristics and the existing create-expense action are preserved.

Independent reviewers `/root/expense_qa` and `/root/expense_security` accept these
final bytes, with no unresolved findings:

- Runtime, 23 files: `982868caf864e82b436bd6a72f756a6f30375efc48d4cdde264f79613da5ef95`.
- Source/tests/CI, 35 files: `edf41386a24f337efbe90c7db416bcb17babe5e31562cf0f72394993df346850`.

Each manifest hashes sorted `sha256  relative-path` lines, including a trailing
newline. Source/tests/CI includes the workflow; runtime excludes test and workflow
files. Documentation is outside these code manifests. Both reviewers independently
recomputed every file hash and confirmed exact agreement.

### Resolved review findings

1. Post-handler evidence drift: the original manual path checked the proposed
   patches but omitted a final comparison of financial evidence. It now verifies
   fresh amounts, currency, dates, references, ownership and link uniqueness after
   the receipt. Independent mutation control demonstrates false success without
   this check and rejection with it. Potentially committed data is not compensated.
2. P2 lock-order cycle: company-first matching could deadlock against transaction-first
   expense creation. The corrected order is transaction, fresh company and expense,
   retaining mode revalidation. Independent actual-service PostgreSQL sessions
   reproduce historical SQLSTATE `40P01`; corrected code yields creation success
   and a clean stale-match rejection. Another case verifies two movements cannot
   claim one expense.
3. P2 action reachability: recorded debits could be displayed as credits by older
   heuristics. Three real mapper-to-parent-page regressions fail before the change
   and pass after it. Recorded credit or unverified evidence cannot offer the new
   action merely because a display guess says debit.

The additional attachment-page fixture change makes its lag selector reactive;
all original assertions remain. Native test support uses the installed SDK console
configuration and an asynchronous worker bootstrap without relaxing lint settings.

### Executed checks

| Check | Result |
| --- | --- |
| Final full Vitest | 1,760 tests / 177 files passed after the recorded-direction fix |
| Final lint / typecheck / build | Passed: 48 existing warnings under 52; 111 baselined type errors, nothing new; production build succeeds |
| Backend focused SQLite suites after lock correction | 129 tests / 563 assertions passed |
| Actual PostgreSQL contention | 2 tests / 36 assertions passed; independently repeated by security |
| Exact CI JUnit no-skips gate | Passed: exactly two cases, no skipped/error/failure nodes |
| Independent PostgreSQL historical/fixed control | Historical `40P01`; corrected clean conflict outcome |
| Real Evolu / SQLite worker | 11 cases passed, including actual COMMIT failure and eight evidence changes |
| Independent QA | 90 temporary cases; repository SQLite/attachment 45 cases; final payment-page 13 cases passed |
| Independent final security UI selection | 10 actual mapper/page cases passed |
| Pint / PHPStan | Full Pint 1,121 files and full PHPStan passed; final service/new PostgreSQL test scoped checks passed |
| Locale parity | Passed, preserving four existing informational SK-only keys |
| API contracts | Export/check and offline validation of all three documents passed |
| Workflow | YAML valid; PostgreSQL contention suite mandatory in the existing CI job |

The existing PostgreSQL CI job creates the dedicated guarded expense test database,
runs `ExpenseReconciliationConcurrencyTest` with
`EXPENSE_RECONCILIATION_PG_TESTS=1`, and rejects skipped or missing cases. This workflow
was reviewed locally; no GitHub run or remote CI success is claimed.

### Broader PHP baseline limitation

The broad isolated SQLite run completed 2,885 cases: 2,825 passed, six failed and
54 were skipped, with 13,693 assertions and one existing PHPUnit annotation
deprecation. All six failures are DNS-dependent guest-upgrade email validation
returning 422 in the network-disabled container. They were reproduced on unchanged
`cb55aab0`: `AccountTest` has the same five failures (20 cases / 45 assertions),
and the selected `SeedFirstLoginTest` case reproduces the sixth. Authentication
validation was not changed to make the environment pass.

That broad run preceded the final lock-order-only backend correction; the final
129-case SQLite rerun and actual PostgreSQL cases cover the corrected implementation.
Do not describe the entire PHP suite or remote CI as green. Conditional Redis and
other provider-specific suites account for the local skips. Actual deployment and
production behavior were not tested. Browser interactions were validated through
mounted real parent components; a new full Playwright server run was not performed.

### Delivery boundary

At the implementation handoff, all work was local in the durable feature worktree;
no commit, push, PR, merge, deployment, real payment or merchant-data access had
occurred. On 2026-11-05 the owner explicitly authorized commit/push of this reviewed
result to `webiumsk/satflux:feat/expense-reconciliation`. The 51-path scope remains
unchanged. Main owns publication and exact remote
verification. The primary checkout and unrelated edits are preserved; PR creation,
merge and deployment remain outside this request.
A public changelog fragment requires a real PR source URL; do not fabricate one.

### Publication normalization

First staging also checks formerly untracked files. It detected one redundant EOF
blank line in `resources/js/evolu/bankPaymentValues.ts`. Only that final LF was
removed: the accepted old bytes equal the publication bytes plus exactly one LF.
Every other code, test and CI file remains byte-equal to the implementation review.
The original review/test results above are prior evidence, not newly rerun tests.

Publication manifests use the same format and path sets:

- Runtime, 23 files: `620c7f0065a82af5a6f69f5db347101cdc6f9c7bdeda9621723fb007f84956f2`.
- Source/tests/CI, 35 files: `c947959b606c42f4cdc1fa5b9e86160a9a9a4bd66e86b86ce97d09899ab8557b`.

Independent QA/security publication review verifies the exact one-LF equivalence
and final code hashes; main verifies the staged path set and published commit/tree.

The disposable PostgreSQL container was stopped and removed after review. Its data
was held only in tmpfs; synthetic JUnit evidence and review probes remain outside
the source checkout. The isolated AST-only Graphify update completed without LLM
extraction, leaving the primary checkout's existing graph unchanged.
