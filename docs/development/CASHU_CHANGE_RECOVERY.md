# Cashu change recovery across Satflux and CashuMelt

## Authorization and baseline

On 2026-10-16 the Product Owner explicitly authorizes implementation in Satflux and
the owned CashuMelt plugin. This supersedes the earlier blanket provider restriction
only for this owned plugin. BTCPay core and third-party plugins remain read-only.
No production access, fund operations, merge, release publication or deployment is
authorized. Preserve the existing root checkout's unrelated owner edits.

Satflux starts at `c652fdbd299ded7c42165ea894cafa72732c743f`, independently accepted
with green CI 38819190804: 1,690 PHP tests/7,188 assertions, 748 Vitest cases,
20 browser passes/three intentional skips, static checks, builds and audits.
Owned Webium plugins start at current main
`79de74f4fd9f770539dd30ef29b7b8e6abdfe7b0`, whose tree matches inspected
`df047c98`. CashuMelt starts at 1.4.0.3; the candidate version is 1.4.0.4.
Use separate repository branches and reviews. The declared exact BTCPay 2.4.5 host
pin stays unchanged and its tracked source must remain clean.

The new owner evidence identifies unresolved change rather than payment rows.
Existing native recovery already exports eligible change and verifies mint-confirmed
consumption. Reuse it; do not lower the automatic sweep threshold, discard small
balances, clear records, fabricate settlement or bypass wallet-change refusal.

## Provider contract

Add a separate Greenfield API-key-only controller, retaining store-scoped
`CanModifyStoreSettings` authorization. The exact host's `Greenfield` constant also
includes Basic authentication; use `GreenfieldAPIKeys` for these new routes.
No new cookie-authenticated or Basic-authenticated mutation surface.
Base: `/api/v1/stores/{storeId}/plugins/cashumelt/change`.

- `GET /`: schema `version: 1`, `pendingSat`, `sweptSat`, `minimumSweepSat` as
  canonical nonnegative decimal strings (signed 64-bit bound); nonnegative integer
  `unresolvedRecords`, `availableRecords`, `exportedRecords`, `sweepingRecords`,
  `manualReviewRecords`, `unsupportedUnitRecords`, `otherUnresolvedRecords`.
  Pending/swept amounts and four state counts are sat-only, matching existing native
  totals; unsupported unit and other unresolved counts are explicit. No mint URLs,
  provider/store/payment identifiers, proofs, secrets or recovery intents.
- `POST /export`: require JSON with literal `confirmed: true`, reuse
  `ExportChangeAsync`, return `{tokens: string[]}`. Eligible exports retain the hold.
  Empty results are explicit; no success claim that a wallet can now change.
- `POST /verify`: same strict JSON confirmation, reuse
  `ConfirmChangeRecoveredAsync`, return `{verified: true}` only on success. This
  verifies exported change, not all store obligations; Satflux refreshes safety
  separately and still performs fresh safety checks on real wallet saves.

All responses use private/no-store cache headers. Errors use fixed safe codes and
messages, excluding caught exception details and raw mint replies. Preserve existing
provider advisory locks and recovery evidence. No migrations or new fund pathways.

## Satflux contract

Use existing verified, Sanctum/CSRF-protected, store-ownership routes and the store
owner's merchant key. Add a dedicated change-recovery controller/service. Export,
verify and challenge routes additionally require the actual non-guest verified
owner; the admin/support exception cannot release another owner's bearer assets.

Base: `/api/stores/{localUuid}/cashu/change`.

- `GET /`: envelope `{data: summary}`. Summary uses snake_case provider fields,
  `status: available|unsupported|unavailable`, and `can_recover`. Missing endpoint
  means unsupported; malformed/unavailable evidence is explicit with null fields,
  never a fabricated zero. Strictly validate schema, bounded counts and amount
  strings. Do not hide an otherwise valid payment list when this separate read fails.
- `POST /confirmation/request`: `{action: export|verify, password?: string}`.
  Existing sensitive-action reauthentication applies at issue time. Issue a
  recovery-specific six-digit email challenge, payload bound to local store and
  action. Return `{data:{challenge: EmailChallengeSummary}}`.
- `POST /confirmation/resend`: `{action}` with the same binding and existing
  cooldown/attempt/send limits; return the same challenge envelope.
- `POST /export`: `{code: six digits, confirmed: true}`. Under the shared
  `wallet-update:{localUuid}` lock, verify the recovery challenge, assert exact
  owner/store/action, atomically consume that exact authorization BEFORE provider
  dispatch, and perform one provider request. Return a fixed-name JSON attachment
  containing the token array, with private/no-store/no-cache/nosniff headers.
- `POST /verify`: same code/confirmation flow, bound to verify. Return
  `{data:{verified:true}}`; the UI refreshes change summary and wallet safety.

There is no reusable wallet-change grant or first-setup exception for asset release.
An uncertain outcome consumes authorization; a fresh challenge permits a later retry.
Never require safe-to-change status before recovery. Add rate limits and metadata-only
auditing. Extend source-level BTCPay client privacy to these exact change endpoints
before exposing exports, and disable automatic retries for their POSTs. Do not log
request codes, passwords, exported tokens, provider bodies or transport error details.

## UI and lifecycle

The Cashu payment page gains a separate recovery panel with amount, state, sweep
threshold explanation and explicit unsupported/unavailable states. Export/verify
each use the existing six-digit input with a fresh action-specific challenge and
password only when existing reauthentication requires it. Explain import into the
owner's wallet, export retaining the hold, and verification needing consumed tokens.
Downloads use transient Blob URLs, never token previews, clipboard, browser storage,
telemetry or persisted application state. Revoke URLs after use and on cleanup.
Cancel/ignore stale responses on store change/unmount, including A-B-A navigation.
Keep existing recovery actions and safety summary; refresh both after operations.
Update EN/SK/ES/DE/CS UI parity and user guides.

## Ownership and gates

Main owns this contract, shared state, integration choices and Git publication.
Plugin specialist owns CashuMelt production/tests/docs/version only. Backend owns
Satflux new controller/service/routes, narrow BTCPay client privacy, challenge purpose
and notification, and PHP regressions. Frontend owns API types/methods, recovery panel,
payment-page integration, five locales and component tests. No overlapping writes.
All authors accommodate others' edits and never publish independently.

Independent security design review accepts the approach subject to owner-only
authorization, action binding, pre-dispatch single use, no POST retry, source-level
privacy, Greenfield-only provider mutations and final frozen-source review. Independent
QA must validate actual behavior, isolation and negative cases. Test only disposable
SQLite/PostgreSQL and stub mints with synthetic proofs; never real wallets or tokens.

Required coverage: small change below sweep threshold, sat/unsupported units and
unknown states, summaries without secrets, export retaining safety, repeat export,
unspent/pending/unknown proofs and uncertain sweeps, remaining holds after verification,
cross-store/API-key/guest/admin denial, strict confirmation/CSRF, action-bound codes,
expiry/attempts/concurrency, one-shot dispatch on unknown failures, secret-marker
absence in every log/error path, older-plugin fallback, stale UI, five locales and
existing wallet/no-op protections. Preserve all static ratchets.

Delivery order is CashuMelt 1.4.0.4 before the new Satflux recovery controls become
available. Older plugins remain supported with an explicit unavailable capability.
Do not downgrade while recovery operations are active; both old native and new API
flows share the same persisted recovery states and locks. No schema downgrade is
needed. Prepare reviewed commits/test evidence in each repository before any
publication or deployment gate; never modify core or third-party source to build.

## Reviewed implementation evidence

CashuMelt production manifest (six files, sorted path/NUL/contents/NUL):
`00769c84247baed37339885c0fc3da6aa65fb021c79cb2af7f5eecd43a80d950`.
Independent security and QA accept it. Both the implementer and independent QA pass
129 CashuMelt tests with zero skips on disposable PostgreSQL and synthetic mint
responses. ASP.NET authentication/policy pipeline tests use synthetic handlers;
the pinned host's actual store-permission handlers were independently source-traced,
not claimed as live-host integration tests. Host guard and guard-regression checks
pass, with clean pinned core and no changes to other plugins.

Plugin source is published at `b86e619380fbbbf1427a3e47124d30b369b78d94` in
[Webium draft PR #37](https://github.com/webiumsk/BTCPayServerPluginsWebium/pull/37).
Exact-head [CI 38824816578](https://github.com/webiumsk/BTCPayServerPluginsWebium/actions/runs/38824816578)
passes, including the exact host, maintained-plugin build and regression suites.
Independent QA confirms nine successful builds and 583 passing maintained-plugin
tests (including 129 CashuMelt cases), zero failures/skips, open draft PR and exact
matching head. The declared >=2.4.5 dependency does not imply testing later hosts.
No package release, merge or deployment is performed.

An existing cached SDK/packer builds a local 1.4.0.4 installable package without
restoring dependencies or rebuilding/changing core. Independent QA verifies version,
host dependency, absence of core/other-plugin assemblies, and byte identity between
the packaged DLL and independently tested DLL. Package SHA-256:
`3879260d2d377248135c0d40a915371b818b544dc10870a829a667d614edfb17`.
Tested/packaged DLL SHA-256:
`3d79450a9dfdd26bc991e81a198d1a457b1297493eee6ce34ab2bfa262fa5b6c`.
The package is a private review artifact, not a published release or installed plugin.

Satflux production manifest (14 files, same algorithm):
`e1321b468da2b3c818d1356ff96f3ba6f57f76b44aa7b13371dec64533932020`.
Independent security accepts it. Final focused backend tests pass 47 cases/412
assertions, including current owner/inbox/eligibility races; earlier combined Cashu
regressions pass 140 cases/856 assertions. Frontend focus passes 53 cases; the full
suite passes 124 files/778 cases with four workers. Full independent PHP and final
static/build checks pass as recorded below; fresh exact-head CI remains required
after Satflux publication. No baseline expansion, live provider calls, real fund
operation or deployment.
After test-only Vue wrapper typing corrections, the affected 23-case panel suite
passes again. QA confirms assertions are preserved. Actual typecheck passes with
121 unchanged baseline diagnostics. No application source or baseline changed.
Independent full PHP subsequently passes 1,737 cases/7,600 assertions across 242
classes. This local run uses unchanged vendor HttpFoundation 7.4.14; the existing
lock remains 7.4.21 and must be freshly installed in exact-head CI. Lint passes
with zero errors/48 existing warnings, and five-locale parity passes. Independent
full Pint passes 1,041 files and full PHPStan reports no errors. The frontend
production build passes with the existing chunk warning; independent QA also passes
the final two new frontend test files (30 cases). Independent security and QA accept
the same frozen production manifest with no blockers.

Evidence limits: no live wallets, real-host plugin installation, or wire-level
cross-application recovery E2E was run. Plugin service/ASP.NET pipeline tests, Satflux
HTTP fakes and component tests cover their boundaries, with schema and native
permission source reviewed independently. The final CI must repeat all application
checks and existing browser smoke on the exact published consumer head. Manual
staging acceptance remains a separate operator/owner step.
