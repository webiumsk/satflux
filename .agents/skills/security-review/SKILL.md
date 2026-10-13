---
name: security-review
description: "Perform independent security/privacy review of Satflux auth, isolation, key management, payments, webhooks, entitlements or external integrations."
---

# Security Review

## Required inputs

Exact reviewed revision/diff or bounded working-tree files, implementation author, acceptance criteria and trust-boundary inventory.

## Repeatable workflow

Verify reviewer independence. Trace entry points through authorization and tenant/company binding. Check Sanctum/CSRF, audited ownership exceptions, secret encryption, signed webhook/replay handling, key/phrase exposure, local-first privacy and recovery. Consider entitlement/admin escalation and external callbacks/SSRF. Reproduce only using synthetic isolated fixtures. Return prioritized evidence and mitigation to main.

## Acceptance criteria

No self-approval or writes; unresolved blocking findings remain open; missing signatures/secrets and unknown settlement fail closed.

## Expected outputs

Reviewer identity, revision/scope, severity, file/line evidence, attack/reproduction, impact, mitigation, regression tests and explicit disposition.

## Relevant tests

Identify/run assigned non-mutating checks; stateful security tests require verified disposable environment. Never use real wallet funds.

## Security and permission boundaries

Apply root AGENTS.md and [the shared workflow](../../../docs/development/MULTI_AGENT_WORKFLOW.md).
Skills organize authorized work; they grant no system permission. Preserve user edits,
global settings and secrets. No external repository writes during bootstrap. No
production state or real funds. Main owns shared state/Git publication; independent
security review cannot be self-approved. Obtain applicable approval before expanding
scope or releasing/deploying; do not repeat approval already provided within scope.
