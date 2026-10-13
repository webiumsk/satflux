---
name: vue-development
description: "Implement authorized Vue 3/TypeScript UI/state changes while preserving existing patterns, localization, accessibility and Evolu encryption."
---

# Vue Development

## Required inputs

Approved UX/API contract, exact resources/js file ownership, locales, expected browser behavior and synthetic test setup.

## Repeatable workflow

Inspect reusable components, Pinia/router/auth conventions and theme. Preserve server authority and browser key boundaries. Coordinate designer/backend dependencies. Handle loading/error/retry states and responsive/keyboard flows. Update all five locales and feature docs together. Prefer focused existing tests over implementation-mirroring tests.

## Acceptance criteria

No client authorization shortcuts or key leaks; EN/SK/ES/DE/CS parity; lint/typecheck ratchets preserved; accessible contrast and focus.

## Expected outputs

Implemented behavior, screenshots only with synthetic data if useful, changed locales, tests and remaining review concerns.

## Relevant tests

npm run lint; npm run typecheck; npm run test:locale-parity; relevant npm test cases; npm run build. E2E only against verified isolated target.

## Security and permission boundaries

Apply root AGENTS.md and [the shared workflow](../../../docs/development/MULTI_AGENT_WORKFLOW.md).
Skills organize authorized work; they grant no system permission. Preserve user edits,
global settings and secrets. No external repository writes during bootstrap. No
production state or real funds. Main owns shared state/Git publication; independent
security review cannot be self-approved. Obtain applicable approval before expanding
scope or releasing/deploying; do not repeat approval already provided within scope.
