---
name: architecture-review
description: "Review a Satflux design, cross-service contract or architectural change before implementation across Laravel, Vue, BTCPay or WooCommerce."
---

# Architecture Review

## Required inputs

Problem/acceptance criteria, current revisions, affected registry projects, existing architecture and threat boundaries.

## Repeatable workflow

Trace actual call/data paths and source evidence. Compare minimal alternatives against existing conventions. Define auth, version, idempotency, failure/recovery and ownership contracts. Reconcile backend/frontend/provider dependencies. Request independent security and QA findings through main before approving implementation scope.

## Acceptance criteria

No speculative historical decisions; compatibility and rollback constraints are explicit; security authority remains separate from entitlements.

## Expected outputs

Evidence-based design note, alternatives, contract matrix, bounded implementation plan, owner questions and validation plan.

## Relevant tests

Identify affected PHPUnit/Vitest/Playwright/plugin suites and negative cross-version scenarios; review existing test isolation.

## Security and permission boundaries

Apply root AGENTS.md and [the shared workflow](../../../docs/development/MULTI_AGENT_WORKFLOW.md).
Skills organize authorized work; they grant no system permission. Preserve user edits,
global settings and secrets. No external repository writes during bootstrap. No
production state or real funds. Main owns shared state/Git publication; independent
security review cannot be self-approved. Obtain applicable approval before expanding
scope or releasing/deploying; do not repeat approval already provided within scope.
