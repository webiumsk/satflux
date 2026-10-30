# SEPA confirmation isolation repair

Owner authorization: 2026-10-29, "paid not for now; perform the repairs and
everything necessary." This authorizes the owned Webium plugin and necessary
Satflux changes/tests. Commercial integrations, publication and deployment remain
outside this implementation. The earlier audit-only restriction is superseded
for this repair; unrelated work remains preserved.

## Required behavior

Automatic confirmation requires a trusted route/source store, the original
request's receiving IBAN, observed credit amount/currency and source evidence.
Matching must reject a foreign reference before any request/dedup mutation.
FIO retains receiving-account evidence from its authenticated statement. NOP
integrity verification uses the immutable invoice account, not current settings.
Manual confirmation is a separate authorized action rather than a magic automatic
source identifier. Recorder checks invoice/request store ownership defensively.

Preserve historical paid records, FIO cursor recovery, legitimate duplicate
handling and existing pending invoice snapshots. No database migration is planned.
Public NOP history remains diagnostic. FIO's bank-respecting poll interval stays
unchanged; this repair does not establish any-bank instant confirmation.

## Provider/consumer contract

The plugin settings response exposes `confirmationContractVersion: 2`.
`POST payment-requests/report` gains optional `creditorIban` and
`bankOriginVerified` (default false). These are evidence from the authenticated
producer, never values inferred from expected invoice settings or a public lookup.
Known store-owned references without required proof return a controlled `review`
outcome, never automatic settlement. Missing proof does not consume dedup identity,
so a corrected trusted retry can be processed. Foreign references remain unknown.

New Satflux checks the capability before reporting; an old plugin cannot silently
ignore new security fields and settle anyway. Old producer/new plugin falls back
to review. Deploy the plugin first, then Satflux, under separate approval.

Satflux appends separately parsed receiving-IBAN and explicit credit/currency
confidence to its bank-notification row. Generic company bank importing keeps its
current behavior; SEPA uses stricter evidence. Unverified Mailgun sender/body text
or arbitrary authentication headers cannot establish bank origin. A protected
native receiving-MTA adapter may attest DKIM/DMARC-aligned bank identity through
the authenticated native webhook; raw Mailgun deliveries without such verified
provenance remain review-only. Existing company-import authenticity risk is
outside this SEPA repair and must not be described as fixed.

## Ownership and independent review

| Owner | Repository/files | Boundary |
| --- | --- | --- |
| `sepa_plugin_audit` implementation | Fresh owned plugin worktree; SEPA confirmation/matching/recorder/controllers/models, version patch and diagnostic labels | Production plugin files only; no test ownership or publication |
| Main coordinator | Satflux SEPA producer, bank row/parser evidence, authenticated inbound metadata, API contract, docs/state | Preserve unrelated edits; no company-import redesign |
| `cleanup_review` QA | SEPA plugin regression tests; Satflux B-mail/parser regressions; disposable test runners | Independent tests; no production file changes |
| `sepa_evidence_security` | Independent final design/source review | Read-only; no implementation edits or publication |

All specialists are operating in a shared workspace. No one reverts another
owner's edits, and only the coordinator controls Git branches/publication.

## Verification and delivery gates

Use installed .NET 10 and the exact verified BTCPay host pin; no host guard bypass.
Database-backed plugin regressions use a dedicated disposable PostgreSQL target
with synthetic records. PHP tests use a separate no-network container, SQLite
memory DB, synthetic environment and isolated cache/storage; no existing app
container or configured merchant database is a test target.

Required cases include actual cross-store matching with no foreign mutation,
wrong/missing receiving account, valid NOP hash for another account, settings
changes after invoice creation, amount/currency mismatch, legitimate duplicate
and retry recovery, explicit authorized manual confirmation, legacy producer,
old plugin, forged mail origin, inferred credit/currency and ambiguous references.
Keep corrected retries possible without duplicated recording.

Independent security and QA review the final exact source scope. Run targeted
and relevant existing suites plus applicable static/style/documentation checks.
Prepare separate repository diffs and compatibility evidence. No push, package
publication, service restart, migration or deployment is included.
