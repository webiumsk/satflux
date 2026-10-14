# Development roadmap

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

## Next: SATFLUX-003 release and activation readiness (not authorized)

1. Verify the exact deployed BTCPay provider and complete PRO-only closure for direct
   checkout, funding portals and prefunded AutoRenew without cancelling subscriptions.
   Provider repository or operational changes need specific authorization.
2. Validate hosted-relay cost enforcement, current quota configuration, all replicas,
   worker/config-cache propagation, supported-client behavior and reversal.
3. Decide notice/data-access terms after promotion and the paid reopening procedure.
4. Prepare the scoped Satflux PR and reviewed rollout/rollback evidence, then obtain
   explicit applicable merge, deployment and activation approval.

Existing payment integrations and deferred NWC work are not expanded by this roadmap.
