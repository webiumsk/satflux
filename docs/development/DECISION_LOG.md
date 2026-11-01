# Development decision log

## 2026-11-01 - Support existing bank-mail behavior through one MIME route

The owner's confirmed Mailgun Free plan permits one inbound route. Correct the
previous two-route prerequisite within the existing bank-mail repair: one MIME
receiver chooses strict Store verification or bounded Company extraction using
server-owned recipient records. Keep strict verifier bytes and settlement guards
unchanged. Preserve company charset/multipart, envelope sender, forwarded checks,
local document matching, owner binding and encrypted job privacy. The native-to-MIME
regression also exposed inherited processing seconds in explicit compact booking
dates. Normalize that date precision only with `!d.m.Y`; preserve missing-date
fallback and all proof flags, with no history or dedup-algorithm migration.
Document legacy replay and indistinguishable same-day collision limits. Company parsing
never grants bank-origin proof or a SEPA report.

Independent backend/security/QA architecture review precedes implementation. No
paid-plan upgrade, provider/billing/UI feature or production action is included.
Document worker-first rollout and coordinated rollback, retaining parsed/native
compatibility. Failed historical MIME retrieval does not justify bypassing proof.
See [the contract and ownership](BANK_MAIL_SINGLE_ROUTE.md).

## 2026-11-01 - Separate SEPA activation from crypto wallet drift

The generic payment-method monitor incorrectly classified the SEPA activation
Boolean as a crypto receiving-wallet change. Exclude exact case-insensitive
SEPA_INSTANT alongside the existing LNURL rule in snapshots and both comparison
maps. Preserve unknown methods, crypto/Cashu guards, saved baseline, payee evidence,
locks and fresh authenticated verification. Existing SEPA-only incidents may clear
only through successful matching verification, retaining historical audits.

Independent security and QA accept the bounded patch; publish it separately from
bank-mail diagnosis. The supplied origin-review log proves processing reached the
origin guard, not authenticated payment or the exact active Mailgun route. Keep
unknown bank origin unconfirmed and distinguish owner-reported deployment from
verified merges. See [evidence](WALLET_SEPA_DRIFT_INCIDENT.md).

## 2026-10-31 - Implement useful automatic Tatra B-mail with real verification

Use existing Mailgun MIME delivery and Node runtime, isolated locked mailauth/
mailparser dependencies and no new service. Verify actual signed message content
before strict known-format transaction parsing; encrypt raw jobs and replace
private downstream exceptions. Preserve observed QR case and reference-independent
conservative event identity. Install runtime dependencies through normal deploy
source, without executing deployment. Keep unknown formats unconfirmed.

The debtor choosing a reference is normal and earlier selection probes did not
prove theft by that fact alone. Product acceptance requires a positive automatic
path; cd951b3b's manual-only fallback was temporary. Independent security/QA and
synthetic cryptographic/real-parser tests qualify source; current bank format and
actual routing remain explicit operational evidence gaps. Do not invent a bank
transaction ID, claim all-bank coverage or use real funds in automated tests.

## 2026-10-30 - Integrate SEPA isolation with exact BTCPay 2.4.7

Apply the prepared consumer repair to merged compatibility instead of modifying
the dirty original checkout. Require matching contract-v2 origin/original-account
proof; preserve scoped ownership, positive-credit tolerance and explicit manual
behavior. Include the reviewed automatic nonpositive-amount rejection in the
companion provider. Keep old compatibility-only archives, independent histories,
review manifests and synthetic runtime evidence. Publish only reviewed source
branches and verified local installable candidates; no release/deployment follows.

## 2026-10-30 - Keep review evidence reproducible and the browser test portable

Accept the three minor incremental CodeRabbit comments on `0e0098db`. Distinguish
historical uncommitted QA observations from committed runnable commands; retain
old source hashes as historical evidence. Use a repository-safe agent identifier
for reviewer attribution. Let Playwright discover its installed browser unless
BROWSER_EXECUTABLE_PATH explicitly overrides it. Independent QA accepts 19 option
checks and 121 actual native assertions; product source is unchanged. Do not
install a browser, change test expectations or suppress the documentation metric.

## 2026-10-30 - Preserve partial settings behavior in PR411 review fixes

Accept both verified CodeRabbit findings on `7bb1677d`. Restrict payment-sound
assets to HTTP(S). Disable the two newly bounded Payment duration controls when
inactive or inaccessible and omit only invalid inactive values, retaining valid
and nullable values and the existing paid-field filter. Do not clamp or silently
rewrite merchant settings. Preserve optional active fields, numeric range/step
validation and server-side authorization. Upstream merged-store validation may
still reject its own invalid persisted values; the merchant must correct those
explicitly. Documentation coverage is an advisory to improve useful contracts,
not evidence of runtime correctness or authority to expand the patch.

## 2026-10-30 - Publish the reviewed BTCPay 2.4.7 consumer branch

The owner approved the audited compatibility implementation and explicitly asked
for the Satflux branch on GitHub and local installable plugin archives. Retain
separate repository histories, existing SEPA work and release gates. Publish only
the scoped consumer changes and their tests/docs; no merge or deployment authority
is inferred. Correct seconds/minutes without changing wire format, preserve
recurrence integers and sparse updates, and retain server-side authorization.
Independent QA/security accepted the core source and existing ratchets stay intact.

## 2026-10-30 - Publish isolated Blink probe diagnostics

The owner authorizes pushing diagnostics to the owned Satflux repository and
plans to deploy separately. Isolate the reviewed change from SEPA implementation
and local environment repairs. Preserve API results and payment protections;
log only stage, hostname, bounded category and available HTTP status. Independent
source security/QA accepted the implementation; local tests and scoped checks
are recorded in [the runbook](BLINK_PROBE_DIAGNOSTICS.md). No merge or agent-run
deployment is implied by this publication.

## 2026-10-22 - Resolve wallet checkout review without relaxing safety gates

Consumer #399's initial Node CI fails on npm's critical dependency-audit finding
for locked DOMPurify 3.4.16; PHP and Playwright pass at `eae6f32f`. Apply the upstream
3.4.17 patch only and preserve audit thresholds. Upstream non-default-mode attack
conditions do not establish exploitable Satflux XSS. Other pre-existing dependency
advisories require separate triage, not a forced major upgrade in this fix.

Accept CodeRabbit's capped-query ambiguity and LNURL-only description findings.
Expose total matching row count/truncation without returning recovery contents or
removing the read-only timeout/cap. Correct the provider description while retaining
Unknown/null/no-payout classification and configured-Lightning checkout exclusion.
Make persisted snapshot-before/after preservation explicit in the provider test.
No settlement, funds guard, core, third-party plugin or production change follows.
Previously built package checksums remain historical; no silent artifact replacement.

## 2026-10-22 - Hide Cashu checkout with any configured Lightning wallet

The owner reports draft-character wallet detection, selectable parallel Cashu and
blocked unchanged-target re-enable. The explicit product choice is to hide Cashu
with every configured Lightning wallet, including Blink API and Boltz, even when
excluded or unavailable. Preserve already materialized Cashu prompts and recovery;
do not substitute a new quote for unverifiable/partial existing prompt material.
Exact server-side store/invoice/quote binding is mandatory. Host request-time
StoreData determines eligibility; no cross-system transaction with simultaneous
core configuration edits is claimed.

Detect only committed input on the existing wallet-change screen, with latest-value
Save/Test and stale-response guards. Preserve the creation wizard's existing
default behavior and all email/ownership restrictions. Re-enable with identical
mint/unit/payout/trust/caps is availability-only: compare fresh provider settings
under its exclusive store lease, preserve consumer grants/journals/Lightning
methods, and retain intent during read-only recovery. Combined changes keep the
fund guard; existing provider standalone fee/trust edits are not tightened.

The owner later confirms the first invoice paid directly through Lightning and is
Settled. That supports an unused parallel quote hypothesis but does not establish
the separate mint quote's funding outcome. HTTP 400 remains unknown; no automatic
cleanup, paid/unpaid override or safety-hold release is authorized. Deliver an exact
read-only diagnostic runbook before asking for additional production data.

Consumer critical-source security hash is `a56ad0da3d63fc2e0f9403e2e5af0fd2aecabadc01086bca58be49097362dcb6`;
provider source/version/docs/test hash is `f42a707e2c486e6a1f27fc70d8ad372888de1f1a9d65cbbac2ff8c8c3410f461`.
Independent source reviews find no change-specific blockers; QA and isolated
runtime checks are recorded in the contract. No new core/third-party changes,
schema/configuration, production access, publication or deployment is included.

## 2026-10-22 - Bound future wallet-attestation probe monitoring

The owner reports a monitored-invoice hold despite creating no customer invoices
on a new store. Source tracing confirms automatic real 100-sat Lightning probes
can inherit approximately one day of monitoring despite one-minute checkout
validity. Archive is a soft flag, not cancellation. This is a confirmed repository
defect; the reported production invoice and deadline remain unverified.

Choose an explicit one-minute monitoring buffer for newly created probes only,
following independent security review. Keep checkout validity, amount, LN-only
methods, merchant credentials, locks and every outstanding-payment/Cashu/journal/
confirmation/ownership guard unchanged. Do not exempt metadata-marked probes or
retroactively alter old invoices. A marker is merchant-controlled, not provenance.

Two new regressions reproduce the omission before the service fix. Independent
QA verifies exact locked dependencies and passes 24 tests/150 assertions; security
accepts the three-file digest recorded in the contract. The deadline regression
proves guard release only, not a full provider wallet replacement. Actual BOLT11
expiry and delayed settlement require isolated provider acceptance; there is no
production cancellation or immediate-unblock claim. Document safe read-only
diagnosis and rollback. No new environment variable, schema, plugin/core change,
production mutation, merge or deployment is authorized by this repair.

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

## 2026-10-19: offline security and reliability investigation

Product Owner authorized offline analysis and focused reversible repository fixes
for guest concurrency, BTCPay file upload authorization, webhook store identity,
BTCPay app-list routing/logging and CashuMelt invalid-quote retries. Preserve
passwordless guests and all existing payment operations. Satflux focused tests:
82/82, 298 assertions; CashuMelt: 147/147 and successful build. Independent
security and QA reviews are complete. Production log conclusions are limited to
provided artifacts. No production access, changes, commits, package publication
or deployment occurred. See the incident report and read-only runbook linked from
PROJECT_STATE. The next milestone is operator-provided, redacted aggregate evidence;
publication requires separately reviewed clean-base branches and exact-head CI.

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


The follow-up CI passes all 1,836 PHP tests/8,425 assertions, Pint, Node and
Playwright, then exposes three unmatched baseline ignores for defects removed by
the ticket repair. Delete exactly those three entries; preserve all other patterns,
counts, PHPStan level and unmatched-ignore enforcement. Run the full PHPStan command
without restricting paths: it passes with no errors. Independent QA accepts the
exact three-entry deletion. Verify fresh exact-head CI. No application behavior
changes are needed for this correction.


### Ready-for-review partial-create visibility correction

CodeRabbit review of `9bc086fe` identifies one minor stale-list/count path after
an event is durably created but its image upload fails. Keep the known saved event
in the local list before refresh, retain editing ID/pending image for retry and
refresh before showing the image error (loading clears notices). Cancel must reveal
the saved event and enforce its event limit even when the refresh request fails.
Add bounded regression coverage and align existing SK/DE ticket terminology.
Independent QA accepts the source and independently passes all 13 focused tests.
Full lint/typecheck/locale parity/build pass; fresh published-head CI/review precede
handoff. No production,
backend/auth or provider modification is part of this follow-up.


### Integration after PR #395 merge

The owner merged #395 into `master` at `d37153ce` and requests resolving #396's
conflict. Merge that base into the existing feature branch, preserving both the
October 19 incident decisions and October 20 ticket/auth decisions. Only their
append sections conflict; no manual application-code resolution is needed. The
combined PHPStan baseline keeps both branches' reductions. Independent integration
QA accepts the result; combined offline checks pass (128 PHP tests/507 assertions,
13 frontend cases and full PHPStan). Require fresh combined-tree CI; this updates
the PR branch and does not
merge #396, publish the provider or deploy anything.

## 2026-10-21: unified Tickets sales and persisted merchant ordering

Status: implementation authorized by the Product Owner after reviewing the public
sales prototype and confirming that existing event authoring must be preserved.

The public sales page remains in the owned Satflux Tickets plugin on the BTCPay
host, using existing URLs. Combine event information, ticket selection and contact
entry before the existing hosted payment. Render saved title, full rich description,
physical/virtual type, optional image/location/end date, UTC times, effective
currency, capacities, redirects, email templates and optional raffle bundle. Remata
is example content only; introduce no accommodation-specific authoring fields or
universal free-entry claims.

Extend existing management lists in Satflux and native BTCPay with inline ordering;
no new Satflux page, tab or competing event/type form. The plugin owns the saved
order. Use a complete permutation, safe revision compare-and-swap and event-level
membership serialization; preserve disabled/default types, prices and sales counters.
Explicit old name/price sorting remains supported. Additive migration backfills by
name and ID because historical creation order was not stored.

The public retry flow must preserve immutable checkout intent, reuse the exact
invoice and retain hosted fulfillment authority. Independent security review found
a pre-existing incorrect internal-tag-prefix lookup and a related missing-invoice-ID
settlement recovery gap, now within the bounded integration review. Do not create
an additional invoice when existing intent evidence conflicts or lookup is unknown.

Separate owned repository branches and reviews are required. Candidate plugin
version is 2.1.0, retaining the uncommitted 2.0.4 dependency. No core/third-party
plugin source changes, real-fund tests, production changes, commits/publication,
merges or deployment are implied. Strict inventory holds and zero-price fulfillment
remain outside scope. See [implementation record](TICKETS_SALES_ORDERING.md).


### Tickets 2.1.0 local packaging and Satflux branch publication

The Product Owner explicitly requested an installable Tickets 2.1.0 package and
push of the Satflux branch to GitHub. Package the accepted, unchanged Release DLL
using the exact pinned BTCPay 2.4.5 packer, preserving standalone plugin identity
and recording archive/DLL hashes. Commit and push only the scoped Satflux feature
branch. This authorizes neither plugin source publication nor merge, installation,
release publication or deployment. Exact-head CI requires a pull request under the
current workflow; existing host dependency advisories and staging gates remain.


## 2026-10-22: public sales language and time-zone presentation

The approved 2.1.0 follow-up corrects plugin presentation without editing BTCPay
core. The host's invariant culture is not an anonymous customer's language preference.
Use allowlisted EN/SK/ES/DE/CS choice or bounded weighted browser languages, and
a CSRF-protected presentation-only language POST preserving the existing session
and incomplete draft. Do not change invoice language/settings or recreate intents.

Events currently store UTC instants without a venue time-zone field. Render those
instants in the visitor's browser zone and explicitly label the zone; keep UTC when
formatting is unavailable. Do not hardcode CET or reinterpret stored dates. Venue
time-zone authoring is a separate product decision, not silently inferred from
location text or language. Candidate version is 2.1.1; no schema change is required.


## 2026-10-22: automatic header language and content-addressed assets

The Product Owner requests changing language immediately from the header rather
than submitting a separate language button. Use the five constant dictionaries in
the existing DOM; update text/allowed attributes, local dates/amounts and form
language without HTTP requests or recipient-data transport. Retain input identity,
drafts, delivery choice, nonce and frozen checkout intent. The bounded backend
presentation action remains compatible.

Unversioned URLs allowed an old 2.1.0 script to be reused against the 2.1.1 page
in offline reproduction, matching the reported failure pair. Require content-hash
asset paths, exact version validation and explicit caching while retaining old
routes; do not weaken CSP. Reproduce the real shared host layout/CSP and check
actual generated route contracts, not only an overridden layout. This does not
confirm the operator's cache origin or authorize production cache/config changes.
Stored 08:00 UTC and browser-local 09:00 CET represent the same winter instant.

## 2026-10-22: physical event venue time, virtual visitor time

Status: Product Owner authorizes implementation and confirms every existing physical
Tickets event belongs to `Europe/Bratislava`. Add explicit IANA metadata with a
metadata-only historical backfill; never rewrite UTC instants. Future physical events
must choose a venue zone in existing authoring forms; old API clients remain compatible
with a clearly labelled unknown-zone UTC fallback. Physical public/ticket output uses
venue time even for foreign visitors; virtual public output uses visitor time. Provider
2.2.0 owns the catalog and civil conversion. Reject DST gaps and ambiguous edited civil
hours rather than silently choose an offset; UTC instant API compatibility remains.
Sparse date writes preserve original precision on unrelated edits. Install provider
before dependent Satflux consumer. See TICKETS_SALES_ORDERING.md for contract/review
ownership. No production operation, merge or deployment follows from this decision.

### 2026-10-22 clarification: store default with event override

The Product Owner approves using the Satflux store's saved timezone as the initial
choice for a new physical event, retaining a per-event override. Snapshot the chosen
zone on the event; later store changes must not alter existing events. Apply defaults
only from the matching store after provider validation, before user interaction.
Do not infer a zone from IP, browser location or a missing catalog.

Source inspection of pinned BTCPay 2.4.5 shows no persisted store timezone in its
standard Store API: the unknown outbound `timeZone` extension is not mapped. Satflux's
local `Store.timezone` is authoritative. Fix settings reload to prefer it rather than
show UTC and risk overwriting the saved choice on an ordinary subsequent save. The
manual form defaults UTC while the guest provisioner explicitly stores Europe/Vienna;
these are code findings, not production observations. Do not automatically rewrite
existing store records or change provisioning defaults in this milestone. Native
BTCPay event creation keeps explicit venue selection because it cannot read Satflux's
local store preference; do not invent a core field or cross-service write bridge.

The independent review also requires a native stale-schedule guard: original UTC
baseline hints detect concurrent start/end changes before civil conversion or writes,
retain the draft and require a fresh reload. These hints do not replace authorization.

### 2026-10-22: publish reviewed Satflux follow-up

The Product Owner explicitly authorizes pushing Satflux changes to its existing owned
feature branch. Publish the reviewed timezone/default/settings consumer and its docs
as an update to PR #397; keep plugin source/package publication separate. No merge,
production change or deployment is authorized. Preserve the unrelated image edit.

### PR #397 review: classify catalog and write failures separately

Confirm and fix the bounded CodeRabbit error-classification finding within the approved
Tickets scope. Catalog failures remain safely mapped; event writes use that mapping
only for exact allowlisted event-time codes when new time fields are present. Preserve
legacy UTC-only and unrelated provider exception behavior. Keep the shared code/field
map as the sole classification authority; no prefix matching or payment/API contract
expansion. Correct the three documentation comments in the same follow-up. Existing
publication authorization applies to the reviewed fix; no merge or deployment follows.

## 2026-10-24: PR #400 review fixes preserve authoritative wallet checks

The owner explicitly requested remediation. Integrate already-merged #399 through
master `709befa2` without rewriting the feature history. Treat settings 404 as absent
configuration consistently with existing consumers, but require a separate fresh,
strictly safe Cashu response; maintained provider source returns 200 for empty
configuration, so do not claim a proven provider regression. Cache only successful
presentation invoice scans for ten seconds, scoped by store/credential/server/state;
never reuse that advisory for a mutation. Keep unresolved-write and safety checks
fresh. Repair timer scheduling and visible retry guidance while preserving
identity/account revisions and stale-result isolation. Independent security and QA
review accepted this bounded scope; main retains publication ownership.

## 2026-10-27: implement a protected, bounded API reference

The owner approved implementation on a new branch after the read-only proposal.
Use Scramble at build time, reviewed OpenAPI 3.1 artifacts and locally pinned
Scalar. Serve a private admin/support portal with server-side authorization,
explicit scope exclusions and deterministic CI generation/validation. Preserve
session/CSRF, integration bearer, one-time code and webhook signature distinctions;
generation never calls production controller endpoints. No provider repository
writes or deployment approval are inferred.

Independent review exposed two concrete documentation/privacy defects: Mailgun
aliases were narrower than the actual receiver, and a separate Scalar MCP upload
control bypassed the normal renderer transport. Model all supported aliases while
retaining signatures, and disable MCP/AI/developer upload features explicitly.
Scoped dependency patches remove new advisories without a Vue/Node upgrade;
the CSS parser 6.1.4 -> 6.1.5 patch is the sole pre-existing package version change.
Security and QA accept digest `88db19b2`; remaining baseline advisories, incomplete
API coverage and offline PHP DNS failures are recorded rather than hidden by
weakened gates. Main owns publication and the next remote-CI handoff.

## 2026-10-27: repair PR #405 with reproducible npm 10 installs

The owner authorizes remediation and push to the same branch/PR. Keep security
patches and locked versions while moving the SDK utility override to project scope
for npm 10 peer resolution; restore missing esbuild dev/optional records. Require
normal clean-install evidence with CI's exact npm, not success over existing
node_modules, a CI toolchain change, legacy-peer flags or an audit bypass.

Correct all four reviewed reference inaccuracies. Document the existing named-PAT
exception for both admin and support because `User::isSupport()` includes both;
do not change authorization to fit previous prose. Add real-PAT regression coverage.
Independent QA/security accept digest `dc86ea04`; normal npm 10/11 installs and
fresh-dependency tests/build pass. Publication is authorized; merge/deployment is
not. Retain the docstring advisory separately instead of adding boilerplate or
weakening review configuration.


## 2026-10-28 - Chronological messages with separate critical visibility

The owner requests date-based message ordering, including security alerts. Replace
security pinning with one chronological feed while retaining critical labels, the
global unread-security banner and a dedicated critical filter. Apply category and
unread filtering before continuation, with user-scoped local keysets and encrypted
user/filter/page-size-bound cursors. Bounded upstream scans may return empty
continuation pages; they must not make older matches permanently unreachable.
Recognize actual BTCPay invoicestate invoice paths and resolve only owned stores to
local UUIDs. Preserve the existing 30-day read-local-message window and every unread
local alert. Document upstream offset insertion limits instead of claiming snapshot
stability. Automatically refresh expired cursors once, isolating stale responses.

Use the current merged master in an isolated feature worktree and preserve the
dirty bootstrap checkout served at local port 8080. Verification uses synthetic browser
data and disposable PHP targets. QA/security accept source digest `5c2bd32c`; obsolete
PHPStan exceptions are removed, with no baseline or auth/security gate relaxation.
Publication, PR-linked changelog synchronization and actual runtime deployment are
separate from this local implementation milestone.


The owner authorizes pushing the usability branch. PR407 is the canonical source
for one combined reviewed changelog fragment; keep the previously accepted runtime
implementation unchanged. CI/check initiation is evidence of publication, not
evidence of approval, merge or deployment.


## 2026-10-28 - Empty filtered-feed continuation remains navigable

Do not infer another filtered page from uninspected raw rows. Reuse the bounded
peek to consume excluded trailing rows, retaining a continuation if the scan budget
prevents proving exhaustion. A later scan can legitimately end empty, so Previous
must remain available outside the message-list branch. Regression tests reproduce
both failures before the fix; independent QA/security accept four-file `949cf289`.
Preserve the 500-row budget, cursor/ownership checks, canonical changelog and all
quality ratchets. Record old green CI separately from fresh followup verification.


## 2026-10-28 - Distinguish passing PHP tests from a failed security audit

Treat run 40753609416's passing 2072 PHP tests separately from its failed Composer
audit. Follow the live October 28 advisory range and official MIME 7.4.22 security
release, not stale indexed patch-version descriptions. Target only symfony/mime
with native Composer and verify actual freshly installed version/reference, normal
mail behavior and malformed MIME-parameter rejection. Preserve audit enforcement,
all other packages and runtime constraints. Independent QA/security accept lock
SHA `8f3133e4`; require fresh exact-head PHP-job completion through audit after push.


## 2026-10-28 - Gate new account creation with Turnstile only

The owner narrows the prior abuse proposal to Turnstile, leaving existing accounts
and their address/status handling alone. Enforce the challenge on the server before
new guest or enabled legacy registration side effects. Preserve authenticated retry
and signed recovery behavior; a public recovery key is never a login proof.

Use deployment-owned exact hostnames, a fixed Siteverify endpoint and register action; do not
trust incoming Host/forwarded-IP fields for the proof. Omit remoteip from Siteverify,
retain existing rate limits, and defer proxy/origin hardening as separate work.
Incomplete enabled configuration and provider failures cannot disable the gate.
Known public test credentials must fail closed in production.

Keep confirmed enrollment local after backup, require explicit fresh-proof retries,
and treat CF code as a trusted CSP origin without claiming executed third-party
code can be unloaded. Preserve all quality baselines; the public-config contract
test gains only the additive expected object. QA/security accept `305d24d0`.
Actual activation requires verified real keys and deployed configuration; a local
implementation, a merged PR or a disabled flag does not establish protection.


Publish the authorized Turnstile implementation as PR408 with one canonical
changelog fragment. Retain the independently accepted runtime scope while adding
publication metadata. Distinguish default-disabled readiness from actual deployed
protection; runtime credentials stay server-side and activation requires verification.


## 2026-10-28 - Keep one visible registration failure

Use the accessible inline signup error as the single failure presentation. Remove
the duplicate global toast to fix E2E selection and UX while preserving 409
identity protections; do not mask it with a relaxed locator. Keep German signup
copy consistent with formal auth language. QA/security accept `dab15e0d`; unchanged
backend evidence remains valid and fresh CI must verify the new full revision.
Widget activation remains distinct from reviewed code and public-key receipt.


## 2026-10-28 - Verify Turnstile asynchronous readiness against the provider SDK

Do not combine an asynchronously loaded Turnstile script with `turnstile.ready()`; the provider rejects this with3857. Use the documented named SDK onload handshake, registered before script insertion, with bounded cleanup and stale-attempt protection. A browser script load event or an early global alone does not release the pending handshake. Include independent real-SDK initialization evidence in addition to mocks; intercept all challenge/account traffic and never create production accounts for QA. Enabled public configuration and CSP are separate evidence from successful token verification.


Accept PR409's bounded asynchronous SDK compatibility fix after independent security/QA review and actual-SDK failure/initialization/retry evidence. Preserve server proof verification, post-backup loading and callback cleanup; neither a successful SDK render nor enabled public configuration establishes a completed human challenge or valid server secret. Publish a separate immutable user-facing PR409 changelog correction and keep owner merge/deployment separate.
