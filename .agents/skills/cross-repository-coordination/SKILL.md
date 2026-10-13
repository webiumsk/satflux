---
name: cross-repository-coordination
description: "Plan and coordinate an initiative spanning Satflux, BTCPay plugins, WooCommerce or shared infrastructure while preserving independent repositories."
---

# Cross Repository Coordination

## Required inputs

Root state/registry, affected repositories/revisions, per-repository authorization, feature acceptance criteria and provider/consumer contracts.

## Repeatable workflow

Resolve paths/env overrides and confirm Git remotes. Keep external repositories read-only during bootstrap. Identify auth/version/state contracts and dependency graph. Assign bounded tasks with exclusive file ownership and shared files owned by main. Use real specialists in waves within runtime capacity. Reconcile independent security/QA findings, prepare separate PRs, merge/release sequence and update state.

## Acceptance criteria

No monorepo/submodules, arbitrary remote publication or unapproved sibling writes; cross-version failure handling and rollback specified.

## Expected outputs

Task/ownership table, integration contract/version matrix, dependency-aware plan, independent reviews, separate PR handoffs and persistent state update.

## Relevant tests

Run affected repository checks on verified disposable targets and cross-contract negative/recovery cases; bootstrap validates registry and discovery only.

## Security and permission boundaries

Apply root AGENTS.md and [the shared workflow](../../../docs/development/MULTI_AGENT_WORKFLOW.md).
Skills organize authorized work; they grant no system permission. Preserve user edits,
global settings and secrets. No external repository writes during bootstrap. No
production state or real funds. Main owns shared state/Git publication; independent
security review cannot be self-approved. Obtain applicable approval before expanding
scope or releasing/deploying; do not repeat approval already provided within scope.
