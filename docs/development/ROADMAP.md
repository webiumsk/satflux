# Development roadmap

## Reviewed text-PDF expense extraction - 2026-11-18

The authorized Satflux-only implementation is complete on
`feat/expense-text-pdf-extraction`, based on merged PR455 at `2ffd3465`.
Readable text PDFs now produce reviewable sparse drafts before Save; embedded
ISDOC retains priority. Detail extraction needs explicit preview approval, and
repeated partial drafts preserve coherent supplier/account pairs. All five locales
and guides are updated. Main and independent QA/security checks accept the final
source; see [evidence and validation limits](EXPENSE_TEXT_PDF_EXTRACTION.md).
Next is reviewed branch publication, new-head CI and owner PR review. OCR, external
AI, pricing changes, merge and deployment are outside this accepted milestone.

## PR455 review corrections accepted - 2026-11-17

All three supplied findings reproduce on `0ec7e835` and are corrected: normalized
payment-note evidence with the original-note veto retained, detail pending-file
selection/removal and exact retry identity, and consent guards while extraction
persistence is active. Only four source/test paths change. Main363 JS tests pass;
independent QA136 and security178 plus final72 overlapping tests pass. Both
reviewers accept delta4 aggregate
`d25b336408ee491b38f3765246982bf8e14f390ca0e8bdf80d27109a61408baf`
and complete23 aggregate
`eab959184516561507d6be2789a7601661e69260ab80765921582f6a2bd14d22`.
Full/scoped lint, unchanged107-error typecheck baseline, locale parity and
production build pass. No ratchet is relaxed.

PR455 is open; original-head CI44130077621 is successful. Correction-head CI is not
yet claimed. Main will publish the reviewed correction to the same authorized
branch; primary unrelated edits remain preserved. No merge/deployment occurs.
[Finding disposition and evidence](EXPENSE_EDIT_QR_ISDOC.md).

## Expense edit QR and ISDOC follow-up - 2026-11-17

PR454 is merged at verified master `422bdd06`. The authorized follow-up restores
an explicit recovery route for legacy payment-review blocks and makes immediate
attachment checking visible, retryable and separate from saving. Implementation
and main checks pass on `fix/expense-edit-qr-isdoc`; independent security accepts
the frozen source and five guides. Independent QA also accepts the exact scope,
with22 native Chrome scenarios,328 JS tests and90 PHP tests passing. Publish the
reviewed branch, verify new-head CI and obtain owner PR review; merge/deployment
remain separate approvals. See
[acceptance contract and evidence](EXPENSE_EDIT_QR_ISDOC.md).

## PR454 currency and form-VS follow-up accepted - 2026-11-17

Both subsequent owner findings reproduce on `a7fa749f` and are corrected. Company
currency defaults now ignore unrelated form edits while preserving explicit select
or extraction choices. Missing/null extracted VS retains the form value; explicit
empty/new values apply, and unconfirmed retained references still veto payment QR.
Generic source-prefill revision and company/owner checks remain intact.

Independent QA/security accept two-path aggregate
`37fc0d218420e06f7e85cc307f4a9d4e8acdee20c52260775a2bb88ac92730c2`
and complete53-source aggregate
`ef3996ca437df43157fe07c306035e9769c3da4fbea250bf29358f9eccc9f6e7`.
Main224 JS tests/four suites, independent QA219/three suites and10 real Chrome
scenarios, security181/four suites, scoped lint, unchanged107-error typecheck
baseline and production build pass. PHP and bank-matching code are unchanged.

Prior-head CI44091941296 is fully successful; new correction-head CI must be
verified after publication. Both review findings are fixed; no embedded review CLI
or bot command, installation, merge or deployment. Primary unrelated work remains
preserved; the correction stays on the same owner-authorized feature branch.
See [follow-up evidence](PR454_REVIEW_FIXES.md).

## PR454 inline review corrections accepted - 2026-11-17

The owner requests six inline finding fixes against published `7d19dbff` on
`feat/expense-supplier-credit-notes`. All six are verified and corrected. Actual
GitHub PR454 is open against master `eee6cd01`; its CI44085701876 passed on the
prior head only. No new correction-head CI result is claimed before publication.

Signed advisory duplicates work in both storage modes; bank matching remains
positive-only. Copies preserve supplier snapshots while dropping non-live contact
links and retaining live company/owner checks. Supplier mismatches now require
review in either event order and detail extraction, superseding the former explicit
contact exception. User edits invalidate delayed source/default-currency prefill.
Sparse extraction retains saved VS with an independent payment-review veto when
seller-reference proof is missing. All five guides document the 70-character limit.

Independent QA/security accept delta16 aggregate
`acd818ec378d89d78b81af04287941aa98b5a79e452cbbcdab4cf3a7ad1b381e`
and complete53-source aggregate
`c0b061befb7f7c053abdb11d0176f0e9ff2c7db968e943642f24d4f85879ac05`.
Main checks: 324 JavaScript tests / nine suites; 164 PHP tests / 862 assertions;
scoped ESLint zero warnings, unchanged107-error typecheck baseline, Pint/PHPStan,
locale parity and production build pass. Independent QA: 208 JS,126 PHP/566,
six real Chrome desktop/320px scenarios. Security independently passes243 JS.
Test scopes overlap and are not summed; no unresolved review blocker remains.

Only the retained feature branch is published. Primary unrelated edits remain
preserved. No unsolicited bot command, dependency installation, production-data
operation, merge, release or deployment. Next is correction-head CI/owner review.
[Finding disposition and evidence](PR454_REVIEW_FIXES.md).

## Expense supplier and received-credit source pushed - 2026-11-17

The owner requested publication. Normal Git push publishes implementation commit
`1ee599001c5edcb4b2431366a9e8223a806e5b90` to owned repository
`webiumsk/satflux`, branch `feat/expense-supplier-credit-notes`, based on verified
master `eee6cd01`. Direct remote Git confirms the same implementation SHA.

The source commit includes exactly 59 scoped files: 49 independently accepted
source/test/locale paths and ten matching guide/development documents. All 49
reviewed blobs retain aggregate
`bb7a48f142f9d4ec6ba8906462c729403ccafb758c8bfd2d1e1ca32d4b064c0f`.
This publication follow-up changes five development documents only. Local and
independent test/review evidence remains attributed to that unchanged source;
no new remote CI pass is claimed. The CI workflow runs for master/main pushes
and pull requests, not an isolated feature-branch push.

The feature checkout is otherwise clean and tracks its matching origin branch.
Primary remains `fix/sepa-confirmation-isolation` with unrelated work preserved.
No PR creation, review-bot request, merge, release, deployment, package publication
or external-repository write is performed. Next is owner review of the published
branch. Public changelog publication remains tied to its normal deployment gate.
See [scope and validation](EXPENSE_SUPPLIER_CREDITS.md).

## Structured expense suppliers and manual received credits accepted locally - 2026-11-17

The owner-authorized implementation is complete in retained worktree
`storage/app/private/worktrees/expense-supplier-credit-notes`, branch
`feat/expense-supplier-credit-notes`, based on fetched remote master `eee6cd01`.
GitHub and remote Git verify that PR451 and PR452 are merged; earlier open-PR
notes below are historical. Actual deployment remains unverified.

Expenses now save optional company-scoped contact references and supplier
name/IBAN snapshots. Explicit contact IBAN takes priority, then a compatible safe
extraction, otherwise the account stays empty and QR is absent. Notes never
supply a QR recipient. Saved/manual recipient edits and explicit clears survive
hydration and extraction. Independent structured review evidence prevents a
contact account from bypassing unsafe XML or partial-payment restrictions.
Legacy original evidence is frozen before note edits and preserved on duplication.

Manual received credits use a sign-inferred document choice and negative totals,
with visible labels and a new source-prefill action that clears the new document
number/VS. Negative totals cannot enable outgoing QR or debit matching. Existing
bank-linked edit locks and local/server duplicate amount behavior are preserved.
All five locales and expense guides are updated together.

Independent QA/security accept all 49 source/test/locale paths at aggregate
`bb7a48f142f9d4ec6ba8906462c729403ccafb758c8bfd2d1e1ca32d4b064c0f`.
Main PHP checks pass 285 tests / 1,806 assertions; independent QA passes 91 / 476,
289 JavaScript tests / 13 suites and 11 real Chrome desktop/320px scenarios with
QR bitmap decoding and no external requests. Main affected JavaScript run passes
837/838 with one unchanged 10 MiB archive timeout under concurrent load; its
complete single-worker retry passes all 35 tests without tolerance changes.
Typecheck adds no errors (107 inherited tolerated); scoped ESLint has no warnings,
Pint/PHPStan, locale parity and whitespace checks pass. The final production build
passes on the accepted source; an interrupted attempt passed on unchanged retry.

Primary remains on `fix/sepa-confirmation-isolation` with unrelated work preserved.
No commit, push, PR creation, merge, deployment, dependency installation, production
operation or external-repository write is performed. Next is owner review of the
local implementation. Publication and deployment remain separate approved steps.
See [scope, contract and validation](EXPENSE_SUPPLIER_CREDITS.md).

## PR452 precision and recovery corrections - 2026-11-17

Both requested CodeRabbit reviews completed in owner order: parent #451 first,
then child #452. Child review5932293520 examined `2357c2a0` and reported two valid
findings. ISDOC payment metadata now rejects more than two raw fractional digits
in payable, paid-instruction, deposit and already-claimed amounts before scale-2
comparisons. No amount is normalized or rounded to authorize a QR; legacy expense
display remains unchanged. Five guides now explain the actual Internal note fields,
exact English metadata labels, separate VS field and verification of the original
full unpaid amount/currency, preserving other notes/payment facts.

The two-source delta against `2357c2a0` has aggregate
`f8e2860d595b0cd4ccca37fb4ac2909a12ec869ec7eda6a186217579f5ead827`;
seven source/guide blobs have aggregate
`0b1acfe93cb1e7f12406300426f78c79b8be1fe3d571bfe4573fd4c7906e0775`.
The complete 27-source scope against parent `156078e4` is
`12abd048b549aa777ddf95c11653c40178d1a4a76ae24ab122f4ff05c03571d7`.
Final ISDOC checks pass 39 tests / 369 assertions. Independent actual-XML probing
passes 24 contexts / 360 assertions, including raw fractions and normal controls.
Pint/PHPStan and whitespace checks pass. Independent QA and security accept the
exact hashes and recovery guidance without blockers. No client/UI source
changed, so earlier browser/client results retain their unchanged-blob attribution.

Next publish the correction and verify new exact-head CI. Parent `156078e4` stays
unchanged with five successful checks. The fresh child automated review covered
`2357c2a0`, not this follow-up. No additional included review slot, PR merge or
deployment is claimed.

## Expense stack integrated for final QR review - 2026-11-17

Parent PR451 corrections are published as `156078e4`: literal VS `0` filtering
and concrete overdue cutoffs for successful-load bulk snapshots. CodeRabbit
review5931726200 covered the preceding `8feb1e8a`; its two findings were verified
and fixed with independent QA/security acceptance. The owner explicitly requests
PR452 as the next included review after PR451.

QR source `2c00627a` enforces PayMe's nine-character amount field without rounding;
PAY by square remains available when PayMe cannot encode the exact amount. The
nine-file correction freeze is
`e8b5a6c229233092ab261889995105c3994e304b67d8c7190dbebb1024b2c8c8`.
The integrated 27-blob QR scope against `156078e4` is
`33d155cf46e020ed0776400189637abf4d7fa189bca6341b6067c6b750a7cbcb`.
All accepted blobs are preserved except the expected combined expense service,
whose cutoff and explicit-null changes received independent integration review.

Final integration checks pass 131 frontend tests / five suites and 47 PHP API
tests / 274 assertions. Typecheck adds no errors against the unchanged 110-error
baseline. Seven new Chrome scenarios verify lossless PayMe amounts and exact
PAY by square bitmap decoding at desktop/320px; that UI source is unchanged by
integration. Scoped lint/style/static checks and locale parity pass. Both
independent reviewers accept the combined source. Documentation conflicts retain
both branches' historical records.

Next publish the synchronized child, verify both exact-head CI runs and request
PR452 review after included capacity returns. No fresh automated approval of the
new parent correction, PR merge or deployment is claimed.

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

## PR452 second review corrections verified - 2026-11-17

The fresh CodeRabbit review of `7e8c0764` reported two valid findings: foreign-
currency ISDOC payment metadata and incomplete missing-reference recovery guidance.
Both are corrected. A related independently reproduced manual-note clearing issue
also now persists explicit null instead of retaining an old supplier bank account.

The follow-up source/test freeze has four blobs, aggregate
`5a8d56bcec7885be22fc7bfc12dc76b29f6f99b2158c734d32eb5029b64de26f`;
complete QR scope remains 27 blobs, aggregate
`4686938f35a5d7279feaad76f5cd75c880a77261e4af97b88d2f3512f22568bf`.
Independent QA and security accepted the final hashes. The affected PHP suites
pass 29 tests / 271 assertions; style/static checks and locale parity pass. Client/UI sources remain unchanged. See [review disposition
and evidence](PR452_REVIEW_FIXES.md). Publish after independent acceptance and
verify new-head CI, preserving the parent branch and stack. No merge/deployment.

## PR452 review corrections verified locally - 2026-11-17

The owner-triggered CodeRabbit review of `481e9c4c` produced three inline findings
and one outside the diff; all four were valid and fixed. Independent review added
server explicit-null persistence and direct-root UBL financial binding regressions.
Shared SWIFT country lengths, explicit supplier references and document replacement
now stay consistent through both storage modes. See [scope, disposition and exact
validation](PR452_REVIEW_FIXES.md).

Independent QA and security accepted the final correction freeze: 17
source/test/data blobs, aggregate
`3b8f3f9b85cb97cd918610409525d4183082dc8b26afb07496f969b5fb22a0b3`.
Whole QR source now has 27 blobs, aggregate
`b2e4ba1b1fb440b7da8f731be681c8dff8fe020e3ac21043516183575a1f3436`.
Focused frontend141, import PHP85/449, server API13/83 and nine Chrome scenarios
pass; lint/typecheck ratchets, locale parity, build and scoped style/static checks
pass. Publish the reviewed correction once, then verify new exact-head CI. PR451
stays unchanged as the stack base. No PR merge or deployment is performed.

## Supplier payment QR source published as PR452 - 2026-11-17

Reviewed QR source `e0dab7a7015842c76ad05c164c56216b565c5536` is pushed to
`feat/expense-payment-qr`. [PR452](https://github.com/webiumsk/satflux/pull/452) is
open and unmerged against PR451's `fix/expense-attachments-bank-direction` at
`8feb1e8a6e5af1ce19cbf85d4ecfe0a775dd1a7c`. The diff contains 30 scoped paths;
this follow-up adds publication documentation and one changelog fragment only.
The 20 reviewed source blobs retain aggregate
`80ba25a20302921fd1aa0bb68f4453bfc1f65d19e1e188084b62742c17f903ff`.

The dependent PR has observed exact-head CI, including five successful jobs on
`7270625a` in run43997399339. The earlier assumption that its stack base prevented
CI was incorrect. Reverify checks after each new head and base change. Neither
PR is merged or deployed.
The public changelog is deferred until its normal approved deployment workflow.
See [implementation evidence and limits](EXPENSE_PAYMENT_QR.md).

## Supplier payment QR implementation reviewed - 2026-11-17

The owner authorized expense-row PayMe/PAY by square QR codes. The isolated
`feat/expense-payment-qr` branch depends on open PR451 at `8feb1e8a`. Both formats
are generated locally for eligible unpaid EUR expenses with explicit supplier
name/account and VS. Import metadata preserves direct-creditor provenance;
partial, ambiguous and nonpositive-payable cases are withheld. No settlement,
subscription, entitlement or production-state changes are introduced.

Independent QA/security accept the 20-blob source freeze
`80ba25a20302921fd1aa0bb68f4453bfc1f65d19e1e188084b62742c17f903ff`.
Nine real-browser scenarios, focused frontend and 37 PHP import tests / 202
assertions pass. Lint/typecheck baselines, five-locale parity and build pass.
See [scope, protocol, limits and exact evidence](EXPENSE_PAYMENT_QR.md), including
historical imports without retained IBAN and the full-run timeout/retry result.
The next step is publication as a dependent review PR; no PR merge or deployment
is authorized or performed by this implementation.

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

## PR451 source published and review handoff - 2026-11-17

The owner requests normal publication of the complete accepted expense/bank
initiative. Source commit `08e68fefc980ec5e8829078110255c0ef883ed29` is pushed to
`fix/expense-attachments-bank-direction`. PR451 is open and unmerged against
verified master `18729a26fc7f99d34364f8317b852d205a33f107`:
https://github.com/webiumsk/satflux/pull/451 .

All 56 reviewed source/test/locale blobs retain aggregate
`dacf07ba8f8487ec8b70562c14838e9c5cd2d2818db5367bca554f2ce7e871b0`.
The initial commit contains exactly 72 scoped paths. This publication follow-up
contains documentation and one reviewed combined changelog fragment only;
feature-branch/PR activity does not publish a public changelog entry.

Local checks and independent QA/security acceptance remain attributed in
[filter evidence](EXPENSE_BANK_LIST_FILTERS.md) and
[attachment/direction evidence](EXPENSE_ATTACHMENTS_BANK_DIRECTION.md).
Current head, exact-head CI and automated review must be checked on PR451;
older/local checks are not new-head GitHub CI or deployed readiness. Source
publication does not establish production behavior. Next is remote check/review
verification and owner merge review. No merge or deployment is performed.

Primary remains `fix/sepa-confirmation-isolation` at `885168f2` with unrelated
work preserved; the isolated feature has its own branch and publication scope.
No private artifacts, screenshots, credentials or unrelated edits are included.

## Detailed expense and bank filters accepted locally - 2026-11-16

The owner requests invoice-style detailed filters/search for expenses and bank
movements. Implementation continues in the retained
`fix/expense-attachments-bank-direction` worktree, based on `18729a26`.
Expenses now combine status, issue period, due range, amount and literal search;
list, summary, pagination, export and select-all share applied criteria. Pending
bulk writes recheck captured context inside the existing financial gate.
Bank filters include booking period, direction, matching state, amount and
scoped reference/counterparty/account/document search before the 50-row display
cap. Totals cover all matches by currency; actual bank balance remains separate.
Local search is not sent into synchronization requests. Prior attachment and
direction fixes remain preserved.

Independent QA/security accept final combined 56-file freeze
`dacf07ba8f8487ec8b70562c14838e9c5cd2d2818db5367bca554f2ce7e871b0`
and continuation 32-file freeze
`211ef9ea8993b0fa92a9f5f76d0d88c37e33a676515676dd16767f54f6d1a556`.
No unresolved blocking findings remain. Full JavaScript passes 3,751 tests /
260 files on the preceding reviewed freeze; the final two-file native mobile
Enter correction passes 125 tests / three affected suites, including nine shared
bar cases. Other 54 files are byte-identical. Fourteen real browser scenarios
pass, including Slovak 320px controls, mobile Enter/validation and draft Clear.
Author PHP scopes pass 49 tests / 278 assertions and 119 tests / 612 assertions;
counts overlap other checks and are not summed. Final build passes 7,680 modules;
typecheck adds no errors against the unchanged 110-error baseline. Full lint has
zero errors / 46 inherited warnings; the final two paths pass zero-warning lint.
Locale parity, API inventory, scoped Pint/PHPStan and whitespace checks pass.
[Filter evidence and precise revisions](EXPENSE_BANK_LIST_FILTERS.md) records
independent tests, privacy/scan limits and prior archive review attribution.

Primary stays `fix/sepa-confirmation-isolation` at `885168f2` with unrelated
changes preserved. The combined feature remains uncommitted and unpublished in
`storage/app/private/worktrees/expense-attachments-bank-direction`; both source
freezes and safe validation evidence remain in the matching private review
folder. Next is owner review. Git publication, merge and deployment are separate
steps. No commit, push, merge, deployment, production-data operation, dependency
installation or external-repository write is performed.

## Manual expense attachment and bank-direction fixes accepted locally - 2026-11-16

The owner-authorized fixes are complete in the isolated
`fix/expense-attachments-bank-direction` worktree, based on verified remote master
`18729a26fc7f99d34364f8317b852d205a33f107` (including PR449/PR450 source).
Manual local expense uploads accept through 10 MiB using canonical MIME-bound
v2 encrypted archives; the 384 KiB inline cell bound and eFaktura v1 custody
remain intact. Interrupted files are visible/deletable and retries keep one
identity. Generic reference/counterparty text no longer changes a recorded bank
debit into credit; display, totals and expense actions agree while original
recorded-direction payment evidence remains separate.

Independent security and QA accept the 30-file source/test/locale freeze
`b677cde350ac194bd89089339877a13c88f914c6222740bf2348a8f663083dc0`.
No unresolved blocking findings remain. Final JavaScript passes 3,685 tests /
257 files; focused PHP passes 77 tests / 243 assertions. Pint and scoped PHPStan
pass. Final lint has zero errors / 46 inherited warnings; typecheck has no new
errors against the unchanged 110-error baseline. Locale parity and build
(7,674 modules) pass. Independent native browser checks verify 450 KiB / 10 MiB
bytes, restart, backup, interruption/retry and canonical deletion. Native
fresh-target restore remains unverified; restore over existing tombstones is
not claimed as that proof. Full details and overlapping review/test counts:
[implementation evidence](EXPENSE_ATTACHMENTS_BANK_DIRECTION.md).

Primary remains `fix/sepa-confirmation-isolation` at `885168f2`; unrelated edits
and other worktrees are preserved. The feature changes are uncommitted and
unpublished in `storage/app/private/worktrees/expense-attachments-bank-direction`.
The source freeze is retained under the matching private review directory.
Next is owner review. Scoped Git publication, merge and deployment remain
separate steps. No commit, push, merge, deployment, tool installation,
production data mutation or provider operation is performed.

## PR450 paid-state and live-row corrections - 2026-11-16

Both findings against published input `f1446a7b` are valid and fixed with two
production-line changes. Paid presentation cannot be replaced by an active
intent, and local settlement uses the guarded live rows rather than a lagging
reactive list. Activation, ownership and retry contracts remain intact.

The four-file freeze is
`e140e46945daf14f33ef2092c358c9274ee03bdd46680404b69a913affcd4df4`;
the complete 62-file source freeze is
`e7ff8e75f96bd2e3e96268e389d134adeb3f9788d4e4bf9ad5ce332f07f9755a`.
Independent QA and security accept both freezes. Local and independent QA
tests pass 85 PHP cases / 412 assertions and 58 JS cases / three suites. Pint, PHPStan, lint, typecheck and locale checks pass.
[Follow-up evidence](PR450_FOLLOWUP_REVIEW.md) attributes independent reviews
and exact-head CI separately from old checks and deployed readiness.

Primary remains `fix/sepa-confirmation-isolation` at `885168f2`; unrelated work
is preserved. Corrections use the existing isolated `fix/invoicing-lazy-btcpay`
worktree and open PR450. No merge, deployment or live provider operation occurs.

## PR450 follow-up corrections reviewed - 2026-11-16

All five further findings against published input head
`4ad6930369c12bb6a43d9be60af79e6f0eaac370` are valid and corrected.
Independent QA and security accept the 11-file follow-up freeze
`e6b0f1ec72791eef2b9326ab30226e0ed87d6700866f9dba59eace606a691e48`
and complete 62-file source/test/locale/CI/API freeze
`daceef31e5a8b867dda6df76d2fc8f3e8117529d19322c85ad26aea9e7b0aae4`.
No blocking review findings remain. See [follow-up evidence](PR450_FOLLOWUP_REVIEW.md).

Issuance preserves invalid-store rejection with fresh preflight before the
transaction and an actionable local-UUID `store_id` message. Query-only service
availability preserves existing behavior. Structured diagnostics exclude original
error data; rejected local paid writes retain polling. All five guides explain
uncertain-attempt reconciliation before refresh.

Local checks pass 134 targeted PHP tests / 676 assertions, 3,648 JavaScript tests /
255 files, Pint 1,185 files, PHPStan zero errors, build, locale parity and unchanged
frontend ratchets. Independent test counts overlap; full PHP is an exact-head CI
gate. Current publication/check results are tracked in PR450 separately from the
input head's earlier green CI and from deployment readiness.

Primary remains `fix/sepa-confirmation-isolation` at `885168f2`, preserving unrelated
work. Corrections use the existing isolated `fix/invoicing-lazy-btcpay` worktree.
Owner review/merge follows exact-head checks. No merge, deployment, installation
or live provider operation is performed.

## PR450 review correction milestone - 2026-11-16

The twelve supplied review findings are verified and fixed on the existing
`fix/invoicing-lazy-btcpay` initiative. Independent QA and security accept
review freeze `2a56d0267ca6b417271820dfa636dea653567340ce562b0e7486ac5fa94704ee`.
Current-term coverage, original historical terms, pre-POST retry safety,
mutex ordering, PDF/frozen snapshots, browser cleanup and explicit refresh
instructions are covered. See [review evidence](PR450_REVIEW_FIXES.md).

Full final-source PHP passes 3,538 tests / 69 skips / 19,332 assertions, with
one inherited PHPUnit deprecation and zero failures or errors. The next authorized step is
normal publication to existing PR450, followed by exact-head CI verification.
Owner merge and deployment remain separate gates. Prior CI and synthetic
provider tests do not establish deployed readiness.

## PR450 published; reviewed source and remote checks - 2026-11-16

The owner requests publication. Normal push publishes reviewed source revision
`627b273386c5c6c5007d8261e5cf39f32f1e103c` on `fix/invoicing-lazy-btcpay`.
PR450 is open and unmerged against master
`077c597df065e000991a67cb9a1b2d32b2d34739`:
https://github.com/webiumsk/satflux/pull/450 . All 68 changed paths belong to
this initiative; unrelated primary-checkout work is excluded.

Independent security and QA accept the unchanged 58-file source/test/locale/
CI/API freeze `1426c72b55687cf866f4ef31a04a9d4d8862e6a75351b0f76033b0612c22fb64`.
The 45-file production aggregate remains
`e047dc6e5f26f7ace5da0fefbb2659cdb020efe325382cc86930b6ad21b6d528`.
The publication follow-up changes documentation only. Local evidence remains
3,637 JavaScript tests / 254 files, three real QR cases, and 3,506 PHP passes /
69 skips / 19,199 assertions with one inherited PHPUnit deprecation. Existing
lint/typecheck ratchets are unchanged; Pint, PHPStan, build, locales and API
checks pass as attributed in `INVOICING_LAZY_BTCPAY.md`.

Current remote head, exact-head CI and CodeRabbit evidence are tracked on the
PR; prior PR449 checks do not substitute for this feature's checks. Deployment
compatibility is not established by local/provider-fake tests. Offline state
propagation, uncertain-attempt reconciliation, historical direct checkout URLs
and the inherited external bank-QR fallback remain explicit limits.

Primary remains `fix/sepa-confirmation-isolation` at `885168f2`; preserve all
unrelated edits and the separate accountant-export clarity freeze. The feature
has its own durable worktree. Next is exact-head CI/review verification and
owner review/merge. No merge, deployment, package/tool installation, live
provider operation or public changelog publication is performed.

## Deferred invoicing BTCPay checkout accepted locally - 2026-11-16

The owner's authorized change is complete in `fix/invoicing-lazy-btcpay`, based
on merged PR449/master `077c597df065e000991a67cb9a1b2d32b2d34739`.
Fresh direct remote verification still reports this master. PR449's reviewed
head `111f5015a67629256241e3c7f61162e897e71511` has the same Git tree.
The new implementation remains uncommitted and unpublished in the durable
`storage/app/private/worktrees/invoicing-lazy-btcpay` worktree.

Issuance, PDF/email/bulk rendering and public GET/HEAD do not create BTCPay
invoices. A stable Satflux link presents a CSRF-protected Bitcoin payment
confirmation. Explicit activation shares encrypted minimal intent/attempt
state, locks and durable CAS with the existing merchant action. Uncertain
creation blocks another automatic POST; actual settlement validates current
terms and known historical attempts. Bank/manual paid, cancellation, deletion
and changed terms terminalize the offer without fabricating BTC settlement.
BTC QR capabilities render locally; a failed renderer retains a clickable link.
An owned unassigned legacy store keeps a generic GET/HEAD page; activation
continues to require exact company assignment, including after plan expiry.

Independent QA and security accept the final 58-file source/test/locale/CI/API
freeze `1426c72b55687cf866f4ef31a04a9d4d8862e6a75351b0f76033b0612c22fb64`.
Production scope is 45 files, aggregate
`e047dc6e5f26f7ace5da0fefbb2659cdb020efe325382cc86930b6ad21b6d528`.
No unresolved blocking findings remain. Main's full JavaScript run passes
3,637 tests / 254 files; three separate real QR encoding cases pass. Final
full PHP exits successfully: 3,506 passed, 69 skipped, 19,199 assertions and
one inherited PHPUnit deprecation. Full Pint passes 1,182 files; the final
two-file compatibility check also passes. PHPStan has zero errors. Lint has
zero errors / 46 inherited warnings; typecheck adds no errors against the
existing 110-error baseline. Locale parity, build 7,673 modules, API inventory,
Blade compilation, CI YAML, whitespace and exact hash checks pass.

The isolated PHP runner reuses existing matching dependencies and Node 22,
synthetic loopback-only DNS, SQLite in memory, temporary storage and no network.
Initial environment failures reproduce on the unchanged base; application
validators and health thresholds remain intact. Provider/race tests are
synthetic; deployed BTCPay, distributed Redis contention and native execution
of the new Evolu projection are not established. Offline terminal updates take
effect after synchronization. Historical direct checkouts and the inherited
external bank-QR fallback remain separate limits. Full details and overlapping
independent test counts are in `INVOICING_LAZY_BTCPAY.md`.

Primary remains `fix/sepa-confirmation-isolation` at `885168f2`. Unrelated edits
and the separate accountant-export clarity freeze are preserved. Next is owner
review and requested Git publication. No merge, deployment, package/tool install,
real provider operation or public changelog publication is performed.

## Deferred invoicing BTCPay checkout authorized - 2026-11-16

PR449 is merged at `077c597df065e000991a67cb9a1b2d32b2d34739`.
The reviewed head `111f5015a67629256241e3c7f61162e897e71511` has the
same Git tree. Remote master was verified directly; deployment is unverified.
This supersedes the earlier open/unmerged milestone below.

The owner authorizes delaying actual BTCPay invoice creation until customer
payment activation. A new isolated `fix/invoicing-lazy-btcpay` worktree starts
from this merged base. PDF/email/issuance will prepare a stable Satflux link;
GET displays the payment page and CSRF-protected POST activates BTC payment.
The bounded design includes minimal encrypted payment-intent state, durable
uncertain-attempt protection, owner-scoped bank/manual/cancel reconciliation,
and payment discovery before the merchant knows a provider invoice ID.

Independent security and QA accept the architecture subject to implementation
gates. Final implementation reviews and tests are pending. Offline local-first
payment changes reach the server only after authenticated reconciliation; this
limit was explained to the owner. Existing provider requests are not silently
cancelled or marked BTC-paid following a bank transfer.

Primary remains `fix/sepa-confirmation-isolation` at `885168f2`; unrelated
work and the separate accountant-export clarity freeze remain preserved.
No merge, deployment or provider operation is performed. Contract and ownership:
`INVOICING_LAZY_BTCPAY.md` in the feature worktree.

## PR449 late-sync deletion intent accepted locally - 2026-11-16

The six original CodeRabbit comments are resolved on published `033fd831`,
whose CI43780501107 passes all five jobs. The owner's seventh finding,
[delayed archive arrival after deletion](https://github.com/webiumsk/satflux/pull/449#discussion_r4580967386),
is independently verified and now corrected in the same branch.

Fresh authorized attachment/reference bindings derive the exact source and
at most 40 chunk IDs. Present row-level checks remain; one atomic, explicit-owner
batch records deletion intent for every canonical ID even when absent. Readback
requires exactly one owner-scoped tombstone per target. No payload bytes or
financial metadata are invented; foreign rows and retry-resurrection guards
remain protected. This supersedes the earlier only-existing-row defense.

Independent QA/security accept the final 44-file source/test/locale freeze:
`2699055fc1a21886a68fd65d7790d35cf2f6698a4dc48ab05716f680929a9688`.
Only the archive helper/test change from the 42-file parent; two existing
memory-only SDK test helpers extend the scope. Total PR scope is 55 paths.
Author57/3, security70/3, selected QA19 (16 filtered) and corrected native10/10
pass. Genuine older SDK history retains original timestamps; replay fills
metadata while tombstones stay1 and backup restore preflight passes. Immutable
parent baseline reproduces the bug; no newer-upsert proxy or relay claim.

Full lint passes with0errors/46inherited warnings, typecheck110inherited/no new,
locale parity, build7,672 and whitespace pass; ratchets are unchanged. Synthetic
native processes/profiles are removed and their port is closed. Evidence and
scope manifest are retained privately.

CodeRabbit CLI is installed, but automatic permission review rejects execution
before start because source upload to external SaaS lacks explicit consent.
The consent question remains pending; no CLI upload/review result is claimed.
Independent local review and tests complete unaffected, with no install, login,
credit, trust or configuration changes.

The follow-up is ready for normal push and exact-head CI. Primary remains
`fix/sepa-confirmation-isolation` at `885168f2`; preserve unrelated edits
and the separate accountant-export clarity freeze. No merge, provider operation,
public changelog publication or deployment.

## PR449 CodeRabbit follow-up accepted locally - 2026-11-16

All six actionable comments are independently checked against published parent
`3f95ad5c9ad6608d24da74f8306d1eb8574b9844`. Missing mocks and the owner-column
SQL ambiguity were already fixed. The remaining four valid findings are now
corrected in the same branch: archive-arrival preview refresh, coherent captured
export selection, checkpoint-before-byte-proof ordering with fresh authority
checks, and authorized deletion of damaged/incomplete archives.

Export selection captures typed rows, range, company/name and copied options
(including formats), and cancels on synchronous selection changes before bridge
requests or held ZIP downloads. Source/chunk evidence refreshes previews.
Private post-proof company/share reads preserve owner, fingerprint and exact
active-share authority checks after the earlier worker marker. Deletion validates
available structural bindings, atomically tombstones only existing owned rows,
and prevents deleted attachments from recreating missing archives. Existing
limits, financial contracts, server protocol, schema, dependencies and global
owner/reconciliation wrappers remain unchanged.

Independent QA and security accept the final 42-file source/test/locale freeze:
`225967876edbab39c8364a6a061aefbdd8b2e4fa100d10b1ddcefe987df86237`.
Seven prior files change; one bank-performance mock adds two query tokens with
all assertions unchanged. The combined PR scope is 53 paths. Author core
128/6, main consumer/planner/performance 31/3, security 102/4 including six private
probes, QA consumer 12 plus six private probes, selected core 28/3 and native
12/12 pass. Runs overlap; 56 QA-filtered tests are not counted as passed.
Lint 0 errors / 46 inherited warnings, typecheck 110 inherited errors / nothing
new, locale parity, build 7,672 modules and whitespace pass.

The wrapper-exit checkpoint and final local read to remote completion interval
remain explicit non-atomic limits. Native fixtures/profile data are removed,
their port is closed, and private evidence distinguishes mocks, native storage
and earlier CI. Details and finding links: `EFAKTURA_LARGE_ORIGINALS.md`.

The reviewed follow-up is ready for normal push and CI on its exact new head.
Prior green CI43776670496 belongs to `3f95ad5c`. Primary remains
`fix/sepa-confirmation-isolation` at `885168f2`; preserve unrelated edits
and the separate accountant-export clarity freeze. No merge, provider operation,
public changelog publication or deployment is performed.

## PR449 startup query correction accepted locally - 2026-11-16

PR449's published head `a7410fc59910aa699a7a6cf10b6581e3c4ed5fd0` passes
CI43773954997 Node (3,555 tests / 250 files), PHP (3,501 passed / 19,624 assertions,
31 skipped), PostgreSQL and changelog. E2E exposes two blank initial routes.
A comparison rerun of PR448 head `54b9eea1` passes 26 tests with two existing
flaky footer cases and three skips, distinguishing the new startup regression.

The actual owner wrapper appends unqualified `ownerId`; the new archive join
makes that generated SQL ambiguous even on an empty database. Replace only the
outer join with a correlated `EXISTS`, retaining source ID, owner, company,
exact kind and live source/chunk predicates. The global wrapper is unchanged.
A new actual-client/wrapper/serializer SQLite regression fails 2/2 before and
passes 2/2 after; 26 focused tests / six suites, scoped ESLint and full typecheck
(110 inherited errors / nothing new) pass.

Independent security accepts both corrected file hashes. QA verifies seven native
Chrome/Evolu/OPFS checks: empty query, snapshot, refresh, fingerprint, exact
owner/company/live-source filtering and account seed binding after page reload.
The immediate SDK restore result and successful post-reload pending-sync read
are distinguished; no real account, provider or multi-device delivery is claimed.

The combined 41-file source/test/locale freeze is
`48b383cc6e879a2d8380ca3a223ada392b206bee2597a39909ddfd032e41ec22`.
Relative to the original 38-file review, only `client.ts` changes; 37 files
retain their hashes. Two prior QA-approved mock corrections and the new SQL
regression extend the scope to 41; with docs/changelog the total is 52 paths.
Contract and evidence: `EFAKTURA_LARGE_ORIGINALS.md`.

Next is normal push of the bounded correction and CI for that exact final head.
Primary remains `fix/sepa-confirmation-isolation` at `885168f2`; unrelated
changes and the separate accountant-export clarity freeze remain preserved.
No merge, provider operation, public changelog publication or deployment.

## PR449 published; publication checks in progress - 2026-11-16

The owner's push instruction publishes reviewed implementation
`91748ce3e7208353f7584e9a323a00139a02c268` on
`feat/efaktura-large-originals`. GitHub PR449 is open against master
`2201bf1bacdc9a26571f188382b5e10999bbbf55`:
https://github.com/webiumsk/satflux/pull/449 . Remote metadata matches that
implementation commit. This supersedes the local-only status below.

The documentation follow-up adds publication records and one PR-linked
user-facing changelog fragment. CI43773180167 on `802412ad` then passes
3,550 tests in 248 files but cannot load two other suites because their existing
client mocks lack the new original-source/chunk query exports. The correction
adds those two tokens to each mock, preserving all test assertions; all five
targeted tests and two-file ESLint pass. Independent QA accepts both exact
file hashes and confirms unchanged assertions. This is a test-fixture correction,
with no additional production-code changes.

All 38 accepted source/test/locale hashes retain aggregate
`d0ba510c9b9617484f65d63fb9981db7673e8c9ba000de5d5318f4b5b8c1143d`.
The fragment and two existing test-file corrections make 51 scoped paths.
Remote checks must be verified on the corrected final head; neither the earlier
local tests nor the failed first CI establish that head's CI or production readiness.

Primary remains `fix/sepa-confirmation-isolation` at `885168f2`, with unrelated
changes preserved. The separate accountant-export clarity freeze is unchanged.
No merge, provider operation, public changelog publication or deployment is
performed. The changelog uses the existing gated deployment publication process.

## Larger eFaktura originals accepted locally - 2026-11-16

The owner authorizes remaining point 1: received originals above 384 KiB,
including UBL/XML with embedded PDF. Implementation remains uncommitted on
`feat/efaktura-large-originals`, based on verified PR448 merge
`2201bf1bacdc9a26571f188382b5e10999bbbf55` from reviewed head `54b9eea1`.
Primary remains `fix/sepa-confirmation-isolation` at `885168f2`; unrelated work
and the separate 17-file accountant-export clarity diff remain preserved.
Merge evidence and these local checks do not establish deployed production readiness.

Owner-scoped archives store at most 40 chunks of 256 KiB under the existing
10 MiB server admission bound. Strict receipt-bound references, durable writes
and fresh context/byte/digest checks before and after custody preparation protect
the unchanged server API and post-ACK cleanup contract. Backup v3 validates the
complete archive before output or restore writes. Download, deletion, sharing,
rekey, fingerprint and resend consumers handle the representation. Ordinary
attachments remain 384 KiB; preview and accountant-export caps remain unchanged.

Independent QA and security accept all 38 source/test/locale files at aggregate
`d0ba510c9b9617484f65d63fb9981db7673e8c9ba000de5d5318f4b5b8c1143d`.
Two medium findings are fixed: noncanonical snapshot live flags now fail before
restore writes, and post-proof company/receiving-context changes prevent custody
completion. Main checks pass 274 frontend tests in 20 suites, 49 PHP tests /
605 assertions, lint with 0 errors / 46 inherited warnings, typecheck with
110 inherited errors and nothing new, locale parity and build (7,672 modules).
Pint passes for both changed PHP test files; no production PHP changes.

QA additionally verifies 44 compatibility/integrity cases, 15 malformed-flag
cases, and 22 native Chrome/Evolu/OPFS checks plus four native context-race
regressions. Exact 10 MiB survives a full browser restart; interrupted writes
resume, and atomic deletion failures retain originals. Review runs overlap.
All synthetic fixture processes are stopped and private browser profiles removed.

New originals require the updated client for access, backup, restore and sharing;
rollback needs a compatible reader/backport. Existing E2EE synchronization is
preserved; SQLite encryption at rest is not configured or certified. Tests use
synthetic custody callbacks and disabled external transports, so real provider,
multi-device synchronization and deployment remain unverified. Contract, evidence,
limits and final review dispositions: `EFAKTURA_LARGE_ORIGINALS.md`.

Source and documentation remain local, with no new commit, push, PR, head CI,
provider operation or deployment. Next is scoped publication when requested;
merge and deployment retain their approval gates.

## PR448 published; final publication checks in progress - 2026-11-15

The owner's push instruction publishes reviewed implementation commit
`373f7e1411b401563c225b6edc9068495e09d302` on
`fix/efaktura-settings-clarity`. GitHub PR448 is open against master `c418dcc3`:
https://github.com/webiumsk/satflux/pull/448 . Remote metadata matches that
reviewed implementation head. A follow-up adds the combined user-facing
changelog fragment linked to PR448 and this publication record; all19 accepted
source/test/locale files retain aggregate
`28e1d944f3b711f361836595c2d93303bd5a4f8fd271d5163390f4424c9f7e10`.

Initial CI43759061107 started for the implementation commit. Fresh-head CI must
be checked again after the metadata/changelog follow-up; no passing CI result is
claimed yet. Main394frontend/19 and independent QA/security/browser evidence
remain attributed to their reviewed source freeze. Earlier local-only and
publication-authorization checkpoints below are historical and superseded by
this observed push/PR. No merge, deployment, provider call or new implementation.
Primary remains `fix/sepa-confirmation-isolation` at `885168f2`, unrelated work
preserved. Owner retains merge/deployment decisions.

## Clarity branch publication authorized - 2026-11-15

The Product Owner explicitly requests pushing the reviewed eFaktura/settings and
profile/navigation corrections. Publish only `fix/efaktura-settings-clarity` to
the owned Satflux repository, open its review PR and verify CI for the resulting
head. Fresh fetch confirms master `c418dcc3` still matches the scoped base;
there is no existing PR for this branch. Primary unrelated work stays preserved.

All19 reviewed source/test/locale hashes still match aggregate
`28e1d944f3b711f361836595c2d93303bd5a4f8fd271d5163390f4424c9f7e10`.
The earlier394frontend/19, static/build, QA44/5+62browserchecks and security40/4
results remain applicable; no source changes or redundant product-test reruns.
Main owns scoped commits/push/PR and a user-facing changelog note linked to the
actual PR. Publication outcome and fresh-head CI must be verified separately.
Earlier local-only/owner-review checkpoints below are historical and superseded
by this authorization. Merge, deployment and larger-original implementation are
not part of this publication request.

## Compact profile guidance and Tools navigation accepted locally - 2026-11-15

- [x] Make profile guidance collapsed native disclosures with visible progress or
  unverified status; preserve existing invoice-list guidance and scoped actions.
- [x] Share desktop width across visible cards; fill the row with one and remove
  the wrapper with zero; stack on mobile and keep sharing full-width below.
- [x] Remove empty filter strips from VAT report, audit and accountant export;
  preserve their period/export controls and real filter-bearing page controls.
- [x] Restore WooCommerce Tools/Application navigation and company links.
- [x] Reproduce and fix same-mode company-switch coverage reload while retaining
  generation fences; obtain independent QA/security and synthetic browser checks.
- [x] Pass394frontend/19, lint/type/locales/whitespace/build7,669; QA44/5 plus
  62browserchecks/28PNG and security40/4 overlap.
- [ ] Publish the scoped branch and verify fresh-head CI under applicable owner
  authorization; owner retains merge and deployment.

Same local `fix/efaktura-settings-clarity` branch over merged PR447 `c418dcc3`;
no publication/provider/deployment action. Final19-file freeze and browser limits:
`INVOICING_LAYOUT_CLARITY.md`. Prior seven-file editor correction is unchanged.
Larger-original import remains proposed separately.

## eFaktura receiving settings clarity accepted locally - 2026-11-15

- [x] Keep one effective editor and no sharing toggle for receiving-only firms.
- [x] Explain the sender relationship in full mode and retain complete receiver
  environment, identity, account/test and explicit-save guidance.
- [x] Add narrowly guarded, explicit previous-connection recovery without data
  copies or network calls before Save; preserve submitted-company fences.
- [x] Close saved-secret and mobile URL review findings; obtain independent
  design/QA/security acceptance and synthetic desktop/mobile/keyboard evidence.
- [x] Pass360frontend/14, lint/type/locales/whitespace and build7,669.
- [ ] Owner reviews the local result before scoped publication and fresh-head CI.

Retain `fix/efaktura-settings-clarity` over merged PR447 `c418dcc3`, uncommitted.
Evidence: `EFAKTURA_SETTINGS_CLARITY.md`. No backend, provider, Live or deployment
change. Larger-original import above384KiB remains the next separate proposal.

## PR447 review corrections independently accepted locally - 2026-11-15

Observed review baseline: open/unmerged PR447 at `a48570d11bbf73956f89f47e36d7c38f446f3389`,
base master `4b00e2ee`. The directional checkout has this HEAD plus the local
corrective diff; the primary checkout remains `fix/sepa-confirmation-isolation`
at `885168f2` with unrelated work preserved. This is remote review evidence and
local source state, not an established deployment or production/provider proof.

All three supplied findings are current and fixed: PR447 milestone evidence,
submitted-company receiving confirmation and complete bounded settings JSON.
The PHP CI failure is the missing legitimate capability in PublicConfigTest's
exact expectation; the exact assertion remains. No requested finding is skipped.
QA/security accept five frozen source/test files after before-failing regressions.
Main passes304PHP/2,223,329frontend/15, PHPStan0, Pint3, lint0/46inherited,
type110/no new, locales/API/three schemas/whitespace and build7,669. Counts and
independent overlapping probes are attributed in `PR447_REVIEW_FIXES.md`.

Baseline CI43710844183 has one PHP failure,3,489passes/31skips; its other four
jobs pass and later PHP static/audit steps are skipped. The corrections above
are not yet published or checked as a new head. Main proceeds with normal
existing-branch publication and fresh-head CI under the owner's existing
publication authorization. Owner retains PR447 merge and deployment. Earlier
local-only/publication records below are historical, superseded by this milestone.

## Independent eFaktura connections accepted locally - 2026-11-15

- [x] Verify owner-merged PR446 and fetched master `4b00e2ee`.
- [x] Keep shared defaults and add one independent receiving connection, tests,
  health, readiness, local bridge confirmation and old-server capability guards.
- [x] Preserve immutable legacy outgoing retries and incoming original custody.
- [x] Close independent ownership/proof/test-state/export findings and obtain
  independent QA/security acceptance of the final source freezes.
- [x] Pass 293 PHP / 2,054 assertions, 318 frontend / 14 suites, PHPStan, Pint20,
  lint/type ratchets, locales, API freshness/three schemas and build7,669.
- [ ] Owner reviews the local result; publish and verify fresh-head CI only under
  applicable authorization, then owner decides merge and deployment.

Changes remain uncommitted in `feat/efaktura-directional-connections`; no provider
operation or deployment. Contract/reviews: `EFAKTURA_DIRECTIONAL_CONNECTIONS.md`.
Backend-first rollout is required. Direct prefeature backend rollback requires a
reviewed compatibility or offline recovery procedure because old code neither
routes the override nor strips its credential ciphertext. Additional providers,
automatic ePostman sending and production/archive/reporting gates stay separate.

## PR446 E2E runner compatibility repair approved - 2026-11-15

Exact-head CI43697361409 forbec2c34f passes PHP, Node, PostgreSQL and changelog,
but E2E fails before tests at browser installation: locked Playwright1.60.0 does
not support chromium on ubuntu26.04-x64 selected by ubuntu-latest. This is
runner compatibility evidence, not an application/E2E assertion failure.

Owner explicitly approves only the prepared E2E-runner repair. Change exactly
`jobs.e2e.runs-on` from ubuntu-latest to ubuntu-24.04. The installed locked
Playwright registry includes ubuntu24.04-x64. Existing Node YAML parser verifies
that this scalar is the only semantic workflow change; whitespace checks pass.
No dependency/action versions, other jobs, permissions, application or tests change.
The accepted two-file runtime/test freeze remains
`b342a06d14ed1ba1cade2bfc67716b4bfb7f1685cc4d5c03fb2ab60cf59f1d89`.

Normal corrective publication and new-head CI follow. The failed run is not
reported green, and no tests are marked skipped to bypass the compatibility issue.
No installation, provider operation, PR merge or deployment. Owner retains merge/deploy.

## PR446 inbound poll budget correction independently accepted - 2026-11-15

The current owner-supplied finding is valid at64d7bcd7. The previous10-second poll
limit is shorter than the server120-second cooperative admission budget plus
finishing provider operations. A dedicated finite300-second poll timeout now
accommodates default30-second detail/ACK and one token-renewal/retry sequence;
ordinary inbox/health/custody requests remain10 seconds. Endpoint/auth/payload,
explicit-only retry and backend acknowledged-receipt skipping are unchanged.

Before-failing virtual regressions now accept a240-second completed poll and
expire a stalled request at300 seconds. Main66frontend/3 and44PHP/519assertions
pass; lint0errors/46inherited, type110/no new, locales/whitespace/build7,669 pass.
Independent QA/security each accept66/3 and the verified two-file freeze
`b342a06d14ed1ba1cade2bfc67716b4bfb7f1685cc4d5c03fb2ab60cf59f1d89`.
Counts overlap. The deadline is not a server-cancellation guarantee or universal
ceiling for arbitrary longer provider/DB settings. See `INBOUND_POLL_TIMEOUT_REVIEW.md`.
Main owns existing-branch publication and exact-head CI; owner retains merge/deploy.
Unrelated primary changes remain preserved; no provider operation or installation.

## PR446 review fixes independently accepted - 2026-11-15

Both owner-supplied findings against1411529a are current and reproduced; none is
skipped. Bound pollInbound with the existing10-second inbox timeout, retaining
POST/auth/payload and explicit retry. Prevent Save/Test overlap in button and
handler admission, preserving unsaved verification and exact existing dirty checks.
Only two runtime paths and two regression files change; no backend/API/schema,
locale, credential-autosave, provider or dependency expansion.

Main passes174 tests /five suites, lint0errors/46inherited, type110/no new,
parity/whitespace and build7,669. Independent QA155/5 and security145/4 accept
four-file freeze `1aa1218f2de98f82442e238caa7db611d3d004e8a3e514f987d4819815f88dd1`.
Counts overlap. Baseline PR446 CI43654838725 passes at1411529a; fresh-head CI
must be verified after normal corrective publication. Scope/dispositions:
`INVOICING_STATUS_REVIEW_FIXES.md`. Main owns existing-branch push/thread handling;
owner retains PR merge/deployment. Primary unrelated edits remain preserved.

## Invoicing status publication reconciliation verified - 2026-11-15

Owner requests push of both accepted corrections. Implementation commit `8d0b4999`
contains exactly31 reviewed paths. Fresh remote master is `4b9cc230`, the owner's
merged PR445. Reconcile it locally with a normal feature-branch merge before push.
Ten conflicts preserve both development histories, the newer five-guide date and
both required inbox imports. The combined panel is exactly the accepted receiving
panel plus the master's PDF import/component. No additional product logic is added.

Independent QA accepts all41 affected paths and162 tests /five suites; security
accepts the narrow17-file byte-preserving integration. Main passes391 combined
tests /15 suites, lint0errors/46inherited, type110/no new, parity/whitespace and
build7,669. Earlier136 PHP/1,018, PHPStan0 and Pint2 remain applicable through
unchanged backend bytes. Merged source fingerprint is
`42fa35d13d66eccc9f6e04224db322cb3066019d11eba289a6901f10097279ee`.

Main owns the pending normal push and remote-head verification. No new-head CI,
PR merge or deployment is claimed by this pre-publication record. Owner retains
PR creation and merge/deployment decisions. Earlier uncommitted/read-only notes
below are historical; both corrections are now authorized and committed locally.

## eFaktura receiving status correction independently accepted locally - 2026-11-15

Owner authorizes fixing the receiving warning after confirming Test plus Save
removes the setup checklist. Extend the unpublished `fix/relay-status-banner`
worktree over remote-verified master `ce725a99`; retain the reviewed relay change.
Equal readable nonempty SAPI secrets now preserve trusted poll/test metadata.
Changed/cleared/unreadable credentials and connection/provider changes invalidate
it; incoming server-owned evidence/ciphertext cannot forge values.

Expenses now offers Check mailbox through the captured current local-company and
bridge poll endpoint, followed by independent list/health reads. Refresh stays
read-only. Duplicate/poll/refresh races, stale company/bridge results and unmounts
are fenced; partial documents remain recoverable after failures. Empty receiving
is neutral, and successful local Test visibly requires explicit Save. Five locales
and guides agree; custody, snooze, payments and dependencies are preserved.

Main passes246 frontend /11 suites,136 PHP /1,018 assertions, full PHPStan0,
Pint2, lint0errors/46inherited, type110/no new, parity/whitespace, existing16
changelog fragments and build7,664. Independent QA accepts120PHP/945assertions
and133frontend/5; security77PHP/480 and123frontend/4. Counts overlap. Frozen18-file
aggregate `4609260d8df5d14e31c16d533ef07c44d467cd650337d1e281fe21ce9bbc8811`
remains verified; prior relay runtime/test hashes are unchanged. No blockers remain.

See `EFAKTURA_RECEIVING_STATUS.md` for contracts, reviewers, tests and limitations.
Initial disposable-harness and sandbox checks were corrected through normal setup/
approval, with subsequent complete checks passing. No installation, production
provider action or deployed-SHA verification. Both corrections remain uncommitted
and unpublished; no PR/new-head CI/merge/deployment is claimed. Primary unrelated
edits and other worktrees remain preserved. Next: review local result, then
publication/fresh-head CI under applicable authorization and owner merge/deployment.

## Current relay banner independently accepted locally - 2026-11-14

Owner authorizes a current per-relay warning and recommended recovery actions.
Implementation is isolated on `fix/relay-status-banner` over remote-verified
master `ce725a99`, in durable worktree `relay-status-banner`. Selected enabled
routes intersect this tab's guarded active destinations and expiring worker
observations. Historical SDK quota errors no longer trigger the warning. Partial
errors are amber, all-enabled errors red; local storage warnings remain separate.
Backup/settings actions and EN/SK/ES/DE/CS guides/copy avoid backup-completeness claims.

Main passes 81 relay tests / four suites and 87 eFaktura tests / two suites;
lint has zero errors / 46 inherited warnings, typecheck110 has no new errors,
locale/whitespace and production build7,664 pass. Existing16 changelog fragments
validate; the new fragment awaits a real PR URL. Independent QA accepts86/5 and
security141/7, including actual channel expiry and lifecycle/default-owner guards.
Counts overlap. Reviewed12-file aggregate SHA256 is
`0aec946d208ecd08b6808bf14e58c449c2d9fd23d7670769e5d88ff535ed833e`.

Separate read-only eFaktura review confirms successful local testing needs explicit
Save to persist its timestamp; the form does not mark that timestamp as unsaved.
Remind later hides the company notice locally for14 days without changing receiving.
No eFaktura policy/source fix is included. See `RELAY_STATUS_BANNER.md` for evidence,
limitations and exact snooze behavior. Changes remain uncommitted/unpublished;
no new-head CI, PR, merge, deployment, real provider operation or complete-backup
proof is claimed. Primary unrelated edits and other worktrees remain preserved.
Next: local-result review/publication under applicable authorization, fresh-head CI,
then owner merge/deployment decision.

## PR445 master reconciliation after PR443 merge - 2026-11-14

- [x] Confirm PR443 merged as `ce725a99` and fetch current master/PR445.
- [x] Preserve both histories while resolving four documentation-only conflicts.
- [x] Verify 90 other changed paths against parents, five-locale three-way values
  and all six previously reviewed PR445 hashes.
- [x] Pass 351 combined frontend tests, static/build/parity checks and independent QA.
- [ ] Verify new-head CI after publishing the reconciliation commit.
- [ ] Owner reviews and merges PR445 after the new checks pass.

See `PR445_REVIEW_FIXES.md`; original owner data and previews remain untouched.

## PR445 verified review corrections - 2026-11-14

- [x] Verify and reproduce all three findings at `49384427`; process duplicates once.
- [x] Await healthy document destruction; bound stalled worker cleanup to one second.
- [x] Restore saved previews after pending-clear and reject namespace-independent XML errors.
- [x] Pass 223 focused tests, static/build/parity and independent QA/security;
  real Chrome verifies parser rejection, graceful/forced cleanup and exact download.
- [ ] Verify published PR445 head CI before the owner's merge/deployment decision.

No PHP/API/schema, dependency or custody change. See `PR445_REVIEW_FIXES.md`.

## Embedded PDF viewing and download - 2026-11-14

- [x] Derive bounded PDFs from original UBL Invoice/CreditNote without custody changes.
- [x] Offer explicit page preview and exact PDF download in inbox and existing expenses.
- [x] Preserve tenant/context fences, source bytes, worker/URL cleanup and other XML.
- [x] Cover EN/SK/ES/DE/CS; pass 204 main tests, static/build checks and independent
  QA/security, including real Chrome two-page rendering and exact download.
- [ ] Owner opens the feature PR and verifies its exact-head CI before merge/deployment.
- [ ] Owner verifies receiving with the browser closed; production/provider obligations
  and archive arrangements remain separate from the Peppol Test Network evidence.

PR444 is confirmed merged as `f5cb7f0e`; PR443 remains separate and open.
See `EFAKTURA_EMBEDDED_PDF.md`; no coordinator merge or deployment.

## PR443 follow-up corrections - 2026-11-14

- [x] Verify all four current claims; correct provider precision, canonical derived
  line totals, reserved issue freshness and UBL zero-rate categories.
- [x] Close independently reproduced mutable-alias and buyer-ownership gaps.
- [x] Pass full frontend 3,224 / 241, related PHP 200 / 1,739, required static,
  build, locale and API checks; independent security accepts the frozen scope.
- [x] Independent QA accepts 158 frontend tests / six suites plus backend
  code/test inspection; PHP evidence is explicitly attributed.
- [ ] Publish the correction to existing PR443 and verify exact-head CI before
  owner merge/deployment review.

No model precision, dependency, API artifact or schema change is required.
See `PR443_FOLLOWUP_REVIEW.md`. Prior PR443 CI belongs to `dbe6cc96`.

## PR443 review corrections - 2026-11-14

- [x] Verify all nine original findings; fix every still-valid issue with regressions.
- [x] Align four-decimal percentage storage/buckets/XML/PDF and signed credit notes.
- [x] Protect checkout, stock, outer commit, precise issue/buyer context and snapshots.
- [x] Close independent QA/security findings including zero-tax buckets, frozen VAT,
  exact buyer references, pre-allocation and post-await isolation, and PDF labels.
- [x] Pass full frontend 3,193 / 241, related PHP 211 / 1,325 and required local
  static/build/API/locale checks; independent QA/security accept 22 frozen files.
- [ ] Push the correction to existing PR443 and verify exact-head complete CI.
- [ ] Owner merge/deployment decision; apply new tax-rate migration only through an
  approved release. Down refuses lossy narrowing while fractional values exist.

Local full PHP runtime preparation remains permission-gated after automatic
rejection; no dependency install is assumed. CI remains a separately observed gate.
See `PR443_REVIEW_FIXES.md`; unrelated PDF branch and existing previews are preserved.

## PR443 Laravel audit repair - 2026-11-13

- [x] Confirm all 3,412 PHP tests, Pint and PHPStan pass after API inventory repair.
- [x] Verify the separate new Composer audit finding and official patched version.
- [x] Update only Laravel 12.69.3 to 12.71.0, retaining constraints/other packages.
- [x] Independently accept actual new-vendor security, API/auth/payment/inventory
  checks, strict Composer validation, clean audit and unchanged artifacts.
- [ ] Publish the dependency correction and verify all gates on the exact new head.

No advisory ignores, shared-vendor changes, merge or deployment.

## PR443 API inventory freshness repair - 2026-11-13

- [x] Verify PHP CI failed before PHPUnit at the generated API freshness gate.
- [x] Reproduce and regenerate exactly two source fingerprints; preserve all
  460 routes, middleware/actions and three OpenAPI specifications.
- [x] Independently accept artifact diff, export check, schema validation and
  API regressions 11 / 230.
- [ ] Push the correction on PR443 and verify exact-new-head CI.

No runtime/dependency change, merge or deployment in this repair.

## Invoice correction publication - 2026-11-13

- [x] Owner authorizes push of the complete reviewed correction branch.
- [x] Verify all 56 source/config hashes and stage exactly 67 scoped files.
- [x] Publish implementation `165d315e` without force and verify its remote SHA.
- [x] Record publication separately from existing local tests and deployment.
- [ ] PR review and exact-commit CI verification.
- [ ] Separate merge/deployment approval and backend-first additive migration.

No PR, merge, existing-database migration or deployment in this push-only step.

## Negative-quantity deduction follow-up - 2026-11-13

- [x] Accept `-1 x 15` manual deductions at positive prices/positive totals and
  retain source precision, VAT, payment balances and stock fences.
- [x] Preserve generated credit-note correction workflows and original invoices.
- [x] Verify PDF/email/bulk/checkout and UBL/ISDOC/ePostman preparation, full official
  UBL schemas and exact fake-provider retry/cache behavior.
- [x] Independently accept QA/security at frozen 56-file fingerprint, update all
  five guides and pass final frontend 3,133 / 240, backend regressions, static,
  lint/type/build/locale gates without ratchet changes.
- [ ] Owner reviews the retained uncommitted correction branch.
- [ ] Separately authorize publication/deployment; retain earlier backend-first
  payment-provenance migration. This quantity extension adds no new migration.

No production provider/delivery or deployment claim. Details:
[implementation report](INVOICING_CORRECTIONS.md).

## Invoice deductions, payment methods and buyer details - 2026-11-13

- [x] Fix negative-price invoice save/issue/PDF/email/checkout with canonical
  positive totals, supported precision/VAT and explicit structured-export limits.
- [x] Record confirmed payment source; remove enabled-BTCPay inference and keep
  unknown historical methods unspecified without a guessed backfill.
- [x] Show buyer identifiers from issued snapshots and preserve contact identifiers
  on blank Woo input; document supported metadata and server/browser boundary.
- [x] Independently accept QA/security, stale-state payment fixes and five-language
  documentation; pass full frontend 3,110 / 239, backend regressions, full static
  analysis, existing lint/type ratchets, build and locale parity.
- [ ] Owner reviews the retained uncommitted correction branch.
- [ ] Separately authorize publication/deployment; apply additive server migration
  before updated backend code. No external plugin change or historical data repair.

Source baseline `e2130aea`; no deployment inferred. See
[implementation report](INVOICING_CORRECTIONS.md) for fingerprint and test limits.

## PR442 verified review fixes - 2026-11-13

- [x] Verify all nine findings against 19b1987c; fix eight, skip the already-fixed
  Peppol character-code guard with its exact revision reason.
- [x] Preserve Enterprise eligibility, custom-provider URL equivalence, safe
  opaque-ID lookup and retry custody, accepted-only capability and key-origin pin.
- [x] Hold unresolved companies before retention batching and locked cleanup;
  clarify C5 in five guides and allow native Enterprise-only key updates.
- [x] Independently accept QA/security, including five new PostgreSQL races and
  the URL-comparison compatibility regression; full frontend 3,058 / 236 passes.
- [x] Fix actual PHP CI blocker by deleting two obsolete baseline entries; final
  full-project PHPStan, lint/type/build/API/locale/FAQ checks pass.
- [ ] Push corrections on existing PR442 and verify exact-head Node and PHP CI.

No authenticated provider operation, preview reset, merge or deployment.

## PR442 Node CI correction - 2026-11-13

- [x] Verify actual failed Node Lint log and fix the equivalent ASCII guard.
- [x] Cover all 33 rejected control codes at boundaries and inside an address;
  preserve fallback, opaque identifiers and unchanged lint/type ratchets.
- [x] Pass full lint, targeted 176 / 4, typecheck and independent parser QA.
- [ ] Push the reviewed correction and verify Node on the new PR head.

No backend/provider/credential/preview changes or deployment in this repair.

## Automatic ePostman status integration - 2026-11-12

- [x] Add optional server-only encrypted Enterprise credentials with explicit
  removal, safe local bridge retries and independent status generations.
- [x] Bind new attempts immutably; keep unbound legacy submissions portal-only
  without backfill, duplicate sends or altered retry UUID/body.
- [x] Implement fixed-origin GETs, separate delivery/C3/C5, atomic projections,
  Enterprise-only historical refresh, five-minute scheduling and bounded backoff.
- [x] Implement five-language setup/display and thirty-second visible cached reads.
- [x] Independently accept QA/security and resolve revocation, sharing, migration
  recovery and non-Peppol completion findings; preserve existing previews/data.
- [x] Main actual app reads exactly two retained TEST receipts, both UNCONFIRMED,
  C3 TIMEOUT, C5 OK; both projection modes and immediate cooldown replay pass.
- [x] Pass scoped PHP, actual PostgreSQL races/migration, final build, static,
  type/locale/API checks; retain inherited lint/deprecation limits explicitly.
- [x] Finish full frontend verification: 3,019 tests / 236 files pass.
- [ ] Prepare separate outbound publication/changelog and release readiness.
- [ ] Confirm production activation/contracts/archive/export and approve deployment;
  authenticated public webhooks and another provider are separate milestones.

No production/provider mutation, new document, owner-preview change, push, merge
or deployment is part of this local milestone. See the automatic-status note.

## Enterprise status preflight - 2026-11-12

- [x] Prepare and independently review hidden TEST-key capture with separate
  owner-only storage, no echo/overwrite/network and durable cleanup.
- [x] Owner saves the key; offline bound-input check passes with zero HTTP.
- [x] Independently review fixed GET-only private probe and synthetic tests.
- [x] Read exactly the two existing RCMD receipts: HTTP200 and exact binding pass;
  UNCONFIRMED, offline-classified C3 TIMEOUT and C5 OK remain separate from
  confirmed recipient UI arrival.
- [ ] Implement per-company encrypted Enterprise-key setup and status reading
  without treating SAPI202 processing as delivery, MLS or tax approval.
- [ ] Verify authenticated webhooks separately when a public HTTPS callback exists.

No new provider documents/ACKs/webhooks or application/production changes.

## Replacement Recommand test-network route - 2026-11-12

- [x] Owner registers the replacement test-only DIČ; independently verify fresh
  authoritative TEST DNS and both Recommand AS4 document registrations before send.
- [x] Preserve prior state; prepare a separate private RCMD fixture/key/database.
- [x] Independent narrow QA/security accept frozen harness; offline 26 checks pass.
- [x] Actual new Invoice/CreditNote HTTP202 and exact-request/UUID/provider-receipt
  recovery pass, followed by two zero-HTTP cached replays.
- [x] Independent actual-capture audit passes 59 checks on unchanged ee5bc3b8.
- [x] Owner confirms both new RCMD documents in the intended Recommand inbox;
  update five-language guides without claiming production or signed MLS proof.
- [ ] Separate outbound PR/changelog; automatic status requires verified
  Enterprise access or authenticated webhooks. Production gates remain separate.

No new runtime edits or product-suite reruns in this follow-up. Existing owner
previews and old sandbox evidence remain preserved; no push, merge or deployment.

## Actual ePostman outbound test-network follow-up - 2026-11-12

- [x] Correct literal participant parsing, SK ICO 0158 and legal-name export.
- [x] Require actual SK parties and matching 0245:DIC before provider HTTP and
  during fresh locked server-document validation; preserve generic providers.
- [x] Preserve the initially rejected run; register a separate synthetic receiver
  in the owner's real Peppol Test Network Playground without moving the real ID.
- [x] Send new Invoice/CreditNote; verify real HTTP202, identical-wire/UUID/provider
  receipt replay after controlled response loss and zero-HTTP cached results.
- [x] Independent QA 69 / 324 plus 14 / 26 / 59 private checks, security 9 / 20, main 81 / 383,
  scoped Pint/PHPStan and prior frontend/type/build/API checks pass.
- [x] Verify owner-reported C3 OK and public TEST route advertising Flowis G3 Test;
  the intended Recommand inbox remains empty. Preserve this diagnostic boundary.
- [x] Register the new test-only DIČ verified absent in authoritative TEST DNS;
  confirm its AS4 route before sending and verify intended receiver arrival.
- [ ] Publish separate outbound PR/changelog after the bounded evidence handoff.
- [ ] Verify Enterprise or authenticated webhook status tracking separately.
- [ ] Production activation, contracts/archive/export and deployment approval.

All new sends use sandbox credentials and separate private state. No owner
preview/database changes, production requests, merge or deployment.

## Manual ePostman outbound milestone - 2026-11-12

- [x] Implement manual SAPI Invoice/CreditNote sending on the separate outbound
  branch based on PR441 receiving head d7e2063e; preserve active previews/data.
- [x] Pin durable encrypted request/UUID before HTTP; serialize company, owner,
  credentials and issued-document fences; preserve unknown outcomes and rollback
  evidence, clear accepted request staging and suppress automatic sending.
- [x] Preserve issuer bridge isolation, source amounts and custom-provider status
  capacity; correct UBL ordering and prepaid balance, reject unsupported exports.
- [x] Independently accept QA/security: PHP 104 / 800 and final 43 / 215, frontend
  129 / 7, financial/malformed cases and 11 actual PostgreSQL scenarios pass.
- [x] Full frontend 2,953 / 235, UBL full-XSD 15 / 67, type/lint/build/API/locale
  and scoped Pint/PHPStan pass without ratchet changes.
- [x] Verify actual authorized sandbox Invoice/CreditNote sending and retry;
  owner confirms both arrive in the intended Recommand test inbox.
- [ ] Publish a separate outbound PR/changelog, retaining the receiving dependency.
- [ ] Add delivery/MLS/tax status tracking only with verified Enterprise auth or
  authenticated webhooks; SAPI submission acceptance is not delivery approval.
- [ ] Confirm production activation and archive/export terms before deployment.

The new branch is feat/efaktura-epostman-outbound. No provider send, master merge,
production configuration or deployment is part of local acceptance.

## PR441 verified review corrections - 2026-11-12

- [x] Verify all 14 findings; no skips. Apply minimal backend/frontend/Python fixes.
- [x] Independently validate PHP 84/725, frontend 113/8, Python 15, PostgreSQL 2, date
  zones and API contracts; final security 24 file scope accepted (`e862291c`).
- [x] Retain settings generation/committed original/tenant/credential/financial-lock
  guarantees and preserve the original manual preview/account.
- [x] Main type/lint/Pint/PHPStan/locale/widget checks pass without ratchet changes.
- [x] Complete final full frontend 2,872 tests/233 files and isolated build 7,659 modules.
- [ ] Publish corrective commits on existing PR441 and verify corrected exact-head CI.

Provider archive/contract and production activation remain separate. Evidence:
`EPOSTMAN_PR441_REVIEW_FIXES.md`; no new provider operation or master merge.

## ePostman GitHub handoff - 2026-11-12

- [x] Owner authorizes publication of reviewed receiving and guided setup.
- [x] Preserve the existing manual preview and prepare an isolated current-master
  publication branch; retain both histories in shared state documents.
- [x] Push `feat/efaktura-epostman-receiving`, create [draft PR441](https://github.com/webiumsk/satflux/pull/441)
  and add its reviewed receiving-only changelog note.
- [ ] Verify exact published-head CI; keep production/archive gates explicit.

## Guided ePostman customer onboarding - 2026-11-12

- [x] Owner confirms the local connection and authorizes a guided customer setup.
- [x] Add built-in fixed production/test choices, SAPI-client guide, identity and
  receiving status, and Expenses link with five-locale UI/user-guide parity.
- [x] Preserve per-company credentials, discard them on host switches, and retain
  encrypted synchronization retry evidence across reload and failed disabling.
- [x] Independent QA/security accept the twelve-file scope: QA76tests/6files,
  wizard35 final repeat, Chrome24 simulated-boundary UI checks, security6 and
  independent serializer/remount2 pass. Full lint/typecheck ratchets, parity,
  widget check and final isolated build pass.
- [ ] Owner reviews the updated existing localhost preview; preserve their data.
- [ ] Provider archive/export/contract verification and production activation.
- [ ] Confirm provider-supported delegated onboarding before offering one-click login.

See the feature worktree's `EPOSTMAN_CUSTOMER_ONBOARDING.md`. No publication or
production deployment follows from local acceptance. Preview still uses manual polling.

## Manual eFaktura localhost preview - 2026-11-12

- [x] Serve normal Laravel/Sanctum/Vue on isolated `127.0.0.1:8082` with own account,
  database/key, sessions and recovered originals.
- [x] Fix the actual inbox settings projection and prove the old regression.
  All 41 tests in 5 files, scoped lint, typecheck ratchet, locale parity and build pass.
- [x] Independent security and no-mock browser login/recovery/company migration,
  real imports/originals/signs/API guards/idempotency/reload pass.
- [x] Restore pristine human state with two pending originals and protected local
  login retrieval. Existing migration work retains its own `localhost:8080` origin.
- [ ] Owner manually tests the preview. New TEST documents can be polled through
  company eFaktura settings > Download incoming now, then inbox refresh.

The manual preview has no scheduler. Earlier cross-AP transport and isolated
full-browser restart evidence remain separate from the normal-UI run.

## Actual Recommand TEST to ePostman receiving verified - 2026-11-12

- [x] Owner sends one synthetic invoice from a new Peppol Test Network playground.
- [x] Actual Satflux client retrieves one incoming listing/detail/XML.
- [x] Actual poller establishes encrypted persistent custody before real ACK;
  one receipt, one ACK, zero failures, preserved after new backend process.
- [x] Independent Chrome/Evolu actual-original import/retry/amount/currency and
  exact-byte checks after reload/full restart; one expense and attachment.
- [x] Actual backend markImported twice after protected browser proof, one imported
  receipt and staging cleared. Browser HTTP interception is explicitly bounded.
- [x] Repeat actual receiving, durable custody/ACK, negative expense, original-byte
  browser restart/retry and backend handoff with one CreditNote. Its original XML
  references the previous test invoice; structured expense linking is absent.
- [x] Correct signed negative-valued CreditNote inversion with six regression cases
  and independent QA/security. Author26/174, independent QA11/25 and security6
  synthetic cases pass; corrected parser also validates the actual test original.
- [ ] Verify provider archive/export/contract and production activation separately.

The historical DNS lookup observations below are superseded by actual delivery.

## Fakturoid adapter from merged master - 2026-11-11

- [x] Verify PR #438 merge and freshly fetch master base `007b9c02`.
- [x] Start the new feature branch while preserving the current local preview.
- [x] Research official export/API contracts without provider business mutations.
- [x] Inspect owner-supplied native XML read-only: two unpaid invoices, three items.
- [x] Implement the bounded unpaid adapter, exact items/totals and source custody.
- [x] Pass focused regression and independent core security checks.
- [x] Pass final lint/type/build and five-locale parity gates.
- [x] Complete isolated browser custody/lifecycle QA, including full restart.
- [x] Obtain authentic partial/full payment XML evidence without real funds.
- [x] Complete exact payment expansion, 313 regressions and independent security.
- [x] Complete focused paid/partial browser restart/custody acceptance.
- [x] Owner confirms import works and accepts the official local logo.
- [x] Owner authorizes branch push only; PR creation remains with the owner.
- [x] Privacy/evidence binding accepts all 20 files; push source commit `c42aa652`
  and verify the remote feature branch.
- [x] Owner opens [PR #439](https://github.com/webiumsk/satflux/pull/439).
- [x] Verify owner merge of PR #439 at `bae0295f` (deployment unverified).
- [ ] Add the omitted canonical changelog source for PR #439 as follow-up.

The local preview supports verified unpaid, partially paid and fully paid native
invoices. Unsupported/inconsistent finance fails
explicitly before writes. See
[FAKTUROID_MIGRATION.md](FAKTUROID_MIGRATION.md).

## iDoklad XLSX + original PDF adapter - 2026-11-12

- [x] Start `feat/invoicing-idoklad-migration` from merged master `bae0295f`.
- [x] Inspect both workbook profiles read-only; require the updated 22 columns.
- [x] Confirm owner has XLSX/CSV only; inspect all four original Slovak PDFs.
- [x] Prove native text, five original lines, identities, VAT and payment semantics.
- [x] Implement strict XLSX, bounded PDF worker, exact join and immutable custody.
- [x] Enable local iDoklad source card with official static logo and five locales.
- [x] Correct physical-coordinate and active-worksheet relationship omission paths.
- [x] Pass final integrated 474-test regression, full lint and five-locale parity.
- [x] Complete independent security and synthetic browser/restart acceptance.
- [x] Complete final typecheck and production build after the corrections.
- [ ] Owner verifies the import in the local preview.
- [x] Owner authorizes branch push; PR creation remains with the owner.
- [x] Push source commit `1e44995a` and verify the remote feature branch.
- [x] Owner opens [PR #440](https://github.com/webiumsk/satflux/pull/440).
- [x] Correct all four verified review findings and pass scoped/local browser gates.
- [x] Complete final independent core/harness review with no open C/H/M findings.
- [ ] Inspect fresh PR CI/review findings before merge.
- [ ] Add the canonical PR-linked iDoklad changelog source before merge.

V1 supports the verified company-invoice XLSX/native-PDF profile. Every original
must match; no zero-tax summary discards the source VAT. CSV and other templates
remain unsupported. See [IDOKLAD_MIGRATION.md](IDOKLAD_MIGRATION.md).

## Feature published for GitHub review - 2026-11-11

- [x] Owner authorizes publishing the existing feature to the owned repository.
- [x] Final full frontend, combined backend, static and publication privacy gates.
- [x] Push the feature branch and open [draft PR #438](https://github.com/webiumsk/satflux/pull/438) targeting master.
- [x] Add the reviewed user-facing changelog with the canonical PR source.
- [ ] Inspect fresh GitHub CI and review findings before owner merge approval.

Local preview and source publication do not activate the feature in production.

## Zaprite migration accepted locally - 2026-11-11

- [x] Verify the owner-created Sandbox with a locally supplied read-only key.
- [x] Add a fixed-host ephemeral read bridge and complete API/JSON migration.
- [x] Preserve verified items, exact fiat sums and actual payment evidence;
  require explicit unsupported-currency exclusion and archive the whole JSON.
- [x] Independent security/QA, real browser restart custody and static gates.
- [x] Owner confirms Zaprite API import works in the localhost feature preview.
- [x] Clarify saved JSON provenance in all five languages.
- [ ] Native BTC precision support remains separate invoicing follow-up scope.

The user explicitly prioritized Zaprite after the SuperFaktura preview worked.
Fakturoid/iDoklad adapters remain future work. No merge/release is implied.
Evidence: [ACTION_CENTER_AND_MIGRATION.md](ACTION_CENTER_AND_MIGRATION.md).

## Local views load independently of optional relay work - 2026-11-11

The company list and Profile statistics no longer await unused archive payloads.
Verified nonempty local company lists load immediately outside pending recovery.
Profile shows local counts before relay usage, and explicit refresh can calculate
database size. Normal app-entry QA verifies the quota/blocked-archive case and
full snapshot custody. Owner verification on localhost is the next step. See
[ACTION_CENTER_AND_MIGRATION.md](ACTION_CENTER_AND_MIGRATION.md).

## Relay quota and local import confirmation corrected locally - 2026-11-11

The migration preview differentiates relay quota refusal from browser storage
pressure. A locally confirmed import can complete while the affected relay
reports its quota limit. Other storage errors and incomplete local receipts
remain blocking. The next step is owner verification on localhost and review
of the affected relay in the existing synchronization settings. See
[ACTION_CENTER_AND_MIGRATION.md](ACTION_CENTER_AND_MIGRATION.md).

## Migration storage-readiness and recovery follow-up - 2026-11-11

The local migration preview checks the current worker's schema before writing
and exposes finite storage-failure diagnostics. Known rejected contact and
audit-event writes stop immediately. Owner verification on the existing
localhost profile is the next step. Reopening all Satflux tabs loads the
current worker, and the same source files support verified draft continuation
and duplicate skipping. See
[ACTION_CENTER_AND_MIGRATION.md](ACTION_CENTER_AND_MIGRATION.md).

## SuperFaktura historical migration with verified deductions available locally - 2026-11-11

Owner preview includes the company action center and the SuperFaktura migration
wizard. Signed ISDOC deductions are preserved when line amounts, VAT and totals
reconcile. The next step is owner review of the local preview. The confirmed
source order after SuperFaktura remains Fakturoid, iDoklad and Zaprite. See
[ACTION_CENTER_AND_MIGRATION.md](ACTION_CENTER_AND_MIGRATION.md).

## First real sandbox transport check passed - 2026-11-10

- [x] Authenticate with actual owner-configured ePostman sandbox credentials.
- [x] Fetch actual receive listing: one page, empty inbox, no provider ACK/send.
- [ ] Obtain a synthetic incoming invoice/credit note and verify actual detail,
  XML identity, durable custody and local import in an isolated persistent test app.
- [ ] Verify cross-AP and archive/production gates separately.

Successful authentication and an empty inbox do not complete receiving validation.

## ePostman read-only sandbox verification prepared - 2026-11-10

- [x] Prepare hidden local credential capture and isolated fixed-host auth/list/detail
  diagnostic with no sending, acknowledgement, import or persistence.
- [x] Security accepts the five-file diagnostic snapshot; offline helper/probe
  regressions cover echo fallback, permissions, isolation, bounds and redaction.
- [ ] Owner rotates the previously shared test secret and captures new SAPI values
  locally; do not copy credentials into chat/tool arguments or source.
- [ ] Execute the authorized read-only probe once private input is configured.
- [ ] Obtain a synthetic incoming invoice/credit note, then verify full durable
  Satflux import separately. Empty listing/authentication alone is not receiving proof.

## ePostman receiving implementation accepted locally - 2026-11-10

- [x] Implement fixed test/production receiving with company/XML validation,
  provenance, pagination/retry, safe custody and observable connection health.
- [x] Verify exact local original before server handoff; repair partial imports,
  preserve pending originals and provide readable view/download in five locales.
- [x] Independent security/QA accept36 runtime/test paths (`b8296faf`); targeted
  lockedPHP104/554, independent67/424, fullUI2325/209/finalwizard19, build/static/API
  checks pass. BroadPHP61 failures exactly reproduce on unchanged base/minimalenv.
- [ ] Verify actual cross-AP sandbox with securely supplied test credentials and
  provider archive/export/contract terms; no network interoperability is claimed.
- [ ] Resolve supported handling of originals above384KiB before claiming complete
  local handoff for all allowed provider document sizes; currently safe/pending.
- [ ] Obtain separate production approval, deploy frontend/backend/migration
  together, activate the correct SK participant and monitor the first receipt.

See the feature runbook. No paid plans, billing/entitlements, external repository,
production or readiness-claim changes. Existing primary work remains preserved.

## Slovak eFaktura delivery work paused; truthful public copy prepared - 2026-11-09

Owner approved withdrawing ready SK eFaktura marketing claims while retaining
existing integrations and file exports. Five-language copy and documentation are
corrected locally on `fix/efaktura-public-claims`; independent QA/security accept
18 source paths. Focused 87/9, lint 48/52, type 110, parity/build, FAQ 12/5/39,
changelog 14 and whitespace checks pass; QA 322/110 compiled messages and 8.75:1
warning contrast pass. See the feature worktree's
`docs/development/EFAKTURA_PUBLIC_STATUS.md` for scope and evidence.

Next allowed step is review of this bounded copy correction; merge/deployment need
applicable owner approval. Further SK delivery implementation waits for a separate
product decision, concrete provider/API terms and complete send/receive verification.
No replacement provider or delivery date is promised. No live change occurred.

## FAQ PR435 Czech precedence and label-depth corrections accepted locally - 2026-11-09

Three owner-supplied findings are verified against `70d57319`: two correctness
issues and one retry-coverage gap. Per-field Czech legacy search now follows
canonical blank/trim precedence without changing English predicates. Link labels
no longer consume an emphasis level for their container; actual eighth-level
formatting is asserted. Retry tests set both filters before failure and check
request parameters and retained controls after success. No finding was skipped.

Independent QA/security accept five exact source/test paths. UI83, SQLite38/403,
actual PostgreSQL11/100, scoped lint/Pint2/PHPStan, type110/parity, catalog12/5/39,
build and whitespace pass. Independent QA adds seven label cases; security13
focused frontend cases. Both original behavioral defects reproduce on the exact
base. Existing sanitizers/URLs/depth bounds, English search and filter grouping
remain. No dependency, schema, catalog, locale-key or baseline change.
Evidence: `docs/faq/REVIEW_FIXES.md`; private `reviews/faq-automation/pr435-round5/`.
Coordinator publication and exact-head CI remain the next step. The disposable
PostgreSQL target is removed. No merge or production command/deployment ran;
unrelated primary changes are preserved.

## FAQ live locale/admin/link fixes accepted locally - 2026-11-08

Owner-reported live failures are fixed on the existing FAQ branch from `85a633b5`.
Explicit allowlisted request locale now controls public list/detail/search/category
text with restored request context. The page reloads on language changes, preserves
filters and ignores/aborts obsolete responses, errors and timers. Admin categories
sort by primary order, canonical English JSON text and id, avoiding the PostgreSQL
JSON ordering error. Deep-scoped CSS makes injected links underlined and visibly
focusable. No global middleware, auth, catalog, schema or dependency changes.

Independent QA/security accept eight exact source/test paths. UI 83, PHP 38/403,
actual PostgreSQL 6/43, additional auth/locale/race probes, PHPStan/Pint 4, scoped
lint/type 110/parity/build/API/whitespace pass. Existing Chrome offline CSS proof
confirms underline/focus and 7.36:1 contrast; no browser install or live data access.
Evidence: `docs/faq/REVIEW_FIXES.md` and private
`storage/app/private/reviews/faq-automation/live-fixes/`.
Coordinator owns source commit/push and exact-head CI verification. The owner
reports an earlier deployment; this corrective revision is not deployed by the
agent. No production command/import ran; unrelated primary changes are preserved.

## FAQ PR435 deep emphasis matching accepted locally - 2026-11-08

The supplied finding is valid against `08cf0e62` and corrected in the existing
FAQ branch. Iterative logical frames and flat output tokens preserve delimiter
ownership beyond eight generated formatting levels, consume matched syntax and
retain ordinary unmatched markers. Label parsing uses one links-disabled child
call, keeping parser calls bounded to two; URLs, sanitizers and scan caching remain.

Independent QA/security accept two exact component/test files. Focused 68, QA 31,
security 18, actual 60 answers/75 links, scoped lint/type 110/parity/build/whitespace
and local JS documentation 9/9 pass. Deep 10,000-layer and malformed-input probes
pass. No backend, catalog, fingerprint, dependency or ratchet changed. See
`docs/faq/REVIEW_FIXES.md`; private evidence is under
`storage/app/private/reviews/faq-automation/pr435-round3/`.
Coordinator owns commit/push and exact-head CI verification. No merge, production
import, FAQ publication or deployment occurred; unrelated primary work is preserved.

## FAQ PR435 second review corrections accepted locally - 2026-11-08

All four owner-supplied findings are valid against `cc60c4b8` and fixed on the
existing FAQ branch. Complete opening-bracket labels match validator grammar;
links remain clickable throughout depth-capped input, without deeper recursion or
quadratic malformed-label retries. PHP/Node accept finite whole decoded JSON
numbers in 0..2147483647 consistently, with PHP integer normalization. The real
--check command test now uses default catalog paths with an unavailable database;
wrong-path and database-read mutation tests prove the regression assertion works.

Independent QA/security accept seven exact source/test paths. PHP 33/185, UI 52,
Node 60, unchanged catalog 12/5/39, PHPStan/Pint 3, scoped frontend lint/type 110/
parity/build pass. Independent raw-number and renderer attack/performance probes
pass; local documentation inspection is 45/45 PHP and 9/9 JS qualified functions.
No dependencies, thresholds, baselines or catalog fingerprints changed. Evidence:
`docs/faq/REVIEW_FIXES.md` and private `reviews/faq-automation/pr435-round2/`.
The coordinator owns corrective publication and exact-head CI verification;
prior-head CI/CodeRabbit results are historical. No merge, production import,
FAQ publication or deployment occurred; unrelated primary work is preserved.

## FAQ PR435 review corrections accepted locally - 2026-11-08

All six owner-supplied findings were valid against `8242c351` and are corrected on
`feat/faq-automation` for existing open PR435. Search predicates live in the public
controller rather than the model referenced by the review. Omitted category keys
retain assignment; explicit null clears. Czech search includes legacy `cz` only for
`cs`. PHP/Node source checks require per-item available translated counterparts and
reject order overflow beyond 2147483647. Bounded Markdown parsing restores nested
links and safe legacy HTML while retaining two sanitization passes and local URLs.

Independent security and QA accept exact corrective source manifests. PHP 30/136,
actual PostgreSQL 4/23, frontend 43, Node 20 and independent renderer/source attack
cases pass. PHPStan, Pint 9, scoped lint/type 110/parity/build/API checks pass. Local
AST/token coverage is 42/42 PHP + 8/8 JS qualified named functions with docstrings;
no external post-push CodeRabbit coverage pass is inferred. New PostgreSQL CI
coverage uses a separate test database and rejects skipped/error/failed JUnit cases;
the exact step passed locally. No thresholds, baselines or dependencies changed.

See `docs/faq/REVIEW_FIXES.md`; sanitized review records remain private under
`storage/app/private/reviews/faq-automation/pr435-corrections/`. Earlier broad PHP
suite/environment limitations remain historical; no new whole-suite pass is claimed.
Catalog 12/5/39 and its fingerprints are unchanged. No merge, production import,
FAQ publication or deployment occurred; unrelated primary work remains preserved.
Coordinator owns the corrective commit/push and exact-head remote verification.

## Multilingual FAQ automation accepted locally - 2026-11-08

Owner-approved FAQ automation is implemented on `feat/faq-automation` from owned
master `f7118b10`, isolated in `storage/app/private/worktrees/faq-automation`.
Twelve questions across four categories have EN/SK/ES/DE/CS text (60 variants),
75 safe documentation links and 39 reviewed source fingerprints. Offline CI checks
reject incomplete translations, unsafe links and changed documentation sources.
No AI provider or dependency installation is introduced.

`faq:sync --check` needs no database; default invocation previews. Explicit staff
attribution and `--apply` create drafts transactionally. Stable keys and baseline
hashes protect manual edits and unmanaged slug collisions; publication state,
counters and extra locale text are preserved. Nullable metadata migration never
imports/publishes content. Canonical Czech uses `cs` with preserved legacy `cz`
fallback. The editor retains omitted languages; public answers allow sanitized
local documentation links and expose accordion keyboard state.

Independent security and QA accept final 23-path implementation scope. Focused
PHP22/115 plus independent real-catalog4/36, UI22 and Node8 pass. Full frontend
2,231/205 passes with two workers after an earlier contention-limited run.
Type110, lint48/52, locale/build, scoped Pint/PHPStan and API-contract checks pass;
nine obsolete FAQ baseline entries are removed with no new suppressions.
The broader PHP run completed with 27 errors/11 failures across 3,169 tests.
All 32 bank cases pass after supplying the existing compatible Node/runtime; six
DNS-dependent e-mail failures reproduce on unchanged master without networking.
No clean whole-PHP-suite rerun or remote CI result is inferred. Full evidence and
limits are in `docs/faq/VALIDATION.md`; sanitized independent records stay private
under `storage/app/private/reviews/faq-automation/`.

Existing legacy-only Czech search remains limited to canonical Czech/English;
new imported entries have `cs`. Some documentation translations use English
fallback. PostgreSQL concurrency and real-browser checks were not executed.
No production import, publication, merge or deployment occurred. Primary unrelated
changes are preserved. Next step: review source changes, then separately approve
normal deployment/import and editorial publication through existing admin tools.

## Per-relay protocol status and Freedomrelay defaults - 2026-11-07

Owner requests separate connection/synchronization status and chooses Satflux plus
Freedomrelay defaults after compatibility verification. Both public endpoints reply
with wire1 in a read-only version challenge; no key, subscription or data is sent.
This does not establish quotas, write acceptance, retention or uptime. Freedomrelay
publishes fair-use/no-uptime terms without a numeric cap; free.evoluhq.com remains
a testing service whose data may be deleted and is no longer automatically offered.
Explicit saved lists/order/off flags and legacy custom-only selections remain exact.

Worker observation uses bounded public SDK header primitives, internal owner-ledger
isolation and finite origin/status/timestamp output. Socket-open and protocol quota
are separate. Only a fresh complete same-owner empty reply clears its error;
unrelated owners/reconnect/partial replies cannot. A reply never proves full backup
or replay of previously rejected history. Profile current warnings use live guarded
protocol evidence; global SDK errors are explicitly historical. Offline paused
status leakage is independently reproduced then fixed. Prior authority/SDK limits
remain; the old confirmed keyless default exception is preserved only in its prior
scope, including build/device-off/account guards.

Security35/4 accepts runtime33 `1feebae8...47cc16e`; final source65
`6626058d...da868aa` differs from tested source only in six reviewed text paths.
QA115/6, actual SDK/SQLite1 and final-CSS browser12 pass; provider proof is version
only. Main frontend2,207/204, PHP119/767, type110, lint48/52, locale/build,
Pint/PHPStan2, API3/changelog11 and7 rendered guides/12 links/3 fallbacks pass.
Local AST documents108/108 qualified named functions; no external pass is inferred.

PR432 is open/unmerged at reviewed base `6e747b30`. Main owns scoped publication
and fresh exact-head CI verification; merge/deployment remain separate gates.
Private sanitized evidence: `reviews/evolu-custom-relay/protocol-status/`.
Unrelated primary work is preserved; no dependency, baseline or production-data change.

## PR432 recovery enrollment CI correction - 2026-11-07

CI42353889750 on `4fab39c6` failed only the unchanged recovery-enrollment E2E:
a Profile wizard opened for A submitted refreshed account B (expected409, got200).
Independent browser reproduction confirms the failure. The wizard now preserves
its opening account/flow; stale completion cannot bind local phrases or update a
later flow. Optional current-context guards stop new owner/snapshot work after
invalidation, preserving existing callers. Already submitted work cannot roll back.

Independent security29/4 accepts runtime32 `74de89b0...5fa619` / source62
`49872677...6499c9`; QA84/7 focused checks pass. Every frozen byte matches.
Main lint48/52, type110, locale parity and CI-flags production build pass.
Main frontend2,179/203 and browser24pass/3existing conditional skips pass without retries.
Original409 and subsequent enrollment/restore/logout checks pass unchanged. Main owns
scoped corrective publication and new-head CI verification.
PR432 is open/unmerged; merge and deployment remain separate pending owner gates.
Evidence: `EVOLU_RELAY_MANAGER.md` and private `reviews/evolu-custom-relay/ci-recovery/`.

## PR432 relay origins and canonical cache review - 2026-11-07

Both outside-diff findings reproduce on published `b84d82a6` and are corrected.
Equivalent IPv6 origins normalize before list uniqueness/persistence; accepted
full-IPv4 trailing-dot aliases normalize consistently too. Cached selections never
authorize startup/restored owners without a fresh canonical account read. Unknown
or stale responses remain paused; device-off and account/read ordering are retained.

Reviewed runtime30 `40db84da...b8d22d` / source57 `c29b6fa7...92a622` match the
independent security/QA scope before and after checks. Security20 integration cases
plus900 origin/90 duplicate comparisons pass; QA16/3 including actual SDK/SQLite
and auth races passes. Main frontend2,156/202, PHP110/737, type110, lint48/52,
locale/build, scoped Pint/PHPStan, API/changelog11 and7 rendered guides pass.
Local AST documents91/91 changed complexity-qualified named functions (81 runtime);
this is local evidence, not a new external docstring coverage result.

PR432 remains open/unmerged; review base is `b84d82a6`. Main owns scoped corrective
publication and exact-head CI verification. Merge and deployment remain separate
pending owner gates. No dependencies, ratchets, billing or unrelated primary work
change. Contract, review limits and private evidence: `EVOLU_RELAY_MANAGER.md` and
`reviews/evolu-custom-relay/cache-confirmation/` in the feature worktree.

## Multi-relay manager independently accepted - 2026-11-07

Owner-authorized list/add/detail/enable/remove replaces the single-custom form.
Defaults are `wss://evolu.satflux.io` and `wss://free.evoluhq.com`; existing
custom-only preferences stay exact. Canonical nullable `evolu_relays` rows preserve
explicit empty/off selections, with strict bounded validation and account CSP.
Real SDK worker sockets supply per-relay state through origin-only observations;
Connected never asserts a complete backup. Same worker/SQLite identity is retained.

Independent security accepts19 TS/SFC +30 PHP cases; QA accepts3 SDK/Profile tests,
8 real Chrome worker/OPFS cases and10 responsive/keyboard UI cases. Six verified
review findings are closed, including stale relay revival, device-off resume,
missing-cache pre-auth exposure, queued status replies and SDK owner-query redaction.
Reviewed runtime30 `ebae93dc...18ac3c` / source54 `f9ca87d8...cd60a8` match before
and after independent checks. Main frontend2,129/201, PHP102/693 (relay/CSP/API +
guide selections), type110, lint48/52, locale/build, Pint/PHPStan/API/changelog pass.
Local AST documents87/87 changed complexity-qualified functions, including78 runtime
functions. No dependency, threshold, billing or external repository changes.

PR432 is open at prior published `da17233a`; main owns the scoped corrective
publication and exact-head verification. Merge/deployment remain separate pending
owner gates. Full contract, compatibility limits and private evidence are in
`EVOLU_RELAY_MANAGER.md`; unrelated primary work is preserved.

## PR432 second review accepted locally - 2026-11-06

All four findings are valid and corrected against published `ee294579`: reject
bare hexadecimal numeric hosts, require recovery backup before closing tabs,
cache successful public derivations by weak owner identity, and allow default-only
keyless profiles after explicit account-bound local owner confirmation. Retained
other-account owners, custom destinations, stale callbacks and device-off remain
guarded. The session proof contains only public account ID/key provenance.

Independent security/QA accept runtime20 `67ba4a4d...cdf5a9` / source36
`b93cdd7e...a0e814`, with all bytes verified before/after. Security56 TS +20 Profile
+23 PHP and QA26/3 pass, including actual SDK/SQLite and Profile race coverage.
Main frontend2,052/196, focused87/4, isolated PHP69/481, Pint/PHPStan, type110,
lint48/52, locales/build/API/changelog pass; local AST documents37/37 changed
complexity-qualified runtime functions. No external docstring success is inferred.

PR #432 remains open; main owns corrective publication and exact-head verification.
Prior-head CI passed; new-head checks need separate evidence. Merge/deployment are
pending owner gates. Full contracts/limits/evidence are in `EVOLU_CUSTOM_RELAY.md`
and private `reviews/evolu-custom-relay/second-review/`. Unrelated primary work,
old-worker restart guidance and existing test ratchets are preserved.

## PR432 review fixes independently accepted locally - 2026-11-06

PR #432 is open. These corrections were verified from published review base
`3b0d1e1c`; merge and deployment are separate pending gates. All five supplied inline findings and the cache-writer test nit
are verified still valid and corrected. Secure numeric-host validation rejects
trailing-dot aliases; Profile probing preserves compatible configured fallbacks.
Status records acknowledge the PR; five guides/notifications require loading
settings, closing all Satflux tabs and reopening.

New workers boot local-only and managed public owner registrations reconcile
canonical relay preferences across cooperative tabs. Atomic URL/mode/public-owner
association guards prevent cross-account or torn-cache destination routing;
unknown canonical identity pauses synchronization. No mnemonic/private key is
persisted or sent by this mechanism. Old/crashed worker references require the
close-all-tabs step; already in-flight protocol traffic has SDK100ms disposal grace.

Independent QA/security accept runtime19 `cbef0059...c8e22` and source34
`b878983d...754c84`. QA actual-source helper/real SDK+SQLite scenario1 and SDK5,
security12/1, author79/6 and main43/2 pass; selections overlap. Main full frontend
2,024/195, PHP64/458, scoped Pint/PHPStan, lint48/52, locale and build pass.
Final typecheck110 passes after two erased test-type assertions. The test's
emitted JavaScript is byte-identical; no baseline/runtime change is introduced.

Local AST audit documents all26 changed complexity-qualified named TS/Vue and
all3 PHP methods. This is local evidence, not a fresh external docstring result;
the prior CodeRabbit76.92% warning covers older source. No threshold is weakened,
CLI installed or manual bot command issued. Full findings, contracts, review
manifests and limitations are in `EVOLU_CUSTOM_RELAY.md` and private review evidence.
Main owns scoped source publication to the existing PR; no PR merge/deployment.

## PR432 master conflicts resolved and published - 2026-11-06

Integrate master `b4c2270d` (merged PoS PR431) into relay head `30a9363e`
without rewriting published history. Only PROJECT_STATE, ROADMAP and DECISION_LOG
conflict: retain both complete parent prefixes and their common history exactly
once. All30 reviewed relay source/test/doc bytes retain runtime16
`5bbc13eb...dc39d7` / source30 `4594a9d3...beb9f`; all12 nonconflicting master
paths retain master bytes. The feature diff remains exactly35 paths.

Independent QA `/root/relay_conflict_qa` accepts this content-preservation proof.
Prior relay security acceptance remains applicable to unchanged relay bytes; this
is not a new semantic/security review of unrelated source. Main PHP182/1408,
type110, lint48/52, locale parity and production build pass on the combined tree.
Full frontend1,998/194 also passes. The inspected PHP target is network-disabled,
SQLite-memory with synthetic uncached configuration. No dependencies/ratchets change.

Conflict resolution is published as `3b0d1e1c` and PR #432 is open. Its remote
head and mergeability were verified separately; new-head CI requires its own
evidence. PR merge and deployment remain pending owner approval.

## Evolu relay source publication authorized - 2026-11-06

The owner requests pushing the independently accepted additional-relay feature.
Prepare one scoped commit on `feat/evolu-custom-relay` in the owned Satflux
repository, then verify the actual remote tip. Product bytes remain runtime16
`5bbc13eb...dc39d7` / source30 `4594a9d3...beb9f`, accepted independently by QA
and security. Exactly35 paths include those30 reviewed source/test/doc files and
five coordination records; no unrelated primary edits or private fixtures belong
in the commit. Prior frontend1,940/192, PHP58/432 and static/build/API results
remain attributed to the reviewed bytes; no product checks are repeated for this
publication-only documentation update.

Source publication is complete and PR #432 is open. Merge and deployment remain
separate pending gates; fresh remote checks require actual-head verification.
The primary checkout remains on its existing branch with unrelated work preserved.

## Additional custom Evolu relay accepted locally - 2026-11-06

Custom + default synchronization now covers account settings, boot and both owner
types, with secure origins, unchanged keys, legacy custom-only/device-off safety
and five-locale UI/docs. QA/security accept final runtime16 `5bbc13eb...dc39d7`
and source30 `4594a9d3...beb9f`; no unresolved finding remains. Main frontend1,940
/192, static/locale/build, PHP58/432 and API export/schema gates pass. Real installed
SDK tests cover outage, additional-only restore, reconnect and protocol mismatch.

Retain the published `feat/evolu-custom-relay` worktree for review in open PR #432.
Merge and deployment remain pending. See its `EVOLU_CUSTOM_RELAY.md` for exact
evidence, excluded offline-DNS cases, protocol simulation and live-backup limits.
Apply separate applicable approval before merge/deployment.

## PR431 seven review findings corrected and independently accepted - 2026-11-06

All seven supplied findings reproduce on published38e03295 and are corrected.
Raw detail completeness and Unicode-aware product titles are validated before
cached overlay; only genuine list paths opt out of full-detail confirmation.
Both aliases reject invalid input with422 before normal or legacy merging/API
work. Valid legacy aliases retain JSON template transport and explicit clears.
App-list writes honor the caller guard; failed reads retain existing Delete
without Save. Original category arrays and post-submit same-app drafts survive.
The legacy bypass and category-ID reuse regression found independently are closed.

Independent QA accepts JS66/3, PHP135/1014 (including its extra legacy proof),
and Chrome10/10. Security accepts the same frozen runtime7 `fe560b24...79bec`
and source11 `1394cad4...50f02`; no unresolved finding remains. Main full
frontend1,969/193, PHP218/1407, lint48/52, type110, five-locale parity, build,
scoped Pint/PHPStan and AST update pass. Earlier superseded frontend evidence
is excluded from these final-head claims. No dependency or ratchet changes.

Publication uses the existing owned PoS branch; verify remote head and new CI
before claiming external success. Resolve only the seven verified review threads.
No PR merge, deployment, production state or unrelated primary edits are changed.

## PR431 base conflicts resolved and independently accepted - 2026-11-06

Integrate master `2154101e` (merged landing PR430) into published PoS head
`5a105124` without rewriting its history. AppsShow retains the exact master
template and published PoS script plus AppScrollPane import. Both prefixes in
PROJECT_STATE, ROADMAP and DECISION_LOG are preserved with common history once.
Other five PoS runtime files and all88 auto-merged master paths retain parent
bytes; the combined PR still changes exactly13 scoped paths versus master.

Independent QA accepts focused71/3 and Chrome18/18, including desktop/tablet/mobile
scroll/footer geometry. Independent security accepts the exact merge and focused45.
Main full frontend1,948/192, PHP127/784, lint48/52, type110, locale parity,
production build and AST update pass. Runtime6 `4a40f576...5cf9fb` / source9
`42586cbb...4252e6` match the reviewed merge. PHP source is unchanged; earlier
Pint/PHPStan evidence carries forward. No new dependency or ratchet changes.

Publish the resolved feature branch under the owner's conflict-fix request, then
verify the actual remote head, PR mergeability and new-head CI separately. No PR
merge or deployment is authorized or performed; preserve unrelated primary work.

## PoS settings preservation accepted before publication - 2026-11-06

The owner conditions source publication on preserving the other settings. A pinned
BTCPay v2.4.7 audit finds missing formId/customTipPercentages request fields and
untouched empty/null values replaced by UI defaults. Both are corrected. All20
writable PoS base fields are covered; metadata remains excluded. Confirmed save
snapshots advance from immutable submitted values, preserve later edits and reject
obsolete app/detail responses. Explicit edits, clears and normalized flags remain.

Independent QA accepts Chrome15/15 with actual PHP request building and PHP43/386.
Independent security accepts focused37/37 and boundary8/8, with no unresolved
finding. Runtime6 `1721c2cb...477cd7` and source9 `60687a9e...aefaa3` match both
reviews. Main PHP102/560, full frontend1,891/186, lint48/52, type110, locale,
production build and AST update pass. Contract, normalization and full review
evidence is recorded in `POS_EDITOR_ROUNDTRIP.md`.

Tax3 fields have cache/payload preservation evidence, not official host support.
Legacy customer-data selector compatibility remains separate; existing formId is
preserved. Production state/version is unqueried. Publish only the owned PoS fix
branch with the verified final gates; no merge/deployment is authorized. Preserve primary edits.

## PoS editor product and tipping hydration accepted locally - 2026-11-06

Branch `fix/pos-editor-roundtrip` is based on owned master `e584490d`; the affected
source matches the owner-reported deployed candidate `76422c07`, without inspecting
production. AppsShow passed list metadata as complete editor props, suppressing the
detail fetch. It now retains the selected detail separately, rejects stale route
responses and requires complete settings before editing. PoS cache/update overlays
preserve omitted fields, false/empty values, product aliases and canonical tip text;
failed or malformed reads block unsafe saves. Customer-data request/formId remains
a separate pre-existing contract audit.

Independent QA/security accept identical runtime6 `db71578a...710df5` and source9
`57e944e5...7165e9`, without unresolved findings. Real Chrome10 scenarios plus a
stable-ID roundtrip, focused frontend25, full frontend1,879/186, PHP96/320, targeted
Pint/PHPStan, lint48/52, type110, locale parity, production build and AST update pass. Full evidence
is recorded in `POS_EDITOR_ROUNDTRIP.md`. All tests use synthetic
isolated state; production host/version and merchant state are not validated or
changed. No source publication, merge or deployment has occurred for this fix.

The correction is ready for owner-directed publication; retain
separate merge/deployment approval. Preserve unrelated primary checkout edits.

## Publish accepted PR430 review corrections - 2026-11-06

Keep the accepted runtime15/source-tests22 manifests and bounded six-document
handoff. Publish the corrections on the existing owned `feat/landing-showcase`
branch, verify its remote head and observe actual new-head CI/CodeRabbit state.
Full local frontend1,910/191 plus final focused53 and footer16, PHP44/279 and all
recorded lint/type/locale/build/Pint/graph/browser gates pass. Preserve thresholds,
public headers and unrelated primary work. Remaining header/navigation redesign,
merge and deployment stay outside this review-fix scope.

## Publish the accepted landing showcase source - 2026-11-06

The owner authorizes commit/push of the existing reviewed 82-path feature scope.
Preserve the accepted runtime/source behavior, allowing only the documented
two-space blank-line normalization, and preserve the dirty primary checkout
and verify the resulting owned remote branch. Retain all local test/review evidence
without inferring fresh CI or deployment. Subsequent navigation, header, type-debt,
PR, merge and deployment actions remain separately bounded.

## Landing showcase and footer ready for source handoff - 2026-11-05

The accepted scope is the V6 Vue showcase, reversible environment-only homepage
selection with matching first paint, five locales and desktop footer placement.
Main integrates server/bootstrap/router contracts; frontend specialists own the
showcase and audited scroll hosts; independent QA/security review actual files.

Frozen-scope QA/security reviews and local gates are complete: frontend1,882/188,
PHP44/279, cache switch/rollback, lint/type/locales/build/Pint/PHPStan and AST graph.
The isolated `feat/landing-showcase` worktree retains all local uncommitted changes.
Source publication and release actions remain separate. No admin selector,
whole-app Inertia migration, billing change, merge or deployment is included.
Follow-up navigation ownership, shared props/auth, public indexing/performance and
frontend type debt are proposals in the implementation contract.

## Publish accepted PR429 second-review fixes - 2026-11-05

The new performance, Node-range and server linked-status findings are repaired
and independently accepted. Runtime45 `2fcb0006...25c73a4e`, source/test/CI72
`dbbcab19...863b81c7`; source is frozen. Full frontend1,854/185 and typecheck,
lint/build pass; backend170/867 and independent semantic/security controls pass.
Keep exact linked-error skipping and truthful bulk counters; no blanket exception
swallowing or independent payment-history changes. Commit/push the scoped delta
from `714e078b`, then verify actual new-head CI and CodeRabbit state. Existing
historical/old-client limits and the owner merge/deployment gate remain.

## Deliver reviewed PR429 remediation - 2026-11-05

Implement and independently review the owner-requested PHP audit/CodeRabbit fixes
on the existing expense feature branch. Local gates pass: PHP: 2,902; PostgreSQL: 4/74;
frontend: 1,830/183; typecheck111; lint48; locale/build/API/Pint/PHPStan. Reciprocal
claims cover new manual, automatic and bank-created expense writes, including
explicit recovery and unchanged zero/negative bookkeeping edits. Preserve legacy
claimless history and current-owner raw backup restoration.

The 74 added docblocks are proved executable-equivalent to accepted logic.
Publication manifests: runtime44 `b89dbb70...366899`, source/test/CI68
`4d52bf9c...1e0013`. Next: main commits/pushes these exact bytes, verifies the
remote tip, actual new-head required CI and CodeRabbit semantic/docstring state.
Staging also removes exactly one redundant EOF LF from the new claim helper;
AST/emitted JavaScript remain identical.

Only a separate applicable owner approval can authorize merge or deployment.

## Publish reviewed expense reconciliation branch - 2026-11-05

Owner-authorized next step: commit/push the existing reviewed 51 paths to
`webiumsk/satflux:feat/expense-reconciliation`, then verify remote tip/tree.
Publication runtime23 is `620c7f00...956f2`, source/test/CI35 `c947959b...8557b`.
The only code-file difference from accepted bytes is one removed trailing LF;
documented behavior/tests are unchanged. PR, merge and deployment remain separate.

## Expense reconciliation ready for local review - 2026-11-05

Implemented user-confirmed full-amount outgoing matches to existing expenses and
advisory duplicate warnings in forms and mapped imports. Both storage modes,
shared-owner/origin safety, EN/SK/ES/DE/CS docs and copy are covered. Unlinking keeps
payment status; candidate evidence is rechecked before and after local writes.

Independent QA/security accept runtime23 `982868ca...5ef95` and source/test/CI35
`edf41386...46850`. Final frontend1,760/177, SQLite129/563 and actual PostgreSQL2/36
pass, with the historical deadlock reproduced and eliminated. The existing PG CI
job now enforces both contention cases. Static, locale, build and API gates pass;
six broad-PHP DNS failures reproduce on unchanged baseline and are recorded.

Retain `storage/app/private/worktrees/expense-reconciliation` on
`feat/expense-reconciliation`. Next: owner review, applicable source publication,
fresh CI and separate merge/deployment approval. No release/deployment occurred.
Relay redundancy, QR expense payments, partial payments and the remaining ideas
stay separate proposals. Full evidence is in the feature worktree's
`docs/development/EXPENSE_RECONCILIATION.md`.


## PR428 semantic review clean; supported test-helper documentation repair - 2026-11-04

The owner-authorized `@coderabbitai review` comment `6638746873` was posted after
the allowance reset at 18:30:30 UTC. Bot reply `6638749080` confirms acceptance;
check `126777122714` completes successfully at 18:33:23 UTC on `1e68623a`.
The actual walkthrough reports no actionable comments, exact-head reviewed coverage
and zero review threads. The earlier held-upload test finding is addressed.

One pre-merge warning remains: 0% documentation at the unchanged 80% threshold.
The first five Vue JSDoc additions did not cover the measured supported helper.
Author and independent QA inventories agree that `render()` in
`resources/js/__tests__/expenseFormDefaults.test.ts` is the only changed named
function in the three supported TypeScript test files. That matches the reported
one analyzed function; CodeRabbit does not explicitly identify its symbol, so the
attribution is a diff-based inference. A four-line JSDoc describes persistence/route
mode, the optional initial-copy marker and awaited hydration. No executable changes.
Independent QA accepts exact test hash
`8fba8d02cb3143faf9a3e39e49d6a74fed254a590ab54c47d0d2f7ae72548bf7`:
JSDoc attaches to `render()`, executable tokens, normalized AST, stripped TypeScript
and emitted JavaScript are identical to `1e68623a`. Scoped lint and whitespace pass.
The six-file source/test manifest is
`88d4515e64d9f9022a85d5bb8603c3e8e62bd23ebe62db77aed5ccb882b0f3ab`;
the three production files retain accepted manifest
`2842ec51ffb268f5e6e7b0fddc9add01ae07ae23e72e20cb088f332ce6351411`.
Publish this comment and three state documents, then verify fresh exact-head CI and
pre-merge results. Official CodeRabbit docs support the separate command
`@coderabbitai run pre-merge checks` to refresh configured checks in the existing
walkthrough: https://docs.coderabbit.ai/pr-reviews/pre-merge-checks#run-all-checks .
Its hourly allowance cost and cache guarantees are not documented; do not assume
either or hide the warning. All five prior CI checks remain green. No merge,
deployment, production access or configuration change.

## PR428 initial CI green; reviewed test and documentation follow-up ready - 2026-11-04

All five required checks completed successfully for `cf064146` in CI run
`41877739679`, attempt 1. The final verification at 17:53 UTC reconciles the
workflow and each exact-head check, including PostgreSQL's 2 tests / 108 assertions
and zero-skip gate. Earlier API snapshots reported inconsistent job states; they
are not evidence of actual queue duration or a failed test. No rerun or cancellation.

CodeRabbit review `5772913881` on that commit completed at 17:36:07 UTC with no
functional or inline finding, one held-upload test nit (`56789731b8f39c6664dd3162`),
and a docstring coverage warning of 0% against the unchanged 80% threshold.
Five meaningful JSDoc contracts and deferred-upload assertions in the existing
local/server test address both. Independent QA and security confirm that production
Vue tokens, normalized ASTs, emitted JavaScript, templates and styles are unchanged.

Both reviewers accept the four-file follow-up manifest
`afaafe54bc00b86cde400c4d34e940cbf2776b148fa4d962b5f41d22e5ad724d`.
The complete fix has runtime manifest
`2842ec51ffb268f5e6e7b0fddc9add01ae07ae23e72e20cb088f332ce6351411`
and six-file source/test manifest
`1e691c39af27e57e2ae3d3f5d7cdc70ef25f2e75bb2d793d631c5d23889ec244`.
Author validation passes 101 tests in three files, lint and the existing typecheck
ratchet. QA verifies both held-upload cases pass and an isolated mutant removing
only the upload awaits fails both. Publish these four files and three state documents,
then require fresh CI and actual CodeRabbit coverage of the resulting head. Invoke
`@coderabbitai review` after the allowance resets if automatic review is limited.
No threshold, authorization, financial, backend or CI configuration change. No merge,
deployment or production access is authorized by this milestone.

## Current: publish reviewed duplicate-expense defaults repair - 2026-11-04

On merged master80139525, four duplicated-edit failures are reproduced and fixed.
Both reviewers accept runtime3 9125a87c...41e10/source6 f7768cff...1a679; author and
reviewer101/3 plus28 independent DOM probes pass. Main1700/173, lint/type/locales
and build pass. Publish exactly nine scoped paths on fix/expense-form-default-events,
verify exact bytes/remote head, then wait for all current CI and actual CodeRabbit.
Keep normal edits/manual overrides, hydration, UPDATE-only copy saves and all
ownership/payment boundaries. No merge/deployment or production changes.

## Current: publish final test coverage and repeat the review gate - 2026-11-04

Actual review 5766899006 leaves two test-only nits; both are repaired and QA accepts
manifest 31757baa...1bb7e7. Client 122/122 and actual PG two/108, lint/Pint pass.
Runtime, migration and five-job CI configuration are unchanged. Publish exactly
six reviewed paths, verify tree/blobs/remote head, complete fresh CI and request
actual manual CodeRabbit review after allowance. Continue until all green; preserve
all production boundaries. No merge/deployment or production replay.

## Current: publish accepted bounded-candidate/PG CI repair - 2026-11-04

Both reviewers accept runtime4 2c1fb965...936bc / source6 e8ebc2c9...39ffa.
Backend and related SQLite tests, two independently repeated actual PG cases,
style/static/workflow/zero-skip gates pass. Publish exactly ten reviewed paths,
verify committed bytes and remote SHA, then wait for all five CI jobs and actual
manual CodeRabbit review after allowance. Preserve raw-scan/coverage distinction,
SQL snapshot semantics and all owner/Unknown/financial boundaries. Continue valid
repairs until all green. No merge/deployment or production action.

## Current: publish accepted scan-progress/index repair and repeat review - 2026-11-04

Both independent reviewers accept runtime4 0090c754...32557 / source6
d075ba2e...9c589. Main 1,675/173, build/static and backend 41/264 passed; query
plans and rollback are verified on disposable data. Publish exactly ten reviewed
paths, verify committed bytes/remote SHA, complete fresh CI including native E2E,
then request actual manual CodeRabbit review after quota. Preserve unresolved
obligations, strict owner/coverage/Unknown boundaries and ordinary compatibility.
Continue valid repairs until all green. No merge/deployment or production action.

## Current: publish accepted progressive-history repair and complete review - 2026-11-04

Runtime13 d4b6bcc4...ab51a/source-tests21 6c628d69...c232bf are independently
accepted. Main1666/173, build/static/locales and backend31/153 pass. Publish exactly
25 reviewed paths, verify committed bytes/remote SHA, then complete all fresh CI,
including four native IndexedDB cases unavailable locally. After full CI, request
actual manual CodeRabbit review and address valid findings until all green. Keep
strict coverage/owner admission, exact Unknown outcomes, ciphertext CAS and ordinary
client compatibility; backend deploys first or together. No merge/deployment or
production actions.

## Current: publish the independently accepted credential-order repair - 2026-11-04

Both reviewers accept runtime2 4304ae73...244a9/source-test5 3734a970...11f44 and main
1623/171/build/static/locales. The bounded runtime queue repairs confirmed
SEC-WISE-CONNECT-ORDER on green889e1e69. Preserve entered outcomes, revalidate queued
intent, share both modes/instances and keep independent bridges/status reads live.
Publish only nine reviewed paths, verify source/remote SHA, complete fresh CI and request
the next actual manual review after capacity. No backend/financial policy, production,
merge or deployment action is included.

## Current: publish the independently accepted ninth repair - 2026-11-04

Verify pre-metadata credential context, same-company transaction refresh, true
Wise import counts, separate status retry, bounded read-only resolution/status
and visible sync lookup failure. Parent3ac059d1 CI and pre-merge88.52% are green,
but actual review5763674984 has findings. Preserve entered remote/financial work,
default helper/API callers and trusted owner policy. Both reviewers accept final
runtime5 9d0e0c91...68b7e/source-test10 67dabf48...1d9d0b; main1609/170/build/static/
locales and independent QA/security pass. Publish only14 reviewed paths, verify
committed bytes/remote SHA, finish fresh CI and repeat actual manual review after
capacity until all green. No merge/deployment or production actions.

## Current: publish the independently accepted eighth repair - 2026-11-04

Verify five findings from actualc0921567 review5763268988, including supplemental
recorded-route and retry-presentation items. Preserve current bridge read budget,
financial completion/Unknown and default issuance semantics. Prefer trusted pinned
routes consistently for bank sync and displayed settings; fail closed for absent
shared owner evidence and await initial readiness within the read-stage budget.
Both reviewers accept runtime10 b1f3de86...a26c/source-test15 e08706b2...9052;
main final1579/169, build/static/locales, QA175/7+25actualVue and security authority/
final-delta probes pass. Post-financial follow-up cannot import another company's
email rows; invalid connect cannot falsely report success. Publish only19 reviewed
paths and verify committed bytes/remote SHA, complete fresh CI, then request actual
manual review after capacity. No merge/deployment or production actions.

## Current: publish the bank-view lifecycle completion guard - 2026-11-04

Independent mounted Vue reproduction confirms the disposed-view stale identity
entry gap before import. Both reviewers accept final runtime1 29becc3a...cce6 and
source-test2 d579efc0...bc43. Main209/10 affected tests, author71/4, independent46+7,
build/lint/typecheck pass. Publish exactly6 reviewed paths, verify raw bytes/remote
SHA, then wait for full current CI before manual review. Parent7e9 CI is green;
a paused check or old review does not complete the owner's all-green loop.
No financial write cancellation, new API/default changes or release action.

## Current: finish final R6 checks, publish and repeat review - 2026-11-04

One inline and two supplemental review items are repaired. Both reviewers accept
final runtime4 927a9d92...27a1/source-test7 a63822f8...2406 with identity, total-fetch,
metadata ambiguity/default allocator and real deleted-query regression evidence.
Main final fullfrontend1504/167, production build and lint48known/zero errors pass
on these exact bytes. Publish only11 reviewed paths, verify committed blobs/remote
SHA and complete exact-head CI. Request the next actual manual review after capacity. Preserve the before-
import identity boundary, unknown outcomes, all thresholds and deployment gates.
No merge/deployment, production replay or unrelated primary publication.

## Current: publish PR427 fifth repair and repeat review - 2026-11-04

The five findings are repaired and both independent reviewers accept final
runtime4 b7887cd1...7983f/source-test9 b33cfdeb...de663. Main1485frontend/165files,
QA112+14temporary, security101+14temporary/6PHP64 and all static/build/locales
pass with unchanged thresholds. Publish exactly13 reviewed paths, verify their
committed bytes/remote SHA and resolve the five threads. Then wait for full new CI
and actual review capacity before requesting another completed manual review.
Continue until findings/checks are green, preserving quarantine and all recorded
browser/device/transport limits. No merge/deployment or production replay.

## Current: complete PR427 documentation and manual review loop - 2026-11-04

All CI jobs pass on6ba7e908. Add genuine missing function contracts to address the
bot's retained docstring warning; preserve executable tokens, annotations, all
thresholds and ordinary error behavior. Independent QA accepts comment/metadata20
manifest75cab067...06b7 with exact TS/PHP executable identity. Publish only the
reviewed24 paths and wait for full fresh CI.
After the approximately01:30UTC reset, post the owner's exact requested
`@coderabbitai review` comment. Repair valid findings and repeat verification until
CI and actual review are green; a rate-limited neutral check is insufficient.
No merge/deployment or production access is authorized by this instruction.

## Current: publish PR427 fourth review fixes - 2026-11-04

All three new review items and two defects exposed by independent regression
checks are repaired on the existing owned branch. Both reviewers accept the same
runtime4/source-test10 manifests (8f71c874...bf1f0 / 8c24ef15...d6918).
Main full frontend1443/164, final build/lint/typecheck/locales pass with unchanged ratchets.
Publish only the reviewed14 paths, verify their committed bytes and remote SHA,
resolve the one new inline thread, and require exact-head CI. Preserve known
browser/device/import limits, current-company feedback and unknown-outcome
quarantine. No server, key, Store or billing authority change; no merge/deployment
or production replay is included. Prior CI validates f0849584 only.

## Current: publish PR427 third-round repair - 2026-11-04

HTML-only notification layout, refresh-error classification and interrupted-import
batch recovery are fixed. All inventoried critical expense writers, maintenance
and bank actions share one origin gate with fresh owner-aware preconditions and
worker completion proof. Final runtime41/source-test65 manifests are
a9478719...76ee7 /5f74ae7b...471c1. Main full1415 plus final merge3/affected35 and
independent native40/security50 pass; typecheck111existing/no-new, lint48known and
build/locales/API/Pint retain previous ratchets and documented limits.
Both reviewers accept all final hashes; publish the exact scoped branch, resolve
three new inline threads and verify new exact-head CI. No production replay,
server migration, new key authority, merge or deployment is included.

## Current: finish PR427 second-round review fixes - 2026-11-03

Four new inline findings and three follow-ups are implemented on the same branch:
strict bank date/amount fields, VS32 default, localized visible errors, Spanish
wording and batched row insertion with full post-handler identity proof. Main
frontend1382, backend262/1018, scoped bank92 and mounted UI39 pass; static/build/
locale/API gates preserve existing ratchets and the local Scramble limitation.
Independent QA/security accept runtime22 4b236b10...a7eb1 and source/test36
b33ad44a...19975 with no unresolved findings; QA131frontend/305PHP1160 and both
reviewers' actual-worker15 pass. Publish the reviewed32-path delta, resolve the
four threads and verify new CI. Retain the fixed-query/quarantine/legacy-device limits.
No production access, replay, merge or deployment is included.

## Current: publish PR427 review remediation - 2026-11-03

Seven CodeRabbit inline findings and five follow-ups are repaired and covered by
parser, delivery-null, Wise, SQL-lock and real Evolu failure regressions. Final
runtime17/source-test31 manifests are 78afb3fd...04eb0 / d52b3e00...0e686.
Independent QA accepts PostgreSQL251/805, contention3/42 and actual-worker12;
main full frontend1363, final Wise19, SQLite237/958 and static/build/locale/API
gates pass with existing ratchets and the known local Scramble dependency limit.
Independent security accepts the same frozen manifests with no unresolved findings.
Publish only scoped changes on PR427, resolve addressed threads and verify the new
commit's complete CI.
Same-browser matching requires updated cooperating clients; old tabs should be
reloaded at rollout. Keep other-device/legacy provenance limitations explicit.
No production replay, merge or deployment is included.

## Approved: publish the reviewed expense/bank repair branch - 2026-11-03

Owner requests push to owned Satflux GitHub. Publish only the36reviewed/scoped
paths on `fix/expense-defaults-bank-import`, retaining accepted source/test bytes,
and verify the remote SHA. Local tests/reviews remain attributed to those bytes;
no remote CI result is inferred. PR creation, its linked changelog note, merge
and deployment remain subsequent actions. Preserve the dirty primary checkout.

## Ready locally: expense defaults and bank-email expense matching - 2026-11-03

Continue owner review of `fix/expense-defaults-bank-import`, based on merged
PR426/686c9304 in the retained expense worktree. VS/date defaults, selected-company
bridge synchronization, recipient compatibility and exact email-debit matching
are implemented. Independent QA/security accept11runtime/21source-test manifests;
main1335frontend, SQLite225/740 and independentPG225/730 plus forcedcontention1/16
pass, with unchanged lint/typecheck ratchets, locales/build/API/guide checks.
Full PHPStan local dependency limitation is recorded separately, not a CI pass.

Production metadata confirms2email rows today, enabled intake and healthy empty
webhooks queue; selected-company correlation and actual UI recovery remain
unverified. Source is uncommitted/local, no new publication or deployment. Next:
owner review, then authorized PR/publication with its linked changelog note;
merge/deployment remain separate gates. Preserve all previous work and never
replay bank mail or broadly retry failed jobs as diagnosis.

## Current: publish accepted PDF recovery and quota follow-up - 2026-11-03

Six findings after green2567fed4 CI are fixed with user-locked quota accounting,
owned pre-commit cleanup, tombstone-aware retry, visible errors, protected
extraction and stale-state guards. Independent QA/security accept the same16-file
source; actual PG13/129, SQLite52/360, focused55 and full1228frontend pass.
Publish on PR426, resolve confirmed threads and verify fresh exact-head CI.
Keep prices, purchases, bulk, recovery and unrelated work unchanged. Merge and
actual deployment retain their separate owner gates.

## Current: publish the reviewed individual expense PDF upload repair - 2026-11-03

Fix local company IDs reaching legacy UUID routes with an authenticated company-
free ISDOC bridge, early UUID validation and local form/detail persistence.
Keep encryption, consent, quota policy,384KiB attachment cap and server ownership;
leave bulk import and purchases unchanged. Independent security and QA accept the
same frozen source after32frontend,13extra probes and47PHP/323assertions.
Full frontend passes 1,205 cases across 151 files. Publish on PR #426 and verify fresh
exact-head CI. Preserve all recovery/unrelated work; merge/deployment stay separate.

## Current: close expense snapshot fixture URL routing bypass - 2026-11-03

Snapshot/year repair `6b07e900` has four green CI jobs and six resolved threads.
Fix the newly confirmed test-only URL/routing target override with allowlisted
configuration, effective/actual identity proof before DDL and ownership-gated
cleanup. Add configuration-only poison-target regressions; preserve runtime
source. Independently review, publish to PR #426 and verify the new head's CI.
No actual expense import, PR merge or deployment is included.

Final independent QA/security accept the exact three-test-file guard. Pure
unit 9 / 59, SQLite 11 / 124 and PostgreSQL 11 / 155 pass, with driver skips;
production source is unchanged and dedicated test infrastructure is removed.
Next is reviewed branch publication and fresh exact-head CI.

## Current: publish the reviewed expense snapshot and year repairs - 2026-11-03

The prior summary head `a1b1c32b` has four green CI jobs. Address the two newer
CodeRabbit findings on the same PR #426: reset page/selection for either year
control, and keep summary/count/page serialization in one stable database read.
Use the accepted PostgreSQL/SQLite design and independent concurrency/recovery
reviews. Publish the bounded repair and verify fresh exact-head CI, including
full PHPStan with locked dependencies. Local PostgreSQL evidence remains
separate from normal SQLite CI. Preserve original backup and unrelated work;
PR merge and deployment require their existing owner gate.

## Current: publish reviewed cross-session expense summary follow-up - 2026-11-03

The owner-provided27-file candidate is adopted after manifest reconciliation,
new type-boundary/currency fallback fixes and independent security/QA acceptance.
The footer sums the entire current company/filter result across pages, keeps
currencies separate and reports whole unpaid documents as selected by the owner.
Receiving checks pass1171frontend cases,39focusedPHP/259assertions, fullPHPStan,
Pint, typecheck with unchanged baseline, lint/locales/build/API and four browser
scenarios. Publish to existing PR426, resolve verified review findings and verify
fresh exact-head CI. Keep immutable backup/primary work; no PR merge/deployment.

## Current: verify expense-import CI after removing one stale PHPStan ignore - 2026-11-02

All2555 PHP tests and Pint pass on a64f0bed; full PHPStan alone rejects an obsolete
arrayFilter.same baseline entry. A one-entry removal preserves the analysis
ratchet and all runtime source. Independent QA accepts the exact delta; full
local analysis reproduces before/fixes after. Publish this bounded repair and
verify the fresh exact-head CI result. PR merge and deployment remain separate.

## Current: verify expense import PR after documentation reconciliation - 2026-11-02

Owner-authorized conflict repair brings master `86e77e81` into the feature
branch while preserving the expense-import and upstream project records.
Verify the three-file documentation resolution, push without force and confirm
PR #426 is mergeable. Retain original source review/test evidence separately
from fresh CI on the synchronized head. No PR merge or deployment is authorized.

## Ready: reviewed expense import for owner review - 2026-11-02

Owner-authorized expense import is implemented on
`feat/superfaktura-expense-import`, base `9dfc4166`, in its retained named
worktree. Automatic current SuperFaktura 33-column mapping, original dates and
identifiers, private metadata, conservative payment status, company duplicates
and accessible import/result UI are independently accepted by QA/security.
Private52-row dry run, PHP51/294 plus docs8/26, lint/typecheck/locales/build,
scoped PHPStan/Pint and API contract checks pass. The full frontend suite passes
1,132 cases across147 files with two workers. Preserve the reviewed local source
for owner review. No actual data
import, external repository write, publication, merge or deployment is performed.
Keep the existing primary edits and other initiatives untouched.

## Current: restore stalled Chorala changelog synchronization - 2026-11-02

Independent QA/security accept the comparison-only CRLF fix, preserving stored
content, historical hashes and no-republish guards; all 71 offline cases pass.
The eight-fragment inventory includes missing merged #411/#413/#415 notes. Publish
the reviewed repair and verify exact-head CI. After owner-approved merge, master
CI prepares missing drafts; publication separately requires the exact confirmed
deployed SHA. New fragments cannot publish for an older deployed commit that
lacks them. No production retry, publication or deployment is performed during
preparation. See [recovery evidence](CHORALA_CHANGELOG_RECOVERY.md).

## Current: ship the observed exact Platba description - 2026-11-01

The private-bank-copy structure now identifies Platba as the only new operation
literal; reference shape and tail labels are already supported. Implement/test
this bounded parser extension with immutable old identities and all payment
proofs intact. Independent source QA/security, provider replay checks and main
API/style/static/hash checks pass. Publish and verify current exact-head CI. Provider read-only evidence protects same-reference label replay;
no plugin/schema/version change is necessary. New actual settlement remains
unverified; do not replay bank messages or request another real transfer.

## Current: qualify incomplete bank-credit evidence - 2026-11-01

Owner-reported new credit reaches incomplete_credit_evidence after origin checks,
before any provider reporting. No current original exists outside Mailgun logs.
The metadata-only failure projection preserves strict parser evidence and all
five eligibility predicates. Independent QA/security and API/style/static checks
pass; publish on the existing PR #415 branch and verify fresh head CI/review. A later normal notification can
identify the unsupported field/frame; do not require another transfer, restore
expired content or replay payment. Actual bank template and settlement remain
unqualified; do not infer a specific missing reference or account.

## Current: publish and verify the qualified ASCII proof profile - 2026-11-01

Independent QA/security accept the frozen narrow fallback for the owner-observed
plain UTF-8/7bit ASCII and relaxed/relaxed family. Preserve the legacy path and
all bank/recipient/amount/account/reference/provider/dedup gates. Publish on the
existing PR #415 branch and require current exact-head CI/CodeRabbit. Then owner
rollout and read-only retained-message recheck must establish actual frame
support. No expired-message restoration, financial replay or new transfer follows.

## Next: current CI and owner rollout for approved private capture - 2026-11-01

Independent QA/security accept the default-off feature on top of037a6fa7/PR414.
Publish the separate branch and verify fresh exact-head CI including Node22 and
real Redis concurrency. Compatible Redis7 and every webhook worker, keys and
backup-retention acknowledgement precede explicit operator arming for one Store.
No automatic activation follows source readiness. Capture the next eligible
normal bank notification for read-only diagnosis; do not require a real transfer
or relax bank proof. Current signing/layout/payment qualification remains open.

## Current: publish and verify PR414 review follow-up - 2026-11-01

All three initial CodeRabbit findings are addressed in reviewed source: optional
sender cannot veto otherwise valid MIME, Node checks use effective configuration,
and the optional compact-date change is removed to avoid new transaction loss.
Bank parser/dedupe/history are unchanged. Preserve legacy compact retry limits.

Author 155/623, independent QA 162/677 and security 50/334 pass; unchanged Node 42 and
12 private probes remain qualified at identical bytes. Publish the revised source,
verify fresh exact-head CI and resolved review threads, then return draft PR414
to owner review. Initial CI passed on `12e00fa4` but does not qualify the revised head.

Owner merge and compatible web/worker deployment precede editing the existing EU
route destination to `.mime`. Old original MIME recovery failed404; bank format/
signing and the old payment remain open. See [evidence](artifacts/bank-mail-single-route-review-2026-11-01/README.md).

## Current: publish reviewed wallet false-alarm repair - 2026-11-01

Verify exact-head CI and review for published
[PR #413](https://github.com/webiumsk/satflux/pull/413), the independently reviewed
SEPA activation exclusion from current and historical crypto wallet comparisons. After an approved
deployment, a successful normal fresh verification can resolve SEPA-only alerts
without accepting a new baseline. Preserve real crypto differences and history.
See the [incident record](WALLET_SEPA_DRIFT_INCIDENT.md).

Continue bank-mail diagnosis from the observed `unverified_bank_origin`: inspect
the active store MIME route and deployed revision, then recover original delivery
if available. Confirm actual bank signing/layout and provider evidence before
claiming payment success. Source tests do not establish live interoperability.
No real transfer, forced-paid operation or production rebaseline is a test step.

## Next: verify source CI and operational Tatra compatibility - 2026-10-31

Automatic Tatra B-mail source is prepared through Mailgun original MIME, real
signature verification, strict parsing and conservative once-only allocation.
Independent QA/security and focused/static/locale gates pass. Preserve explicit
limits: no current original bank message, no live route or deployment proof.
Qualify actual Tatra delivery privately and review provider-first rollout before
owner-authorized installation/deployment. Source preparation alone does not mark
bank interoperability or the rollout complete.

## Current: publish matched SEPA isolation source - 2026-10-30

Consumer isolation is rebased on merged 2.4.7 compatibility with independent
source/runtime acceptance, 2,253 PHP and 1,094 frontend tests plus existing gates.
Publish this branch separately from the repaired provider 0.8.2.0; verify both
exact-head CI/reviews and final local archive identity. Provider-first installation
and actual MTA attestation remain operator work after applicable approval. See
[the integration record](SEPA_247_INTEGRATION.md).

## Current: PR411 minor review follow-up - 2026-10-30

Publish the independently accepted browser portability and review-record fixes.
All CI/checks passed on the prior `0e0098db`; original findings are resolved.
Verify fresh exact-head checks and the three minor review threads after pushing.
Preserve the separate 40% documentation advisory and owner merge/deployment gates.

## Current: PR411 compatibility review - 2026-10-30

Resolve the verified sound-URL and hidden-duration CodeRabbit findings while
preserving nullable settings, tenant/permission guards and wire units. Publish
the independently reviewed follow-up to the existing authorized branch and verify
new-head CI. If BTCPay itself persists an invalid duration, its merged-store
validation still requires an explicit merchant correction in Payment settings;
consumer omission does not repair upstream data. Merge/deployment remain owner gates.

## Completed locally: BTCPay 2.4.7 consumer compatibility - 2026-10-30

[The implementation](../BTCPAY_2_4_7_IMPLEMENTATION.md) passes full PHP/frontend
and static/style/documentation gates plus independent QA/security. Publish only
the owner-authorized compatibility branch. Coordinate the separately qualified
provider artifacts, SEPA prerequisite and target schema inventory before any
explicitly approved merge/installation/deployment.

## Blink probe diagnostics - 2026-10-30

Publish the owner-authorized, independently reviewed diagnostic-only branch;
then verify its remote CI. The owner handles live rollout and supplies sanitized
`LUD-21 probe failed` evidence if the issue recurs. No wallet-routing or plugin
change is included. See [the runbook](BLINK_PROBE_DIAGNOSTICS.md).

## Current: wallet checkout review and CI

Address valid findings on Satflux #399 and owned plugins #42: a bounded query must
advertise omitted rows, LNURL-only classification must be consistent, and original
snapshots remain unchanged during re-enable. Patch the critical-audit-blocking
DOMPurify dependency without lowering CI policy or broad dependency updates.
Next milestone: verified new-head CI and independent review, then owner-approved
merge/package refresh and provider-first staging. The previous local 1.4.0.8
archive remains the pre-review artifact until explicitly rebuilt and verified.

## Current: wallet detection and Lightning-exclusive Cashu checkout

Complete reviewed isolated Satflux/provider fixes described in
[WALLET_CHECKOUT_CASHU_POLICY](WALLET_CHECKOUT_CASHU_POLICY.md). The owner chooses
no Cashu in new checkout whenever any Lightning wallet is configured. Retain all
existing quote evidence/recovery and restore enable-only availability without
changing payout targets. Both scopes pass independent security and QA; provider
full suite passes 163 cases with no skips. Next milestone: hand off the local
CashuMelt 1.4.0.8 candidate and separate consumer/provider commits for owner-approved
publication, followed by provider-first installation and representative staging.
The reported direct-LN settlement does not clear the separate Cashu funding hold.
Use the read-only runbook before any further production evidence or active recovery.

## Current: bounded monitoring for wallet verification probes

Complete the isolated `fix/wallet-probe-monitoring` repair and retain all existing
wallet-change safety gates. Future automatic probes request one-minute checkout
validity plus one minute of monitoring; pre-existing deadlines remain unchanged.
Independent source security and 24 focused QA tests pass. Main verification passes
151 PHP tests plus 8 documentation tests, changed-file Pint and full PHPStan. See
[WALLET_PROBE_MONITORING](WALLET_PROBE_MONITORING.md) for broader checks, read-only
operator diagnosis and rollback. Next milestone: review/publish the focused Satflux
change and validate actual probe expiry with representative direct Lightning and
LNURL providers in isolation before deployment approval. No production access,
configuration change, merge or deployment is included.

## Current: Tickets lifecycle, scoped images and passwordless migration

Complete owner-authorized consumer/provider fixes and independent checks described in
[TICKETS_MEDIA_PASSWORDLESS](TICKETS_MEDIA_PASSWORDLESS.md). Keep the old-account
migration login while retiring reset mail/token consumption. Tickets 2.0.4 is
packaged locally, and the owner authorizes the separate Satflux commit/push. Next
milestone: verify published combined-tree CI after integrating owner-merged #395
(`master` at `d37153ce`) into #396. Both decision-log sections are preserved;
independent integration QA, 128 focused PHP cases, 13 frontend cases and full
PHPStan pass. The
prior ticket head `cfa0467f` has green full CI; CodeRabbit's original finding is
resolved and its incremental review is quota-limited. Provider publication and
installation approval remain separate gates. Verify Tickets 2.0.4 before deploying
the dependent Satflux changes. No production mutation is part of this branch update.

## Current: Cashu recovery API export compatibility

Correct the owned API controller classification for BTCPay 2.4.5 without changing
core, native UI CSRF or recovery protections. Deliver CashuMelt 1.4.0.6 with an
actual-host filter regression and exact Satflux request assertions. Independent
security/QA and exact-head CI precede handoff. Provider PR #38 is owner-merged;
prepare a new PR. See [CASHU_CHANGE_RECOVERY](CASHU_CHANGE_RECOVERY.md). Operator
installation, a fresh email challenge and safe staging export/verification are
the next acceptance step. No merge, release or deployment is performed here.

## Completed: persistent orchestration bootstrap

Deliver root instructions, ten Codex specialist definitions, thirteen reusable skills,
portable project registry, ecosystem/workflow/state/decision documentation and offline
validation. Preserve existing work and global configuration; external repositories
remain read-only. Complete independent security/QA review and discovery checks.
No product implementation, release or deployment is part of this milestone.
Bootstrap status: COMPLETED locally, with independent security and QA acceptance.
Configuration/discovery, registry, skill and preservation checks passed; see
PROJECT_STATE for measured coverage and the native-routing limitation.

## Completed: SATFLUX-001 temporarily free PRO audit

Status: COMPLETED. Backend, frontend, payments, security, designer and QA performed
bounded independent reviews. The owner subsequently approved SATFLUX-002 in Satflux.
The audit's historical read-only boundary remains applicable to external repositories
and production. The work packages below describe the completed audit scope.

Product objective: eligible registered users receive PRO features temporarily without purchasing
a subscription. Preserve real subscription/billing history, historical payments and
invoices. Create no fake subscriptions and do not silently cancel renewals. Never
grant Enterprise or administrative rights automatically. Keep tenant/authentication
controls unchanged, and end the promotion safely under an approved data-access policy.

Audit five separate concepts: actual billing status, effective promotional entitlement,
security authorization, administrative privilege and usage limits/operational cost.
The reviewed direction was a centralized reversible promotional entitlement policy,
subsequently approved for implementation as recorded in DECISION_LOG.

The Product Owner reports ZERO paying subscribers. Audit evidence remains limited
to non-production sources; production billing was not inspected. No existing
paying-customer migration, refund handling or discontinued-paid-access communication
is required under that stated assumption; revisit only if evidence contradicts it.

Prioritize disabling new PRO subscription purchases and upgrade prompts during the
promotion, preserving the existing billing architecture for future monetization,
maintaining all security/authorization/tenant boundaries and defining a safe transition
back to paid plans. Release and production activation still require approval.

### Audit work packages

| Wave | Role | Read-only scope and expected evidence |
| --- | --- | --- |
| 1 | backend | Feature gates, middleware/policies, User/Subscription models, guest/verified restrictions, quotas, admin access, existing paid activation side effects |
| 1 | payments | Non-production evidence for zero paying subscribers, temporary purchase suppression, preserved checkout/renewal architecture, BTCPay invoices, webhooks and entitlement idempotency |
| 1 | security | Independent authorization/isolation/escalation threat model; encryption, relay and abuse/cost boundaries |
| 2 | frontend | Plan composables, auth store/router, temporary upgrade-prompt suppression/onboarding, local-first and relay checks, localization/docs |
| 2 | designer | Existing application patterns, clear promotion/upgrade/end UX, accessibility and data-access expectations |
| 2 | qa | Independent regression matrix for billing vs effective entitlement, limits, guest/verified, isolation and safe reversal |
| As needed | wordpress / btcpay-plugins | Cached invoicing capability and cross-repository entitlement/API assumptions |

Run no more than three specialists alongside main. Security reviews designs and final
findings independently; implementation agents never approve their own security work.
Audit inputs include config/pricing.php, config/guest.php, User plan/limit methods,
SubscriptionEntitlementService, subscription controllers/jobs/commands and relevant
payment/idempotency/webhook tests. Resolve exact paths and current revision first.

### Audit acceptance criteria

- Evidence-based backend/frontend gate inventory with file references and repository
  revisions; separate verified findings, hypotheses and policy questions.
- Billing/renewal preservation and webhook/idempotency analysis; no payment logic changes.
- Zero-paying-subscriber evidence assessed only from non-production sources; limitations
  stated and no production billing data accessed or modified.
- Purchase/upgrade-prompt suppression and safe restoration to paid plans are covered
  without dismantling the existing billing architecture.
- Role/tenant/guest/verification/Enterprise protections independent of promotion.
- Explicit quota/cost/abuse assessment and proposed end/data-access policy options.
- Minimal centralized reversible design, alternatives, rollback and test matrix.
- Dependency/compatibility plan and independently reviewed security/QA findings.
- Concrete owner decision request before any implementation phase is authorized.

## Completed locally: SATFLUX-002 temporary free PRO implementation

Owner-approved scope: eligible non-guest/email-verified accounts; indefinite duration;
current PRO quotas; retain manual PRO validity, balances and over-limit objects; pause
new PRO/trial/PRO-credit/slot/ISDOC purchases. Enterprise, administrative permissions,
tenant isolation and billing history remain separate from promotional capabilities.

Central policies, server feature adapters, additive API contracts, UI gates, five
locales, access guides and regressions are implemented. Promotion defaults off,
purchases default closed, and no fake subscriptions are created. Independent security
and QA accepted the source scope. Full suites, browser smoke, static checks and docs
validation passed; measured evidence is in PROJECT_STATE. No merge or deployment.

## Completed: SATFLUX-003 release and activation readiness

Documentation-only readiness was authorized by the owner's continuation and is
completed with independent payments/devops/security/QA evidence in
[FREE_PRO_READINESS](FREE_PRO_READINESS.md). The owner identifies deployed BTCPay
as 2.4.5; the exact build and operational evidence remain unverified. Source handoff
is ready, but activation is NO-GO. At this earlier milestone no provider implementation, publication, merge,
deployment or activation was authorized or executed.

Remaining readiness work:

1. Verify the exact deployed BTCPay provider and complete PRO-only closure for direct
   checkout, funding portals and prefunded AutoRenew without cancelling subscriptions.
   Provider repository or operational changes need specific authorization.
2. Validate hosted-relay cost enforcement, current quota configuration, all replicas,
   worker/config-cache propagation, supported-client behavior and reversal.
3. Decide notice/data-access terms after promotion and the paid reopening procedure.
4. Prepare the scoped Satflux PR and reviewed rollout/rollback evidence, then obtain
   explicit applicable merge, deployment and activation approval.

The implementation proposal at SATFLUX-003, subsequently approved in SATFLUX-004:
central reversible PRO
sales policy in owned `webiumsk/btcpayserver` at the selected 2.4.5-compatible host.
Cover direct and stale checkout/portal mutations, trials, prefunded/AutoRenew and
target-PRO scheduled migration while retaining balances, settlement and Enterprise.
Preserve stored intentions and prevent automatic catch-up spending on reopening.
No legacy plugin work is inferred without verifying that it is installed.

## Completed locally: SATFLUX-004 provider sales pause

Owner approved bounded implementation and isolated tests in `webiumsk/btcpayserver`
for exact 2.4.5, with no push/merge/deployment/production changes. Central policy,
controllers/views, durable reopening barriers and operator/API documentation are
complete. Independent security/QA accept source; native policy 20, configuration 3,
lifecycle integration 1 and documentation 1 tests pass. Build/Swagger/whitespace
pass. Synthetic invoices and no-chain integration do not establish real-chain or
production readiness. See
[FREE_PRO_PROVIDER_IMPLEMENTATION](FREE_PRO_PROVIDER_IMPLEMENTATION.md).
Upstream and conditional legacy plugins remain read-only; activation remains NO-GO.

## Next proposed: SATFLUX-005 coordinated staging and release handoff

Current recovery prerequisite: implement [CASHU_PAYMENT_RECOVERY](CASHU_PAYMENT_RECOVERY.md)
for owner-confirmed native funding lookup failures on CashuMelt 1.4.0.4. Candidate
1.4.0.5 preserves unknown-state holds, recovers only validated original durable
evidence, fixes automatic retry accounting and explains all wallet blockers without
equating total obligations to FAILED rows. Separate plugin/Satflux reviews, native
and consumer regressions, package identity and exact-head CI precede staging handoff.
Both source scopes are independently security accepted. Consumer independent PHP
(124/900 assertions) and final frontend focus (76), UX, static/build gates pass.
Native runtime QA, package identity and exact published-head CI remain required;
the repository PRs record final handoff evidence.
No core, third-party plugin, production or real-fund work. Install compatible plugin
before the consumer; legacy diagnostic metadata remains explicitly unknown.

Owner-approved UX follow-up: show wallet-change safety before editing the Cashu
connection, with explicit blocked/unavailable states and recovery navigation.
Refresh preserves edited settings and sends no codes or mutations. Use the existing
settings read and safety service; preserve all write checks. Source is independently
security/UX accepted; QA confirms source and focused PHP passes 25 cases/122
assertions. Full author Vitest passes 793 cases. Complete final frontend static/build,
independent frontend focus and exact-head CI before final handoff; PR #394 carries
the published revision and final evidence.
Baseline recovery source `fec8a923` passed CI 38826049379; existing npm dependency
debt remains outside this bounded UI follow-up. Provider source/package stay fixed.

Current approved prerequisite: implement the owned CashuMelt/Satflux
[change recovery contract](CASHU_CHANGE_RECOVERY.md), candidate plugin 1.4.0.4,
with owner-only action-bound email confirmation, private downloads, old-plugin
fallback and independent security/QA. The owner explicitly permits this plugin
work while keeping BTCPay core and third-party plugins unchanged. Prepare separate
repository changes and provider-before-consumer release evidence; no real recovery,
production operation, merge or release publication follows from implementation approval.

Implementation is independently security/QA accepted. CashuMelt source is published
as `b86e6193` in Webium draft PR #37 with green exact-head CI (583 tests). Local
Satflux full PHP (1,737/7,600 assertions), frontend (778), Pint (1,041), PHPStan,
typecheck/lint/parity/build pass. A private tested 1.4.0.4 package is prepared.
Next: exact-head Satflux CI and coordinated owner staging/release approval; no
production recovery or installation is implied by these tests.

2026-10-16 follow-up: the owner confirms no-op saves work but actual address changes
remain refused by CashuMelt 1.4.0.3. Implement the bounded Satflux-only
[safety diagnostic contract](CASHU_SAFETY_DIAGNOSTICS.md), obtain independent review
and exact-head CI, then use the owner-visible counts/raw state to identify the
provider refusal. This is not authorization to clear provider records, recover funds,
modify BTCPay or claim the address change is unblocked. Previous final head
`6b289f75` passed all CI including installed Symfony security patch validation.

Current owner-reported branch testing adds a bounded Satflux Cashu repair before
handoff: require existing-wallet email confirmation, permit authoritative unchanged
saves without remote writes, retain first-setup behavior and distinguish translated
outstanding/unavailable safety errors. See
[CASHU_WALLET_CHANGE_FIX](CASHU_WALLET_CHANGE_FIX.md). Independent security/QA
source review, 28 focused Cashu tests, 32 corrected fixture cases, 726 Vitest tests,
Pint, PHPStan, unchanged typecheck baseline, lint/locales/build and graph/docs checks
pass. Candidate publication must be followed by passing exact-head full CI; the
initial local full run's eight corrected fixture failures are documented in state.
Published `e24608d1` then passed the full application suite (1,656 PHP tests, 726
Vitest tests and 20 browser tests), but Composer audit identified a vulnerable
previously locked Symfony dependency. A one-package 7.4.21 lock update passes strict
validation and locked audit; independent review and installed-runtime head CI remain
required. No provider changes or production wallet inspection. This repair does not
establish production activation
readiness or resolve the existing fallback combined-write recovery limitation.

The owner authorized repair of the failed checks in PR #394. Bounded CI repair is
validated locally: unchanged production limits, isolated checkout fixture budget,
fail-closed typecheck execution and unchanged baseline allowances. Full independent
E2E passes 20 tests with three intentional promo skips and no failures/retries; gate
regressions, actual typecheck, 708 Vitest tests, lint/locales/build/seeder style pass.
Independent security/QA accept source. Published repair `7b06ed7f` passed all three
jobs in CI 38594038116, including 1,631 PHP tests, 708 Vitest tests and 20 E2E tests.
The CI repair is complete; subsequent documentation-only heads must retain passing
PR checks. No provider modifications are part of the repair. Remaining work is
Satflux PR review/staging and the operational/approval gates below.

Current owner correction: delivery is Satflux only. The owner explicitly requested
removal of the provider feature branch, which is now verified absent. SATFLUX-004
is withdrawn from delivery and must not be republished, merged or deployed. The
Satflux feature branch remains published; no PR/merge/release/deployment was
performed. Exact publication/deletion evidence is in PROJECT_STATE. Satflux source
is unchanged. Direct BTCPay purchases/AutoRenew are outside the Satflux guard
boundary; the owner accepted that limitation.

1. Establish the correct Satflux release base, prepare its reviewable PR, and obtain
   exact head CI. No BTCPay implementation, PR or rollout dependency is planned.
2. Validate Satflux compatibility with the existing provider and prove consistent
   Satflux artifacts/policy on all replicas and workers. Do not modify provider
   configuration or claim closure of direct provider purchases.
3. Complete hosted relay cost/quota/capacity evidence and private backup/restore.
4. Resolve promotion-end historical access/notice and paid reopening terms; validate
   Satflux staging, retained balances/dates, ownership and genuine fulfillment.
5. Obtain explicit applicable merge/release/deployment/activation approval against
   concrete reviewed evidence. No production activity is authorized by this roadmap.

Existing payment integrations and deferred NWC work are not expanded by this roadmap.

## Offline security/reliability follow-up — 2026-10-19

The source fixes and local test evidence are recorded in
[the incident analysis](../security/incident-analysis-2026-10-18.md). The immediate
next milestone is operator execution of the linked read-only diagnostic runbook,
followed by reconciliation of evidence and preparation of clean-base, separately
reviewed changes. No production behavior is verified, and no deployment or
operational intervention is authorized by this investigation.

## Tickets sales and ordering - 2026-10-21

Authorized implementation: extend the existing Satflux/native type lists with saved
merchant order and unify the plugin-hosted public sales page. Preserve authoring,
URLs, hosted checkout and fulfillment. The provider 2.1.0 candidate precedes the
Satflux ordering consumer; old provider 404 must remain a clear upgrade state.

The [implementation record](TICKETS_SALES_ORDERING.md) tracks ownership, exact
contract, isolated tests and independent review. Complete server recovery and
concurrency tests, actual rendered-page QA, final security review and final checks
before handoff. Then prepare separate reviewable repository publication only under
applicable authorization. No production rollout or merge is authorized here.


Tickets local milestone completed: source/security/QA accepted, frozen plugin
100/100 (15 PostgreSQL), 32 browser and 9 visual scenarios, consumer 850 Vitest and 77 relevant
PHP/documentation tests pass, together with build/typecheck/lint/locales/PHPStan/Pint.
Next: independent repository publication and exact-head CI handoff, then provider-first
representative staging. Preserve pending-intent compatibility during any plugin
rollback; no mixed old/new workers or automatic attempt-marker resets.


Tickets delivery follow-up: the Product Owner authorized local installer creation
for 2.1.0 and commit/push of the Satflux consumer branch. The exact reviewed DLL is
packaged without rebuilding; see the implementation record for artifact integrity.
Next: consumer PR/exact-head CI, separate provider source publication review, then
representative provider-first staging and release/rollback approval. Building the
installer and pushing the consumer do not authorize installation or deployment.


### Tickets 2.1.1 presentation correction - 2026-10-22

Complete independent browser acceptance of automatic/manual language choice,
draft preservation and DST-aware visitor-zone display; then deliver the verified
local installer. Keep hosted BTCPay checkout language independent and UTC storage
unchanged. The 108 non-database tests and 7 JS tests pass; source security review
accepts the bounded delta. Separate plugin source publication, exact-head consumer
PR CI and representative staging/release gates remain. No production action is
implied by the local package.

Final local 2.1.1 gate completed: 39 focused and 32 existing browser checks pass;
the rebuilt accessible picker passes six viewport/theme scenarios across interaction
states and final draft/time smoke checks. The final DLL passes 108 non-database
regressions, zero skips. Next: deliver the checked installer and prepare separate
source publication/CI and staging when requested. Existing host dependency warnings
and production release/rollback gates remain; no live resolution is claimed.


### Tickets 2.1.2 host/cache integration - 2026-10-22

Finish independent actual shared-layout/CSP/production-asset browser validation
for the header language selector and content-hash paths, including stale-script
reproduction, five languages, no-request draft retention and DST. The exact-host
build, 115 non-database tests and 10 JS tests pass; independent security accepts
the frozen scope. Deliver the checked local 2.1.2 installer after final QA. Keep
operator cache provenance unconfirmed until browser/network evidence is supplied;
no production changes or clock/data adjustment are authorized. Separate source
publication, CI, representative staging and release/rollback gates remain.

Final local 2.1.2 milestone complete: 26 actual shared-layout/CSP/resource browser
cases and independent installer integrity pass, alongside 115 .NET/10 JS tests and
security review. Next: hand off the local installer, then separately prepare source
publication/CI and representative staging when requested. Never rewrite event UTC
dates to compensate for display or claim production cache provenance from a local
reproduction. Core and production configuration remain untouched.

## Tickets venue-time correction - 2.2.0

Owner-approved milestone: preserve UTC instants; render physical events in explicit
venue IANA time and virtual events in visitor time. Historical physical-zone backfill
is Europe/Bratislava per owner confirmation. Extend existing native/Satflux forms,
provider catalog/conversion and sparse updates; independently review and test real
metadata-only migration, DST, cross-browser-zone display and old-provider refusal.
Prepare a local 2.2.0 installer, then await separate installation/consumer release and
production acceptance. No production changes are authorized by local verification.

Venue-time implementation now includes the owner-approved Store default with per-event
override and a fix for misleading UTC on Store settings reload. Final local suites
pass: provider172, PHP125 and focusedfrontend74; final artifact/review identity is in
TICKETS_SALES_ORDERING.md. Next gate: final independent candidate QA, then separately
coordinate provider installation before consumer publication/deployment. Existing
Store values previously overwritten to UTC need operator confirmation, not inference.

Final local independent QA/security gate is complete: actual final native guard8,
browser smoke8 and exact installer integrity pass. Next milestone is provider2.2.0
installation before releasing dependent Satflux changes, followed by deployed UX
acceptance; no production action or source publication was performed here.

The Product Owner authorizes publication of the reviewed consumer follow-up to PR397
on `feat/tickets-sales-ordering`. Next source gate: exact-head GitHub CI/review, then
separately approved provider-first installation and deployed acceptance. Existing local
verification is not presented as a completed remote CI run.

PR #397 head e8333505 passes GitHub PHP/Node/Playwright CI. Address its four minor
CodeRabbit findings with a bounded event-error classification fix plus documentation
corrections. Preserve catalog capability handling and unrelated write error behavior;
152 related PHP cases / 513 assertions, full PHPStan and Pint pass locally. Obtain
independent follow-up acceptance and verify fresh published-head CI before merge.

### PR #400 remediation follow-up

The owner authorized CI/review fixes after #399 merged. The feature branch now
contains master `709befa2`, deterministic compatibility discovery, defensive Cashu
404 handling with strict safety, a short presentation-only invoice cache, reliable
Chorala renewal scheduling and localized edited-save guidance. Independent QA and
security accepted this delta; the full 959-case frontend run and selected PHP
regressions pass. Publish the reviewed correction to the existing PR and inspect
its new CI/review. No merge or deployment approval is inferred.

## 2026-10-27: API reference initial implementation ready

Owner-authorized `feat/api-documentation` adds protected Scalar/OpenAPI reference
for 14 selected Satflux/WooCommerce/webhook operations and an explicit 449-entry
API inventory. Independent QA/security accept final functional digest `88db19b2`;
final build and focused/backend/frontend/schema/browser checks pass. Six legacy
PHP DNS failures reproduce unchanged on clean base; remote CI remains a separate
gate. See [implementation evidence](API_DOCUMENTATION_IMPLEMENTATION.md).

Next publish the reviewed branch when authorized and verify exact-head CI/review.
Merge/deployment remain owner decisions. Later expand the 435 deferred operations
with reviewed schemas; provider/plugin specifications retain independent versions.
No provider upgrade, billing-policy change or public interactive API console is
part of this milestone.

PR #405 follow-up is authorized for publication: npm 10 clean-install failure
reproduced and repaired without dependency-version churn or weaker gates; all four
CodeRabbit documentation findings corrected. Independent QA/security accept digest
`dc86ea04`. Normal pristine npm 10/11 installs, full 1,000-case frontend suite,
build/style/type/locales, 11 focused PHP cases and schema/export checks pass.
Next verify exact published-head CI and review; merge/deployment remain separate.


## 2026-10-28 - Support/admin/message usability

- [x] Start from verified merged master d3cb91ba; preserve dirty bootstrap checkout.
- [x] Confined support-table scrolling, compact pills and mobile wrapping.
- [x] Consistent date/time on admin creation/login and every message.
- [x] Chronological merged messages, category/unread filters, owned invoice links,
  distinct read state and bounded complete-history continuation.
- [x] Independent QA/security acceptance of `5c2bd32c`; targeted PHP 26/191, final
  component 22, full frontend 1024 before recovery followup, browser regressions,
  static checks, locale parity and final production build pass.
- [ ] On publication request: create owned PR, attach its reviewed changelog
  fragment and verify fresh CI/CodeRabbit; no merge/deployment approval inferred.

Local port 8080 remains a separate stale bootstrap preview; synthetic QA does not
certify that runtime or its existing database/provider configuration.


PR407 now contains the reviewed support/admin/messages change on the owned
feature branch. Its canonical Chorala fragment passes schema validation, and all
53 offline changelog tests pass. Verify exact-head CI/CodeRabbit before owner
merge/deployment.


PR407 CodeRabbit finding 4404620462 is fixed in a bounded four-file followup: no
phantom filtered next page and Previous remains on terminal empty results. QA and
security accept `949cf289`; PHP 28/218, component 24, independent boundaries 2/1463,
browser 3 and build/static checks pass. Publish and verify fresh-head CI/CodeRabbit;
base `33242fd8` CI was green, not evidence for the new head.


PR407 PHP-job failure is isolated to Composer audit, with 2072 PHP tests passing.
The reviewed one-package symfony/mime 7.4.13→7.4.22 lock update `8f3133e4` passes fresh
strict validation/full audit (zero advisories), platform checks, mail regressions,
independent QA/security and PHPStan/API checks. Publish and wait for the new full
PHP CI job, including audit, before reporting this failure resolved remotely.


## 2026-10-28 - Turnstile-only registration scope

- [x] Implement server-validated challenge before fresh guest/legacy creation.
- [x] Preserve existing accounts, login/recovery, phrase privacy and API defenses.
- [x] Complete signup UI, CSP, five locales and secure activation runbook.
- [x] Independent QA/security accept 25-file `305d24d0`; targeted suites, full Node 1050,
  browser 6, build/static/Composer checks pass. Update one exact public-config test
  expectation without weakening assertions (focused 44/482 pass).
- [ ] Publish reviewed branch/PR-linked changelog and verify fresh full CI.
- [ ] Obtain real Managed-widget configuration securely, deploy reviewed client
  and server, enable protection and verify the actual public configuration.

Existing account cleanup/labels, new creation budgets and proxy/firewall changes
remain outside this approved implementation. No active production protection is
claimed from local tests or default-disabled configuration.


PR408 publishes the reviewed Turnstile-only change with a canonical PR-linked
changelog fragment. Verify final CI/review, then prepare real widget configuration
and activation. Existing accounts/statuses and recovery remain outside changes.


PR408 followup fixes a duplicated ownership error without E2E selector changes
and aligns six German strings with formal auth copy. Independent acceptance
`dab15e0d` covers the final 25 files; 23 component and seven browser scenarios
pass. Prior PHP/Node CI passed; rerun the exact-head workflow for corrected E2E.
Public Site Key received; Secret Key and deployment remain operator setup.


## Current: Turnstile async SDK compatibility fix

Replace unsupported async `turnstile.ready()` usage with Cloudflare's explicit SDK readiness callback. Verify against the actual SDK in an isolated, network-intercepted browser as well as focused regressions; preserve fail-closed account creation and recovery identity. Public production configuration is enabled with appropriate CSP, but successful verification is not established. Publish reviewed fix, verify exact-head CI and hand off for owner merge/deployment.


PR409 fix is published and independently accepted (`a18a4557` source/tests, `be6bf93e` with runbook). Real-SDK failure/initialization/retry evidence and26 focused regressions pass. Lint/typecheck/locale/build pass locally. Next: exact-head CI, owner merge and deployment of rebuilt frontend, then confirm actual browser verification. Do not change environment keys to work around this loader bug.
