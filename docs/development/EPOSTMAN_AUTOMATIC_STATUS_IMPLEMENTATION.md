# ePostman automatic status integration

Date: 2026-11-12. Baseline: d4778fb2. Status: locally accepted; unpublished and undeployed.

## Scope and acceptance

The owner explicitly authorizes integrating the verified Enterprise reads into
Satflux. Add optional per-company encrypted API-key setup, manual status refresh,
scheduled refresh for both server and local-first documents, and five-language
status display. Preserve receiving, generic providers, outbound request/UUID
recovery, owner previews/data and all existing sandbox evidence. Production,
public webhook registration, automatic invoice sending and billing are outside
this milestone. Reuse authenticated company/document routes and existing UI.

Use fixed-origin GET polling first: it works locally without exposing a public
callback and can later provide recovery alongside authenticated webhooks.

## Shared contract

- Input `efaktura_epostman_api_key`: omitted means keep; nonempty printable ASCII
  up to 4096 characters replaces the encrypted value; explicit null removes it.
  Public output is only `efaktura_epostman_api_key_set`. Reject supplied ciphertext,
  flags and internal generation metadata; strip secrets from serialization,
  validation flash, snapshots and logs. Enterprise secrets are server-only in
  both modes. Local-first sends transient input directly to its owner-authorized
  bridge and retains only the public set flag after bound server acknowledgement.
  Never persist this secret in an Evolu company/shared row or sharing migration.
  A same-view bridge failure retains the transient input; after reload the owner
  re-enters it if the new key was not saved. Nonsecret local sync markers remain.
- Keep a separate server-only status generation/fingerprint. API-key changes
  cannot change the existing send connection fingerprint. Preserve the API key
  across SAPI credential rotation/inbound toggles; clear it on effective provider
  origin or company participant-identity change. Compare normalized effective
  URLs so explicitly saving the same implicit URL does not discard it.
- Retain a minimal authenticated encrypted status binding on new attempts:
  origin, sender, receiver, document type, process and attempt UUID. The accepted
  request XML is still cleared. Existing attempts without trustworthy binding
  fail closed with a fixed binding-unavailable state and portal access; never
  invent local-first metadata or resend/backfill automatically.
- Accepted submission stays `submitted`. Preserve all existing epostman/retry
  metadata and add `response_payload.epostman.enterprise_status` containing only
  finite `status`, `mls_c3_status`, `mls_c5_status`, `checked_at`, `last_attempt_at`,
  `next_check_at` and fixed `error_code`. Include observed TIMEOUT and safe OTHER.
  These are provider observations, not business, signed-MLS or tax approval.
- Derive the Enterprise URL only from the exact approved production/test
  ePostman origin. Validate direct JSON object and receipt/client UUID, expected
  parties, process and document type before storing safe metadata. Disable
  redirects; bound response size/time; never report raw HTTP exceptions/bodies.
- Serialize fresh settings reads/merges and status GETs under company then
  canonical-attempt then matching-projection locks. Check owner, enabled module,
  independent status generation, immutable binding and projection linkage again.
  A completed disable/key/provider change fences subsequent reads/updates.
- Canonical attempts cover server and local sources. Update their matching
  projections consistently; late send/projection writes must retain newer status
  observations. Do not use current edited contact data as historical binding.
- Use a separate bounded scheduler every five minutes, independent of generic
  SAPI detail-path configuration. Durable due times/backoff, per-company quotas
  and a manual-refresh cooldown prevent monopolization and tight failure loops.
  Preserve last valid observations on auth/rate/network/schema failures. Keep
  checking UNCONFIRMED and success with unresolved tax state; stop completed
  states appropriately and allow explicit manual refresh after cooldown.
- UI supports both modes, clears server keys on the same connection changes, preserves
  local bridge retry markers, fences responses after company/document switches,
  hides secrets after save and displays fixed localized errors. A visible open
  panel periodically reloads cached status; the scheduler performs provider GETs.

Independent security reproduced a new shared-key disclosure in the initial
local-storage draft: company appSettingsJson follows the SharedOwner namespace,
whose decryption key is available to accepted members/accountants. Existing SAPI
storage is outside this change. The Enterprise field is therefore server-only,
and share-copy/local-merge paths defensively remove its secret fields. No actual
owner Enterprise key was written into Evolu; only synthetic draft tests existed.

## Ownership and verification

| Responsibility | Owner | Writable scope |
| --- | --- | --- |
| Backend settings, binding, client, sync, scheduler | Backend specialist | Relevant PHP settings/requests/models/controllers/services, one additive attempt migration, scheduled command, PHP tests |
| Setup/display/local bridge and locales | Frontend specialist | Company eFaktura form/composable, local settings/bridge helpers, invoice panel, five catalogs, focused frontend tests |
| Contract, integration, API export and documentation | Main | This note, generated API artifacts, user guides, shared project state and private actual-service check |
| Independent security and QA | Separate reviewers | Read-only product scope; synthetic temporary tests/reports only |

Required tests include write-only redaction and input rejection, unchanged send
fingerprint, key/provider/identity invalidation, local bridge and remount races,
strict fixed-host transport and metadata matching, nullable/TIMEOUT/unknown states,
both projections, stale credential/owner/projection fences, backoff and scheduler
fairness. Preserve existing receiving/custom-provider regressions and type/lint
ratchets. Run meaningful concurrent SQL tests in disposable infrastructure.
Actual provider verification, if performed by main, uses only the two retained
test receipts in a new isolated app database; owner previews are not modified.

Apply the additive migration before new code. Preserve immutable status bindings
and retry evidence across rollback; older code simply lacks status synchronization.
No push, merge or deployment follows automatically from local acceptance.

## Implemented behavior and operating bounds

The optional Enterprise field is a password input in the full eFaktura settings
form. An empty input preserves the server key; the explicit removal action sends
null. The UI does not claim that a saved key has been authenticated. A failed
local-first save retains the replacement only in component memory. After reload,
the public pending marker requires re-entry, while disabling the module or
explicitly removing the pending key remains possible. Successful disable does
not falsely complete an unsaved replacement. Invalid local tax identity cannot
prevent revocation; enabling or supplying a new key still validates identity.

`efaktura:sync-epostman-status` runs every five minutes when the global module is
enabled. Defaults select five rotating companies, at most three due attempts per
company and twenty attempts overall. Manual reads have a sixty-second cooldown;
failed reads back off exponentially from ten minutes, capped below one day. The visible invoice panel
reloads the application cache every thirty seconds. These cache GETs do not call
the provider. Completed positive delivery observations require positive C3 and
C5; the provider's non-Peppol flow has no C3 and completes on positive C5 alone.
Unresolved C5 and UNCONFIRMED keep polling. All last-known observations remain
distinct from application submission acceptance and payment status.
Reference: [provider lifecycle](https://postman.slovakodata.com/developers/stavy/).

Historical refresh uses the retained bound attempt, independently of current SAPI
credentials and edited buyer data. Generic-provider refresh and sending retain
their existing configuration and eligibility requirements. New API-key setup pins
the effective configured origin explicitly without changing the send fingerprint.
The additive attempt migration retains its evidence on rollback.

## Actual application verification

Main uses a new private SQLite database, generated application key and synthetic
company/projection rows. Its two status bindings come from the authenticated old
RCMD accepted-request evidence. This is a bounded test fixture, not an application
backfill of unbound historical submissions. The original fixture, credentials,
captures and both owner previews are mounted read-only or not mounted at all.

After independent backend/harness QA and security acceptance, offline preparation
creates two attempts and two projections with zero HTTP. Exactly two fixed TEST
GETs through the actual `EpostmanStatusSyncService` then succeed: both report
UNCONFIRMED, C3 TIMEOUT and C5 OK. One server Invoice projection and one local
CreditNote projection match their canonical attempt atomically; top-level status
remains Submitted. Immediate repeated refreshes perform no additional GET because
of the cooldown. Accepted request staging stays empty and immutable bindings stay
retained. Responses and the execution report remain encrypted in private state.

No new invoice, credit note, inbound ACK, provider mutation, production request,
public webhook registration or owner preview/database modification occurs. Actual
receiver UI arrival was confirmed earlier by the owner; this read check does not
establish cryptographic MLS or statutory production acceptance.

## Independent review findings

The initial shared-key persistence design was rejected before owner input entered
it. Server-only storage plus defensive local/share-copy filtering closes that
path. QA also reproduces and verifies corrections for two revocation cases:
invalid local DIČ and an interrupted replacement after reload. Both can disable
the bridge; an unsaved replacement still requires re-entry before re-enabling.
The suggested hidden pending-removal control is skipped after verifying that the
current button already includes the pending marker.

Main identifies a rollback/reapply defect in the first additive migration; the
backend author reproduces it:
retained columns collide when its migration record is reapplied. Independent
column/index guards correct it without deleting evidence, with old-version
writes, repeated up and missing-index repair tested. Security independently
passes sixteen actual PostgreSQL migration checks. QA also finds conservative
permanent polling for the non-Peppol terminal state with null C3; the narrow C5
completion correction is independently accepted after checking the provider
lifecycle. No recipient C3 is fabricated and unresolved C5 keeps polling.

Independent security passes nine actual PostgreSQL concurrency scenarios with
simulated HTTP, three API-boundary tests with thirty-one assertions, and twenty-six
shared-secret filtering checks. PostgreSQL cases cover credential clear/rotation,
disable, owner/origin changes, the allowed SAPI/inbound rotation control, fresh
settings merges, in-flight GET serialization and changed projection receipt.
Its disposable database/network are removed after testing. These results do not
represent actual provider network calls. Independent QA passes 117 PHP tests with
765 assertions and a separate mounted-form interrupted-replacement sequence.
Counts overlap other suites and must not be combined.

The final Enterprise suite passes twenty-two PHP tests with 193 assertions,
including both migration and non-Peppol corrections. Scoped Pint and PHPStan
pass without baseline changes. A separate main receiving/generic-provider
regression passes ninety-seven tests with 717 assertions. One inherited PHPUnit
deprecation remains. Final frontend build passes with 7,660 modules after the
revocation corrections. Typecheck retains its existing 110-error baseline with
no new errors; changed-file ESLint and five-language parity pass. Full lint
still reports the unchanged baseline `peppolEndpoint.ts` control-regex error and
forty-six warnings; no warning or type ratchet is raised. OpenAPI validation for
all three documents and isolated API-export freshness pass; route inventory
changes only the bootstrap source hash for secret-flash exclusions.

The primary historical Graphify query was available earlier in this task; no
outbound graph was available. Targeted source reads follow, without installing or
rebuilding tooling. The final full frontend suite passes 3,019 tests across 236
files in 700.01 seconds. The initial sandbox run ends SIGTERM without a result;
only the successful final retry is counted. Final TypeScript verification passes
on the frozen frontend inputs with no new baseline errors. No runtime source
changes follow independent acceptance or this final suite.
