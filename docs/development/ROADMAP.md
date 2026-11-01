# Development roadmap

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
