# Project state

## Tickets media and passwordless migration repair (2026-10-20)

Owner-authorized repository fixes are in `fix/tickets-media-passwordless` from
Satflux `5e6953cf`; see [TICKETS_MEDIA_PASSWORDLESS](TICKETS_MEDIA_PASSWORDLESS.md).
Correct nested ticket-type mutations, remove the privileged Files path from ticket
images, stage image changes until Save and retain IDs on partial failures. The owned
Tickets candidate 2.0.4 returns actual scoped logo URLs and allows 2 MiB raster images;
install/verify the provider before the dependent consumer. Preserve grouped QR emails.

The owner explicitly retains a temporary password migration entry for older accounts.
Disable reset emails and both reset POSTs (uniform 410), redirect both historic reset
URL formats server-side, and preserve guest/recovery/passkey/legacy migration login.
Reported self-addressed reset emails are consistent with the old Laravel endpoint;
the caller is unknown. No production behavior or deployment was verified.

Local evidence: 44 focused PHP tests pass, with a final overlapping nine-test reset/
migration rerun; independent QA passes 31 frontend tests; provider passes 24 tests.
PHPStan, Pint, lint, typecheck, locale parity and final build pass. Tickets 2.0.4.0
is packaged locally; its DLL matches the tested Release DLL. Browser discovery passes;
actual browser execution and persisted provider image lifecycle remain staging gates.

Independent security found no high/medium blockers; QA's deletion-before-Save finding
and security's missing old-query-link redirect are closed. Tests/checks and remaining
staging gates are recorded in the contract. No new environment variable, production
change or deployment is included. Incident PR #395 was absent from the initial
ticket base; the integration follow-up below records its subsequent owner merge. Graphify's query graph was unavailable; targeted source reads used.

### Authorized Satflux publication follow-up

The owner authorizes committing and pushing the Satflux repair branch to the owned
`webiumsk/satflux` repository. The reviewed 31-file source/test fingerprint remains
`5b0b8c410790be6ef6ef9b37f91cfe3c8557f9713c74dce3571f10752ce0f83d`;
only publication documentation changes after review. Publish the consumer separately
from the locally prepared Tickets 2.0.4 provider. Exact-head CI and GitHub review,
provider publication/installation and staging acceptance remain next gates. No merge
or deployment is authorized by the push request. Preserve the unrelated image edit
in the original checkout and exclude disposable environment files/artifacts.

### PR #396 PHP CI compatibility follow-up

PR #396 head `12bb3d2b` passed Node and Playwright CI, but the PHP suite failed
five obsolete `AuthTest` password-reset expectations while 1,831 tests passed
(8,391 assertions). [CI run 39469452552](https://github.com/webiumsk/satflux/actions/runs/39469452552)
is the source of these results. The approved reset contract is HTTP 410 with no
mail or credential changes; five general-auth tests still expected 200/422, mail
or a successful reset. Update those cases without changing application source,
retaining registration, login/logout and email-verification assertions. Add explicit
no-notification/no-token/no-login and hash/session/valid-token preservation assertions.

Isolated offline `AuthTest` plus `PasswordResetLinkTest`: 25 passed, 118 assertions;
changed-file Pint passes. Independent QA accepts the test-only delta. Fresh
exact-head CI remains required; do not treat the initial failed run as acceptance.

At follow-up head `2c783de1`, [CI run 39480617117](https://github.com/webiumsk/satflux/actions/runs/39480617117)
passes all 1,836 PHP tests (8,425 assertions), Pint, Node and Playwright. PHPStan
then identifies exactly three obsolete baseline ignores in the changed ticket
controller/service code. Remove only those unmatched entries, without regenerating
or expanding the baseline or disabling unmatched-ignore reporting. The earlier
changed-file analysis did not enforce this whole-project baseline check. Full
local PHPStan passes with no errors; independent QA accepts exactly the three-entry
removal. Fresh published-head CI is required for final acceptance.

### Ready-for-review CodeRabbit follow-up

At `9bc086fe`, [CI run 39482230312](https://github.com/webiumsk/satflux/actions/runs/39482230312)
passes PHP (1,836 tests/8,425 assertions), full PHPStan/Pint/Composer audit, Node and
Playwright (20 passes). The owner marks PR #396 ready for review. CodeRabbit reviews
this exact head and posts one minor actionable finding plus two terminology nits.

The persisted event must remain visible/count toward limits after an image failure,
even if the form is cancelled or list refresh fails. Upsert the returned creation
into local events, refresh after a create/image partial failure, and show the image
notice after refresh because loading clears notices. Retain the event ID and pending
file for retry. Add regressions for successful/failed refresh plus Cancel/limit and
retain the existing no-duplicate retry test. Align SK "podujatie" and DE "Event".
Independent QA accepts this bounded frontend delta and independently passes all
13 focused frontend tests. Full lint (zero errors/48 existing warnings), typecheck
(120 unchanged diagnostics), locale parity and production build pass. Fresh-head
CI/review remain required.
No backend, auth, provider, billing or production behavior is changed by this follow-up.

### PR #396 integration after owner merge of #395

The owner merged #395 into `master` at `d37153ce` (2026-10-20) and requested resolving
#396's conflict. Integrate that base into `fix/tickets-media-passwordless`, preserving
both decision-log append sections in chronological order. All application changes
combine automatically; the only manual conflict is `DECISION_LOG.md`. Keep both
PHPStan baseline reductions and image-route throttles. This branch update does not
merge #396 into master or alter production/provider state.

Prior ticket head `cfa0467f` has green [CI 39485258893](https://github.com/webiumsk/satflux/actions/runs/39485258893):
1,836 PHP tests/8,425 assertions, Node and 20 browser passes. Its original CodeRabbit
finding is resolved; incremental review was quota-limited despite a successful check.
Independent integration QA accepts preservation of both parents and all decision
sections. Combined offline checks pass: 128 PHP tests/507 assertions, 13 frontend
tests and full PHPStan with no errors. Fresh published-head CI remains required.

## Cashu recovery API export compatibility repair (2026-10-16)

The owner reports refused Satflux change export while native BTCPay export works.
Source diagnosis at Satflux `35f5e414` / CashuMelt `3486115a`: MVC `Controller`
inheritance makes the exact BTCPay 2.4.5 global UI antiforgery filter require a native
form token for API POSTs. Satflux sends correct JSON and the merchant key.
The bounded fix and acceptance criteria are in
[CASHU_CHANGE_RECOVERY](CASHU_CHANGE_RECOVERY.md): `ControllerBase`, plugin 1.4.0.6,
actual-host filter regression and exact consumer request assertions. Preserve
API/store/email-code/privacy protections and native UI/core/third-party source.

Provider PR #38 is owner-merged; Satflux PR #394 remains open at the initial check.
Prepare a separate provider PR/package. Independent security/QA, native regressions
and exact-head CI precede handoff. No production state was inspected or changed;
the reported balance and native export success are owner-provided evidence.

## Cashu funding-lookup recovery repair (2026-10-16)

Continue the owner-authorized owned CashuMelt/Satflux recovery work. The owner
confirms CashuMelt 1.4.0.4, native retry HTTP 400 and exactly two FAILED records.
The safety count of three obligations does not establish a third failed payment;
terminal records can retain recovery material. Source review identifies funding
polling before durable recovery, insufficient blocker metadata and a worker retry
counter reset. No actual merchant evidence category or recoverable balance is known.

The bounded contract is [CASHU_PAYMENT_RECOVERY](CASHU_PAYMENT_RECOVERY.md), candidate
plugin 1.4.0.5. Reuse existing restore/exact-payout paths with validated original
issuance/proof binding; unknown funding cannot establish unpaid/settled status.
Preserve all holds without sufficient evidence. Add read-only blocker categories,
phase/reasons and monotonic automatic retries under the quote lock. Core, third-party
plugins, billing, configuration and production remain unchanged. Main owns contracts,
integration/Git and guides; specialists have disjoint plugin/backend/frontend scope.

Baseline Satflux `94a3f593` is independently accepted with green
[CI 38829804278](https://github.com/webiumsk/satflux/actions/runs/38829804278):
1,744 PHP tests/7,657 assertions, 793 Vitest cases, 20 browser passes/three expected
skips, static/build gates and fresh locked HttpFoundation 7.4.21. Existing production
npm findings remain 40 (36 moderate/four high), permitted by the unchanged threshold.
Provider baseline `b86e6193` is the reviewed 1.4.0.4 candidate in Webium PR #37.

Source implementation and independent security review are complete: consumer
11-file manifest `6866913178facfcea23cc5bcfa71d6871328c1781a23a5c61edf7e9f5183d296`,
provider six-file manifest `1ee6a9ace00056766d1401be382abe93c4b1fe3f1a10a1d62cb1004af360ed8e`.
Independent QA repeats 124 focused PHP cases/900 assertions and the final 76-case
frontend focus. Four-file Pint, two-file PHPStan, typecheck (121 unchanged baseline
diagnostics), lint (zero errors/48 warnings), five-locale parity and production build
pass. Author full Vitest passes 815 cases before compact markup/compatibility fixes;
final focus covers those changes. UX source review accepts the compact native
disclosures. Plugin/test compilation passes with existing warnings only; native
runtime/independent QA, package identity and exact published-head CI remain required.
Existing PRs record final revisions/results. Use verified disposable SQLite/PostgreSQL
and synthetic mints only. No production or real recovery is implied; the owner's
actual outcome remains unknown until safe staging evidence.

## Authorized Cashu connection preflight follow-up (2026-10-16)

The owner requests wallet-change blockers on the Cashu connection/settings screen
before editing or saving. The bounded contract is the connection-screen follow-up
in [CASHU_CHANGE_RECOVERY](CASHU_CHANGE_RECOVERY.md): an optional aggregate on the
existing settings read, persistent advisory status, recovery navigation and a
safety-only refresh that retains edited fields. No new mutation, authorization
shortcut, provider change, configuration or production operation is included.

Baseline `fec8a92353ca974d1d01bc35b37712b483a2de1d` is published in PR #394 and
independently accepted with green
[CI 38826049379](https://github.com/webiumsk/satflux/actions/runs/38826049379):
1,737 PHP tests/7,600 assertions, 778 Vitest cases and 20 browser passes/three
intentional promotion-fixture skips, no retries. All static/build gates pass and
fresh PHP CI installs HttpFoundation 7.4.21. Composer audit has no advisories.
The existing production npm audit reports 40 affected packages (36 moderate/four
high), identical to baseline; its unchanged critical-only failure threshold permits
success. JavaScript dependencies are unchanged. Do not describe that audit as clean.

Implementation source is complete; independent security and UX reviews accept the
eight-file production manifest `682b2c895ff99ff496f6a7d6e799f4e3c95c17339d2c082f355250eb4580f0fd`.
Independent QA confirms the same source scope and passes focused PHP (25 cases/122
assertions). Author frontend focus passes 47 cases and full Vitest passes 793 cases
in 124 files. Targeted Pint (two PHP files), controller PHPStan, AST graph update and
documentation validator pass. At this source snapshot, final frontend static/build
checks, independent frontend focus and exact published-head CI remain required;
the existing PR #394 records final completion evidence. No dependency, baseline,
workflow or configuration changes. CashuMelt 1.4.0.4 and its reviewed package remain
unchanged; coordinated staging/merge/release/deployment approval is separate.

## Authorized Cashu change recovery integration (2026-10-16)

The Product Owner explicitly clarifies that the owned CashuMelt plugin may change
alongside Satflux. BTCPay core and third-party plugins remain read-only. This
supersedes the earlier blanket provider restriction only within this owned-plugin
scope. No production access, real fund operations, merge, release publication or
deployment is authorized.

Satflux starts at `c652fdbd299ded7c42165ea894cafa72732c743f`, whose
[CI 38819190804](https://github.com/webiumsk/satflux/actions/runs/38819190804)
passed and was independently accepted by QA: 1,690 PHP tests/7,188 assertions,
748 Vitest cases, 20 browser passes/three intentional skips/no retries, static
checks, build and audits. Both PHP jobs freshly installed HttpFoundation 7.4.21.
The owner subsequently supplies diagnostic evidence of unresolved change with
completed payment rows; native recovery already exists but has no Greenfield API.

The approved implementation contract is
[CASHU_CHANGE_RECOVERY](CASHU_CHANGE_RECOVERY.md). It adds an aggregate balance/state
API plus guarded export/verification to CashuMelt and a six-digit-code-protected
owner recovery flow to Satflux. Implementation and frozen-source independent
security/QA review are complete with no blockers. Full independent PHP passes
1,737 tests/7,600 assertions, Pint 1,041 files and PHPStan reports no errors.
Full frontend passes 778 cases, final independent focus 30 cases, actual typecheck
retains 121 baseline diagnostics, lint has zero errors/48 existing warnings, and
five-locale parity/production build pass. The AST-only Graphify update completes.
Provider base is owned Webium main `79de74f4fd9f770539dd30ef29b7b8e6abdfe7b0`,
branch `feat/cashu-change-recovery`, candidate CashuMelt 1.4.0.4. Satflux continues
on `feat/free-pro-access`. Existing host pin, recovery services, locks and safety
refusals remain authoritative. Provider graph is unavailable; targeted source
inspection supplements Satflux Graphify queries. Publication and operational
readiness must be assessed separately in both repositories. Plugin commit
`b86e619380fbbbf1427a3e47124d30b369b78d94` is published in
[draft PR #37](https://github.com/webiumsk/BTCPayServerPluginsWebium/pull/37), with
independently verified green
[CI 38824816578](https://github.com/webiumsk/BTCPayServerPluginsWebium/actions/runs/38824816578):
nine builds, 583 maintained-plugin tests including 129 CashuMelt cases, zero skips.
Independent QA verifies the local 1.4.0.4 package matches the tested DLL and includes
no core/other-plugin assemblies; hashes are in the contract. Exact-head Satflux CI
remains required after candidate publication. No real-host installation or wire-level
cross-application recovery E2E was run. No real funds or production changes.

## Cashu blocked address change diagnostics (2026-10-16)

The owner confirms unchanged saves work after the previous fix, but changing the
payout address remains blocked while the visible list contains two settled and one
expired unpaid row. The owner identifies CashuMelt as 1.4.0.3; its exact deployed
commit and safety counters have not been inspected. Previous final Satflux commit
`6b289f751b535cb09a061e6f4d94c466e1425417` passed
[CI 38809977505](https://github.com/webiumsk/satflux/actions/runs/38809977505),
independently verified by QA: 1,656 PHP tests/7,025 assertions, 726 Vitest tests,
20 E2E passes/three intentional skips, Pint/PHPStan/typecheck/locales/build/audits.
Fresh CI installed HttpFoundation 7.4.21. PR #394 was open at that head.

Independent backend/frontend/security diagnosis confirms a Satflux visibility gap:
provider safety counts are discarded, separate change records are not in the payment
list, and normalized displayed `SETTLED` can conceal original `PENDING`/missing
provider state. None establishes the actual cause or presence of funds for this
account. Terminal rows cannot override the provider's refusal.

The bounded Satflux-only follow-up contract is
[CASHU_SAFETY_DIAGNOSTICS](CASHU_SAFETY_DIAGNOSTICS.md). It adds allowlisted read-only
counts to existing responses, preserves original known provider state additively,
explains discrepancies and unavailable evidence in five locales, and prevents safety
response/error bodies from reaching client logs. Existing write checks, email grants,
no-op behavior and recovery actions remain authoritative. Implementation review and
verification use the frozen contract. Independent security and QA accept the same
14-file application/locale manifest, recorded with local test evidence in that
document. Full Pint passes 1,038 files, full PHPStan has no errors, complete frontend
tests pass 122 files/748 cases, and independent diagnostics tests pass 18/18. Actual
typecheck passes with 121 unchanged baseline diagnostics, lint has zero errors/48
existing warnings, locale parity and production build pass. Fresh published-head
CI remains the final gate; no resolved address change is claimed. The isolated PHP
test container/network are removed.

No BTCPay changes, production calls/data mutation, fund recovery, merge or deployment.
After reviewed publication and green exact-head CI, the next evidence is the owner's
two diagnostic counts and original displayed plugin state. Any provider recovery/code
change retains its separate authorization boundary; SATFLUX-005 readiness remains open.

## Cashu CI dependency follow-up (2026-10-15)

Published Cashu repair `e24608d1c03931b913936fb573e777c78c5b4b8b` passed all
application checks in
[CI 38808562407](https://github.com/webiumsk/satflux/actions/runs/38808562407):
1,656 PHP tests/7,025 assertions, Pint/PHPStan, 121 Vitest files/726 tests,
typecheck/lint/locales/build, and 20 browser tests with three intentional promotion
skips. The overall run failed at Composer audit for the previously locked
`symfony/http-foundation` 7.4.14, advisory `PKSA-xmwy-rmx5-m7h4` /
`CVE-2026-108082` (HeaderUtils Cache-Control parsing ReDoS).

The bounded follow-up changes only that package to 7.4.21 in the existing 7.4 line,
including the upstream whitespace-parsing follow-up fix. Composer generated the
partial lock update in a private manifests-only project with existing PHP/Composer,
without installing packages, running scripts/plugins, or touching application/vendor
state. The other 166 packages, root metadata, content hash and composer.json remain
unchanged. Strict validation and fresh `composer audit --locked` pass with no
advisories or abandoned packages. Lock SHA-256:
`882b46a24fbc260a0479f859aef2cf691b96964a51c0af765287c7ee85e843f4`.

Independent security/QA accept the exact lock above; exact follow-up head CI is
required before completion. Tests against the unchanged local vendor do not
validate the new lock; CI must install it and repeat the complete suite. The Cashu application manifest
below is unchanged. No audit ignore, baseline expansion, provider or production
change, merge or deployment. Temporary Cashu test containers/network are removed.

## Reviewed Cashu wallet-change repair candidate (2026-10-15)

The owner is testing the deployed but unmerged feature branch and reports missing
six-digit email confirmation when editing Cashu, plus an outstanding-settlement
error even on an unchanged save. Visible history is owner-reported as two settled
payments and one expired unpaid payment. Production and the deployed plugin build
were not inspected. Work remains Satflux only on PR
[#394](https://github.com/webiumsk/satflux/pull/394), based on
`8b2bbf6136357b8427fb82051aec932c7b7d4c87`; its prior
[CI 38596184075](https://github.com/webiumsk/satflux/actions/runs/38596184075)
passed all three jobs. That result does not validate the new repair.

The bounded design, acceptance contract, provider source evidence and rollback
constraints are in [CASHU_WALLET_CHANGE_FIX](CASHU_WALLET_CHANGE_FIX.md).
Backend/frontend implementation reuses existing email grants and store locks,
recognizes authoritative read-only no-ops, preserves unknown/outstanding safety,
captures exact grant IDs in backward-compatible encrypted journals, and distinguishes
translated safety errors in all five UI locales. Wizard-selected Cashu without a
configured wallet retains first-setup behavior. The known fallback combined-write
journal/readback limitation remains outside this bounded repair.

Independent security and QA reviewed the source. Exact repaired-head CI is required
after candidate publication; local evidence below precedes that remote run. Backend
focused safety checks pass
28 tests/147 assertions. Independent full PHP ran 1,656 cases: 1,648 passed and eight
old fixture cases failed (seven lacked grants before newly centralized enforcement;
one returned a store DTO instead of a safety DTO). Only those two fixture files were
corrected; original assertions and owner-negative cases remain. Both complete classes
then pass 32 tests/109 assertions. Exact repaired-head CI must repeat the complete PHP
suite; the earlier partial pass is not a full green result.

Full frontend validation passes: 121 files/726 Vitest tests, actual typecheck with
121 unchanged baseline diagnostics, lint with zero errors/48 existing warnings,
five-locale parity and production build. Independent QA separately
passes 26 focused cases and reruns the expanded seven-case Cashu/Lightning switch
suite, including visible translated safety errors, preserved input and no success
event after refusal. Existing English/Slovak Cashu guides are updated; missing guide
locales use the documented English fallback. Local results must not be reported as
repaired-head remote CI. Current checks remain available through PR #394.

Final independent source acceptance is by `/root/cashu_security_review` and
`/root/ci_qa_review`. The 11-file application manifest (sorted path, NUL, content,
NUL) is `141a69b0f473cbc552f79f42fb7b1c070699a5e690df08f89061305cac553251`.
Full Pint passes 1,037 files, with four subsequent type-fix files checked again;
independent full PHPStan passes with no errors. The final Cashu 28-case/147-assertion
run also passes after those type fixes. No ratchet, compiler or dependency allowance
was expanded. Graphify AST-only and orchestration/doc-link/whitespace checks pass.
Independent QA accepts candidate publication subject to exact repaired-head CI.

No new environment variable, migration, provider change, production mutation,
merge or deployment is part of this repair. After reviewed publication and passing
head CI, the next milestone remains SATFLUX-005 Satflux review/staging/readiness,
with the existing operational evidence and separate release/activation approvals.

## Validated CI repair: SATFLUX-005 (2026-10-14)

The owner requested CI diagnosis and repair on the existing Satflux feature branch.
PR [#394](https://github.com/webiumsk/satflux/pull/394) targets master at observed
head `c22660c074d7b59cc59d2d1caaa792b5d9ff4d76`.
[CI 38563933755](https://github.com/webiumsk/satflux/actions/runs/38563933755) failed
typecheck and local-first checkout; PHP/Pint/PHPStan/security checks passed.
CodeRabbit skipped review because the original PR exceeded its 100-file limit;
its success status does not mean source review occurred.

Independent diagnosis confirmed API 429 responses: earlier tests exhausted the
shared account's 120/min budget. Checkout now uses a separate synthetic ordinary
PRO account and stored authentication. Helpers negotiate JSON/check statuses and
assert the actual checkout POST before waiting for its UI link. Production rate
limits, auth/CSRF, effective access and payment fulfillment code are unchanged.

The old local typecheck wrapper could incorrectly report zero errors when child
pipe capture returned empty output after a nonzero compiler exit. That earlier
local typecheck observation is superseded. The repaired gate uses private output
files, the installed compiler directly and fails closed on crashes/silent/unparsed
errors. Six regressions include genuine new diagnostics and increased occurrences.
An isolated compile of baseline `7c4a6bc8` matched the original 100 groups/121
occurrences; current source has the same counts. Full diagnostic/source-anchor
comparison proved 32 changed prefixes are property/union display-order changes.
Only those existing fingerprints were refreshed one-to-one; no new errors or higher
counts were allowed. Independent security/QA reviewed the evidence and caps.

Local lint (zero errors/48 existing warnings), five-language parity, build, 119
Vitest files/708 tests, actual typecheck (121 existing errors, nothing new), six
runner regressions and changed-seeder Pint/syntax checks pass. Independent full E2E
passes: 20 tests, three intentional promotion-only skips, zero failures/retries,
2.5 minutes, including checkout creation and signed settlement. Independent reviewers
`/root/ci_security_review` and `/root/ci_qa_review` accept the bounded final source;
security also executed all six gate regressions. Graphify AST-only update and
orchestration/doc-link/whitespace checks pass. Published repair commit
`7b06ed7f1c1b7bda708e62887953a7a6303c6582` passed
[CI 38594038116](https://github.com/webiumsk/satflux/actions/runs/38594038116):
all three PHP, frontend and E2E jobs succeeded. Remote results are 1,631 PHP tests /
6,891 assertions, Pint 1,036 files, clean PHPStan, six gate regressions, real
121-diagnostic typecheck, 119 Vitest files/708 tests, lint/locales/build/audits and
20 E2E tests with three intentional skips and no retries. This completion record
changes documentation only; PR #394 checks track any later published head.
Tests use new disposable SQLite,
synthetic accounts/stub, cached browser/runtime, an internal-only network and a
loopback transport. No provider changes, real funds, production access, merge,
deployment or activation are authorized.
The task-specific containers, network and loopback proxy were removed/stopped after
validation. The feature worktree is clean at the repair commit; the original checkout
still contains only the preserved owner image edit. Next work is scoped Satflux PR
review/staging/readiness, subject to the separate merge/deployment/activation gates.

## Current owner constraint: Satflux only (2026-10-14)

The Product Owner clarified that BTCPay changes are not wanted, accepted the stated
limitation of Satflux-only purchase guards, and explicitly requested deletion of the
published provider feature branch. Main deleted only
`webiumsk/btcpayserver:feat/satflux-pro-sales-pause` with an exact expected-head lease
for `734791fdd8ac6df46088c43fa0152ad5f185be3e`. Remote verification confirms the
branch is absent and owned master remains
`3ec6a9847bd06e5dad348caaec373276dc718520`. No provider PR/merge/deployment or
production change occurred. The isolated local commit/worktree is retained solely
as historical evidence; do not republish, integrate or deploy it.

The published Satflux feature branch and implementation are preserved. Future
implementation/release scope is **Satflux only**, without modifying BTCPay code,
configuration or billing records. SATFLUX-004 is withdrawn from the delivery plan;
its tests and runbook below are historical evidence, not an active dependency.
Satflux guards do not establish a global ban on direct BTCPay purchases or host
prefunded AutoRenew. Do not claim those provider paths are closed. The owner accepted
this stated boundary; no further provider closure is authorized.

Next milestone remains SATFLUX-005, narrowed to Satflux PR/head CI and staging of
the approved effective access/purchase behavior. Relay cost/quota, replica/cache,
private restore and promotion-end access evidence remain outstanding. No merge,
deployment or promotion activation is authorized by this cleanup.

## Feature publication follow-up (2026-10-14)

After the accepted SATFLUX-004 result, the Product Owner explicitly requested push.
Main published the existing reviewed feature branches to their owned origins and
verified exact remote heads:

| Repository | Published branch | Verified initial publication head |
| --- | --- | --- |
| `webiumsk/satflux` | `feat/free-pro-access` | `a5ed908e384936678d4b41bdddf89c07e2eb9489` |
| `webiumsk/btcpayserver` | `feat/satflux-pro-sales-pause` | `734791fdd8ac6df46088c43fa0152ad5f185be3e` |

This publication record adds documentation only on the Satflux feature branch;
application source and reviewed provider fingerprints are unchanged. Both branches
were newly created remotely without force or tag publication. Original checkouts
and the owner's pre-existing image modification remain untouched. No upstream
write, PR creation, merge, release, deployment or production change occurred.
GitHub run queries returned no feature-branch CI runs at this observation; local
validation is not remote head CI evidence. Provider import remains locally shallow;
successful branch publication does not establish a reviewed integration/PR base.

SATFLUX-005 still needs correct release bases/PRs/head CI and the operational evidence
below. Production activation remains NO-GO and requires separate applicable approval.
The historical no-publication authorization statements below describe their original
milestone scopes and are superseded only for this explicit feature push.

## Completed locally: SATFLUX-004 (2026-10-14)

The Product Owner approved bounded provider implementation and isolated tests in
owned `webiumsk/btcpayserver` for BTCPay 2.4.5. No push, merge, deployment, production
changes or activation is authorized. Upstream, Kukks and other repositories remain
read-only. Work uses the isolated `feat/satflux-pro-sales-pause` branch based on exact
`5d0745cae6be5d8210459e38813f317673aa97b8`, imported from existing local reference
objects without cloning or changing original checkout branches. The imported
boundary is shallow; publication/base ancestry needs separate verification.

Central provider policy/services, controller/UI and disposable tests are complete.
Local provider commit: `734791fdd8ac6df46088c43fa0152ad5f185be3e`; clean feature
worktree, original owned master unchanged. Task PostgreSQL/network were torn down.
Acceptance, repository limits and the intent/settlement/reopening contract are in
[FREE_PRO_PROVIDER_IMPLEMENTATION](FREE_PRO_PROVIDER_IMPLEMENTATION.md).
The immutable pause defaults OFF, validates exact mapping, pauses every non-Enterprise
plan in the configured offering, and separates new purchase/debit from genuine paid
fulfillment. Existing settings hold durable monotonic scoped/subscriber barriers:
reopening does not perform catch-up debit; fresh deliberate purchase can reauthorize
renewal. No schema or persistence-model migration, application/package-version
change or production change was introduced. UI view models were extended.

Independent `/root/provider_pause_security` and `/root/provider_pause_qa` accept the
bounded source. Native policy 20/20, real CLI/file/environment configuration 3/3,
complete lifecycle integration 1/1 and operator documentation/help/link check 1/1
PASS. Exact native Release build passes with zero errors and three obsolete setter
warnings confined to synthetic invoice fixtures. Swagger and diff whitespace pass.
Disposable PostgreSQL/internal-only/no-chain execution covers real API/HTTP/CSRF,
balances/ledger/dates, scheduled targets, recovery, cold restart, reopening and
fresh/historical obligations. Invoice events are synthetic; full upstream regression,
real-chain regtest and provider Playwright were not executed. Frozen service SHA-256:
`c72740723fb5673bb1f52640ca3382849424c32f5cb1fb5c493482457b45498f`.

**Next milestone: SATFLUX-005 coordinated staging and release handoff.** Correct
release bases/PR head CI, deployed build/mappings/legacy inventory, all replicas,
relay costs/quotas, private restore and end-access/reopening terms remain unverified.
Production activation remains NO-GO. Publication, merge, release, deployment and
activation need their separate applicable approvals. Stock/older workers ignore
resume barriers; binary downgrade is not a safe reopening procedure.

## Readiness follow-up: SATFLUX-003 (2026-10-14)

The owner requested continuation after SATFLUX-002 and identified the deployed
BTCPay version as **2.4.5**. The main agent completed documentation-only readiness
preparation with independent payments, devops, security and QA reviews. At this
earlier milestone, no provider
implementation, remote publication, merge, deployment or production changes were
authorized or performed. Application source remains at `c0d532fb`.

Disposition: documentation handoff ready; activation **NO-GO**. See the concrete
source map, provider work proposal, staging matrix and ordered release/reversal
procedure in [FREE_PRO_READINESS](FREE_PRO_READINESS.md). The modern subscription
provider is owned BTCPay core, not the legacy Kukks plugin. The readable host
reference `3ec6a9847bd06e5dad348caaec373276dc718520` has no complete PRO-only sales
pause: independent checkout/portal/migration and prefunded AutoRenew remain outside
Satflux flags. Retired/nonrenewable or hiding links is insufficient.

Payments independently compared the exact local `v2.4.5` host reference
`5d0745cae6be5d8210459e38813f317673aa97b8`: hosted service and plan/subscriber/
checkout models match the owned reference, and the controller/store-binding changes
add no sales guard. The gap therefore applies to 2.4.5. That upstream submodule is
reference-only, not a write/publication target; actual deployed build remains unknown.

Security and QA independently hold activation. Current-artifact promotion-off can
block legacy historical reads/exports and local invoicing navigation despite data
retention. Pre-policy code rollback ignores purchase flags and can reopen PRO and
add-on sales. The runbook now states both limits; routine reversal stays on the
reviewed new artifact. Planned end/access terms remain separate owner decisions.

Operational evidence still needs exact deployed build/plan mapping, relay capacity/
write-side quota/cost enforcement, all-replica environment/cache convergence, private
artifact backup and isolated restore proof, exact release-head CI and expanded
staging. Existing scripts/static health responses cannot establish those facts.
No lower promotional quotas, subscription cancellation or permanent manual grants
were approved. Owner-reported version/subscriber/payment facts remain attributed.

Fresh GitHub read-only evidence: master is
`7bb26f3f5ccb5c3e8a1ca29350090493d3135230` (bootstrap PR #393 merged), with tree
`20c5fd77d31ebe2f3cdf3ba7707570cfce60649c`, identical to the implementation baseline.
[Master CI 38482352651](https://github.com/webiumsk/satflux/actions/runs/38482352651)
passed. No feature PR exists and no feature-head CI is claimed. The source suite
results below were not rerun for this documentation-only review; devops passed
non-mutating shell syntax checks, and QA verified unchanged application scope.

The next bounded work identified at SATFLUX-003 required repository-specific approval for a central reversible
PRO sales policy in owned `webiumsk/btcpayserver` on a selected 2.4.5-compatible
revision, with isolated tests and independent reviews. Preserve prefunded/scheduled
intent and prevent surprise catch-up spending. Conditional legacy work requires
separate authorization only if installed; merge/deployment/activation stay separate.
The owner subsequently approved and completed the provider scope in SATFLUX-004 above.

## Current milestone: SATFLUX-002 (2026-10-14)

The Product Owner approved bounded Satflux implementation after the SATFLUX-001
read-only audit. SATFLUX-002 is implemented and validated locally on
`feat/free-pro-access`, based on `7c4a6bc8731a434dc863928d616a30c823b94382`.
This is source/test evidence, not a deployed version. No push, merge, deployment,
production configuration change or external repository write is authorized or done.
The original checkout and its pre-existing `public/og-image.webp` are preserved.

Approved policy and implementation contracts are in
[FREE_PRO_IMPLEMENTATION](FREE_PRO_IMPLEMENTATION.md): non-guest plus verified
email, indefinite duration, current PRO quotas, existing manual PRO validity,
preserved over-limit data and purchased balances, and paused PRO/slot/ISDOC sales.
Effective access is separate from factual billing and excludes Enterprise/admin
privileges. Promotional access defaults off; the three purchase controls default
closed independently. Billing reconciliation and settlement remain intact.

Independent source reviewers `/root/free_pro_security` and `/root/free_pro_qa`
accepted the implementation with no unresolved blocking source findings. Review
scope was the baseline plus the final working-tree changes, not production.
Company-owner authorization, numbered WooCommerce retry completion, fresh guest/
email checks and provider-derived Enterprise credit availability were corrected
and covered by regressions. QA independently passed 55 PHP tests (282 assertions),
32 affected frontend tests and additional auth/recovery coverage. Security
inspected source and test evidence without modifying code or rerunning the suites.

| Final validation | Observed result |
| --- | --- |
| Full PHPUnit | PASS: 1,631 tests, 6,891 assertions |
| Full Vitest | PASS: 119 files, 708 tests |
| Promotion browser smoke | PASS: 4 Playwright tests, including authentication setup |
| Full Pint | PASS: 1,036 files |
| Full PHPStan | PASS: no errors; 11 obsolete ignored occurrences removed, none added |
| ESLint | PASS: 0 errors, 48 existing warnings; cap remains 52 |
| Typecheck ratchet | PASS: no new errors, baseline unchanged |
| Locale parity | PASS: EN/SK/ES/DE/CS; four existing SK-only informational keys |
| Production frontend build | PASS; existing bundle/import warnings remain |
| User documentation | 21 articles checked, including five localized access guides |
| Graphify | Existing graph queried first; isolated AST-only update, no LLM extraction |
| Diff whitespace | PASS |

All execution used synthetic accounts and disposable SQLite databases. Existing
email DNS validation passed against an internal-only fixture resolver with no
external forwarding; validation was not weakened. Browser smoke used a separate
isolated database and a loopback transport so WebCrypto retained its secure-context
requirements. No real funds, merchant credentials or production services were used.

**Next milestone: SATFLUX-003 - release and activation readiness.** Before activation,
verify a complete PRO-only purchase closure at the exact deployed BTCPay provider,
including direct checkout, credit portals and prefunded AutoRenew; Satflux-only
guards do not prove that closure. Verify hosted-relay cost enforcement, configured
plans, replica/cache propagation and reversal. Future notice/data-access terms and
paid reopening need owner decisions. No subscription cancellation or plan deletion
is an acceptable substitute. Obtain separate merge/deployment/activation approval.

## Observation baseline

Bootstrap observation date: 2026-10-13. Scope: development orchestration only.
Initial root checkout: `fix/btcpay-245-wallets` at
`f2c8536797d8ebb9d74d392bd24a73be8dd8236f`, origin `webiumsk/satflux`.
Pre-existing modified `public/og-image.webp` belongs to the Product Owner and must
remain untouched. That initial checkout was older than recent merged payment/recovery work.
Remote PR status is evidence about GitHub, not proof of a deployed server version.

Related repositories and portable path resolution are recorded in
[the registry](../../workspace/projects.yaml). Existing siblings were readable.
The plugin sibling is a stale clean checkout on `feat/sepa-nop-public-history`
at `4185325`; a newer existing isolated worktree provided current API/CI evidence.
WooCommerce is a clean `main` checkout at `061e2e83477db023ec36227dfab76f604e3818b7`.
No external repository was modified, fetched, moved or converted into a submodule.

## Current architecture and versions

See [ecosystem architecture](ECOSYSTEM_ARCHITECTURE.md) for contracts/trust boundaries.
Lockfile evidence in this checkout:

| Component | Locked version |
| --- | --- |
| Laravel / Sanctum | 12.69.3 / 4.3.3 |
| PHPUnit / Pint / Larastan | 11.5.50 / 1.29.3 / 3.10.0 |
| Vue / TypeScript / Vite | 3.5.40 / 5.9.3 / 5.4.21 |
| Pinia / Vue Router | 2.3.1 / 4.6.4 |
| Vitest / Playwright | 4.1.10 / 1.60.0 |
| @evolu/vue / @evolu/web | 1.4.0 / 2.4.0 |
| Compose PostgreSQL / Redis | 16-alpine / 7-alpine |

Source: composer.lock, package-lock.json, docker-compose.yml. Node requirement is
>=22; existing CLI Node is 22.18.0. Application package version is 2.0.0.
Recent maintained plugins use .NET SDK 10 with exact BTCPay 2.4.5 host validation.
Actual WooCommerce header version is 2.0.23; metadata compatibility is not test proof.

## Verified recent milestones

GitHub CLI read-only observations on the baseline date:

No open Satflux issues were returned by the inspected issue-list query. That does
not establish that there is no work outside GitHub issues or in other repositories.

- Satflux [#389](https://github.com/webiumsk/satflux/pull/389),
  [#390](https://github.com/webiumsk/satflux/pull/390),
  [#391](https://github.com/webiumsk/satflux/pull/391) are merged: BTCPay 2.4.5
  compatibility, wallet safety and merchant Cashu recovery.
- Satflux [#392](https://github.com/webiumsk/satflux/pull/392) is merged: readable
  Cashu store labels. This is not yet present in the inspected older root checkout.
- Webium plugins [#32](https://github.com/webiumsk/BTCPayServerPluginsWebium/pull/32)
  through [#36](https://github.com/webiumsk/BTCPayServerPluginsWebium/pull/36) are merged:
  host/client guards, settlement recovery, persisted checkout payout snapshots,
  merchant recovery API and Lightning checkout instructions. Current inspected
  CashuMelt source version is 1.4.0.3, LNAddressConnect 1.0.4.0.
- Earlier billing fulfillment/revoked-entitlement work is merged in Satflux
  [#388](https://github.com/webiumsk/satflux/pull/388).

These statuses were freshly read; historical test counts in old handoff documents
were not rerun during bootstrap. Live production settings and deployment versions
were not inspected. No new production transaction, invoice or wallet test was made.

## Development tools and testing

Codex CLI 0.162.1 supports standalone project agent TOML files, multi-agent mode,
strict configuration and app-server discovery. Ten roles and thirteen local skills
are defined. Config caps spawned threads at three, plus main. Live session capacity
is four total. Current-session routing of newly defined roles is not assumed.

Installed: Git 2.43.0, GitHub CLI 2.93.0, Docker 29.9.0, Compose 5.6.0, Node 22.18.0,
npm 11.17.0, Python, Graphify and local Chrome/Playwright dependencies. PHP/Composer
are absent from host PATH; use an existing verified isolated PHP environment. .NET
SDK 10.0.400 was verified outside default PATH; resolve it through the user's environment without
installing or tracking a machine-specific path. WP CLI is absent. Existing
`openaiDeveloperDocs` MCP is enabled; no MCP servers or plugins were installed.
System Python already includes PyYAML; default environment Python does not.

CI entry points and isolation requirements are in the registry and workflow.
Local-first relay tests, plugin exact-host suites and WooCommerce functional tests
must be selected for actual future scope. Do not run deployment scripts as checks.

## Known risks and instruction drift

1. Raw BTCPay ID prohibition in older README/architecture/Cursor material conflicts
   with the narrow existing Pay Button/API-key formatting exception in CLAUDE.md and
   StoreController. Preserve that exception; prohibit new exposure. Do not broaden it.
2. Older testing docs say Vitest is absent; package/CI show Vitest, typecheck and five
   locales. Existing rules and historical documents are preserved, not silently edited.
3. WebhookController rejects missing/invalid signature and missing secret; weaker old
   wording must not justify unsigned events. Ownership includes audited admin/support.
4. Older plugin checkout lacks current host guard/CI. Resolve the intended checkout
   before tests. The discovered BTCPay fork is not the pinned build dependency.
5. WooCommerce README/version/legacy CLAUDE instructions drift from current source.
   No comprehensive automated WordPress suite was found; plan isolated coverage.
6. PHPUnit defaults can be overridden by inherited environment/cached config; inspect
   effective disposable targets before tests. E2E traces may contain sensitive state.
7. Cashu safety depends on mint trust, original payment snapshots, Data Protection keys
   and worker-version consistency. Outstanding-payment guards may not be bypassed.
8. New project configuration may need discovery in a future client session; this
   bootstrap does not restart the active session or modify user trust/configuration.

## Historical Free-PRO bootstrap boundary

The bootstrap permitted only a subsequent read-only audit. That historical boundary
is superseded for bounded Satflux source/test implementation by the explicit owner
approval recorded above and in [DECISION_LOG](DECISION_LOG.md). Older audit-only
instructions are not release authorization. Backend, payments, frontend, designer,
security and QA reviews were completed; related repositories remained read-only.

Product Owner clarification: Satflux currently has ZERO paying subscribers. This is
owner-provided context, not an independently verified billing finding. The audit used
non-production evidence and stated its limits; it did not access or modify production
billing data. Existing paying-customer migration, refunds and discontinued-paid-access communications are
outside the intended scope unless evidence contradicts the assumption.

The owner also reports no in-flight payments. Both statements remain owner-provided
context. Billing handling is retained regardless of those assumptions. Eligibility,
duration and quotas were approved; ending/reopening and deployment details remain
separate decisions.

## Bootstrap validation record

Completed setup checks at the bootstrap working-tree scope, base revision above:

| Check | Observed result |
| --- | --- |
| `python3 scripts/orchestration/validate.py --check-projects` | PASS: ten roles, thirteen skills, seven entries, all origins and local documentation links |
| System skill-creator validator with existing system Python/PyYAML | PASS for all thirteen SKILL.md files; no dependencies installed |
| Installed CLI strict `config/read` and `skills/list` | PASS: project limit of three spawned threads and thirteen enabled project skills |
| Installed CLI `debug prompt-input` | PASS: root AGENTS.md discovered; raw prompt discarded |
| Strict unknown-field negative control | PASS: unsupported field rejected |
| Ten role session-setting probes | PASS: developer instructions and optional read-only sandbox settings accepted |
| Three synthetic RPC transport tests | PASS: fragmented/buffered frames, partial-frame timeout and RPC error handling |
| Preservation fingerprints | PASS: existing README/CLAUDE/SECURITY, Cursor/GitHub files, global configuration/auth where present and pre-existing image unchanged |

Main `/root` authored orchestration. Genuine delegated agents audited the ecosystem,
NBDP reference and security boundaries. Independent reviewers `/root/security_audit`
and `/root/reference_audit` checked the new working-tree scope using the new security
and QA role files. Initial review accepted scope/authorization/structure and identified
probe assertion/transport issues; main corrected them. Follow-up security review
accepted the revised scope with no material findings. Independent QA accepted the
setup, reran all three transport tests successfully and checked twenty immediate-exit
response cases. Neither reviewer independently reran the live CLI probe; that result
is coordinator evidence. No blocking review finding remains.
Project-resume and cross-repository-coordination skill dry runs selected read-only
audit waves and did not authorize promotional implementation.

The CLI probe needed normal tool-approved execution outside the default sandbox to
start existing CLI runtime machinery. It preserved HOME/CODEX_HOME, configuration,
credentials and trust; only child diagnostics/state used temporary paths. No model
thread was created and the active session was not restarted. Standalone role metadata
conforms to the documented schema and TOML checks; native named-role selection/hot
reload in this active client was not exercised. Do not claim that setting acceptance
alone demonstrates native routing. Actual explicit-role subagent delegation worked.

No application/plugin code, CI configuration, database, wallet, invoice or production
settings changed during bootstrap. The initial local setup performed no clone,
dependency installation, external write, push, PR, merge, publication or deployment.

## Authorized publication follow-up

The Product Owner authorized committing/pushing the orchestration files and opening
an unmerged PR only in `webiumsk/satflux`. The dedicated branch is
`chore/persistent-multi-agent-bootstrap`, based on verified default branch `master`
at `13011b707d3e13c2d26f55889c15381c357b983b`. Initial checkout observations above
remain historical evidence. The image hash was unchanged when switching branches;
the pre-existing modified image remains excluded from commit and PR scope. Re-run
validation evidence and publication results belong in the PR handoff. Sibling writes,
merging, deployments and starting the free-PRO audit are not authorized by this step.

## PR review follow-up

Bootstrap PR [#393](https://github.com/webiumsk/satflux/pull/393) is open against master.
Its initial commit `bc4c4a2ee6b6b05f8a03c3c56a0fe6d3f559522e` passed PHP, Node and
Playwright CI. CodeRabbit identified three validation issues: settings need checkout
project-layer provenance, registry paths need exact checkout-root verification and
Git metadata reads need bounded timeouts. Follow-up guards retain value checks,
reject matching global/session/other-checkout origins and nested directories, and
report a 10-second Git timeout as CLI failure. Nine synthetic tests and the installed
CLI probe pass; all helper functions have docstrings. Independent security/QA review
accepted these fixes. Updated-head CI/review is separate from the initial green run.

The Product Owner requested recurring CI/CodeRabbit checks. Local reminder capability
was tested with the installed Codex queue command into this same thread. Runtime
watcher state is temporary and private, excluded from Git. Monitoring must use fixed
read-only GitHub requests/reminders, deduplicate changes, retain managed permissions,
stop when the PR closes and have a bounded lifetime. It must not automatically edit,
publish, merge, deploy or begin the free-PRO audit. The recorded role-routing limit
and non-production-only future billing audit scope remain unchanged.

## 2026-10-19: offline security and reliability investigation

The Product Owner authorized source-level investigation and focused fixes in
Satflux and the owned CashuMelt plugin, with no production access or deployment.
The evidence, confidence limits, code references, test results and production
diagnostic runbook are recorded in
[the incident analysis](../security/incident-analysis-2026-10-18.md) and
[the read-only runbook](../operations/SECURITY_RELIABILITY_DIAGNOSTICS_2026-10.md).
Satflux has 82 focused tests/298 assertions passing; its full PHP, Node and E2E
CI jobs pass on PR #395. CashuMelt's 147/147 tests and build passed on PR #40;
the latest independent review requested a narrow upgrade-era manual-review fix,
now under re-review. Both changes are committed and published as open PRs. Neither
is merged, packaged or deployed. No production behavior is verified. Next
milestone: finish PR review and exact-head CI, then obtain redacted aggregate
diagnostics from the operator. No account, wallet, billing, payment or production
configuration action is implied.

## 2026-10-21: Tickets sales and merchant ordering implementation

The Product Owner approved the generic public sales design and authorized its
implementation together with persisted inline ticket-type ordering. Existing
Satflux event/type forms remain authoritative; public sales and hosted payment stay
on the BTCPay host. The feature contract and ownership are recorded in
[TICKETS_SALES_ORDERING](TICKETS_SALES_ORDERING.md).

Fresh GitHub evidence verifies PR #396 merged into master at
`fe0ace6eeeab4019a557326c4b05166b53e99f59`; the new consumer branch starts there.
The owned plugin branch starts at `6bdd68828673c8a75aa744f9f3483b7a251a938d`,
with the prior uncommitted Tickets 2.0.4 candidate preserved as a dependency.
Original checkouts and the owner's modified image are untouched. The feature
candidate is 2.1.0; repository publication and deployment status are separate gates.

Current local evidence: 67 related PHP tests/209 assertions, followed by 34 new
order-specific tests/91 assertions including audited admin/support access; full
PHPStan passes without expanding its baseline. Frontend specialist reports focused
31 cases, lint, five-locale parity, unchanged typecheck ratchet and build passing.
Actual rendered plugin-page QA, provider database tests and final security review
are in progress. Do not treat these interim results as final acceptance.


### Final Tickets local acceptance

Implementation and independent source/security/QA review are complete locally.
The frozen provider passes 100/100 tests, including 15 actual disposable PostgreSQL
cases; exact-host build and guard pass. Independent browser QA passes 32 actual
compiled-Razor/embedded-asset checks plus 9 visual/contrast scenarios, including
private virtual-location exclusion and stable responsive contact/summary behaviour.
The provider DLL SHA256 is
`a24b3e36f55545b82fa4894df608adc670bd09b92b913a8f1db327127240b2da`.

Final consumer verification passes 850 Vitest cases/128 files (serialized without
changing timeouts), 77 relevant PHP/documentation cases/241 assertions, PHPStan,
Pint, lint, five-locale parity, unchanged typecheck ratchet and production build.
Earlier parallel local timeouts are superseded by the unchanged complete serial
pass; quiet agent-reporter output was expected. See the implementation record for
review scope and limitations rather than inferring deployment readiness.

New branches remain local/uncommitted. Original plugin WIP and pinned core source
are unchanged; no production or remote publication was performed. Next milestone:
separate repository publication/CI handoff, provider-first staging and reviewed
release/rollback planning. No installer, merge or deployment occurred. Existing
Greenfield purchase recovery, strict inventory holds, all-zero checkout and external
email/raffle exactly-once guarantees remain outside this completed layout scope.


### Tickets delivery authorization and local installer

The Product Owner subsequently authorized a local installable Tickets 2.1.0 package
and commit/push of the Satflux consumer branch `feat/tickets-sales-ordering` to the
owned GitHub repository. This supersedes the local-only publication gate above for
these two actions. Plugin repository publication, merge, installation and deployment
remain separate gates.

The pinned BTCPay 2.4.5 PluginPacker produced the standalone 2.1.0.0 installer from
the unchanged reviewed Release DLL. Archive SHA256:
`d892b22df02ddbe5970e2faab70489d14f05be30c056ca822b5965077c0b6df3`.
The package sidecar declares `BTCPayServer >=2.4.5`; its DLL checksum matches the
accepted build above. Existing host/transitive dependency advisories remain open;
packaging does not upgrade dependencies or establish production readiness.

Consumer publication is scoped to the 23 reviewed application/test/localization/doc
files. Feature-branch pushes alone do not trigger this repository's CI workflow;
exact-head PR CI and representative provider-first staging are the next gates.
No production configuration or `.env` change is required by this milestone.


## 2026-10-22: Tickets 2.1.1 public language and local times

The Product Owner reported successful 2.1.0 operation with incorrect public UI
language/time presentation. Source and an offline browser reproduction confirm
invariant host culture forces English and the old view explicitly displays UTC.
The focused 2.1.1 candidate resolves supported browser/explicit language, preserves
drafts during a bounded presentation-only language POST, and formats UTC instants
in the visitor's labelled browser zone with an accurate UTC fallback. No event
persistence, checkout/fulfillment, core or third-party plugin change is included.

Exact-host Release build passes; 108 non-database .NET cases (including 23 new
presentation cases) and 7 production-JS cases pass with no skips. The original
15 PostgreSQL cases belong to the preceding 2.1.0 acceptance and were not rerun for
this presentation-only delta. Independent security accepts the frozen eight-file
scope. Independent QA passes 39 focused browser checks and 32 existing browser
regressions. A scoped CSS correction closes the new picker contrast finding; the
final rebuilt embedded assets pass six light/dark viewport scenarios across all
button states, 44px keyboard/label checks and draft/local-time smoke tests.

Local installer version 2.1.1.0 contains the tested DLL SHA256
`2e4d27ee3c9aab3bab750e8c5dfcfc70c73f6aaf3e260976bc6277cdd888bcc9`.
Archive SHA256 is `3c173ddf1257943744b6d7a411b2d4e516b93b26580d90bf558b7211d33293b9`.
The previous installer and original plugin WIP remain unchanged. Consumer code
remains the already pushed `a9be6a17e63f6070b4145b3509070bd8466ee900`; its new
follow-up docs are local. Plugin changes remain uncommitted/unpublished. No live
behavior, installation, merge or production deployment is independently verified.

Independent final archive QA also passes: 103 entries, exact browser-tested DLL
and assets, CRC/path/sidecar checksums and no forbidden configuration/key/database
artifacts. The checked local installer is ready for Product Owner delivery; no
plugin source publication or production installation is performed.


## 2026-10-22: Tickets 2.1.2 header switch and asset freshness

The Product Owner reports 2.1.1 URL language works but its button and time
enhancement do not. Independent offline reproduction using the actual unchanged
shared BTCPay layout, pinned CSP and production asset handlers establishes that
fresh 2.1.1 assets work, while replaying 2.1.0 JS at the identical unversioned URL
reproduces both the UTC display and ineffective language button. No missing nonce
cause is supported. Actual operator cache provenance remains unverified. Earlier
layout-override QA was narrower and did not establish this host/cache integration.

Candidate 2.1.2 moves language selection to the header and changes constant public
copy, accessible labels, amounts and local dates immediately in the existing DOM.
It sends no request and preserves recipient inputs/drafts, quantities, delivery
choice and nonce; hidden form language/shareable URL stay synchronized. Embedded
JS/CSS have content-hash paths with exact version validation/cache policy; old
paths remain compatible. UTC storage, CSP, CSRF, payment/session/recovery and
private virtual-location boundaries remain unchanged.

Exact-host build passes (zero errors, 19 existing warnings). All 115 non-database
.NET and 10 production-JS cases pass, zero skips. Independent security accepts the
frozen eight-file digest
`a82819c1b2b71127e6bd8eaa3ded0c281311571b830d9642eaee3a32a06c81d6`.
Final full-layout/browser/cache and installer QA remain the local delivery gate.
The local 2.1.2.0 archive SHA256 is
`58dabe1628d9350f87ca8f9eaa05f0e1c7c91efe6d27ead7a8f486374dbbb207`;
its exact tested DLL SHA256 is
`06e3f19ddaa3592524a2bd99496e8816e873a1f921dc187a33abcd64212e2a65`.
The prior installer/original WIP/pinned core remain unchanged. No plugin source
commit/publication, merge, production access or deployment is performed.

Final 2.1.2 delivery gate passes: independent QA accepts 26 focused actual shared-
layout/CSP/resource-route browser cases and the exact 103-entry installer. Header
switching preserves drafts without requests, new hash paths bypass the reproduced
stale legacy script, and browser-zone/DST times are correct. Synthetic-DI/page/
submit, Chrome-engine and non-production limits are detailed in the handoff.
Operator Brave/+/-/empty-console feedback is consistent; actual cache origin is
still unverified. Ready to deliver the checked local installer; publication/CI and
representative staging remain separate next steps under applicable authorization.

QA additionally passes the host-filter CSRF check (missing token 400, valid
synthetic token 200) and stops its disposable harness. All required local delivery
gates are complete; source/package fingerprints remain unchanged.

## Tickets 2.2.0 venue-time correction (2026-10-22)

Owner-approved implementation separates physical venue time from virtual visitor time.
The owner confirms all historical physical events are `Europe/Bratislava`; the additive
provider migration backfills only their null timezone metadata and preserves all UTC
instants. New authoring extends existing forms, with provider-owned IANA catalog/civil
conversion and safe partial date updates. See TICKETS_SALES_ORDERING.md for the full
contract, bounded agent ownership, provider-first rollout and rollback limitations.
Independent security/QA and exact-host tests are in progress; no production migration,
installation, deployment or merge has been performed. Graphify graph/tool remain
unavailable; targeted source reads were used. Existing unrelated image edits remain
untouched in the original checkout. The prior 2.1.2 installer remains immutable.

### Store timezone clarification

The owner approved store-default plus event-override behavior. Source review confirms
that BTCPay 2.4.5 does not persist Satflux's outbound store `timeZone`; use local
`Store.timezone` as authority. Settings reload previously defaulted to UTC instead
of reading local data, allowing a later Save to overwrite it. A focused correction
and reload/save regression now pass within 125 related PHP cases / 386 assertions.
No store records or provisioning defaults were changed. The initial 2.2.0 candidate
is preserved; final follow-up adds safe new-event defaults and native stale-schedule
rejection before a replacement local artifact/review. No release or deployment yet.

### Final 2.2.0 source/test follow-up

Store-default behavior and the local settings read fix are complete. Native stale
forms compare original start/end UTC ticks plus original venue/type before conversion
or writes, preserve drafts and require reload on conflict. Exact-host final build
passes; provider172/172 (21 PG) and consumer125/386 pass; frontend74 focused tests,
full PHPStan/Pint, final typecheck (120 unchanged) and production build pass.
Independent security accepts bounded source, including closure of both stale native
schedule reproductions; final actual-artifact QA remains the last local gate.
Final source digests and the new archive302e2065/DLL15fbf88c identity are recorded in
TICKETS_SALES_ORDERING.md. Prior candidates are preserved. No source publication,
merge, production migration or deployment has occurred in this follow-up.

### Independent final local acceptance

Independent security accepts final provider150/consumer20 fingerprints. Independent
QA passes final8 actual native guard database cases,8 compiled browser smoke cases
and final archive/DLL/resource equality, in addition to baseline31 browser/5 real
upgrade groups and focused frontend checks. No remaining blocker in authorized scope.
Local installer2.2.0.0 final archive SHA302e206578c213b4740637df34ce8f4f5a3d96503f901e5531171251eedf82b3
contains tested DLL15fbf88ce04543226540be0fe1347d8ece46cf871223eaf7a50702856c726c0c.
Next milestone is separately coordinated provider installation before the Satflux
consumer release and deployed acceptance. Nothing was merged or deployed. Current
follow-up source changes are local; the previously pushed branch head is unchanged.

### Owner-authorized Satflux publication

The Product Owner authorizes pushing the prepared Satflux follow-up to the existing
`feat/tickets-sales-ordering` branch and PR #397. Remote head before publication is
`a9be6a17e63f6070b4145b3509070bd8466ee900`; it matches the local parent. The reviewed
20-file consumer fingerprint remains
`0efce0679cc1e1b9269c7b993c8be2cc8ba0b5476eff835c1a3815f3303c124e`.
Only publication documentation is added after final independent review. Previous
local test results remain evidence for unchanged application source, not new CI
results. Exact published-head CI and GitHub review are the next source gates; install
provider 2.2.0 before consumer deployment. This authorizes no plugin publication,
merge, production migration or deployment. The unrelated original image edit remains
excluded from this branch and commit.
