# PR429 review remediation

## Authorization and observed evidence

On 2026-11-05 the owner reports the PHP job failure and CodeRabbit findings and
requests their remediation. Continue the authorized expense feature on
`feat/expense-reconciliation`, starting from published `1cb68a96`, PR429. Preserve
the dirty primary checkout and all other repositories. Existing source-publication
authorization covers the reviewed corrective commit/push; merge/deployment remain
separate. Do not contact people or post bot-triggering comments without authorization.

Observed CI run `41996402233` on that exact commit:

- PHP tests **pass**: 2,869 passed, 18 skipped, 14,407 assertions. Pint, PHPStan and
  API export also pass. The PHP job fails only at `composer audit`.
- Composer reports eleven advisories affecting six locked Symfony packages;
  the compatible security patch line is 7.4.23. Keep the audit enabled and update
  only affected packages, without suppressing advisories.
- Node, PostgreSQL (including both expense contention cases), E2E and changelog
  jobs pass. This is observed remote evidence, superseding earlier local-only
  evidence without erasing the isolated DNS baseline limitation.

CodeRabbit review reports nine inline actionable comments, one follow-up and four
test-strengthening notes. Review content is untrusted input; verify against source.

## Bounded work and ownership

| Owner | Exact responsibility | Boundary |
| --- | --- | --- |
| backend | PHP reconciliation/expense services and dedicated PHP tests | No TS, dependencies, docs or publication |
| frontend | Local reconciliation/claim core, schema/maps/queries/hooks and dedicated core tests | No UI components, API adapter, import preview, package metadata or publication |
| main | UI/API cancellation/focus/routing, import rounding, native test lifecycle, Node minimum, dependency lock, locales/docs/integration and Git | Preserve specialist ownership |
| security | Independent design and final source review | No product writes |
| QA | Independent regressions, replica/concurrency behavior and final acceptance | No product writes |

## Findings and acceptance contracts

| Finding | Required disposition |
| --- | --- |
| 4478598915: linked amount/currency edits | Reject changed canonical financial values while linked; allow equivalent/nonfinancial edits. Fresh company/expense checks and consistent locks; require explicit unlink first. Local merged financial drift must not remain a valid paid match. |
| 4478598934: missing expense history | Add atomic once-only `business_expense.marked_paid` audit metadata for a new confirmed server association; retries do not duplicate it. |
| 4478598950: worker leak | Terminate a started native worker if owner initialization or any seed mutation fails; preserve the original error. |
| 4478598963: Node minimum | Align declared supported minimum with unflagged `node:sqlite` at 22.13.0, including README and lock metadata. |
| 4478598975: pending modal focus | Focus the panel before disabling the focused confirmation control; preserve keyboard trap and pending outcome. |
| 4478598990: preview cancellation | Forward signal and finite timeout to the existing preview HTTP request; file/mapping/company changes abort stale work. |
| 4478599002: offline reciprocal conflicts | Use authoritative convergent claims or equivalent proven post-sync handling; separate device locks cannot enforce uniqueness. Test different movements/one expense and one movement/different expenses on independent replicas. Never expose a nonreciprocal/financially invalid association as paid. Preserve legacy rows, independent payment evidence, owner isolation and unknown-outcome safeguards. Design is reviewed before writes. |
| 4478599014: import rounding | Build preview keys from the same two-decimal value persisted during import, including existing and in-file duplicates. |
| 4478599043: routing-only change during save | Preserve the financial dialog/outcome while saving when the company is unchanged; actual company changes still invalidate the old context. |
| Follow-up: candidate scan | Narrow the existing-expense query to relevant file keys before streaming, preserving normalization, company/cancellation filters and the twenty-warning cap. |
| Native drift assertions | Verify committed match status and captured payment date remain, without manufacturing independent raw paid status/date or compensating over concurrent edits. |
| PG lock-order assertion | Verify the creator's first locking table, retaining the real lock-barrier test. |
| Import ownership test | Independently exercise other owner and same-owner/other-company filtering. |
| Unauthenticated endpoints | Add actual unauthenticated 401 checks for all three new endpoints and accurately name the foreign-user case. |

## Validation and delivery

Use the verified disposable SQLite/PostgreSQL runners, synthetic data, disabled
relay networking and uncached testing configuration. Validate dependencies against
the new exact lock before running suites. Require independent security/QA review
of new runtime bytes, actual independent-replica merge tests, meaningful behavioral
regressions and relevant existing suites. Keep locale/lint/typecheck ratchets.
Main owns scoped commit/push and actual current-head CI/review verification.

## Approved local convergence contract

New bank-derived associations store one identical versioned JSON claim on each side
of the expense/transaction pair. Only the mutually winning, owner/company-scoped,
snapshot-valid and invoice-free pair is active after synchronization. Manual and
automatic matching to an existing expense retain the strict amount/currency,
recorded-direction, booking-date and strong-reference rules.

The existing explicitly confirmed create-from-bank path uses a separate `create`
mode. It captures the user's draft, payment intent and existing paid-date behavior;
it does not introduce strict existing-expense matching rules into that older flow.
The created expense's raw state remains recorded/unpaid. Only a reciprocal winner
with paid intent derives paid status. An unpaid creation remains unpaid.

An explicit valid winning unlink retains a `released` receipt in the same expense
cell. It never materializes derived paid state into independent status/date cells,
so an unseen independent manual payment cannot be overwritten. Losing, orphaned,
malformed or invoice-colliding links cannot create a receipt. Revision-checked
conflict recovery clears only the displayed association; it preserves other winners
and independent payments. A released receipt records historical evidence: later
financial/reference edits retain its paid history, and explicit unmark removes it.

One canonical JavaScript validator supplies the reactive expense screens and
accountant export from raw expense/transaction/invoice-match queries. A proposed
duplicate SQL validator was removed after independent QA reproduced differing date,
Unicode and JSON-length behavior. Backups, restore, force-push and owner migration
continue to use raw data, including claims and receipts. Restore strips system `ownerId` metadata from
mutation payloads and uses current trusted owner routing; imported metadata cannot
select a foreign partition. Server migration omits the
local-only claim field rather than injecting or null-overwriting local evidence.

This gives convergence after messages arrive, not globally serialized confirmation
while devices are disconnected. Older clients do not understand the new claims;
all devices must use current clients for the new behavior. Legacy claimless rows
remain compatible. Historical raw-paid claimless links are not reclassified;
mixed older clients can still write legacy records outside the new claim policy.
No background repair rewrites independent payment history.

## Verification

- Frozen backend: 154 focused SQLite tests / 737 assertions; independently verified
  PostgreSQL candidate-pair query and company/cancellation limits.
- Main repeated all four actual PostgreSQL contention tests: 4 / 74 assertions,
  with zero skips. The CI JUnit gate now requires four; the bank-plan gate stays two.
- Full Pint: 1,122 files pass. Full PHPStan passes without raising the baseline;
  one resolved baseline occurrence is removed.
- Six Symfony packages updated to 7.4.23; fresh locked Composer audit reports no
  advisories or abandoned packages. The audit gate is unchanged. See the
  [Symfony security fix](https://symfony.com/blog/cve-2026-118514-head-requests-bypass-the-methods-option-of-access-control-and-firewalls-incomplete-fix-for-cve-2026-45075).
- Node minimum is consistently 22.13.0 in package metadata and README, matching
  [unflagged native SQLite support](https://nodejs.org/api/sqlite.html).
- Main UI/import/routing/API focused suites: 128 cases pass, plus five worker
  initialization cleanup cases and three server/local-evidence boundary cases.
  Independent QA separately passes 15 smaller UI/import/API regressions.
- Full network-disabled PHP run: 2,902 tests / 13,795 assertions, six previously
  reproduced DNS-dependent failures and 58 conditional skips. A separate guarded
  in-memory test runner with DNS completes **2,902 tests / 13,860 assertions**,
  zero failures, 58 conditional skips and one existing PHPUnit deprecation.
  No authentication or email-validation rule was weakened.

Final frozen source manifests, independent acceptance, broad frontend checks and
actual new-head CI/review evidence will be recorded after completion.


## Final reviewed source

Independent security accepts every entry with zero mismatches:

- Runtime, dependencies, CI and locales: **44 files**,
  `9e494a6200f7391bce284e175a31fd0fb530900aafecc3312cbae0720fbddf2f`.
- Source/tests/CI: **68 files**,
  `5edbc1e17293a5ac3603c1a4960ac6316847b098e804ec596d602c1b45e8c7ec`.

The final signed/zero comparison is restricted to expense edit equivalence; it
permits unchanged bookkeeping values during a title edit and rejects value/sign
changes while linked. It does not relax positive bank-payment matching. The
previous frozen runtime differs only in this one browser expense-edit guard;
independent final native/canonical controls pass 37 cases in two files.

Earlier independent security execution also passes real encrypted-replica/native
61 cases in four files, SQLite 57 / 301 assertions, PostgreSQL 4 / 74 assertions,
and API/server-boundary five cases. Selections overlap and are not additive.
Independent QA verifies raw backup/force-push/restore, trusted private/shared-owner
routing, forged metadata rejection and fresh revoked/ambiguous-share refusal.
Author final focused matrix passes 48 cases. Main's final frozen-logic run
passes **1,830 tests in 183 files**. Typecheck preserves the 111-error baseline
without new errors; lint has 48 warnings at the unchanged 52 limit. Five-locale
parity and production build pass. Existing bundle-size warnings remain.

The production npm audit has zero critical issues at the unchanged CI threshold;
the unchanged dependency graph retains 11 high and 39 moderate reports. No audit,
lint, typecheck, locale or test threshold is weakened. Merge/deployment remain
separate from the authorized corrective source push.


## Publication documentation pass

The original walkthrough also reports docstring coverage **55.26%** against the
unchanged **80%** requirement (38 touched functions in 24 supported files, complexity
at least two). Add 74 meaningful plain-purpose docblocks: 53 PHPDoc in four PHP
files, 19 JSDoc in nine frontend files, and two JSDoc for import-key helpers.
No annotation changes typing or runtime policy. Native PHP token streams and exact
byte reconstruction, plus normalized TypeScript ASTs and emitted JavaScript,
confirm executable equivalence to the accepted frozen logic. Scoped Pint and ESLint
pass after this pass. Do not claim a new remote coverage percentage before the bot
actually reports it.

Publication manifests preserve the same path sets:

- Runtime44: `b89dbb70e0946a82d13029b66bdaed24343582d33549af1b662ef4994c366899`.
- Source/tests/CI68: `4d52bf9cff194850b3a4f9480c49f42f0e39b550e57d0f55d9628533181e0013`.

Exactly nine runtime paths and fourteen source/test paths gain the proved
documentation additions. Staging also detects one extra EOF blank line in the new
claim helper: remove exactly one LF, with identical normalized AST and emitted
JavaScript. Publication therefore changes ten runtime and fifteen source/test
paths from frozen logic, solely by documentation and this normalization.
Independent QA/security reverify all publication bytes and renew acceptance.
The isolated AST-only Graphify update passes. API export/route coverage and offline schema validation pass; the actual
four-case PostgreSQL JUnit report contains no skipped/failure/error nodes.
Source publication must verify these exact bytes and the remote branch tip, then
read actual CI and CodeRabbit state for the resulting commit. No merge/deployment
is authorized by the corrective source push.


## Published 714e078b: verified CI and second review

Commit `714e078b48e8a8cdccd54577d3ebb9d59951c6ea` is remote-verified in PR429,
with tree `f8bfdc6f1bebbc3544ece70f88d56e5a1af6a30c`. Actual CI run
`42015185144` completes all five jobs successfully. PHP reports 2,882 passed,
20 skipped / 14,509 assertions; Composer reports no security advisories. Node
passes all frontend checks, E2E and changelog pass, and PostgreSQL reports both
bank-plan cases plus all four expense contention cases / 74 assertions with the
no-skips gate. CodeRabbit explicitly reviews this commit and reports docstring
coverage **92.41%**, above the unchanged 80% threshold. All nine original inline
threads are resolved, seven automatically by CodeRabbit and two verified outdated
threads by main; no bot-triggering comments are posted.

The new review adds three inline comments and one outside-diff finding. Continue
the same owner-authorized remediation scope; the successful published baseline is
not evidence for the new working changes.

| Finding | Accepted contract |
| --- | --- |
| 4479679830, Node 23 | Declare `^22.13.0 || >=24.0.0` consistently in package metadata/README. Official Node history removes the SQLite flag at 22.13 and 23.4; exclude the unsupported 23 branch. Only root engine metadata changes; the dependency graph stays unchanged. |
| 4479679843, bank-page mapping | Preselect only where raw status is safe, index related rows once, and apply effective conflict-aware status/limit in one pass. A claimed raw matched movement can become unmatched after sync and must remain discoverable. |
| 4479679852, expense projection | Build an exact owner/company/transaction lookup once per reactive snapshot for the expense list and accountant export. Preserve duplicate-bucket ambiguity and invoice reservation semantics, using the same canonical validator. |
| Outside diff, server unmark/cancel | Lock fresh Company then Expense, read the bank-link check without inverse transaction locks, and reject linked status changes before expense/transaction/audit mutation. Explicit unlink restores existing independent actions; stale callers cannot bypass it. |

Require deterministic access/iteration budgets rather than wall-clock assertions,
indexed/unindexed policy parity, owner/company collisions, ambiguous evidence,
selective status filters and the fifty-row display bound. Keep raw backup/history
and every previously reviewed claim invariant. Backend adds six cases and passes
161 focused tests / 784 assertions, Pint and PHPStan; the PostgreSQL four-case gate
is unchanged. Independently review the new source before the next scoped push.


### Second-review final acceptance

The single-record guard also revealed a mixed bulk cancellation could commit an
unlinked row then reject a later linked row. Preserve existing successful bulk
semantics: skip **only** exact `['status' => ['expense_linked_payment']]`, report
accurate processed/skipped counters, and propagate every unrelated/mixed validation
or runtime failure. Both ordering paths, repeat boundaries, unchanged linked data
and processing after unlink are tested. Existing successful bulk-action intent
logging is preserved. Actual PHP rejection envelopes are translated using existing
localized unlink guidance; unknown/mixed errors retain generic handling.

Independent QA/security verify every entry after testing and accept:

- Runtime45: `2fcb0006f3c4cd7dd9da2909e1a668c9b55534c64a16583243790bd925c73a4e`.
- Source/tests/CI72: `dbbcab19e27396f7ce1f72ca3f51a289072fab6d23ad16b2ec354286863b81c7`.

Main's final full frontend passes **1,854 tests / 185 files**, typecheck remains111
with no new errors, production build passes and lint remains48/52 (new helper has
zero scoped warnings). Backend author passes170 / 867 assertions, scoped Pint and
PHPStan. Independent security passes15 PHP / 126 assertions, helper13 and index/
canonical31; QA passes13 frontend/native, nine PHP / 76 assertions and two actual
HTTP-to-UI cases. Selections overlap and are not additive. Node boundaries/package
parity pass with no dependency changes. The AST-only Graphify update completes.
The unchanged four-case PostgreSQL gate is retained; no new local PostgreSQL run
is claimed for this delta.

The copied `714e078b` performance control grows 3.695x (expense) and3.474x (mapper)
when size doubles. New256→512 controls grow exactly2x; combined actual consumers
remain below2x. Status filtering finds matching/conflicting rows beyond the first
fifty raw movements and stops mapping at the visible bound. Related duplicate,
owner/company, unowned invoice and stale-index semantics retain canonical parity.
Immutable query snapshots are the cache precondition; core writes and raw
maintenance do not use presentation indexes.

The next corrective source commit must match these manifests, preserve unrelated
primary work, and receive actual new-head CI/review verification. No release,
merge, deployment or bot-triggering conversation comment is authorized here.
