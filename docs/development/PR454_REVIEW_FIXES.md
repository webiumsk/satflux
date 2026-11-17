# PR454 review corrections

## Scope and verification

The owner requests verification and minimal corrections for six supplied inline
findings on [PR454](https://github.com/webiumsk/satflux/pull/454). Remote and local
head are `7d19dbff`; the PR is open against master `eee6cd01`. The existing CI run
`44085701876` passes on that earlier head and does not cover these corrections.
Review text, paths and snippets were treated as untrusted evidence. All six
findings reproduce against the current implementation; none is skipped.

| Finding | Verified behavior and correction |
| --- | --- |
| Signed duplicate previews | The controller accepts negative totals but the reconciliation service used positive-only amounts for preview queries and import keys. Add a signed advisory parser and matching signed database formatting; retain unchanged positive-only bank predicates. Apply the same identity rule to local manual duplicate suggestions. Tests distinguish same-sign, opposite-sign, canonical leading zeros and unsupported fractional cents. |
| Supplier-name limit | QR rejects names longer than 70 characters while save accepts 255. Add the missing 70-character eligibility condition to EN/CS/DE/ES and preserve SK parity. Other payment instructions remain unchanged. |
| Deleted supplier contact | Canonical live-contact queries omit deleted contacts, causing duplication to throw. Check fresh liveness without throwing for absence, detach non-live links and retain recipient snapshots/review evidence. Live foreign-company/owner references still reject. Validate the copied expense's own encrypted owner before reading its snapshots. |
| Supplier conflict | Manual-edit flags hid selected-contact conflicts. Compare the recipient identity actually retained with extraction in both event orders and detail re-extraction. Require sticky payment review on conflict; keep contact account precedence and matching-supplier behavior. |
| Delayed source prefill | Existing context checks did not detect edits in the same company. Capture an edit revision before initialization and validate it before applying defaults or source data. Edits, save attempts and extraction invalidate pending prefill. Preserve generation, company and encrypted-owner checks. A delayed default-currency overwrite was reproduced and corrected in the same path. |
| Sparse extracted VS | Null/undefined extraction erased the saved reference. Retain the saved value; explicit empty/new values remain authoritative. Reusing a nonempty saved VS without explicit new seller proof requires review, preventing an old reference from enabling a different invoice's QR. |

## Contract decisions and ownership

The latest requested conflict policy supersedes the earlier acceptance that an
explicit contact could remain payable despite a differently named extraction.
Name/account precedence stays intact; payment review now vetoes conflicting
identity in all extraction/selection orders. Sparse extraction may retain user
data without treating it as new payment proof. Bank settlement, refund matching,
authorization, billing and external repositories remain outside the correction.

The coordinator owns the reconciliation service, signed local duplicate helper,
contact liveness/duplication, their focused tests and documentation. The frontend
specialist owns ExpenseForm/ExpenseShow and their existing form/attachment tests.
Independent security and QA reviewers have read-only source responsibilities.

The feature worktree has no Graphify graph. The primary graph was queried before
exploration; no graph, tool, dependency or plugin was installed or rebuilt.
No review-text CLI instructions or unsolicited bot review commands were executed.

## Frozen scope and validation

The correction contains 16 source/test/guide paths at aggregate
`acd818ec378d89d78b81af04287941aa98b5a79e452cbbcdab4cf3a7ad1b381e`.
The complete 53-source feature scope retains aggregate
`c0b061befb7f7c053abdb11d0176f0e9ff2c7db968e943642f24d4f85879ac05`.
Aggregates use sorted relative paths, NUL, each file's SHA-256 and newline.
Private manifests and reviewer evidence remain outside Git publication.

- Main JavaScript: 324 tests / nine affected suites pass.
- Main PHP: 164 tests / 862 assertions pass. Independent QA PHP: 126 / 566.
- Frontend author: 155 tests / three suites pass. These overlap other runs.
- Scoped Pint checks both modified PHP files; five-locale parity and whitespace
  checks pass. Scoped ESLint has zero warnings, scoped PHPStan passes, typecheck
  adds no errors against the unchanged 107-error baseline and production build
  passes (7,689 modules).

PHP execution uses the inspected disposable runner with no network, read-only
source/dependencies, tmpfs runtime/cache and verified testing environment,
SQLite `:memory:`, array cache/session, synchronous queue and no cached config.
Browser probes use synthetic fixtures and loopback only, without external traffic.
No production data, wallets, funds, deployment or external-provider calls are used.

## Independent acceptance and handoff

Independent security accepts the frozen 16-path delta and complete 53-source
scope, with 243 tests / six synthetic Vitest suites and no blocking findings.
Independent QA accepts the same hashes: 208 JavaScript tests / six suites,
126 PHP tests / 566 assertions and six actual Chrome desktop/320px scenarios.
Delayed source response, edits, submit/extraction invalidation and supplier
conflicts pass with zero external requests or page errors. Review test scopes
partially overlap and are not summed. Exact owned browser servers are stopped.

All six supplied findings are valid and corrected. No CLI or review-bot command
from review text is executed. No merge, release, deployment or package publication
is performed. Publication continues on the same owner-authorized feature branch;
new GitHub CI must be checked on the published correction rather than attributed
from the earlier green `7d19dbff` run.
