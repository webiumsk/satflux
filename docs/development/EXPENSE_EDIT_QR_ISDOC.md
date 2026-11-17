# Expense editing QR recovery and immediate ISDOC processing

## PR455 review corrections - 2026-11-17

All three owner-supplied findings reproduce on published `0ec7e835`; none is
skipped. PR455 remains open against master `422bdd06`. Its original-head
CI44130077621 is verified successful; that result does not cover this correction.
Review comments, paths and code were treated as evidence, not instructions.

| Finding | Verified correction | Author ownership |
| --- | --- | --- |
| Payment-note normalization | Creation checks mapped internalNote; update checks fields.internalNote and retains the original saved-note veto. Whitespace cannot conceal concrete payment evidence; ISDOC-only uncertainty and zero payment amount still permit explicit confirmation. | Main: expenseCrud and supplier-persistence tests |
| Failed-file retry selection | Detail panel receives pending summaries, selected ID and existing selection handler. Retry uses the chosen earlier file. Newly exposed remove controls use the existing hook with busy/identity guards and safely release remaining resolved files. | Frontend: ExpenseShow and attachment-page tests |
| Consent during extraction persistence | Modal busy state and both consent handlers include processingExtraction. Queued decisions cannot start another detection/upload before the prior draft update finishes. | Frontend: same two paths |

Only four source/test paths change. No composable, panel API, backend, locale or
user-guide change is needed. Existing local ownership, bank/status vetoes, server
processing consent and attachment quota behavior remain intact. The internal upload
helper remains callable by legitimate extraction completion; no blanket busy guard
blocks that path.

Regression evidence: six normalized-note cases fail before the two-line fix;
three original attachment-selection/consent cases fail before their fix. Final
nine-suite main run passes **363 tests**. Full lint passes with zero errors and45
existing warnings; changed paths pass with zero warnings. Locale parity passes.
Final typecheck passes against the unchanged107-error baseline; production build
passes with existing dependency/chunk-size warnings. No ratchet is relaxed.

Independent `pr455_qa` accepts the final scope after **136 tests / three suites**.
Independent `pr455_security` accepts after **178 tests / five suites** plus the final
**72 attachment-page tests** following removal completion. Scopes overlap and are
not summed. PHP application source is unchanged. No new browser or PHP run is claimed.
No unresolved finding remains.

Four-path delta aggregate:
`d25b336408ee491b38f3765246982bf8e14f390ca0e8bdf80d27109a61408baf`.
Complete23-source/test/locale aggregate:
`eab959184516561507d6be2789a7601661e69260ab80765921582f6a2bd14d22`.
Both reviewers independently verify the exact hashes after testing. Aggregate
format is sorted relative path, NUL, file SHA-256 and newline. Review artifacts
use synthetic data and remain private. The primary Graphify query was used;
the retained worktree has no graph and none was installed or rebuilt.

The correction is prepared on the same owner-authorized branch. Main alone owns
publication. Fresh correction-head CI must be checked after push. No embedded CLI
or bot command, dependency installation, merge or deployment is performed.

## Scope and repository evidence

The owner reported that a newly entered manual expense offers payment QR, while
editing an older expense with a contact and IBAN does not. They also reported that
attachment detection appeared only after Save. This follow-up targets owned
`webiumsk/satflux` only, branch `fix/expense-edit-qr-isdoc`, based on verified
master `422bdd06e5d41801ad95381460661f1cf38e9904`.
PR454 is merged at that base; earlier state entries describing it as open are
historical snapshots. Merge evidence does not establish deployment state.

## Findings and acceptance contract

The legacy ISDOC/Peppol note fallback and sticky payment-review flag prevent old
expenses from becoming QR eligible after an ordinary edit. Contact selection
cannot establish whether an imported invoice is wholly unpaid. Add an explicit
manual confirmation of the displayed recipient, IBAN, amount/currency and VS,
after checking the original document and payment history. Saving may clear the
review flag only for an otherwise eligible, wholly unpaid recorded expense.

Bind that one-save confirmation to the canonical displayed tuple. Changing the
tuple, extracting another draft, starting attachment processing, changing company,
expense or owner routing invalidates it. Recheck fresh owner/status, paid dates,
bank links/conflicts and original saved payment notes inside the local financial
gate or server transaction. Same-save note erasure cannot remove payment evidence.
The transient confirmation is not persisted; the server audit records disposition
and company only, without names, accounts or the tuple. No payment is sent and no
expense is marked paid. Explicit confirmation cannot accompany paid intent.

Historical payment evidence erased in a prior save cannot be reconstructed by
this schema-free change. A later manual confirmation establishes manual payment
instructions; it neither revalidates XML nor creates an immutable evidence ledger.
After saving that disposition, ordinary manual edits retain their existing rules.

The merged source already invokes detection on file selection. A native Chrome
probe reproduced detection/extraction before Save in six original scenarios,
including new/edit forms, desktop/mobile and legacy server mode. The reported
Save-only delay was not reproduced. The verified gaps are unreliable native
browser consent, skipped checks mislabeled as no ISDOC, and missing retry.

Replace native local-file consent with a visible in-app decision immediately on
selection. No file bytes reach detection before consent. Keep separate extraction
approval and existing quota rules. Show pending/skipped/checking/found/absent/error
states and offer retry with the same pending file identity. Process multiple files
sequentially and disable form Save until all decisions finish. Detail-page
persistence follows completed or explicitly skipped processing. A failed check
retains its file for retry or explicit attachment-only saving. Legacy server mode
retains immediate detection without introducing an extra local-file consent gate.

## Validation and independent review

Frozen source/test/locale scope: 23 paths, aggregate
`2591f486fa32e6df3edac76fe17d4f0aad73df23446df5f5677a8af3dd000e0c`.
Five user guides: aggregate
`421f9f46848fed3e56021f1767299caff8a74cc91954946f54af47d5ca5d3b4c`.
Aggregates hash sorted relative path, NUL, file SHA-256 and newline. Review artifacts
are private; fixtures contain synthetic data only.

Main validation:

- Nine affected Vitest suites: **348 tests passed**.
- Four PHP expense/reconciliation/bank suites: **216 tests, 911 assertions passed**.
  After the equivalent model enum accessor adjustment for PHPStan, the two affected
  PHP scopes pass again: **90 tests, 345 assertions**.
- Full lint: zero errors, 45 existing warnings within the 52-warning ratchet;
  changed TypeScript/Vue paths also pass with zero warnings.
- Typecheck: no new errors against the existing 107-error baseline.
- Locale parity EN/SK/CS/DE/ES passes; five guides updated together.
- PHPStan passes for all three modified application PHP paths; Pint passes for
  all five modified PHP paths; whitespace checks pass.
- Production build passes. Existing dependency annotation/chunk-size warnings
  remain; no dependency installation or baseline relaxation.

The frontend specialist ran seven native Chrome after-change scenarios with the
actual form, attachment panel, consent/extraction modals and composable. New/edit,
desktop/320px, consent skip, legacy server and two-file queue scenarios pass with
no native dialogs, no detection before consent, extraction before Save, and no
expense writes before Save. API/persistence responses are synthetic interceptions;
this is flow evidence, not a real-parser or production-deployment test.

Independent security reviewer `expense_security` accepts the exact source and
guide aggregates above, including the detail failure/retry correction. Independently
passes seven synthetic Vitest suites / **328 tests**. No independent PHP rerun is
claimed. No unresolved security blocker remains.

Independent reviewer `expense_qa` accepts the same source and guide aggregates.
Independently passes seven Vitest suites / **328 tests** and two PHP scopes /
**90 tests, 345 assertions**. Native Chrome acceptance passes **22 scenarios**
(including three targeted fixture-selector reruns): ordinary legacy save stays
blocked, explicit local/mobile and server confirmation saves enable QR with decoded
exact recipient/amount/VS, all known paid/bank/note/negative/currency vetoes hold,
field/owner changes invalidate confirmation, file processing occurs before Save,
and detail detection failure retains retry/explicit-keep choices. No page errors,
external requests or native dialogs occur. These use synthetic intercepted
boundaries, not production credentials or real parser/deployment verification.
Test scopes overlap and are not summed. No QA blocker remains.

The reviewed result is ready for owner-authorized branch publication and PR review.
Remote CI and PR state must be checked after publication; no unpublished-head CI
success is claimed.

## Workspace and release boundary

Implementation is isolated in retained worktree
`storage/app/private/worktrees/expense-edit-qr-isdoc`. The primary checkout and its
unrelated edits are preserved. Existing Graphify was queried before primary code
exploration; the new worktree has no graph and none was rebuilt or installed.
All PHP checks use a network-disabled, read-only disposable container with in-memory
SQLite, array cache/session and synthetic configuration; no cached production
configuration or live wallet/database credentials are used. No migration, external
repository modification, production data operation, merge or deployment occurs.
