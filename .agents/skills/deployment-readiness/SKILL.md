---
name: deployment-readiness
description: "Review coordinated release/deployment readiness, CI, backups, compatibility, recovery and rollback before Product Owner approval."
---

# Deployment Readiness

## Required inputs

Reviewed revisions/PRs, actual CI evidence, package versions/checksums, target version matrix, approved scope and backup/rollback plan.

## Repeatable workflow

Inspect scripts without executing deployment during review. Check exact BTCPay host, provider endpoint readiness and worker/migration sequence. Pair Cashu database and Data Protection backups. Assess irreversible schema/downgrade risk, health checks and restore proof. Order plugins before dependent Satflux before WooCommerce. Prepare concrete approval handoff; main alone coordinates execution.

## Acceptance criteria

No deployment, tag, release dispatch, wallet change or migrations during readiness review. No unsupported rollback promises or mixed-version Cashu workers.

## Expected outputs

Go/no-go evidence, missing prerequisites, merge/release order, backup/restore/rollback procedure and owner approval request.

## Relevant tests

Review relevant passing CI and isolated recovery/restore tests; do not rerun production deployment scripts as validation.

## Security and permission boundaries

Apply root AGENTS.md and [the shared workflow](../../../docs/development/MULTI_AGENT_WORKFLOW.md).
Skills organize authorized work; they grant no system permission. Preserve user edits,
global settings and secrets. No external repository writes during bootstrap. No
production state or real funds. Main owns shared state/Git publication; independent
security review cannot be self-approved. Obtain applicable approval before expanding
scope or releasing/deploying; do not repeat approval already provided within scope.
