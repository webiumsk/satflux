---
name: btcpay-plugin-development
description: "Coordinate authorized C#/.NET BTCPay plugin APIs, recovery, packaging or compatibility using the exact tested host and separate repository lifecycle."
---

# Btcpay Plugin Development

## Required inputs

Registry resolution, repository-specific write authorization, plugin/version scope, exact host pin and assigned C#/test/build files.

## Repeatable workflow

Read target instructions and resolve actual checkout. During bootstrap audit only. Check current host guard and manifest/framework declarations; define Greenfield auth and consumer contract. Preserve Cashu snapshots/retry evidence and Data Protection recovery. Validate against exact host using existing suites. Prepare package/version/checksum evidence only when authorized; main owns publication.

## Acceptance criteria

Pin verified, not inferred from sibling fork; no guard bypass, real wallets or mixed worker assumption; each repository has separate PR/version.

## Expected outputs

Provider API/version matrix, scoped implementation/test evidence, packaging readiness, release dependencies and rollback restrictions.

## Relevant tests

Existing scripts/verify-btcpay-host.sh and guard regression script; dotnet test applicable plugin suites with verified disposable PostgreSQL. Packaging script only in approved build phase.

## Security and permission boundaries

Apply root AGENTS.md and [the shared workflow](../../../docs/development/MULTI_AGENT_WORKFLOW.md).
Skills organize authorized work; they grant no system permission. Preserve user edits,
global settings and secrets. No external repository writes during bootstrap. No
production state or real funds. Main owns shared state/Git publication; independent
security review cannot be self-approved. Obtain applicable approval before expanding
scope or releasing/deploying; do not repeat approval already provided within scope.
