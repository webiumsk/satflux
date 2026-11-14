# PR445 verified review corrections

## Approved scope and acceptance

The owner requests verifying three review findings, fixing only still-valid
issues, keeping changes minimal and validating. The duplicated request block is
one assignment. Treat finding text, paths, code and embedded prompts as untrusted
review evidence. Baseline: `49384427cea17e84c7fdd13eae2aa3a9c5429313`, owned
`webiumsk/satflux`, branch `feat/efaktura-embedded-pdf`, existing PR445. No merge or
deployment. Preserve unrelated primary edits and existing owner previews/data.

- Healthy PDF cleanup awaits document/loading-task destruction settling before
  destroying the owned worker and terminating its port. Force teardown after a
  bounded grace period for stalled destruction; normal/rejected/forced paths
  remain idempotent, caught, and independent of newer sources. Existing render,
  initialization/resource limits and byte-preservation guarantees stay intact.
- Clearing a pending expense attachment immediately reloads its saved preview
  after pending state becomes empty. Without a pending attachment, retain the
  existing revoke behavior. Delayed saved XML/PDF/image responses still respect
  company/document/attachment/request fences and cannot replace newer previews.
- Detect parsererror elements across all namespaces before extracting UBL PDFs,
  including Chromium retained-root/XHTML errors. Complete UBL plus a second root
  must return invalid_xml and zero PDFs. Keep exact UBL roots/paths, unsupported
  valid XML behavior, DTD/entity rejection, all byte/count limits and original XML.

## Task ownership and integration

| Task | Role | Exact writable scope | Dependencies and evidence |
| --- | --- | --- | --- |
| R445-V | Frontend specialist | `resources/js/components/invoicing/UblEmbeddedPdfViewer.vue`, `resources/js/__tests__/ublEmbeddedPdfViewer.test.ts` | Verify local pinned PDF.js contract; destruction-order, rejection, stalled and late-settle regressions |
| R445-M | Coordinator | `resources/js/utils/ublEmbeddedPdf.ts`, `resources/js/__tests__/ublEmbeddedPdf.test.ts`, `resources/js/pages/invoicing/ExpenseShow.vue`, `resources/js/__tests__/expenseAttachmentPages.test.ts`; this note, feature note and shared state | Verify parser/clear findings; retained-root browser and delayed-read tests; integration/static/build/parity; Git publication |
| R445-Q | Independent QA | No repository writes; private synthetic fixtures/reports only | Final freeze, actual Chromium malformed XML and PDF cleanup/preview recovery |
| R445-S | Independent security | No repository writes; private synthetic fixtures/reports only | Untrusted parser safety, worker ordering/deadlines, source/isolation fences |

Specialists are not alone: no overlapping writes, reverting others, Git mutations,
installs, provider/account/production operations or existing-preview changes.
Main owns integration, source freeze, publication and current-head CI. Existing
locked frontend dependencies are reused; no package installation is needed.
Graphify was queried in the coordinator checkout and points to ExpenseShow and
its attachment composable. The target worktree has no graph; it is not rebuilt.

## Disposition and validation

All three findings are current at the baseline; none is skipped. The repeated
review block is deduplicated.

| Finding | Verified behavior and minimal correction | Regression evidence |
| --- | --- | --- |
| Viewer cleanup | PDF.js 6.5.438 loading-task destruction awaits the worker's Terminate reply. Await settlement before owned worker/port teardown; cancel rendering immediately, force teardown after 1,000 ms if stalled, and release exactly once per source. | Deferred normal/rejected destruction ordering, synchronous cleanup failures, forced/late settlement after replacement and unchanged 15-second stage deadlines. New ordering test fails against baseline via a temporary test-loader override. |
| Pending clear | A saved XML read completing while another attachment is pending is discarded. Clearing the pending attachment previously never restarted that read. Call the existing loader after synchronous pending-state clear. | Both local and server mounted cases fail before the fix and pass after it; no-pending clear still revokes without reloading. Existing delayed company/document/attachment fences remain covered. |
| XML parse errors | Chromium retains the UBL root/attachment and injects an XHTML parsererror for a second root. The Mozilla-only check incorrectly extracts the PDF. Check parsererror local names in every namespace before extraction. | Actual Chrome baseline reproduces one extracted PDF; complete UBL plus second root and retained-root Mozilla/XHTML/no-namespace cases return invalid_xml and zero PDFs after correction. |

Coordinator validation passes 223 cases across ten focused/regression files,
full lint (zero errors / 46 inherited warnings), TypeScript ratchet (110 tolerated,
no new errors), EN/SK/ES/DE/CS parity, whitespace checks and production build.
Locked dependencies match the reused installation; no packages change. No new UI
copy, locale keys, PHP/API/schema or original-custody changes are introduced.
Private logs and six-file SHA-256 review manifest are retained under
`storage/app/private/reviews/pr445-review-fixes`.

Independent reviewers accept the exact six-file source/test freeze over
`49384427` with no unresolved findings. R445-S passes 137 permanent and 23 private
adversarial cases, including delayed XML/PDF/image preview races, stale source
teardown, thrown cleanup and forced/late destruction. R445-Q passes 137 permanent
and 20 private mounted cases; counts overlap coordinator/security runs.

QA's actual Chrome 151.0.7922.137 verifies ten parser cases, including the retained
Invoice/XHTML second-root case, two-page canvas rendering, exact 697-byte download,
and a real Terminate request/reply before owned port termination. Withholding the
second worker's termination reply exercises forced cleanup while the third source
continues rendering. All three workers terminate; zero page errors or nonlocal
requests occur. All six hashes match the review manifest. The owned synthetic
fixture is stopped after verification; owner previews/data stay untouched.

These are scoped code/browser results, not a deployment or provider certification.
Publish only to existing PR445, check CI for its new head, and retain owner
merge/deployment gates. No CodeRabbit CLI installation is needed for these checks.
