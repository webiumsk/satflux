---
name: integration-testing
description: "Independently validate Satflux and plugin/WooCommerce contracts, regression and recovery behavior using disposable environments and reproducible evidence."
---

# Integration Testing

## Required inputs

Acceptance criteria, exact revisions/artifacts, affected contracts, known test entry points and explicitly verified disposable targets.

## Repeatable workflow

Verify environment/cached config before execution. Map unit/API/browser/provider coverage; include old/new version mismatch, denied tenant, invalid webhook, duplicate delivery, timeout, interrupted settlement/retry and recovery. Use CI stub and synthetic auth fixtures. Review reviewer independence. Report actual commands/results/skips and redact traces. Never seed default production-looking Compose targets.

## Acceptance criteria

No inferred passes, real funds or production writes; negative/recovery cases covered; independence and exact version pairing documented.

## Expected outputs

QA report, compatibility matrix, reproduction evidence, unresolved blockers and acceptance disposition for main.

## Relevant tests

Select relevant PHPUnit, Vitest, locale parity, Playwright and plugin suites; WooCommerce isolated functional checks where no suite exists. Setup-only work uses orchestration validators.

## Security and permission boundaries

Apply root AGENTS.md and [the shared workflow](../../../docs/development/MULTI_AGENT_WORKFLOW.md).
Skills organize authorized work; they grant no system permission. Preserve user edits,
global settings and secrets. No external repository writes during bootstrap. No
production state or real funds. Main owns shared state/Git publication; independent
security review cannot be self-approved. Obtain applicable approval before expanding
scope or releasing/deploying; do not repeat approval already provided within scope.
