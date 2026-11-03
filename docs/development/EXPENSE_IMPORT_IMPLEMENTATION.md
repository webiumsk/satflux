# SuperFaktura expense spreadsheet import

## Authorized scope and contract

The Product Owner requested expense import on 2026-11-02. Satflux is the only
writable repository. Existing expense import is extended to the current 33-column
SuperFaktura export, using its public column labels and synthetic regression data.
The supplied workbook stays private and outside source control.

Acceptance requires automatic mapping, original document numbers and dates,
faithful totals, safe payment status, preserved supplier/payment/item metadata,
company-scoped duplicate handling and an accessible entry from company Import.
Local-first imports remain entirely in the browser; the existing authenticated
company-owned server path remains compatible. No schema or billing change.

Payment date without a supplied payment amount preserves legacy paid behavior.
With a supplied amount, only sufficient payment in the expense currency (or an
unspecified payment currency) marks the expense paid. Partial or cross-currency
payments remain recorded; their original date and metadata are retained in notes.
Exchange-rate direction is not inferred.

Extra supplier, category, payment and item fields are stored in the existing
private note. Rows exceeding existing field limits are skipped with a reason.
Explicit internal numbers provide rerun duplicate protection within a company.
Imports without internal numbers generate new numbers and require user care on
rerun because identical costs can be legitimate.

## Delegation and file ownership

| Task | Role | Writable ownership | Dependencies and evidence |
| --- | --- | --- | --- |
| Local import | frontend | expenseImportLocal.ts, useExpenseImportFields.ts, expenseCrud.ts, expenseImportLocal.test.ts | Shared field keys; synthetic import regressions |
| Server parity | backend | BusinessExpenseExcelImportService.php, BusinessExpenseImportFields.php, BusinessExpenseImportTest.php | Same field/note/payment contract; isolated SQLite tests |
| UI/parser/docs | coordinator | ExpenseImportModal.vue, DocumentImport.vue, spreadsheetParse.ts, five locale files, related tests and docs | Import lifecycle and shared numeric parser regressions |
| Independent QA | qa | None; temporary synthetic probes only | Frozen implementation; functional/regression evidence |
| Independent security | security | None | Frozen implementation; privacy/company/data-integrity review |

All specialists inherit model settings and cannot publish Git changes. Other
worktrees and unrelated edits are preserved. Graphify query ran successfully but
returned no useful import code edges; targeted source inspection followed.

## Independent review

Implementation authors were the frontend and backend specialists plus the
coordinator. Independent reviewers `/root/expense_security` and
`/root/expense_qa` accept the frozen implementation with no blocking findings.
Both reviewed the bounded working-tree diff over `9dfc4166`.

Security independently passed 38 focused frontend cases, 15 PHP cases / 116
assertions and 33 additional parser assertions. Ten authentication, ownership,
API, encryption/schema and configuration boundary files are byte-identical to
the base. QA independently passed 37 synthetic cases, the same 15 PHP cases,
locale parity and whitespace checks. Initial keyboard-file-picker and
pre-import duplicate-snapshot findings were fixed and independently rechecked.
No reviewer accessed the private workbook or wrote implementation files.

Frozen source SHA-256 anchors:

```text
BusinessExpenseExcelImportService.php 4ea1b0c9bdeb0cd42cb01dba2e8a4ca9dd11b811bba9d8bc82843860d460e917
BusinessExpenseImportFields.php      6007f8150bf07dcad41d0644ba3ed4d7117942a1fd44f7e33f9308f518eac293
expenseImportLocal.ts                757867f691300f5fcc234cca153e59c7a80438a00fa6995db073f8e9fd6ec53c
expenseCrud.ts                       0f457832425c6a01d455750acc6a480639c39711e1d4a192de0dcdcd079f85f5
spreadsheetParse.ts                  2eb2949307413f91ab932cd92405234953286040dcd7dc8b6f32d4e042c3cf41
ExpenseImportModal.vue               731667a640216a39232c82d6fbd03a90fc402bec726b1762f5479e626e59a8d8
```

## Coordinator verification

### CI baseline correction - 2026-11-02

CI `41539349631` on synchronized head `a64f0bed` passes all 2,555 PHP tests /
12,812 assertions and Pint. Its PHP job fails later during full-project PHPStan:
the old `arrayFilter.same` ignore for `BusinessExpenseExcelImportService` no
longer matches any error after the import note builder was corrected.
The earlier focused PHPStan checks did not catch this global baseline mismatch.

Remove only that obsolete one-count entry from `phpstan-baseline.neon`.
Independent QA verifies exact bytes for every retained entry, a decrease from
625 to 624 entries / 825 to 824 ignored counts, unchanged level-5 configuration
and 1,615 unchanged application/test/frontend/config/dependency files.
The coordinator reproduces the single `ignore.unmatched` failure before the
removal and passes the exact full-project command afterward:
`vendor/bin/phpstan analyse --memory-limit=1G --no-progress`.
No application behavior, tests, CI rules or static-analysis thresholds change.
Fresh exact-head CI after push remains a separate required verification.

- The private export dry run accepts all 52 rows and all 33 mappings in an
  in-memory fake, checking original numbers, dates and totals. There are no app
  writes, original-value logs or committed workbook fixtures.
- The original invoice date-parser regression remains in place alongside six
  new shared parser cases; 27 importer and five modal cases provide new coverage.
- Related PHP regression set: 51 cases / 294 assertions. Documentation controller:
  eight cases / 26 assertions. One existing PHPUnit discovery deprecation remains.
- Scoped Pint and PHPStan pass. Full frontend lint passes with 48 existing warnings
  within the unchanged 52-warning ratchet. Typecheck passes with 119 existing
  baselined errors, no new errors, and one stale baseline entry.
- EN/SK/ES/DE/CS locale parity, production build, generated API contracts/route
  inventory and whitespace checks pass. Existing bundle size/import warnings remain.
- Full frontend suite: 1,132 cases across 147 files pass with
  `npm test -- --maxWorkers=2`. The initial unconstrained run encountered timeout
  failures while sharing resources with build/typecheck; the constrained run
  resolves them without production source changes.

Backend checks use a disposable, network-disabled PHP container with SQLite in
memory, synthetic key, array cache/session/mail, empty synthetic environment file
and no cached configuration. The coordinator reuses existing development
dependencies with the same composer.lock and the available DOMPDF driver; no
dependency installation is required. Initial missing PDF/development dependency
checks were resolved in this disposable target.

The feature worktree has no Graphify graph; no graph rebuild or installation was
performed. PostgreSQL concurrent imports and adversarial archive expansion were
not independently exercised. Browser-level independent checks use mounted Vue
components with synthetic boundaries; no live production or wallet test occurred.

Source is retained on `feat/superfaktura-expense-import` in its named durable
worktree. Primary checkout, unrelated edits and other worktrees stay preserved.
No external repository change, Git publication, actual expense import, merge or
deployment occurred. A public changelog fragment needs the eventual PR URL;
publication and deployment remain separate owner decisions.
