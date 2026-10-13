---
name: project-resume
description: "Resume a Satflux session or hand off a milestone using persistent state, authorization and repository evidence before planning."
---

# Project Resume

## Required inputs

Current request, root AGENTS.md, PROJECT_STATE, ROADMAP, DECISION_LOG, workflow, registry and Git status.

## Repeatable workflow

Read state before planning. Check cwd/status/branch/remotes without changing branches. Resolve affected project paths and confirm remotes. Identify existing authorization, user edits, conflicts and unverified deployment claims. Use Graphify before code exploration if available. Select the next bounded allowed step and real specialist delegation only when substantial.

## Acceptance criteria

State facts have sources/revisions; preserved edits and read-only repositories are explicit; proposed free PRO is not treated as implemented or authorized.

## Expected outputs

Concise resume report: objective, current checkout, affected projects, accepted scope, blockers, next step and required reviews.

## Relevant tests

Run orchestration validator when changing setup; record unavailable tooling. Do not run product tests merely to resume.

## Security and permission boundaries

Apply root AGENTS.md and [the shared workflow](../../../docs/development/MULTI_AGENT_WORKFLOW.md).
Skills organize authorized work; they grant no system permission. Preserve user edits,
global settings and secrets. No external repository writes during bootstrap. No
production state or real funds. Main owns shared state/Git publication; independent
security review cannot be self-approved. Obtain applicable approval before expanding
scope or releasing/deploying; do not repeat approval already provided within scope.
