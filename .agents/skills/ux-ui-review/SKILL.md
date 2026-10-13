---
name: ux-ui-review
description: "Review Satflux UX/UI, onboarding, responsive layouts, accessibility or promotion copy using existing application patterns before redesign."
---

# Ux Ui Review

## Required inputs

User goal, affected pages/components, current theme/locale patterns and synthetic flows/screenshots.

## Repeatable workflow

Study current navigation, components and design tokens first. Map normal/loading/error/recovery flows. Review mobile, keyboard, focus, labels, contrast and translations. Explain promotion/billing distinctions using product language. Propose minimal consistent changes with concrete acceptance criteria; coordinate frontend ownership via main.

## Acceptance criteria

Existing patterns reviewed; clear actionable recovery; no implementation jargon in product UX; no personal data in visuals.

## Expected outputs

Prioritized usability/accessibility findings, proposed flows/copy, component references and responsive acceptance checks.

## Relevant tests

Review contrast and keyboard traversal; relevant Playwright flows and locale parity when authorized UI implementation follows.

## Security and permission boundaries

Apply root AGENTS.md and [the shared workflow](../../../docs/development/MULTI_AGENT_WORKFLOW.md).
Skills organize authorized work; they grant no system permission. Preserve user edits,
global settings and secrets. No external repository writes during bootstrap. No
production state or real funds. Main owns shared state/Git publication; independent
security review cannot be self-approved. Obtain applicable approval before expanding
scope or releasing/deploying; do not repeat approval already provided within scope.
