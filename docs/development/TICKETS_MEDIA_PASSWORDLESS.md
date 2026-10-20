# Tickets lifecycle, scoped images and passwordless recovery

## Scope and acceptance contract (2026-10-20)

The Product Owner requests fixes for ticket-type activation/deactivation/deletion,
event image uploads and obsolete password-reset emails. The owner explicitly keeps
a temporary password migration entry for older accounts without recovery credentials.
Preserve guest onboarding, recovery phrases, passkeys, tenant permissions and payments.
No production inspection, data change or deployment is part of this work.

Consumer branch: `fix/tickets-media-passwordless`, based on Satflux `5e6953cf`.
Provider changes extend the owned Tickets email feature branch; candidate version
2.0.4 includes the previously tested 2.0.3 grouped inline-QR emails. Exact host pin:
BTCPay 2.4.5, `5d0745cae6be5d8210459e38813f317673aa97b8`.
The separate incident PR #395 is not included in this consumer base; do not infer
that unrelated Files API hardening is merged or deployed.

## Confirmed source defects and resulting behavior

- `TicketService::deleteTicketType` and `toggleTicketType` omitted
  `events/{eventId}` in provider paths. Both now use the nested store/event/type
  endpoint. Uncertain toggle transport failures are not automatically replayed.
- Event image handling could use the global Files API and a privileged fallback.
  Ticket images now require an owned event, the store owner's merchant API key,
  and the existing event-scoped `/logo` endpoint. The pre-event upload endpoint
  returns 410 without making a provider request. Event JSON cannot bind arbitrary
  file IDs or trigger privileged URL lookup. Existing admin/support exceptions
  still use the merchant owner's credentials; guests remain excluded.
- The provider omitted logo fields, and Satflux constructed invalid storage URLs
  from opaque file IDs. Every provider event response now includes
  `eventLogoFileId` and the actual `eventLogoUrl` from `IFileService.GetFileUrl`.
  A missing storage URL is never fabricated. An older provider response after
  upload is an explicit compatibility failure, not reported as successful.
- JPEG, PNG, GIF and WebP images are limited to 2 MiB. Laravel validates image
  contents; the provider checks MIME, signature and size before native image
  processing. Images remain scoped to the authenticated store and event.
- Image selection and removal are staged until Save. Create saves the event first;
  an image failure retains the saved event ID and pending changes. Retrying updates
  the same event. Cancel before Save preserves an existing stored image.
- Public password-reset endpoints could still send Laravel's default notification.
  Both reset endpoints now return the same 410 `password_reset_unavailable` without
  mail, broker calls or credential changes. The model notification hook also
  suppresses reset notifications. Both historic reset URL formats redirect on the
  server to a fixed relative `/login`, without forwarding token/email to the SPA.
  Existing tokens are not deleted, but cannot be consumed through these endpoints.
  Recovery/passkey login and legacy password migration authentication remain.

The owner reports reset notices sent from and to support@satflux.io with the default
subject. That is consistent with the retained Laravel reset path, but does not
identify the caller or prove automated abuse. No production logs were inspected.
A redirect cannot erase secrets already present in an incoming URL/access log.

## Validation and independent review

Run focused Laravel ticket/ownership/reset/migration tests in a disposable container,
with no network, SQLite `:memory:`, array mail/cache/session and synthetic keys.
One broader guest-upgrade test fails offline because production validation requires
DNS for the submitted email domain; this is outside the changed path and must not
be worked around by weakening email validation.

Regression coverage includes foreign stores/guest denial, support using owner keys,
no privileged fallback, SVG/spoofed/oversized images, old-provider missing URLs,
arbitrary file binding rejection, nested lifecycle paths, uncertain toggle replay,
partial image failures/retry, cancellation, staged deletion, uniform reset responses,
old tokens, hostile Host headers and both historic reset URL formats.

The existing PasswordReset.vue TS2769 diagnostic disappears after retiring its form;
only that resolved baseline entry is removed. Do not regenerate or expand ratchets.
Five frontend locales and five backend messages carry the changed behavior.

Independent security and QA review found and closed a missing server redirect for
the old query-token reset URL and image deletion before Save. Provider tests use
synthetic file services; successful persisted upload/read/delete remains a staging
acceptance check, not a claim of live validation. Native file deletion before DB Save
has an existing partial-failure risk: a DB failure can leave a stale logo reference.
No payment or ticket fulfillment changes are included in these fixes.

## Release order and manual acceptance

1. Review and publish the owned Tickets 2.0.4 provider separately. It is additive and
   compatible with the older consumer; no schema migration is introduced here.
2. Install on an approved test host and check existing logos on list/read/edit,
   upload JPEG/PNG/GIF/WebP, reload, replace and delete; test another store's event
   denial. Confirm grouped ticket emails still contain all correct QR codes.
3. Deploy the reviewed Satflux consumer only after provider compatibility is verified.
   Test type activation/deactivation/deletion, scoped logo upload, failed upload retry,
   Cancel, reset endpoints, old reset links and legacy/recovery/passkey/guest login.
4. Roll back Satflux independently if necessary; keep the additive provider installed.
   Rolling back auth restores the old reset functionality and must be a conscious
   security decision. Restoring the old provider removes logo response compatibility.

No new environment variables, secret rotation, account deletion, billing changes,
BTCPay core edits or third-party plugin changes are required. The owner subsequently authorizes the Satflux commit/push. Provider publication,
merge and deployment remain separate owner-approved actions.

## Recorded validation evidence

- Consumer focused PHP: 44 tests, 167 assertions; final reset/migration rerun:
  9 tests, 51 assertions (overlapping coverage, not 53 distinct tests).
- Independent QA frontend: 11 ticket/auth regressions and 20 existing
  passkey/recovery/cross-tab cases passed. Locale parity passed.
- Provider Release: 24 tests passed, including 16 new event-image cases and
  8 existing ticket-email cases, against the exact pinned 2.4.5 host.
- PHPStan on six changed application files: no errors. Pint on 15 changed files:
  passed. Final frontend typecheck: 120 existing baseline diagnostics, no new ones;
  full ESLint passed with zero errors/48 existing warnings; final production build
  passed. Existing bundle size and dependency/compiler
  warnings are unchanged by this repair.
- Browser test discovery succeeds (23 tests); browser execution is pending.
  Helpers now explicitly open the migration entry and test that query flags alone
  cannot reveal it. Discovery does not count as an E2E pass.
- Security reviewer: `security_boundary_audit`; QA reviewer: `qa_tickets_auth`.
  Their read-only reviewed scope is the working-tree repair at the base revisions
  above; source references and closed findings are recorded in this document.

Consumer source/test fingerprint (relative filename, NUL, bytes, NUL, sorted;
documentation excluded): `5b0b8c410790be6ef6ef9b37f91cfe3c8557f9713c74dce3571f10752ce0f83d`.

Provider event-logo contract fingerprint (same format; controller, DTO, project,
OpenAPI schema and regression test): `7b3be045b6bae9431d0efb9a81b96d6b59c2760bcebb0e8730bb7f6c050f8bfd`.

The DLL staged locally for packaging matches the Release DLL exercised by the
24-test rerun:
`debf2f4710d8aa68b4607f8ec7315bced252b5a76cd70ad2f949a65518dfd1cf`.

Local installable artifact: Tickets `2.0.4.0`, package SHA-256
`8ec8fe755a13fa984948304f19994d2dbdfbb0e5edfe21fbe2259b61fd9d7010`. The packaged DLL matches the tested DLL above.
The package has not been published, installed or deployed.


## PR #396 PHP CI correction

At head `12bb3d2b`, complete CI reports 1,831 PHP passes and five failures, all
legacy reset expectations in `tests/Feature/AuthTest.php`. Node and Playwright pass.
The follow-up updates only those five cases and the unused notification import:
uniform 410 for malformed/known/unknown requests; no notification/mail/token issuance;
no login; invalid input cannot change credentials; a previously valid token cannot
change the hash/remember token or be consumed. Other auth assertions remain intact.
No application, plugin, production configuration or workflow change is included.

`AuthTest` and `PasswordResetLinkTest` pass together offline: 25 tests, 118 assertions;
changed-file Pint passes. Independent QA accepts the delta; require fresh exact-head
CI before merging. The original source fingerprint above remains historical reviewed
implementation evidence; this additional test file has SHA-256 `b08820d18391dd04cd6dc3aa5138238e2cc8635c6b447f577edb354a16880b73`.
