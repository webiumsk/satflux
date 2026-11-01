# SEPA confirmation isolation compatibility and recovery

This runbook describes the owner-authorized repair. Local source/test readiness
does not establish package installation, deployment or any-bank instant payment
verification. Commercial bank integrations remain deferred.

## Contract and rollout

Plugin 0.8.2 exposes `confirmationContractVersion: 2` in its settings response.
Automatic external reports require route-store ownership, verified bank origin,
the observed receiving IBAN and matching credit amount/currency/reference.
The original invoice's account remains authoritative after settings changes.
Genuine complementary FIO/NOP/B-mail feeds may confirm the same account without
changing the original backend snapshot. Manual confirmation stays an explicit
authorized action.

| Producer/provider pairing | Behavior |
| --- | --- |
| New Satflux + contract-v2 plugin | Complete authenticated evidence may confirm; missing proof remains unconfirmed |
| Old Satflux + new plugin | Missing origin/receiving-account fields return review; no automatic settlement |
| New Satflux + old plugin | Capability check refuses automatic reports, preventing ignored security fields |

Prepare and review independent repository changes. With explicit operator approval,
install the plugin first, then deploy Satflux. Verify the actual settings capability
after installation; version text alone is not the contract check. No new database
migration is introduced. Preserve the plugin database and Data Protection keys
together for recovery. Do not downgrade to a provider that accepts unbound reports
to work around incomplete evidence.

## FIO

Use a read-only account token dedicated to this consumer. The authenticated
statement must contain the actual receiving IBAN; configured settings are not a
replacement. A credit to another account cannot confirm the original invoice.
The bank-limited 35-second poll tick is unchanged and is not a latency guarantee.
Multiple distinct QR references in a fallback message require manual review; an
explicit primary bank reference retains precedence.

Recording failures must propagate to the existing cursor-rewind path. Verify this
with a fake bank response and disposable database, including a recorder that
returns false and one that throws. Never reset a real account cursor for testing.

## Trusted bank-mail ingress

Tatra automatic confirmation uses Mailgun original-MIME delivery. The compatible
consumer can share one MIME destination between store SEPA addresses and company
bank-import addresses. This supports the one-inbound-route limit on Mailgun Free;
check the actual account plan and region rather than assuming another route can
be created.

After compatible consumer deployment and worker reload approval, edit the existing
route in the domain's receiving region. Preserve its intended recipient scope and
other unrelated routes. With the default domain/prefixes, a single combined rule is:

```text
match_recipient("^(?:ps|pay)[a-z0-9]{8,16}@payments[.]satflux[.]io$")
forward("https://<your-app>/api/webhooks/bank-inbound.mime")
stop()
```

Adapt the domain and prefixes to actual configuration. An existing domain-wide
recipient rule may also keep its scope and use this same MIME destination; unknown
recipients are rejected by server-owned address resolution. No additional Mailgun
storage action is required. Do not add a second destination for the same message
or infer payment from an intake acknowledgement.

The server selects policy using the persisted recipient owner:

| Recipient | Processing policy |
| --- | --- |
| Store SEPA address (default `ps`) | Existing strict Tatra bank signature and transaction-evidence verification |
| Company import address (default `pay`) | Separate bounded legacy MIME extraction into existing Tatra/SLSP importing and local matching; no SEPA bank-origin proof |

The optional Mailgun envelope sender is encrypted and retains precedence for
company importing; forwarded-message detection remains active. Company MIME can
contain supported multipart/charset formats. It does not relax the store's UTF-8,
single plain-text, signature or recipient-binding requirements. Unknown owners,
owner changes and invalid store proof cannot choose the company policy.

Older consumers reject company MIME. Therefore do not switch a shared route until
all web and queue workers support this contract. Existing parsed company/native
receivers remain compatible; two separate routes may still be used on other plans
but are not required. Roll back route/worker configuration together and account for
pending encrypted jobs before downgrading. No route, bank setting or deployment is
changed by preparing this source. See [the design and evidence](BANK_MAIL_SINGLE_ROUTE.md).

Install the isolated locked runtime dependencies with
`npm ci --prefix runtime/bank-mail-verifier --ignore-scripts` in the deployed
checkout. The PHP Dockerfile installs Node22. After rebuilding/reloading, inspect
the effective Laravel `bank_inbound.node_binary` configuration in each worker,
including any `BANK_MAIL_NODE_BINARY` override and cached configuration. Run that
selected executable with `--version`; an unqualified `node --version` can report
a different binary. The PHP queue worker launches
the local verifier over argv/stdin; there is no new network service. Configure
`BANK_MAIL_NODE_BINARY` only when Node is not available on the worker PATH.

Intake verifies the existing Mailgun token/timestamp HMAC and replay cache. It
preserves original MIME bytes (including CRLFs), limits them to 256KiB and encrypts
them before queueing. The same raw bound applies to companies; decoded text is
limited to 65,535 bytes, so attachment-heavy or oversized legacy messages may need
manual import rather than silently exceeding the supported profile. Keep the app encryption key with queue recovery backups.
Failed jobs retain ciphertext and sanitized errors, never raw mail. For store recipients, the verifier
independently verifies an aligned Tatra DKIM signature over the complete body,
signed From/To/Subject and MIME controls. Its signed recipient must match the
store address. Sender-supplied authentication headers, partial-body signatures,
ambiguous MIME, HTML and missing proof cannot authorize payment. Temporary DNS or
process errors retry through the existing bounded queue backoff.

### Diagnose an acknowledged but unconfirmed bank message

Mailgun Delivered/HTTP200 acknowledges intake, not bank proof or settlement. Inspect
the `webhooks` queue and the audit actions `sepa.bmail_review_required`,
`sepa.bmail_processed` and `bank_inbound.mime_rejected` in the actual deployment.
Convert event times to the configured application timezone before correlating
them. Container health alone does not prove queue consumption.

Store MIME rejection keeps the compatibility reason `unverified_bank_mime` and
adds an allowlisted `verifier_reason`. This distinguishes unsupported MIME,
missing/invalid signatures, identity/header problems and pre-verifier invalid
UTF-8. Unknown or malformed child reasons become a fixed `unknown` value; never
copy arbitrary verifier output or bank contents into diagnostics. Temporary
verification failures retain the existing retry path rather than producing a
permanent-rejection diagnostic. Company policy is unchanged.

Older audit rows contain only the broad reason. A handled rejection does not
retain the original MIME in the consumed job, and new diagnostics cannot recover
its lost reason. Preserve any privately available original bank EML; do not
reconstruct signed content, replay a payment or require another real transfer
just to diagnose it. An empty queue or audit result cannot establish settlement:
unsupported transaction layouts can also produce no parsed credit rows.

### Supported transaction profile

The initial profile is one UTF-8/ASCII text/plain part and the documented classic
Tatra numeric prefix: greeting, valid date/time and receiving account, positive
EUR credit, three balances, constrained CCINT/CSO account description, then the
QR/E2E or structured VS reference before free recipient/name text. Required
fields cannot be skipped or reordered. Duplicate/reserved labels, markup and
unknown layouts require review. Public historical examples establish this
bounded profile; the synthetic QR fixture is not proof of actual current Tatra
rendering. No current original EML is available for interoperability qualification.
Store SEPA confirmation via SLSP remains review-only. Existing company importing/matching and FIO/NOP policies are unchanged; company
MIME extraction is a separate transport adapter, not automatic SEPA proof.
Company extraction is qualified by synthetic UTF-8/ASCII, Windows-1250 and
ISO-8859-2 examples, with bounded supported legacy root charsets. Nested encoding
handling follows the locked mailparser behavior; it is not independent bank
verification or a guarantee that every malformed MIME is rejected. An unusable
optional envelope sender is ignored so it cannot veto a valid store message;
company importing then uses MIME From. Usable envelope metadata keeps precedence.

Company compact-date parsing and deduplication remain unchanged. Compact Tatra
messages with date-only or missing dates retain the processing-time component,
so retries across processing seconds may create additional rows. This repair does
not normalize them to midnight, invent a bank event ID or silently merge distinct
same-day compact credits. Native/MIME retry qualification uses a classic message
with an explicit bank timestamp. Review compact cases manually; broader event
identity/reconciliation design is separate from the one-route adapter.

Credit dedup uses a versioned canonical fingerprint of receiving account, bank
booking minute, amount/currency, all balances and bank transaction description.
It excludes invoice references, free notes and message rendering. This is not a
bank-issued unique ID: indistinguishable legitimate credits can collide, so they
must not be counted twice automatically. Provider dedup remains store/source/
account scoped; a repeated key cannot prove that a second invoice was paid.
Review outstanding intent and old confirmation history before rollout.

Existing authenticated native receiving-MTA adapters may continue submitting the
optional complete `bank_authentication` object (`dkim_result`, `dkim_domain`,
`dmarc_result`). Such adapters remain responsible for real verification on the
exact selected body. Mailgun inputs cannot supply the internal verified-domain
marker. The concrete MIME verifier is Tatra-only; accepting a legacy notification
or acknowledging HTTP200 does not establish a payment.

## Recording and NOP recovery boundaries

Plugin matching reserves its scoped dedup identity before recording, using the
plugin transaction and existing unique index. Invalid evidence cannot consume an
identity; corrected trusted reports can retry. BTCPay uses the same reference-based
payment identity across retries. The plugin database transaction is **not atomic**
with BTCPay's payment persistence: an interruption after external recording but
before plugin commit requires same-reference retry/reconciliation. This repair
does not establish crash-atomic arbitrary cross-reference event reservations.

NOP recovery must not derive transaction-ID creation filters from the last
notification polling time. Fetch retained notifications and handle pagination;
failed processing or incomplete fetches must not advance recovery checkpoints.
MQTT callback failures require bounded reconciliation retries even while connected.
NOP's upstream retention window still limits recovery; old unavailable evidence
is not proof of payment. Public NOP history remains a diagnostic timeline only.

## Operator handoff

Before approving installation/deployment, review exact source revisions, independent
security/QA dispositions and synthetic test evidence. Afterwards, verify actual
provider capability and ingress configuration without pasting credentials or bank
messages into chat/logs. Confirm legitimate same-account operation and manual
fallback, and keep real-money testing outside automated checks. No runtime changes
are performed by this runbook.

## Local verification - 2026-10-29

Independent security accepted the final 11-file PHP and 14-file plugin production
scopes, including the FIO ambiguity guard and the disposable-database CI change.
Independent QA verified 162 plugin tests with zero skips against PostgreSQL 17
and the pinned BTCPay 2.4.5 host. Plugin and test assemblies were rebuilt; the
plugin retains two existing obsolete Razor API warnings. The local dependency
symlink required a private canonical host-reference import; CI retains the real
pinned submodule and its host guard. Remote CI has not been run for these branches.

The full PHP suite passes 2,163 tests / 10,437 assertions in an isolated no-network
container. Its synthetic fixture uses DOMPDF, sufficient temporary storage/memory
and a testing-only DNS shim for the existing `satflux.io` MX validation cases; this
is not a live DNS or mail-deliverability check. One unrelated existing PHPUnit
doc-comment deprecation remains. PHPStan, Pint and API export/check pass.

The full frontend suite passes 1,054 tests in 142 files with two workers. Locale
parity passes; typecheck preserves its existing 120-error baseline and lint passes
with 48 existing warnings under the unchanged 52-warning limit. Project manifests
and lockfiles are unchanged. Private evidence and scoped recovery patches are
retained in ignored storage; original unrelated edits remain outside these changes.

These are local source checks. No paid provider, package publication, installation,
production banking test or deployment is established by these results.
