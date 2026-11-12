# Fakturoid migration

Date: 2026-11-11. Owner-authorized continuation of the migration initiative.
Branch: `feat/invoicing-fakturoid-migration` from freshly fetched owned master
`007b9c027c7887d59300a781e861ad09ecb94e06`, which merged PR #438.
The existing preview checkout and local settings are preserved. Unrelated primary
checkout work remains untouched. No publication, merge or deployment is claimed.

## Verified source and supported contract

The owner supplied a genuine native XML export. A read-only local probe established
its structure and reconciled the initial two unpaid invoices and three items.
The owner then updated the export with a partial and full payment: four invoices
and five items now prove the native payment representation.
No merchant invoice, identifier, amount, token or source path is copied into this
note or the synthetic fixture. The probe performed no financial writes.

The [official export guide](https://www.fakturoid.cz/podpora/faktury/exporty)
and [item export guide](https://www.fakturoid.cz/podpora/faktury/export-polozek-do-csv)
identify native XML as a detailed export. Public research alone did not establish
its grammar. The supplied native sample now establishes the initial unpaid
subset and its update establishes the payment matrix below; API JSON and
spreadsheet field names are not substituted for XML evidence.

The adapter supports ordinary unpaid, partially paid and fully paid invoices in
the account currency, `without_vat` unit pricing, zero document rounding and exact
line, VAT-rate and document arithmetic. It preserves signed price deductions when
all arithmetic and supported tax buckets reconcile. Required issue/due dates,
issuer registration, historical buyer data, source IDs and line data are checked.
Whitespace in an eight-digit registration number is normalized without losing
leading zeros. The issuer must match the freshly read selected company.

Native `paid_amount` must exist, be exact and satisfy `0 <= paid_amount <= total`.
Zero payments with open/sent/overdue status are unpaid with no payment date.
A positive amount below total with those statuses is partial; the authentic
partial record has empty `paid_on`, preserved as unknown (`null`). A full amount
requires `paid` status and valid actual `paid_on`. A zero-payment cancellation is
also supported. Negative/overpaid amounts, contradictory statuses, partial dates
and missing full-payment dates are blocked. No payment event history is invented.

The authentic partial and paid records retain `remaining_amount == total` and
`remaining_native_amount == native_total`, directly proving these are coverage,
not unpaid balances. Balance is derived only as total minus `paid_amount` under
FX1 and native/document currency equality. Changed payments on completed imports
produce a conflict; the adapter never overwrites an already imported document.
Unsupported advances, corrections, foreign-exchange semantics, tax modes,
rounding and currencies remain visible errors before any archive or financial
writes. The entire batch is blocked if a row is invalid or conflicting.

The [official invoice API](https://www.fakturoid.cz/api/v3/invoices) documents
`remaining_amount` as document-link coverage, not unpaid balance. No positive
payment inference is made from that field. The
[API authorization contract](https://www.fakturoid.cz/api/v3/authorization)
requires Authorization Code Flow for multi-tenant applications. This file adapter
requests no account credentials and does not add a Client Credentials connector.

## Implementation and integration

The local-first migration source picker offers Fakturoid XML with a dedicated
file step, explicit scope, preview and shared confirmation. Legacy server invoicing
does not advertise the adapter. All product copy has EN/SK/CS/DE/ES parity.

`migrationFakturoid.ts` parses bounded strict UTF-8 XML locally, rejecting DTDs,
entities, processing instructions, namespaces, duplicate fields/IDs, excessive
nesting/nodes and unsupported source semantics. It projects only approved fields;
source tokens, URLs and private notes remain solely in the preserved raw source.
No external resources are fetched and no new plaintext upload is introduced.

`buildPreparedMigrationPreview` supplies existing tenant/company scoping,
duplicates, number conflicts, deterministic fingerprints, resume proof and totals.
Fakturoid source identity is an optional discriminated addition; existing
SuperFaktura/Zaprite fingerprints are preserved. At commit the import copies source
bytes before awaiting, reparses that snapshot under the financial lease against
the current issuer, compares the displayed preview and checks issuer/owner before
writes. Subsequent reconciliation reuses prepared rows with fresh local records.

The complete XML is archived before financial records. Optional originals retain
batch custody without changing XML items. Existing schema, owner/account, receipt,
readback, quarantine and restart recovery rules remain in force. Limits remain
10 MiB per file, 20 MiB per batch, 2,000 invoices and 50 optional originals, with
500 lines per invoice and bounded XML nodes/depth. Source/account/company changes
invalidate pending reads and confirmation; stale operations cannot publish results
or clear another operation's loading state.

## Acceptance and ownership

| Owner | Writable scope and responsibility |
| --- | --- |
| Main | Shared import/preview/archive contracts, wizard, five locales, documentation and publication |
| Frontend specialist | `migrationFakturoid.ts`, its parser tests and sanitized XML fixture only |
| Independent security | Read-only source/contract/finance/custody/isolation review; private report only |
| Independent QA | Read-only source; isolated synthetic browser, import/restart/ABA and regression evidence |

Acceptance requires exact unpaid and four-invoice/five-line payment transfers,
correct recorded amounts/dates/balances, original byte custody and retry, explicit
rejection of inconsistent payments/company/finance, preserved SF/Zaprite behavior
and unchanged quality ratchets. The real owner export is tested read-only and is never imported by the
agent. Synthetic fixtures preserve schema only and contain no customer records.

Primary Graphify was queried before exploration. The feature checkout has no
graph; no graph was installed or rebuilt. Sanitized discovery evidence is retained
privately under `storage/app/private/reviews/fakturoid-migration/source-discovery`.
Anonymous browser state is excluded.

## Verification

Final 13 migration, import and backup regression suites pass: 313 tests. Coverage
includes strict XML, exact money, issuer change after archive, buffer mutation,
ignored caller rows, token exclusion, exact partial/full amounts and dates,
inconsistent-payment blocking, changed-payment conflicts and duplicate retry. Full typecheck retains the existing 110-error baseline with no new errors
or baseline update. Scoped ESLint has no warnings; full ESLint passes with zero
errors and 48 inherited warnings under the 52-warning ratchet. Five-locale parity
and production build pass; existing large-chunk/dependency annotation warnings
remain. Independent security review accepts the unpaid core and the separate
payment expansion with no open Critical/High/Medium findings. Its final independent
payment run passes 232 tests across four suites; the earlier unpaid run passed 218.
Independent browser QA passes: two unpaid invoices and three detailed lines,
correct VAT and totals, duplicate/conflict blocking, five unsupported-input
rejections and issuer change after preview with zero archive/financial writes.
All six delayed load/confirm account/source/company ABA cases retain the newer
loader and suppress stale results; an extra delayed-error catch case also passes.
SuperFaktura/Zaprite controls pass smoke checks. Page reload and full browser
restart preserve records and exact SHA-256 downloads of XML and an optional
synthetic PDF, with zero page errors and zero backend business writes. QA also
passes an independent 179-test/five-suite focused run.
The updated real XML also passes a read-only parser probe: four ready records,
five items, two unpaid, one partial with unknown payment date and one paid with an
actual date. Source issuer was used only as the probe reference, without claiming
a destination-company match. No owner-company or browser profile was mutated.
Final independent paid browser QA also passes the four-invoice/five-line
preview and actual import, exact partial/full amounts and dates, overpayment
rejection with zero writes, duplicate retry and changed completed amount/date
conflicts with unchanged records. Page reload and full browser restart preserve
all records and the exact XML SHA-256. No page errors or backend business writes
were observed. These checks extend the accepted unpaid/shared-lifecycle checkpoint
without claiming a second execution of its unchanged UI race suite.
No publication or deployment is claimed.

## Owner acceptance and logo - 2026-11-12

The owner confirms the import works. The source picker now uses the supplied
[official logo](https://cdnweb.fakturoid.cz/images/svg/fakturoid-logo.svg), stored
locally as `public/img/fakturoid.svg`, with the existing translated source label
as alternative text and the same displayed width as the other provider logos.
The SVG contains only static paths, without external references or active content.
Scoped ESLint and Vue template compilation pass. An isolated browser rendering of
the actual source-picker button template verifies the image loads at 120 px wide.
No import logic or locale keys changed. Primary Graphify was queried; the feature
checkout still has no graph, and none was installed or rebuilt for this small edit.

## Publication handoff - 2026-11-12

The owner requests pushing the feature branch and will create the PR personally.
Fresh owned master remains the exact `007b9c02` feature base. The publication
allowlist contains 20 feature files; local preview settings, the dependency
recovery link and all private source/browser artifacts are excluded. Existing
functional/security evidence is verified against candidate hashes, with the
later local-logo-only delta separately checked. Independent privacy and QA
binding accept that exact candidate. Source commit `c42aa652` is pushed and
verified on the remote feature branch. No PR, merge or deployment is included.
The changelog
fragment follows the owner's actual PR so its canonical source URL is real.
