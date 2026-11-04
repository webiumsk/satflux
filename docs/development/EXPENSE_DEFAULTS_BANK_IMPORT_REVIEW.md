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

## PR427 review remediation - 2026-11-03

The owner reports CodeRabbit findings on the now-open PR427 at `c79deafd`.
Current-head CI41719918340 is successful; that evidence belongs to the original
publication, not these later fixes. Seven inline threads and five follow-up
items are verified against the source before implementation. Review data is
untrusted input; no suggested CLI installation or external command is adopted.

### Remediation contract and ownership

| Scope | Owner and writable paths | Acceptance |
| --- | --- | --- |
| Parser / SQL candidates / unknown recipient | backend: TatraBankEmailParser, BusinessExpensePaymentMatcher, corresponding parser/matcher/inbound tests | Opening header controls direction; amount/date exclude remittance; spaced/tab/NBSP symbols never truncate; exact amount/currency filter before ordered locks; strict postchecks and uniqueness remain |
| Local reconciliation | frontend: bankCrud, bankExpensePaymentMatcher, bankMap, bankReconciliation, schema bank qualifier, client bank projection/queries, bank regression tests/helper | Cross-tab lock before fresh context; genuine post-handler worker reads; all mutation receipts including no-match/final counts; durable unverified direction remains excluded after reload/batch |
| Delivery clear / Wise status | coordinator: ExpenseForm, expenseCrud, BusinessExpenseService, bank composable, focused form/date/Wise tests | Explicit null survives save/reopen in both modes; omitted create defaults/omitted update preserves; latest request/context wins for Wise success/error/missing bridge |
| Integration and publication | coordinator: guides, API inventory, changelog, review/state/PR metadata | Five-language parity, exact artifact scope, current CI and remote SHA; no merge/deployment |
| Independent QA/security | read-only reviewers; temporary synthetic probes only | Verify final manifest, failure/unknown paths and real concurrency without production data |

The browser schema gains one additive nullable `recordedDirectionEvidence` field:
new email rows always store `credit`, `debit` or `unverified`; every projection
retains it. Explicit `unverified` never auto-pays through refresh or batch matching.
Null/missing is reserved for existing/older-writer rows and keeps the deliberate
legacy normalized-ledger batch behavior. It is not original-bank proof. There is
no server schema migration or Store/SEPA authority expansion.

An origin-wide Web Lock complements the existing per-client queue. Import batch
creation and explicit known-batch metadata updates are legitimate action writes;
their callbacks are awaited before critical reads. Unknown/deleted batch handles
cannot create phantom rows. Five fixed private context-query variants remain
unsubscribed: each legitimate mutation flush invalidates their cached promises,
so subsequent worker query responses run after the prior mutation handler and
SQLite COMMIT. There is no per-operation nonce or unbounded query-cache growth.

Installed Evolu posts its onComplete message before the SQLite transaction
returns. That callback alone is not commit proof. Matching keeps the bank and
expense writes synchronous in one validated owner batch, then verifies their
stored state through the private worker queries before changing context/counts.
No-match imports and final batch counts receive the same completion checks.

Timeout, worker error or missing proof is UNKNOWN. An entered operation rejects
to its public caller and permanently quarantines its internal gate/Web Lock until
the tab is reloaded/closed. A late pre-COMMIT callback cannot release quarantine.
Queued operations have bounded pre-entry cancellation and cannot mutate after
cancellation. There is no automatic replay or uncertain rollback claim. Missing
or rejected Web Locks preserve basic import while disabling automatic expense
matching. Other devices remain outside the same-browser lock guarantee.

Explicit delivery null now means clear; omission defaults only on create and
preserves storage on update. This replaces the earlier documented blank-date
save fallback. User guides and the single PR427 changelog fragment follow the
new final behavior. Final review manifests/check results are appended after freeze.


### Review disposition and compatibility

| Review item | Final behavior and regression evidence |
| --- | --- |
| 4460664772 | Only the anchored bank opening controls debit/credit direction; quoted remittance words cannot suppress a valid classic credit. |
| 4460664781 | Compact amount/date qualification ends before remittance/counterparty fields; a date found only in remittance cannot qualify a debit. |
| 4460664792 | Space, tab, NBSP and dot-plus-numeric VS continuations reject instead of truncating. A complete bank date after sentence punctuation remains supported. |
| 4460664802 | A fixed origin-wide Web Lock is acquired before critical context reads; two actual clients with warmed caches match one expense only once. |
| 4460664809 | Pre-COMMIT callbacks are followed by private worker-backed reads; real SQLite COMMIT failure rejects and keeps the gate quarantined. |
| 4460664827 | The additive stored direction qualifier survives refresh and explicit batch matching; new unverified/credit rows cannot pay an expense. |
| 4460664845 | Explicit cleared delivery dates remain null through server/local save, reopen and table display; omitted updates keep existing values. |
| Wise local/server follow-ups | Request generations reject stale success/error/A-B-A responses and clear status on company changes. A shared bridge-lookup generation prevents an older bank refresh from overwriting current Wise availability. |
| SQL-lock follow-up | Exact canonical amount and trimmed uppercase currency narrow candidates before ordered locks. Independent two-connection tests prove unrelated amounts/currencies do not wait behind the match. |
| Real-matcher failure follow-up | The actual second expense write fails Evolu validation after successful prevalidation: no worker financial mutation is sent and context/counts remain unchanged. |
| Unknown-token follow-up | A well-formed but absent recipient token reaches the real inbound service and fails lookup; malformed addresses remain separately rejected. |

Independent security review additionally found a late-callback quarantine release
and dot-plus-space VS truncation. Both are fixed with regressions. No unresolved
product/security tradeoff is introduced by these changes.

The same-browser lock guarantee requires updated cooperating clients. Reload old
tabs when rolling out the new frontend; older writers and other devices cannot
participate in this lock or supply the new qualifier. Existing null-qualified rows
keep the documented explicit legacy batch behavior. No distributed uniqueness or
original-message proof is claimed. A worker outcome that remains unknown requires
closing/reloading the owning tab before another fresh matching attempt; there is
no automatic replay of that operation.

Independent temporary actual-worker probes use two real Evolu client instances,
the installed Db/CRDT/SQLite transaction code and native in-memory SQLite. Realm,
lock and broadcast adapters are synthetic; these are not full browser/OPFS E2E
claims. They explicitly exercise warmed subscribed/private caches, fresh worker
queries, real validation rejection, COMMIT failure and missing/late receipts.


### Final independent review and checks

Both independent reviewers accept the full PR artifact relative to merged
`686c9304`. Runtime17 manifest is
`78afb3fdf73ad9f6b30f537684f711ef2914ece59c46f21845096a4aa0e04eb0`;
source/test31 manifest is
`d52b3e003a8466d669a64ed092af03a8a616843f36dbec024a1bc8972610e686`.
Each manifest hashes lexically sorted UTF-8 lines containing the file SHA256,
two spaces, repository-relative path and LF. No unresolved QA/security findings
remain. Reviewer probes did not write repository files or touch production.

- Main frontend: 1,363 cases / 158 files pass; final Wise shared-bridge scope
  separately passes19 after its last change. Full lint has48 existing warnings
  and no errors; final typecheck has112 baseline findings and no new fingerprint.
- Main disposable SQLite:237 cases /958 assertions pass, one existing PHPUnit
  deprecation. Pint passes all8 modified PHP files.
- Independent QA PostgreSQL:251 cases /805 assertions pass with5 expected
  SQLite-only malformed-storage skips and one existing deprecation; the three
  new delivery-null API cases are included. The older full expense fixture's
  nested Read Committed snapshot limitation is not reported as a new pass.
- Independent real PostgreSQL contention/narrow-lock cases:3 /42 pass. The old
  matcher overlay blocks unrelated amounts and fails the negative control.
- QA focused frontend126 and final bridge19 pass. Security independently passes
  frontend179, final bridge19, backend/date166 /736 and final parser84 /278.
  These corpora overlap and are not added together.
- Both reviewers independently pass12 temporary actual Evolu/native SQLite
  probes, including two-client warm caches and actual COMMIT failure. Their
  adapter boundary is described above; they do not establish OPFS/browser E2E.
- Final build, five-locale parity, three OpenAPI documents and validator test
  pass. All453 route inventory records and other source hashes are unchanged;
  only the parser hash is refreshed. The one PR427 changelog fragment validates.
- Full local PHPStan completes with only the known optional missing Scramble
  class in unchanged DocumentationExporter.php:18. There is no new diagnostic,
  installation, ignore, baseline or warning-budget change. Fresh locked CI must
  verify the published commit; old c79deafd CI is not reused as that evidence.

The QA-owned network-isolated temporary PostgreSQL container and tmpfs were
removed after review. Existing primary edits and all recovery artifacts remain.
Main publication is limited to this reviewed branch. No merge, deployment,
merchant-data change, historical job retry or bank-message replay is performed.


## PR427 second review remediation - 2026-11-03

The owner asks for additional CodeRabbit findings. The completed incremental
review of `06c837b3` adds four inline findings and three follow-ups. That commit's
CI41729050967 passed all four jobs; it does not validate the changes below.
Graphify remains unavailable because the local graph is absent and no graph tool
is exposed; no graph rebuild or tool installation is performed.

### Scoped contract and disposition

| Finding | Owner / bounded files | Required behavior |
| --- | --- | --- |
| 4461201972, compact date suffix | backend / parser and two parser test files | Complete compact debit date token with/without VS; malformed time cannot fall back to a date; malformed plus valid date remains ambiguous and rejects |
| Follow-up18bcf517, compact credit amount | backend / same parser/tests | Amount and currency come from bank-authored prefix; remittance-only amounts cannot qualify a credit |
| 4461201992, long derived VS | coordinator / ExpenseForm and mounted form tests, five import guides | At most32 derived digits including leading zeros; overlong automatic value is blank, number retained, shortened number resumes default, manual overrides stay intact |
| 4461202003, Spanish direction wording | coordinator / Spanish bank guide | Say recorded movement direction (cargo/abono), avoiding the payment-address ambiguity |
| 4461202025, batch error visibility | coordinator / CompanyPayments, mounted page tests, five locales/guides | Catch rejected batch matching; localized busy/unknown/generic errors, no silent failure or automatic retry; keep success refresh |
| Follow-up170a9eee, import reads | frontend / bankCrud and bank regression tests | Prevalidate/dedupe prepared rows, one synchronous row batch and one post-handler private-context read, verify each actual id/company/hash before counts/matching |
| Follow-up82f95bd4, lock regression | frontend / concurrency test | Assert the fixed literal lock name and exclusive options so a changed name cannot pass the test |
| Independent acceptance | read-only QA and security | Verify exact frozen bytes, rejection/unknown invariants, real worker boundaries and UI/parser behavior using only synthetic disposable probes |

Only validated rows reserve hashes in the current-import set. Invalid rows remain
skipped individually; a later valid row with the same hash is eligible. An empty
prepared group never waits for a nonexistent row receipt. Every actual insert is
queued synchronously without an await; a real Result-false cancels that entire
Evolu microtask batch, aborts the import explicitly and publishes no row counts or
expense matches. Initial import metadata can remain with zero counts. There is
no automatic retry. Thrown/unknown worker outcomes retain the existing permanent
quarantine. The final legitimate metadata receipt and private fresh read remain.

The five fixed private query identities, origin lock, owner scoping, direction
qualifier, financial matching rules and previous mixed-client/device limitations
are unchanged. A Map verifies stored row identities without a quadratic scan.
The actual-Evolu mixed five-input fixture imports two rows and skips three with
three context reads (15 query loads, five keys), including initial/final metadata
barriers. This measures query work; no production backlog timing is inferred.

UI messages distinguish a busy operation, an unknown result requiring tab reload,
a known rejected import and a generic matching failure. All five locales and
matching guides carry the final behavior. Known internal reconciliation codes are
mapped to localized messages; no new payment payload or raw worker error is shown.
Final manifests, independent reports and completed gates follow after review.


### Second-round frozen artifact and independent acceptance

Both independent reviewers verify every listed file and accept runtime22 manifest
`4b236b10f2ff2bd403a8287599f0594707a573ff495ab6cfd4e678357c2a7eb1`
and source/test36 manifest
`b33ad44a7e63e15235aded39654cdeb025d9c0533d3701febfdc898f35b19975`.
The manifest format is unchanged. No unresolved QA/security finding remains.
Only four previous runtime paths and five locale files change this round; the
prior browser schema, lock/query module and SQL matcher remain byte-identical.
The previous Compose delta is unchanged and outside this follow-up.

- Main full frontend:1,382 cases /158 files pass on frozen bytes. Scoped author
  bank92 and coordinator mounted UI39 pass; full typecheck retains112 existing
  findings with no new fingerprint, full lint has48 existing warnings/no errors.
- Backend author disposable SQLite262 /1,018 and Pint8 pass. Independent QA
  SQLite305 /1,160 and security177 /539 pass with one inherited deprecation.
  These corpora overlap. No PostgreSQL target is created for this round.
- Independent QA frontend131 /5 files and security bank92 plus UI39 pass.
- Each reviewer passes15 actual Evolu/native SQLite probes, including three new
  multirow/known-abort cases. The installed worker/transaction code is real;
  asynchronous realm, broadcast and lock adapters remain synthetic. No browser,
  OPFS or cross-device uniqueness claim is inferred.
- Production build, five-locale parity, all three OpenAPI documents, validator
  test and updated PR427 fragment pass. Structural inventory comparison proves
  the parser hash is its only change; all453 route records remain unchanged.
- Full local PHPStan has only the inherited optional missing Scramble class in
  unchanged DocumentationExporter.php:18. No dependency, ignore, baseline,
  warning budget or permission setting changes. New locked-dependency CI must
  verify the new published head; earlier06c837b3 success remains historical.

Main publishes only the32 reviewed follow-up paths, verifies committed manifests
and remote SHA, updates PR evidence and resolves the four addressed threads.
The primary checkout and previous recovery artifacts remain preserved. No real
bank message, production data, job retry, merge or deployment is used/performed.


## PR427 third review remediation - 2026-11-04

A later completed CodeRabbit review of3f47a24b reports three inline findings and
one outside-diff follow-up. The initial rate-limited check and green
CI41733930901 are historical observations; they are not new-source validation.
Graphify graph/tool remain unavailable; no installation or rebuild occurs.

### Accepted architecture and ownership

| Finding | Owner / scope | Acceptance contract |
| --- | --- | --- |
| 4461780133 | backend / Tatra parser and two parser test files | Preserve layout whitespace in HTML-only bodies without splitting inline numeric tokens or relaxing date/symbol/remittance qualification |
| 4461780146 | frontend / expense CRUD/import/bulk, bank compound creation/matcher, company delete/merge, snapshot and sharing maintenance | Same origin lock spans fresh preconditions, actual critical writes and post-handler proof; every production matching-critical expense writer cooperates |
| 4461780158 | coordinator / CompanyPayments, local bank composable and page tests | Match failure and refresh failure have separate scopes/messages; a saved successful match is not reported failed because refresh rejects |
| Outside-diff218c8a3e | coordinator / batch map, projection and UI/tests | Local batch action availability derives from actual same-company persisted unmatched rows, including verified counter0 after an interrupted import; never move counters before identity proof |
| Review and publication | read-only QA/security, coordinator shared docs/metadata/Git | Verify exact artifacts, real worker ordering/unknown behavior and current CI; preserve primary work; no merge/deployment |

The expense writer inventory includes stable-ID inbox upserts, spreadsheet and
bulk actions, bank expense creation, company delete/merge, snapshot restoration,
share migration/rekey/recovery and ordinary form/detail edits. Note saving uses
only its intended note field. Public writer results become awaited; callers retain
context-generation checks before navigation, attachments or success feedback.

Installed Evolu exposes no public forced refresh for warmed query keys. A small
inert `_expenseReconciliation` local-only coordination table with one fixed opaque
row supplies a genuine entry marker mutation before private worker reads. It has
no company/merchant/financial fields, stays AppOwner-only and is excluded from
explicit backup/share/relay data allowlists. Installed Evolu excludes underscore
local-only changes from CRDT sync. This is a deliberate additive browser-schema
exception needed for fresh writer preconditions, with no server migration or
payment authority expansion.

The five existing private financial query identities remain unchanged. Bounded
additional private, unsubscribed variants support only the needed maintenance/
share/tombstone decisions, without nonce keys or unbounded cache growth. Ordinary
writes must not load unrelated attachment/snapshot/stock datasets. Owner/share,
number allocation, stable-ID existence and target liveness decisions use fresh
state inside the gate. Terminal receipts and worker reads prove the completed
handler before return/release. TypeScript brands alone are insufficient: opaque
already-locked capabilities require private runtime validity and revocation.
Compound create/link and bulk groups preserve synchronous mutation batching;
known validation rejection remains distinct from Unknown and no nonexistent
callback is awaited. Unknown retains permanent quarantine and never auto-retries.

Expense writers require an origin lock and fail before any marker/financial
mutation if it is unavailable. The existing basic bank-import fallback remains,
with automatic expense payment disabled. Updated cooperating clients in the same
browser are the guarantee boundary; old tabs and other devices remain outside it.
Reload older tabs at rollout. The additive local-only marker needs no backup,
relay or server migration; the existing encrypted expense data remains intact.

Ordering is intentional: an edit to20 committed before a debit19 prevents that
match. A valid debit19 committed first may be followed by an explicit authorized
edit to20 that keeps the existing paid status. Preserve that editable-paid policy
and original bank evidence; do not invent unpayment, unlinking, compensation or
paid-expense immutability. Matching postproof additionally checks the original
critical amount/currency/hint snapshot as defense.

Five locales/guides describe visible busy/unsupported/unknown recovery and saved
match refresh failure. Server batch behavior remains unchanged; the derived local
flag is presentation/recovery information, not payment authority. Final source
manifests, independent acceptance and completed checks follow after freeze.


The inherited unlocked basic-import fallback retains its previous owner-registry
routing; the new fresh-owner proof applies to the locked cooperating scopes only.
The fallback cannot auto-pay an expense and does not acquire a new privacy/owner
guarantee in this change. No silent change to that previous fallback is claimed.

Independent review required bank Ignore/manual-match/unmatch eligibility writers
to use the same gate and fresh state. Their callers await completion and separate
saved-action refresh failures from mutation errors. Existing unlink-without-unpay
semantics remain. Fresh parent ownership also routes new bank-match/attachment
children, avoiding a stale-registry AppOwner fork. Missing/unready/ambiguous owner
state fails before a financial mutation, without installing keys or registering
new owners. A known missing-company bank-import preflight occurs outside the
mutation receipt catcher, so a proven no-financial-write refusal is not incorrectly
classified as Unknown.

Review-discovered fixes include private runtime lease revocation, bounded owner
initialization, cancelled-note rejection, pre-gate rejection of non-email debit
matching, selective auxiliary reads, owner/parent routing, and correct known-abort
classification. Expected in-memory patches from an aborted synchronous group are
discarded; previously committed groups remain. Existing known bank row rejection
keeps its localized error, and a rejected expense pair leaves the imported bank row
unmatched instead of undoing earlier valid matches. There is no financial rollback.

One unchanged drawer binding exposed a fragile existing TypeScript fingerprint
after the schema expansion: an object class was passed to an icon's string class
prop. The equivalent conditional string preserves the exact CSS behavior and
removes that diagnostic; no baseline, warning budget or type assertion is changed.
Current full typecheck tolerates111 existing findings with no new fingerprint.


### Third-round artifact and independent evidence

Final runtime41 manifest is
`a9478719b0e67ac46526bbd8e3db03657c935b656944ac2a37401aac02d76ee7`;
source/test65 manifest is
`5f74ae7b795c4c3786cf4f3123855e64aa186a9a9e725a8535d8da244dd471c1`.
The manifest format remains lexically sorted repository-relative SHA256 lines.

Main full frontend passes1,415 cases /160 files after fixing three test-adapter
mismatches without weakening financial assertions. The final additional pure
cross-owner merge preflight is separately verified by3 cases and the affected35
cases, with zero merchant prefix mutations for invalid groups and a same-owner
positive. Current full typecheck has111 existing findings/no new fingerprint;
full lint48known and final scoped lint0 preserve budgets/baselines. The build,
five locales, all3 OpenAPI documents, validator and existing PR fragment pass.
The API inventory changes only the parser hash, keeping all453 route records.
Author HTML/parser SQLite280/1145 and Pint8 pass. Local full PHPStan retains only
the known optional missing Scramble class in the unchanged exporter.

Independent QA passes311 repository cases /21 files, native40 /5 and isolated
SQLite323 /1287 (one inherited PHPUnit deprecation). Independent security passes
104 repository cases, SQLite180 /641 and50 distinct native/security cases, with
the repaired classification case rerun and10 boundary cases rechecked. These
corpora overlap. Both reviewers verified the earlier40 runtime files; the final
company-merge preflight and renewed manifests receive separate bounded review.
No PostgreSQL target or infrastructure is created this round.

Native tests use two installed Evolu clients and actual DbWorker/CRDT/SQLite code,
including a genuine worker-thread COMMIT pause with both clients responsive.
Realm/tab/lock adapters and storage remain synthetic; this does not establish
browser/OPFS or cross-device uniqueness. Cases prove edit-v-match order, stale
caches, owner routing, revocation, known abort recovery, unknown quarantine,
stable IDs/numbering, Ignore, compound bank creation, deletion and restoration.
Known failures retain earlier valid matches and permit explicit later recovery.

The final company-merge delta validates EVERY resolved duplicate group before
any mutation. No-expense and invalid-later groups cannot leave document/contact
prefix changes. Main publishes only the reviewed allowlist after both renewed
acceptances, verifies committed blobs/remote SHA and requires new locked CI;
CI41733930901 belongs to3f47a24b. No merge, deployment or real bank-message replay.
