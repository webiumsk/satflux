# API documentation implementation

Owner authorization: 2026-10-27, implement the proposed protected API reference on
a new Satflux branch. Base: `7252d531fc67e30e9c45823d921fba3739a82bad`.
Other ecosystem repositories remain read-only. Merge and deployment are separate
owner decisions.

## Contract and acceptance

- `/admin/api-docs` is a Vue portal for admin/support accounts. Backend catalog
  and specification endpoints independently enforce Sanctum authentication, the
  existing email-verification policy and staff roles. `RequireVerifiedEmail`
  retains its existing exception for an unverified admin/support account
  (`User::isSupport()`) authenticated with a Sanctum personal access token named
  `btcpay-config-bot`; its existing
  guest handling is also unchanged. This feature does not introduce a stricter
  verification policy or grant merchant/store access.
- `GET /api/admin/api-docs/catalog` returns `documents` and `providers` arrays.
  Document entries have `id`, `title`, `url`, `description`; document IDs are
  `satflux`, `woocommerce`, `webhooks`.
- `GET /api/admin/api-docs/specs/{document}` serves reviewed static OpenAPI 3.1
  artifacts with private, no-store caching. Generation does not run in requests.
- Initial detailed coverage includes representative account/store/raffle routes,
  all six WooCommerce integration routes, connect exchange and both inbound
  signed webhooks. The route inventory records remaining exclusions and reasons;
  this first phase does not claim complete application API coverage.
- Preserve session-cookie/CSRF, scoped bearer, BTCPay raw-body HMAC and bank
  shared-secret/Mailgun timestamp-token signature distinctions,
  local UUID identifiers, tenant authorization and existing public user manuals.
- Pin and bundle Scalar locally. Disable interactive API requests, external
  proxies, AI/MCP document uploads and credential persistence. Provider references are links; browsing the
  portal must not fetch their specifications or forward Satflux credentials.
- Scramble compatibility and inferred schema quality must be tested against the
  actual dependency lock. Dynamic responses need explicit reviewed corrections.
- Isolated generation must be deterministic, with structural/schema validation
  and route coverage drift gates in CI. Use synthetic examples and no production
  configuration, external controller requests or live merchant data.
- Meaningful backend/UI regression tests, locale parity, existing style/type
  ratchets, build, independent security review and independent QA are required.

## Ownership and dependencies

| Task | Role | Writable scope | Dependencies / evidence |
| --- | --- | --- | --- |
| Generator, contracts, protected delivery | Backend | Composer files, dedicated provider/config/service/command/controller, route additions, `docs/api`, dedicated PHP tests | Resolve Scramble; deterministic export; auth/schema/coverage tests |
| API portal | Frontend | npm files, dedicated UI/service/tests, router/navigation and five locales | Catalog contract above; bundled Scalar; role/loading/retry tests |
| CI contract gate | DevOps | CI workflow and `scripts/api-docs` | Coordinate exporter command with backend; isolated regeneration/validation |
| Integration and handoff | Coordinator | This plan, runbook, README links, project state/roadmap/decisions | Reconcile artifacts and results; preserve unrelated checkout work |
| Independent review | Security / QA | Read-only source; disposable evidence outside tracked code | Final integrated diff; synthetic targets; explicit findings/disposition |

Specialists do not publish Git changes or deploy. No concurrent shared-file writes.
The isolated checkout has no Graphify graph; the coordinator queried the existing
root graph before targeted exploration. No graph rebuild or tool installation is
part of this task.

## Delivery and rollback

This change adds a private reference surface and build-time documentation tooling.
It does not require provider releases or business-data migrations. Deploy only
after owner approval and exact-head CI. Rollback pairs the previous application
revision with its dependency lock and built assets. Retain existing API behavior;
documentation is not authorization to invoke an operation.

## Validation evidence

Scramble 0.13.49 resolved as one new development package with zero existing
Composer package updates. The final exporter migrates only its checked in-memory
database and fails on remaining inference diagnostics. Fourteen operations are
documented; the runtime inventory has 449 non-HEAD `api/*` entries, with 435
explicitly deferred. Three OpenAPI documents validate and deterministic export
and negative route-drift checks pass.

The renderer is Scalar 1.60.0 and the validator is 0.23.13, compatible with the
tested Node 22.18 / Vue 3.5.40 environment. New advisories were removed with scoped
SDK/CSS dependency patches. The sole existing package version update is
`postcss-selector-parser` 6.1.4 -> 6.1.5, required when Scalar promotes that
previous development dependency into runtime. Vue and other existing versions
remain unchanged. Composer validation/audit pass with no advisories. Production
npm audit matches the baseline: 35 moderate, six high, zero low/critical entries;
no newly introduced Scalar-related advisory remains. Existing advisories need
their own maintenance scope; the established critical-severity gate is preserved.

- Focused PHP: eight tests / 170 assertions, including twelve valid signed
  Mailgun sender/body alias combinations, role gates, static delivery and drift.
- Independent security additionally exercised two tests / 55 assertions.
- PHPStan passes; assigned-file Pint and whitespace checks pass.
- Full frontend suite: 1,000 tests / 138 files pass. After the dependency/security
  follow-ups, all 17 affected portal cases and eight validator cases pass again.
- ESLint preserves 48 existing warnings; typecheck preserves 120 baselined errors;
  all five locales pass parity. No ratchet was raised.
- The existing 2,009-case PHP suite completed with six email-DNS validation
  failures. All six reproduce on clean base `7252d531` in the same network-disabled
  environment. This is not a full-green PHP claim. New focused tests pass.

Independent browser QA exercises the actual bundled renderer with synthetic
fixtures, not a production account. It covers all three documents and operation
navigation, retry after failure, account access loss, keyboard selection, mobile
width and absence of external requests or JavaScript exceptions. QA found and
closed an independent MCP upload control that bypassed the normal API-client
transport. `mcp.disabled` now disables that control; AI and developer-tool upload
surfaces are also disabled. A synthetic intercepted probe established the original
path without sending a document to an external service.

Independent QA and security accept the final 31-file functional/artifact scope at
digest `88db19b214786e511fbb1ffbc8354036c53b0ba81b3689d158e7bb7be7f8f08a`.
The matching build result is recorded in project state. Remote CI and production
deployment are separate evidence.

## PR #405 CI and review remediation

The owner authorized fixing and pushing the CI/review findings on 2026-10-27.
Published head `4c14d216` passes remote PHP (2,017 tests / 9,369 assertions), export,
style/static analysis/audit and changelog. Node/E2E fail before their test stages
because npm 10.9.9 rejects the npm 11-generated lock as incomplete. Previous local
install/build success did not establish clean-install compatibility with CI.

Acceptance for the follow-up: a normal clean `npm ci` using CI's npm 10.9.9,
unchanged security patches, no legacy-peer or weakened audit/check configuration,
regenerated and valid contracts, all four reviewed documentation corrections and
independent QA/security acceptance. Preserve existing auth/billing/provider logic.
Use existing cached npm tooling; no global configuration change is required.

The frontend specialist owns only npm package/lock files, the backend specialist
owns the PAT regression tests, and the coordinator owns documentation/schema
wording, regenerated artifacts, shared state and integration/publication.
Independent security/QA review is read-only. Publication updates the same PR;
merge and deployment remain separate.

The repaired lock passes normal pristine installs with npm 10.9.9 and npm 11.17.0.
The SDK utility override is project-level so npm 10 applies it consistently across
peer dependency contexts; the CSS override remains scoped to Scalar. No existing
locked version changes or removals are required. Twenty-seven missing development/
optional esbuild 0.28.2 records are restored. Patched utility 4.0.33 and CSS parser
6.1.5 remain installed; audit results do not add advisory packages or vulnerable nodes.

Independent QA and security accept the 34-file follow-up scope at digest
`dc86ea040cfd7f3ffdb941670de9394f7d14e43ffa174ca15648bb13f9beff1c`.
QA independently installs from an empty dependency directory and task cache.
Fresh-install validation passes all 1,000 frontend tests / 138 files, 17 focused
portal cases, eight validator cases, three specifications, build, locale parity,
lint and the unchanged typecheck ratchet. Eleven PHP cases / 230 assertions and
three additional independent PAT probes / 45 assertions pass. Export drift and
assigned-file Pint checks pass. Local Node is 22.18.0 with exact CI npm 10.9.9;
the remote CI Node patch 22.23.3 is a separate verification after publication.
The prior CodeRabbit docstring advisory is not a failed GitHub check; no coverage
threshold, review configuration or code-quality gate was weakened to suppress it.
