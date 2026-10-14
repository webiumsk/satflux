# Development decision log

This log starts with the current bootstrap. It does not reconstruct undocumented
historical decisions. Product history is linked from PROJECT_STATE with source evidence.

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
