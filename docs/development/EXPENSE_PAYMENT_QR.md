# Expense supplier payment QR codes

## Authorized scope and dependency

The owner requested PayMe/PAY by square QR codes on unpaid expense rows when
supplier payment details are available. This work is isolated on
`feat/expense-payment-qr`, depending on PR451 at `8feb1e8a`. PR451 remains open and
unmerged; these changes do not establish deployed behavior. No external repository,
production database, bank application, wallet or real payment is used.

## Acceptance and contract

- A visible desktop/mobile row action opens one native modal with PAY by square
  and PayMe choices and explicit recipient, IBAN, amount and VS.
- Only recorded unpaid EUR expenses with a positive amount (up to 12 integer
  digits), a unique explicit supplier name/account, checksum-valid IBAN and
  1-10 digit VS qualify. Leading zeros are retained. Supplier name is required by
  both current standards; it is not guessed from a generic expense title.
- Paid/cancelled records, conflicting bank evidence, nonzero or unresolved imported
  payments and ambiguous metadata have no action. An authoritative remaining
  balance is not invented from partial-payment notes.
- No company-account fallback, attachment scan, external QR image request, new
  dependency, schema migration or payment-state mutation is introduced.
- Company, row membership, paid state or payment detail changes close an open
  preview. Late asynchronous images are ignored. Native dialog focus restoration,
  keyboard use, background inertness and 320px layout are verified.
- Explicit PayMe navigation opens only the fixed official origin with no referrer.
  Generating either QR stays local; scanning/opening is not settlement evidence.

## Protocol and supplier provenance

The browser encoder implements bounded PAY by square 1.2.0 single credit transfers
with CRC32, literal-only LZMA1 (lc3/lp0/pb2, 128 KiB dictionary, EOS), base32hex,
alphanumeric QR mode and correction level L. PayMe uses editable `/2/q/PME`
Payment Link Standard 2.0 instructions, EUR, creditor name and `/VS` identification;
no scheduled date is added. Sources: [SBA PAY by square 1.2.0](https://www.sbaonline.sk/wp-content/uploads/2020/03/bysquare-PAYspecifications-1.2.0.pdf)
and [SBA PayMe 2.0](https://www.payme.sk/docs/Payment_Link_standard_2_0.pdf).

CSV/Excel already retain explicit supplier-note metadata. New UBL/ISDOC extraction
retains it only for an unambiguous direct creditor instruction. Credit notes,
nonpositive original payable amounts, partial/prepaid instructions, multiple terms
and conflicting account-owner names suppress the account. UBL explicit PayeeParty
also suppresses it because the expense model has no independent factor identity.
ISDOC invoices declaring a foreign currency withhold bank metadata because local
bookkeeping amounts do not identify the foreign payment instruction. Re-extraction
removes previous supplier/account metadata while preserving user prose; explicitly
cleared notes remain cleared on server edits. Existing
expenses whose original import discarded supplier bank details remain ineligible.
Legacy gross-total display fallback remains; a nonpositive or ambiguous original
payable amount cannot enable a payment action. Payment references now require a
supported explicit seller instruction: one direct UBL PaymentID (plain numeric or
exact /VS notation) or an explicit ISDOC VS. BuyerReference and invoice numbers
remain routing/display data. See [review corrections](PR452_REVIEW_FIXES.md) for
reference replacement, shared country lengths and direct-root financial binding.

## Independent review and validation

QA and security accepted the same 20 source/test/locale blobs with aggregate
`80ba25a20302921fd1aa0bb68f4453bfc1f65d19e1e188084b62742c17f903ff`.
The source-freeze manifest and synthetic browser evidence remain in ignored local
review storage, without production data.

- Independent focused frontend: 114 tests; final QR-renderer option correction
  rerun: 13 tests. Counts overlap and are not summed.
- PAY by square encoder: 19 tests, including deterministic fixture, independent
  xz decompression, decoded-length and CRC verification, Unicode and bounds.
- UBL/ISDOC: 37 tests / 202 assertions, independently repeated in a network-none,
  read-only-source SQLite-memory test container. Scoped Pint and PHPStan pass.
- Nine actual Chrome scenarios pass at desktop and 320px. Both displayed QR images
  decode locally. The actual PAY by square image payload independently decodes
  with raw LZMA/CRC and exact recipient/amount/VS checks. Escape, focus return,
  background inertness and stale-data invalidation pass with zero external requests
  or payment events. No live banking app compatibility or transfer was exercised.
- Full lint passes with 46 existing warnings against the unchanged cap of 52.
  Typecheck passes with 110 existing baseline errors and no new errors. Five-locale
  parity passes; four existing Slovak-only keys remain informational. Build passes
  and includes the final alphanumeric/L renderer configuration.
- The first fully parallel JavaScript run passed 3821/3824 tests; three existing
  large-archive/footer scenarios hit time limits under concurrent review/build
  load. All three files pass on the single-worker retry: 55 tests. No timeout
  or warning/typecheck ratchet was increased.

Graphify was queried in the primary checkout before exploration. The isolated
worktree has no graph; it was not silently rebuilt or installed. EN/SK/ES/DE/CS
strings and guides are updated together. No security blocker remains after
rejecting nonpositive-payable and split-instruction cases found by review.

## Related PR451 review disposition

All three supplied CodeRabbit findings were valid and fixed in `8feb1e8a`:
composite keyset bank scanning, restored numeric-input validity and successful-load
issue-period filter snapshots. Independent QA/security accepted that six-file
source/test delta; 66 JavaScript tests and 9 PHP tests / 85 assertions passed.
Existing overdue enums still resolve against the current day. This related
preexisting behavior is outside the three requested corrections and is not claimed
as frozen by the issue-period snapshot fix.

Next: review the dependent QR PR and its exact-head CI. PR merge and deployment
require applicable owner approval; no release, merge or deployment is performed.

## Supplier payment QR source published as PR452 - 2026-11-17

Reviewed QR source `e0dab7a7015842c76ad05c164c56216b565c5536` is pushed to
`feat/expense-payment-qr`. [PR452](https://github.com/webiumsk/satflux/pull/452) is
open and unmerged against PR451's `fix/expense-attachments-bank-direction` at
`8feb1e8a6e5af1ce19cbf85d4ecfe0a775dd1a7c`. The diff contains 30 scoped paths;
this follow-up adds publication documentation and one changelog fragment only.
The 20 reviewed source blobs retain aggregate
`80ba25a20302921fd1aa0bb68f4453bfc1f65d19e1e188084b62742c17f903ff`.

Current CI only triggers PRs targeting master/main. PR452 therefore requires
retargeting and exact-head CI after the approved PR451 merge; local QA/build
results are not GitHub CI for this dependent PR. Neither PR is merged or deployed.
The public changelog is deferred until its normal approved deployment workflow.
See [implementation evidence and limits](EXPENSE_PAYMENT_QR.md).
