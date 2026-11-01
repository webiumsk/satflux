# Proposed private bank MIME capture - 2026-11-01

Status: PROPOSED. Design review only; implementation, activation, deployment and
capture of real bank content require explicit owner approval for this new
retention scope. Existing bank verification remains unchanged.

## Evidence and problem

The owner reports the diagnostic revision deployed. A newer Store audit at
11:44:51 UTC reports `invalid_signature`. At reviewed source `037a6fa7`, this means
no signature met the complete eligible-bank-proof predicate. It includes both
cryptographic failures and valid signatures missing required signed headers,
alignment or time constraints. Independent security probes demonstrate the latter
with 18 synthetic assertions; the actual message's failure is not established.

Only Mailgun logs are available. Handled review jobs do not retain original MIME.
Official Mailgun documentation ties message storage to account/domain retention;
the current Free account's availability is unverified. Another generic reason
patch cannot restore these bytes. Do not loosen bank trust or require another
real transfer to obtain evidence.

## Bounded proposed contract

1. Disabled by default. An operator must explicitly arm exactly one existing
   local Store UUID for a new random activation generation with an absolute UTC
   deadline, at most two hours after activation. Arming creates one expiring
   authenticated-encrypted ARMED slot in the existing private Redis cache. It
   cannot replace any existing unexpired slot or accept/reuse a caller-selected
   generation. No wildcard, Company capture, request-controlled owner or public API.
2. Capture only the first eligible rejection decision processed after arming:
   a Store message reaching `invalid_signature` for that owner during the window.
   This defines processing order, not SMTP arrival order; an already queued
   message can be eligible. Retain the current rejected/
   review outcome. An authenticated Mailgun delivery is not proof of bank origin;
   all captured message bytes remain untrusted diagnostic input.
3. Atomically transition only that existing, matching, unexpired ARMED slot to
   CAPTURED, preserving its exact absolute expiry. The handler must never create
   or re-arm a missing/evicted/expired slot. Concurrent losers cannot overwrite;
   disappearance fails closed for capture. Only a new explicit operator activation
   can create a new generation. Store the original MIME, envelope recipient,
   selected owner, generation, deadline, capture time, schema version and bounded
   summary together under authenticated encryption. The existing 256KiB raw limit
   remains; enforce a 1MiB hard cap on the complete serialized encrypted record.
   One slot/one capture per activation, no overwrites or expiry extension on
   restart, read, disable/re-enable or repeated delivery. Oversize/cache failure
   skips capture with a fixed status, without changing verification. Never fall
   back to filesystem/plaintext storage if Redis is unavailable.
4. Keep configuration and all owner/capture identifiers server-side. No raw mail,
   body, subject, address, signature, canonicalized headers or arbitrary library
   comments enter logs, audit metadata, browser/API responses or process argv.
5. Provide an operator-only Artisan diagnostic command. It can inspect the
   encrypted capture and run only verification/structural analysis, then emit
   fixed allowlisted status codes and tri-state facts for at most five signatures:
   cryptographic verdict, bank signer/alignment, algorithm eligibility, time,
   complete body coverage and coverage of the six fixed required header names.
   Unavailable/not-evaluated facts must remain distinct from evaluated false;
   unknown result codes become a fixed unknown value. Domain names, selectors,
   keys, arbitrary header names/values and library comments are not emitted.
   Preserve the original
   safe verification summary because later key rotation/expiry can change a
   recheck. Label original and current verification separately; neither diagnostic
   report is a payment confirmation. Do not print full library output or decoded
   message values.
6. The command must never dispatch the bank intake job, import transactions,
   create an audit claiming a payment, report to BTCPay or replay a financial
   operation. An expired, malformed or unavailable record yields a fixed status.
   There is no general raw-export command or new HTTP endpoint in this scope.
7. Capture failure does not change the bank verification decision or existing
   retry classification. It can add only a fixed operational capture status.
   Existing review/payment guards, owner binding and idempotency remain intact.
8. Disable writing after the selected capture/window. Read-only analysis can
   inspect an unexpired captured slot while capture is disabled, without extending
   expiry. Live Redis TTL is not a promise
   about old persistence files or backups: encrypted residuals follow existing
   infrastructure retention and key handling. That limitation must be accepted
   explicitly before activation; do not change Redis or backup settings silently.

The capture permits repeated read-only analysis of one original during its
window. It does not recover an earlier consumed message or guarantee that a
normal bank notification will arrive during the selected window. A new transfer
is not a prerequisite; do not reconstruct or ordinarily forward signed content
as a substitute for exact received bytes.

## Alternatives and compatibility

- Existing Mailgun original recovery is preferable if the provider confirms it
  exists. Current failed retrieval and storage metadata do not establish that.
- Adding `store()` to the same route is structurally supported but current Free
  retention is unverified. No paid plan or route change is proposed.
- More audit detail alone narrows a future failure but leaves the original lost
  for subsequent proof/parser qualification. Full request/body logging or public
  mail collectors are outside the permitted privacy boundary.

This is a Satflux-only follow-up with no new dependency, provider package, schema
migration, UI, entitlement or billing change. Older workers continue normal
verification but cannot supply capture; every worker consuming the relevant
webhook queue must be compatible before activation. Rollback disables capture and leaves normal
verification intact; remove only the specific diagnostic cache key if requested,
never flush application queues/cache or alter historical financial records.

## Acceptance and ownership after approval

| Owner | Scope and evidence |
| --- | --- |
| Main | Final contract/configuration, docs, integration manifest, Git publication and separate rollout approval |
| Backend | Dedicated capture service/config/operator command and narrowly scoped verifier/job integration; synthetic tests |
| Independent security | Read-only review of encryption, one-owner/one-record bounds, hostile MIME, TTL, output privacy and absence of payment replay |
| Independent QA | Disposable cache/DB/Node targets including real Redis concurrency; default-off, ARMED-to-CAPTURED transition, eviction/state loss fails closed, deadline/expiry, duplicate no-overwrite, owner isolation, numeric encrypted-size bound, cache failure and positive/negative verification regression |

Before implementation, freeze exact file ownership and test environment. Existing
signature/header policy cannot change as part of capture. Require independent
review and fresh exact-head CI before publication/activation; no production funds,
wallets, database tests or real-message uploads are part of validation.

## Sources

- [Mailgun Routes](https://help.mailgun.com/hc/en-us/articles/360011355893-Routes)
  qualifies storage duration by plan/domain settings.
- [Mailgun inbound HTTP](https://documentation.mailgun.com/docs/mailgun/user-manual/receive-forward-store/receive-http)
  documents original `body-mime` delivery to the existing receiver.
- [Route actions](https://documentation.mailgun.com/docs/mailgun/user-manual/receive-forward-store/route-actions)
  documents forwarding/store actions, not this account's storage entitlement.

Private research and source-audit reports contain no actual bank message or
merchant identifier. Design review disposition will be recorded before requesting
new-scope approval.
