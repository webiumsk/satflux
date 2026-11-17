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
