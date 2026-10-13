---
name: github-review-handoff
description: "Prepare or review separate repository PRs and CodeRabbit feedback with verified CI, scoped changes and main-agent publication ownership."
---

# Github Review Handoff

## Required inputs

Authorized owned repositories, branch/base/revision, acceptance criteria, independent review reports and existing PR/comment/check state.

## Repeatable workflow

Read current PR reviews/checks without assuming historical status. Prioritize actionable CodeRabbit/security/QA findings; validate claims against code and resolve in assigned scope. Main prepares repository-specific titles/descriptions explaining final behavior, evidence and dependency order. Use structured bodies or body files; inspect exact diff and exclude user changes/secrets. Do not contact others without applicable authorization.

## Acceptance criteria

No external upstream push, self-security approval, unrelated file inclusion, unapproved merge/tag/deployment or stale check claims.

## Expected outputs

Review disposition by finding, relevant checks, final reviewer-ready PR description and outstanding owner gates.

## Relevant tests

Relevant required CI plus targeted regression for fixes; read-only Git diff/status and current GitHub review/check evidence.

## Security and permission boundaries

Apply root AGENTS.md and [the shared workflow](../../../docs/development/MULTI_AGENT_WORKFLOW.md).
Skills organize authorized work; they grant no system permission. Preserve user edits,
global settings and secrets. No external repository writes during bootstrap. No
production state or real funds. Main owns shared state/Git publication; independent
security review cannot be self-approved. Obtain applicable approval before expanding
scope or releasing/deploying; do not repeat approval already provided within scope.
