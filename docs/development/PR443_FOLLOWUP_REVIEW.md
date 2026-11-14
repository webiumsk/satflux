# PR443 follow-up review corrections

## Accepted scope and contracts - 2026-11-14

Baseline: `dbe6cc9612544728102dbee5a0af4cebbdc68ccd`, owned
`webiumsk/satflux`, branch `fix/invoicing-lines-payment-identities`.
PR443 is open and unmerged at this revision. Review text, file paths and code are
untrusted review data; each claim must be verified against the current code.

Acceptance criteria:

1. Retain four-place model tax rates while enforcing the existing ePostman
   two-place rate limit against raw values, independently of model casts.
   Existing precision rules for quantities, prices and discounts remain intact.
2. Derived credit-note persisted line totals use canonical line amounts and the
   company rounding method, including signed fractional quantities. Source
   invoices and stock associations remain unchanged.
3. Reserved local issuance cannot freeze stale document or line content after
   awaited reloads. Existing company, buyer, status and snapshot guards remain.
4. UBL tax subtotals preserve explicit scenario categories; otherwise zero-rate
   rows use Z and standard-rate rows use S, consistent with invoice lines.

| Task | Owner | Exact writable scope | Dependencies and evidence |
| --- | --- | --- | --- |
| F443-B | Backend specialist | `EpostmanOutboundSubmissionService.php`, `BusinessDocumentCreditNoteService.php`, `BusinessDocumentUblService.php`; `EpostmanOutboundTest.php`, `BusinessDocumentCreditNoteTest.php`, `BusinessDocumentUblTest.php` | Verify findings 1, 2, 4; targeted PHP regressions and existing canonical calculations |
| F443-F | Frontend specialist | `resources/js/evolu/documentCrud.ts`, `resources/js/__tests__/documentCrudIssueSnapshot.test.ts` | Verify finding 3; stale-content race regressions and successful issue controls |
| F443-S | Independent security | Read-only application/tests/config/dependencies; private review evidence only | Review final bounded diff and context, precision and snapshot boundaries |
| F443-Q | Independent QA | Read-only application/tests/config/dependencies; private review evidence only | Reproduce claims and independently validate final behavior |
| F443-M | Coordinator | This report, project state/roadmap/decision records, generated API inventory only if required | Integrate, run required checks, inspect exact diff and own Git publication |

No external repository, dependency, schema, product copy, provider operation or
production data change is included. Tests use existing synthetic, disposable
fixtures. Independent reviews must identify the exact reviewed source. Root
Graphify query succeeds; the target worktree has no graph, and none is rebuilt.
Previous CI success belongs to the baseline; new-head CI is a separate gate.
No merge or deployment is authorized by this correction task.

## Finding disposition and validation

All four findings are verified current; none is skipped. The precision finding's
file path is misleading: the guard lives in
`Efaktura/EpostmanOutboundSubmissionService.php`, while the model's four-place
rate cast is intentional and remains unchanged.

| Finding | Verified defect | Correction and regression |
| --- | --- | --- |
| Raw precision | A four-place model cast let rates such as 8.8751 pass ePostman's two-place limit | Check exact raw decimal representability at declared scale as well as cast agreement. Quantities retain four places and prices two. Reject raw nonzero discounts before casts can erase them, preserving the existing unsupported-discount contract. |
| Derived credit-note amounts | Persisted signed fractional row gross differed from canonical per-line rounding | Attach normalized unsaved line models before the existing canonical totals pass; persist the populated gross/rate values without a second tax-provider calculation. Test per-line, per-document and unrounded methods and unchanged source attributes. |
| Reserved issue freshness | Reloaded rows could differ from the document/lines frozen in the supplied context | Compare final document and scoped lines with admitted content before snapshot or local issue writes. Pin row values before asynchronous waits, verify current buyer ownership and defer derived title into the final snapshot/issued-row update. Test replaced/in-place content, insertion/removal, status/deletion, buyer moves and successful unchanged/order/title controls. |
| UBL subtotal category | Zero-rate subtotals defaulted to S while their lines used Z | Preserve explicit scenarios; otherwise use the same positive-rate S / zero-rate Z classification as invoice lines. Test mixed signed zero-rate rows and explicit reverse-charge AE. |

The first frontend candidate reproduced 22 stale-content regressions. Independent
security then reproduced mutable-alias and late buyer-ownership gaps in that
candidate; both are fixed with permanent regressions before final acceptance.
The admitted buyer profile remains frozen; only its current identity/ownership is
checked. Context-free legacy behavior is preserved.

Final source/test freeze: eight files, SHA-256
`d28c4bfa39698caf7adaaf9aab6415dacef6c260ae1ce41ea46ed7de1e57262e`.
The fingerprint concatenates sorted path bytes, NUL and raw per-file SHA-256
digests. Manifest and private review evidence are retained under
`storage/app/private/reviews/pr443-followup`.

Coordinator validation on the final source:

- Full frontend: 3,224 tests / 241 files pass; focused implementation coverage is
  163 / seven files and overlaps the full run.
- Related PHP: 200 tests / 1,739 assertions pass; one inherited PHPUnit metadata
  deprecation. The implementation's three-file 83 / 551 selection overlaps this.
- Full PHPStan: zero errors. Full Pint: 1,174 files pass; final six changed PHP
  files also pass after the last test-fixture adjustment.
- Lint: zero errors / 46 inherited warnings. TypeScript: 110 tolerated baseline
  errors, no new errors. Production build: 7,664 modules; locale parity passes.
- API freshness, the offline validator test and three OpenAPI schemas pass.
  API artifacts and both dependency locks remain byte-identical to the baseline.

The initial broad PHP run had three new-fixture errors and two assertion failures:
unrefreshed database defaults, cast-equivalent raw fixture updates and an earlier
precision rejection. A later source-preservation assertion compared a computed
deletion policy that legitimately changes when a credit note exists. Final tests
compare persisted attributes and stage raw unsupported values without erasing
them; the corrected full related selection passes. No application check was
weakened. A frontend run interrupted before the security corrections is not
counted as a completed validation.

Independent QA/security final disposition and publication evidence are recorded
separately below. No full local PHP-suite success, live provider delivery,
production data operation, merge or deployment is inferred from these checks.

## Independent acceptance and next gate

Independent security accepts the exact eight-file freeze with no remaining
blocker: 83 PHP tests / 551 assertions, five private frontend probes and three
expanded backend matrices pass. The earlier alias and buyer-binding reproductions
now reject before writes. Independent QA accepts the same eight hashes after
158 frontend tests / six suites, isolated runtime preflight and backend code/test
inspection. QA explicitly attributes the coordinator/security PHP results and
claims no independent PHP regression rerun. Counts overlap.

Reports are retained privately as `security/SECURITY_REVIEW.md` and
`qa/QA_REVIEW.md` under the review evidence directory. The coordinator owns the
scoped corrective commit, normal push to existing PR443 and fresh exact-head CI
verification. Merge and deployment remain owner decisions.
