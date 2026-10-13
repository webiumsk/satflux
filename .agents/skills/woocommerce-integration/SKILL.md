---
name: woocommerce-integration
description: "Audit or implement authorized Satflux WooCommerce connection, invoicing, HPOS/Blocks, order reconciliation or release compatibility."
---

# Woocommerce Integration

## Required inputs

Resolved WooCommerce checkout, target instructions/header version, repository authorization, API contracts and isolated WP/Woo versions.

## Repeatable workflow

Inspect actual entry header rather than stale README. Trace one-time exchange, scoped tokens, signed settled webhooks, order idempotency and company inbox binding. Treat cached invoicing_enabled as UX state only. Cover HPOS/Blocks and update lifecycle. Preserve historical aliases. External bootstrap scope is read-only; main coordinates consumer/provider PRs.

## Acceptance criteria

No unsupported claim of automated suite; no stale company authority or client entitlement escalation; no release tag/SVN publication by specialist.

## Expected outputs

Compatibility findings, targeted authorized changes, isolated functional test matrix, version evidence and release handoff.

## Relevant tests

PHP lint changed files and isolated connect/order/webhook/inbox/idempotency/HPOS/Blocks tests. Existing release build is packaging, not functional test proof.

## Security and permission boundaries

Apply root AGENTS.md and [the shared workflow](../../../docs/development/MULTI_AGENT_WORKFLOW.md).
Skills organize authorized work; they grant no system permission. Preserve user edits,
global settings and secrets. No external repository writes during bootstrap. No
production state or real funds. Main owns shared state/Git publication; independent
security review cannot be self-approved. Obtain applicable approval before expanding
scope or releasing/deploying; do not repeat approval already provided within scope.
