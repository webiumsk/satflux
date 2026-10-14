# SATFLUX-004: BTCPay 2.4.5 scoped subscription sales pause

## Authorization and repository boundary

The Product Owner approved implementation and isolated tests in owned
`webiumsk/btcpayserver` after the SATFLUX-003 readiness report. This authorization
supersedes the historical read-only provider boundary for this bounded change.
It does not authorize push, merge, release, production changes or activation.
Kukks, upstream BTCPay and all other repositories remain read-only.

Implementation uses an isolated owned-repository worktree on
`feat/satflux-pro-sales-pause`, based on exact BTCPay v2.4.5 commit
`5d0745cae6be5d8210459e38813f317673aa97b8`. The commit was imported from the existing
local upstream reference; neither source checkout nor original owned branch was
changed. The imported history is shallow at that boundary; do not infer complete
upstream ancestry or a ready PR base from it. No clone or upstream write occurred.

Final local provider implementation commit:
`734791fdd8ac6df46088c43fa0152ad5f185be3e` (23 scoped files). The provider worktree
is clean. Satflux application source remains at `c0d532fb`; its later milestone
changes are documentation only. No feature branch was pushed.

## Acceptance contract

- Default host behavior remains unchanged: the new sales pause defaults false.
- Enabling pause requires exact valid store/offering/PRO and distinct Enterprise
  identities, using normal command-line/config/environment providers.
- Reject new PRO checkout/trial/funding records and execution before side effects,
  including old links, prefunded/zero-price/hard-migration paths and scheduler work.
- Scheduled target PRO and prefunded PRO AutoRenew cannot change plan/start a period/
  debit credit while paused. Preserve stored intentions and factual current phases.
- Actual PRO credit funding cannot be disguised by a caller-provided Enterprise
  target. Legitimate PRO-to-Enterprise migration remains available; Enterprise-to-PRO
  is rejected. Unrelated stores/offerings and merchant payments remain unchanged.
- Preserve genuine existing invoice settlement/credits/recovery/refunds and duplicate
  idempotency. Do not blanket-deny shared paid-fulfillment methods.
- Keep history, balance, receipt and administrative accounting access, ownership,
  authorization and CSRF defenses intact. Do not cancel subscriptions or erase data.
- Suppress paused purchase solicitation; stale UI/API posts receive an explicit
  business refusal. Retain existing invoice continuation.
- Reopening cannot trigger automatic catch-up spending. A scoped durable resume
  barrier in existing settings is permitted; no schema or subscriber/plan migration.
  Preserve dates/preferences and require deliberate renewal/reconciliation for old
  periods, with fresh explicit accepted obligations handled normally.
- Validate configuration, request/service/scheduler mutations, positive Enterprise
  controls, phase transitions, reopening and genuine settlement on disposable state.

## Work ownership

| Specialist | Exclusive implementation ownership |
| --- | --- |
| `/root/provider_pause_core` | New central policy/refusal type, plugin/config registration, hosted service, focused policy/integration tests |
| `/root/provider_pause_ui` | Five controllers plus assigned offering/checkout/subscriber Razor views and view models |
| `/root/provider_pause_test_infra` | Temporary disposable infrastructure and ignored build artifacts only |
| Main | Operator configuration reference, shared state/contracts/runbook and Git integration |
| Independent security/QA | Review/test evidence, no implementation-source writes |

The provider graph is absent; targeted source reads are used and no graph tools are
installed or rebuilt. Exact test dependencies may be restored to a task-private
cache without changing project versions or global tools. Never use production
wallets, databases, authentication states or merchant identifiers in tests/reports.

## Completed source design and enforcement map

The bounded implementation is complete and independently accepted locally. The
policy is separate from plan lifecycle, factual billing and Satflux effective
feature access. It does not fabricate paid subscriptions or grant product roles.
References below are relative to the provider worktree at the reviewed revision.

| Boundary | Source evidence | Behavior |
| --- | --- | --- |
| Immutable host policy | `BTCPayServer/Plugins/Subscriptions/SubscriptionSalesPolicy.cs:26,46,53,75` | Default OFF; exact store/offering mapping, distinct PRO/Enterprise identities, caller-independent funding checks and startup database validation |
| Configuration and registration | `BTCPayServer/Configuration/DefaultConfiguration.cs:41,143`; `BTCPayServer/Plugins/Subscriptions/SubscriptionsPlugin.cs` | Normal CLI/file/environment inputs and singleton policy; no custom configuration loader |
| Existing obligations versus new work | `BTCPayServer/Plugins/Subscriptions/SubscriptionHostedService.cs:137,182,516,537,650` | Guard invoice-free/new work; preserve valid issued invoice continuation and real paid-event fulfillment, credits and idempotency |
| Prefunded renewals and scheduled targets | Same service `:370` | Block new automated debit/period/target-PRO changes and reminders; retain preferences, credit and natural phase transitions |
| Durable reopening safety | Same service `:43,69,82,102,114,550,564,700` | Uncached validated settings barriers, atomic monotonic cutoff, historical invoice classification and full fresh periods |
| Greenfield refusal | `BTCPayServer/Plugins/Subscriptions/Controllers/GreenfieldOfferingController.cs:419,440,520` | HTTP 403 with `subscription_sales_temporarily_paused`, before checkout/email/financial mutation |
| Checkout and portal UI | `BTCPayServer/Plugins/Subscriptions/Controllers/UIPlanCheckoutController.cs:100`; `UISubscriberPortalController.cs:102,190,242`; offering/base controllers and assigned views | Paused solicitation suppressed; stale posts refused; existing history, invoice continuation and authorized administration preserved |
| Reopening UI | `BTCPayServer/Plugins/Subscriptions/Views/UISubscriberPortal/SubscriberPortal.cshtml:458,474,509` | Held renewal notice replaces automatic charge/reminder promises; deliberate purchase is available only when sales reopen |
| API/operator documentation | `BTCPayServer/wwwroot/swagger/v1/swagger.template.subscriptions.json`; `docs/operators/configuration-reference.md` | Both checkout POSTs document refusal; five options generated from actual command-line help |

Within the configured offering, **all non-exempt plans are paused**, including
unlisted/new plan IDs. The PRO list validates known identities; it is not a bypass
allowlist. Only explicitly mapped Enterprise IDs are exempt. Other stores/offerings
retain host behavior. This conservative boundary prevents a newly created or
renamed PRO plan from silently reopening sales. Operators must inventory every
offering and conditional legacy plugin before claiming complete live closure.

PRO-to-Enterprise migration remains available when the server validates the actual
target and declared migration. A submitted Enterprise plan ID cannot disguise PRO
credit funding. An Enterprise subscriber with a stored PRO target may still fund
their real Enterprise balance; the scheduled PRO debit remains blocked.

## Operator controls and activation prerequisites

These are supported inputs, **not production values or executed instructions**:

| Configuration key | Environment variable | Required value when paused |
| --- | --- | --- |
| `subscriptionsalespaused` | `BTCPAY_SUBSCRIPTIONSALESPAUSED` | `true`; default `false` |
| `subscriptionsalesstoreid` | `BTCPAY_SUBSCRIPTIONSALESSTOREID` | Exact server-side store identity |
| `subscriptionsalesofferingid` | `BTCPAY_SUBSCRIPTIONSALESOFFERINGID` | Exact offering identity belonging to that store |
| `subscriptionsalesplanids` | `BTCPAY_SUBSCRIPTIONSALESPLANIDS` | Comma-separated distinct known PRO plan identities |
| `subscriptionsalesenterpriseplanids` | `BTCPAY_SUBSCRIPTIONSALESENTERPRISEPLANIDS` | Comma-separated distinct Enterprise exemptions in that same offering |

CLI options use the same keys prefixed with `--`. Missing, duplicate, overlapping,
unknown or wrong-store mappings reject enabled startup. Do not create fake
Enterprise plans to satisfy configuration. If live topology has no distinct
Enterprise identity, multiple affected offerings, or a legacy provider, reassess
the contract before deployment rather than weakening validation.

Every web/worker replica must run the reviewed implementation and the same immutable
policy. Record exact deployed build/plugin inventory and actual mappings privately;
owner-reported version 2.4.5 alone is insufficient. The local commit is based on
the exact tag, not the stale owned master. Publication must establish the correct
2.4.5 release branch/ancestry and conflict-free integration separately.

The Satflux promotion still defaults OFF. Provider pause also defaults OFF; merging
this source alone would not close provider sales. Satflux PRO/slot/ISDOC purchase
controls must remain independently false; provider pause does not cover add-ons.
Close initiators before checking outstanding payable/processing invoices, and keep
genuine fulfillment running. The owner's no-in-flight statement is not a race-free
operational check. No cancellation, retirement, billing reseed or paid-date rewrite
is part of this implementation.

## Reopening and rollback contract

Pause writes narrowly scoped JSONB records with the `SubscriptionSalesPause:` prefix
in the existing provider `Settings` table. No schema/model migration is introduced.
The durable record stores the exact offering/store, Enterprise exemptions and a
monotonic cutoff. A later historical invoice can add an individual subscriber
barrier, so fulfillment cannot reauthorize unexpected automatic spending or hold
unrelated newly renewed subscribers. Reads bypass settings cache and reject malformed
barrier data before new money/subscriber mutations. Replica writes cannot move the
cutoff backwards.

Turning the pause flag OFF permits deliberate new purchases while retaining old
automatic renewal holds. It does not spend accumulated credit, execute old scheduled
PRO intent or erase AutoRenew preferences. Fresh explicit purchases can reauthorize
future renewal; a held Grace purchase buys a full current period. Genuine older
issued obligations retain historical dating and receive an individual hold.
Completed duplicate delivery cannot re-hold a freshly renewed period. UI explains
the hold and does not promise an automatic charge.

Before paid reopening: approve notice/end-access terms, reconcile held and scheduled
accounts, verify reviewed artifacts on all replicas, stage the OFF transition with
balances/dates/ledger assertions, and require deliberate purchase or separately
approved reconciliation. Do not delete barriers as a generic reopening step. Back
up provider database/settings and relevant host keys with the ordinary private
recovery inventory. An older database snapshot can lose safety barriers.

**An old/stock binary ignores both the policy and resume barriers.** Binary downgrade
or mixed worker versions can reopen sales and spend credit even if environment
flags are false. Prefer a forward fix retaining these guards. Emergency downgrade
needs separately demonstrated selective provider and add-on closure, stopped/drained
initiators and workers, preserved billing/fulfillment recovery, and owner approval.
Do not equate code rollback with safe promotion reversal.

## Validation and independent reviews

| Check | Observed result |
| --- | --- |
| Exact native restore/build | PASS; unchanged package versions; Release build, zero errors |
| Policy unit tests | PASS: 20 passed, zero failed/skipped |
| Actual CLI/file/environment configuration | PASS: 3 passed, zero failed/skipped |
| Native provider lifecycle integration | PASS: 1 complete scenario, zero failed/skipped, approximately 29.9 seconds |
| Operator documentation/actual help/link check | PASS: generated five controls, reviewed delta, rerun 1 test passed |
| Swagger and diff whitespace | PASS |
| Independent security | `/root/provider_pause_security`: SOURCE ACCEPT; no unresolved blocking source finding |
| Independent QA | `/root/provider_pause_qa`: ACCEPT bounded source; selected native execution completed |

Independent QA also accepts this runbook and the updated readiness/state/roadmap/
decision documentation. The documentation/help check was executed by main and its
result inspected by QA; the 24 policy/configuration/integration cases were executed
independently by QA.

The build has three CS0618 warnings from the existing obsolete `InvoiceEntity.Payments`
setter used only to construct synthetic test invoices; there is no replacement
mutation API. No warning suppression, package downgrade or ratchet change was added.

Tests used a task-private exact dependency cache, existing SDK 10.0.400/runtime
10.0.11, disposable PostgreSQL 17.11 on tmpfs and an internal-only network, no host
ports/default network route and no chain processes. Source/SDK were mounted read-only
for independent execution. No tools/images were installed, production services
contacted or real funds used. Logs/results remain private task evidence; no tokens,
cookies or merchant data belong in the repository.
The task-specific PostgreSQL container and internal network were removed after all
tests completed; no existing project containers or persistent volumes were touched.

The integration scenario executes actual API, rendered checkout HTML and CSRF,
before-mutation refusal, Enterprise positive controls, mapping rejection, prefunded
balances/ledger/date invariance, scheduled targets, hosted-service invoice events,
Processing/Settled/duplicate/Invalid recovery, cold restart, reopening, long Grace,
malformed barrier repair/replay, fresh/historical fulfillment and PRO-to-Enterprise
migration. Invoice/payment objects are **synthetic**: this is not Bitcoin/NBXplorer
regtest settlement evidence, the full upstream suite or a provider Playwright run.
Portal/offering controls were source-reviewed; executed HTTP UI coverage exercises
checkout HTML/stale posts. SATFLUX-002 application suites were not rerun because
Satflux application source is unchanged.

Final reviewed SHA-256 fingerprints:

| File | SHA-256 |
| --- | --- |
| `SubscriptionHostedService.cs` | `c72740723fb5673bb1f52640ca3382849424c32f5cb1fb5c493482457b45498f` |
| `SubscriptionSalesPolicy.cs` | `49a60c4ace80181fd42ffb721f744a3ea863ba09631e3ce3d23a659530b1f6e3` |
| `SubscriptionSalesPolicyTests.cs` | `f2d31e8a9c5e89c9155f09f3f284aefa42d99b859c81bcb547f69f6f54ce03e5` |
| `SubscriptionSalesConfigurationTests.cs` | `a093aabe2018e9325ebd925fe6225dc21d55d3e8bcf6bc45552c6911eafeb0f1` |
| `SubscriptionSalesPauseIntegrationTests.cs` | `79ed885a687e7815eef12f9f1e036ec32c9c93778035d1b0eaecf048dc37fd7a` |

## Remaining milestone

**SATFLUX-005: coordinated staging and release handoff.** Prepare the correct separate
repository release bases/PRs and exact head CI after publication authorization;
verify deployed build/mappings/legacy inventory, every replica, end-access/reopening
terms, hosted relay costs/quotas, private backup/restore and cross-artifact staging.
The outstanding matrix and rollout remain in
[FREE_PRO_READINESS](FREE_PRO_READINESS.md).

Production activation remains **NO-GO** until that evidence and explicit applicable
merge/release/deployment/activation approval exist. No push, merge, production
record/configuration change or deployment occurred in SATFLUX-004.
