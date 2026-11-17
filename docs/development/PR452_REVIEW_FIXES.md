# PR452 payment instruction review corrections

## Scope and disposition

The owner requested stack monitoring and a fresh CodeRabbit review. The owner
triggered PR452 review on `481e9c4c`; review5930387309 reported three inline findings
and one outside the diff. All four were verified as valid and corrected:

1. All five expense guides now state positive amounts, the 12-integer-digit bound
   and supplier names up to 70 characters. Missing extracted references remain
   blank for confirmation, while ordinary manual-entry defaults are preserved.
2. Applying a replacement extraction always replaces or clears its VS alongside
   supplier metadata. It never retains a prior supplier's VS or invents a payment
   reference from an invoice number. Server updates now persist explicit null VS;
   omitted fields still retain the previous value. Local Evolu already preserves
   explicit null references.
3. Browser eligibility, PAY by square and both PHP importers use the same pinned
   country-length registry before checksum verification. Unknown countries and
   checksum-valid wrong-length accounts are rejected, including the short SK77
   example. ASCII normalization rejects controls and Unicode lookalikes uniformly.
4. UBL uses one direct credit-transfer PaymentID for seller reconciliation, never
   BuyerReference routing data. Only plain 1-10 digits or exact `/VSdigits` are
   mapped, preserving zeros. Missing, duplicate or unsupported references withhold
   payment metadata. ISDOC likewise needs an explicit numeric VS; a numeric invoice
   ID is not a seller payment instruction.

Independent security review also identified two related issues. The explicit-null
server persistence issue is covered above. UBL descendant financial XPath could
select an extension snapshot instead of root invoice amounts/currency. Parsed
fields now use direct-root paths. Payment metadata requires unique root currency,
monetary total and positive canonical payable amount, consistent currencyID when
provided, and at most one explicit zero prepaid amount. Display-only gross-total
fallback remains; ambiguous or nonpositive payable evidence cannot enable a QR.

No settlement, tenant authorization, subscription, entitlement or production-state
behavior changes. Supplier account, amount, currency and reference remain bound to
one supported document instruction. No new dependencies or schema migrations.

## Primary source evidence

- [SWIFT IBAN Registry, Release 103, September 2026](https://www.swift.com/sites/default/files/files/iban-registry_4.pdf): 89 registered country/length pairs in resources/data/iban-lengths.json. All 89 entries were compared with the official chapter structure formulas; representative entries were independently checked. JSON SHA256 is `8cd43068c53b59201680b4dd8535990f2b72044b637711cf7d7f76d2392e75ef`.
- [Peppol BuyerReference BT-10](https://docs.peppol.eu/poacc/billing/3.0/syntax/ubl-invoice/cbc-BuyerReference/) is routing data; [PaymentID BT-83](https://docs.peppol.eu/poacc/billing/3.0/syntax/ubl-invoice/cac-PaymentMeans/cbc-PaymentID/) carries seller reconciliation information.

## Freeze and validation

Independent QA and security accepted both final source manifests. The 17 changed
source/test/data blobs against `481e9c4c` have aggregate
`3b8f3f9b85cb97cd918610409525d4183082dc8b26afb07496f969b5fb22a0b3`.
The complete current QR scope has 27 source/test/locale/data blobs against PR451
`8feb1e8a`, aggregate
`b2e4ba1b1fb440b7da8f731be681c8dff8fe020e3ac21043516183575a1f3436`.
Ignored local manifests retain each file hash and independent reviewer evidence.

- Focused frontend: 141 tests / six files passed independently, including document
  replacement, persisted payloads, eligibility, country checks and QR decoding.
- Import/IBAN PHP: 85 tests / 449 assertions passed independently, including 21 new
  direct-root financial/ambiguity regressions. Server expense API: 13 tests / 83
  assertions passed, covering omitted VS versus explicit null and reopening.
  Independent security also repeated the combined scope: 98 tests / 532 assertions.
- Nine actual Chrome scenarios at desktop and 320px passed with final browser
  helpers. Both bitmaps decode; actual PAY by square payload passes independent
  raw-LZMA, decoded-length, CRC and exact recipient/amount/reference checks. No
  external requests, browser errors, payment events or live transfers occurred.
- Full lint passes with 46 inherited warnings and unchanged cap52; typecheck
  retains the 110-error baseline with no new errors. Locale parity and build pass.
  Scoped Pint and PHPStan pass; PHPStan used a local memory flag after default
  worker-memory exhaustion, without changing repository configuration or baseline.

Sandbox process restrictions required normal approved retries for nested xz and
Docker execution. No source/test timeout or warning baseline was increased.
Graphify was queried in the primary checkout; the isolated worktree has no graph.
No graph installation or rebuild was performed.

## Stack and handoff

PR451 remains the base, unchanged at `8feb1e8a`; all three original review threads
are resolved after verification of the successful-load issue-period fix. Its five
CI jobs pass. PR452 retains its dependent base branch. These corrections are
published together after independent QA/security acceptance, preserving stack
history. Verify exact-head CI after the push; earlier green checks are attributed
to `481e9c4c`, not to this correction. No PR merge or deployment is performed.

## Second CodeRabbit pass and payment-metadata clearing

The owner-authorized review of `7e8c0764` completed in review5931052198 with two
new findings. Both were verified as valid. Declared foreign-currency ISDOC invoices
now withhold supplier bank metadata: local bookkeeping PayableAmount is not the
foreign PayableAmountCurr payment instruction. Legacy displayed values remain
unchanged. The ordinary null-ForeignCurrencyCode path remains eligible. The five
recovery guides now explain that a missing reference also omits the bank account;
a QR remains unavailable until name and IBAN are complete and verified.

Independent security verification also reproduced a related manual-edit issue:
clearing the private note sent explicit null, but server updates retained the old
supplier/account note while saving a new VS. Updates now distinguish an omitted
note from explicit null. An API regression verifies omission, clearing, reopening
and removal of the canonical payment metadata.

The follow-up has four source/test blobs against `7e8c0764`, aggregate
`5a8d56bcec7885be22fc7bfc12dc76b29f6f99b2158c734d32eb5029b64de26f`.
The complete QR scope remains 27 blobs with current aggregate
`4686938f35a5d7279feaad76f5cd75c880a77261e4af97b88d2f3512f22568bf`.
Affected PHP suites pass 29 tests / 271 assertions, with scoped Pint/PHPStan and
locale parity passing. Independent QA and security accepted the same hashes and recovery text. JavaScript/UI files are
unchanged, so earlier browser/client validation remains attributed to unchanged
blobs rather than presented as a new run.

Primary format evidence: [official ISDOC 6.0.2 schema documentation](https://isdoc.cz/6.0.2/doc-en/isdoc-invoice-6_0_2_xsd.html)
distinguishes local PayableAmount and foreign PayableAmountCurr. Both foreign
fixtures failed before the guard and pass afterward; the normal control passes.

CodeRabbit reviews remain per pull request. The fresh child reviews do not
constitute another review of parent PR451 head `8feb1e8a`; its last delivered
automated review covered `0e9ea219`. Parent corrections have independent QA/security
and green exact-head CI, and the original threads are resolved. No parent fresh
CodeRabbit approval, PR merge or deployment is claimed.


## PayMe amount representation

A direct check of the official [SBA Payment Link Standard 2.0](https://www.payme.sk/docs/Payment_Link_standard_2_0.pdf),
section 3.4 Table 1, confirms AM is String(9). The existing general expense amount
limit can exceed that field. The helper now rejects unsupported PayMe amounts,
while losslessly compacting only insignificant zeros when needed. Examples:
`1000000.00` becomes `1000000`, `1000000.10` becomes `1000000.1`, and
`1000000.01` cannot produce PayMe instructions. Exact integer-cent comparisons
cover the supported boundary values. PAY by square preserves the original
canonical amount and remains available. The dialog disables only PayMe with a
translated explanation and resets a newly unsupported active choice safely.
Five user guides explain the available alternative without exposing encoding
details in the payment flow.

Independent QA and security accepted the nine changed source/test/locale blobs
against `7270625a`, aggregate
`e8b5a6c229233092ab261889995105c3994e304b67d8c7190dbebb1024b2c8c8`.
The complete pre-integration QR scope remains 27 blobs, aggregate
`f11488d9bbcab9b5724be9d0e7e93eae6ddc139ff9b2dfcba88712e1864cb7e1`.
Author checks pass 80 helper/dialog/encoder tests; independent checks pass 66
helper/dialog/table tests. Counts overlap and are not summed. Security additionally
runs 69 integer-cent boundary probes, with 39 accepted and 30 rejected amounts.
Seven real Chrome scenarios pass at 1440px and 320px: `1000000.10` produces exact
AM `1000000.1`; `1000000.01` disables PayMe alone, and its actual PAY by square
bitmap independently decodes through raw LZMA/CRC to the original amount, IBAN and
VS. Focus, Escape, background inertness and width bounds pass with no external
requests, payment events or browser errors. Scoped ESLint and locale parity pass;
the one test-only href type error was corrected without altering the baseline.
Final stack integration and exact-head CI remain separately verified steps.
