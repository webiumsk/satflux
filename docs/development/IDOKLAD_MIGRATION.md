# iDoklad XLSX and original PDF migration

Date: 2026-11-12. Owner-authorized continuation of the migration initiative.
Branch: `feat/invoicing-idoklad-migration`, based on freshly fetched master
`bae0295f9c071f37eb219b65129bd45ed4b0b080` after owner-merged PR #439.
The existing local preview and unrelated primary-checkout changes are preserved.
This is local implementation evidence; no publication or deployment is claimed.

## Verified source and supported scope

The owner has invoice-list XLSX/CSV exports, without Pohoda XML. The initial
12-column workbook lacks actual paid amounts and original items. The updated
workbook contains 22 columns, a four-row preamble, native Excel dates and four
records: fully paid, partially paid and two unpaid (including overdue).
It supplies exact net/VAT/gross, actual paid amounts and buyer identifiers.
The fully paid record has a payment date; the partial date remains unknown.

All four matching original Slovak PDFs were inspected read-only. They have native
selectable text, no embedded ISDOC or associated files, and five original lines
in total. Main's private source-format probe parses all four records and reconciles
all lines and totals. Its issuer reference is taken from the PDFs for this probe;
it does not assert a match to any destination company or import owner records.
No merchant values, source paths or owner files are included in tracked fixtures.
The Czech announcement about embedded ISDOC does not establish this Slovak format.

V1 accepts the verified 22-column XLSX and the matching native Slovak company
invoice PDFs. Each page must contain one complete supported invoice, with
8-digit supplier and buyer registration identifiers. CSV, scans/OCR, consumer
invoices without buyer registration, unsupported templates, advances and
corrections are outside this profile. Unsupported evidence blocks the whole batch.
No API subscription, credentials, external extraction service or upload is used.
The source picker includes the owner-supplied official logo as a static local SVG.
The owner-requested visible order is SuperFaktura, iDoklad, Fakturoid, Zaprite, XLS.

## Source contract and finance

The XLSX title and exact row-5 header are validated, with a single sheet and native
numeric money/date cells. Identifiers remain text, including leading zeros.
Formula/error cells, ambiguous ZIP structures and bounded-resource violations
fail closed. ZIP contents, the active workbook/worksheet relationship and physical worksheet
coordinates are checked before trusting the spreadsheet reader's declared used
range. A worksheet may omit the optional dimension; a present dimension must
contain every physical cell, and duplicate row/cell addresses are rejected. Generic spreadsheet
imports keep their existing behavior; the row-5 profile is opt-in.

PDF text is read by a same-origin owned PDF.js worker, with initialization/read
budgets, abort/teardown, byte/page/item/text limits and no fallback to UI-thread
parsing. Native text geometry, document profile, identities, dates, line columns,
VAT buckets and invoice totals must be complete and unambiguous. Existing
SuperFaktura ISDOC extraction retains its attachment contract.

Each PDF must match a unique XLSX record by invoice number, issuer, buyer,
currency, issue/delivery/due dates and all three net/VAT/gross totals. Extra,
missing, duplicate, unreadable or mismatched originals block the complete batch.
The source's known VAT is never replaced with a generic zero-tax summary.

Original price, quantity and tax rate prove each line. iDoklad uses `per_line`
rounding: VAT buckets and invoice totals equal sums of the rounded original lines,
not a second rounding of aggregate raw values. Signed deduction lines preserve
their signs and still satisfy line, bucket and document checks. Existing Fakturoid
and ISDOC arithmetic is unchanged.

Payment authority comes from explicit XLSX amounts and dates. A status label alone
cannot establish an amount. Fully paid requires exact full payment and its source
date. A partial payment with no source date remains unknown. PDF labels such as
`Spolu na úhradu` print the original gross even on paid invoices; they are not
used as outstanding balance or payment evidence.

## Preview, custody and commit

The dedicated source step requires XLSX plus original PDFs, displays supported
scope, and leads to a per-record preview with original lines and payment states.
All five locales have matching keys and finite sanitized source errors.

Commit copies XLSX and every original byte buffer before callbacks or awaits,
then reconstructs the source and PDF evidence. Caller preview rows or mappings
cannot authorize records. Extraction runs outside the financial lease; the final
join uses fresh company registration and existing owner/company authority under
the lease. Exact original bytes are archived before fiscal writes. Existing
fingerprints, duplicate/conflict checks, readback, recovery, retries, cancellation
and account/company/source change guards remain in force.

The existing generic migration tables and version-2 backup representation retain
this source kind without a fiscal schema or backup-version change. Financial and
original-document data remain under the existing Evolu encryption boundaries.

## Validation and handoff

- Main's private read-only real-source probe on the final parser: four ready
  records, five original lines, exact totals, known full-paid date and unknown
  partial-paid date. No owner financial writes.
- Final integrated regression: 474 tests across 17 suites passed.
- Foundation correction regression: 131 tests across three suites passed.
- Independent security: 430 tests across 11 suites passed, including separate
  understated-dimension, active-sheet relationship alias, Excel 1904 and omitted
  optional-dimension probes. No open Critical/High/Medium findings.
- Independent isolated browser QA: four invoices/five detailed lines, exact
  full/partial/unpaid amounts and payment-date semantics, duplicate retry,
  completed-payment conflicts and unchanged records after reload/full Chrome
  restart. All five XLSX/PDF downloads match original bytes and SHA-256 twice.
- Browser negatives: ten rejection cases with zero custody/fiscal writes; six
  account/company/source ABA cases, held-PDF cancellation, stale-error suppression
  and fresh-issuer rejection. A canceled scan's stale progress label was corrected
  and independently rechecked. No backend business writes or app page errors.
- Final narrow QA: seven parser/order checks pass against the frozen source,
  including valid export acceptance and malformed-dimension/relationship rejection.
- Full ESLint: zero errors, existing 48 warnings within the unchanged ratchet.
  Five-locale parity passes with the four pre-existing Slovak-only keys unchanged.
- Final full typecheck passes with the unchanged 110-error baseline; no new
  diagnostics. Production build passes after the last corrections, retaining
  existing bundler annotation/chunk-size advisories.

Review corrections bind the actual workbook/worksheet relationship and effective
Default/Override content types to the validated physical sheet. Every physical
coordinate is validated; a legitimate absent optional dimension remains supported.
The generic importer is unchanged and no baseline is expanded.

Primary Graphify was queried. No graph exists in the feature checkout; none was
installed or rebuilt. Owner source inspection stayed private and read-only.
No provider mutation, production test, real payment, source push or deployment
was performed. The next gate is local owner acceptance; publication requires the owner's
applicable instruction.
