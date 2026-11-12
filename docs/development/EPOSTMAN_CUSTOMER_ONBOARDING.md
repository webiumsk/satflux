# ePostman customer onboarding

## Approved scope - 2026-11-12

The owner accepts guided per-company receiving setup following the successful
manual localhost test. Reuse the existing SAPI credentials, authentication,
bridge synchronization and encrypted original custody. No provider-side account
creation, delegated OAuth, sending, billing or production activation is included.

Acceptance criteria:

- A built-in ePostman choice works without an administrator preset. Production
  and sandbox have explicit names and fixed origins; custom providers remain usable.
- Provider activation guidance links to official instructions and the appropriate
  portal. Explain Settings > SAPI clients, a dedicated Satflux client, copying
  Client ID and Client Secret, and the distinction from REST API keys.
- Derive the participant from existing company identity. Missing identity is
  actionable; selecting a provider never claims registration or legal readiness.
- Changing provider/environment invalidates tests and cannot reuse another
  connection's credentials. Preserve existing stored secrets on unchanged accounts.
- Clearly distinguish unsaved setup, failed checks, saved receiving configuration,
  no poll yet, successful empty poll, received documents and failed polling.
  A credential check alone never claims end-to-end receiving readiness.
- Explain saving, background receiving and Expenses > Incoming e-invoices.
  Keep manual polling and original custody behavior intact.
- All five locales and user guides agree. Focused regressions, lint, typecheck
  ratchet, locale parity, build and independent QA/security validate the result.
- Preserve the owner's current localhost account/data and all unrelated work.

## Ownership and integration contract

| Task | Owner | Writable scope | Dependencies / evidence |
| --- | --- | --- | --- |
| Guided settings | Frontend specialist | CompanyEfakturaSettingsForm.vue, its wizard test, five locale files | Existing API unchanged; focused regressions and lint |
| Customer guide | Documentation specialist | docs/user/{en,sk,es,de,cs}/efaktura-slovakia.md | Same behavior and official provider documentation |
| Integration and state | Main coordinator | This runbook and project state documents | Author checks, independent review, local preview |
| Independent QA | QA specialist | Private isolated review artifacts only | Exact final settings/locales/docs; no owner account mutation |
| Independent security | Security specialist | Read-only product review; private report only | Credentials, host switches, stale state, truthful status |

The feature worktree has no Graphify graph/tool integration. Use targeted reads;
do not install or rebuild a graph for this task. The existing localhost preview
serves this worktree; no reset or re-seed is allowed while the owner is using it.

## Provider references checked 2026-11-12

- https://postman.slovakodata.com/help/zaciname/onboarding/
- https://postman.slovakodata.com/help/portal/nastavenia/
- https://postman.slovakodata.com/developers/autentifikacia/

Public authentication describes client_credentials. A delegated customer consent
or partner onboarding contract remains unconfirmed. No one-click login is promised.

## Review refinements

Independent QA/security identified that transient bridge-error state disappeared
after reopening settings. The bounded correction records a local-only pending
sync marker in the company's existing encrypted settings before synchronization,
and clears it only after confirmed bridge synchronization and successful local
metadata update. Pending/interrupted synchronization must remain actionable after
remount, with manual polling blocked until the settings are saved successfully.
This changes no provider contract, backend schema or original custody.

Legacy server mode intentionally clears its saved credential-test timestamp on a
changed connection. Its saved form can request a second explicit test. User guides
explain this; no client-authored server stamp or automatic provider test bypasses
that existing boundary. Local-first persists a successful test with the full
matching settings rather than stamping the previously saved connection.

## Accepted implementation and verification

Built-in fixed production/test choices, provider activation links, the SAPI-client
guide, identity guidance, saved/unsaved/poll states and the Expenses link are
implemented. The five localized guides describe the same flow. Unchanged accounts
keep their secret; environment and manual endpoint changes discard entered
credentials and invalidate prior evidence. No automatic provider request was
added during editing or saving.

The pending marker is `efaktura_inbound_bridge_sync_pending`. A failed or interrupted
disable also leaves an alert visible outside the enabled form, so a failed server
update cannot disappear when settings are reopened with the local feature off.

Independent QA accepts the exact twelve-file scope: component SHA256
`41fd1aa022c349fefe9ccfd2911dfe78b266b6959de9d1e45b4c7d078f0faeef`,
wizard test SHA256
`684b444cba9ef1c4328e1cd866df7bd3da5ea9e8844e35ac39707e1db31ef774`.
All 76 tests in six wizard/inbox suites pass. The final test-only ES compatibility
cleanup was independently repeated with 35 passing wizard tests. Two additional
independent tests verify remount recovery and the actual settings serializer's
marker/secret roundtrip using a simulated Evolu interface.

Actual Chrome against an isolated synthetic component harness passes 24 checks,
five locales at 390px, visible keyboard focus, and has zero page errors or external
requests. It loads the exact Vue component, real i18n/CSS/settings utilities; API
and external Evolu boundaries are simulated. This is UI evidence, separate from
the earlier actual sandbox transport and original-custody milestones.

Independent security accepts manifest SHA256
`13d17b52012409ab09e36583e22e24981e97ca1b34b26c9d72a4c3cebb92646c`;
main verifies all twelve source hashes still match it. The reviewer independently
passes 35 wizard and six synthetic security tests, reproducing the failed-remount
regression before the correction. No unresolved blocking QA/security findings.

Scoped ESLint has zero warnings. Full lint has 46 warnings within the unchanged
52-warning ratchet. Five-locale parity passes with four inherited informational
Slovak-only keys. Widget provenance/output check and whitespace checks pass.
Final typecheck passes with 110 inherited errors tolerated and no new errors;
the baseline is unchanged. A fresh isolated-output production build after the
runtime/locale freeze passes with 7,636 transformed modules (3m10s), retaining
the existing dependency-annotation, mixed-import and large-chunk warnings.
No build output replaces the running preview's assets.
The existing localhost preview serves the final component successfully (HTTP200),
and its login remains available. No owner login, account reset, database change,
provider request, Git publication or deployment was performed for onboarding.

Private QA/security reports and manifests are retained under
`storage/app/private/reviews/epostman-onboarding` in the primary checkout.
Only the separate QA harness was stopped; the owner's preview remains running.
Its scheduler remains absent: use the existing manual download action there.
Production activation, provider archive/export/contract and delegated customer
onboarding remain separate outstanding work.
