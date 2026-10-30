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

Raw Mailgun delivery proves forwarding-service authentication, not bank origin.
Sender text, a bank-like subject or arbitrary MIME `Authentication-Results` cannot
authorize settlement. Legacy unverified messages remain for manual bank checking;
the invoice must not be marked paid from them.

A protected native receiving-MTA adapter may send this optional metadata alongside
the existing normalized payload:

```json
{
  "bank_authentication": {
    "dkim_result": "pass",
    "dkim_domain": "tatrabanka.sk",
    "dmarc_result": "pass"
  }
}
```

The existing native webhook secret authenticates that adapter. The adapter must
perform actual DKIM/DMARC verification and remove untrusted authentication headers
before attesting a bank domain; it must never copy these values from user-supplied
mail. Do not expose the secret to merchants, browsers or example commands.
Supported signing/From domains are Tatra banka and SLSP, including aligned bank
subdomains. Incoming Mailgun form fields cannot set the internal verified-domain
marker, even if they include this object.

Automatic SEPA parsing currently recognizes the bounded opening classic Tatra
bank statement (optional greeting, date/time, own full checksum-valid SK/CZ IBAN,
explicit credit amount and currency). Other formats, inferred currencies/directions,
masked accounts, ambiguous references and generic compact notifications remain
review-only. The payment reference must come from its explicit transaction field,
not arbitrary payer notes. Generic company bank importing is preserved and is not
represented as covered by this SEPA origin repair.

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
