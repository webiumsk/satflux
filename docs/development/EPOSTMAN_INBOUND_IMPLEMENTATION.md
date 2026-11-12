# ePostman receiving implementation and activation

Date: 2026-11-10. Base: `6e6c0587`. Branch: `feat/efaktura-epostman-inbound`.
Implementation is owner-authorized; production activation is not performed.

## Post-acknowledgement custody protocol - 2026-11-12

PR441's second review adds `post_ack_proof_v1` for browser import handoff. This is
separate from the provider's SAPI acknowledgement: provider ACK still follows
committed encrypted server custody before a browser ever imports the original.

The list/detail advertise the protocol. A new client calls the dedicated
`POST .../efaktura/inbox/{receipt}/prepare-import` with the original digest. The
server retains the validated original and returns a company/receipt/connection/
original/stable-expense-bound authenticated custody token. The client then reads
back the exact local original again under its financial lock. Only successful
fresh post-prepare-response proof permits `/imported` with that token to clear
staging. HTTP waits occur outside the financial lock. Missing proof, prepare
failure or browser closure before proof leaves server recovery available.

The existing `/imported` call without a token is non-destructive, retaining pending
originals for older clients. A new client never falls back from missing protocol,
prepare 404 or invalid responses to the old destructive call. The dedicated route
also protects against stale advertisements during a backend downgrade. Deploy the
backend protocol before its new client. Do not restore destructive old cleanup.

Successful post-prepare proof is the custody transfer boundary. Intentional local
deletion after that proof is client responsibility, including while the later
completion request is pending. The phase token is not remote browser-storage
attestation and the protocol introduces no permanent server archive. Existing
provider archive and production gates remain. Details and regressions are recorded
in [the second review](EPOSTMAN_PR441_REVIEW_ROUND2.md).

## Publication integration - 2026-11-12

The owner authorizes GitHub publication. The separate
`feat/efaktura-epostman-receiving` checkout integrates owned master `07c1af23`
without changing the original manual preview at `bb31eb21`. Only the three state
logs needed conflict resolution; both added histories are retained. All ePostman
runtime and test files remain the previously accepted bytes. The merged locale
catalogs preserve all 174 existing eFaktura messages per language and add only
already-reviewed master migration content. Existing route ownership/auth and
receiving-only controls remain intact.

Independent integrated QA passes 76 tests in six wizard/inbox suites, three
OpenAPI validators and the actual `api-docs:export --check` command in a verified
network-disabled, read-only-source disposable PHP environment. No generated
contract update is required. Reused dependencies match the current master lock;
no dependency installation or existing preview data change is performed.
Main full lint passes with 46/52 warnings, typecheck with 110 inherited/no new
errors, locale parity and widget provenance pass, and isolated production build
passes with 7,659 transformed modules. Additional full frontend tests and remote
exact-head CI are tracked separately from these completed targeted checks.
[Draft PR441](https://github.com/webiumsk/satflux/pull/441) contains the published
branch and its receiving-only changelog note. Exact-head CI is recorded separately.
Provider archive/export/contract and production activation remain outstanding.

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
  the evidence date. Initial contract tests use synthetic provider replies.
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
Initial local checks used synthetic identities and network-disabled provider
replies; actual sandbox operations are recorded below. PHP regression tests use a
network-disabled container, SQLite in-memory, per-container
temporary storage and isolated test settings. Frontend tests use synthetic data.

## Production handoff gates

For an initial read-only sandbox authentication/list/detail diagnostic, see the
[protected-input probe instructions](../../scripts/efaktura/README.md). This does
not acknowledge or import documents and is separate from the full test below.

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

## Manual localhost preview

The isolated preview runs at `http://127.0.0.1:8082`. Its own PHP/nginx/Vite project,
SQLite database, application key, sessions and browser origin are independent of
the migration preview on `localhost:8080`. Vite is internal and same-origin proxied;
relay sync is disabled. This preview uses the local feature worktree with the
reviewed signed-total and receiving-settings projection fixes.

From the main checkout, retrieve the synthetic account credentials in your local
terminal with `bash storage/app/private/.env.efaktura-local/show-login.sh`. The
helper reads an owner-only file; password values do not belong in this runbook.
Use the normal legacy-account login and complete the recovery-phrase enrollment.
The normal account then uses its phrase for future sign-in or device unlocking.
Import the server company through **Import from server** on Invoicing, select
**eFaktura sandbox**, then open **Expenses > Incoming e-invoices**. Review/download
the originals and choose **Import to expenses**. Two recovered copies of the
owner's earlier actually received Invoice/CreditNote are initially pending for
this separate browser handoff; their provider ACK provenance is retained.

For a fresh Recommand TEST document, use company settings > eFaktura > **Download
incoming now** to poll the provider, then refresh the Expenses inbox. The inbox
refresh button only reloads staged items. This manual preview has no scheduler.
The sandbox connection is already configured from the protected local input.

The private lifecycle helper is
`bash storage/app/private/.env.efaktura-local/infrastructure/preview.sh start`
or `stop`. Seed tooling is exclusive to a fresh preview database. Configuration,
the pristine pre-QA snapshot and private evidence live under
`storage/app/private/.env.efaktura-local`; keys, fixtures and login input use
owner-only `.env.*` files. Normal-UI QA and the pristine human handoff are recorded
below.

## Verification record

### Normal localhost UI and inbox projection correction - 2026-11-12

Independent QA exercised the actual loopback application without API mocks.
Normal consent, password login and recovery enrollment succeeded. Continuation
used the existing authenticated profile and ordinary phrase unlock, then normal
server-company import. Two pending recovered originals were viewed/downloaded and
imported through actual HTTP routes. Digests, signed totals and currency matched
the independent recovery manifest. Two expenses and attachments retained exact
original bytes after reload. Guest 401, missing-CSRF 419, foreign-company 403/404 and
duplicate confirmations passed. The accepted run recorded zero page errors and
zero blocked external requests. Full Chrome restart was not repeated in this run
and requires normal phrase unlock; earlier custody restart evidence is separate.
Backend network traffic was not captured and provider polling was not invoked.

The inbox initializer used allCompaniesQuery, whose intended lightweight
projection excludes appSettingsJson. The two-line fix reuses the existing
allCompaniesDetailQuery. Global list projection, ownership, company selection and
generation guards are retained. A realistic summary/detail regression fails on
old source. All 41 tests in 5 files, scoped lint, typecheck ratchet (110 inherited, no new errors),
five-locale parity, widget check and isolated-output production build pass.
Independent security accepts runtime SHA256
`98d72bd17dc6ffc47389d8f0bcefa34b491b117090a9b7bdc3eaf792eb64c03c`
and test SHA256
`c1a0c1ae1adc8e338b3be48fac10ff61d2806b43adee53f998aef472dd87236c`.

Preview config reuses the project's Vue absolute-image handling and narrowly maps
observed public font/SQLite assets to existing allowed dependency paths. Private
paths remain blocked. Independent security accepts startup/seed/configuration
and normal-UI helpers. The seed requires the exact empty preview database and
runs without networking. After QA, its post-test database was retained, only
preview PHP stopped, the pristine snapshot restored, own sessions/cache cleared
and PHP restarted. Human handoff again has two pending originals and needs its
own recovery enrollment. Private evidence is under coordinator
`storage/app/private/.env.efaktura-local/state`.

### Actual cross-AP CreditNote and signed-total fix - 2026-11-12

The owner's Recommand TEST CreditNote passed actual SAPI listing/detail/XML and
the real poller in a fresh persistent isolated database: imported1, acknowledged1,
failed0. Before ACK, the reviewed guard independently verified the CreditNote root,
namespace and signed amount inversion from direct root PayableAmount, together
with committed encrypted custody and synchronized recovery bytes. Its original
BillingReference exactly matches the prior test invoice. The reference remains
in XML; structured expense linking is not implemented.

Independent Python Decimal/XML expectations verified a negative local expense.
Actual Chrome/Evolu import, interrupted confirmation/retry, reload and full browser
restart preserved one expense, one attachment and identical original XML bytes.
The actual backend markImported transition passed twice after protected digest
and negative-total proof, leaving one imported receipt and cleared staging. Browser
HTTP was intercepted; provider and backend service transitions were actual.

Inspection found that negative-valued CreditNotes were incorrectly forced to
negative expenses. The one-line parser fix now subtracts the signed source amount
from zero. Six literal regression cases cover positive/negative/zero CreditNotes,
negative invoices and positive/negative TaxInclusive fallback. Author parser plus
existing ePostman tests pass26tests/174assertions; scoped Pint/PHPStan and whitespace
pass. Independent QA passes11tests/25assertions and independently reproduces the
two old negative-CreditNote failures. Security separately accepts six synthetic
parser cases. Reparse of the actual CreditNote with corrected source also passes.
[Peppol BIS Billing sections5.6 and5.6.1](https://docs.peppol.eu/poacc/billing/3.0/bis/)

Reviewed parser SHA256 is
`83334960f4b0433677ed1e565d3433267b03b497084f45e80eceb6a70d32e3e2`;
regression test SHA256 is
`7c79a97b5a59c74b32169f9a762f9a0ae0c6ef3fd3ca5d3425e6b393cbb89499`.
The QA-tested pre-Pint test differs only in one brace/newline formatting change,
independently reconstructed and accepted. The feature worktree has no Graphify
graph; the primary graph was queried and no unrelated graph was rebuilt.

Private anonymous evidence, encrypted original recovery bytes, separate test key
and a private actual-browser profile archive are retained in coordinator
`storage/app/private/reviews/epostman-inbound/live-creditnote`. The plaintext detail
fixture was removed after verification; dedicated Vite stopped. The same bounded
background browser diagnostics from the invoice test remain recorded by QA.
Source is local `f01f7d5b` plus the two-file signed-total correction and this runbook.
Provider archive/export/contract, production activation and deployment remain
outstanding. Existing normal localhost preview and production were untouched.

### Actual cross-AP sandbox invoice and local handoff - 2026-11-12

The owner created a Recommand playground explicitly connected to Peppol Test
Network and sent one synthetic Invoice to the existing ePostman participant.
The actual SapiSkClient listed one receipt and retrieved its original XML. The
unchanged EfakturaInboundService then received it into isolated persistent SQLite:
imported=1, acknowledged=1, failed=0. Before the real provider ACK, reviewed test
middleware verified encrypted custody through an independent SQLite connection
and synchronized an encrypted recovery copy. A new backend process confirmed
the pending receipt and encrypted original remained available.

Independent actual Chrome/Evolu QA imported that original with matching amount
and currency, one expense and one attachment. An intercepted first confirmation
returned503; retry preserved stable identities without duplicates. Exact original
bytes survived reload and full Chrome restart, with six OPFS files observed.
After the protected browser proof matched the receipt digest, the actual backend
markImported service succeeded twice, leaving one imported receipt, zero pending
items and cleared server staging. Browser HTTP transport was intercepted; real
provider operations and backend service transitions were exercised separately.
Existing accepted tests supply authenticated API/CSRF/tenant-route coverage.

Browser QA also recorded six background error events reproduced with synthetic
data: three optional classic OPFS proxy NetworkErrors and three empty-source
events that remain unclassified. Three external requests were blocked without
retaining their URLs. Every required SAHPool persistence assertion passed. These
diagnostics remain recorded separately from the bounded receiving/import result.
The dedicated Vite server was stopped, a private browser-profile recovery archive
was retained with encrypted original recovery bytes and the separate test key,
and the temporary plaintext detail fixture was removed after verification.

Independent security accepted the helpers after owner-only `.env.*` sensitive
artifact naming and key/directory synchronization corrections. Persistent test
SQLite uses synchronous=FULL, a separate retained key, readonly source/vendor
mounts, null logging and a fixed sandbox HTTP allowlist. Credentials, original
content, participant/document IDs and browser owner material are absent from
anonymous reports. Private evidence is retained in the coordinator checkout at
`storage/app/private/reviews/epostman-inbound/live-sandbox`.

This invoice test used local `f01f7d5b`; no product code, existing localhost preview,
Git publication or deployment changed during that test. One actual Invoice was
verified. The subsequent CreditNote test and parser correction are recorded above;
provider archive/export/contract and production activation remain outstanding.
The earlier direct DNS NXDOMAIN observations below did not establish the cause
of the initial failure and are superseded by actual cross-AP delivery.

### Earlier discovery observations

On 2026-11-12 the existing isolated read-only probe again authenticated and listed
one empty page. After the owner's Recommand send returned recipient-not-found,
direct standard Peppol TEST SMK DNS checks returned NXDOMAIN for both the legacy
MD5/CNAME and current SHA-256/base32/NAPTR participant encodings. The test-zone SOA
control succeeded. No identifiers or credentials are retained in this record.
External TEST discovery must be investigated with the sandbox Peppol registration;
the provider's internal state and Recommand playground network selection remain
unverified. No provider registration, sending, ACK or import was performed by the
agent during these checks.

Actual read-only ePostman sandbox probe on 2026-11-10: authentication succeeded
and the real receive listing returned one page with zero documents. No XML detail,
ACK/send, expense import or company write occurred. This is actual transport
evidence, not actual invoice reception or cross-AP/archival/production proof.

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
checkout's `storage/app/private/reviews/epostman-inbound`. The 2026-11-12 records
above verify one cross-AP test Invoice and CreditNote with isolated local handoff.
Provider activation/archive verification and production remain unexecuted.
No production readiness or signing of provider terms is claimed.
