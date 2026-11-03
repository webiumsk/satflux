# Expense defaults and bank-mail reconciliation

## Authorized scope and observed evidence

On 2026-11-03 the owner requested linked defaults for new expenses and diagnosis
of missing production bank movements since July 16. They subsequently confirmed
that today's Tatra notification appears as Delivered in Mailgun and should mark
an existing expense paid. Mailgun retains only one day of logs. Delivered proves
webhook acceptance, not queue completion, persistence or expense reconciliation.
No production email, account, token, database or queue has been accessed here.

PR426 is actually merged as `686c9304`; older state entries saying open are
historical. Work continues on `fix/expense-defaults-bank-import` in the retained
expense worktree. The unrelated primary checkout and other repositories remain
untouched. Graphify's graph is absent; no build or tool installation is attempted.

## Acceptance and integration contract

- New expenses: supplier document digits default VS, received date defaults
  delivery date. Manual overrides and explicit attachment fields detach the
  respective default. Existing records retain their stored values.
- Bank address and local synchronization resolve the same selected company's
  bridge. Stale responses cannot change another company's address or data;
  overlapping same-company inbound refreshes share one import.
- Existing persisted Company recipient tokens of 8-16 characters remain usable
  under generation-length configuration drift. Exact domain/prefix/DB lookup,
  Store recipients, webhook authentication and bank-origin proofs remain intact.
- The base Compose queue restarts after its configured hourly exit. Local deploy
  configuration selects standalone Compose, which already has a restart policy;
  this source defect is therefore not established as the production cause.
- Automatic existing-expense reconciliation applies only to email movements:
  explicit raw debit, unmatched, no invoice/expense link, not a balance snapshot.
  The sole eligible expense must belong to the same company, be recorded with
  no paid/cancelled date, have no other movement link, and match exact positive
  fixed-point amount and a nonempty three-letter currency. There is no tolerance,
  partial-payment accumulation, overpayment conversion or first-candidate choice.
- A present bank VS takes precedence and must contain digits. It matches the
  normalized expense VS, or external document digits only when expense VS is
  absent. Malformed nonempty VS on either side fails closed. When bank VS is
  absent, only an exact trimmed external-number/reference match is eligible.
  Leading zeros remain significant; reference substrings never auto-pay expenses.
- A valid actual calendar booking date supplies the expense paid date. PHP locks
  and rechecks current state inside one transaction. Local writes prevalidate
  both updates and submit them synchronously to the same Evolu mutation queue;
  installed Evolu7.4.1 aborts the complete queue on validation failure. Context
  and counts change only after both validation results succeed.
- New API responses add `recorded_direction` independently of the existing
  inferred display direction. New local debit auto-match requires that explicit
  recorded direction; older servers can still import movements without immediate
  expense auto-match. Import and explicit batch matching serialize context reads
  and writes per Evolu instance and refresh affected queries before releasing it.
- New imports and explicit batch auto-match use this rule. Duplicate refreshes
  do not retroactively change historical expenses. Existing unlink semantics
  preserve the expense's paid state. No provider checkout, SEPA fulfillment,
  subscription, entitlement, schema or bank verification changes are involved.

Company mail reconciliation retains its existing bookkeeping trust boundary;
Company MIME decoding does not independently attest bank origin. This feature
does not promote that evidence into Store payment confirmation. Concurrent
offline-device CRDT uniqueness is not a new guarantee.

Debit-only parser qualification prevents new Tatra/SLSP imports from inventing
missing dates/currency or inferring VS from unrelated remittance digits. Credits
and Store proof remain unchanged. Existing persisted transactions have already
lost their original parsing provenance: their reconciliation uses normalized
ledger fields and does not retroactively qualify the original bank email.

## Ownership and review gates

| Task | Role | Writable ownership | Acceptance evidence |
| --- | --- | --- | --- |
| Expense defaults | frontend | ExpenseForm.vue, expenseFormDefaults.test.ts | Real mounted local/server form, manual/edit/ISDOC regressions |
| Address and queue | backend | BankInboundAddressService.php, compatibility test, one old fixture, base Compose queue | Token drift, strict rejection, no rotation, exact one-line restart change |
| Existing expense server match | backend | BusinessExpensePaymentMatcher.php, BusinessDocumentPaymentMatcher.php, focused PHP test | Atomic exact matching, denied/ambiguous/duplicate/rollback and concurrency cases |
| Debit evidence and API | backend | TatraBankEmailParser.php, BankTransactionController.php, strict-debit unit test | No defaulted debit evidence, no partial VS, recorded/display direction split |
| Device sync and expense match | coordinator | Bank composable, CompanyPayments.vue, local matcher/bankCrud, focused tests | Correct company, stale response, exact debit matching, atomic failure |
| Shared state and guides | coordinator | This note, state/roadmap/decisions/workspace, five-language guides | Verified outcomes and operational limitations |
| Independent QA | qa | None; synthetic temporary probes only | Before/after failures, regression and final manifest |
| Independent security | security | None | Separate review of trust, company isolation, failure and final manifest |

Both independent reviewers accepted the conservative matching design before its
implementation. No specialist can commit, push, merge, publish or deploy.

## Production diagnosis still required

Privately inspect the actual deployed revision/Compose variant, queue status,
pending/failed webhooks counts, effective inbound-enabled boolean and latest
persisted email-movement date/count for the intended company. Correlate today's
Delivered event with sanitized job outcome. Never dump raw job payloads, failed
exceptions, recipient tokens, bank messages or credentials. Do not replay the
message or retry all failed jobs as diagnosis. Missing historical mail cannot be
recovered from expired Mailgun logs; already-persisted rows can be synchronized.

The owner confirms standalone Compose. A direct Docker listing shows the queue
container Up 22 minutes and healthy. The initial Compose `ps` ran without its
expected environment/project and returned no matching containers, so it was not
evidence of a missing worker. Pending/failed webhooks counts remain requested.

## Validation

Final source manifests, independent dispositions and completed check results are
recorded below when implementation is frozen. Earlier counts belong only to
their stated scope; they do not verify later matching changes or production.

### Final frozen implementation

Runtime source manifest (11 paths):
`07d8edd3af8810cbe369899dfe23c413c2b25761e1438746ef33364543198b29`.
Source and test manifest (21 paths):
`2b843d64a6cfc2ad7c63294360cd99a38657a1bff5f2586ab7570f1d73aab4ab`.
Both independent reviewers reconciled these exact final manifests. Earlier hashes
are superseded. The final annotation describes the fresh DB-query helper as
`@phpstan-impure`; removing only that PHPDoc reproduces the tested runtime body.
No baseline, ignored error count or static-analysis guard changes.

- Main: full **1,335 frontend tests across 157 files**; lint **48 existing warnings**
  within the unchanged52 ratchet; typecheck **112 existing diagnostics**, none new;
  locale parity, all10guides, production build and whitespace pass. Existing bundle
  warnings and8stale typecheck baseline entries remain unchanged.
- Author: isolated SQLite **225 tests / 740 assertions** and Pint9PHPfiles pass.
  Final PHPDoc also passes Pint. Existing PHPUnit discovery deprecation remains.
- Independent QA: **144 frontend tests / 9 files**, actual PostgreSQL16.11
  **225 cases / 730 assertions** with5expectedSQLite-only malformed-storage skips,
  and forced two-connection contention **1 test / 16 assertions** pass. Only the
  owned network-isolated memory-backed PG container was stopped/auto-removed.
- Independent security: **152 frontend cases / 9 files**, **206 PHP / 671 assertions**,
  then **64 PHP / 189 assertions** for the final raw-DB variant pass. Final PHPDoc
  is independently proven comment-only. No unresolved findings. SEC-BANK-UI-1 and
  the synthetic same-instance reconciliation race were reproduced and fixed.
- The unguarded mounted bank page fails6of9deferred context probes; the guarded
  page passes9of9. Real Evolu+real owner proxy tests verify one synchronous mutation
  batch for the actual matcher and no queued writes after validation failure.
  This does not prove durable fake-worker commits or distributed uniqueness.
- All three OpenAPI documents and the existing validator test pass. All453runtime
  inventory records and every other selected source hash are unchanged; exactly
  the final parser hash is refreshed. The bank-list endpoint remains outside the
  curated initial OpenAPI scope; its additive recorded-direction field has an
  actual authenticated owner/denied-owner API regression.

Full-project PHPStan completes against the original level5configuration and
unchanged baseline/unmatched-ignore guard with **zero new findings**. Its sole
remaining error is the known missing local optional `Dedoc\Scramble\Scramble`
class in unchanged `DocumentationExporter.php:18`. Full generator verification
is unavailable for the same dependency reason; no installation, stub, ignore or
baseline weakening was used. CI on a future published branch is not inferred.
The broader real-MIME subprocess suite is not claimed passed: this minimal PHP
image has Node20and lacks the locked verifier dependencies. Credit/SEPA source
regressions pass; current-bank interoperability is still an operational check.

### Production observation and handoff

Owner-supplied metadata reports intake enabled,2email transactions created today,
latest creation `2026-11-03 17:14:08`, a healthy standalone worker and zero waiting,
delayed or reserved webhooks. The supplied failed-job listing contains June/July
entries. No IDs or payloads from that listing are retained. These are aggregate
observations and do not identify the selected company's exact transaction.

Git history independently shows address identity resolution introduced in
`bc075a65` on July13, while device synchronization retained the June17 first-row
bridge in `bf4c724a`. The source mismatch is reproduced and repaired; deployment
chronology and the selected company's recovery are not inferred from that history.

At completion of the implementation milestone, the candidate was **local and uncommitted** on
`fix/expense-defaults-bank-import`, based on mergedPR426/686c9304, in the retained
expense worktree. Primary source edits, previous records and recovery artifacts
are preserved. A future PR needs its own linked changelog fragment and applicable
publication authorization. There is no new PR/push, merge, package, deployment,
service restart, merchant-data modification or bank-mail/failed-job replay.

### Owner-authorized GitHub publication - 2026-11-03

The owner subsequently requests push of this reviewed candidate. Publication
scope is the exact36allowlisted paths:21reviewed source/tests,10guides,3shared
state documents, this review note and the API inventory. Runtime and source/test
manifest bytes remain unchanged. Main owns commit/push to the owned Satflux
branch and verifies the remote SHA; publication does not establish a new CI pass
or a deployed change. The PR-linked changelog note remains for a subsequent PR.
