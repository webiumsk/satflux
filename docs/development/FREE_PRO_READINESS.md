# SATFLUX-003: temporary free PRO release readiness

## Scope and disposition

The Product Owner's continuation authorizes readiness preparation after the approved
Satflux implementation. This review changes documentation only. It does not authorize
provider implementation, remote publication, merge, release, deployment, production
configuration, invoice/subscription mutation or promotion activation.

**Disposition: source handoff ready; activation NO-GO.** The complete PRO purchase
ban is not established at the provider boundary. Live deployment identity, relay
enforcement, replica convergence and recovery evidence also remain unverified.
Do not represent the locally tested Satflux guards as a global provider sales pause.

Acceptance for this milestone is an independently reviewed evidence map, concrete
provider work proposal, release/reversal sequence and remaining owner gates. It is
not successful production activation. Main owns shared documentation/publication;
payments, devops, security and QA reviewers have no writable file ownership.

## Verified revisions and evidence

| Boundary | Verified evidence | Limit |
| --- | --- | --- |
| Satflux implementation | `c0d532fbcc711cb201cdb987dbcfd5d1f15dbc09`, `feat/free-pro-access` | Local source, not published/deployed |
| Approved source baseline | `7c4a6bc8731a434dc863928d616a30c823b94382` | Source/test comparison baseline |
| Current GitHub master | `7bb26f3f5ccb5c3e8a1ca29350090493d3135230`, observed 2026-10-14 | Merged bootstrap PR #393; same tree as baseline, not proof of deployment |
| Master CI | [Run 38482352651](https://github.com/webiumsk/satflux/actions/runs/38482352651), success | Master CI only; feature head has no PR/CI |
| Modern provider reference | Registry project `btcpay-server`, owned `webiumsk/btcpayserver`, clean `3ec6a9847bd06e5dad348caaec373276dc718520` | Exact deployed host and configured plans unknown |
| Owner-reported deployed version | BTCPay Server **2.4.5**, confirmed during this readiness review | Version statement does not identify the exact build/commit or installed plugins |
| Exact 2.4.5 source reference | Existing upstream host submodule, clean `5d0745cae6be5d8210459e38813f317673aa97b8`, exact local tag `v2.4.5` | Reference-only upstream checkout; no writes/publication target |
| Conditional legacy plugin | Registry project `kukks-btcpay-plugins`: clean sibling `a5bf692e7e3e4d83fda0601eff06e78662d56e7f`; compatible existing worktree `6871d24217f20c6c241f9502c776a907b20a0cb6` | Installation/use unknown; compatible worktree pins host `5d0745cae6be5d8210459e38813f317673aa97b8` |
| Hosted Evolu relay | Direct browser transport in Satflux; no relay repository established by registry | No deployed write-side quotas, aggregate budget or recovery proof |

The modern `/api/v1/plan-checkout` implementation lives in BTCPay core. The cited
`3ec6a984` belongs to the owned host, not the Kukks plugin. Changing only that plugin
would not close modern core sales. External repositories were inspected read-only;
no tools were installed, graphs rebuilt, refs fetched or production APIs contacted.
Satflux Graphify was queried first; external provider graphs were unavailable.

Read-only comparison against exact `v2.4.5` confirms the hosted service and subscriber,
plan and checkout models are byte-identical to the owned reference. Controller/DB
extension differences contain comments/import/regex and authorization/store-binding
improvements, not sales guards. The purchase-ban finding therefore survives the
2.4.5 version selection. Preserve those 2.4.5 security fixes in future work. The
owned reference does not contain the pinned object/tag; preparing an owned isolated
branch based on the verified version is future repository-authorized work.

Prior SATFLUX-002 evidence remains 1,631 PHPUnit tests / 6,891 assertions, 708 Vitest
tests, four browser tests, passing Pint/PHPStan/build/typecheck/locales and independent
source acceptance. These suites were not rerun for documentation-only readiness.
Focused browser smoke stubs subscriber details and does not test provider closure.
Devops additionally passed shell syntax checks for the four existing deployment/
backup/restore scripts. Source contains no new migrations, seed requirements,
dependency/lockfile changes or deployment-script changes in SATFLUX-002.

## Provider purchase enforcement map

Provider references below are relative to `BTCPayServer/Plugins/Subscriptions/`
at `3ec6a984`. They describe independently reachable reference-code paths, not
verified production exposure.

| Mutation path | File and line evidence | Required pause behavior |
| --- | --- | --- |
| API plan/trial/topup intent | `Controllers/GreenfieldOfferingController.cs:448,499` | Deny PRO before checkout record creation |
| Old anonymous API checkout proceed | Same file `:397,427` | Resolve persisted target; deny new work even with an old valid ID |
| Old anonymous checkout UI POST | `Controllers/UIPlanCheckoutController.cs:72,121` | Deny execution; retain existing invoice/read/completion handling at `:80-88` |
| Provider management creates checkout link | `Controllers/UIOfferingController.cs:50,81` | Gate server mutation; active-only UI choices are insufficient |
| Portal topup/pay/migrate | `Controllers/UISubscriberPortalController.cs:180-193,209-227` | Deny PRO funding/purchase and target-PRO changes using actual server identity |
| Shared checkout execution | `SubscriptionHostedService.cs:83,97,182,230,425` | Guard invoice, subscriber, trial, zero-price, prefunded and period-start mutations |
| Credit checkout | Same service `:692,706,708` | Deny checkout/invoice creation before side effects |
| Migration intent | Same service `:712,737,751` | Deny scheduled and immediate target-PRO work |
| Prefunded AutoRenew | Same service `:274,286,290,294` | No automatic credit debit or new PRO period |
| Previously scheduled target-PRO migration | Same service `:307-315` | Evaluate the stored target independently of current plan |
| Hard-migration fallback | Same service `:551-571` | Pause must survive fallback after normal active-plan predicate fails |
| Existing invoice settlement | Same service `:445,497,498` | Preserve authoritative paid-event fulfillment, balances and idempotency |
| Privileged accounting/recovery | `Controllers/GreenfieldOfferingController.cs:320,375`; `Controllers/UIOfferingController.cs:106,150` | Preserve existing permissions; automatic debit/start must still use central policy |

Checkout/portal IDs are persisted capabilities. `PlanCheckoutData.cs:141` defaults
to a one-day expiry, but creation permits custom validity; unexpired portals are
loaded in `BTCPayServer.Data/ApplicationDbContextExtensions.Subscriptions.cs:157`.
Hiding current links or clearing browser caches does not revoke old POSTs.

`PlanData.cs:69,87` in the provider data project exposes `Renewable` and
Active/Retired lifecycle concepts, not a separate sales pause. `SubscriberData.cs:133-146`
governs ordinary start/renewal, but invoice creation precedes those checks and the
hard-migration fallback can still start a target. Retirement/nonrenewability is not
a complete ban. Plan deletion cascades checkout data and violates preservation.
Path-only proxy denial cannot distinguish PRO from Enterprise IDs or stop scheduler
debits. Do not disable ordinary merchant invoices or payment processing.

If deployed, legacy Kukks subscription routes independently create payment requests:
`Plugins/BTCPayServer.Plugins.Subscriptions/SubscriptionController.cs:70-113`
(subscribe/reactivate) and `SubscriptionService.cs:179-197` (renewal). Inventory
this conditional surface; no legacy implementation is authorized by this review.

## Recommended provider implementation, requiring separate approval

Implement a central reversible sales policy in the owned BTCPay host, scoped to
the exact server-resolved store, offering and all applicable PRO plan IDs. Never
identify plans by display name, user role or Satflux effective feature access.
Confirm distinct Enterprise identities before enabling closure.

Keep new purchase intent, credit funding, automated renewal/debit and legitimate
existing-invoice fulfillment separate. Guard both record creation and execution;
cover stale IDs, trials, prefunded/zero-price paths, hard migration and scheduled
target changes. Preserve AutoRenew preferences, scheduled intentions, billing dates,
credits, refunds, historical reads and administrative recovery. No fake paid period
or blanket cancellation. Preserve Enterprise and unrelated stores/offerings.

Use the host's normal configuration providers. The proposed separate sales-pause
boolean defaults false to preserve normal host behavior; enabling pause requires
valid scoped identities and rejects missing/ambiguous or PRO/Enterprise overlap.
Use an explicit refusal type so existing caught `InvalidOperationException` paths
cannot swallow a pause decision. Do not blanket-deny `ProcessSubscriptionPayment`
or `TryStartPlan`: they also fulfill genuine previously issued paid obligations.
Policy decisions must distinguish new work from accepted settlement/recovery.

An Enterprise-to-PRO target must be denied. PRO-to-Enterprise transitions must not
be rejected merely because the source plan is PRO: determine actual funding intent
and validate the distinct allowed target. Generic Enterprise funding must never
enable a later automatic PRO debit. This contract needs provider regression tests.

Expose stable refusal semantics and suppress purchase/reminder solicitation. Ensure
every web/worker replica uses the same policy. Reopening needs a separate procedure
with no automatic catch-up credit spending or backdated periods. Before activation,
close initiators, then establish that no PRO invoice remains payable/processing;
the owner's zero-in-flight statement is not a race-free operational check. If one
exists, stop for an explicit recovery decision; never discard a paid event.

Proposed future ownership (no writes authorized now):

| Owner / work package | Exact prospective writable scope |
| --- | --- |
| Provider policy/services | New `SubscriptionSalesPolicy.cs`, new `SubscriptionSalesPausedException.cs`, `SubscriptionsPlugin.cs`, `SubscriptionHostedService.cs` under the provider plugin directory; `BTCPayServer/Configuration/DefaultConfiguration.cs` |
| Provider API/UI | The four controllers listed above, `Controllers/UISubscriptionControllerBase.cs`; `Views/UIOffering/{Offering,NewSubscriberModal}.cshtml`; `Views/UIPlanCheckout/PlanCheckout.cshtml` and `PlanCheckoutViewModel.cs`; `Views/UISubscriberPortal/{SubscriberPortal,NextAction}.cshtml` and `SubscriberPortalViewModel.cs` |
| Provider tests/docs | `BTCPayServer.Tests/SubscriptionTests.cs`, new `BTCPayServer.Tests/SubscriptionSalesPolicyTests.cs`, `docs/operators/configuration-reference.md` |
| Independent security/QA | No writable files; source and isolated evidence review |
| Main coordinator | Shared Satflux state/contract/runbook and Git handoff |

Views/controller/policy paths in that table are relative to
`BTCPayServer/Plugins/Subscriptions/` unless fully qualified. Exact host baseline,
configuration format and any additional localization files must be reviewed before
implementing. No schema/model mutation, invoice-accounting rewrite, deployment
manifest, package/version/tag, unrelated wallet/plugin or upstream write is proposed.

Provider acceptance must include direct API/UI/old-link purchases; trial, zero-price,
sufficient-credit, hard migration and portal funding; scheduled target-PRO changes;
prefunded AutoRenew; no records/invoices/debits on denial; actual Enterprise/other-store
positive controls; tenant/permission/CSRF negative controls; genuine existing settlement,
duplicate recovery/refunds; stale pages and policy convergence; pause reversal with
no surprise spending. Conditional legacy tests apply only if installed. Use synthetic
identities and an isolated provider database/regtest/stubs, never production funds.

## Operational source limits

| Area | Evidence | Required treatment |
| --- | --- | --- |
| Environment propagation | `docker-compose.standalone.yml:71-72,100-101,124-125,153-154` | Service `env_file` is `.env.standalone`; restart does not replace container environment. Recreate/reload every affected replica. CLI `--env-file` alone does not redirect service `env_file`. |
| Deployment ordering | `deploy.sh:119-220` | Stack starts before Composer/assets/migrations/config cache. Do not run this script as a readiness check or unqualified reviewed-artifact deployment. Drain workers/scheduler before replacing shared artifacts. |
| Dynamic browser state | `auth.ts:168-181`; `usePricing.ts:46-49,65-67,105-110`; `useAccountLimits.ts:12-24` | Focus refresh/30-second throttle and 60-second pricing TTL are not immediate revocation. Keep HTML/manifest and hashed assets coherent; exclude policy/profile responses from stale proxy caching. |
| False health confidence | `docker/nginx/prod.conf:118-121`; Compose `:75-80,126-131,155-160`; `deploy.sh:243-257` | Static 200, a PHP echo and any container Up do not prove application/worker/policy readiness. |
| Relay health | `SystemHealthService.php:107-118` | Missing URL can be skipped and any HTTP response accepted; this is not quota/write/recovery proof. |
| Current quotas | `EffectiveAccessPolicy.php:128-152`; `BusinessExpenseIsdocQuotaService.php:23-39,60-96` | Use actual configured PRO/beta/purchased allowances. Seeder values are source defaults; do not reseed plans for activation. No lower promotional limits are approved. |
| External costs | `evolu/config.ts:21-37,100-131`; `evolu/client.ts:8-10`; `docs/EVOLU_RELAY_USAGE_API.md:29-35,59-75` | Direct transport bypasses Laravel; the quota document is a sketch, not deployed enforcement. Verify independent byte/rate/capacity controls and aggregate spend monitoring. |
| Backup inventory | `backup.sh:256-305` | Existing dump/public/exports/env coverage omits private storage. Inventory actual DB/private artifacts/encryption keys and relay storage privately. |
| Restore proof | `restore.sh:170-243,338-360` | Archive checks are not successful restore evidence; current existing-DB import lacks clean-target/error-stop safeguards. Do not execute it blindly against production. |

Full current PRO quotas, including unlimited object-count cases, are approved.
This review invents no lower ceilings or budget amount. Independent hosted resource
enforcement, capacity and alerts must be evidenced separately. Retain browser recovery
and encryption; Laravel cannot revoke direct relay transport or prevent custom/offline
local writes. Reverb is a different service from the Evolu relay.

## Ordered release plan (not executed)

1. Identify exact deployed host/application versions, configured PRO/Enterprise
   mappings, conditional plugins, relay, Compose target, replicas, worker queues and
   scheduler leadership. Record sanitized evidence, never keys or merchant data.
2. Complete approved provider work and independent security/QA review. Verify closure
   at every initiator/worker and reconcile any outstanding invoice before activation.
3. Obtain exact release-head CI; current master CI and local counts do not substitute.
   Validate private backup inventory and an isolated restore rehearsal. Verify relay
   quota/capacity/recovery and current external-service monitoring.
4. Obtain explicit applicable publication/merge/deployment authorization before any
   remote write, serving artifact change or operational step. Prepare immutable/coherent source, dependencies, assets and config outside serving
   traffic. Use the reviewed target-specific maintenance/drain procedure. Preserve
   durable webhook intake and queued jobs; stop scheduling and finish in-flight work.
5. Deploy the reviewed artifact with promotion initially off and all three purchase
   flags false. Recreate PHP, Reverb, queue and scheduler with the actual service
   environment; prepare config/route/view caches consistently before resuming work.
   Do not clear shared locks/rate counters or flush Redis as a generic revocation step.
6. Verify authenticated policy responses and purchase denials, factual billing,
   Enterprise, manual validity, owner/member isolation, numbered completion and balances.
   Prove worker/scheduler progress with disposable jobs, not only container status.
7. After separate explicit activation approval and prerequisite acceptance, enable
   `FREE_PRO_ENABLED=true`; keep `PRO_PURCHASES_ENABLED`,
   `COMPANY_SLOT_PURCHASES_ENABLED` and `ISDOC_PACK_PURCHASES_ENABLED` false.
   Repeat all-replica environment/config propagation. There is no percentage rollout
   flag; use staging before the global promotion toggle.
8. Verify eligible verified non-guests, guest/unverified denial, unchanged Enterprise/
   admin/manual access, current limits/over-limit objects, old tabs/links and both
   Satflux/provider denials. Monitor disk, queues, failures and external consumption.

SATFLUX-002 itself needs no billing migration or seeding. Check the eventual merged
release against the actual deployed artifact rather than generalizing that statement
to unrelated outstanding changes. Never run deployment/migration/backup scripts merely
to gather readiness evidence.

## Staging acceptance matrix

These rows are **MISSING** expanded release/staging evidence, not failures of the
already accepted local source suites. None was executed against production in this
review. Provider tests must use the selected 2.4.5-compatible artifact and disposable
state; the owner-provided version is not deployment verification.

| Acceptance row | Required observable result | Status |
| --- | --- | --- |
| Exact artifact/CI/provider mapping | Reviewed feature release head passes required CI; exact host/build, historical PRO IDs and distinct Enterprise IDs recorded privately | MISSING |
| Every-replica policy cycle | OFF -> ON -> OFF gives matching version/flags on all web/queue/scheduler replicas; recreated environment/cache and worker progress proven | MISSING |
| Eligibility/privilege/isolation | Guest, unverified, verified, manual PRO, expired trial/grace, Enterprise, admin/support and company owner/member/outsider matrix respects existing boundaries | MISSING expanded staging; local regressions passed |
| Complete provider sales pause | Direct API/UI/old checkout/portal, trial, zero-price, prefunded/AutoRenew and scheduled target-PRO deny before invoice/record/debit; Enterprise/merchant payments succeed | MISSING; mechanism not implemented |
| Preservation and completion | Current quotas, over-limit objects, paid slots/credits/history retained; numbered Woo retries complete, new unnumbered work remains gated | MISSING expanded staging; local regressions passed |
| Historical accessibility | Approved local/server read/export/PDF/ISDOC/attachments/accountant/migration and backup-navigation outcomes after promo-off; owners/members/outsiders tested | MISSING; future terms unresolved |
| Webhook/recovery compatibility | Invalid/missing-secret signatures fail closed; duplicate/out-of-order genuine settlement/recovery remains idempotent across artifacts | MISSING cross-artifact staging; existing application suites passed |
| Hosted resource costs | Direct synthetic-owner relay writes, bytes, rate/connections, concurrent clients, configured limits, aggregate monitoring and recovery proven | MISSING external evidence |
| Private restore rehearsal | DB, private attachments, correct encryption key, relay storage and any affected provider DB/Data Protection restore validated in isolation | MISSING |

Independent payments and devops supplied provider/operations evidence; independent
security reviewer `/root/free_pro_readiness_security` holds activation with no new
blocking Satflux implementation finding. Independent QA reviewer
`/root/free_pro_readiness_qa` accepts the documentation handoff and also holds
activation. Both reviewed source and evidence without rerunning the application
suites; QA checked unchanged application scope and diff whitespace.

## Promotion reversal versus code rollback

Routine reversal stays on the reviewed new artifact: set `FREE_PRO_ENABLED=false`,
retain closed purchase flags, drain/recreate/reload and rebuild config consistently.
Verify factual billing, Enterprise, manual validity, balances, company permissions,
numbered completion and existing data. Do not restore an old billing snapshot,
erase records, silently cancel subscriptions or change dates/AutoRenew preferences.

**Retention is not continued product access.** On the current artifact, promotion-off
can deny legacy company/document reads, migration/accountant exports and PDF/ISDOC
when the owner has no other invoicing entitlement (`routes/api.php:394,407-409,612,644,652-656`;
`EnsurePlanAllowsBusinessInvoicing.php:38-53`). Members lose owner-sponsored access.
The local invoicing index hides company navigation (`Index.vue:118-125`). Local
encrypted bytes remain, but that does not prove all read/export/backup/restore flows
are usable. Planned promotion end requires approved notice and historical-access
terms and a tested owner/member/outsider plus local/server matrix. Any new read-only
allowance is separate implementation, preserving ownership and prohibiting new work.

**Older code does not understand the purchase flags.** A pre-policy code downgrade
removes guards at `SubscriptionController.php:65,79,624`, provider service `:41,519`
and slot/ISDOC initiators. False environment flags alone cannot keep those purchases
closed. Prefer a forward fix retaining guards. Emergency code rollback requires a
separately proven selective closure of PRO and both add-ons and a reviewed access
plan; provider closure alone does not cover Satflux add-on invoices.

## PR handoff and owner gates

Prepared Satflux PR title: `Add reversible temporary free PRO access`.

Suggested description:

> Eligible verified non-guest accounts can receive current PRO features through a
> reversible policy without changing their billing plan, subscription or role.
> Independent controls close new PRO/trial/credit and slot/ISDOC purchases at Satflux
> controllers and service initiators. Enterprise, ownership and billing fulfillment
> remain separate. UI, onboarding and five-language documentation consume effective
> access and live purchase availability.
>
> Validation: 1,631 PHPUnit / 708 Vitest / four focused browser tests; Pint, PHPStan,
> lint/typecheck/locales/build passed; independent security/QA accepted source.
> Promotion defaults off. Complete provider closure, live readiness and approved
> end-access terms remain release gates; no deployment/activation is part of the PR.

No feature PR exists as observed; no branch was pushed. Master is the verified target.
The reviewed baseline has the same tree as current master, so bootstrap files are
already present there; final publication must recheck exact diff/base/CI.

Owner gates are bounded provider implementation authorization in `webiumsk/btcpayserver`
at the selected deployable revision; intent-preserving prefunded/scheduled pause and
no catch-up spend semantics; conditional legacy work only if relevant; future notice/
historical access and paid reopening; and explicit publication/merge/deployment/
activation scope. No subscription cancellation, permanent manual grant, lower quota
or unapproved external write is inferred from continuing this readiness review.
