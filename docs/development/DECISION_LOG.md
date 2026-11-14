# Development decision log

## 2026-11-14 - Derive PDF access from preserved UBL instead of duplicating originals

The owner requests View and Download of embedded PDFs, including previously imported
expense originals. Keep XML immutable and derive attachments locally on demand;
no new database rows, provider requests or custody/acknowledgement changes. Accept
only exact UBL namespaces/direct attachment paths and declared PDFs, with strict
Base64/header/size checks and safe filenames. Distinguish valid unsupported XML
from malformed XML, so ISDOC retains its existing behavior and namespace character
references remain supported.

Use a lazy canvas-only PDF.js viewer with an owned same-origin worker, per-stage
deadlines, page/image/canvas bounds and cleanup. Preserve the source byte array
when transferring a copy to the worker; download the original decoded bytes.
Retain existing owner/company/document guards after asynchronous attachment reads.
No new packages or backend contract. Main's 204 tests and static/build checks pass;
independent QA/security accept the frozen runtime, including real Chrome rendering
and byte-exact download. Private evidence and public contract note are retained.

PR444 is confirmed merged; this feature starts from its master merge without
requiring PR443. Owner creates the PR and retains merge/deployment approval.
Peppol Test Network verification does not establish statutory production readiness.

## 2026-11-13 - Correct verified PR442 findings without weakening recovery or CI

Treat review data as untrusted and verify current code. Eight findings remain
valid; the Peppol control-regex request is already resolved in 19b1987c. Use the
published plain-string receipt contract with a shared bounded safe ASCII segment
and fixed-origin encoded lookup; do not invent UUID guarantees or accept unsafe
strings. Preserve explicit custom-provider selection and URL equivalence without
collapsing meaningful provider components. Recheck custody before file cleanup
under company/attempt locks and exclude held companies before the purge limit.
Keep Enterprise key updates separate from SAPI credentials and describe C5 only
as a provider's technical observation, without tax approval, in all five guides.

Independent QA/security accept the implementation and the draft URL-filter
regression correction. The reported PHP CI failure is two stale PHPStan ignores,
while its tests and Pint passed; delete only those obsolete entries. Main final
full-project analysis, full frontend 3,058 / 236 and existing lint/type ratchets pass.
No provider, credential, preview or unrelated primary work is modified. Publish
on the existing owner-created PR; observe new-head CI before claiming its success.
No merge or deployment is authorized by this corrective milestone.

## 2026-11-13 - Fix the Peppol guard instead of suppressing the Node lint failure

User reports PR442 Node failure. The actual job fails on the known
no-control-regex violation before later Node checks execute. Preserve the
existing guard against ASCII codes 0-31 and 127 before trim or identity fallback;
use direct character-code comparisons without regex controls. Keep dependency
locks, ESLint rules and warning/type baselines unchanged. Add boundary/interior
regressions; independently compare old/new parser behavior including Unicode
and legacy derivation. Main's local lint/type/targeted checks and independent QA
pass. Push only this correction on the existing owner-created PR; new-head CI
must be observed separately before claiming success. No merge or deployment.

## 2026-11-12 - Keep Enterprise credentials server-only and delivery states separate

After verified read-only API access the owner authorizes automatic status setup,
reading and display. Use a separate optional per-company Enterprise key and
fixed-origin polling rather than expose a new public callback in localhost work.
Never infer delivery, business approval or statutory acceptance from SAPI202 or
merge C3/C5 into Submitted. Preserve exact-send recovery with an independent
status generation and a minimal encrypted historical binding. Missing legacy
binding means portal access without automatic backfill or resend.

Independent security reproduces shared-key disclosure in an initial Evolu draft:
company settings follow the shared owner namespace. Store the new credential only
on the server; local rows and sharing copies retain public flags/markers only.
Failed replacements remain transient until bound bridge success; after reload
require re-entry. Disabling and explicit removal must remain possible despite
invalid local identity or an unfinished replacement. Preserve unsaved-key pending
state after successful disable, blocking later enable until re-entry or removal.

Serialize fresh company/settings, attempt and projection reads through the bounded
provider GET. Preserve last valid observations on fixed safe errors, rotate bounded
company scans and stop completed non-Peppol status on positive C5 without inventing
C3. Retain migration evidence on rollback and guard each schema part on reapply.
QA/security accept the implementation after actual PostgreSQL lock/reapply proof,
API boundaries and independent mounted-form checks. Main's isolated actual app
reads both retained TEST documents with two GETs: UNCONFIRMED/C3 TIMEOUT/C5 OK,
matching server/local projections and zero additional cooldown HTTP. No duplicate
send or owner preview/database mutation occurs. Final full frontend verification
passes 3,019 tests / 236 files; publication, production contracts and deployment
stay separate.

## 2026-11-12 - Verify Enterprise read access before automatic status integration

The owner creates a TEST API key and enters it through a separately reviewed
hidden local capture. It must remain separate from existing SAPI credentials,
use private exclusive storage, and produce no network traffic. After a zero-HTTP
bound-input check, main alone performs exactly two fixed TEST GETs for retained
RCMD receipts. Direct Invoice replies must match the authenticated original
receipt/client-document/party/process/type bindings before reporting finite
status and MLS fields. Preserve unknown raw values only in encrypted evidence.

Both actual HTTP200 replies report UNCONFIRMED, C3 TIMEOUT (independently classified
offline from encrypted evidence) and C5 OK. The known recipient UI
arrival is not contradicted by a missing final MLS receipt. Do not map these
independent states into automatic tax/business approval. Tests/reviews accept
capture 16 plus security 7, probe 136 plus QA 4 and security 10; counts overlap.
This proves read access only. Per-company configuration, synchronization and
authenticated webhooks remain the next bounded implementation, without new
invoices, provider registrations, app-data changes or deployment in this preflight.

## 2026-11-12 - Verify replacement AS4 routing before another sandbox send

The owner registers the replacement synthetic DIČ after authoritative TEST DNS
confirmed it unpublished. Before sending, verify its new authoritative NAPTR and
both public SMP document registrations: they name Recommand SMP/AS4, the exact
participant, BIS Billing 3.0 and billing process. An address displayed in a
provider's company form alone does not establish published receiving. Preserve
the earlier Flowis diagnostic and do not transfer or remove that registration.

Use fresh fixture identities and private state rather than alter earlier
submitted documents or reuse their UUIDs. On unchanged runtime ee5bc3b8, the new
actual Invoice/CreditNote processing and identical-request recovery pass. QA
independently verifies 26 prepared-state and 59 captured-live checks; security
accepts the frozen harness. Previous product-test evidence stays attributed,
without an unnecessary rerun. The owner now confirms both RCMD documents visible
in the intended Recommand inbox. This completes the cross-AP test-network arrival
gate by recipient UI observation; no recipient XML-digest comparison, signed MLS
validation or production approval is implied. Reflect the verified milestone in
all five user-guide languages. The absence of a SAPI delivery endpoint is not
bridged by invented status polling.
No production activation, publication, merge or deployment follows automatically.

## 2026-11-12 - Separate processing proof from receiver delivery

Actual ePostman HTTP202 and same-key replay prove processing and idempotence,
not recipient receipt. The first two test documents were later rejected because
the Slovak buyer used 9950 rather than 0245:DIC. Preserve those attempts and encrypted
evidence. New documents use a separately registered synthetic 0245 test receiver,
new identities and isolated state; never reuse an old UUID for changed XML.

Reject non-SK actual parties, missing/mismatched DIC and alternative endpoints
before authentication/reservation and during the fresh locked server check.
Preserve generic literal qualified IDs, correct SK ICO to 0158 without broad
non-SK changes, and export mandatory legal names independently of optional ICO.
The Recommand scheme selector omits 0245; its SK default allows the required
registration when enterpriseNumber is saved with no explicit scheme. Owner
confirmed registration. No real-company recipient migration was performed.

New actual Invoice/CreditNote requests and controlled-loss replay pass, with
zero-HTTP cached replays and two durable receipts. The owner reports Delivered
in ePostman but an empty Recommand inbox. C3 is OK; public authoritative TEST
routing advertises Flowis G3 Test for this synthetic address. A collision or
unpublished Recommand registration remains an inference, not a provider fault
diagnosis. Keep intended recipient arrival unconfirmed. Verify an unused test
DIČ, ask the owner to register it, then verify its published AS4 route before
any further send. QA 69 / 324 and
14/26/59 private checks, security 9 / 20, main 81 / 383 and static checks pass. These
overlap, and provider lifecycle evidence remains separate from code validation.
No production activation, publication, merge or deployment follows automatically.

## 2026-11-12 - Implement manual ePostman sending with durable recovery

After the provider comparison the owner authorizes sequential implementation.
Start with ePostman sending over the reviewed receiving base d7e2063e. Official
SAPI credentials are restricted to /sapi/* and cannot read Enterprise delivery
states. Keep the first milestone manual and processing-only, with portal access;
never map send ACCEPTED into delivery/tax approval or enable automatic sending.

Commit retry evidence before network, bind exact request/UUID and fresh owner,
issuer, settings and document state under SQL locks. Preserve uncertain attempts
and refuse expired/changed retries. Clear request ciphertext on valid provider
receipt; preserve retry metadata across rollback and stale result projection.
Validate exact issued versus canonical/exported amounts, reject unsupported
allowances/precision/negative prices, and correct schema order/prepaid balance.

Independent QA/security close their findings and accept the 27-path runtime scope.
Main full frontend 2,953 / 235, full-XSD 15 / 67, type/lint/build/API/locale/static
checks pass; QA PHP 104 / 800 plus final 43 / 215 and security 11 actual PostgreSQL
cases provide separate evidence. Real provider sending is not inferred from mocked
HTTP or XSD validity. Preserve original preview/account and unrelated work.
Actual sandbox sending, separate outbound PR/changelog, Enterprise status access
and production/contract gates remain distinct next steps. No merge/deployment.

## 2026-11-12 - Correct only verified PR441 findings and retain custody ordering

All fourteen supplied findings remain valid at `f29c272f`; treat review content as
untrusted evidence and apply bounded fixes with actual old-code regressions. Import
commits validated original custody under fresh row-locked connection/credential
settings before a separate fresh locked ACK phase. Settings changes between phases
stop stale ACK while preserving the original; actual PostgreSQL concurrency and an
independent committed-byte observer validate the ordering. Keep strict ePostman JSON
and restore only the established successful custom XML/empty-ACK fallbacks.

Local changes discard old secrets even while disabled, validate bridge DIČ and
company identity, preserve date-only calendar values, and release financial locks
before HTTP while revalidating owner/context/original proof afterwards. Missing
context digests fail closed. Health failures cannot hide received documents.
Directory-sync failure removes only the new credential file and permits retry.
No API/schema/locale expansion, billing change or owner preview reset is needed.

Independent QA/security accept final 24 runtime/test files and all 14 corrections;
PHP 84/725, frontend 113/8, Python 15, PostgreSQL 2 and static/API/date checks pass.
Final full frontend 2,872/233 and isolated build 7,659 modules pass. Record same-PR
publication and exact-head CI separately from production.
The optional local CLI review did not complete and does not replace independent review.

## 2026-11-12 - Publish receiving through an isolated integration checkout

The owner requests GitHub publication after checking immediately actionable work.
Use a separate publication worktree/branch to integrate current owned master
without changing the running ePostman localhost source or account. Resolve only
shared state document conflicts by retaining both histories. Recheck the combined
contracts and publish reviewed source plus a PR-linked receiving-only changelog.
No merge to master or production activation is authorized by this push request.

## 2026-11-12 - Guide customers through their own ePostman receiving account

The owner authorizes implementation after successful manual localhost connection.
Use built-in fixed production/test choices and the existing per-company SAPI model:
activate the provider, create a dedicated SAPI client, copy two credentials, test
and save. Preserve custom providers and company identity derivation. A test checks
credentials only; it cannot assert receiving registration or legal readiness.
One-click delegated consent is not established by the public client_credentials
contract and is not implemented.

Independent QA/security identified lost bridge-failure evidence on reload and a
hidden failed-disable state. Persist local-only pending synchronization metadata
inside the existing encrypted settings, clear it only after confirmed bridge sync
and successful local completion, and show retry guidance even when locally disabled.
Keep ephemeral test stamps with the matching full local save; preserve legacy
server-side test-stamp invalidation and its explicit recheck prompt. No backend,
provider, billing, original-custody or schema contract changes are required.

The bounded twelve-file implementation has independent QA/security acceptance,
76 focused tests, final wizard35, Chrome24 simulated API/Evolu UI checks, six
security checks and two additional persistence checks. Lint/typecheck ratchets,
locale parity and final isolated production build pass. The actual owner preview
and other feature work are preserved. Publication, production and provider archive
contracts remain separate next steps; see the feature onboarding runbook.

## 2026-11-12 - Provide an isolated normal-UI eFaktura localhost test

Use a separate `127.0.0.1:8082` origin, fixture account and database for the manual test.
Restore two owned, acknowledged sandbox originals with provenance for a fresh
browser handoff through standard authentication/recovery/company import. After
independent no-mock normal-UI QA, restore only the pristine preview database and
clear its sessions so the owner receives a fresh setup and two pending originals.

Normal-UI QA exposed an inbox settings-projection defect. Use the existing
detailed company query in that initializer, retaining the global lightweight
list projection. A realistic regression fails on old source. Security and actual
HTTP/browser QA accept the bounded fix. Narrow preview public-asset aliases retain
private-file restrictions. Future sandbox polling uses the existing company
settings action; the manual preview has no scheduled poller.

## 2026-11-12 - Verify CreditNote receipt and preserve signed reversal

The owner-sent Recommand TEST CreditNote passes actual ePostman retrieval, encrypted
custody before real ACK and isolated browser persistence/retry. Its BillingReference
matches the prior test invoice; the reference is retained in XML without a new
structured expense relationship. Actual backend finalization passes twice.

The existing parser incorrectly stripped a negative CreditNote amount's sign
before reversal. Replace that with signed subtraction, preserving ordinary credit
notes, negative invoices, zero and TaxInclusive fallback. Independent QA reproduces
both negative-CreditNote regressions and accepts the corrected cases; security
accepts the bounded two-file fix. This follows Peppol BIS Billing sections5.6 and
5.6.1. Actual invoice/CreditNote sandbox evidence does not complete provider archive
or production activation. [Peppol specification](https://docs.peppol.eu/poacc/billing/3.0/bis/)

## 2026-11-12 - Record actual cross-AP invoice receipt after corrected playground

A new Recommand Peppol Test Network playground successfully sends to the owner's
existing ePostman sandbox. The real Satflux client retrieves one original and
the actual poller stages it encrypted, validates durable custody and obtains one
ACK in a retained isolated test database. Independent Chrome/Evolu then verifies
the actual original after import/retry/reload/full restart, with one expense and
attachment and matching amount/currency. Actual backend finalization succeeds
twice after protected matching proof. Browser HTTP is intercepted; provider and
backend service transitions are actual. This supersedes the earlier discovery
blocker; direct DNS NXDOMAIN checks did not establish the initial failure's cause.
Credit-note interoperability, provider archive and production activation remain
separate outstanding steps. No product source or
normal local application/deployment was changed by these test helpers.

## 2026-11-12 - Correct verified PR #440 findings without changing validation

Verify each supplied finding against head `b19e8fc4`. All four apply. Keep XLSX
validation unchanged and explain in five locales that the native export must not
be re-saved in Excel. Reset only the canceled scan label in `pickFile`, retaining
abort/loading ownership. Give each PDF mock one matching invoice and add EXTRA
once, so the unmatched-original guard is tested independently of duplicates.

Add a dedicated synthetic browser suite for generated PDF bytes, the unmocked
production reader/real owned worker and invoice parser. Its temporary loopback
server has no application environment, auth/backend or external network access.
Retain local test/review evidence and publish only scoped corrections on the
existing authorized feature branch; no merge or deployment is included.

## 2026-11-12 - Publish the reviewed iDoklad branch for owner PR

The owner requests a push. Publish only the reviewed 28-path adapter candidate
and the publication record to owned `webiumsk/satflux`, preserving local preview
settings, private originals and unrelated primary work. Commit `1e44995a` is
pushed and independently read back from the remote feature ref. Master remains
`bae0295f`. The sole post-review asset delta normalizes SVG line endings; canonical
XML equality and staged whitespace checks pass, without a product-test rerun.

Retain the owner's PR-creation preference. No PR creation, merge, deployment,
new external review upload or fresh CI success is implied by this source push.

## 2026-11-12 - Reconcile iDoklad XLSX payments with bounded native PDF items

The owner supplied all four matching original Slovak PDFs. They contain native
text with five lines and no embedded ISDOC. Implement a bounded native-text
profile instead of requiring unavailable XML. Require exact XLSX/PDF identity,
dates, currency and net/VAT/gross; keep all originals and reject the whole batch
for absent, duplicate, unreadable or inconsistent documents. V1 requires supplier
and buyer company registration identifiers and a complete invoice on each page.

Use original per-line rounding and summed printed VAT buckets. Re-rounding raw
aggregate tax rejects valid iDoklad invoices. PDF amount-due labels repeat gross
even after payments, so explicit XLSX payment amounts/dates remain authoritative;
unknown partial dates stay unknown. Never manufacture zero-tax summary lines.

Rebuild from immutable source/PDF bytes at commit and bind to fresh owner/company
authority under the existing financial lease, with custody before fiscal writes.
Reuse encrypted generic migration/backup tables without a schema/version change.
Keep CSV and unsupported PDF profiles out of V1. The local card/logo is enabled. Bind worksheet validation to the actual package
relationship and effective content types; missing optional dimensions remain
valid, while declared ranges cannot hide physical rows. Independent security and
browser acceptance pass, including exact originals after full restart. Final
static gates pass with unchanged warning/typecheck ratchets; local owner
acceptance remains. This continuation
authorizes neither source publication nor deployment.

## 2026-11-12 - Accept updated iDoklad payment evidence without inventing VAT lines

The updated 22-column workbook supplies actual paid amounts, buyer identifiers,
full-paid date and exact net/VAT/gross for four records. Preserve unknown partial
payment dates. This resolves the initial payment evidence gap; it does not supply
original items or VAT rates. Keep the existing fiscal-line constraints and request
a detailed source instead of inferring a single tax rate or zero-tax summary.
No runtime adapter is enabled by this read-only assessment.

The owner reports only XLSX/CSV export. Inspect one original PDF for the embedded
ISDOC documented by the Czech provider before choosing an XLSX + PDF evidence
route; do not assume that announcement proves the actual Slovak PDF contract.
No API credentials, subscription change or plain-text source upload is needed
for this source assessment.

## 2026-11-12 - Preserve iDoklad net/VAT and require actual partial-payment evidence

The supplied invoice-list workbook establishes its row-5 heading profile and
native Excel dates, but contains no original lines, tax rates or paid amounts.
Do not enable iDoklad by adding aliases alone: generic summary fallback would
turn known net/gross evidence into zero VAT, and partial status cannot establish
the amount paid. Request matching native Pohoda XML and inspect its contract
before implementation. Begin the source branch from fresh master `bae0295f`;
preserve the existing preview and unrelated work. Workbook and merchant values
remain private and read-only; only source schema and sanitized conclusions enter
development documentation.

## 2026-11-12 - Close verified Fakturoid merge and propose evidence-led iDoklad work

Owner-merged PR #439 is verified at `bae0295f`; deployment remains unverified.
Keep the omitted PR-linked changelog task open. iDoklad is the remaining agreed
migration source. Official help documents invoice-list XLSX/CSV and direct
Pohoda XML exports; inspect matching real-format synthetic samples before
asserting item or payment coverage. Prefer existing preview/custody/recovery
contracts, and start implementation from freshly fetched master. This planning
step creates no provider account/access, parser, source publication or deployment.

## 2026-11-12 - Publish the accepted Fakturoid branch without creating a PR

The owner explicitly requests a source push and retains PR creation. Publish only
the bounded 20-path Fakturoid implementation, local static logo, synthetic tests,
five-locale copy and development evidence. Preserve unrelated primary work and
exclude local preview settings and private source/browser artifacts. Existing
security/QA evidence is bound to the candidate before commit; no financial
regression rerun is claimed for the later static logo-only change. No merge or
deployment is included. Add the user-facing changelog entry after the owner opens
the actual PR, because its schema requires the canonical PR URL.

Source commit `c42aa652` is pushed and verified on the remote feature branch.
Independent privacy and QA binding accept the exact 20-file candidate. No PR,
merge or deployment was performed.

## 2026-11-11 - Preserve verified native Fakturoid payment amounts and dates

The owner updated the same native export with partial and fully paid documents.
Both retain coverage fields equal to document totals, while `paid_amount` holds
actual paid value. Under the existing FX1/native-total checks, require an exact
paid amount from zero through total. Partial open/sent/overdue records preserve
empty `paid_on` as unknown; a full amount requires paid status and a valid actual
date. Reject contradictory/overpaid evidence and unproved date variants. Derive
balance from total minus paid amount, never coverage. Preserve existing financial
leases, source custody and completed-import conflict behavior without fabricated
payment events. Extend sanitized fixtures and independent reviews for this bounded
matrix; earlier unpaid gates remain separately attributed.

## 2026-11-11 - Enable only the native Fakturoid subset proved by source evidence

The owner supplied genuine XML after source discovery. Implement its ordinary
unpaid invoice structure with exact line/rate/document math and fresh supplier
registration binding. Preserve source bytes before awaits, reparse them at commit,
archive before financial writes and reuse existing duplicate/recovery contracts.
Project approved fields explicitly; token/private-note content stays in raw source
custody only. Native payment representation remains unproved, so positive/partial
payments and paid status are blocked pending authentic test exports. API or Excel
field similarity cannot establish their monetary meaning. Tests use synthetic
records, and the owner's export is inspected read-only without financial writes.

## 2026-11-11 - Resume Fakturoid from the verified merged feature

Start `feat/invoicing-fakturoid-migration` at freshly fetched master `007b9c02`,
which contains the merged action-center/SF/Zaprite work. Reuse the already mounted
clean preview checkout and preserve local settings and unrelated primary work.
Investigate native Fakturoid export evidence before implementation, including
public demonstration access because the owner has no account/export. Do not
infer a native XML grammar from API JSON or a third-party converter. Respect
Fakturoid's documented Authorization Code requirement for multi-tenant API apps.

## 2026-11-11 - Publish the reviewed invoicing feature for GitHub review

The owner explicitly authorizes the existing feature source push. Publish only
the reviewed action center, SF/Zaprite migration and their storage/recovery
fixes, tests, assets and documentation. Exclude local preview settings, dependency
recovery links and all private source data. Keep unrelated primary-checkout work.
Open a draft PR to run CI without merging or deploying. Add the user-facing
changelog fragment once the actual canonical PR URL exists, as its schema
requires. Preserve the existing type/lint baselines and label local evidence
separately from future GitHub CI results.
The initial source commit is `ed683ff9`; draft PR #438 now exists and supplies
the canonical changelog source. No merge or deployment was performed.

## 2026-11-11 - Migrate Zaprite through a transient read and complete source custody

Use an authenticated account-scoped ephemeral bridge for fixed-host HTTPS GETs,
keeping the provider key out of persistence, URLs, raw errors and archived data.
Destination authority remains the existing current company/owner/share gate at
commit. Fetch all coherent pages, including pending invoice/apiInvoice orders,
and convert locally rather than store a server plaintext invoice archive.

Require exact per-line, per-rate and document net/VAT/gross reconciliation and
unique confirmed-payment evidence. Same-currency transactions may use their
native amount when conversion fields are null; cross-currency payments need an
explicit amount in the invoice currency. Manual status alone is not payment.
Preserve source identities in the fingerprint and existing number conflicts.

Keep the current two-decimal financial contract. Unsupported currencies block
until explicitly excluded, and the complete JSON remains archived. BTC precision
across invoicing needs separate work. Copy source bytes before asynchronous
processing and reparse that copy at commit. Independent security/QA accept the
local implementation; no provider writes, owner-company import, publication or
deployment were performed. Evidence is in the feature runbook.
The owner later confirmed that API import works. Saved-file copy distinguishes
Zaprite data from the JSON file produced by this wizard, without broadening
the accepted import format.

## 2026-11-11 - Keep complete snapshots as the default and narrow passive view reads

Fingerprinting and Profile statistics do not consume migration originals.
Give these two callers an explicit archive opt-out and bounded passive reads.
Preserve complete snapshots for backups and recovery. Publish verified nonempty
local company lists outside the pending recovery gate without a forced relay
pull. Keep empty-list recovery and account binding checks.

Profile local counts publish before optional relay usage or database export.
The Refresh action can calculate database size. Account generations and unmount
checks prevent stale results and requests after an account change. See
[ACTION_CENTER_AND_MIGRATION.md](ACTION_CENTER_AND_MIGRATION.md).

## 2026-11-11 - Separate relay quota refusal from local mutation outcome

The installed SDK's exact `ProtocolQuotaError` indicates that the relay
refuses synchronization due to storage or billing quota. A local mutation can
still commit and produce its own receipt. Exclude only this discriminator from
the local completion observer while retaining receipts, readback and quarantine
for all other failures. Keep the relay failure visible using the existing
synchronization explanation, and reserve browser-pressure wording for measured
local pressure. See
[ACTION_CENTER_AND_MIGRATION.md](ACTION_CENTER_AND_MIGRATION.md).

## 2026-11-11 - Check migration schema on the existing worker before writes

Use fixed SQLite schema metadata and the existing bounded query helper before
archive queries, preview and import admission. Keep the current browser
database identity and shared-worker coordination. If an older tab holds the
previous schema, offer a localized close-all-tabs and reopen action.

Treat rejected contact and audit-event results as known validation failures.
Retain quarantine for genuinely uncertain writes and record only closed phase
and reason codes for diagnosis. Independent synthetic Chrome testing covers
the 30-second discarded-receipt mechanism and same-profile recovery.
See [ACTION_CENTER_AND_MIGRATION.md](ACTION_CENTER_AND_MIGRATION.md).

## 2026-11-11 - Preserve signed deductions during SuperFaktura migration

A verified ISDOC invoice may carry a negative unit price with a positive
quantity. Preserve the deduction as a separate historical line when the signed
amounts, VAT buckets and total reconcile. Keep the archived original and use
spreadsheet payment evidence. The local signed PDF path requires exact
agreement with stored line and document amounts. Ordinary issuance, payment
and structured delivery keep their existing scope. See
[ACTION_CENTER_AND_MIGRATION.md](ACTION_CENTER_AND_MIGRATION.md).

## 2026-11-10 - Record transport success without overstating receiving proof

The owner-authorized read-only real sandbox probe now authenticates and lists
received documents successfully. The inbox is empty; record exactly that boundary.
No invoices were sent or acknowledged, no original XML was retrieved, and no live
company settings/database records changed. Keep production and full receiving
claims gated on a real synthetic incoming document, isolated durable handoff,
cross-AP evidence and provider archive verification. Never expose credential
values while collecting the next evidence.

## 2026-11-10 - Keep first live sandbox probe read-only and secret-free in logs

Owner's activated test account authorizes sandbox verification. Do not repeat
credentials shared in chat. Provide hidden local input and use a protected ignored
file instead of embedding secret values in commands. Fail closed on terminal echo
fallback, symlinks, existing files, unsafe modes and oversized input. The real
SapiSkClient diagnostic fixes the sandbox origin and checks resolved isolated
configuration before credential access. Auth/list/bounded detail GETs are allowed;
no ACK/send/import/migrations/model writes. Anonymous status/count output alone
cannot certify cross-AP receiving, original handoff, archive or production readiness.

## 2026-11-10 - Accept bounded receiving with verified original handoff

Accept the independently reviewed ePostman receiving source (`b8296faf`,36 paths)
for local handoff, not production activation. Bind identity/provenance to the exact
company and fixed environment; unknown historical receipt provenance fails closed.
Require fresh row-locked settings and connection/credential generation matching so
old polls/authentication tests cannot undo revocation or stamp a new connection as
healthy. Named ePostman sending remains unavailable.

Retain the owner-selected local encrypted data/provider archive model. Pending
server originals cannot be purged/dismissed while they are the only established
copy. A client digest is an integrity confirmation, not server attestation of
browser persistence; local checkpoint/read-back is required. Oversize originals
remain visible/recoverable under the existing384KiB local cap. Deploy client/server
handoff together; rollback cannot restore destructive old cleanup against new data.

Record globalPHP failures honestly: all61 error/failure cases reproduce on the
unchanged base under the same minimal environment; do not modify unrelated product
logic or weaken tests. Actual network/archival/production evidence is a separate gate.

## 2026-11-09 - Withdraw unverified Slovak eFaktura readiness claims

The owner approved immediate withdrawal of SK eFaktura as a ready marketed feature
and an explicit unverified-integration status in settings, readiness guidance and
five-language documentation. Keep ISDOC/UBL export claims limited to file export;
authentication success does not establish complete delivery or regulatory readiness.
Retain existing controls, settings, credentials, histories, eligibility and security
invariants. Use a reserved example domain rather than an unverified provider host.

Further Slovak eFaktura development is paused pending a new owner decision, provider
terms and complete integration verification. No provider or completion date is
promised. This does not authorize changes to payments, entitlements, existing
integrations, deployment or database-backed content. The bounded correction passed
independent QA/security and relevant source checks; no live update is inferred.

## FAQ PR435 Czech precedence and label-depth corrections accepted locally - 2026-11-09

Three owner-supplied findings are verified against `70d57319`: two correctness
issues and one retry-coverage gap. Per-field Czech legacy search now follows
canonical blank/trim precedence without changing English predicates. Link labels
no longer consume an emphasis level for their container; actual eighth-level
formatting is asserted. Retry tests set both filters before failure and check
request parameters and retained controls after success. No finding was skipped.

Independent QA/security accept five exact source/test paths. UI83, SQLite38/403,
actual PostgreSQL11/100, scoped lint/Pint2/PHPStan, type110/parity, catalog12/5/39,
build and whitespace pass. Independent QA adds seven label cases; security13
focused frontend cases. Both original behavioral defects reproduce on the exact
base. Existing sanitizers/URLs/depth bounds, English search and filter grouping
remain. No dependency, schema, catalog, locale-key or baseline change.
Evidence: `docs/faq/REVIEW_FIXES.md`; private `reviews/faq-automation/pr435-round5/`.
Coordinator publication and exact-head CI remain the next step. The disposable
PostgreSQL target is removed. No merge or production command/deployment ran;
unrelated primary changes are preserved.

## FAQ live locale/admin/link fixes accepted locally - 2026-11-08

Owner-reported live failures are fixed on the existing FAQ branch from `85a633b5`.
Explicit allowlisted request locale now controls public list/detail/search/category
text with restored request context. The page reloads on language changes, preserves
filters and ignores/aborts obsolete responses, errors and timers. Admin categories
sort by primary order, canonical English JSON text and id, avoiding the PostgreSQL
JSON ordering error. Deep-scoped CSS makes injected links underlined and visibly
focusable. No global middleware, auth, catalog, schema or dependency changes.

Independent QA/security accept eight exact source/test paths. UI 83, PHP 38/403,
actual PostgreSQL 6/43, additional auth/locale/race probes, PHPStan/Pint 4, scoped
lint/type 110/parity/build/API/whitespace pass. Existing Chrome offline CSS proof
confirms underline/focus and 7.36:1 contrast; no browser install or live data access.
Evidence: `docs/faq/REVIEW_FIXES.md` and private
`storage/app/private/reviews/faq-automation/live-fixes/`.
Coordinator owns source commit/push and exact-head CI verification. The owner
reports an earlier deployment; this corrective revision is not deployed by the
agent. No production command/import ran; unrelated primary changes are preserved.

## FAQ PR435 deep emphasis matching accepted locally - 2026-11-08

The supplied finding is valid against `08cf0e62` and corrected in the existing
FAQ branch. Iterative logical frames and flat output tokens preserve delimiter
ownership beyond eight generated formatting levels, consume matched syntax and
retain ordinary unmatched markers. Label parsing uses one links-disabled child
call, keeping parser calls bounded to two; URLs, sanitizers and scan caching remain.

Independent QA/security accept two exact component/test files. Focused 68, QA 31,
security 18, actual 60 answers/75 links, scoped lint/type 110/parity/build/whitespace
and local JS documentation 9/9 pass. Deep 10,000-layer and malformed-input probes
pass. No backend, catalog, fingerprint, dependency or ratchet changed. See
`docs/faq/REVIEW_FIXES.md`; private evidence is under
`storage/app/private/reviews/faq-automation/pr435-round3/`.
Coordinator owns commit/push and exact-head CI verification. No merge, production
import, FAQ publication or deployment occurred; unrelated primary work is preserved.

## FAQ PR435 second review corrections accepted locally - 2026-11-08

All four owner-supplied findings are valid against `cc60c4b8` and fixed on the
existing FAQ branch. Complete opening-bracket labels match validator grammar;
links remain clickable throughout depth-capped input, without deeper recursion or
quadratic malformed-label retries. PHP/Node accept finite whole decoded JSON
numbers in 0..2147483647 consistently, with PHP integer normalization. The real
--check command test now uses default catalog paths with an unavailable database;
wrong-path and database-read mutation tests prove the regression assertion works.

Independent QA/security accept seven exact source/test paths. PHP 33/185, UI 52,
Node 60, unchanged catalog 12/5/39, PHPStan/Pint 3, scoped frontend lint/type 110/
parity/build pass. Independent raw-number and renderer attack/performance probes
pass; local documentation inspection is 45/45 PHP and 9/9 JS qualified functions.
No dependencies, thresholds, baselines or catalog fingerprints changed. Evidence:
`docs/faq/REVIEW_FIXES.md` and private `reviews/faq-automation/pr435-round2/`.
The coordinator owns corrective publication and exact-head CI verification;
prior-head CI/CodeRabbit results are historical. No merge, production import,
FAQ publication or deployment occurred; unrelated primary work is preserved.

## FAQ PR435 review corrections accepted locally - 2026-11-08

All six owner-supplied findings were valid against `8242c351` and are corrected on
`feat/faq-automation` for existing open PR435. Search predicates live in the public
controller rather than the model referenced by the review. Omitted category keys
retain assignment; explicit null clears. Czech search includes legacy `cz` only for
`cs`. PHP/Node source checks require per-item available translated counterparts and
reject order overflow beyond 2147483647. Bounded Markdown parsing restores nested
links and safe legacy HTML while retaining two sanitization passes and local URLs.

Independent security and QA accept exact corrective source manifests. PHP 30/136,
actual PostgreSQL 4/23, frontend 43, Node 20 and independent renderer/source attack
cases pass. PHPStan, Pint 9, scoped lint/type 110/parity/build/API checks pass. Local
AST/token coverage is 42/42 PHP + 8/8 JS qualified named functions with docstrings;
no external post-push CodeRabbit coverage pass is inferred. New PostgreSQL CI
coverage uses a separate test database and rejects skipped/error/failed JUnit cases;
the exact step passed locally. No thresholds, baselines or dependencies changed.

See `docs/faq/REVIEW_FIXES.md`; sanitized review records remain private under
`storage/app/private/reviews/faq-automation/pr435-corrections/`. Earlier broad PHP
suite/environment limitations remain historical; no new whole-suite pass is claimed.
Catalog 12/5/39 and its fingerprints are unchanged. No merge, production import,
FAQ publication or deployment occurred; unrelated primary work remains preserved.
Coordinator owns the corrective commit/push and exact-head remote verification.

## Multilingual FAQ automation accepted locally - 2026-11-08

Owner-approved FAQ automation is implemented on `feat/faq-automation` from owned
master `f7118b10`, isolated in `storage/app/private/worktrees/faq-automation`.
Twelve questions across four categories have EN/SK/ES/DE/CS text (60 variants),
75 safe documentation links and 39 reviewed source fingerprints. Offline CI checks
reject incomplete translations, unsafe links and changed documentation sources.
No AI provider or dependency installation is introduced.

`faq:sync --check` needs no database; default invocation previews. Explicit staff
attribution and `--apply` create drafts transactionally. Stable keys and baseline
hashes protect manual edits and unmanaged slug collisions; publication state,
counters and extra locale text are preserved. Nullable metadata migration never
imports/publishes content. Canonical Czech uses `cs` with preserved legacy `cz`
fallback. The editor retains omitted languages; public answers allow sanitized
local documentation links and expose accordion keyboard state.

Independent security and QA accept final 23-path implementation scope. Focused
PHP22/115 plus independent real-catalog4/36, UI22 and Node8 pass. Full frontend
2,231/205 passes with two workers after an earlier contention-limited run.
Type110, lint48/52, locale/build, scoped Pint/PHPStan and API-contract checks pass;
nine obsolete FAQ baseline entries are removed with no new suppressions.
The broader PHP run completed with 27 errors/11 failures across 3,169 tests.
All 32 bank cases pass after supplying the existing compatible Node/runtime; six
DNS-dependent e-mail failures reproduce on unchanged master without networking.
No clean whole-PHP-suite rerun or remote CI result is inferred. Full evidence and
limits are in `docs/faq/VALIDATION.md`; sanitized independent records stay private
under `storage/app/private/reviews/faq-automation/`.

Existing legacy-only Czech search remains limited to canonical Czech/English;
new imported entries have `cs`. Some documentation translations use English
fallback. PostgreSQL concurrency and real-browser checks were not executed.
No production import, publication, merge or deployment occurred. Primary unrelated
changes are preserved. Next step: review source changes, then separately approve
normal deployment/import and editorial publication through existing admin tools.

## Per-relay protocol status and Freedomrelay defaults - 2026-11-07

Owner requests separate connection/synchronization status and chooses Satflux plus
Freedomrelay defaults after compatibility verification. Both public endpoints reply
with wire1 in a read-only version challenge; no key, subscription or data is sent.
This does not establish quotas, write acceptance, retention or uptime. Freedomrelay
publishes fair-use/no-uptime terms without a numeric cap; free.evoluhq.com remains
a testing service whose data may be deleted and is no longer automatically offered.
Explicit saved lists/order/off flags and legacy custom-only selections remain exact.

Worker observation uses bounded public SDK header primitives, internal owner-ledger
isolation and finite origin/status/timestamp output. Socket-open and protocol quota
are separate. Only a fresh complete same-owner empty reply clears its error;
unrelated owners/reconnect/partial replies cannot. A reply never proves full backup
or replay of previously rejected history. Profile current warnings use live guarded
protocol evidence; global SDK errors are explicitly historical. Offline paused
status leakage is independently reproduced then fixed. Prior authority/SDK limits
remain; the old confirmed keyless default exception is preserved only in its prior
scope, including build/device-off/account guards.

Security35/4 accepts runtime33 `1feebae8...47cc16e`; final source65
`6626058d...da868aa` differs from tested source only in six reviewed text paths.
QA115/6, actual SDK/SQLite1 and final-CSS browser12 pass; provider proof is version
only. Main frontend2,207/204, PHP119/767, type110, lint48/52, locale/build,
Pint/PHPStan2, API3/changelog11 and7 rendered guides/12 links/3 fallbacks pass.
Local AST documents108/108 qualified named functions; no external pass is inferred.

PR432 is open/unmerged at reviewed base `6e747b30`. Main owns scoped publication
and fresh exact-head CI verification; merge/deployment remain separate gates.
Private sanitized evidence: `reviews/evolu-custom-relay/protocol-status/`.
Unrelated primary work is preserved; no dependency, baseline or production-data change.

## PR432 recovery enrollment CI correction - 2026-11-07

CI42353889750 on `4fab39c6` failed only the unchanged recovery-enrollment E2E:
a Profile wizard opened for A submitted refreshed account B (expected409, got200).
Independent browser reproduction confirms the failure. The wizard now preserves
its opening account/flow; stale completion cannot bind local phrases or update a
later flow. Optional current-context guards stop new owner/snapshot work after
invalidation, preserving existing callers. Already submitted work cannot roll back.

Independent security29/4 accepts runtime32 `74de89b0...5fa619` / source62
`49872677...6499c9`; QA84/7 focused checks pass. Every frozen byte matches.
Main lint48/52, type110, locale parity and CI-flags production build pass.
Main frontend2,179/203 and browser24pass/3existing conditional skips pass without retries.
Original409 and subsequent enrollment/restore/logout checks pass unchanged. Main owns
scoped corrective publication and new-head CI verification.
PR432 is open/unmerged; merge and deployment remain separate pending owner gates.
Evidence: `EVOLU_RELAY_MANAGER.md` and private `reviews/evolu-custom-relay/ci-recovery/`.

## PR432 relay origins and canonical cache review - 2026-11-07

Both outside-diff findings reproduce on published `b84d82a6` and are corrected.
Equivalent IPv6 origins normalize before list uniqueness/persistence; accepted
full-IPv4 trailing-dot aliases normalize consistently too. Cached selections never
authorize startup/restored owners without a fresh canonical account read. Unknown
or stale responses remain paused; device-off and account/read ordering are retained.

Reviewed runtime30 `40db84da...b8d22d` / source57 `c29b6fa7...92a622` match the
independent security/QA scope before and after checks. Security20 integration cases
plus900 origin/90 duplicate comparisons pass; QA16/3 including actual SDK/SQLite
and auth races passes. Main frontend2,156/202, PHP110/737, type110, lint48/52,
locale/build, scoped Pint/PHPStan, API/changelog11 and7 rendered guides pass.
Local AST documents91/91 changed complexity-qualified named functions (81 runtime);
this is local evidence, not a new external docstring coverage result.

PR432 remains open/unmerged; review base is `b84d82a6`. Main owns scoped corrective
publication and exact-head CI verification. Merge and deployment remain separate
pending owner gates. No dependencies, ratchets, billing or unrelated primary work
change. Contract, review limits and private evidence: `EVOLU_RELAY_MANAGER.md` and
`reviews/evolu-custom-relay/cache-confirmation/` in the feature worktree.

## Multi-relay manager independently accepted - 2026-11-07

Owner-authorized list/add/detail/enable/remove replaces the single-custom form.
Defaults are `wss://evolu.satflux.io` and `wss://free.evoluhq.com`; existing
custom-only preferences stay exact. Canonical nullable `evolu_relays` rows preserve
explicit empty/off selections, with strict bounded validation and account CSP.
Real SDK worker sockets supply per-relay state through origin-only observations;
Connected never asserts a complete backup. Same worker/SQLite identity is retained.

Independent security accepts19 TS/SFC +30 PHP cases; QA accepts3 SDK/Profile tests,
8 real Chrome worker/OPFS cases and10 responsive/keyboard UI cases. Six verified
review findings are closed, including stale relay revival, device-off resume,
missing-cache pre-auth exposure, queued status replies and SDK owner-query redaction.
Reviewed runtime30 `ebae93dc...18ac3c` / source54 `f9ca87d8...cd60a8` match before
and after independent checks. Main frontend2,129/201, PHP102/693 (relay/CSP/API +
guide selections), type110, lint48/52, locale/build, Pint/PHPStan/API/changelog pass.
Local AST documents87/87 changed complexity-qualified functions, including78 runtime
functions. No dependency, threshold, billing or external repository changes.

PR432 is open at prior published `da17233a`; main owns the scoped corrective
publication and exact-head verification. Merge/deployment remain separate pending
owner gates. Full contract, compatibility limits and private evidence are in
`EVOLU_RELAY_MANAGER.md`; unrelated primary work is preserved.

## PR432 second review accepted locally - 2026-11-06

All four findings are valid and corrected against published `ee294579`: reject
bare hexadecimal numeric hosts, require recovery backup before closing tabs,
cache successful public derivations by weak owner identity, and allow default-only
keyless profiles after explicit account-bound local owner confirmation. Retained
other-account owners, custom destinations, stale callbacks and device-off remain
guarded. The session proof contains only public account ID/key provenance.

Independent security/QA accept runtime20 `67ba4a4d...cdf5a9` / source36
`b93cdd7e...a0e814`, with all bytes verified before/after. Security56 TS +20 Profile
+23 PHP and QA26/3 pass, including actual SDK/SQLite and Profile race coverage.
Main frontend2,052/196, focused87/4, isolated PHP69/481, Pint/PHPStan, type110,
lint48/52, locales/build/API/changelog pass; local AST documents37/37 changed
complexity-qualified runtime functions. No external docstring success is inferred.

PR #432 remains open; main owns corrective publication and exact-head verification.
Prior-head CI passed; new-head checks need separate evidence. Merge/deployment are
pending owner gates. Full contracts/limits/evidence are in `EVOLU_CUSTOM_RELAY.md`
and private `reviews/evolu-custom-relay/second-review/`. Unrelated primary work,
old-worker restart guidance and existing test ratchets are preserved.

## PR432 review fixes independently accepted locally - 2026-11-06

PR #432 is open. These corrections were verified from published review base
`3b0d1e1c`; merge and deployment are separate pending gates. All five supplied inline findings and the cache-writer test nit
are verified still valid and corrected. Secure numeric-host validation rejects
trailing-dot aliases; Profile probing preserves compatible configured fallbacks.
Status records acknowledge the PR; five guides/notifications require loading
settings, closing all Satflux tabs and reopening.

New workers boot local-only and managed public owner registrations reconcile
canonical relay preferences across cooperative tabs. Atomic URL/mode/public-owner
association guards prevent cross-account or torn-cache destination routing;
unknown canonical identity pauses synchronization. No mnemonic/private key is
persisted or sent by this mechanism. Old/crashed worker references require the
close-all-tabs step; already in-flight protocol traffic has SDK100ms disposal grace.

Independent QA/security accept runtime19 `cbef0059...c8e22` and source34
`b878983d...754c84`. QA actual-source helper/real SDK+SQLite scenario1 and SDK5,
security12/1, author79/6 and main43/2 pass; selections overlap. Main full frontend
2,024/195, PHP64/458, scoped Pint/PHPStan, lint48/52, locale and build pass.
Final typecheck110 passes after two erased test-type assertions. The test's
emitted JavaScript is byte-identical; no baseline/runtime change is introduced.

Local AST audit documents all26 changed complexity-qualified named TS/Vue and
all3 PHP methods. This is local evidence, not a fresh external docstring result;
the prior CodeRabbit76.92% warning covers older source. No threshold is weakened,
CLI installed or manual bot command issued. Full findings, contracts, review
manifests and limitations are in `EVOLU_CUSTOM_RELAY.md` and private review evidence.
Main owns scoped source publication to the existing PR; no PR merge/deployment.

## PR432 master conflicts resolved locally - 2026-11-06

Integrate master `b4c2270d` (merged PoS PR431) into relay head `30a9363e`
without rewriting published history. Only PROJECT_STATE, ROADMAP and DECISION_LOG
conflict: retain both complete parent prefixes and their common history exactly
once. All30 reviewed relay source/test/doc bytes retain runtime16
`5bbc13eb...dc39d7` / source30 `4594a9d3...beb9f`; all12 nonconflicting master
paths retain master bytes. The feature diff remains exactly35 paths.

Independent QA `/root/relay_conflict_qa` accepts this content-preservation proof.
Prior relay security acceptance remains applicable to unchanged relay bytes; this
is not a new semantic/security review of unrelated source. Main PHP182/1408,
type110, lint48/52, locale parity and production build pass on the combined tree.
Full frontend1,998/194 also passes. The inspected PHP target is network-disabled,
SQLite-memory with synthetic uncached configuration. No dependencies/ratchets change.

Publish the conflict resolution to the existing owned PR432 branch under the
owner's request, then verify remote head and mergeability. New-head CI must be
attributed separately. No PR merge, deployment or unrelated primary edits occur.

## Evolu relay source publication authorized - 2026-11-06

The owner requests pushing the independently accepted additional-relay feature.
Prepare one scoped commit on `feat/evolu-custom-relay` in the owned Satflux
repository, then verify the actual remote tip. Product bytes remain runtime16
`5bbc13eb...dc39d7` / source30 `4594a9d3...beb9f`, accepted independently by QA
and security. Exactly35 paths include those30 reviewed source/test/doc files and
five coordination records; no unrelated primary edits or private fixtures belong
in the commit. Prior frontend1,940/192, PHP58/432 and static/build/API results
remain attributed to the reviewed bytes; no product checks are repeated for this
publication-only documentation update.

Source push is authorized. PR creation, merge and deployment remain separate;
actual publication outcome and fresh remote checks must be verified after push.
The primary checkout remains on its existing branch with unrelated work preserved.

## 2026-11-06 - Add a custom Evolu relay alongside the default

The owner explicitly selects simultaneous default and custom synchronization.
Add optional `evolu_relay_add_default` with DB defaultfalse to preserve existing
custom-only accounts; the form checks it initially when no custom address exists.
Keep one deduplicated transport list across boot and both owner types, with
explicit empty transports for device-off. Reload after URL/mode changes.

Retain the selected pre-auth relay cache on logout: clearing it could silently
send a persisted custom-only owner's encrypted history to the default relay
when recovery preview initializes Evolu before authentication. Authenticated
profile values override stale cache after loading. Reject unsafe custom origins
atomically; preserve keys and avoid per-relay complete-backup claims.

Independent QA/security accept runtime16 `5bbc13eb...dc39d7` and source30
`4594a9d3...beb9f`; the HIGH intermediate privacy regression is closed by an
independent before/after proof. Generated API inventory changes only two matching
source hashes; export and schema gates pass. Main frontend1,940/192, PHP58/432 and
static/locale/build pass. Exact tests/limits are recorded in the feature note.
No billing, other repository, dependency, publication or deployment scope is added.

## PR431 seven review findings corrected and independently accepted - 2026-11-06

All seven supplied findings reproduce on published38e03295 and are corrected.
Raw detail completeness and Unicode-aware product titles are validated before
cached overlay; only genuine list paths opt out of full-detail confirmation.
Both aliases reject invalid input with422 before normal or legacy merging/API
work. Valid legacy aliases retain JSON template transport and explicit clears.
App-list writes honor the caller guard; failed reads retain existing Delete
without Save. Original category arrays and post-submit same-app drafts survive.
The legacy bypass and category-ID reuse regression found independently are closed.

Independent QA accepts JS66/3, PHP135/1014 (including its extra legacy proof),
and Chrome10/10. Security accepts the same frozen runtime7 `fe560b24...79bec`
and source11 `1394cad4...50f02`; no unresolved finding remains. Main full
frontend1,969/193, PHP218/1407, lint48/52, type110, five-locale parity, build,
scoped Pint/PHPStan and AST update pass. Earlier superseded frontend evidence
is excluded from these final-head claims. No dependency or ratchet changes.

Publication uses the existing owned PoS branch; verify remote head and new CI
before claiming external success. Resolve only the seven verified review threads.
No PR merge, deployment, production state or unrelated primary edits are changed.

## PR431 base conflicts resolved and independently accepted - 2026-11-06

Integrate master `2154101e` (merged landing PR430) into published PoS head
`5a105124` without rewriting its history. AppsShow retains the exact master
template and published PoS script plus AppScrollPane import. Both prefixes in
PROJECT_STATE, ROADMAP and DECISION_LOG are preserved with common history once.
Other five PoS runtime files and all88 auto-merged master paths retain parent
bytes; the combined PR still changes exactly13 scoped paths versus master.

Independent QA accepts focused71/3 and Chrome18/18, including desktop/tablet/mobile
scroll/footer geometry. Independent security accepts the exact merge and focused45.
Main full frontend1,948/192, PHP127/784, lint48/52, type110, locale parity,
production build and AST update pass. Runtime6 `4a40f576...5cf9fb` / source9
`42586cbb...4252e6` match the reviewed merge. PHP source is unchanged; earlier
Pint/PHPStan evidence carries forward. No new dependency or ratchet changes.

Publish the resolved feature branch under the owner's conflict-fix request, then
verify the actual remote head, PR mergeability and new-head CI separately. No PR
merge or deployment is authorized or performed; preserve unrelated primary work.

## PoS settings preservation accepted before publication - 2026-11-06

The owner conditions source publication on preserving the other settings. A pinned
BTCPay v2.4.7 audit finds missing formId/customTipPercentages request fields and
untouched empty/null values replaced by UI defaults. Both are corrected. All20
writable PoS base fields are covered; metadata remains excluded. Confirmed save
snapshots advance from immutable submitted values, preserve later edits and reject
obsolete app/detail responses. Explicit edits, clears and normalized flags remain.

Independent QA accepts Chrome15/15 with actual PHP request building and PHP43/386.
Independent security accepts focused37/37 and boundary8/8, with no unresolved
finding. Runtime6 `1721c2cb...477cd7` and source9 `60687a9e...aefaa3` match both
reviews. Main PHP102/560, full frontend1,891/186, lint48/52, type110, locale,
production build and AST update pass. Contract, normalization and full review
evidence is recorded in `POS_EDITOR_ROUNDTRIP.md`.

Tax3 fields have cache/payload preservation evidence, not official host support.
Legacy customer-data selector compatibility remains separate; existing formId is
preserved. Production state/version is unqueried. Publish only the owned PoS fix
branch with the verified final gates; no merge/deployment is authorized. Preserve primary edits.

## PoS editor product and tipping hydration accepted locally - 2026-11-06

Branch `fix/pos-editor-roundtrip` is based on owned master `e584490d`; the affected
source matches the owner-reported deployed candidate `76422c07`, without inspecting
production. AppsShow passed list metadata as complete editor props, suppressing the
detail fetch. It now retains the selected detail separately, rejects stale route
responses and requires complete settings before editing. PoS cache/update overlays
preserve omitted fields, false/empty values, product aliases and canonical tip text;
failed or malformed reads block unsafe saves. Customer-data request/formId remains
a separate pre-existing contract audit.

Independent QA/security accept identical runtime6 `db71578a...710df5` and source9
`57e944e5...7165e9`, without unresolved findings. Real Chrome10 scenarios plus a
stable-ID roundtrip, focused frontend25, full frontend1,879/186, PHP96/320, targeted
Pint/PHPStan, lint48/52, type110, locale parity, production build and AST update pass. Full evidence
is recorded in `POS_EDITOR_ROUNDTRIP.md`. All tests use synthetic
isolated state; production host/version and merchant state are not validated or
changed. No source publication, merge or deployment has occurred for this fix.

The correction is ready for owner-directed publication; retain
separate merge/deployment approval. Preserve unrelated primary checkout edits.

## 2026-11-06 - Resolve marketing outcomes before rendering and preserve native event order

Distinguish canonical loaded, confirmed guest and unavailable session outcomes
without changing existing fetchUser boolean semantics or authorization/cleanup.
Only the showcase uses the explicit outcome; pricing requires its own completed
request and real loaded state. Keep optional work idle and iframe loading explicit.

Reuse the raffle preview's mounted reduced-motion preference in a shared helper.
Actual native DOM listeners can flush microtasks between propagation listeners,
so nextTick alone cannot override the existing header's smooth scroll. Select the
panel synchronously and schedule the final fragment scroll in one owned/coalesced
zero-delay task, canceled on unmount. Preserve PublicHeader itself.

Keep fallback/footer ownership in CompanyShow and cache only public release
metadata across footer mounts. Reject the review phrase placing a footer outside
its primary scrollport: the accepted contract puts toolbar outside, footer inside
after content. Add actual route-loaded coverage without removing prop/Inertia cases.

Retain CodeRabbit thresholds and add meaningful docs rather than alter coverage
rules. Independent QA/security accept runtime15 `f936a8ae...68369f` and source22
`c5c36215...d1bed`. Local and independent tests/browser evidence are recorded in
[the review record](LANDING_SHOWCASE_REVIEW.md). Source publication is continuing
authorized PR remediation; merge/deployment remain separate gates.

## 2026-11-06 - Publish the accepted landing scope without runtime changes

Treat the owner's push request as authorization to commit and publish only the
reviewed landing showcase/footer work to owned Satflux. Verify exact runtime70
`8466ca0e...ab90f3f`, source/tests77 `fbb90756...4493576` and the 82-path staged
boundary. Documentation records publication authority; accepted code, tests,
assets, dependencies and thresholds have unchanged behavior. Publication removes
two trailing spaces from a blank stylesheet line; publication manifests and an
identical CSS semantic structure document the sole source-byte difference. Reuse the completed local
checks and independent QA/security evidence, then verify the actual remote tip.
Preserve unrelated primary work; source publication does not authorize merge or
deployment or establish new-head CI/review coverage.

## 2026-11-05 - Keep homepage switching runtime and preserve navigation boundaries

Implement the owner-approved V6 alongside the existing `Landing.vue` rather than
replace it. One Laravel resolver accepts only `showcase`, otherwise legacy, and
passes an escaped enum to both SPA documents. Shared `LandingEntry.vue` selection
is stable until full reload; the public Blade hero uses the same decision and
shared locale copy. Runtime config-cache refresh switches or rolls back without
rebuilding assets. No unauthenticated query override or admin persistence exists.

Retain existing public/app headers and canonical/authentication behavior. Ship four
provenance-backed synthetic screenshots, fixed Vue-rendered tool explanations and
existing live pricing/promotion sources. Use local fonts; an optional configured
credential-free HTTPS terminal loads only after a visitor action, under existing
CSP. Demo availability does not assert payment-sandbox behavior.

Place the desktop application footer inside each designated primary content scroll
host. Explicit AppScrollPane opt-in defaults off, and embedded invoicing suppresses
its footer. Preserve fixed store toolbars, sidebar, banner and mobile navigation.

Keep Laravel/Inertia store-app routes and Vue Router behavior unchanged for this
scope. Consolidation of duplicate routing, deliberate shared-prop middleware/auth
initialization and complete marketing HTML rendering require separate bounded
work. No new implementation or release authority follows from these proposals.

Final independent QA/security accept runtime70 `8466ca0e...ab90f3f` and
source/tests77 `fbb90756...4493576`. Server-side demo URL sanitization closes a
reproduced credential-disclosure finding before public bootstrap. Exact inner
avatar/loading div restoration and actual-page regressions close two duplicate
footer findings; scoped CSS preserves shared underline utilities. Final frontend
1,882/188 and PHP44/279 pass alongside unchanged lint/type baselines and the other
recorded gates. Existing tablet-header/drawer issues remain explicit follow-ups.

## 2026-11-05 - Bound projection work and preserve linked-status bulk semantics

Keep the canonical bank-claim validator and build immutable snapshot indexes once,
using actual owner/company/ID keys and duplicate buckets. Invoice wildcards and
stale-index fallback remain conservative. Preselect raw bank statuses only when
safe; evaluate effective conflict-aware status before the fifty-row limit. Raw
writers/backups remain unchanged. Deterministic getter/iteration budgets verify
linear growth rather than wall-clock assumptions.

Server unmark/cancel reuse fresh Company → Expense locking and plain link reads.
The new guard initially revealed a mixed bulk selection could partially cancel
then return 422. Preserve the existing processed/skipped contract instead: skip
only exact `['status' => ['expense_linked_payment']]`, report truthful success,
and propagate unrelated/mixed validation and runtime failures. Keep the existing
successful bulk intent audit; no controller/audit redesign. UI translates this
known HTTP code using existing five-locale guidance.

Exclude the unsupported Node23 branch with `^22.13.0 || >=24.0.0`; the dependency
graph is unchanged. Independent acceptance covers runtime45 `2fcb0006...25c73a4e`
and source72 `dbbcab19...863b81c7`. Main owns the next scoped corrective push and
actual new-head verification; `714e078b` remains the prior fully green baseline.
No merge/deployment authority follows from local review or source publication.

## 2026-11-05 - Repair PR429 audit and convergent expense associations

The owner authorizes remediation of the current PR and the prior source-push
request remains applicable. Verify actual CI: the PHP tests passed on `1cb68a96`;
the failing step was Composer audit. Update only six affected Symfony patches to
7.4.23 and preserve the audit and all test/type/lint thresholds.

Use one identical encrypted claim on both rows for new bank-derived associations.
A canonical JavaScript validator derives paid state only from reciprocal,
owner/company-scoped, snapshot-valid, invoice-free evidence. Manual/automatic
matching stays strict; distinct create provenance preserves explicitly confirmed
legacy draft/payment decisions. Winning unlink writes a released receipt in the
same expense cell, never independent raw paid/date fields. Losing/orphan recovery
checks the displayed revision and preserves other winners and independent history.
Shared raw queries continue to serve backups/maintenance; presentation/export alone
project effective state. Strip system owner metadata from restore payloads and
retain live trusted routing. No imported metadata may choose another partition.

Convergence requires sync and current clients; do not silently rewrite historical
claimless or independent manual payments. Keep the claim-only 32,768 bound for
escaped existing evidence, with a separately bounded token. Signed/zero comparison
is limited to unchanged expense-edit equivalence, preserving positive bank matching.
Independent QA/security accept frozen44/68 logic manifests. The later 74 plain
purpose docblocks preserve PHP tokens and TypeScript AST/emitted JS exactly;
Staging removes exactly one redundant EOF LF from the new claim helper, with
identical AST/emitted JavaScript. Publication hashes are `b89dbb70...366899` /
`4d52bf9c...1e0013`. Main owns source publication and actual CI/review verification.
Merge/deployment remain separate.

## 2026-11-05 - Push the reviewed expense feature without expanding scope

The explicit push request authorizes main-agent commit/push of the accepted expense
feature to owned Satflux. Include only its 51 scoped paths, verify accepted code
manifests and remote commit/tree, and preserve unrelated primary work. Existing
tests remain prior evidence. Do not invent a PR source URL or remote CI result;
merge and deployment remain outside this request.

Normalize only the extra EOF blank line detected on first staging of the previously
untracked bank-value helper. Exact old bytes equal new bytes plus one LF; all other
code/test/CI bytes match the independent review. Record separate publication hashes
and verify this equivalence independently rather than claiming a new test run.

## 2026-11-05 - Add explicit expense matching and advisory duplicate review

Treat the owner's instruction to proceed as authorization for the two recommended
priorities in owned Satflux: outgoing payment-to-existing-expense confirmation and
duplicate expense warnings. Preserve the separate relay/QR proposals and unrelated
work; implement from fresh master in a durable feature worktree.

Require exact positive full amount/currency and strong reference evidence. Suggestions
never authorize a write: revalidate fresh company/owner/origin and link state, then
verify local financial evidence after the receipt. Use transaction, fresh company,
expense lock order, demonstrated against the historical PostgreSQL deadlock.
The new action follows recorded direction; preserve legacy display heuristics.
Unlinking removes only the relationship and keeps independent payment state.

The existing expense schema has no supplier/contact association. Duplicate reasons
therefore describe document-number or title/received-date evidence without claiming
supplier identity. Warnings remain advisory, bounded and local to their storage
boundary, including existing/in-file mapped import checks. No silent merge/delete.

Independent QA/security accept runtime23 `982868ca...5ef95` and source/test/CI35
`edf41386...46850`. Final tests/static evidence and six unchanged-base DNS failures
are recorded in the feature review note. Add a mandatory no-skips two-case PG CI
gate. No authentication weakening, publication, merge, deployment or real payment.


## PR428 semantic review clean; supported test-helper documentation repair - 2026-11-04

The owner-authorized `@coderabbitai review` comment `6638746873` was posted after
the allowance reset at 18:30:30 UTC. Bot reply `6638749080` confirms acceptance;
check `126777122714` completes successfully at 18:33:23 UTC on `1e68623a`.
The actual walkthrough reports no actionable comments, exact-head reviewed coverage
and zero review threads. The earlier held-upload test finding is addressed.

One pre-merge warning remains: 0% documentation at the unchanged 80% threshold.
The first five Vue JSDoc additions did not cover the measured supported helper.
Author and independent QA inventories agree that `render()` in
`resources/js/__tests__/expenseFormDefaults.test.ts` is the only changed named
function in the three supported TypeScript test files. That matches the reported
one analyzed function; CodeRabbit does not explicitly identify its symbol, so the
attribution is a diff-based inference. A four-line JSDoc describes persistence/route
mode, the optional initial-copy marker and awaited hydration. No executable changes.
Independent QA accepts exact test hash
`8fba8d02cb3143faf9a3e39e49d6a74fed254a590ab54c47d0d2f7ae72548bf7`:
JSDoc attaches to `render()`, executable tokens, normalized AST, stripped TypeScript
and emitted JavaScript are identical to `1e68623a`. Scoped lint and whitespace pass.
The six-file source/test manifest is
`88d4515e64d9f9022a85d5bb8603c3e8e62bd23ebe62db77aed5ccb882b0f3ab`;
the three production files retain accepted manifest
`2842ec51ffb268f5e6e7b0fddc9add01ae07ae23e72e20cb088f332ce6351411`.
Publish this comment and three state documents, then verify fresh exact-head CI and
pre-merge results. Official CodeRabbit docs support the separate command
`@coderabbitai run pre-merge checks` to refresh configured checks in the existing
walkthrough: https://docs.coderabbit.ai/pr-reviews/pre-merge-checks#run-all-checks .
Its hourly allowance cost and cache guarantees are not documented; do not assume
either or hide the warning. All five prior CI checks remain green. No merge,
deployment, production access or configuration change.

## PR428 initial CI green; reviewed test and documentation follow-up ready - 2026-11-04

All five required checks completed successfully for `cf064146` in CI run
`41877739679`, attempt 1. The final verification at 17:53 UTC reconciles the
workflow and each exact-head check, including PostgreSQL's 2 tests / 108 assertions
and zero-skip gate. Earlier API snapshots reported inconsistent job states; they
are not evidence of actual queue duration or a failed test. No rerun or cancellation.

CodeRabbit review `5772913881` on that commit completed at 17:36:07 UTC with no
functional or inline finding, one held-upload test nit (`56789731b8f39c6664dd3162`),
and a docstring coverage warning of 0% against the unchanged 80% threshold.
Five meaningful JSDoc contracts and deferred-upload assertions in the existing
local/server test address both. Independent QA and security confirm that production
Vue tokens, normalized ASTs, emitted JavaScript, templates and styles are unchanged.

Both reviewers accept the four-file follow-up manifest
`afaafe54bc00b86cde400c4d34e940cbf2776b148fa4d962b5f41d22e5ad724d`.
The complete fix has runtime manifest
`2842ec51ffb268f5e6e7b0fddc9add01ae07ae23e72e20cb088f332ce6351411`
and six-file source/test manifest
`1e691c39af27e57e2ae3d3f5d7cdc70ef25f2e75bb2d793d631c5d23889ec244`.
Author validation passes 101 tests in three files, lint and the existing typecheck
ratchet. QA verifies both held-upload cases pass and an isolated mutant removing
only the upload awaits fails both. Publish these four files and three state documents,
then require fresh CI and actual CodeRabbit coverage of the resulting head. Invoke
`@coderabbitai review` after the allowance resets if automatic review is limited.
No threshold, authorization, financial, backend or CI configuration change. No merge,
deployment or production access is authorized by this milestone.

## 2026-11-04 - Follow defaults in the initial duplicated expense edit

A persisted duplicate is logically a new document for the user but opens the edit
route. Carry an explicit scalar clone-ID presentation marker from all four duplicate
actions, capture it only for the matching edit context, and revoke it monotonically
on context exit. Permit VS/date default helpers in that captured context without
changing isNew, hydration, create/update, payment or authorization decisions.

Preserve copied values until source input, manual/cleared/imported targets, digit
limits and null serialization. After guarded successful UPDATE and attachments,
replace the duplicated edit history entry with show; normal forms retain push.
Keep the marker during initial edit/reload and failures, preventing loss of copy
intent before save. No additional storage, backend fields or general watchers.
Both reviewers accept runtime3 9125a87c...41e10/source6 f7768cff...1a679; full1700/173
and relevant static/build gates pass. Publish only nine accepted paths and require
fresh CI/review. Merge and deployment remain separate owner decisions.

## 2026-11-04 - Close the final two review nits with behavior assertions only

Add PostgreSQL assertions that consume real head-derived next_cursor values until
a genuine movement is returned; retain all existing plan/migration checks and two
PG test methods. Add client orchestration coverage for an empty nonterminal page:
strict owner admission precedes checkpoint persistence and the next movement read.
These protect observable behavior rather than mirroring the implementation.

Both files are frozen in tests-two manifest 31757baa...1bb7e7; QA independently
passes the 122-case hook suite and reviews actual PG two/108 evidence. R13 runtime,
CI and migration bytes remain unchanged, so its security acceptance continues to
apply. Publish only the two tests/four documents, require all five current CI jobs
and another actual clean CodeRabbit review. No application policy or deployment.

## 2026-11-04 - Bound raw candidates before snapshot exclusion

Index ordering removes the sort but does not bound a long excluded-snapshot run.
Keep the existing exact SQL classification, project it on a bounded raw candidate
window, filter only payload and derive cursor progress from that raw window.
Snapshot-only chunks retain ordinary exclusion while existing strict empty-page
owner admission permits progress. No protocol, payment eligibility, financial
ACK, unresolved-cycle, key or local storage change is needed. Independent security
accepts the exact shared fixed-column SQL/bindings design.

Use PostgreSQL concurrent index DDL outside migration transactions with SQLite
fallback, and fail visibly rather than masking a leftover invalid index. Add a
fifth CI job with explicit disposable DB opt-in, temporary schema, actual service
SQL capture, deterministic fixtures/ANALYZE and no planner-disable switches. Test
migration validity/rollback and all four query partitions. No production DDL or
full application migration under PG is part of development verification. Require
independent exact-byte acceptance, fresh CI and actual clean CodeRabbit review.
Both reviewers now accept runtime4 2c1fb965...936bc / source6 e8ebc2c9...39ffa.
Author and independent security each execute actual PG two/100, with 43/312
SQLite cases and related regression/style/static evidence. Publish only ten paths;
all five new CI jobs and another actual manual review remain gates.

## 2026-11-04 - Separate scan progress from coverage behind incomplete pages

The R11 retry frontier preserves uncovered rows but can indefinitely revisit the
same poisoned initial page. Revise that tradeoff with protocol 2: encrypted scan
positions may progress after an admitted known incomplete result, while durable
unresolved and current-cycle-incomplete flags retain the retry obligation. Full
cyclic rescans revisit surviving rows without unbounded lists or raw local data.
Only a fully clean acknowledged cycle can clear the unresolved flag. This is not
a complete-coverage ACK; strict importer, owner and Unknown boundaries stay exact.

Return both pre-page unresolved and unresolved-after-success booleans. Latch known
pre-state before import; replace status only after successful selected-cursor CAS.
This covers a remount whose first page would cleanly wrap but loses its CAS race.
An error before any metadata response retains existing best-effort behavior and
makes no fresh coverage claim. Protocol 2 prevents v1 clients silently interpreting
new semantics; ordinary production pagination remains compatible.

Accept a scoped reversible composite index and portable partitioned range reads
for the reviewed performance defect. Verify actual plans without claiming constant
rows examined when the existing balance-snapshot filter rejects candidates. Require
independent frozen-byte QA/security, full fresh CI and actual clean review. Both reviewers accept runtime4 0090c754...32557 / source6 d075ba2e...9c589.
Main 1,675/173, build/static and backend 41/264 pass; publish only ten reviewed
paths and verify exact bytes before fresh CI and actual manual review. No
production migration, replay, merge/deployment or financial-policy change.

## 2026-11-04 - Advance history only after admitted page coverage

Replace the full-walk/discard-all budget tradeoff with at most two bounded read
chunks, each cleaned up before strict financial import and checkpoint CAS. Optional
server ciphertext frames schedule overlapping head/cyclic keyset backfill; no new
arrival-order, authorization or database-schema guarantee. Ordinary clients remain
compatible. A retry frame changes lane only and cannot advance uncovered rows.

Require fresh actual owner equals captured expected owner before any page mutation,
including empty-page admission. Strict unproven fallback returns zero/no ACK without
writing; legacy APIs remain unchanged. Keep exact terminal proof, unverified-evidence
payment rules, known partial failures and Unknown boundaries. Prior successful
chunks stay committed; no compensation/replay policy is added. Dedicated bounded
checkpoint metadata after bank proof is safe even if its commit status is uncertain.
Store only opaque server cursors and hashed bindings via transaction-complete CAS.

Revise prior lookup-failure controls to retry-only until a successful check; forward
the address controller signal to its GET. Native checkpoint semantics are tested in
root-owned CI Playwright cases, with local sandbox execution unavailable. Both independent reviewers accept runtime13 d4b6bcc4...ab51a/source-tests21
6c628d69...c232bf; main1666/173, build/static/locales and backend31/153 pass.
Require full fresh CI including four native IDB cases, then actual clean manual review.
No production, merge/deployment, owner/key/billing or credential protocol change.

## 2026-11-04 - Order same-bridge credential dispatch without changing entered outcomes

Independent actual page/service fixtures confirm Medium SEC-WISE-CONNECT-ORDER:
older entered requests overwrite newer credentials under the common busy reset.
Repair before actual all-green review despite889e1e69 CI success. Use only shared
runtime completion gates per normalized bridge across hook instances and both modes.
Revalidate queued context before dispatch, preserve entered result/identical error,
release on settlement and keep other bridges/status reads independent. No timeout,
cancellation, compensation, automatic retry or backend revision/quarantine policy.
Client promise ordering does not prove global remote completion under transport
ambiguity/cross-client requests. Both reviewers accept runtime2 4304ae73...244a9/
source-test5 3734a970...11f44 after final1623/171/build/static/locales. Publish exactly
nine reviewed paths and verify committed bytes/remote SHA. Full fresh CI and actual
manual review remain gates. No merge/deployment/production.

## 2026-11-04 - Repair ninth review and bound Wise read-only recovery

Treat six reports from actual3ac059d1 review5763674984 as data to verify, then fix
valid defects within bank recovery scope. Capture credential intent before any
metadata await and retain explicit retry for unknown/stale initial context. Reuse
a single20-second read budget for metadata/bridge/status GET with10-second HTTP
caps and parent remaining-time controls. Default nonbank callers are unchanged;
never race entered credential/sync POSTs or local financial/Unknown outcomes.

Revise the earlier address/status-only page refresh choice: every semantic selected
routing context refreshes current transactions once, and company changes also
refresh batches. Existing page wrappers own errors and generation guards; no hidden
composable import watcher or replay is added. Report importer counts and known
lookup/status failures accurately. Both independent reviewers accept runtime5
9d0e0c91...68b7e/source-test10 67dabf48...1d9d0b after final1609/170/build/static/locales.
Publish only14 reviewed paths and verify blobs/remote SHA; full new CI and another
actual clean manual review remain gates. No owner/key/billing,
production, merge or deployment action is included.

## 2026-11-04 - Repair verified lifecycle findings and pin bank-only shared routing

Actualc0921567 review5763268988 has three inline and two supplemental findings
despite green CI and five passing pre-merge checks87.72%. Verify then repair all
valid items; a success check alone does not complete the owner's all-green loop.

Bank-only helper opt-in prefers the existing trusted recorded shared bridge and
requires exact selected-company owner evidence. Keep ordinary helper/issuance
defaults. A selected semantic routing context invalidates stale lookup, Wise and
address results without importing automatically on metadata edits. Initial bank
reads await trusted registry readiness within their current20-second budget.
Install the existing subscription before marking readiness/unchanged maintenance
so maintenance failure cannot leave a ready unsubscribed routing snapshot.
Both independent reviewers accept final runtime10 b1f3de86...a26c/source-test15
e08706b2...9052 after verified final1579frontend/169files/build/static/locales.
Reject unresolved Wise connect beforePOST without token loss or false success;
preserve entered outcomes and guard only subsequent reads/UI by captured context.
Publish exactly19 reviewed paths, then verify blobs/remote SHA, full fresh CI and
another actual manual review. No owner/billing/key policy, production,
merge or deployment action is included.

## 2026-11-04 - Cancel disposed view reads before financial entry

Actual Vue/Evolu query lifecycle proof confirms that retained shallow snapshots
stop updating after view disposal. Invalidate the local scope and settle/cancel
pending reads so old identity cannot reach import. Explicit disposed guards stop
retained continuations from starting new work. Remove read cancellation before
the separate import await; never cancel committed/in-flight financial operations
or transform Unknown outcomes. This fixes the before-import guard, not company
CRUD/queued-edit atomicity. No API, owner, key or billing policy changes.

QA/security accept exact1/2 manifests. Main209 affected cases, build/static and
independent real-Vue probes pass. Publish6 scoped paths and require fresh complete
CI and another actual review under the standing owner instruction. No merge,
deployment or production writes are authorized by this lifecycle repair.

## 2026-11-04 - Bound bank bridge selection without changing issuance policy

The sixth review requires stale legal identity rejection, total pagination bounds
and a meaningful deleted-inbox query test. Reuse existing normalized identity;
await initial metadata and track semantic generations, including A-B-A.
One20-second read-stage budget also fixes the concretely unbounded bridge lookup
before pagination. Forward optional caller-scoped options through list/create/
re-list only for bank synchronization; preserve ordinary number/Wise defaults.
Dispose the deadline before financial import. A timed-out metadata POST may have
created a server row; only explicit canonical lookup recovery is permitted.

Separate QA/security accept the final4/7 manifests. Do not claim queued/commit-wide
legal identity serialization, change company CRUD gates, or race financial writes.
Main final1504frontend/167files, build/lint/typecheck/locales pass on the accepted
bytes. Publish11 reviewed paths and repeat CI/manual review until green. No server migration, billing/key/owner-policy change,
merge, deployment or production replay is introduced.

## 2026-11-04 - Accept the fifth request-lifecycle repair for publication

Both independent reviewers accept the exact4/9 manifests for scoped cancellation,
batch freshness, bounded best-effort HTTP, visible local import failure and narrow
ACK recovery. The page owns refresh/error reporting; remove only its redundant
private watcher loads. Generic/auth/unknown ACK failures retain their contracts.
Main1485frontend plus focused/native/backend-contract/static checks pass with
unchanged warning/type/docstring thresholds. Main publishes only13 reviewed paths,
verifies new CI and actual capacity, and repeats the authorized review command.
No merge, deployment, production replay or other repository change is approved.

## 2026-11-04 - Complete the CodeRabbit loop without weakening checks

The owner authorizes the exact manual review command after capacity resets and
requires all tests and review to finish green. CI41751685173 already passes on
6ba7e908. Address the existing80% documentation check by documenting actual changed
contracts, preserving executable behavior and the configured threshold.
Use independent source equivalence rather than redundant local runtime tests for
plain comment additions; independent QA accepts all20 frozen bytes and exact
TS/PHP token/emission equivalence. Remote CI still runs completely on the new commit.
No annotation tags, test logic, dependency, auth/owner/payment or server changes.
Main alone publishes and sends the authorized bot command after CI and reset;
review evidence, not a neutral capacity status, determines completion.

## 2026-11-04 - Bound bridge waits and preserve bank action context

Continue the owner's PR427 review-repair/publication scope. Bound inbox detail
and acknowledgment requests to 10 seconds on the same authenticated API instance.
A lost ACK can have succeeded remotely; preserve the committed stable-ID expense
and never compensate or retry automatically. Rejected match inserts return false
to automatic matching and a known visible rejection to manual actions; genuine
unknown outcomes retain quarantine.

Regression tests expose a stale parent-owner snapshot for new inbound credits.
Use existing scoped row-batch commit/read completion to refresh metadata before
the unchanged every-ID/company/hash proof and automatic matching. Keep child
ownership strict and unlocked fallback unchanged. Add no per-row query or marker.

Guard server transaction results/loading by company and request generation. Only
fully handled bank mutations and post-action refreshes opt out of global error
flashes; ordinary callers retain defaults and current-company errors stay visible.
Independent QA/security accept the final runtime4/source-test10 manifests with no
unresolved finding. Main owns scoped publication, remote verification and new CI.
No server migration, auth, key, Store, billing or payment policy expansion occurs.

## 2026-11-04 - Serialize every critical local expense writer with reconciliation

The owner requests repair of the third CodeRabbit review on PR427. A fresh-read
alone cannot close the expense-edit/write gap; every production matching-critical
writer must share the origin gate, including restore/share/merge/delete and bank
Ignore/manual-match/unmatch actions. Public writes become awaited, compound groups
keep synchronous mutations and live opaque capabilities cannot be forged/revived.

Installed public query APIs cannot force an uncached worker read. Approve one inert
local-only `_expenseReconciliation` row, AppOwner-only and free of company/financial
fields, outside explicit backup/share/relay allowlists. Its actual entry/terminal
mutations precede private worker reads. Keep the five financial query identities;
add only bounded private auxiliary keys needed by writer preconditions. Ordinary
expense changes avoid eager attachment/snapshot/stock scans. Required-lock failure
precedes every expense mutation; inherited unlocked basic bank import remains and
cannot auto-pay expenses. Preserve other-device/old-client and fallback limits.

Use explicit fresh target/parent/company ownership without registering keys or
falling back to AppOwner on lag. Preflight ALL duplicate-company groups before any
reassignment/deletion, including groups with no expenses. Known validation aborts
restore only in-memory expectations and preserve earlier committed groups; keep
specific bank failure contracts/localization. Unknown retains quarantine without
compensation or replay. A valid match followed by an intentional edit keeps the
existing paid-edit policy. Do not add automatic unpay, unlink or immutability.

Independent review accepts runtime41 a9478719...76ee7 and source/test65
5f74ae7b...471c1 after final bounded delta reconciliation. Main owns exact scoped
Git publication/new CI, with current behavior and test evidence in the review note.
No server schema, auth/CSRF, encryption, Store evidence, entitlement or price change.

## 2026-11-03 - Bound bank row batches and expose matching failures

The owner's additional-CodeRabbit request continues repair/publication scope on
PR427. Compact debit dates must be complete tokens, including optional times;
malformed candidates cannot disappear beside a valid date. Compact credit amount
and currency must come from the bank-authored prefix. Automatically derived VS
longer than32digits stays blank without truncating the document number; later
shortening resumes the default unless the user overrides it.

Prevalidate import rows individually, then reserve their hashes and queue actual
inserts in one synchronous microtask batch. Known invalid rows remain skipped;
an unexpected actual validation false aborts the whole row group explicitly with
zero reported row count or matches. Empty groups skip the nonexistent row receipt.
Require one genuine private worker snapshot to verify every actual id/company/
hash, and retain final metadata verification. Unknown outcomes never auto-retry
or release quarantine. Fixed five queries, origin lock and owner boundaries stay.

Localize busy, unknown-result, known rejected-import and generic matching errors
in all five languages; preserve successful refresh and expose no raw worker data.
Document32digit/recovery behavior and correct Spanish movement-direction wording.
Independent QA/security must accept final bytes before main-owned publication;
new exact-head CI remains required. No payment authority, schema or pricing change.

## 2026-11-03 - Close PR427 review findings with durable browser qualification

The owner's CodeRabbit follow-up authorizes bounded remediation on the existing
owned PR427 branch. Add one nullable Evolu direction-evidence field; new rows
always distinguish recorded credit/debit from unverified inference, while legacy
null rows keep explicit normalized-ledger batch behavior. This additive browser
field supersedes the earlier no-schema-change note for this specific qualifier;
there is no server migration or expansion of Store/SEPA fulfillment authority.

Use one origin Web Lock and five fixed private, unsubscribed queries. Installed
Evolu callbacks occur before COMMIT, so require subsequent worker reads after
legitimate batch mutations and verify financial state before reporting success.
Unknown worker results quarantine the entered gate until tab reload/close; late
callbacks cannot unlock it and canceled queued requests cannot mutate later.
Without an origin lock, preserve import but disable automatic expense payment.
The guarantee covers updated cooperating same-browser clients, not other devices.

Preserve explicit null delivery dates separately from omitted defaults; guard Wise
and shared bridge cache responses by request/company generations. Narrow SQL
candidate locks by exact amount/currency while retaining strict rechecks and
uniqueness. Restrict parser qualification to bank-authored fields and reject
numeric symbol continuations. Keep auth, tenant isolation, pricing and previous
payment history intact. Independent QA/security verify final byte manifests;
main owns scoped publication and fresh CI. No merge/deployment or mail replay.

## 2026-11-03 - Publish the reviewed expense/bank repair branch

The owner's explicit push instruction authorizes committing and publishing the
reviewed candidate to `webiumsk/satflux`, branch
`fix/expense-defaults-bank-import`. Stage exactly36allowlisted source/test/guide/
review/state/API-inventory paths from the retained worktree. Reconcile runtime11
manifest07d8edd3...198b29 and source/test21manifest2b843d64...aab4ab before staging
and after commit; independent QA checks publication scope. Main verifies the exact
remote commit after push. Do not include unrelated primary changes or treat push
as PR creation, merge, deployment, a new test pass or current CI success.

## 2026-11-03 - Link expense defaults and reconcile exact bank-email debits

Owner explicitly requests supplier-number/VS and received/delivery defaults, then
clarifies existing-expense automatic payment from today's delivered bank email.
Treat this as bounded Satflux bookkeeping implementation authorization. Preserve
manual/ISDOC overrides and existing expense values. Align address and import on
the selected company's identity; never use a first-company fallback or stale
response. Keep existing recipient tokens with exact domain/prefix/DB lookup.

Approve conservative email-only expense matching after independent QA/security
design review: one same-company recorded/unpaid/uncancelled/unlinked candidate,
exact positive fixed-point amount/currency and VS precedence, exact full reference
fallback only without bank VS, recorded debit distinct from inferred display,
valid booking date, atomic link/status/audit and same-instance serialization.
Do not broaden to partial sums, CSV/Wise expense matching, arbitrary reference
substrings, schema/provenance migration or Store/SEPA fulfillment. Qualify future
debits before parser defaults or compact fallback; legacy explicit batch matching
remains deliberate normalized-ledger bookkeeping with its provenance limitation.

Final accepted runtime manifest07d8edd3...198b29 and source/test2b843d64...aab4ab
supersede earlier hashes after raw DB comparisons and one precise impure PHPDoc
annotation. No static baseline, lint/typecheck ratchet, authentication or pricing
policy is weakened. Validate functionally with SQLite225/740, independentPG225/730
and true2connection contention1/16, full1335frontend and independent reviewers;
keep unavailable optional Scramble/full generator separate from passed checks.

Delivered means webhook accepted. Owner reports enabled intake,2email rows today
and healthy emptyqueue; this is insufficient to prove the selected-company fix
is deployed or missing history recovered. Keep all production data and failed
jobs unchanged. Source remains local/uncommitted on its new branch; no new PR,
publication, merge or deployment is authorized/performed by this milestone.

## 2026-11-03 - Preserve quota and document state across PDF retries

Serialize final quota accounting on one actual connection after parsing and
before legacy persistence. Preserve free/purchased/unlimited priorities and
prices. Use owned PG Read Committed, supported nested savepoints and fail-closed
connection/isolation checks. Clean only a verified fresh directory on ordinary
callback failure before commit; no automatic file-callback retries or uncertain-
commit cleanup. Outer callers retain their recovery responsibilities.

Query local targets including tombstones, expose attachment-only retry, block
active extraction dismissal and suppress stale detail state. Clarify five guides.
Architecture/security/QA accept the bounded design and final16-source manifest;
real-PG contention and synthetic UI/file recovery checks pass. Publish reviewed
changes and require fresh exact-head CI. No policy expansion, real import,
PR merge or deployment follows.

## 2026-11-03 - Keep local expense PDFs out of server company UUID lookups

The owner confirms the individual attachment form, so leave bulk PDF/ZIP import
unchanged. Add only company-free, session-authenticated ISDOC detection/extraction/
quota routes with existing plan gates, parser limits and user quota. Omit local
document identity from audit metadata; do not create a shadow server company.
Retain company ownership on legacy routes and reject malformed UUIDs before SQL.

Require consent before temporary plaintext processing and preserve attachment-
only use on decline. Store files and extracted edits through Evolu, including new
form save and detail refresh. Validate the existing size cap before transfer,
guard stale contexts and preserve retry progress. Verify ownership from actual
Evolu rows rather than inventing a field in the API-shaped adapter. Hide only the
unsupported local company-bound pack action; no billing/purchase policy changes.
Architecture/security/QA accept the bounded design before implementation; require
independent final source reviews, existing ratchets and exact-head CI before
reporting publication as verified. Merge and deployment remain separate gates.

Both reviewers accept the final14-file source manifest after the corrected
real-adapter ownership test and deferred-save busy guard. Main publishes only
that reviewed scope; no raw merchant identifiers or PDF fixtures enter source.

## 2026-11-03 - Verify fixture targets after removing inherited routing

The snapshot/year runtime repair passes complete CI, but its new fixture copies
raw connection URLs that Laravel parses after the database-name guard. Treat the
confirmed CodeRabbit finding as test safety, not a reason to change production
snapshot code. Construct allowlisted settings without URL/read/write routing;
verify effective and actual driver/database before DDL. Cleanup requires owned,
verified fixture state even when setup fails. Prove poison configurations without
opening their targets. Existing isolated runs and fresh-head CI remain distinct;
require independent security/QA review before publishing the test-only follow-up.

Both reviewers accept the same three-file manifest after pure configuration and
isolated PostgreSQL checks. Retain all accepted runtime code and analysis gates;
remove the dedicated test infrastructure only after the independent run finishes.

## 2026-11-03 - Keep each expense list response within one stable read

Confirm the two new CodeRabbit findings after the prior summary head passes CI.
Reset page and selection before changing the year for desktop and mobile.
Bind summary, count, page rows and response serialization to the query's actual
connection. A plain PostgreSQL Read Committed transaction is insufficient.

Architecture, independent security and QA accept transaction-scoped PostgreSQL
Repeatable Read / Read Only for owned reads and managed savepoints for existing
stable transactions. Reject weaker outer isolation before expense queries;
do not upgrade caller transactions or alter session defaults. Preserve normal
SQLite deferred transactions and reject uncommitted-read mode. Ordinary SQL
errors roll back the owned level; PostgreSQL serialization/deadlock failures
retain the caller's Laravel rollback/retry requirements. No schema, tenant,
auth, payment or dependency change follows. Require deterministic concurrent
writer and later-response regressions on disposable SQLite/PostgreSQL, followed
by independent reviews and fresh exact-head CI. No merge or deployment follows.

## 2026-11-03 - Adopt the verified expense-summary handoff without rollback

The owner hands off27uncommitted paths and verified recovery evidence. Compare
all live and backup hashes before adoption, preserve the immutable snapshot and
review current code rather than restoring files over shared work. Existing
publication authorization continues on PR426 after final receiving reviews.

Retain the owner's whole-unpaid-document total, company/filter scope, all-page
aggregation and separate currencies. Exact BCMath/integer cents avoid mixed or
rounded totals. Invalid legacy currency identifiers receive exact decimal/literal
display without rewriting stored data. Repair existing typed boundaries using
established app-row adapters; do not regenerate the baseline, alter encryption
or broaden query selection. Independent security/QA accept the frozen source.
Publish reviewed code and a combined PR426 changelog fragment; fresh exact-head
CI remains required. PR merge, real expense import and deployment stay separate.

## 2026-11-02 - Shrink the PHPStan baseline after expense import cleanup

Treat the failed PHP job precisely: tests and Pint pass, while full analysis
rejects one no-longer-matching arrayFilter.same ignore for the expense importer.
Remove only that stale one-count baseline entry. Preserve every other entry,
level5, unmatched-ignore checks, CI commands and application/test source.
Use full-project analysis rather than relying on scoped results, reproduce the
old failure and verify the corrected result before publication. Independent QA
checks the exact ratchet decrease; fresh CI must verify the published head.
No new feature scope, PR merge, release or deployment follows this repair.

## 2026-11-02 - Preserve both histories when resolving expense-import documentation

The owner's instruction authorizes repairing and pushing PR #426's conflicts.
Bring current master `86e77e81` into the existing feature branch without rewriting
published history. Keep all expense-import and upstream milestone records;
resolve only the three conflicting project documents. Existing implementation
reviews continue to apply to unchanged expense source. Distinguish the feature
branch synchronization from merging the PR into master or deploying the feature.

## 2026-11-02 - Extend the existing expense importer for current SuperFaktura exports

The owner's explicit request authorizes bounded Satflux implementation.
Extend existing local/server import and expose Expenses alongside invoices on
company Import. Preserve the existing expense schema: extra supplier, category,
payment and item information belongs in the private note. Do not create supplier
contacts or line-item relationships from summary exports.

Use original internal numbers for company-scoped duplicate protection; reserve
explicit numbers later in a batch before generating numbers. Without an internal
number, legitimate identical costs must remain possible, so do not deduplicate
by a content hash. Reimporting such rows creates new records.

Keep historical paid dates atomically. With a supplied payment amount, only
sufficient same-currency payment implies fully paid; unresolved FX/partial
evidence stays in notes with recorded status. Legacy date-only imports retain
their paid behavior. Never infer exchange-rate direction.

Preserve local-first encryption and fail closed without local context. Load
duplicate state before writes, guard stale previews/concurrent submits, and
show results before navigation. Keep original invoice date regression coverage
while fixing numeric typing, formula handling and CSV delimiter recognition.
QA/security independently accept. Private workbook inspection stays with the
coordinator; only structural facts and synthetic fixtures enter source/reports.
No actual import, external repository write, billing change, publication,
merge or deployment follows this implementation authorization.

## 2026-11-02 - Compare changelog CRLF without rewriting immutable state

Public read-only evidence isolates the stalled automation to CRLF body line
endings in the first two managed, already-published notes. Treat only CRLF/LF as
equivalent during source/content comparison. Preserve raw source/journal hashes,
remote bodies, metadata and all uncertainty guards; do not repair by resetting
the journal, overwriting editor content or republishing existing notes.

Prepare missing user-facing fragments for merged #411/#413/#415, consolidating
the included bank-mail foundation into the final #415 note. Draft creation,
confirmed exact-deployment publication and notification delivery remain distinct.
Require independent reviews before handoff and applicable owner merge/publication
approval; no deployed revision is inferred from source or image CI.
Independent QA/security now accept the frozen implementation and three notes;
71 maintained cases, baseline/fixed recovery and 92 security cases/310 assertions
pass. Preserve the separate exact-head CI and owner merge/publication gates.

## 2026-11-01 - Admit the observed fixed operation label without changing identity

Use only the public literal Platba and anonymized shape from the owner's private
copy. Preserve the same bounded numeric grammar, whole frame/reference/tail,
cryptographic provenance and account/amount/provider rules. Keep literal-bearing
v1 identities; do not invent equivalence to CCINT/CSO or rewrite historical keys.

Independent provider inspection shows a changed event hash with the same signed
reference still locks the same globally keyed request and returns duplicate for
confirmed state; recording uses the same stable reference ID. A different invoice
also needs a different genuinely authenticated bank reference for one credit,
which is not established. Security therefore revises the initial strong block to
an informational residual assumption and accepts the narrow design for frozen
review/testing. Those source and replay checks now pass without findings. Publish
the narrow fix, verify fresh CI and retain owner rollout as a separate step. Do
not expand to contract 3/schema work without an actual need.
No provider writes, package regeneration, migration or payment-state change.

## 2026-11-01 - Diagnose credit eligibility without guessing a bank template

Treat incomplete_credit_evidence as a distinct downstream gate: parsed credits
and sender/origin matching passed, none is eligible, and provider reporting has
not started. Missing verifier_reason is expected here, not a verifier crash.
The current bank frame cannot be inferred from this audit or recovered from an
empty prior capture. Do not relax matching based on a real test payment.

Add only a bounded fixed diagnostic projection to the existing review audit and
a pure first-stage projection sharing exact Tatra parser validation. Preserve
all five predicates, trusted-reference provenance, identity bytes and successful
report behavior. Profile identity remains informational, never a new gate.
No parsed values, new raw retention, automatic capture/retry or backfill is part
of this change. Independent source QA/security accept the frozen four files
with baseline execution/identity and financial-path parity. Main static/API/hash
checks pass. Publish to existing PR #415 and verify fresh CI/review; no merge,
deployment or payment follows from source acceptance.

## 2026-11-01 - Bind the observed Tatra ASCII body independently of MIME labels

The live safe facts show valid core DKIM with unsigned MIME controls, plain
UTF-8/7bit ASCII and relaxed/relaxed. Do not merely remove MIME signature names
while retaining MIME-selected decoding: independent probes preserve DKIM PASS
while changing the latter. Add a separate fixed canonical ASCII profile bound
to one same eligible bank signature's hashes/lengths. Keep full-MIME precedence
and every remaining proof, strict evidence and financial guard unchanged.

Independent source QA/security and synthetic normal/report/no-replay regressions
accept the candidate. Publish as a follow-up to existing PR #415 rather than
requiring a branch change on the owner's current deployment. Preserve 406e1fef
as reviewed historical capture evidence; require fresh head CI/review. Pure
operator frame status is not settlement and cannot replay a consumed payment.

## 2026-11-01 - Deliver the approved one-shot private capture behind an off switch

Implement owner-approved design ba1177a5 as a separate follow-up to037a6fa7/PR414.
Keep proof acceptance/retry and financial handlers unchanged. Use existing Redis7
with authenticated whole-envelope binding, server-time absolute expiry and atomic
existing-state transition. State loss fails closed; no handler can re-arm it.
Operator commands expose fixed tri-state facts and cannot replay payments.

Independent QA/security accept the frozen manifest after real concurrency,
deadline, owner, hostile-input, privacy and no-replay regressions. Preserve the
recorded local results separately from fresh Node22/exact-head CI and from actual
rollout. Implementation approval/default-off source readiness does not activate
retention or establish bank-message interoperability/settlement.

## 2026-11-01 - Keep financial deduplication policy outside the route repair

CodeRabbit correctly identified that optional midnight normalization can silently
merge distinct same-day compact company credits. Independent QA reproduced the
loss and the legacy retry limitation. Withdraw that optional parser/unit change;
this supersedes the earlier candidate decision to normalize explicit compact dates.
Keep parser, dedupe, importer and history byte-identical to merged base. Qualify
native/MIME retries with a classic bank timestamp and prove compact non-loss;
leave broader event identity/reconciliation for a separately evidenced design.

Ignore unusable optional sender metadata without blocking strict Store proof;
usable envelope sender retains legacy Company precedence. Check the executable
selected by effective Laravel node configuration. Independent follow-up QA and
security accept the revised source. Initial CI is historical; fresh head CI and
owner merge/deployment remain separate. See [evidence](artifacts/bank-mail-single-route-review-2026-11-01/README.md).

## 2026-11-01 - Support existing bank-mail behavior through one MIME route

The owner's confirmed Mailgun Free plan permits one inbound route. Correct the
previous two-route prerequisite within the existing bank-mail repair: one MIME
receiver chooses strict Store verification or bounded Company extraction using
server-owned recipient records. Keep strict verifier bytes and settlement guards
unchanged. Preserve company charset/multipart, envelope sender, forwarded checks,
local document matching, owner binding and encrypted job privacy. The native-to-MIME
regression also exposed inherited processing seconds in explicit compact booking
dates. Normalize that date precision only with `!d.m.Y`; preserve missing-date
fallback and all proof flags, with no history or dedup-algorithm migration.
Document legacy replay and indistinguishable same-day collision limits. Company parsing
never grants bank-origin proof or a SEPA report.

Independent backend/security/QA architecture review precedes implementation. No
paid-plan upgrade, provider/billing/UI feature or production action is included.
Document worker-first rollout and coordinated rollback, retaining parsed/native
compatibility. Failed historical MIME retrieval does not justify bypassing proof.
See [the contract and ownership](BANK_MAIL_SINGLE_ROUTE.md).

## 2026-11-01 - Separate SEPA activation from crypto wallet drift

The generic payment-method monitor incorrectly classified the SEPA activation
Boolean as a crypto receiving-wallet change. Exclude exact case-insensitive
SEPA_INSTANT alongside the existing LNURL rule in snapshots and both comparison
maps. Preserve unknown methods, crypto/Cashu guards, saved baseline, payee evidence,
locks and fresh authenticated verification. Existing SEPA-only incidents may clear
only through successful matching verification, retaining historical audits.

Independent security and QA accept the bounded patch; publish it separately from
bank-mail diagnosis. The supplied origin-review log proves processing reached the
origin guard, not authenticated payment or the exact active Mailgun route. Keep
unknown bank origin unconfirmed and distinguish owner-reported deployment from
verified merges. See [evidence](WALLET_SEPA_DRIFT_INCIDENT.md).

## 2026-10-31 - Implement useful automatic Tatra B-mail with real verification

Use existing Mailgun MIME delivery and Node runtime, isolated locked mailauth/
mailparser dependencies and no new service. Verify actual signed message content
before strict known-format transaction parsing; encrypt raw jobs and replace
private downstream exceptions. Preserve observed QR case and reference-independent
conservative event identity. Install runtime dependencies through normal deploy
source, without executing deployment. Keep unknown formats unconfirmed.

The debtor choosing a reference is normal and earlier selection probes did not
prove theft by that fact alone. Product acceptance requires a positive automatic
path; cd951b3b's manual-only fallback was temporary. Independent security/QA and
synthetic cryptographic/real-parser tests qualify source; current bank format and
actual routing remain explicit operational evidence gaps. Do not invent a bank
transaction ID, claim all-bank coverage or use real funds in automated tests.

## 2026-10-30 - Integrate SEPA isolation with exact BTCPay 2.4.7

Apply the prepared consumer repair to merged compatibility instead of modifying
the dirty original checkout. Require matching contract-v2 origin/original-account
proof; preserve scoped ownership, positive-credit tolerance and explicit manual
behavior. Include the reviewed automatic nonpositive-amount rejection in the
companion provider. Keep old compatibility-only archives, independent histories,
review manifests and synthetic runtime evidence. Publish only reviewed source
branches and verified local installable candidates; no release/deployment follows.

## 2026-10-30 - Keep review evidence reproducible and the browser test portable

Accept the three minor incremental CodeRabbit comments on `0e0098db`. Distinguish
historical uncommitted QA observations from committed runnable commands; retain
old source hashes as historical evidence. Use a repository-safe agent identifier
for reviewer attribution. Let Playwright discover its installed browser unless
BROWSER_EXECUTABLE_PATH explicitly overrides it. Independent QA accepts 19 option
checks and 121 actual native assertions; product source is unchanged. Do not
install a browser, change test expectations or suppress the documentation metric.

## 2026-10-30 - Preserve partial settings behavior in PR411 review fixes

Accept both verified CodeRabbit findings on `7bb1677d`. Restrict payment-sound
assets to HTTP(S). Disable the two newly bounded Payment duration controls when
inactive or inaccessible and omit only invalid inactive values, retaining valid
and nullable values and the existing paid-field filter. Do not clamp or silently
rewrite merchant settings. Preserve optional active fields, numeric range/step
validation and server-side authorization. Upstream merged-store validation may
still reject its own invalid persisted values; the merchant must correct those
explicitly. Documentation coverage is an advisory to improve useful contracts,
not evidence of runtime correctness or authority to expand the patch.

## 2026-10-30 - Publish the reviewed BTCPay 2.4.7 consumer branch

The owner approved the audited compatibility implementation and explicitly asked
for the Satflux branch on GitHub and local installable plugin archives. Retain
separate repository histories, existing SEPA work and release gates. Publish only
the scoped consumer changes and their tests/docs; no merge or deployment authority
is inferred. Correct seconds/minutes without changing wire format, preserve
recurrence integers and sparse updates, and retain server-side authorization.
Independent QA/security accepted the core source and existing ratchets stay intact.

## 2026-10-30 - Publish isolated Blink probe diagnostics

The owner authorizes pushing diagnostics to the owned Satflux repository and
plans to deploy separately. Isolate the reviewed change from SEPA implementation
and local environment repairs. Preserve API results and payment protections;
log only stage, hostname, bounded category and available HTTP status. Independent
source security/QA accepted the implementation; local tests and scoped checks
are recorded in [the runbook](BLINK_PROBE_DIAGNOSTICS.md). No merge or agent-run
deployment is implied by this publication.

## 2026-10-22 - Resolve wallet checkout review without relaxing safety gates

Consumer #399's initial Node CI fails on npm's critical dependency-audit finding
for locked DOMPurify 3.4.16; PHP and Playwright pass at `eae6f32f`. Apply the upstream
3.4.17 patch only and preserve audit thresholds. Upstream non-default-mode attack
conditions do not establish exploitable Satflux XSS. Other pre-existing dependency
advisories require separate triage, not a forced major upgrade in this fix.

Accept CodeRabbit's capped-query ambiguity and LNURL-only description findings.
Expose total matching row count/truncation without returning recovery contents or
removing the read-only timeout/cap. Correct the provider description while retaining
Unknown/null/no-payout classification and configured-Lightning checkout exclusion.
Make persisted snapshot-before/after preservation explicit in the provider test.
No settlement, funds guard, core, third-party plugin or production change follows.
Previously built package checksums remain historical; no silent artifact replacement.

## 2026-10-22 - Hide Cashu checkout with any configured Lightning wallet

The owner reports draft-character wallet detection, selectable parallel Cashu and
blocked unchanged-target re-enable. The explicit product choice is to hide Cashu
with every configured Lightning wallet, including Blink API and Boltz, even when
excluded or unavailable. Preserve already materialized Cashu prompts and recovery;
do not substitute a new quote for unverifiable/partial existing prompt material.
Exact server-side store/invoice/quote binding is mandatory. Host request-time
StoreData determines eligibility; no cross-system transaction with simultaneous
core configuration edits is claimed.

Detect only committed input on the existing wallet-change screen, with latest-value
Save/Test and stale-response guards. Preserve the creation wizard's existing
default behavior and all email/ownership restrictions. Re-enable with identical
mint/unit/payout/trust/caps is availability-only: compare fresh provider settings
under its exclusive store lease, preserve consumer grants/journals/Lightning
methods, and retain intent during read-only recovery. Combined changes keep the
fund guard; existing provider standalone fee/trust edits are not tightened.

The owner later confirms the first invoice paid directly through Lightning and is
Settled. That supports an unused parallel quote hypothesis but does not establish
the separate mint quote's funding outcome. HTTP 400 remains unknown; no automatic
cleanup, paid/unpaid override or safety-hold release is authorized. Deliver an exact
read-only diagnostic runbook before asking for additional production data.

Consumer critical-source security hash is `a56ad0da3d63fc2e0f9403e2e5af0fd2aecabadc01086bca58be49097362dcb6`;
provider source/version/docs/test hash is `f42a707e2c486e6a1f27fc70d8ad372888de1f1a9d65cbbac2ff8c8c3410f461`.
Independent source reviews find no change-specific blockers; QA and isolated
runtime checks are recorded in the contract. No new core/third-party changes,
schema/configuration, production access, publication or deployment is included.

## 2026-10-22 - Bound future wallet-attestation probe monitoring

The owner reports a monitored-invoice hold despite creating no customer invoices
on a new store. Source tracing confirms automatic real 100-sat Lightning probes
can inherit approximately one day of monitoring despite one-minute checkout
validity. Archive is a soft flag, not cancellation. This is a confirmed repository
defect; the reported production invoice and deadline remain unverified.

Choose an explicit one-minute monitoring buffer for newly created probes only,
following independent security review. Keep checkout validity, amount, LN-only
methods, merchant credentials, locks and every outstanding-payment/Cashu/journal/
confirmation/ownership guard unchanged. Do not exempt metadata-marked probes or
retroactively alter old invoices. A marker is merchant-controlled, not provenance.

Two new regressions reproduce the omission before the service fix. Independent
QA verifies exact locked dependencies and passes 24 tests/150 assertions; security
accepts the three-file digest recorded in the contract. The deadline regression
proves guard release only, not a full provider wallet replacement. Actual BOLT11
expiry and delayed settlement require isolated provider acceptance; there is no
production cancellation or immediate-unblock claim. Document safe read-only
diagnosis and rollback. No new environment variable, schema, plugin/core change,
production mutation, merge or deployment is authorized by this repair.

## 2026-10-16: Cashu recovery API controller classification

Within the existing owner-authorized Satflux/owned-CashuMelt defect work, change
the recovery API to `ControllerBase`. Pinned BTCPay 2.4.5 classifies its previous
`Controller` base as UI and rejects API export/verify for missing form CSRF.
Keep API-key-only authentication, store policy, strict JSON, private headers and
Satflux single-use owner email codes. Do not exempt native UI or modify core.

Require the actual host filter regression with a UI rejection control, denied
non-API authentication and exact consumer wire assertions. Candidate 1.4.0.6 needs
a new provider PR because #38 was owner-merged. Independent security/QA, native
regressions, package identity and exact-head CI are handoff gates. No production
access, recovery, merge, release publication or deployment is performed.

This log starts with the current bootstrap. It does not reconstruct undocumented
historical decisions. Product history is linked from PROJECT_STATE with source evidence.

## 2026-10-16: recover durable Cashu payment phases without interpreting HTTP errors as settlement

Status: bounded continuation of owner-authorized owned CashuMelt/Satflux repair.
The owner confirms plugin 1.4.0.4 and two native failures; a counter of three
obligations is not evidence of a third failure. Implement the reviewed contract
[CASHU_PAYMENT_RECOVERY](CASHU_PAYMENT_RECOVERY.md), candidate 1.4.0.5, using existing
restore-only issuance and exact original-payout recovery. Require original snapshot
and issuance/proof binding; preserve legacy, incomplete, corrupt, mismatched and
unknown evidence. Never infer unpaid/settled state from HTTP 400/404 or age.

Expose only allowlisted read-only blocker categories/reasons/phases. Keep counts of
FAILED, other unfinished and terminal-with-material records distinct. Diagnostics
do not authorize operations. Automatic retry accounting is monotonic under the
quote lock; explicit authorized manual retry retains existing reset behavior.
Independent security/QA, disposable tests, package identity and separate exact-head
CI remain delivery gates. No production/fund operations, core/third-party changes,
merge, package release or deployment follow from source implementation approval.

## 2026-10-16: show Cashu wallet-change safety before editing

Status: owner-authorized bounded Satflux UX follow-up. Present the existing
merchant-scoped safety evidence on the Cashu connection screen before edit/save,
with recovery navigation and explicit unavailable status. Add the aggregate to the
existing settings read; preserve first-setup/Lightning-switch behavior. Refresh must
not overwrite edits, reuse grants, send codes or initiate fund operations. Retain
server-side write checks and existing email confirmation. Update all five locales
and user guides; independently review source and verify focused regressions plus
existing static/build gates before publication. No provider/core/package change,
production operation, merge or deployment follows from this authorization.

## 2026-10-13: project-local orchestration

Status: authorized setup decision from the Product Owner's bootstrap request.
Main remains the PM/technical lead/orchestrator and owns shared state and publication.
Specialists have bounded ownership and independent security/QA review. Ten roles live
in `.codex/agents/`, reusable skills in `.agents/skills/`, portable registry in workspace/.
Keep existing Claude/Cursor/CI/security conventions; record contradictions explicitly.
External repositories remain read-only during setup. No global settings are changed.

## 2026-10-13: conservative concurrency and inherited settings

Status: bootstrap implementation choice, validated against CLI 0.162.1.
Set `max_concurrent_threads_per_session` to 3 because the supported key counts spawned
threads excluding main. This yields four simultaneous agents total, matching current
runtime capacity. Inherit model/reasoning/provider/approval settings; security, QA and
researcher default read-only. Live runtime permissions remain authoritative.

## 2026-10-13: independent repositories and version evidence

Status: authorized workspace constraint.
Keep separate histories/PRs/tests/releases. Use relative/env-configurable paths, verify
remotes, and distinguish stale checkout, fresh PR status and unverified deployment.
Do not create a monorepo, submodules, speculative relay repository or external edits.
Treat the NBDP workflow as reference, adapting ownership and trust boundaries to
Satflux rather than copying protocol-specific instructions or local Git workarounds.

## 2026-10-13: temporarily free PRO

Status: PROPOSED, NOT AUTHORIZED FOR IMPLEMENTATION.
Preferred architecture is a centralized reversible promotional entitlement policy,
separate from billing, security authorization, administrative privilege and limits.
The next task is a read-only multi-agent audit. Eligibility, promotion end/data access,
costs, quotas, existing subscriber behavior and rollout require Product Owner decisions.
Do not create subscriptions, cancel recurring payments or grant Enterprise/admin rights.

## Publication follow-up: owner clarification and authorization

Status: authorized orchestration publication only. Create a dedicated Satflux branch,
commit only prepared orchestration files, push to the owned repository and open a PR
to verified default branch `master`. Do not merge, deploy, change product code or
write to sibling repositories. Do not start the free-PRO audit in this step.

The Product Owner reports ZERO paying subscribers and intends promotional PRO access
for eligible registered users. This statement is not a verified production billing
finding. The future audit should use available non-production evidence only and
document its limitations. Existing paid-customer migration/refunds/communications
are not required under the stated assumption; revisit only if evidence contradicts it.
Prioritize centralized reversible entitlements, disabling new PRO purchases and
upgrade prompts during promotion, preserving billing for future monetization,
unchanged authorization/isolation/security and a safe return to paid plans.
Implementation remains NOT AUTHORIZED.

## 2026-10-14: SATFLUX-001 policy decisions and SATFLUX-002 authorization

Status: explicit Product Owner approval for bounded Satflux implementation, tests,
localization and documentation following the read-only audit. No external repository
writes, push, merge, deployment, production billing/configuration change or activation
is authorized. Historical audit-only instructions are superseded only within this
approved source/test scope.

Approved eligibility is non-guest plus verified email. Duration is indefinite.
Use current PRO quotas, retain over-limit objects and existing purchased allowances.
Existing manual PRO retains its current validity; no permanent grant or provenance
migration was approved. Pause all new PRO/trial/PRO-credit purchases, company slots
and ISDOC packs. Enterprise and administrative privileges remain unchanged.
No paying subscribers or in-flight payments exist according to the owner; production
records were neither accessed nor changed to verify those statements.

Implement effective access separately from factual billing with an explicit PRO
feature allowlist, preserving Enterprise exclusion, tenant/company-owner checks,
guest/verification restrictions and existing fulfillment/reconciliation. Promotion
defaults off; purchase flags default closed independently. No subscription, role,
email verification, trial usage or balance is manufactured by promotional access.

Source implementation passed independent security/QA review and full validation
recorded in PROJECT_STATE. Targeted fixes preserve numbered WooCommerce completion,
owner/member isolation, fresh verified local writes and actual-provider Enterprise
credit behavior. PHPStan debt was reduced without adding suppressions.

Release prerequisite: Satflux purchase guards alone cannot close direct BTCPay sales,
funding portals or prefunded AutoRenew. Verify the exact deployed provider and a
PRO-only closure before activation; plan deletion or subscription cancellation is
not authorized. Hosted-relay costs and rollout propagation also require readiness
evidence. Future notice/data-access policy, permanent manual grants and paid reopening
remain separate owner decisions. Next milestone is SATFLUX-003 readiness.

## 2026-10-14: SATFLUX-003 readiness continuation and provider boundary

Status: owner-authorized readiness preparation after the local implementation.
The owner reports deployed BTCPay Server 2.4.5. This identifies the intended version,
not the exact deployed build, plugin inventory or live purchase closure.
Independent payments/devops/security/QA source reviews completed read-only; the
coordinator records documentation only. No provider or production changes authorized.

Activation is NO-GO: the modern core provider reference has independently reachable
checkout, funding, migration and prefunded AutoRenew paths. Existing Retired/Renewable
concepts do not provide a complete sales pause. Recommend separately authorizing a
central reversible policy in owned `webiumsk/btcpayserver` at a selected compatible
revision, preserving subscriber intentions/balances/settlement and Enterprise.
No automatic catch-up debit on reopening; no blanket cancellation or plan deletion.

Clarified reversal: current-artifact promotion-off may retain data while blocking
regular legacy reads/exports and local invoicing navigation. Older code ignores new
purchase flags and may reopen sales. Routine reversal uses the new reviewed artifact;
code rollback needs separately proven selective purchase closure and access terms.
No new historical read-only allowance or permanent manual grant was approved.

Prepared [FREE_PRO_READINESS](FREE_PRO_READINESS.md) includes exact source references,
staging acceptance gaps, operational/cache/backup/relay limitations and a local PR
handoff. Fresh master/CI evidence does not imply feature publication or deployment.
Repository-specific provider implementation and subsequent publication/merge/release/
activation require their own applicable approval.

## 2026-10-14: SATFLUX-004 owned-provider implementation approval

Status: explicit owner approval for scoped subscription sales-pause implementation
and isolated tests in `webiumsk/btcpayserver` for BTCPay 2.4.5. No push, merge,
deployment, release, production configuration/record changes or activation.
Upstream and unrelated repositories remain read-only.

Use exact v2.4.5 baseline `5d0745cae6be5d8210459e38813f317673aa97b8` in an isolated
owned branch. Central pause defaults off and uses validated store/offering/plan
identities; Enterprise, permissions, settlement/recovery and balances remain intact.
Block new intent/execution and prefunded/scheduled PRO work, preserve stored
preferences, and prevent surprise catch-up debit on reopening. Existing settings
may hold a scoped resume barrier; no schema/subscriber/plan migration is authorized.
Exact project dependencies may be restored privately for authorized isolated tests;
no tools are installed or package versions changed. Independent security/QA review
must assess the final provider implementation before release readiness advances.

### SATFLUX-004 completion evidence

The bounded provider implementation is complete locally on the exact approved host
baseline, commit `734791fdd8ac6df46088c43fa0152ad5f185be3e`. Independent security and QA accept the final source; native policy 20,
real configuration 3, lifecycle integration 1 and generated documentation/help 1
tests pass. Release build has zero errors and three obsolete setter warnings only
in synthetic invoice fixtures. Swagger and whitespace checks pass. Source fingerprint,
contract, evidence limits and runbook are in
[FREE_PRO_PROVIDER_IMPLEMENTATION](FREE_PRO_PROVIDER_IMPLEMENTATION.md).

The enabled policy pauses all non-exempt plans in the configured offering, including
unknown/new identities; only explicit distinct Enterprise IDs remain exempt. Durable
uncached JSONB barriers in existing settings retain monotonic cutoff and individual
historical-fulfillment holds. Turning OFF does not debit old credit automatically;
deliberate fresh purchase reauthorizes future renewal and buys a full current period
from held Grace. Historical obligations retain dating, genuine fulfillment and
idempotency. Do not erase barriers or downgrade to stock workers to reopen sales.

No push/merge/deploy or production mutation occurred. Synthetic invoice lifecycle
coverage is not Bitcoin/NBXplorer regtest evidence or full upstream-suite acceptance.
Next proposed milestone is SATFLUX-005 coordinated staging and release handoff;
deployed build/mappings/replicas/legacy inventory, relay costs/quotas, private restore,
exact release-head CI and end-access/reopening decisions still gate activation.

## 2026-10-14: owner-approved feature branch publication

The Product Owner requested push after the reviewed SATFLUX-004 handoff. Main
published `feat/free-pro-access` to `webiumsk/satflux` and
`feat/satflux-pro-sales-pause` to `webiumsk/btcpayserver`. Verified initial remote
heads are `a5ed908e384936678d4b41bdddf89c07e2eb9489` and
`734791fdd8ac6df46088c43fa0152ad5f185be3e`, respectively. Both were new remote
branches; no force, tags, upstream write, PR creation, merge or deployment.

Publication authorization supersedes the earlier no-push milestone boundary only
for these owned feature branches. This follow-up changes documentation only; no
application implementation or test rerun was necessary. No feature CI runs were
returned by the current GitHub queries; no remote validation success is claimed.
Release/base/ancestry review, exact head CI, operational evidence and explicit
applicable merge/release/deployment/activation approvals remain outstanding.

## 2026-10-14: provider work withdrawn; Satflux-only delivery

The Product Owner clarified that BTCPay must not be changed and then explicitly
requested deletion of the published provider feature branch. Main deleted only
`webiumsk/btcpayserver:feat/satflux-pro-sales-pause`, protected by an expected-head
lease for reviewed commit `734791fdd8ac6df46088c43fa0152ad5f185be3e`. Remote
verification confirms absence; master remains
`3ec6a9847bd06e5dad348caaec373276dc718520`. No provider merge/deployment or
production mutation occurred. Local source is retained as historical evidence;
no republication/integration/deployment is authorized.

SATFLUX-004 is withdrawn from the release plan. The Satflux feature branch remains
published and application code is unchanged. The owner accepted the stated limitation
that Satflux guards do not block independently reachable direct BTCPay purchases;
provider-side automatic credit renewals also remain outside those guards. Do not
claim global purchase closure or deploy provider pause controls from the historical
runbook. SATFLUX-005 is narrowed to Satflux review/CI/staging and its existing
operational readiness. Merge/deployment/activation still require applicable approval.

## 2026-10-14: approved Satflux CI repair

The owner requested repair of PR #394 CI 38563933755 at `c22660c0`. Keep the scope
Satflux only. A confirmed 429 is fixed by an independent ordinary checkout fixture
account, not by increasing/disabling production limits. Real authentication/CSRF
and signed settlement remain in the browser scenario; JSON/status diagnostics must
not print document snapshots or auth state.

The typecheck wrapper must reject silent nonzero/compiler failures. Its previous
local zero-error success was unreliable and is superseded by the real captured
121-error run. The baseline may refresh only the 32 proven equivalent existing
diagnostic prefixes, retaining exactly 100 groups/121 occurrences and file/code
allowances. No broad suppression or error-count increase is permitted. Independent
security and QA must inspect the migration proof and regression behavior.

Use the existing feature branch for the reviewed repair and authorized publication;
final PR head CI is required. Local/remote test results remain distinct. No merge,
deployment, activation, provider code/configuration or production billing changes.

Local completion: independent security and QA accept the bounded fix. Full Playwright
passes 20 tests, three intentional promotion-only skips, zero failures/retries in
2.5 minutes; real checkout creation/signed settlement and recovery are exercised.
Six runner regressions, actual 121-diagnostic typecheck, 119 Vitest files/708 tests,
lint (zero errors/48 existing warnings), five-language parity, build, changed-seeder
Pint/syntax, Graphify AST update and doc-link/whitespace checks pass. Browser/runtime
and network remain disposable/synthetic, with no production connection. Fresh remote
head CI must be checked after this publication; local results are not its substitute.

Remote completion: repair commit `7b06ed7f1c1b7bda708e62887953a7a6303c6582`
passed all PHP, frontend and E2E jobs in
[CI 38594038116](https://github.com/webiumsk/satflux/actions/runs/38594038116).
Verified counts: 1,631 PHP tests/6,891 assertions, Pint 1,036 files, clean PHPStan,
six gate regressions, actual 121-error baseline with no new errors, 119 Vitest files/
708 tests, 20 E2E passes/three intentional promo skips/no retries. Lint, locales,
builds and dependency audits succeeded. The completion follow-up changes docs only;
later PR heads still need matching checks. Local task containers/network/proxy were
stopped/removed; provider scope remains withdrawn. No merge or deployment occurred.

## 2026-10-15: bounded Cashu wallet-change repair

Status: authorized Satflux defect repair on the existing unmerged feature branch.
The owner reports missing six-digit confirmation and an outstanding-settlement error
on unchanged saves, with two settled and one expired unpaid payment displayed.
These observations do not authorize inspecting or modifying production wallets.
The no-BTCPay-change constraint remains binding.

Reuse the existing password/session and email challenge, centralize configured Cashu
and shared wallet-path enforcement under the store lock, and preserve tenant/guest/
verification restrictions. Wizard selection alone is not a configured wallet: use
the provider's exact empty settings response or explicit missing configuration to
recognize first setup; unknown/malformed reads cannot remove a confirmation gate.

Treat an authoritative exact settings/payment-method match on an existing pure
Cashu store as read-only, with no provider mutation, journal or code consumption.
Real changes retain outstanding-invoice and settlement checks. Explicit false safety
means outstanding funds; missing/malformed/unavailable safety means verification is
unavailable. Neither permits mutation. Terminal visible payment labels do not prove
that no retained proofs or recoverable change exist.

Capture and consume the exact pre-write grant ID after confirmed success; encrypted
journal envelopes remain readable alongside legacy records. Failed local grant
finalization must not discard recovery intent. An older reader cannot safely handle
new unresolved envelopes, so prefer a forward correction over a blind code downgrade.
The pre-existing fallback combined-write durable journal/readback gap is documented,
not expanded into a provider or recovery redesign. No combined atomicity is claimed.

Require independent security/QA source review and focused/static/frontend checks
before publication; the complete backend suite and exact head CI must pass before
final delivery. Main owns shared documentation and existing branch publication.
No environment change, migration, billing rewrite, provider
modification, merge, deployment or activation is authorized by this repair.

Candidate evidence: independent security/QA accept final application manifest
`141a69b0f473cbc552f79f42fb7b1c070699a5e690df08f89061305cac553251`.
Focused Cashu 28/147 and both corrected fixture classes 32/109 pass; full Pint,
PHPStan, 726 Vitest tests, actual unchanged 121-diagnostic typecheck, lint (zero
errors/48 existing warnings), locale parity, build and graph/docs checks pass.
Initial full PHP had 1,648 passes and eight old fixture failures; the targeted
corrections preserve their assertions and negative ownership coverage. Exact-head
remote full CI remains required, so this is not a claim of local full-suite success.

## 2026-10-15: repair newly failing Composer audit without suppressions

The Cashu source commit `e24608d1` passed all application tests/static checks in
CI 38808562407, including 1,656 PHP cases/7,025 assertions, 726 Vitest cases and
20 browser cases (three intentional promotion skips). The final Composer audit
failed on existing `symfony/http-foundation` 7.4.14 with `CVE-2026-108082`.

Within the approved Satflux CI repair, update only this dependency to 7.4.21 using
Composer's partial update and unchanged Laravel/PHP constraints. This contains both
the security fix and the subsequent header-whitespace fix; do not stop at 7.4.19.
See the [upstream release](https://symfony.com/blog/symfony-7-4-21-released) and
[advisory](https://symfony.com/blog/cve-2026-108082-redos-in-headerutils-split-reachable-through-the-cache-control-request-header).
Do not add audit ignores, broaden constraints or update unrelated dependencies.

The generated lock changes one of 167 packages, retaining all other package objects
and metadata. An isolated manifests-only PHP 8.3/Composer run passes strict validation
and fresh `audit --locked` with no advisories. Existing vendor and production are
untouched. Security/QA must independently accept the exact lock, and new-head CI must
install it and repeat tests before delivery. No BTCPay, merge or deployment action.

## 2026-10-16: expose blocked wallet-change evidence without weakening safety

Owner testing confirms unchanged saves work, while a changed address is blocked.
CashuMelt version 1.4.0.3 is owner-reported; visible history shows two settled rows
and one expired unpaid row. Previous final head `6b289f75` passed all CI 38809977505,
including the updated dependency, with independent final QA acceptance.

Independent read-only source reviews establish missing visibility, not the live
asset state: the provider counts retained recovery fields and separate change rows;
Satflux discards those counts and normalizes some provider states for display.
Keep the provider boolean authoritative and preserve existing recovery behavior.

Within the authorized Satflux defect repair, add only safe diagnostic status/counts
to existing error/payment-list responses and known original provider state alongside
the existing normalized field. Counts are bounded integers or unknown, never inferred
amounts or permission. Do not expose/log raw provider extras, errors or recovery
material. The BTCPay client correction is limited to safety-endpoint logging and
error redaction; authentication, retries and unrelated logs are preserved.

Update the existing UI, five locales and user guides, test denied access, stale
snapshots, counter validation, sensitive-extra stripping, read-only behavior and
unchanged write/no-op/grant safety, then require independent security/QA and exact
head CI. No production inspection, provider modification, fund movement, new recovery
action, merge or deployment. Actual refusal diagnosis still needs the new read-only
counts/raw state; do not claim it is solved from screenshots alone.

Independent security and QA accept the frozen application/locale manifest recorded
in [CASHU_SAFETY_DIAGNOSTICS](CASHU_SAFETY_DIAGNOSTICS.md). Focused backend checks,
full frontend tests, full Pint and full PHPStan pass. A test-only translation mock
was made compatible with the existing TypeScript target without changing assertions
or expanding a baseline. Publication requires the remaining frontend checks and
fresh exact-head CI; local vendor does not validate the locked security patch.
Remaining frontend checks subsequently pass: actual typecheck (121 unchanged
baseline diagnostics), lint (zero errors/48 existing warnings), five-locale parity
and production build. Exact published-head CI remains required for handoff.

## 2026-10-16: owned CashuMelt and Satflux change recovery implementation

Status: explicitly authorized by the Product Owner. The restriction on changing
BTCPay concerns core and third-party plugins; the owned CashuMelt plugin may be
changed together with Satflux. Earlier blanket provider restrictions are superseded
only for this bounded owned-plugin initiative, not the withdrawn core subscription
sales-pause branch. No production access, fund operations, merge, release publication
or deployment is authorized.

Implement [CASHU_CHANGE_RECOVERY](CASHU_CHANGE_RECOVERY.md) using existing provider
recovery services and advisory locks. Add only Greenfield-scoped summary/export/
verification routes in CashuMelt and actual-owner-only action-bound six-digit
confirmation in Satflux. Consume authorization before one provider dispatch, sanitize
logs at source, retain uncertain outcomes and fresh verification, and keep unsupported
old-plugin behavior explicit. Do not lower sweep limits or discard small balances.

Separate repository branches, plugin 1.4.0.4 before dependent Satflux controls,
unchanged exact host pin, frozen-source independent security and QA, and disposable
stubbed tests are required. Preserve all existing billing, tenant and wallet-change
protections. Main owns shared state and publication. Application and plugin specialists
have disjoint writable ownership; core and other plugin source remain untouched.

Implementation evidence: independent security accepts plugin manifest `00769c84...`
and Satflux manifest `e1321b46...` (full hashes in the contract); independent QA
accepts both. Plugin `b86e6193` has green exact-head CI 38824816578 (583 maintained
tests), and the private 1.4.0.4 package matches the independently tested DLL. Satflux
full PHP 1,737/7,600 assertions, full frontend 778 cases, independent final focus 30,
Pint 1,041 files, PHPStan/typecheck/lint/parity/build and AST graph/docs checks pass.
Fresh-lock exact-head Satflux CI remains required after publication; real-host
installation and cross-application wire-level recovery remain staging acceptance.
No package release, merge, deployment, production change or real recovery performed.

## 2026-10-19: offline security and reliability investigation

Product Owner authorized offline analysis and focused reversible repository fixes
for guest concurrency, BTCPay file upload authorization, webhook store identity,
BTCPay app-list routing/logging and CashuMelt invalid-quote retries. Preserve
passwordless guests and all existing payment operations. Satflux focused tests:
82/82, 298 assertions; CashuMelt: 147/147 and successful build. Independent
security and QA reviews are complete. Production log conclusions are limited to
provided artifacts. No production access, changes, commits, package publication
or deployment occurred. See the incident report and read-only runbook linked from
PROJECT_STATE. The next milestone is operator-provided, redacted aggregate evidence;
publication requires separately reviewed clean-base branches and exact-head CI.

## 2026-10-20: scoped ticket images and retirement of email password reset

Status: owner-authorized implementation; publication and deployment remain separate.
Fix event-scoped ticket-type paths and use the merchant-scoped event logo endpoint,
matching the working store-logo permission model. Never fall back to the server key
for ticket images or infer URLs from opaque file IDs. Require the owned Tickets
2.0.4 additive logo response before deploying the consumer; preserve identity, schema,
existing ticket email QR behavior, core and third-party plugins.

Stage image replacement and removal until Save; retain the persisted event ID and
pending changes on failure. Independent QA identified and closed removal before Save.
Support JPEG/PNG/GIF/WebP up to 2 MiB in all three layers.

The owner chooses a temporary migration password entry for old accounts without
recovery/passkey credentials. Retire reset email and reset-token consumption uniformly,
including framework notification suppression, without invalidating existing accounts,
sessions or hashes. Redirect both historic URL formats on the server; this does not
remove secrets from old incoming access logs. Do not attribute the reported default
Laravel notices to abuse without request evidence. Independent security's missing
query-link server redirect is closed. See the contract for reproducible checks and
remaining provider persistence/staging gates. No production inspection was performed.


### Satflux publication authorization

The Product Owner subsequently requests pushing the Satflux changes to GitHub.
Commit/push only the reviewed consumer repair and its scoped tests/documentation in
`webiumsk/satflux`. Preserve local plugin changes and its installer without publishing
them under this request. Exact-head CI/review and provider-first staging remain
required; the push request does not authorize merging or deploying either repository.


### PR #396 obsolete reset-test alignment

The initial complete PHP CI run exposes five general-auth tests still asserting
legacy reset behavior. Preserve the Product Owner's disabled-reset decision and
update those expectations to the explicit 410/no-side-effect contract. Do not restore
email resets, skip the tests or change application/CI configuration for this fix.
The entire changed AuthTest and dedicated reset regressions pass locally (25 tests,
118 assertions) with disposable SQLite/array mail and no network. Independent QA
accepts the bounded test delta; fresh full CI is the next acceptance gate.


The follow-up CI passes all 1,836 PHP tests/8,425 assertions, Pint, Node and
Playwright, then exposes three unmatched baseline ignores for defects removed by
the ticket repair. Delete exactly those three entries; preserve all other patterns,
counts, PHPStan level and unmatched-ignore enforcement. Run the full PHPStan command
without restricting paths: it passes with no errors. Independent QA accepts the
exact three-entry deletion. Verify fresh exact-head CI. No application behavior
changes are needed for this correction.


### Ready-for-review partial-create visibility correction

CodeRabbit review of `9bc086fe` identifies one minor stale-list/count path after
an event is durably created but its image upload fails. Keep the known saved event
in the local list before refresh, retain editing ID/pending image for retry and
refresh before showing the image error (loading clears notices). Cancel must reveal
the saved event and enforce its event limit even when the refresh request fails.
Add bounded regression coverage and align existing SK/DE ticket terminology.
Independent QA accepts the source and independently passes all 13 focused tests.
Full lint/typecheck/locale parity/build pass; fresh published-head CI/review precede
handoff. No production,
backend/auth or provider modification is part of this follow-up.


### Integration after PR #395 merge

The owner merged #395 into `master` at `d37153ce` and requests resolving #396's
conflict. Merge that base into the existing feature branch, preserving both the
October 19 incident decisions and October 20 ticket/auth decisions. Only their
append sections conflict; no manual application-code resolution is needed. The
combined PHPStan baseline keeps both branches' reductions. Independent integration
QA accepts the result; combined offline checks pass (128 PHP tests/507 assertions,
13 frontend cases and full PHPStan). Require fresh combined-tree CI; this updates
the PR branch and does not
merge #396, publish the provider or deploy anything.

## 2026-10-21: unified Tickets sales and persisted merchant ordering

Status: implementation authorized by the Product Owner after reviewing the public
sales prototype and confirming that existing event authoring must be preserved.

The public sales page remains in the owned Satflux Tickets plugin on the BTCPay
host, using existing URLs. Combine event information, ticket selection and contact
entry before the existing hosted payment. Render saved title, full rich description,
physical/virtual type, optional image/location/end date, UTC times, effective
currency, capacities, redirects, email templates and optional raffle bundle. Remata
is example content only; introduce no accommodation-specific authoring fields or
universal free-entry claims.

Extend existing management lists in Satflux and native BTCPay with inline ordering;
no new Satflux page, tab or competing event/type form. The plugin owns the saved
order. Use a complete permutation, safe revision compare-and-swap and event-level
membership serialization; preserve disabled/default types, prices and sales counters.
Explicit old name/price sorting remains supported. Additive migration backfills by
name and ID because historical creation order was not stored.

The public retry flow must preserve immutable checkout intent, reuse the exact
invoice and retain hosted fulfillment authority. Independent security review found
a pre-existing incorrect internal-tag-prefix lookup and a related missing-invoice-ID
settlement recovery gap, now within the bounded integration review. Do not create
an additional invoice when existing intent evidence conflicts or lookup is unknown.

Separate owned repository branches and reviews are required. Candidate plugin
version is 2.1.0, retaining the uncommitted 2.0.4 dependency. No core/third-party
plugin source changes, real-fund tests, production changes, commits/publication,
merges or deployment are implied. Strict inventory holds and zero-price fulfillment
remain outside scope. See [implementation record](TICKETS_SALES_ORDERING.md).


### Tickets 2.1.0 local packaging and Satflux branch publication

The Product Owner explicitly requested an installable Tickets 2.1.0 package and
push of the Satflux branch to GitHub. Package the accepted, unchanged Release DLL
using the exact pinned BTCPay 2.4.5 packer, preserving standalone plugin identity
and recording archive/DLL hashes. Commit and push only the scoped Satflux feature
branch. This authorizes neither plugin source publication nor merge, installation,
release publication or deployment. Exact-head CI requires a pull request under the
current workflow; existing host dependency advisories and staging gates remain.


## 2026-10-22: public sales language and time-zone presentation

The approved 2.1.0 follow-up corrects plugin presentation without editing BTCPay
core. The host's invariant culture is not an anonymous customer's language preference.
Use allowlisted EN/SK/ES/DE/CS choice or bounded weighted browser languages, and
a CSRF-protected presentation-only language POST preserving the existing session
and incomplete draft. Do not change invoice language/settings or recreate intents.

Events currently store UTC instants without a venue time-zone field. Render those
instants in the visitor's browser zone and explicitly label the zone; keep UTC when
formatting is unavailable. Do not hardcode CET or reinterpret stored dates. Venue
time-zone authoring is a separate product decision, not silently inferred from
location text or language. Candidate version is 2.1.1; no schema change is required.


## 2026-10-22: automatic header language and content-addressed assets

The Product Owner requests changing language immediately from the header rather
than submitting a separate language button. Use the five constant dictionaries in
the existing DOM; update text/allowed attributes, local dates/amounts and form
language without HTTP requests or recipient-data transport. Retain input identity,
drafts, delivery choice, nonce and frozen checkout intent. The bounded backend
presentation action remains compatible.

Unversioned URLs allowed an old 2.1.0 script to be reused against the 2.1.1 page
in offline reproduction, matching the reported failure pair. Require content-hash
asset paths, exact version validation and explicit caching while retaining old
routes; do not weaken CSP. Reproduce the real shared host layout/CSP and check
actual generated route contracts, not only an overridden layout. This does not
confirm the operator's cache origin or authorize production cache/config changes.
Stored 08:00 UTC and browser-local 09:00 CET represent the same winter instant.

## 2026-10-22: physical event venue time, virtual visitor time

Status: Product Owner authorizes implementation and confirms every existing physical
Tickets event belongs to `Europe/Bratislava`. Add explicit IANA metadata with a
metadata-only historical backfill; never rewrite UTC instants. Future physical events
must choose a venue zone in existing authoring forms; old API clients remain compatible
with a clearly labelled unknown-zone UTC fallback. Physical public/ticket output uses
venue time even for foreign visitors; virtual public output uses visitor time. Provider
2.2.0 owns the catalog and civil conversion. Reject DST gaps and ambiguous edited civil
hours rather than silently choose an offset; UTC instant API compatibility remains.
Sparse date writes preserve original precision on unrelated edits. Install provider
before dependent Satflux consumer. See TICKETS_SALES_ORDERING.md for contract/review
ownership. No production operation, merge or deployment follows from this decision.

### 2026-10-22 clarification: store default with event override

The Product Owner approves using the Satflux store's saved timezone as the initial
choice for a new physical event, retaining a per-event override. Snapshot the chosen
zone on the event; later store changes must not alter existing events. Apply defaults
only from the matching store after provider validation, before user interaction.
Do not infer a zone from IP, browser location or a missing catalog.

Source inspection of pinned BTCPay 2.4.5 shows no persisted store timezone in its
standard Store API: the unknown outbound `timeZone` extension is not mapped. Satflux's
local `Store.timezone` is authoritative. Fix settings reload to prefer it rather than
show UTC and risk overwriting the saved choice on an ordinary subsequent save. The
manual form defaults UTC while the guest provisioner explicitly stores Europe/Vienna;
these are code findings, not production observations. Do not automatically rewrite
existing store records or change provisioning defaults in this milestone. Native
BTCPay event creation keeps explicit venue selection because it cannot read Satflux's
local store preference; do not invent a core field or cross-service write bridge.

The independent review also requires a native stale-schedule guard: original UTC
baseline hints detect concurrent start/end changes before civil conversion or writes,
retain the draft and require a fresh reload. These hints do not replace authorization.

### 2026-10-22: publish reviewed Satflux follow-up

The Product Owner explicitly authorizes pushing Satflux changes to its existing owned
feature branch. Publish the reviewed timezone/default/settings consumer and its docs
as an update to PR #397; keep plugin source/package publication separate. No merge,
production change or deployment is authorized. Preserve the unrelated image edit.

### PR #397 review: classify catalog and write failures separately

Confirm and fix the bounded CodeRabbit error-classification finding within the approved
Tickets scope. Catalog failures remain safely mapped; event writes use that mapping
only for exact allowlisted event-time codes when new time fields are present. Preserve
legacy UTC-only and unrelated provider exception behavior. Keep the shared code/field
map as the sole classification authority; no prefix matching or payment/API contract
expansion. Correct the three documentation comments in the same follow-up. Existing
publication authorization applies to the reviewed fix; no merge or deployment follows.

## 2026-10-24: PR #400 review fixes preserve authoritative wallet checks

The owner explicitly requested remediation. Integrate already-merged #399 through
master `709befa2` without rewriting the feature history. Treat settings 404 as absent
configuration consistently with existing consumers, but require a separate fresh,
strictly safe Cashu response; maintained provider source returns 200 for empty
configuration, so do not claim a proven provider regression. Cache only successful
presentation invoice scans for ten seconds, scoped by store/credential/server/state;
never reuse that advisory for a mutation. Keep unresolved-write and safety checks
fresh. Repair timer scheduling and visible retry guidance while preserving
identity/account revisions and stale-result isolation. Independent security and QA
review accepted this bounded scope; main retains publication ownership.

## 2026-10-27: implement a protected, bounded API reference

The owner approved implementation on a new branch after the read-only proposal.
Use Scramble at build time, reviewed OpenAPI 3.1 artifacts and locally pinned
Scalar. Serve a private admin/support portal with server-side authorization,
explicit scope exclusions and deterministic CI generation/validation. Preserve
session/CSRF, integration bearer, one-time code and webhook signature distinctions;
generation never calls production controller endpoints. No provider repository
writes or deployment approval are inferred.

Independent review exposed two concrete documentation/privacy defects: Mailgun
aliases were narrower than the actual receiver, and a separate Scalar MCP upload
control bypassed the normal renderer transport. Model all supported aliases while
retaining signatures, and disable MCP/AI/developer upload features explicitly.
Scoped dependency patches remove new advisories without a Vue/Node upgrade;
the CSS parser 6.1.4 -> 6.1.5 patch is the sole pre-existing package version change.
Security and QA accept digest `88db19b2`; remaining baseline advisories, incomplete
API coverage and offline PHP DNS failures are recorded rather than hidden by
weakened gates. Main owns publication and the next remote-CI handoff.

## 2026-10-27: repair PR #405 with reproducible npm 10 installs

The owner authorizes remediation and push to the same branch/PR. Keep security
patches and locked versions while moving the SDK utility override to project scope
for npm 10 peer resolution; restore missing esbuild dev/optional records. Require
normal clean-install evidence with CI's exact npm, not success over existing
node_modules, a CI toolchain change, legacy-peer flags or an audit bypass.

Correct all four reviewed reference inaccuracies. Document the existing named-PAT
exception for both admin and support because `User::isSupport()` includes both;
do not change authorization to fit previous prose. Add real-PAT regression coverage.
Independent QA/security accept digest `dc86ea04`; normal npm 10/11 installs and
fresh-dependency tests/build pass. Publication is authorized; merge/deployment is
not. Retain the docstring advisory separately instead of adding boilerplate or
weakening review configuration.


## 2026-10-28 - Chronological messages with separate critical visibility

The owner requests date-based message ordering, including security alerts. Replace
security pinning with one chronological feed while retaining critical labels, the
global unread-security banner and a dedicated critical filter. Apply category and
unread filtering before continuation, with user-scoped local keysets and encrypted
user/filter/page-size-bound cursors. Bounded upstream scans may return empty
continuation pages; they must not make older matches permanently unreachable.
Recognize actual BTCPay invoicestate invoice paths and resolve only owned stores to
local UUIDs. Preserve the existing 30-day read-local-message window and every unread
local alert. Document upstream offset insertion limits instead of claiming snapshot
stability. Automatically refresh expired cursors once, isolating stale responses.

Use the current merged master in an isolated feature worktree and preserve the
dirty bootstrap checkout served at local port 8080. Verification uses synthetic browser
data and disposable PHP targets. QA/security accept source digest `5c2bd32c`; obsolete
PHPStan exceptions are removed, with no baseline or auth/security gate relaxation.
Publication, PR-linked changelog synchronization and actual runtime deployment are
separate from this local implementation milestone.


The owner authorizes pushing the usability branch. PR407 is the canonical source
for one combined reviewed changelog fragment; keep the previously accepted runtime
implementation unchanged. CI/check initiation is evidence of publication, not
evidence of approval, merge or deployment.


## 2026-10-28 - Empty filtered-feed continuation remains navigable

Do not infer another filtered page from uninspected raw rows. Reuse the bounded
peek to consume excluded trailing rows, retaining a continuation if the scan budget
prevents proving exhaustion. A later scan can legitimately end empty, so Previous
must remain available outside the message-list branch. Regression tests reproduce
both failures before the fix; independent QA/security accept four-file `949cf289`.
Preserve the 500-row budget, cursor/ownership checks, canonical changelog and all
quality ratchets. Record old green CI separately from fresh followup verification.


## 2026-10-28 - Distinguish passing PHP tests from a failed security audit

Treat run 40753609416's passing 2072 PHP tests separately from its failed Composer
audit. Follow the live October 28 advisory range and official MIME 7.4.22 security
release, not stale indexed patch-version descriptions. Target only symfony/mime
with native Composer and verify actual freshly installed version/reference, normal
mail behavior and malformed MIME-parameter rejection. Preserve audit enforcement,
all other packages and runtime constraints. Independent QA/security accept lock
SHA `8f3133e4`; require fresh exact-head PHP-job completion through audit after push.


## 2026-10-28 - Gate new account creation with Turnstile only

The owner narrows the prior abuse proposal to Turnstile, leaving existing accounts
and their address/status handling alone. Enforce the challenge on the server before
new guest or enabled legacy registration side effects. Preserve authenticated retry
and signed recovery behavior; a public recovery key is never a login proof.

Use deployment-owned exact hostnames, a fixed Siteverify endpoint and register action; do not
trust incoming Host/forwarded-IP fields for the proof. Omit remoteip from Siteverify,
retain existing rate limits, and defer proxy/origin hardening as separate work.
Incomplete enabled configuration and provider failures cannot disable the gate.
Known public test credentials must fail closed in production.

Keep confirmed enrollment local after backup, require explicit fresh-proof retries,
and treat CF code as a trusted CSP origin without claiming executed third-party
code can be unloaded. Preserve all quality baselines; the public-config contract
test gains only the additive expected object. QA/security accept `305d24d0`.
Actual activation requires verified real keys and deployed configuration; a local
implementation, a merged PR or a disabled flag does not establish protection.


Publish the authorized Turnstile implementation as PR408 with one canonical
changelog fragment. Retain the independently accepted runtime scope while adding
publication metadata. Distinguish default-disabled readiness from actual deployed
protection; runtime credentials stay server-side and activation requires verification.


## 2026-10-28 - Keep one visible registration failure

Use the accessible inline signup error as the single failure presentation. Remove
the duplicate global toast to fix E2E selection and UX while preserving 409
identity protections; do not mask it with a relaxed locator. Keep German signup
copy consistent with formal auth language. QA/security accept `dab15e0d`; unchanged
backend evidence remains valid and fresh CI must verify the new full revision.
Widget activation remains distinct from reviewed code and public-key receipt.


## 2026-10-28 - Verify Turnstile asynchronous readiness against the provider SDK

Do not combine an asynchronously loaded Turnstile script with `turnstile.ready()`; the provider rejects this with3857. Use the documented named SDK onload handshake, registered before script insertion, with bounded cleanup and stale-attempt protection. A browser script load event or an early global alone does not release the pending handshake. Include independent real-SDK initialization evidence in addition to mocks; intercept all challenge/account traffic and never create production accounts for QA. Enabled public configuration and CSP are separate evidence from successful token verification.


Accept PR409's bounded asynchronous SDK compatibility fix after independent security/QA review and actual-SDK failure/initialization/retry evidence. Preserve server proof verification, post-backup loading and callback cleanup; neither a successful SDK render nor enabled public configuration establishes a completed human challenge or valid server secret. Publish a separate immutable user-facing PR409 changelog correction and keep owner merge/deployment separate.
