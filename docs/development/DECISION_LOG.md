# Development decision log

## 2026-10-16: Cashu recovery API controller classification

Within the existing owner-authorized Satflux/owned-CashuMelt defect work, change
the recovery API to `ControllerBase`. Pinned BTCPay 2.4.5 classifies its previous
`Controller` base as UI and rejects API export/verify for missing form CSRF.
Keep API-key-only authentication, store policy, strict JSON, private headers and
Satflux single-use owner email codes. Do not exempt native UI or modify core.

Require the actual host filter regression with a UI rejection control, denied
non-API authentication and exact consumer wire assertions. Candidate 1.4.0.6 needs
a new provider PR because #38 was owner-merged. Independent security/QA, native
regressions, package identity and exact-head CI are handoff gates. No production
access, recovery, merge, release publication or deployment is performed.

This log starts with the current bootstrap. It does not reconstruct undocumented
historical decisions. Product history is linked from PROJECT_STATE with source evidence.

## 2026-10-16: recover durable Cashu payment phases without interpreting HTTP errors as settlement

Status: bounded continuation of owner-authorized owned CashuMelt/Satflux repair.
The owner confirms plugin 1.4.0.4 and two native failures; a counter of three
obligations is not evidence of a third failure. Implement the reviewed contract
[CASHU_PAYMENT_RECOVERY](CASHU_PAYMENT_RECOVERY.md), candidate 1.4.0.5, using existing
restore-only issuance and exact original-payout recovery. Require original snapshot
and issuance/proof binding; preserve legacy, incomplete, corrupt, mismatched and
unknown evidence. Never infer unpaid/settled state from HTTP 400/404 or age.

Expose only allowlisted read-only blocker categories/reasons/phases. Keep counts of
FAILED, other unfinished and terminal-with-material records distinct. Diagnostics
do not authorize operations. Automatic retry accounting is monotonic under the
quote lock; explicit authorized manual retry retains existing reset behavior.
Independent security/QA, disposable tests, package identity and separate exact-head
CI remain delivery gates. No production/fund operations, core/third-party changes,
merge, package release or deployment follow from source implementation approval.

## 2026-10-16: show Cashu wallet-change safety before editing

Status: owner-authorized bounded Satflux UX follow-up. Present the existing
merchant-scoped safety evidence on the Cashu connection screen before edit/save,
with recovery navigation and explicit unavailable status. Add the aggregate to the
existing settings read; preserve first-setup/Lightning-switch behavior. Refresh must
not overwrite edits, reuse grants, send codes or initiate fund operations. Retain
server-side write checks and existing email confirmation. Update all five locales
and user guides; independently review source and verify focused regressions plus
existing static/build gates before publication. No provider/core/package change,
production operation, merge or deployment follows from this authorization.

## 2026-10-13: project-local orchestration

Status: authorized setup decision from the Product Owner's bootstrap request.
Main remains the PM/technical lead/orchestrator and owns shared state and publication.
Specialists have bounded ownership and independent security/QA review. Ten roles live
in `.codex/agents/`, reusable skills in `.agents/skills/`, portable registry in workspace/.
Keep existing Claude/Cursor/CI/security conventions; record contradictions explicitly.
External repositories remain read-only during setup. No global settings are changed.

## 2026-10-13: conservative concurrency and inherited settings

Status: bootstrap implementation choice, validated against CLI 0.162.1.
Set `max_concurrent_threads_per_session` to 3 because the supported key counts spawned
threads excluding main. This yields four simultaneous agents total, matching current
runtime capacity. Inherit model/reasoning/provider/approval settings; security, QA and
researcher default read-only. Live runtime permissions remain authoritative.

## 2026-10-13: independent repositories and version evidence

Status: authorized workspace constraint.
Keep separate histories/PRs/tests/releases. Use relative/env-configurable paths, verify
remotes, and distinguish stale checkout, fresh PR status and unverified deployment.
Do not create a monorepo, submodules, speculative relay repository or external edits.
Treat the NBDP workflow as reference, adapting ownership and trust boundaries to
Satflux rather than copying protocol-specific instructions or local Git workarounds.

## 2026-10-13: temporarily free PRO

Status: PROPOSED, NOT AUTHORIZED FOR IMPLEMENTATION.
Preferred architecture is a centralized reversible promotional entitlement policy,
separate from billing, security authorization, administrative privilege and limits.
The next task is a read-only multi-agent audit. Eligibility, promotion end/data access,
costs, quotas, existing subscriber behavior and rollout require Product Owner decisions.
Do not create subscriptions, cancel recurring payments or grant Enterprise/admin rights.

## Publication follow-up: owner clarification and authorization

Status: authorized orchestration publication only. Create a dedicated Satflux branch,
commit only prepared orchestration files, push to the owned repository and open a PR
to verified default branch `master`. Do not merge, deploy, change product code or
write to sibling repositories. Do not start the free-PRO audit in this step.

The Product Owner reports ZERO paying subscribers and intends promotional PRO access
for eligible registered users. This statement is not a verified production billing
finding. The future audit should use available non-production evidence only and
document its limitations. Existing paid-customer migration/refunds/communications
are not required under the stated assumption; revisit only if evidence contradicts it.
Prioritize centralized reversible entitlements, disabling new PRO purchases and
upgrade prompts during promotion, preserving billing for future monetization,
unchanged authorization/isolation/security and a safe return to paid plans.
Implementation remains NOT AUTHORIZED.

## 2026-10-14: SATFLUX-001 policy decisions and SATFLUX-002 authorization

Status: explicit Product Owner approval for bounded Satflux implementation, tests,
localization and documentation following the read-only audit. No external repository
writes, push, merge, deployment, production billing/configuration change or activation
is authorized. Historical audit-only instructions are superseded only within this
approved source/test scope.

Approved eligibility is non-guest plus verified email. Duration is indefinite.
Use current PRO quotas, retain over-limit objects and existing purchased allowances.
Existing manual PRO retains its current validity; no permanent grant or provenance
migration was approved. Pause all new PRO/trial/PRO-credit purchases, company slots
and ISDOC packs. Enterprise and administrative privileges remain unchanged.
No paying subscribers or in-flight payments exist according to the owner; production
records were neither accessed nor changed to verify those statements.

Implement effective access separately from factual billing with an explicit PRO
feature allowlist, preserving Enterprise exclusion, tenant/company-owner checks,
guest/verification restrictions and existing fulfillment/reconciliation. Promotion
defaults off; purchase flags default closed independently. No subscription, role,
email verification, trial usage or balance is manufactured by promotional access.

Source implementation passed independent security/QA review and full validation
recorded in PROJECT_STATE. Targeted fixes preserve numbered WooCommerce completion,
owner/member isolation, fresh verified local writes and actual-provider Enterprise
credit behavior. PHPStan debt was reduced without adding suppressions.

Release prerequisite: Satflux purchase guards alone cannot close direct BTCPay sales,
funding portals or prefunded AutoRenew. Verify the exact deployed provider and a
PRO-only closure before activation; plan deletion or subscription cancellation is
not authorized. Hosted-relay costs and rollout propagation also require readiness
evidence. Future notice/data-access policy, permanent manual grants and paid reopening
remain separate owner decisions. Next milestone is SATFLUX-003 readiness.

## 2026-10-14: SATFLUX-003 readiness continuation and provider boundary

Status: owner-authorized readiness preparation after the local implementation.
The owner reports deployed BTCPay Server 2.4.5. This identifies the intended version,
not the exact deployed build, plugin inventory or live purchase closure.
Independent payments/devops/security/QA source reviews completed read-only; the
coordinator records documentation only. No provider or production changes authorized.

Activation is NO-GO: the modern core provider reference has independently reachable
checkout, funding, migration and prefunded AutoRenew paths. Existing Retired/Renewable
concepts do not provide a complete sales pause. Recommend separately authorizing a
central reversible policy in owned `webiumsk/btcpayserver` at a selected compatible
revision, preserving subscriber intentions/balances/settlement and Enterprise.
No automatic catch-up debit on reopening; no blanket cancellation or plan deletion.

Clarified reversal: current-artifact promotion-off may retain data while blocking
regular legacy reads/exports and local invoicing navigation. Older code ignores new
purchase flags and may reopen sales. Routine reversal uses the new reviewed artifact;
code rollback needs separately proven selective purchase closure and access terms.
No new historical read-only allowance or permanent manual grant was approved.

Prepared [FREE_PRO_READINESS](FREE_PRO_READINESS.md) includes exact source references,
staging acceptance gaps, operational/cache/backup/relay limitations and a local PR
handoff. Fresh master/CI evidence does not imply feature publication or deployment.
Repository-specific provider implementation and subsequent publication/merge/release/
activation require their own applicable approval.

## 2026-10-14: SATFLUX-004 owned-provider implementation approval

Status: explicit owner approval for scoped subscription sales-pause implementation
and isolated tests in `webiumsk/btcpayserver` for BTCPay 2.4.5. No push, merge,
deployment, release, production configuration/record changes or activation.
Upstream and unrelated repositories remain read-only.

Use exact v2.4.5 baseline `5d0745cae6be5d8210459e38813f317673aa97b8` in an isolated
owned branch. Central pause defaults off and uses validated store/offering/plan
identities; Enterprise, permissions, settlement/recovery and balances remain intact.
Block new intent/execution and prefunded/scheduled PRO work, preserve stored
preferences, and prevent surprise catch-up debit on reopening. Existing settings
may hold a scoped resume barrier; no schema/subscriber/plan migration is authorized.
Exact project dependencies may be restored privately for authorized isolated tests;
no tools are installed or package versions changed. Independent security/QA review
must assess the final provider implementation before release readiness advances.

### SATFLUX-004 completion evidence

The bounded provider implementation is complete locally on the exact approved host
baseline, commit `734791fdd8ac6df46088c43fa0152ad5f185be3e`. Independent security and QA accept the final source; native policy 20,
real configuration 3, lifecycle integration 1 and generated documentation/help 1
tests pass. Release build has zero errors and three obsolete setter warnings only
in synthetic invoice fixtures. Swagger and whitespace checks pass. Source fingerprint,
contract, evidence limits and runbook are in
[FREE_PRO_PROVIDER_IMPLEMENTATION](FREE_PRO_PROVIDER_IMPLEMENTATION.md).

The enabled policy pauses all non-exempt plans in the configured offering, including
unknown/new identities; only explicit distinct Enterprise IDs remain exempt. Durable
uncached JSONB barriers in existing settings retain monotonic cutoff and individual
historical-fulfillment holds. Turning OFF does not debit old credit automatically;
deliberate fresh purchase reauthorizes future renewal and buys a full current period
from held Grace. Historical obligations retain dating, genuine fulfillment and
idempotency. Do not erase barriers or downgrade to stock workers to reopen sales.

No push/merge/deploy or production mutation occurred. Synthetic invoice lifecycle
coverage is not Bitcoin/NBXplorer regtest evidence or full upstream-suite acceptance.
Next proposed milestone is SATFLUX-005 coordinated staging and release handoff;
deployed build/mappings/replicas/legacy inventory, relay costs/quotas, private restore,
exact release-head CI and end-access/reopening decisions still gate activation.

## 2026-10-14: owner-approved feature branch publication

The Product Owner requested push after the reviewed SATFLUX-004 handoff. Main
published `feat/free-pro-access` to `webiumsk/satflux` and
`feat/satflux-pro-sales-pause` to `webiumsk/btcpayserver`. Verified initial remote
heads are `a5ed908e384936678d4b41bdddf89c07e2eb9489` and
`734791fdd8ac6df46088c43fa0152ad5f185be3e`, respectively. Both were new remote
branches; no force, tags, upstream write, PR creation, merge or deployment.

Publication authorization supersedes the earlier no-push milestone boundary only
for these owned feature branches. This follow-up changes documentation only; no
application implementation or test rerun was necessary. No feature CI runs were
returned by the current GitHub queries; no remote validation success is claimed.
Release/base/ancestry review, exact head CI, operational evidence and explicit
applicable merge/release/deployment/activation approvals remain outstanding.

## 2026-10-14: provider work withdrawn; Satflux-only delivery

The Product Owner clarified that BTCPay must not be changed and then explicitly
requested deletion of the published provider feature branch. Main deleted only
`webiumsk/btcpayserver:feat/satflux-pro-sales-pause`, protected by an expected-head
lease for reviewed commit `734791fdd8ac6df46088c43fa0152ad5f185be3e`. Remote
verification confirms absence; master remains
`3ec6a9847bd06e5dad348caaec373276dc718520`. No provider merge/deployment or
production mutation occurred. Local source is retained as historical evidence;
no republication/integration/deployment is authorized.

SATFLUX-004 is withdrawn from the release plan. The Satflux feature branch remains
published and application code is unchanged. The owner accepted the stated limitation
that Satflux guards do not block independently reachable direct BTCPay purchases;
provider-side automatic credit renewals also remain outside those guards. Do not
claim global purchase closure or deploy provider pause controls from the historical
runbook. SATFLUX-005 is narrowed to Satflux review/CI/staging and its existing
operational readiness. Merge/deployment/activation still require applicable approval.

## 2026-10-14: approved Satflux CI repair

The owner requested repair of PR #394 CI 38563933755 at `c22660c0`. Keep the scope
Satflux only. A confirmed 429 is fixed by an independent ordinary checkout fixture
account, not by increasing/disabling production limits. Real authentication/CSRF
and signed settlement remain in the browser scenario; JSON/status diagnostics must
not print document snapshots or auth state.

The typecheck wrapper must reject silent nonzero/compiler failures. Its previous
local zero-error success was unreliable and is superseded by the real captured
121-error run. The baseline may refresh only the 32 proven equivalent existing
diagnostic prefixes, retaining exactly 100 groups/121 occurrences and file/code
allowances. No broad suppression or error-count increase is permitted. Independent
security and QA must inspect the migration proof and regression behavior.

Use the existing feature branch for the reviewed repair and authorized publication;
final PR head CI is required. Local/remote test results remain distinct. No merge,
deployment, activation, provider code/configuration or production billing changes.

Local completion: independent security and QA accept the bounded fix. Full Playwright
passes 20 tests, three intentional promotion-only skips, zero failures/retries in
2.5 minutes; real checkout creation/signed settlement and recovery are exercised.
Six runner regressions, actual 121-diagnostic typecheck, 119 Vitest files/708 tests,
lint (zero errors/48 existing warnings), five-language parity, build, changed-seeder
Pint/syntax, Graphify AST update and doc-link/whitespace checks pass. Browser/runtime
and network remain disposable/synthetic, with no production connection. Fresh remote
head CI must be checked after this publication; local results are not its substitute.

Remote completion: repair commit `7b06ed7f1c1b7bda708e62887953a7a6303c6582`
passed all PHP, frontend and E2E jobs in
[CI 38594038116](https://github.com/webiumsk/satflux/actions/runs/38594038116).
Verified counts: 1,631 PHP tests/6,891 assertions, Pint 1,036 files, clean PHPStan,
six gate regressions, actual 121-error baseline with no new errors, 119 Vitest files/
708 tests, 20 E2E passes/three intentional promo skips/no retries. Lint, locales,
builds and dependency audits succeeded. The completion follow-up changes docs only;
later PR heads still need matching checks. Local task containers/network/proxy were
stopped/removed; provider scope remains withdrawn. No merge or deployment occurred.

## 2026-10-15: bounded Cashu wallet-change repair

Status: authorized Satflux defect repair on the existing unmerged feature branch.
The owner reports missing six-digit confirmation and an outstanding-settlement error
on unchanged saves, with two settled and one expired unpaid payment displayed.
These observations do not authorize inspecting or modifying production wallets.
The no-BTCPay-change constraint remains binding.

Reuse the existing password/session and email challenge, centralize configured Cashu
and shared wallet-path enforcement under the store lock, and preserve tenant/guest/
verification restrictions. Wizard selection alone is not a configured wallet: use
the provider's exact empty settings response or explicit missing configuration to
recognize first setup; unknown/malformed reads cannot remove a confirmation gate.

Treat an authoritative exact settings/payment-method match on an existing pure
Cashu store as read-only, with no provider mutation, journal or code consumption.
Real changes retain outstanding-invoice and settlement checks. Explicit false safety
means outstanding funds; missing/malformed/unavailable safety means verification is
unavailable. Neither permits mutation. Terminal visible payment labels do not prove
that no retained proofs or recoverable change exist.

Capture and consume the exact pre-write grant ID after confirmed success; encrypted
journal envelopes remain readable alongside legacy records. Failed local grant
finalization must not discard recovery intent. An older reader cannot safely handle
new unresolved envelopes, so prefer a forward correction over a blind code downgrade.
The pre-existing fallback combined-write durable journal/readback gap is documented,
not expanded into a provider or recovery redesign. No combined atomicity is claimed.

Require independent security/QA source review and focused/static/frontend checks
before publication; the complete backend suite and exact head CI must pass before
final delivery. Main owns shared documentation and existing branch publication.
No environment change, migration, billing rewrite, provider
modification, merge, deployment or activation is authorized by this repair.

Candidate evidence: independent security/QA accept final application manifest
`141a69b0f473cbc552f79f42fb7b1c070699a5e690df08f89061305cac553251`.
Focused Cashu 28/147 and both corrected fixture classes 32/109 pass; full Pint,
PHPStan, 726 Vitest tests, actual unchanged 121-diagnostic typecheck, lint (zero
errors/48 existing warnings), locale parity, build and graph/docs checks pass.
Initial full PHP had 1,648 passes and eight old fixture failures; the targeted
corrections preserve their assertions and negative ownership coverage. Exact-head
remote full CI remains required, so this is not a claim of local full-suite success.

## 2026-10-15: repair newly failing Composer audit without suppressions

The Cashu source commit `e24608d1` passed all application tests/static checks in
CI 38808562407, including 1,656 PHP cases/7,025 assertions, 726 Vitest cases and
20 browser cases (three intentional promotion skips). The final Composer audit
failed on existing `symfony/http-foundation` 7.4.14 with `CVE-2026-108082`.

Within the approved Satflux CI repair, update only this dependency to 7.4.21 using
Composer's partial update and unchanged Laravel/PHP constraints. This contains both
the security fix and the subsequent header-whitespace fix; do not stop at 7.4.19.
See the [upstream release](https://symfony.com/blog/symfony-7-4-21-released) and
[advisory](https://symfony.com/blog/cve-2026-108082-redos-in-headerutils-split-reachable-through-the-cache-control-request-header).
Do not add audit ignores, broaden constraints or update unrelated dependencies.

The generated lock changes one of 167 packages, retaining all other package objects
and metadata. An isolated manifests-only PHP 8.3/Composer run passes strict validation
and fresh `audit --locked` with no advisories. Existing vendor and production are
untouched. Security/QA must independently accept the exact lock, and new-head CI must
install it and repeat tests before delivery. No BTCPay, merge or deployment action.

## 2026-10-16: expose blocked wallet-change evidence without weakening safety

Owner testing confirms unchanged saves work, while a changed address is blocked.
CashuMelt version 1.4.0.3 is owner-reported; visible history shows two settled rows
and one expired unpaid row. Previous final head `6b289f75` passed all CI 38809977505,
including the updated dependency, with independent final QA acceptance.

Independent read-only source reviews establish missing visibility, not the live
asset state: the provider counts retained recovery fields and separate change rows;
Satflux discards those counts and normalizes some provider states for display.
Keep the provider boolean authoritative and preserve existing recovery behavior.

Within the authorized Satflux defect repair, add only safe diagnostic status/counts
to existing error/payment-list responses and known original provider state alongside
the existing normalized field. Counts are bounded integers or unknown, never inferred
amounts or permission. Do not expose/log raw provider extras, errors or recovery
material. The BTCPay client correction is limited to safety-endpoint logging and
error redaction; authentication, retries and unrelated logs are preserved.

Update the existing UI, five locales and user guides, test denied access, stale
snapshots, counter validation, sensitive-extra stripping, read-only behavior and
unchanged write/no-op/grant safety, then require independent security/QA and exact
head CI. No production inspection, provider modification, fund movement, new recovery
action, merge or deployment. Actual refusal diagnosis still needs the new read-only
counts/raw state; do not claim it is solved from screenshots alone.

Independent security and QA accept the frozen application/locale manifest recorded
in [CASHU_SAFETY_DIAGNOSTICS](CASHU_SAFETY_DIAGNOSTICS.md). Focused backend checks,
full frontend tests, full Pint and full PHPStan pass. A test-only translation mock
was made compatible with the existing TypeScript target without changing assertions
or expanding a baseline. Publication requires the remaining frontend checks and
fresh exact-head CI; local vendor does not validate the locked security patch.
Remaining frontend checks subsequently pass: actual typecheck (121 unchanged
baseline diagnostics), lint (zero errors/48 existing warnings), five-locale parity
and production build. Exact published-head CI remains required for handoff.

## 2026-10-16: owned CashuMelt and Satflux change recovery implementation

Status: explicitly authorized by the Product Owner. The restriction on changing
BTCPay concerns core and third-party plugins; the owned CashuMelt plugin may be
changed together with Satflux. Earlier blanket provider restrictions are superseded
only for this bounded owned-plugin initiative, not the withdrawn core subscription
sales-pause branch. No production access, fund operations, merge, release publication
or deployment is authorized.

Implement [CASHU_CHANGE_RECOVERY](CASHU_CHANGE_RECOVERY.md) using existing provider
recovery services and advisory locks. Add only Greenfield-scoped summary/export/
verification routes in CashuMelt and actual-owner-only action-bound six-digit
confirmation in Satflux. Consume authorization before one provider dispatch, sanitize
logs at source, retain uncertain outcomes and fresh verification, and keep unsupported
old-plugin behavior explicit. Do not lower sweep limits or discard small balances.

Separate repository branches, plugin 1.4.0.4 before dependent Satflux controls,
unchanged exact host pin, frozen-source independent security and QA, and disposable
stubbed tests are required. Preserve all existing billing, tenant and wallet-change
protections. Main owns shared state and publication. Application and plugin specialists
have disjoint writable ownership; core and other plugin source remain untouched.

Implementation evidence: independent security accepts plugin manifest `00769c84...`
and Satflux manifest `e1321b46...` (full hashes in the contract); independent QA
accepts both. Plugin `b86e6193` has green exact-head CI 38824816578 (583 maintained
tests), and the private 1.4.0.4 package matches the independently tested DLL. Satflux
full PHP 1,737/7,600 assertions, full frontend 778 cases, independent final focus 30,
Pint 1,041 files, PHPStan/typecheck/lint/parity/build and AST graph/docs checks pass.
Fresh-lock exact-head Satflux CI remains required after publication; real-host
installation and cross-application wire-level recovery remain staging acceptance.
No package release, merge, deployment, production change or real recovery performed.


## 2026-10-20: scoped ticket images and retirement of email password reset

Status: owner-authorized implementation; publication and deployment remain separate.
Fix event-scoped ticket-type paths and use the merchant-scoped event logo endpoint,
matching the working store-logo permission model. Never fall back to the server key
for ticket images or infer URLs from opaque file IDs. Require the owned Tickets
2.0.4 additive logo response before deploying the consumer; preserve identity, schema,
existing ticket email QR behavior, core and third-party plugins.

Stage image replacement and removal until Save; retain the persisted event ID and
pending changes on failure. Independent QA identified and closed removal before Save.
Support JPEG/PNG/GIF/WebP up to 2 MiB in all three layers.

The owner chooses a temporary migration password entry for old accounts without
recovery/passkey credentials. Retire reset email and reset-token consumption uniformly,
including framework notification suppression, without invalidating existing accounts,
sessions or hashes. Redirect both historic URL formats on the server; this does not
remove secrets from old incoming access logs. Do not attribute the reported default
Laravel notices to abuse without request evidence. Independent security's missing
query-link server redirect is closed. See the contract for reproducible checks and
remaining provider persistence/staging gates. No production inspection was performed.


### Satflux publication authorization

The Product Owner subsequently requests pushing the Satflux changes to GitHub.
Commit/push only the reviewed consumer repair and its scoped tests/documentation in
`webiumsk/satflux`. Preserve local plugin changes and its installer without publishing
them under this request. Exact-head CI/review and provider-first staging remain
required; the push request does not authorize merging or deploying either repository.


### PR #396 obsolete reset-test alignment

The initial complete PHP CI run exposes five general-auth tests still asserting
legacy reset behavior. Preserve the Product Owner's disabled-reset decision and
update those expectations to the explicit 410/no-side-effect contract. Do not restore
email resets, skip the tests or change application/CI configuration for this fix.
The entire changed AuthTest and dedicated reset regressions pass locally (25 tests,
118 assertions) with disposable SQLite/array mail and no network. Independent QA
accepts the bounded test delta; fresh full CI is the next acceptance gate.
