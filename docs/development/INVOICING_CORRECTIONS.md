# Invoice deductions, payment provenance and buyer identity

## PR443 PHP CI preflight repair - 2026-11-13

Run `43502893971` at PR head `c71c4aed` fails before PHPUnit at
`api-docs:export --check`: the generated route inventory still records old
hashes for the changed Woo contact service and business invoice model.
The required API freshness check was omitted from the earlier local validation.
PHPUnit, Pint and PHPStan were skipped in that CI job; the other four jobs pass.

Regenerate with the existing guarded exporter in the isolated, uncached SQLite
in-memory fixture. The diff is exactly two source SHA256 values in
`docs/api/route-coverage.json`; all 460 routes/actions/middleware, the three
OpenAPI documents and runtime source remain unchanged. Independent QA accepts
artifact SHA256 `b70fd1dbc7f321af32d67419ec59396f226d07ace8774572e4adebd15a678569`.
Freshness, three schema validations, the validator regression and API tests
11 / 230 pass, with one inherited PHPUnit deprecation.

This correction changes no product, auth, dependency or CI behavior. Publish on
the existing owner-created PR443, then verify its new-head PHP job. No merge or
deployment. Public CI evidence:
[PHP job](https://github.com/webiumsk/satflux/actions/runs/43502893971/job/132152970297).

## Publication - 2026-11-13

The owner authorizes push. Implementation commit
`165d315e2023836d43af59a3c1d7647b0b56d491` publishes the 67 scoped files to
`fix/invoicing-lines-payment-identities` in the owned Satflux repository.
A fresh remote-ref read verifies that implementation commit. The source/config
fingerprint is unchanged; only final guide newlines were normalized. This
documentation-only follow-up records publication. Earlier uncommitted/no-push
statements below describe their dated local milestones.

No PR, merge, release, existing-database migration or deployment. CI success is not
claimed from this push. Local validation and independent acceptance remain as
recorded below; PR/CI review and deployment approval are separate next steps.

## Approved follow-up: negative quantity deductions - 2026-11-13

The owner accepts entering a manual deduction as `-1 x 15` with a positive
unit price and a positive final invoice total. Implement this directly in the
existing correction worktree, preserving the previously reviewed payment/buyer
fixes and imported source history. Earlier results below describe the preceding
negative-price milestone and are not presented as rerun evidence for this change.

Acceptance and integration contract:

- Ordinary SK/CZ/manual-US invoices accept nonzero negative quantities at strictly
  positive unit prices. Credit notes support the same quantity representation so
  an invoice with a deduction remains correctable through the existing derived
  credit-note workflow. The original invoice remains unchanged. Zero quantity,
  double-negative quantity/price, unsupported document types/providers and
  zero/negative document totals remain invalid. Negative unit-price support stays
  confined to the preceding ordinary-invoice visual/PDF contract.
- A negative-quantity deduction is a manual financial line without a stock item
  or warehouse link; it cannot silently add inventory. Existing stock-sale/return
  flows remain unchanged.
- A signed line is detected by negative quantity OR negative price throughout
  local save/issue, preview, persisted snapshots, ephemeral requests and guards.
  Retain existing canonical precision, VAT bucket/source-total checks and signed
  rounding. Preserve positive-only behavior and historical migration evidence.
- PDF/email/checkout and UBL/ISDOC/ePostman preparation agree on line amounts,
  VAT, payable total and saved identity. XML uses the actual negative quantity
  and positive price; never negate the price or fabricate another document.
- Negative unit prices remain unsupported by structured exports. Existing
  ePostman restrictions on separate line/document discounts remain unchanged;
  UBL/ISDOC quantity deductions also reject these unsupported combinations.
- Verify emitted XML with existing schemas and relevant amount/rule checks;
  use fake HTTP only. No provider acceptance, actual delivery or live send is
  claimed without separate evidence. No new schema, external repository change,
  package installation, publication or deployment is part of this follow-up.

Primary sources: [Peppol negative invoices and credit notes](https://docs.peppol.eu/poacc/billing/3.0/bis/#_negative_invoices_and_credit_notes),
[BR-27 positive item price](https://docs.peppol.eu/poacc/billing/3.0/rules/ubl-tc434/BR-27/),
and [invoiced quantity](https://docs.peppol.eu/poacc/billing/3.0/syntax/ubl-invoice/cac-InvoiceLine/cbc-InvoicedQuantity/).

| Follow-up task | Owner | Writable scope | Review / evidence |
| --- | --- | --- | --- |
| Quantity backend | backend specialist | Invoice/ephemeral request rules and deduction helper; signed issue/PDF/email guards; ISDOC; ePostman/ephemeral submission eligibility; focused PHP tests | Canonical totals, no stock mutation, prepared XML and fake-provider retry |
| Quantity local/UI | payments specialist | Invoice form/preview/composable; signed local helper/document CRUD/ephemeral bridge; five locales; focused frontend tests | Nonzero quantities, live totals, retained snapshots and source/export payloads |
| UBL and integration | main | UBL service/test if necessary; shared task/state/public docs and final checks | Negative quantity, positive price, positive totals and XML schema |
| Independent review | security and QA | Read-only product/doc scope, disposable probes only | Exact final diff/fingerprint; no author self-approval |

The root Graphify query was repeated for this follow-up and located the UBL,
canonical and stock paths. Target worktree graph remains absent; no graph rebuild.

### Quantity follow-up implementation and independent acceptance

The approved quantity contract supersedes the earlier positive-quantity-only
deduction constraint below. Normal manual input clears warehouse-only defaults;
an actual stock-item link requires explicit disconnection. Raw stock/warehouse
associations are checked before payload projection, saving, numbering, issued
edits, export and ePostman preparation/locked retries. Tiny raw values cannot
be rounded to zero to evade the signed checks.

Canonical VAT buckets now account for negative quantities as well as negative
prices. UBL uses the original negative quantity and positive item price; ISDOC
computes the positive per-unit tax-inclusive price by signed nonzero division.
Source totals, line totals and payment balances agree. Derived credit notes
retain the deduction and preserve their source document/snapshots. Structured
preceding-invoice BillingReference support remains an existing separate gap;
this follow-up makes no claim to add it.

Independent QA and security accept the frozen 56-file source/config fingerprint
`bb61393f355a0a4c7a94787819c7b75fcdc31c38950ec9a152f4e841de9e8a75`,
using the same fingerprint definition as the preceding milestone below.
QA also accepts the five updated public guides and this follow-up contract.

Verified quantity follow-up checks:

- Main backend regressions: 289 tests / 1,631 assertions, with one inherited
  PHPUnit metadata deprecation. Existing stock/warehouse regressions: 16 / 100.
- Main UBL schema/amount checks: 16 / 107, including positive prices, negative
  quantities, VAT buckets, payable/prepaid, invalid raw source and unsupported
  extra-discount rejection. These overlap the broader backend run.
- Full PHPStan, Pint, lint (zero errors / 46 inherited warnings), unchanged
  typecheck (110 inherited / no new), locale parity and build (7,664 modules)
  pass. No analysis level or warning/type baseline is weakened.
- QA independently passes 69 frontend / five files, 16 PHP / 155 assertions,
  a server credit-note/PDF/ISDOC probe 1 / 21, local save/issue/snapshot/bridge
  and real composable probes, and generated Invoice/CreditNote snapshots through
  authenticated fake outbound 2 / 138. The latter covers official schemas,
  VAT/balances, invalid inputs, retry bytes/UUID, cached replay and no stock writes.
- Security independently passes 67 frontend / four files, 17 PHP / 197 assertions
  and 22 actual-service retry assertions across Invoice/CreditNote. Changing
  `-1 x 15` to `-3 x 5` still conflicts despite unchanged totals; retained retry
  evidence is preserved and restored retries/cached acceptance are idempotent.

Counts overlap and must not be added. Official unmodified OASIS UBL 2.1 schemas
are mounted read-only for offline validation: 79 XSD files, source archive SHA256
`60b80d76394a8a2add90723ecb8e0e2e9d826775de9749df37a72d60703f86ed`.
The public archive was fetched after normal network approval; no tool or package
was installed. No live provider acceptance, PostgreSQL contention, browser E2E
or cross-device CRDT atomicity is claimed. No new migration, publication or
deployment occurred in the quantity follow-up.

Final complete frozen-source frontend run passes 3,133 tests / 240 files with
four workers and a command-only 60-second timeout. A preceding run overlapped
the derived-credit-note edits and loaded a stale transformed helper against newly
added tests (three failures, including a missing new export). Its fresh focused
retry passes 34 / two files and the subsequent complete frozen-source run passes.
No in-flight verification is represented as an accepted final run. Final Pint
passes all 28 changed PHP files; final typecheck and build also pass after freeze.

The quantity extension needs no new database migration. The earlier additive
payment-provenance migration remains part of the retained correction branch.
All work remains uncommitted on the durable correction worktree. Logs, source
manifest and unmodified schemas are retained privately under
`reviews/invoicing-quantity`. No push, merge, release, existing-database migration
or deployment occurred.

## Scope and acceptance - 2026-11-13

Owner reports failed issuance of invoices with deduction lines, incorrect Bitcoin
labels on externally paid invoices, and missing buyer company identifiers.
Implementation is confined to Satflux; the WooCommerce repository is read-only.
Baseline: `e2130aea` from freshly fetched `origin/master`. This is source evidence,
not evidence of what is deployed. Existing primary-checkout work is preserved.

Acceptance criteria:

- Ordinary invoices support negative unit prices with positive quantities and
  consistent net-positive totals, VAT and payment balances. Preserve inventory
  quantity semantics and imported historical total verification. Reject unsupported
  electronic exports explicitly rather than emitting invalid structured prices.
- A paid invoice displays its recorded payment method. Enabling BTCPay does not
  establish payment provenance. Preserve WooCommerce's supplied method on import,
  distinguish verified BTCPay settlement, and use an unspecified label for legacy
  records without evidence. Do not backfill payment history by guessing.
- Buyer registration, tax and VAT identifiers appear when present. Issued document
  previews use their retained buyer snapshot; drafts use the selected contact.
  WooCommerce custom checkout identifiers require a supported source field.
- Preserve owner/company isolation, encrypted local-first data, settlement checks,
  idempotency, positive quantities and all existing test/type/lint ratchets.

## Ownership and review

| Task | Role | Writable scope | Dependencies / evidence |
| --- | --- | --- | --- |
| Deductions | frontend specialist | Invoice form, invoice request validators, invoice/ephemeral controllers, issue/UBL/ISDOC services, ephemeral bridge, focused deduction tests | Canonical amount verification; explicit export limits |
| Payment provenance | payments specialist | Invoice detail/sidebar/composable, payment and inbox Evolu maps/schema, backend payment source/model/migration, focused tests and five locale files | Sole writer of documentCrud; applies agreed deduction guard changes there |
| Buyer identifiers | wordpress specialist | InvoiceLivePreview and its dedicated mounted test | Read-only external Woo mapping audit; caller snapshot fix belongs to payments specialist |
| Coordination | main | Shared state, task documentation, integration and final checks | Separate independent QA and security acceptance |

Specialists do not publish Git changes or operate production. New worktree graph is
absent; initial root Graphify query was available but returned unrelated matches,
so investigation uses targeted reads. No graph/tool installation or rebuild.

## Validation and delivery

Use synthetic mounted frontend tests and an isolated PHP container with no network,
SQLite `:memory:`, array mail/cache/session, a minimal synthetic test-only `.env`,
no cached config and a test-only key. Existing lock-compatible dependency overlays
are reused; no package or tool installation. No owner environment is copied.
Run focused regressions, lint, typecheck, locale parity, build and independent reviews.
No push, merge, release, migration of an existing database or deployment is part
of this task.

## Implemented behavior

Negative prices are supported on ordinary Slovak, Czech and manually taxed US
invoices, with positive quantities and positive aggregate totals. Canonical
per-line/document rounding, document discounts, supported decimal precision,
nonnegative VAT buckets and persisted source totals are verified before issuance
and issued edits. PDF, email and aggregate BTCPay checkout share these protections.
ISDOC/UBL/eFaktura and German hybrids reject unsupported negative prices; signed
visual PDFs omit the unsupported ISDOC attachment. Positive-only calculations and
historical migration evidence retain their existing behavior.

New nullable server `paid_payment_method` and encrypted local `paidPaymentMethod`
fields record confirmed source classifications. An enabled payment option or
unused checkout is never payment evidence. Duplicate marking preserves provenance;
unmark and duplicate clear it, while proforma conversion and server migration
preserve it. Historical missing values remain unspecified without a guessed backfill.
Deploy the additive server migration before using the updated backend code.

Buyer identifiers render with existing labels and escaping. Issued previews select
the retained buyer snapshot; drafts use current contact data. Blank Woo identifiers
preserve nonempty saved contact identifiers. The read-only Woo audit found support
for `billing_company_wi_id`, `billing_company_wi_tax` and `billing_company_wi_vat`;
arbitrary checkout extensions require separate mappings. The audited local plugin
checkout is not evidence of the installed production version. Server autoissued
PDFs cannot obtain browser-only contact fields before import, and no old issued
snapshot or already sent PDF is rewritten.

## Independent review and corrections

Independent security reproduced stale-model overwrites of payment provenance,
resurrection of cancellation and a false full payment after a total change. Fresh
row locks now protect mark/unmark decisions, amount comparisons and audit writes.
Woo notification waits for outer commit and checks the current paid state. Local
payment mutations prefer current query rows over stale caller arrays. This does
not claim atomic offline reconciliation across devices.

QA found and verified fixes for unsupported signed types/providers, issued-update
validation bypass, structured-export bypass, per-line rounding/document discounts,
source/provider settings stripped by request validation and stale editor totals.
Main also retained numeric-string quantity compatibility for existing callers and
aligned signed preview row rounding. Static checking uses cast-aware attribute
reads in the payment service; six obsolete service baseline entries are removed,
with no added ignores or reduced analysis level.

Final source/config scope: 49 files, fingerprint
`c6e80f7e13f711e416a4d5686b234b4909557e3f0c58c03f42e684fa80a7cb83`.
Hash sorted paths, a NUL separator and each file's raw SHA256 digest, then SHA256
the concatenation. Documentation is excluded from this runtime/config fingerprint.
Both independent QA and security accept this final delta; QA also accepts the
seven public documentation files and this implementation report.

Final main validation:

- Frontend: 3,110 tests / 239 files pass with two workers and a command-only
  60-second timeout; repository timeout/ratchets are unchanged. The first broad
  run failed two numeric-string quantity regressions and nine resource-related
  failures. The two regressions are fixed; the complete retry passes.
- Backend: 201 tests / 962 assertions pass on the semantic payment correction;
  one inherited PHPUnit metadata deprecation remains. After the equivalent
  cast-aware attribute-read cleanup, payment/bank regressions pass 34 / 146.
- Full-project PHPStan passes after reusing existing lock-compatible dependencies
  and clearing the earlier incompatible dependency cache. Six obsolete service
  baseline entries are deleted; no added ignores or reduced level.
- Pint passes all 22 changed PHP files, with the final two service/test files
  checked again after the payment correction. Whitespace checks pass.
- Full lint passes with zero errors / 46 inherited warnings. Typecheck reports
  110 inherited errors / no new errors; its baseline is unchanged.
- Build passes, 7,664 modules; existing bundle-size and third-party annotation
  warnings remain. EN/SK/ES/DE/CS locale parity passes.

Independent final-delta evidence includes QA's 44 frontend / three files,
34 PHP / 164 assertions and three after-commit probes / ten assertions; security
passes 14 PHP / 74 assertions, 44 frontend / three files, twenty adversarial
signed-value probes, the three reproduced stale-state scenarios and four
outer-transaction assertions. These counts overlap and must not be added.
Earlier focused author runs remain separately attributed in private reports.

Actual PostgreSQL contention, browser E2E, production WooCommerce and cross-device
CRDT atomicity are not claimed by these synthetic tests.

## Handoff

Implementation `165d315e` is published on `fix/invoicing-lines-payment-identities`;
the durable `invoicing-corrections` worktree tracks that branch. Main's unrelated
checkout and all owner previews/accounts/data are preserved. Private logs and
review summaries are retained under `reviews/invoicing-corrections` and
`reviews/invoicing-quantity`. No PR, merge, existing-database migration or
deployment occurred. Next is PR/CI review and separately authorized deployment
with the additive migration first.
