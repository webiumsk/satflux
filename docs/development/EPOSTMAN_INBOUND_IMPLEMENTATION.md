# ePostman receiving implementation and activation

Date: 2026-11-10. Base: `6e6c0587`. Branch: `feat/efaktura-epostman-inbound`.
Implementation is owner-authorized; production activation is not performed.

## Product and authority

Receiving is baseline functionality for a Slovak company using Satflux without
another invoicing application. The owner explicitly selected encrypted local
Satflux/Evolu data plus the provider archive, with temporary encrypted server
staging. No permanent Satflux server archive or billing change is introduced.
An independently configured LLC stays separate. Existing SK eligibility, company
ownership, support/admin exception, Sanctum/CSRF and feature flag remain in force.

Scope is ePostman receiving. This is not a complete sending/reporting integration;
ePostman outgoing must fail closed until independently implemented and verified.
Generic existing providers retain their integration behavior except for necessary
shared original-custody safeguards.

## Acceptance and integration contract

- Fix ePostman origins to `https://postman.slovakodata.com` and
  `https://testpostman.slovakodata.com`; never send credentials through redirects.
  JSON client-credentials authentication and `/sapi` paths follow the current
  [public OpenAPI](https://postman.slovakodata.com/openapi/sapi.json), fetched on
  the evidence date. No provider operation or demo credential is used for tests.
- Bind receipt identity to company, provider origin/environment and participant.
  A switch cannot rebind old receipt identifiers; concurrent polls cannot duplicate
  expenses. Pagination must finish or fail visibly, not silently lose later pages.
- Validate invoice/credit-note XML and the exact customer endpoint identity before
  durable custody. Reject malformed replies, unexpected roots, DTD/entity content,
  mismatched recipients, redirects and provider errors without acknowledging them.
- Provider ACK follows successful durable custody, including the original XML.
  This is not a statement that the browser imported the document or that the
  provider's reporting obligations are verified.
- Scheduled polling receives with the browser closed. The browser imports later,
  with stable expense and attachment identifiers and recovery after partial writes.
  Verify persisted attachment bytes and company before resolving the server item.
  An echoed original digest is an integrity guard, not remote storage attestation.
- A failed or oversized local attachment remains pending and downloadable. Merely
  finding an expense cannot hide or clear the original. No destructive dismissal
  or age purge may remove the only established copy.
- Pending staging may last until successful local import. XML is encrypted with
  server-held Laravel keys; server processing and parsed summaries are not E2EE.
  After successful handoff, local data uses existing Evolu encryption. Provider
  archive access/retention/export must be confirmed before production use.
- Setup distinguishes FS selection, provider account/contract activation, API
  authentication and actual receiving evidence. No token-only green check claims
  statutory readiness. Keep EN/SK/ES/DE/CS copy aligned.

## Ownership and dependencies

| Task | Role | Writable ownership | Dependencies / evidence |
| --- | --- | --- | --- |
| Provider transport, poll/custody/provenance, safe API | Backend | `app/`, `config/efaktura.php`, necessary `routes/api.php`, migrations, PHP tests | Published schema; isolated PHP regression tests |
| Setup, detail/export, verified local handoff | Frontend | `resources/js/` and its tests/locales | Backend detail/import contract; focused Vitest and static checks |
| Coordination, docs, test environment, integration | Main | This runbook, five user articles, shared state, isolated test infrastructure | Disjoint authors; exact-file manifests; no Git publication yet |
| Security review | Independent security | None (read-only) | Tenant/credential/XML/custody/replay/connection scenarios |
| QA | Independent QA | None (read-only) | Provider contract, partial failure/retry, scheduler, local attachment proof |

No other repository is changed. No new dependency is added. The original local
vendor directory was older than the committed lock, so a separate test copy was
installed from that lock; existing application dependencies were not modified.
No real funds, production database, customer credential or provider account is
used. PHP tests use a network-disabled container, SQLite in-memory, per-container
temporary storage and isolated test settings. Frontend tests use synthetic data.

## Production handoff gates

1. Complete local implementation checks and independent QA/security review.
2. Obtain company-scoped sandbox credentials securely outside chat/source and
   verify their allowed test environment. Do not reuse production credentials.
3. With explicit sandbox authorization, receive an invoice and credit note from
   a different AP in the actual test network. Verify XML, receiver, pagination,
   delayed import, original export and retry after simulated failure. A fake API
   response or internal simulator is not this evidence.
4. Confirm provider contract, MINI entitlement, archived originals, cancellation
   access/export and any API restrictions against the actual account. Public
   advertised ten-year storage is not an independently tested archive guarantee.
5. After separate production approval, select/activate the correct SK participant,
   deploy reviewed frontend/backend and migration together, enable the operator
   flag and verify scheduler, receiving and monitoring for that one company.
   Do not enroll the LLC or move an existing receiving registration implicitly.

## Compatibility and rollback

The frontend/backend handoff must deploy together: old browser imports cannot
clear an original without the new confirmation. Reload stale clients. Preserve
receipt metadata and originals during rollback; an old destructive purge/import
implementation must not be reactivated against newly received pending documents.
Disable only the affected receiving connection if transport recovery is needed;
preserve staged documents and restore access after fixing the connection.

The provenance migration preserves historical receipts under `legacy`; it does
not guess their provider from current company settings. A new receipt colliding
with an unknown historical document ID fails closed and requires operator review.
Migration rollback refuses to discard provenance when multiple connections have
the same company/document ID. Preserve evidence and roll forward in that case.

## Known receiving limits

The existing local attachment limit is **384 KiB**. Larger originals remain in
encrypted temporary staging, visible and downloadable; they cannot finish local
handoff in this implementation. Staging can therefore continue until the import
problem is resolved. Do not promise unlimited local document sizes or automatic
expiry of unresolved originals. The separate provider archive does not constitute
proof that this copy is recoverable until actual account terms/export are verified.

## Verification record

Independent security and QA accept the frozen 36 runtime/test paths, manifest
SHA256 `b8296fafe97a6d7a77c57eecc9a1e83165335a8a19e6a24732e5268a4051b75f`.
The final 17-path frontend manifest is
`775bcfec6322405d616c6277959c714c3260a7991d2ac5ad35be279eea205039`.
These reviews do not approve provider contracting or production readiness.

Verified local checks:

- Locked-dependency targeted PHPUnit: **104 tests / 554 assertions pass**.
  Independent QA separately runs **67 tests / 424 assertions**, passing under the
  same exact locked dependencies and isolated per-container temporary storage.
- Full frontend suite: **2,325 tests / 209 files pass**. The final bounded origin
  normalization change has a separately rerun **19-test** wizard suite. These
  counts overlap and must not be added together.
- Actual Chrome with real Evolu SQLite-WASM/SAHPool: exact BOM/non-ASCII original
  bytes verified after import, page reload and full browser close/relaunch.
  A deliberately failed first import-confirmation request is recovered without
  duplication (one expense/attachment, two requests); oversized original stays
  pending. Six OPFS files are observed. All external traffic is blocked and
  provider responses are synthetic. The optional classic OPFS proxy logs an
  initialization warning; the used SAHPool persistence passes the restart test.
  Report SHA256 `7aa66d90f9d0abb3d5180953c7b4223cc8e9d2f98ee3e40fa876cd6938000b32`;
  executed harness SHA256
  `19fe46078d01ded4cec78f05cde8b9115f106179108c7ee3c2e33ffe889d8469`.
- Production build, lint (48 inherited warnings, limit52), typecheck (110 baseline,
  no new errors), five-locale parity, explicit Pint19, PHPStan (no errors), API
  export/check and three OpenAPI validators, changelog14 and whitespace pass.
- The full PHP run is **not green**: 3,216 tests / 15,805 assertions, 33 errors,
  28 failures, 69 skips and one inherited PHPUnit deprecation. All **61** error/
  failure cases reproduce on an unchanged source-only snapshot of `6e6c0587`
  using the identical minimal test environment. Independent QA verifies every
  case identity, classification and first diagnostic, plus all2,498 baseline file
  blobs. Causes include missing PDF-driver defaults, absent bank-mail Node
  dependencies and network-disabled DNS email checks. No unrelated product fix,
  test exclusion or weakened assertion is introduced to hide those results.

The publicly fetched SAPI schema SHA256 is
`281d68e06762768856ceb1ad2916194f9d6622ef9804df1363e52e5cc2325fd2`.
The existing primary-checkout Graphify graph was queried before exploration; this
isolated worktree has no graph of its own. No graph was silently rebuilt or
updated against unrelated primary source.

Private manifests, reviewer reports and logs are retained under the coordinator
checkout's `storage/app/private/reviews/epostman-inbound`. Actual cross-AP sandbox,
provider activation/archive verification and production remain unexecuted. No live
readiness or signing of provider terms is claimed by this document.
