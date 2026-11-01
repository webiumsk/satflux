# Bank mail through one Mailgun route - 2026-11-01

## Problem and accepted repair scope

The owner confirms Mailgun Free, which permits one inbound route. The existing
shared EU route delivers parsed mail; the store confirmation guard correctly
rejects its missing bank-origin proof. The previously proposed pair of store MIME
and company parsed routes cannot be configured under this constraint. A separate
route creation failed and the owner confirmed only the original remains.

Repair the existing Satflux bank-mail intake within its authorized scope. Preserve
both store SEPA verification and existing company bank imports through one MIME
destination. This is core-only: no provider package, billing, subscription, plan,
wallet baseline, user-interface, migration or deployment change is included.
The original dirty checkout and other repositories remain untouched.

Base: merged `c90caa40e048cdf2b740e0195fd7198bd5209fe7`; branch
`fix/bank-mail-single-route`. Graphify query succeeded in the primary checkout;
its older graph is orientation only. The isolated worktree has no graph and none
was installed or rebuilt.

## Architecture contract agreed before implementation

One Mailgun original-MIME route may serve all generated bank addresses after the
consumer is deployed. Existing HMAC, freshness/replay and size checks remain.
Envelope recipient is resolved using server-owned persisted address tokens, not a
request-provided company/store mode. Recheck and bind owner identity/class before
final downstream handling so token reassignment cannot cross processing policies.

| Recipient owner | Processing | Authority |
| --- | --- | --- |
| Store | Existing UTF-8 MIME, Tatra DKIM, signed recipient and strict evidence parser | Existing original account, positive amount/currency, reference, contract-v2 and dedup guards |
| Company | Separate bounded MIME extraction into the existing Tatra/SLSP importer | Existing company import/matching behavior; never bank-origin attestation or SEPA provider reporting |
| Unknown or changed owner | Reject without importing/reporting | No fallback to another owner or policy |

Unusable optional envelope-sender metadata is ignored at intake without blocking
valid store MIME. Company extraction falls back to MIME From; a usable sender
retains precedence. Sender metadata cannot attest bank identity.

Company extraction uses the already locked mailparser dependency and a separate
CLI. Its binary-safe input supports legacy charset decoding without relaxing the
Store UTF-8 or signature policy. The strict store CLI and verifier stay byte
identical. Company output is extracted/parsed, never verified. Validate original
content digest, field types and byte limits in PHP. It cannot supply a trusted
bank-domain marker or choose the store destination.

Preserve legacy Mailgun envelope-sender preference and forward-detection facts.
Optional sender metadata is encrypted alongside the original MIME before queueing;
old store jobs remain processable. Decoded values remain local and never replace
the encrypted serialized job. Retries/failures and company no-row diagnostics must
not expose decrypted body, sender, subject, account or references. Temporary process
failures retry; invalid content remains unconfirmed. Existing company imports can
match local business documents as paid; this is not proof of authenticated bank
origin and is not a new privilege granted by the MIME adapter.

## Alternatives and rollout

Keep the old parsed/native receiver for compatibility. A two-route deployment is
still possible but no longer required. Upgrading the Mailgun plan is not required
by this repair. Mailgun also offers a separate Forwards API, but no documented
Free quota exemption or mixed-format interaction was established; do not assume
that additional forwarding rules bypass the account constraint. Redirecting the current shared route before consumer deployment
would break company imports and must not be used as a workaround.

After independent review and applicable rollout approval, deploy compatible PHP
code and reload all workers before editing the one EU Mailgun destination to
`https://<app>/api/webhooks/bank-inbound.mime`. Preserve the configured recipient
filter, bank settings, signing/encryption keys and provider contract. Confirm real
intake and downstream processing separately. Rollback must coordinate the route
and workers; do not downgrade workers while company MIME jobs are pending.

## Ownership and independent gates

| Agent | Writable ownership | Dependencies and acceptance |
| --- | --- | --- |
| `/root/wallet_drift_backend` | BankInboundWebhookController, BankMimeVerificationService, ProcessBankNotificationEmail, BankInboundEmailService; separate company Node CLI/extractor; scoped company/store MIME PHP and Node regressions; typed internal result DTO | Implement only this contract; preserve strict verifier bytes and existing APIs; run focused suites, Pint, PHPStan |
| `/root/wallet_bmail_security` | Reports/private synthetic fixtures only | Independent read-only source/security review of exact final hashes and negative cross-policy/privacy cases |
| `/root/wallet_bmail_qa` | Reports/private synthetic fixtures only | Independent read-only regression/import/owner-race/charset/retry checks in disposable environment |
| Main | This note, project state, public documentation in five locales, API schema and generated documentation, integration runner and Git | Integrate accepted source, preserve ratchets, publish scoped branch/PR only; no merge or deployment |

No specialists may commit, push, merge, publish packages, deploy, access actual
bank messages/credentials or use production data. Worktree tests use public sample
configuration, SQLite memory, faked HTTP, existing locked dependencies and disabled
container networking. Ownership is exclusive; authors must preserve others' edits.

Acceptance tests cover both owners on one endpoint, Store never taking company
fallback, no company SEPA report or proof marker, tenant/owner changes, original
sender and forwarded-header behavior, Tatra/SLSP imports and dedup, multipart/charset
and malformed/oversize cases, encrypted retries/failed payloads, safe no-row logs,
old parsed/native routes and all existing strict signature/parser/report regressions.

## Company compact-date and review scope

A native-to-MIME compact retry test exposed an existing parser limitation: explicit
compact dates inherit processing time, making retries across processing seconds
non-idempotent. The initial candidate normalized the date to midnight. CodeRabbit
correctly identified that this optional change also silently merged distinct
same-day credits under the existing dedup fields. Independent QA reproduced both
the collision and the legacy retry limitation using the real importer.

The final adapter restores parser and unit bytes exactly to the merged base;
company deduplication, persistence and history are not changed. Retry qualification
uses classic notifications with explicit bank timestamps and advances processing
clocks. A separate regression proves same-day compact notifications remain separate
under the existing behavior. Compact date-only/undated retry ambiguity remains a
known limitation requiring reconciliation; this patch does not claim to solve it
or invent a bank event ID. Broader financial event-identity design is separate.

## Operational evidence limits

The original event was delivered to the parsed endpoint and correlated with
`unverified_bank_origin`. Original MIME retrieval returned404 through both the
storage URL and canonical EU API; no authentic current bank message is available.
This repair removes the one-route compatibility blocker; it cannot establish the
current bank's layout/signing interoperability or retrospectively confirm that
payment. No new real transfer, forced-paid operation or trust bypass is a test step.

Implementation and all three initial review follow-ups are independently
accepted. [Final follow-up evidence](artifacts/bank-mail-single-route-review-2026-11-01/README.md)
records author PHP 155/623, independent QA 162/677 and security 50/334, with private
controls distinguished from committed tests. The initial 42 runtime cases and 12 probes
passed at unchanged bytes; initial Node22 CI passed but a fresh revised-head run
is required. The original `12e00fa4` candidate and its superseded date normalization
remain explicitly historical evidence. No merchant-visible UI/copy or dependency
changed. Source publication, owner merge/deployment and actual bank qualification
remain distinct gates.
