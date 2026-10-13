---
name: laravel-development
description: "Implement authorized Laravel/PHP services, APIs, jobs or data changes in Satflux with tenant isolation and existing backend checks."
---

# Laravel Development

## Required inputs

Approved task/contract, assigned app/routes/config/database/test files, repository revision and disposable test target.

## Repeatable workflow

Inspect existing controller/service/job patterns and middleware. Keep controllers thin and validate inputs. Enforce store/company ownership independently of plans; preserve encrypted scoped credentials. Coordinate schemas/API contracts and migration reversibility with main. Add meaningful behavior/regression coverage where required and execute relevant existing checks.

## Acceptance criteria

Assigned scope only; no unsafe production migrations; auth/webhooks/idempotency unchanged unless explicitly reviewed; baseline ratchets never raised.

## Expected outputs

Changed files, behavior rationale, integration dependencies, test evidence and review risks for main.

## Relevant tests

In verified isolated PHP environment: php artisan test with relevant filters, vendor/bin/pint --test, vendor/bin/phpstan analyse; CI remains the exact source of invocation.

## Security and permission boundaries

Apply root AGENTS.md and [the shared workflow](../../../docs/development/MULTI_AGENT_WORKFLOW.md).
Skills organize authorized work; they grant no system permission. Preserve user edits,
global settings and secrets. No external repository writes during bootstrap. No
production state or real funds. Main owns shared state/Git publication; independent
security review cannot be self-approved. Obtain applicable approval before expanding
scope or releasing/deploying; do not repeat approval already provided within scope.
