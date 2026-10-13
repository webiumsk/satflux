---
name: payments-and-entitlements
description: "Audit or implement explicitly authorized payment, subscription, entitlement, pricing, webhook or Cashu recovery changes without corrupting billing history."
---

# Payments And Entitlements

## Required inputs

Allowed audit/implementation scope, provider/consumer revisions, invoice state machine, permission boundaries and synthetic fixtures.

## Repeatable workflow

Separate actual billing, effective entitlement, authorization, admin privilege and quota/cost policy. Trace checkout/webhooks/renewal/reconciliation and retries. Preserve original mint/payout evidence and outstanding-invoice safety. Verify signatures and idempotency before settlement. For free PRO produce read-only findings and owner questions only; never call paid activation to manufacture promo access.

## Acceptance criteria

No fake subscriptions, silent cancellation, Enterprise/admin grants, real funds or unknown-state paid confirmation. Provider evidence and recovery limits are explicit.

## Expected outputs

State/contract matrix, verified findings, minimal authorized changes or audit-only recommendations, failure/recovery tests and review gates.

## Relevant tests

Relevant subscription entitlement/payment idempotency/billing invoice/plan limits/webhook secret tests, plus Cashu/LUD-21 plugin cases on disposable infrastructure.

## Security and permission boundaries

Apply root AGENTS.md and [the shared workflow](../../../docs/development/MULTI_AGENT_WORKFLOW.md).
Skills organize authorized work; they grant no system permission. Preserve user edits,
global settings and secrets. No external repository writes during bootstrap. No
production state or real funds. Main owns shared state/Git publication; independent
security review cannot be self-approved. Obtain applicable approval before expanding
scope or releasing/deploying; do not repeat approval already provided within scope.
