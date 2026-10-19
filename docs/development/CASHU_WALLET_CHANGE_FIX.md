# Cashu wallet change confirmation and unchanged saves

Scope: owner-reported regressions on unmerged Satflux PR #394, based on
`8b2bbf6136357b8427fb82051aec932c7b7d4c87`. Satflux changes only. The owner
reports the error also occurs without changing values, with two settled payments
and one expired unpaid payment visible. These are observations, not production
database or provider safety evidence. No provider writes or production inspection
are authorized.

## Findings and selected design

Pure Cashu stores deliberately have no `WalletConnection` row. The previous
`WalletChangeConfirmationGuard::requiresConfirmation` tested only connected rows,
and `CashuController::saveSettings` asserted the grant only for Lightning switches.
The Cashu component's password step therefore did not enforce the existing email
challenge for a configured Cashu wallet. Extend the central guard and reuse the
existing password/session, six-digit email, resend and short grant flow.

`CashuWalletUpdateService::update` previously checked outstanding settlements before
recognizing unchanged settings and always rewrote the provider configuration.
Under the existing store wallet lock, authorize the owner, check the grant and any
unresolved configuration journal, then compare authoritative provider settings and
payment methods. For an already configured pure Cashu store, an exact match returns
the current settings without a provider write, method deletion, local wallet change,
new journal or grant consumption. A topology switch or mismatch retains all safety
checks. Client-side equality alone is insufficient.

Selecting Cashu in the create wizard sets `wallet_type` before first configuration.
That selection alone must not gate initial setup. A connected wallet, configured
fallback or known Cashu configuration journal requires confirmation. Otherwise the
provider's complete empty settings DTO (including matching internal store ID), or an
explicit missing configuration response, permits first setup. A partial/malformed or
unknown read cannot establish that the wallet is new. Missing/unknown safety still
prohibits writes.

## Contracts and security

| Boundary | Required behavior |
| --- | --- |
| Existing request/confirm/resend API | User, purpose and local store binding; preserve expiry, attempt limits, password/session authentication and guest upgrade |
| Cashu settings API | Add `change_confirmation`; a Cashu confirmation returns a nullable secret and never reveals an unrelated Lightning credential |
| Shared mutation paths | Reassert the grant inside `wallet-update:{store}`; preserve ownership, Sanctum/CSRF, verified-email and invoice monitoring checks |
| Grant lifecycle | Capture the challenge ID before mutation; consume that ID after confirmed success, never a newer challenge created during a slow operation |
| Journal | Encrypt desired configuration plus captured grant ID; read legacy flat Cashu settings/raw Lightning credentials; retain recoverable intent if local finalization fails |
| Reconciliation | Read authoritative configuration; no blind replay or adoption of an uncertain result; do not consume an unrelated fresh grant |
| Safety response | Only boolean `true` authorizes a real change; explicit `false` returns `cashu_outstanding_payments`; missing, malformed or unavailable safety returns `cashu_safety_unavailable` |
| Browser state | Ignore responses for an old selected store; preserve typed settings when a grant expires; require deliberate resubmission after confirmation |

Read-only provider reference: maintained Webium plugins at
`df047c98b48a0f2e2247d613029b7fa7bb4b9f98`,
`CashuMeltApiController` safety action and `CashuMeltConfigService` unresolved
payment/change predicates. Its camel-case safety response matches the consumer.
Terminal payment labels alone do not exclude retained proofs, payout intent or
recoverable change. Do not clear those records, force success or automatically move
funds to dismiss an error. The actual deployed plugin build remains unverified;
owner-reported BTCPay host version 2.4.5 does not identify the plugin version.

The existing fallback configuration path does not have a durable combined journal
and readback for all primary-wallet/fallback remote writes. A fallback timeout may
leave its outcome uncertain while primary configuration succeeds. This bounded fix
adds the applicable authorization/safety checks; it does not claim cross-system
atomicity or redesign fallback recovery. Do not infer fallback settlement readiness
from a successful primary connection alone.

## UX and documentation

Reuse the existing email code component. Distinguish outstanding funds from an
unavailable safety check in both Cashu edits and Lightning switches, with
EN/SK/ES/DE/CS messages and a link from the Cashu form to settlements.
An unchanged save returns to the connected summary; it does not claim funds moved.
Update existing English/Slovak Cashu user guides; other guide locales retain the
documented canonical English fallback. All newly added application keys exist in
all five locales. No new environment variables or provider endpoints are needed.

## Acceptance and verification

Required regressions: configured pure Cashu, first setup including wizard selection
and guests, Cashu/Lightning switches, fallback destination changes, wrong/expired
code and resend, foreign store, missing/malformed safety, no-op with outstanding
assets, unresolved journal before no-op, active Lightning topology, lost response,
legacy journal, newer grant during reconciliation and local finalization failure.
Run focused and full PHPUnit, independent security/QA review, focused/full Vitest,
typecheck without baseline expansion, lint, locale parity, build, Pint and PHPStan.
The existing browser suite and exact PR head CI remain delivery checks. Synthetic
SQLite/stub environments cannot establish the state of the owner's wallet.

Validation and review results are recorded in PROJECT_STATE after completion.

Published source `e24608d1` passed all application checks in CI 38808562407
(1,656 PHP cases, 726 Vitest cases, 20 browser passes and three intentional skips).
Its Composer audit failed on an existing Symfony advisory. The separate lock-only
follow-up upgrades `symfony/http-foundation` to 7.4.21 without changing the Cashu
source or other dependencies; it needs its own installed-runtime head CI.

## Rollback and next gate

No migration, billing-record rewrite, new environment variable or provider change.
Encrypted journal readers remain compatible with legacy entries. Reverting to old
code would remove configured Cashu confirmation and would not understand new journal
envelopes: do not downgrade with unresolved attempts. Prefer a forward correction;
if rollback is necessary, first stop configuration writes, preserve the database and
application encryption key, and reconcile affected attempts on the new reader without
replaying remote writes. Restoring a pre-fix artifact reintroduces the reported
security/UX regressions and requires an explicit release decision.

Publish the reviewed Satflux fix on the existing feature branch and verify exact
head CI. Next milestone remains SATFLUX-005 Satflux review/staging and readiness.
Merge, deployment and promotion activation retain their separate approval gates.
