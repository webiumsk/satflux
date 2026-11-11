# Invoicing action center and migration wizard

Date: 2026-11-10. Updated: 2026-11-11. Owner-authorized implementation in Satflux only.
Base: freshly fetched owned master `6e6c0587`; branch
`feat/invoicing-action-center-migration`. Primary dirty work is preserved.
No external repository changes or production activation is included. The owner
authorized source publication and draft PR review on 2026-11-11; earlier sections
retain the chronology of local implementation and validation.

## Product contract

The company-scoped Needs attention page combines unmatched bank movements,
failed document delivery, synchronization issues and received documents awaiting
processing. Each item explains its reason and links to an existing resolution
surface. Reading the page never sends, imports, dismisses, synchronizes or marks
anything paid. It is available in desktop and mobile navigation without requiring
a configured bank account.

Extend the existing document import route rather than add a competing wizard.
The initial source priority was SuperFaktura, Fakturoid, iDoklad and Zaprite.
After accepting SuperFaktura, the owner explicitly selected Zaprite next.
The current implementation supports SuperFaktura-compatible spreadsheets,
generic CSV/XLSX mapping and the bounded Zaprite API/JSON adapter documented
below. Fakturoid and iDoklad remain follow-up scope. Existing ISDOC expense
import remains available and is distinct from issued-invoice XML migration.

## Acceptance criteria

1. Every attention source has explicit loading, checked, unavailable or
   unconfigured state. Failed/partial/stale reads never become an all-clear.
   Company/share changes invalidate earlier responses immediately.
2. Server aggregation enforces existing company membership, current integration
   binding and stricter owner-only source access independently of plan flags.
   No raw error/provider payload, buyer email, credential, token or arbitrary URL
   is returned. GET never calls a provider or performs a mutation.
3. Keep local-first bank matches authoritative on the client. Remote dedupe hashes
   may correlate incoming bank copies without uploading local matching decisions.
   Source counts remain qualified when only a bounded sample has been checked.
4. Manual email failure evidence is persisted as a generic timestamp/code for
   legacy server documents and encrypted audit events for local documents. A
   successful later send resolves the warning. No historical failure is invented.
5. Validate every spreadsheet row up to existing supported limits before commit.
   Show invalid rows, in-file duplicates, existing-company conflicts and totals
   separately by currency. Preserve supplied actual payments, dates and balances;
   reject contradictory or unsupported payment evidence rather than invent it.
6. Retain the supplied source export once using encrypted company-scoped local
   archive/chunk records. Confirm read-back and SHA256 before document writes;
   preserve it across partial failure/retry and expose download after reload.
   A spreadsheet export is not the original invoice/PDF. Summary-line import is
   not a complete original line/VAT ledger migration.
7. Revalidate current company authority, file/mapping generation and duplicates
   at commit. Disable double-submit. Retry must not duplicate invoices or overwrite
   existing financial history; partial/unknown outcomes remain visible.
8. Existing encryption, company sharing/rekey, source-specific controls and
   legacy invoicing remain intact. All new copy/docs have EN/SK/ES/DE/CS parity.
9. Independent security and QA review exact final files; existing relevant unit,
   API, browser, lint/type ratchets, build and locale checks must be exercised on
   isolated synthetic targets. Existing global failures remain attributed.

## API and local contracts

`GET /api/invoicing/companies/{company}/action-center` returns `data.checked_at`,
`data.categories` and `data.restricted_sources`. Category keys are
`unmatched_payments`, `failed_sends`, `synchronization`, `incoming_documents`.
Each category carries `state` (`ready`, `unavailable`, `not_configured`), nullable
`count`, bounded `items` (25) and `has_more`. An unavailable source has no asserted
zero count. Counts are server-source counts, not a claim about client reconciliation.

Item fields are stable `id`, controlled `kind`, nullable `reference`, `document_id`,
`occurred_at`, `amount`, `currency`, and optional `bank_dedupe_hash` for bank rows.
Kinds are `unmatched_bank`, `legacy_email_failed`, `integration_email_failed`,
`efaktura_send_failed`, `integration_pending`, `efaktura_pending`, `wise_sync_due`.
The client maps these codes to named routes. Wise due means never/older successful
sync, not a claim of a recorded provider failure. Owner-only eFaktura restrictions
remain explicit. New aggregation does not require the separate unmerged ePostman
feature or claim verified Slovak eFaktura readiness.

Local sending failure uses `business_document.email_failed` audit events with
generic reason only; existing email-sent evidence resolves it. Archive metadata
and every source chunk carry `companyId` for shared-owner routing. No plaintext
source file is uploaded to a new server archive.

## Ownership and delivery

| Responsibility | Agent | Writable scope |
| --- | --- | --- |
| API aggregation, legacy email failure evidence | Backend | New company action controller/service/test; routes/api.php; BusinessDocument model/controller and bounded migration |
| Migration validation/UI/original archive | Frontend | DocumentImport.vue, documentImportLocal.ts, useDocumentImportFields.ts, migration modules/tests, schema.ts/client.ts |
| Attention page/client aggregation | Designer (explicit UI implementation) | New NeedsAttention.vue, action-center service/composable/local modules/tests |
| Shared integration and documentation | Main | Navigation/routes, email modal/local audit helper, payment-filter/sync deep links, five locale JSONs, feature/user docs, safe test setup and state |
| Security / QA | Independent reviewers | No implementation writes; explicitly disposable synthetic checks only |

Specialists cannot commit, push, merge or deploy. New locale modules are temporary
handoff inputs; main integrates strings into established five JSON catalogs.
Unrelated feature worktrees stay read-only. Schema changes are additive; rolling
back code must not delete source archives or imported financial records.

Archive-bearing backups use inner format version2 so older readers reject them
before restoring only the financial rows and omitting originals. Any source or
chunk row triggers version2, including pending records. Archive-empty backups
retain version1; the current reader accepts numeric versions1 and2. The outer
AES-GCM envelope/version and passphrase policy are unchanged. Reload/update
Satflux before restoring archive-bearing backups; never downgrade their version
or strip archive tables to make an older reader accept them.

## Initial summary-import verification

Implemented locally on the feature branch above, without commit, publication,
merge or deployment. The primary checkout and its unrelated changes are intact.
Graphify's existing graph was available for initial orientation but stale; targeted
current-source reads supplied the contracts. No graph/tool was installed or rebuilt.

Independent security accepts 65 runtime/test files, manifest SHA256
`446070d4fa60044c6524111e047c1ba9dd673db7c43b944605be2118ef8d8b84`.
No open critical/high/medium finding remains in that scope. Independent QA accepts
the exercised functionality using real Chrome151 and Evolu SQLite-WASM/OPFS:
paid/partial/unpaid/cancelled preservation, exact original bytes after reload and
full browser restart, duplicate retry, blocked writes on archive readback failure,
interrupted-line repair, company switching and actual shared-owner conversion.
The shared-owner retry preserves the original document/source/chunk IDs. Desktop
and mobile checks show no page overflow or page errors. Final currency wording was
reviewed statically after browser execution; the later backup-wrapper delta was
independently tested and security-reviewed separately.

Sanitized private evidence is retained in the primary checkout under
`storage/app/private/reviews/action-center-migration/{qa,security,verification}`.
This excludes browser profiles, OPFS/authentication/key state and merchant data.

| Check | Result and boundary |
| --- | --- |
| Full frontend suite | 217 files, 2,387 tests pass before the final narrow backup compatibility guard |
| Final backup compatibility/restore suites | Four files, 27 tests pass on final source, also independently repeated; counts overlap the full suite |
| Historical backup reader | Test fixture parser is byte-identical to base `6e6c0587`; rejects archive-bearing inner version2 before restore; current reader restores exact bytes and links |
| PHP API/email/docs | 23 tests, 152 assertions pass on final SQLite memory configuration; one inherited PHPUnit metadata deprecation |
| PostgreSQL API/email | 15 tests, 126 assertions pass on disposable PostgreSQL17.11/PHP8.3.31 with fresh schema; same inherited deprecation |
| Typecheck | Final source passes existing ratchet: 110 tolerated baseline findings, no new errors; baseline unchanged |
| ESLint | Final source passes: zero errors, 48 existing warnings within unchanged limit52 |
| Production build | Final source passes; existing annotation/chunk-size warnings remain |
| PHP formatting/static analysis | Seven changed PHP files pass Pint; changed backend classes pass PHPStan using existing baseline and temporary cache |
| Locale parity | EN/SK/ES/DE/CS pass; four inherited extra SK keys remain informational |
| API documentation | Export freshness passes; validator test passes; all three OpenAPI documents validate |
| Changelog tooling | 71 tests pass; 14 existing fragments validate. New fragment waits for an actual canonical PR URL required by the schema |
| Whitespace/dependencies | Diff check passes; the initial summary implementation keeps package/lock files and warning/type baselines unchanged |

PHP tests use locked dependencies, guarded uncached testing configuration, synthetic
keys, temporary storage and array transports. SQLite has no network; PostgreSQL
uses an isolated internal network, no host ports and a disposable database. No
production configuration, real email/provider operation or real funds were used.
Task PostgreSQL containers/networks and QA's loopback server were removed/stopped.
Full project-wide PHP success is not asserted; the unrelated minimal-environment
failures documented for the base were not changed by this feature.

## Limits and rollout

Implemented sources are SuperFaktura-compatible CSV/XLS/XLSX, generic mapping
and the bounded Zaprite API/JSON adapter. Fakturoid and iDoklad remain future
adapters. Zaprite supports the selected two-decimal fiat currencies; native BTC
and other precision require explicit exclusion with complete source retention.
Supplied originals remain verified batch evidence. Limits are 2,000 rows, 50 optional originals,
10 MiB per file and 20 MiB per batch including the export. Source validation rejects
ambiguous payments and conflicts; existing financial history is never overwritten.

Deploy the backend migration, API and client together only after owner approval.
Require a refreshed client for new archive tables and version2 backups. Rollback
must retain added columns, financial records and source/chunk archives; never strip
archive tables or rewrite backup versions for an old client. No actual provider
interoperability or production eFaktura readiness follows from these local tests.
The separate ePostman feature branch is not a dependency of this implementation.

## Larger batches and SuperFaktura items - 2026-11-11

Allow 50 originals within the existing 10 MiB per-file and 20 MiB combined
limits. The original readback checks the selected company and source query,
then verifies owner, count, order, lengths and SHA256. Progress covers file
retention before invoice writes. Other archive operations keep their existing
batch and snapshot contracts.

For SuperFaktura, read an ISDOC 6.0.x invoice from a selected ISDOC/XML file or
from a document-level PDF attachment. The pinned PDF.js 6.5.438 worker runs
locally. Its public attachment API enumerates candidate names and reads their
contents. The app refuses multiple recognized ISDOC candidates, caps XML at
512 KiB after decoding and terminates stalled workers. The reader inherits
PDF.js handling of malformed name trees. A time limit and post-decode XML cap
provide no worker heap guarantee for compressed attachments.

The preview compares invoice number, supplier registration, buyer, issue date,
currency and gross amount against the selected company and spreadsheet. It
shows item names, quantities, rates and gross values for matched rows. Exact
cent arithmetic verifies supported lines, tax groups and document totals.
Ambiguous evidence or mismatches block the invoice. Missing or unsupported
items permit a summary when the operator confirms the fallback. Use the archived
original PDF for its original appearance and historical tax context. New output
from existing rendering tools follows their current company and contact rules.

The importer reparses the supplied originals and compares the displayed preview
before file or invoice writes. Stable document and line IDs and a complete line
readback protect retry and rekey. Payment evidence still comes from the
spreadsheet. Completed summary records retain their original fingerprint and
require separate review if new detail conflicts with them. Source bytes remain
in the existing local archive and share/backup lifecycle.

For the Slovak SuperFaktura spreadsheet, exact buyer headings take priority
over generic aliases. This includes `Odberateľ IČO`, `Odberateľ DIČ`,
`Odberateľ IČ DPH`, contact details and country. Document totals and payment
evidence keep their own mappings. The source card uses the supplied local SVG
with its localized accessible label.

### Follow-up verification

- Seven focused suites pass 105 tests on the follow-up source. The dedicated
  buyer-header regression covers a generic seller IČO preceding the buyer
  IČO, contact values, original document currency and unchanged settlement
  interpretation. A fresh Chrome context verifies the mapping from actual
  localhost modules with the application API blocked.
- Actual Chrome and Evolu SQLite/OPFS accept 30 synthetic 350 KiB originals
  plus the export. All 31 digests match after import, reload and a full browser
  relaunch. Scoped reads reduce cumulative returned base64 characters from
  652,601,824 to 28,678,984 in this run. Overall times are comparable under
  concurrent test load. The byte and count limits reject oversized batches.
- Independent final runtime QA verifies two structured invoices and one
  explicitly confirmed summary. All five lines, taxes, actual payments and
  statuses match after writes. Four archives match independent SHA256 after
  reload. Conflict and absent confirmation stop before writes, and an exact
  retry creates zero additional invoices. The wizard exercises the same
  preview, consent, successful result and archive list. All four downloads
  match their source digests after reopening the wizard. Retry shows three
  duplicates and disables another import. Mobile preview has no page overflow.
- Full application build passes before the final worker asset URL and buyer
  alias delta. The exact final worker builds to a JavaScript asset under Vite 5
  and passes strict CSP and MIME checks in Chrome. The trusted worker start has
  a 15-second deadline, followed by six seconds to parse untrusted bytes. The
  active localhost loader verifies two items and an intact original, and the
  supplied SVG decodes successfully.
- Final typecheck passes the existing baseline of 110 tolerated findings.
  Full ESLint passes with 48 existing warnings, and the final changed-file
  check passes with zero warnings. Five-locale parity and diff checks pass.
- Independent security accepts 30 reviewed paths, manifest SHA256
  `0f0ad064ff8b8c8b1fe56fd7e08b5ff2bc4905f98907534fc8e22b145f4edf29`.
  No open critical, high or medium finding remains in that scope. Private
  evidence is under
  `storage/app/private/reviews/action-center-migration/item-followup`.

### Paid SuperFaktura export compatibility - 2026-11-11

The export can contain a positive gross invoice amount and zero payable.
Retain the validated invoice and VAT totals. Admit item evidence for zero
payable only when the spreadsheet independently confirms exact full payment
and zero balance. When the original lists nontaxable deposits, require their
positive amounts to sum to the full-gross deposit field and their IDs to be
unique. The original retains deposit details in the archive. The import
continues to use spreadsheet payment evidence and creates invoice records only.
This initial adjustment excluded taxable deposits, foreign-currency adjustments
and signed unit prices. See
[ISDOC 6.0.2 monetary amounts](https://isdoc.cz/6.0.2/doc/isdoc.html).

Preview now explains controlled parsing and matching reasons. A same-number
existing invoice remains a conflict until the wizard can prove an exact
duplicate. The new message distinguishes this state from a proven data
difference. On the owner-supplied XLSX and original PDFs, a local read-only
comparison using the common issuer as the reference matched 27 itemized rows.
Six files contain negative unit prices. The comparison used no company
database or existing invoice state and wrote no records. Only aggregate
diagnostic counts were retained. The destination company still needs its own
matching registration number in the real preview. Focused parser, validation
and import suites passed 88 tests before the spacing follow-up. Five-locale parity, scoped lint and whitespace
checks pass. Typecheck reports no new findings beyond the existing 110.
Independent security accepts the 30-path manifest
`256f799210184fe4579d8b4a43e05aab648fed8d28e3c0a6b663f10a294f6a9d`.
Aggregate-only evidence is retained under
`storage/app/private/reviews/action-center-migration/settled-export-followup`.

The spacing follow-up ignores whitespace in eight-digit supplier and buyer
registration numbers during comparison. All digits and existing fingerprints
are preserved. The validation and import suites pass 64 tests after this change.
The updated module is served by the local preview.

### Signed deductions in historical invoices - 2026-11-11

Allow a negative unit price with a positive quantity in the SuperFaktura ISDOC
reader. Keep the deduction as a separate line and reconcile signed line amounts,
nonnegative net and VAT buckets by rate, and a positive invoice total with exact
decimal arithmetic. The spreadsheet still supplies payment evidence. Pending
migration drafts cannot use ordinary save or issue to bypass import readback.

Signed single and bulk PDF output is available for issued or paid Slovak and
Czech invoices with payment options disabled. The PDF endpoints verify the
stored line gross amounts, subtotal, VAT and total against the server
calculation. Use the archived
original when current VAT settings or PDF precision prevent reproducing the
stored amounts. Structured exports and outbound payment and delivery requests
retain their existing nonnegative-price validation.

The actual localhost PDF worker read 33 original PDFs. All 32 rows in the
provided spreadsheet matched itemized originals, including all six deductions.
This comparison used a reference issuer and empty destination state and wrote
no records. Independent Chrome and Evolu QA verified negative prices, VAT, full
and partial payment, four original checksums after reload, rejection of
inconsistent evidence, and duplicate retry using synthetic documents.

Scoped frontend regression passed 143 tests across 11 suites. The final
snapshot fixture passed again after adding the stock fields. Offline backend
regression passed 57 tests and 304 assertions, including offsetting line VAT
and changed VAT context. Scoped PHPStan, Pint, locale parity and whitespace
checks passed. Frontend lint reports four existing warnings in the changed
files. Typecheck passes the existing baseline of 110 tolerated findings. The final independent security manifest covers 43 paths with
SHA256 `2f8cbd97f4eea9e7fa0e19d43c57ae88e786ad94e80ed2a8a4e784631721edec`.
Private synthetic and aggregate evidence is under
`storage/app/private/reviews/action-center-migration/signed-deduction-followup`.

### Storage readiness and mutation receipt follow-up - 2026-11-11

The owner reported the storage-unknown message after an import attempt and
estimated about 30 seconds elapsed. The message alone cannot identify the
failed operation. Independent Chrome QA reproduced an old tab retaining the
shared database worker with the previous schema. A current client then fails
to read migration tables. Closing every client and reopening the same profile
loads the current schema and retains existing records. Parsing selected
originals precedes the financial gate, so time after clicking includes parsing.

Before archive queries, preview and the importer gate, inspect fixed
`pragma_table_info` metadata on the existing worker with the existing bounded
query helper. Require both archive tables and the three migration document
columns. Missing schema produces a localized close-all-tabs and reopen action
before migration writes. The existing database identity and worker lock apply.

Known rejected contact updates and insertions produce a typed failure that
the importer passes through its mutation result. Check the audit event result
before finalizing an invoice. These checks prevent a known aborted batch from
waiting for a completion callback that Evolu discards. Uncertain outcomes retain
quarantine. Finite phase and reason codes identify a missing receipt, worker
failure or start exception without serializing raw worker errors or document
values. The wizard disables another attempt in the quarantined session.

Independent prepatch Chrome QA completed 32 synthetic invoices and 33 originals
of 350 KiB, including existing unchanged contacts and a pending source retry.
All 34 originals matched after reload and a full browser restart. This excludes
normal batch size and identical-value receipt loss as established causes.
The actual workbook's 32 new-contact payloads passed schema-only validation,
with no record writes. The exact cause of the owner's attempt remains subject
to the new phase diagnostics or confirmation after reopening all clients.

Final independent Chrome QA rejected actual import on the legacy worker in
5 ms with zero document, line, marker or archive-table writes. Closing all
clients and reopening the same profile imported 32 invoices with 33 originals.
All 34 archive digests matched after reload and an exact retry produced no
new records. A deliberately rejected existing-contact SDK update reproduced the
old 30-second failure. The corrected path returned a known contact error in
672 ms and the same-session retry imported the signed invoice successfully.

Focused regression passes 150 tests across 12 suites. Typecheck passes the
110-finding baseline, scoped lint and five-locale parity pass, and the application
build passes. Private synthetic evidence is in
`storage/app/private/reviews/action-center-migration/storage-readiness-followup`.
The feature worktree has no Graphify graph. The root graph query was supplemented
with targeted reads of the affected feature modules.

Independent security accepts the final 46-path scope with manifest SHA256
`f3668e4184633980c665cc242fc00f1c332717463b455a54c9bbd00ca49a1404`.
Its independent 52-test run and final eager-load guard review pass.
The final wizard module is served by localhost after the additional guard.

### Relay quota and local mutation confirmation - 2026-11-11

A browser-storage warning prompted a read-only SDK investigation. The installed
SDK emits `ProtocolQuotaError` for a relay's storage or billing refusal.
Its local transaction commits and emits a receipt independently. The global
Evolu error store also carries this relay error. The previous broad quota
classifier labeled it browser storage exhaustion, and the local mutation
observer rejected the same error before receiving a valid local receipt.

Exclude only the exact `ProtocolQuotaError` discriminator from the local
outcome observer. Keep its receipt deadline, post-handler readback, all other
error handling and existing quarantine. The relay error remains in the SDK
and synchronization UI. Show the existing relay-limit explanation with a relay
heading. Measured browser pressure and local storage-error headings keep their
own evidence and can appear alongside the relay warning. No relay settings or
limits are changed by this correction.

Focused regression passes 295 tests across 15 suites. Typecheck passes the
110-finding baseline, scoped lint and five-locale parity pass. Independent
security passes 59 tests and accepts the bounded implementation. Real binary
protocol browser evidence and the final build are recorded in the follow-up
private evidence directory when complete.

Final isolated Chrome QA exercises the installed SDK's binary quota response.
The previous code rejected receipt observation despite a committed local write,
and the rendered banner claimed browser exhaustion at zero percent usage.
The correction permits a confirmed local import with exact payments and original
file digests. A deliberately missing receipt still times out and an actual
SQLite error still blocks mutation. Measured 85-percent pressure can appear
alongside the relay refusal. The native test deliberately delayed the receipt
by 30 ms to exercise quota-before-receipt ordering. The owner's actual error
object was unavailable, so this evidence establishes the mechanism and fix.

The final application build passes. Independent security accepts 50 paths,
manifest SHA256
`8a2178589fd309b327088a3b7406393762e135a1288973cc9efd334620aac1fe`.
Synthetic reports and exact manifests are retained privately under
`storage/app/private/reviews/action-center-migration/relay-quota-followup`.
The corrected observer and banner are served by the local preview.

### Local company list and Profile availability - 2026-11-11

The owner reported the company list and Profile statistics remaining in loading
states. A normal app.ts/router/bootstrap/browser reproduction confirmed that
local company queries could answer while the views awaited unrelated archive
queries. Fingerprinting and statistics used the complete backup snapshot,
including migration source bytes that neither computation consumed.

The snapshot API defaults to complete data. Only fingerprinting and statistics
explicitly omit migration originals and use bounded passive query reads.
Nonempty verified local company lists load without a forced relay pull when
the existing pending-recovery flag is clear. Empty lists and pending recovery
retain their existing remote recovery gates. Passive owner/query waits are
bounded before follow-on registration or synchronization steps.

Profile publishes local counts before relay usage completes. Automatic refresh
reads local statistics, and the explicit Refresh action can calculate database
size using a bounded export. Account generations, account ABA and unmount
checks invalidate stale statistics and owner-specific usage requests.

The integrated regression groups pass 109 tests across 12 suites, including
native snapshot, account seed, relay subscription, local companies and mounted
Profile recovery tests. Typecheck passes the existing 110-finding baseline.
Scoped lint passes with one existing Profile warning, locale parity and the
application build pass. Independent security accepts 62 paths, manifest SHA256
`ecbed5414a31c170ff4306b34737de46120672135d8cc5793136e58f50292864`,
and independently passes all 45 view/snapshot/Profile tests.

Normal-entry Chrome QA uses the same retained and verified synthetic owner
before and after the correction. With the actual decoded quota error and
archive replies withheld, the corrected company list and Profile counts show
the retained data. The views request zero archive queries and zero automatic
database exports. A complete default snapshot still requires the two archive
queries and preserves the CSV/PDF digests after they answer. The actual Profile
Refresh action performs one optional database export and displays its size.
The synthetic relays can remain unknown without holding the local views.

Final independent full-entry evidence is retained under
`storage/app/private/reviews/action-center-migration/local-loading-followup`.
The corrected production entry renders retained companies and Profile statistics
on the same verified synthetic owner. Complete CSV/PDF custody and manual
database export pass. The zero-of-two unknown relay fixture models missing
telemetry from an unobserved worker. It does not establish the owner's worker
age or actual provider connectivity.


## Zaprite adapter contract - 2026-11-11

The owner authorized proceeding with Zaprite and configured a read-only Sandbox
key locally. A bounded authenticated GET read verified three synthetic invoices,
two items each, including a confirmed same-currency partial payment whose
`amountInOrderCurrency` and `orderCurrency` are null. This case uses the exact
transaction amount only when its currency equals the invoice currency.

The adapter uses an account-authenticated ephemeral POST bridge to the fixed
Zaprite HTTPS orders endpoint. The provider operation is GET only. The transient
key is not retained or included in the encrypted source export. No automatic
provider writes, original-PDF retrieval or continuous synchronization is included.
The API result includes invoice and apiInvoice orders, including pending orders,
and requires complete coherent pagination before selecting a source. Limits are
20 pages, 2,000 orders and the existing 10 MiB source-file custody limit.

The browser converts supported two-decimal fiat invoices into the same migration
preview, duplicate checks and encrypted custody used by the spreadsheet importer.
Confirmed transactions must provide exact payment amounts and unique identities.
Source status alone never creates a payment. Line net, discount and VAT amounts
must reconcile exactly. The source organization, invoice and order IDs contribute
to the fingerprint, without renaming duplicate invoice numbers across customers.

BTC/LBTC and currencies with other decimal precision remain unsupported by this
adapter. They block the preview unless explicitly excluded. Exclusion counts are
shown before confirmation and the complete JSON source is retained, including
excluded invoices and non-invoice orders. This does not migrate their financial
records or claim original-PDF custody. JSON bytes are parsed again at commit,
then existing current-account/company/owner, receipt, readback and retry gates
apply. Native BTC precision support across invoicing is separate follow-up work.

Main owns route/UI/shared validation/import/archive/locales/docs. The frontend
specialist owns only the new converter and its tests, and backend owns only its
new controller/service/test. Independent security and QA must accept the final
implementation. No source publication or deployment follows from local preview.


### Zaprite verification and local acceptance

- Main exercised the actual fixed-host PHP client with the owner-configured
  read-only Sandbox key inside an isolated synthetic application environment.
  Status 200 returned three invoice orders; no Satflux invoice or provider write.
  A separate local conversion showed two ready supported invoices with two lines
  each and one explicitly unsupported BTC invoice. No customer details or IDs
  were printed or placed in committed evidence.
- Main migration/archive/backup/share regression passed 218 tests / 13 suites;
  the final converter passed 53. Independent security passed 131 frontend tests
  / 5 suites and repeated the backend's 31 tests / 151 assertions in guarded
  network-disabled SQLite-memory infrastructure with read-only source.
- Scoped ESLint passed without warnings, typecheck passed the unchanged 110
  baseline, all five languages passed locale parity (existing four SK extras),
  production build passed with existing chunk/zod annotation warnings, and API
  documentation export/inventory checks passed. Pint passed the three new PHP
  files; the final sorted route and a pristine route baseline both passed.
- Independent actual Chrome/Evolu wizard QA verified three account/source/company
  ABA cancellations, default BTC blocking and explicit exclusion, partial payment
  and VAT/item persistence, exact duplicate retry, conflicting source rejection
  and complete two-page API loading. Four synthetic documents, six detailed lines
  and two complete sources survived page reload and full Chrome restart with
  unchanged financial state. Both downloaded JSON files matched independent
  Node SHA256 and retained excluded BTC and other orders without the key.
- Independent security accepted the final cumulative 74-path manifest
  `1fa38f5159491a672c76f0f88dfcd7d54a779db5858a7710933a880c75ba571d`.
  Review closed credential flashing, total stream deadlines and a reproduced
  fractional-quantity gross-rounding discrepancy before acceptance.

Private sanitized evidence is retained under
`storage/app/private/reviews/action-center-migration/zaprite-followup` in the
primary checkout. Runtime checks used the existing primary Graphify graph because
this feature worktree has none; no graph was silently installed or rebuilt.
The complete source JSON is an API export, not an original invoice PDF. No native
BTC support, automatic source-PDF retrieval or background synchronization is
claimed. Local preview remains on the existing feature worktree and localhost
origin, without publication or deployment. The owner confirms any financial
import in the wizard after reviewing the selected company and rows.


### Final date-evidence and logo follow-up

Null Zaprite `daysToPay` means DUE ON RECEIPT and does not establish a concrete
due date. Because this importer requires a date, it now rejects that row instead
of substituting the issue date. Explicit zero and positive day counts retain
strict UTC calculation. The final 53-test parser suite covers this narrowing;
the actual Sandbox and browser QA invoices use explicit day counts.

The owner-supplied [Zaprite SVG](https://cdn.prod.website-files.com/64c01b910051916b9c45d480/64d80f45d3cd38d81bb452ac_zaprite-logo-nav-bar.svg)
is bundled unchanged at `public/img/zaprite.svg` and rendered as a same-origin
image in the source card, with the existing localized alternative text. A dark
background preserves the supplied mint wordmark's visibility. Static review
verified only eight inert paths, with no external resources or active content.
No provider request is needed to render the logo.

The full build/typecheck preceded these final bounded date/logo deltas. Final
parser tests, scoped lint, independent security and bounded UI checks cover the
narrowing and local image. The complete earlier import/restart evidence remains
attributed to the same explicit-day financial fixtures.


Independent final-delta QA passed 57 tests / 2 files, including null rejection
and unchanged explicit 0/14/30-day calculations. A fresh step-one browser verified
the local SVG, translated accessible image/button name, dark background and no
390px horizontal overflow, with zero company/document/contact rows. No financial
write was needed for that final check. The coordinator verified all 625 final
QA source hashes with zero mismatches, as well as all 74 security checksums.
The primary private evidence folder retains the final QA report, accepted source
manifest, original full-browser manifest and custody/logo results.


### Owner confirmation and saved JSON wording

The owner confirms Zaprite API import works in the localhost preview. The file
label and invalid-export message now identify Zaprite as the data source and
this wizard as the JSON producer in all five locales. The versioned Satflux
export envelope remains required; arbitrary provider JSON is not newly accepted.
This copy-only follow-up does not alter the previously reviewed runtime logic.
Prior review manifests retain their exact earlier locale bytes. Locale parity
and diff checks cover this text delta.


## Authorized GitHub publication gates - 2026-11-11

Owner authorizes source publication on the existing feature branch. Freshly
fetched owned master is still the original `6e6c0587` base. The reviewed 124-file
allowlist excludes the local preview configuration and dependency recovery link;
no private invoice, key, browser profile, database or machine-specific new path
is included. Source-only publication leaves production activation separate.

The final complete frontend suite passes 2,540 tests / 225 files with zero
failures, skips or todos. Combined backend coverage passes 80 tests /
665 assertions across seven suites. It uses masked synthetic configuration,
network-disabled disposable SQLite and the documented dompdf driver. Pint passes
17 PHP paths, canonical scoped PHPStan passes 11 runtime paths and API export
freshness passes. Additional route-only static findings were verified against
unchanged base code and are outside the configured app-only PHPStan scope.

Full lint passes with zero errors and 48 warnings under the unchanged limit of
52. Five-locale parity passes with the four existing informational SK extras.
Final full typecheck passes the unchanged 110 baseline, build passes, changelog
71 tests and 14 committed fragments pass, FAQ validates 12 questions / 5 locales /
39 sources, and all three OpenAPI documents validate. The production dependency
critical gate passes with zero critical findings. The existing 11 high and
39 moderate advisories are unchanged dependency nodes; none targets the added
PDF.js/canvas nodes. Initial default-sandbox changelog helpers could not spawn Git;
the offline checks passed with the normal approved execution boundary.

Private publication evidence is under
`storage/app/private/reviews/action-center-migration/publication-followup` in the
primary checkout. Previous browser restart/source-digest evidence remains valid;
this gate does not claim an additional browser run. GitHub CI is not yet observed.
The canonical PR URL and reviewed changelog follow the initial source push.
