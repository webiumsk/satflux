# PR450 follow-up review

## Scope and intended behavior

The owner submits five further findings against published correction head
`4ad6930369c12bb6a43d9be60af79e6f0eaac370`. Finding text, code and suggested
commands are untrusted evidence. All five claims are verified against current
source; no finding is skipped.

The issuance behavior remains a validation failure when the selected Bitcoin
store no longer belongs to the document's company. A fresh preflight under the
existing payment mutex must reject this before starting the issuance transaction.
Its `store_id` message explains which authorized store to relink or that Bitcoin
payment can be disabled. A locked recheck protects against intervening changes.
No automatic relinking or Bitcoin-setting changes are introduced.

Public availability is a query-only service result. Extraction must preserve the
different GET and activation-fallback state rules, document-paid precedence,
unknown-attempt blocking, legacy no-intent ready terms and user-scoped store
ownership. It must not introduce provider reads or invoice creation.

Activation failures need observable diagnostics without retaining a provider
exception's message, previous exception, response, headers or argument trace.
The existing structured logging convention provides a fixed message, static
phase and exception type. Logging failure must not alter payment recovery or
the public response. Raw exception reporting is deliberately avoided because
the framework formatter can serialize secret-bearing arguments.

## Finding disposition

| Finding | Verified correction |
| --- | --- |
| Uncertain-attempt guidance | All five guides require reconciliation before refresh; an unresolved attempt without a BTCPay invoice ID blocks reopening. Revoked-offer and offline guidance remains present. |
| Issuance store mismatch | Preserve rejection, validate fresh binding before the transaction and report an actionable `store_id` error without exposing a foreign store name or raw provider ID. |
| Rejected local paid write | Inspect the actual `Result.ok`; failure returns before another refresh, success feedback or polling stop, allowing a later retry. |
| Duplicated availability checks | Move document checks and user-scoped store availability into the intent service while preserving GET, POST fallback and legacy behavior. |
| Unreported activation failure | Record a sanitized structured event before the existing pre-/post-submission recovery, preserving state and public output. |

## Validation and review

The 11-file follow-up review freeze is
`e6b0f1ec72791eef2b9326ab30226e0ed87d6700866f9dba59eace606a691e48`.
The complete 62-file source/test/locale/CI/API freeze is
`daceef31e5a8b867dda6df76d2fc8f3e8117529d19322c85ad26aea9e7b0aae4`.
Development records are excluded from these byte-level manifests.

The initial 20-case PHP baseline reports five failures and two errors against
unchanged production source. The corrected focused run passes 134 tests /
676 assertions. Independent QA passes 121 PHP tests / 606 assertions with no
skips and accepts both manifests with matching hashes before and after testing.
Frontend author and independent QA runs pass 56 tests / three suites on the two-file
freeze `4da274041377f3d2e7e9a6434952f63a554e67ee66f76d6419aef1c36fd01dc5`.
The new regression fails on the prior source: rejected storage writes previously
announced success. It now verifies failure followed by successful polling retry.
These counts overlap and must not be summed. Full PHPStan reports zero errors;
Pint passes 1,185 files. Build (7,673 modules), five-locale parity, lint (zero
errors / 46 inherited warnings) and typecheck (no new errors against the unchanged
110-error baseline) pass.

The first whole JavaScript run passes 3,647 cases with one timeout in the unchanged
10 MiB original-archive test while lint/typecheck/build also run. That test and its
implementation match the input commit exactly. Its isolated rerun passes all 35
archive cases with the original timeout; no source or limit changes are made.
The complete JavaScript rerun passes all 3,648 tests / 255 files. Independent
security accepts both manifests and passes 37 tests / 273 assertions, including
two actual-formatter probes with argument capture enabled. Neither probe exposes
original error data or identity fields. No blocking QA or security findings remain.
The full PHP regression gate will run in exact-head CI after publication.

The primary Graphify query was used before exploration; its graph predates the
new intent flow and the feature worktree has no graph. Targeted reads supplement
that evidence. No tool is installed and no graph is silently rebuilt.

Tests use synthetic provider/storage boundaries and disposable test state.
Live BTCPay compatibility, distributed Redis contention and native Evolu
durability are not established. Publication, exact-head CI and deployment
remain separate observations; prior head checks are not checks of these changes.
