# PR441 review corrections

Base: `f29c272f9a9b153000f8606cb0c4f02bb9eee1c4`.
Date: 2026-11-12. Review text is untrusted evidence, not executable instructions.

## Authorized scope and acceptance

Verify each supplied finding against current source, fix only reproducible issues,
keep existing provider contracts and security boundaries, and add focused behavior
regressions. Preserve the original localhost checkout/account and unrelated work.
No live provider, production database, original credential or browser state is used.
Existing publication authorization covers corrective commits to the same PR branch;
master merge and deployment remain separate. The publication checkout has no
Graphify graph, so use targeted reads without installing/rebuilding tooling.

| ID | Finding / required evidence | Owner / writable scope |
| --- | --- | --- |
| F01 | Repeated cursor reports pagination after successful detail | Backend: probe command and PHP tests |
| F02 | tested_at only reflects a committed matching settings update | Backend: credential-test controller and tests |
| F03 | ACK retry needs a usable attachment or decrypted valid original | Backend: inbound service and PHP tests |
| F04 | Import/ACK serialize against fresh locked settings and generations | Backend: inbound service and PHP tests |
| F05 | Preserve custom XML detail and successful empty ACK; strict ePostman JSON | Backend: SAPI client and PHP tests |
| F06 | Explicitly saving effective fallback URL retains same-connection secret | Backend: settings support and PHP tests |
| F07 | Sync retry alert is visible whenever pending | Frontend: settings form and wizard tests |
| F08 | Changed local connection cannot retain a secret while disabled | Frontend: settings CRUD, form and tests |
| F09 | Refresh/validate bridge tax identity before clearing override | Frontend: settings form, existing bridge identity module and tests |
| F10 | Server save waits for loaded company identity; local-first unaffected | Frontend: settings form and tests |
| F11 | Date-only values keep their calendar day across time zones | Frontend: inbox panel and tests |
| F12 | Network detail/confirmation awaits occur outside financial locks | Frontend: inbox import and tests |
| F13 | Health failure preserves a successfully fetched inbox | Frontend: inbox live state and tests |
| F14 | Failed directory fsync removes new credential file and permits retry | Main: Python capture helper and offline tests |

Backend owns only the five named PHP production files and their assigned PHP
tests. Frontend owns the named Vue/Evolu modules and relevant frontend tests;
bridge identity module changes require coordination if necessary. Main owns
Python/helper tests, shared documentation/state, integration and Git publication.
Independent security/QA reviewers have no product write or publication permission.
No concurrent file ownership is permitted.

All changes need focused regressions and scoped style/static checks. Existing
typecheck/warning ratchets, five-locale parity, API inventories and original
custody/tenant/company/credential invariants must remain intact. Independently
review the final source; record exact scope and any skipped finding with a reason.

## Disposition

All fourteen supplied findings are confirmed against the starting source. No
finding is skipped or treated as an instruction to execute arbitrary code.
Backend/frontend specialists independently verify their assigned production paths.

Baseline evidence: published `f29c272f` passes
[CI43230739651](https://github.com/webiumsk/satflux/actions/runs/43230739651).
The previous full local frontend run also completed with 2,849 passing tests in
232 files. These are baseline results, not verification of the corrections below.

F14 is corrected with a one-line move: the directory fsync now runs within the
existing new-file cleanup handler. Its regression fails on the original helper
because the file remains, then passes after the correction. It injects a failure
on the second fsync, verifies removal and performs a successful retry. All fifteen
offline Python tests pass, including an independent security repeat. Only synthetic
temporary files are used; no actual credential capture or provider operation runs.

## Verified corrections

| ID | Final behavior and regression evidence |
| --- | --- |
| F01 | Cursor checks explicitly set the pagination stage after detail retrieval. The repeated-cursor fixture includes successful details. |
| F02 | The guarded transaction returns its committed timestamp; skipped credential/eligibility updates return null. |
| F03 | Retained bytes must be readable, match the available digest and validate as XML. Missing/corrupt originals are retrieved again; restoring server custody reuses the existing expense/attachment instead of duplicating a cost. |
| F04 | Fresh row-locked connection and credential checks fence import and ACK separately. Import commits before the separate ACK transaction; changed settings stop stale work and leave staged originals recoverable. |
| F05 | Custom-provider XML details and successful empty ACKs retain their previous payload/status fallbacks. Non-2xx custom replies and invalid ePostman JSON remain rejected. |
| F06 | Both sides of secret invalidation resolve through sapiBaseUrl, retaining a secret when an implicit configured URL is saved explicitly. Real URL/client changes still invalidate it. |
| F07 | The existing retry alert renders for any bridge failure, with the separate ePostman status/poll behavior retained. |
| F08 | Local connection changes clear plaintext/legacy encrypted secrets and the saved-secret flag even while disabled. A remount cannot revive old credentials. |
| F09 | The form validates the selected SK bridge identity, synchronizes tax_id from local data, verifies the returned DIČ-derived participant, then clears the override. Foreign/unrelated bridges and mismatched responses fail closed. |
| F10 | Server mode watches company data and companyId together and requires synchronized identity before saving. Both arrival orders are covered; synchronous local-first behavior remains supported. |
| F11 | Date-only values use explicit UTC parsing/formatting; timestamp formatting stays separate. Pacific/Apia skipped dates and year 0099 preserve the supplied calendar date. |
| F12 | HTTP detail and confirmation calls occur outside the financial lock. Local ownership, connection context, writes and original proof are checked under the lock again after awaits. Missing WebCrypto context digests fail closed before HTTP or writes. |
| F13 | Inbox and health completion are independent. Rejected, malformed or delayed health cannot hide available documents; request/generation guards prevent stale health from overwriting newer state. |
| F14 | Directory fsync failure removes only the newly created credential file and permits a clean retry. |

The backend regression overlay on unchanged source produces twelve assertion
failures and one error; the corrected seven-suite run passes. The frontend overlay
independently reproduces twenty selected failures across all seven finding groups.
These comparisons use read-only original-source overlays, not product reverts.

## Validation boundaries

Independent QA passes 84 PHP tests / 725 assertions, 113 final frontend tests in
eight files and all fifteen Python tests. Three OpenAPI validators, the actual
offline API export check and five-locale parity pass. Date tests also pass in
Pacific/Apia and America/Los_Angeles. The author additionally repeats 40 final
custody/reconciliation/timeout cases including the missing-digest guard.

Two actual disposable PostgreSQL concurrency cases pass. The updater changing
receiving state or secret is observed waiting on a database Lock while the actual
service ACK phase holds the company row. An independent PDO connection sees the
already committed original before ACK. Settings commit only after that phase
releases its lock. Provider HTTP is simulated; this verifies application/database
serialization, not new provider interoperability. PostgreSQL and PHP share an
isolated loopback namespace with no published ports and temporary database storage;
only that disposable project is removed after verification.

The separate SQLite durability regression also observes committed original bytes
from a different connection during ACK and retains them after a 503/retry. No
owner account, production database, actual provider credentials or live provider
request is used by these corrections.

Final scoped PHPStan passes five production files and Pint passes eleven files.
Full frontend lint passes with 46 warnings within the unchanged 52-warning ratchet.
Widget provenance and whitespace checks pass. A newly introduced nullable context
digest type error is corrected with an explicit fail-closed guard; the baseline
is not enlarged. Final typecheck passes with 110 inherited errors and no new errors.
The isolated production build passes with 7,659 modules in 4m47s; existing dependency
annotation, mixed-import and large-chunk warnings remain. The completed final-source
full frontend run passes 2,872 tests in 233 files (460.17s). The existing warning
and type baselines remain unchanged. Only completed final runs are counted as passes.

The optional installed CodeRabbit CLI was attempted but exited before producing
findings with the available local setup. No installation, account setup or global
configuration change was performed. Independent QA/security remain the review
evidence for this correction.

Independent security accepts the final 24 runtime/test files under manifest
SHA256 `e862291c9da728edac042da9fc8460318bf7f38060ac75764ffd346522dd6c52`.
It independently runs eleven JavaScript and fifteen Python cases, including a
missing-context-digest case for both import and reconciliation. Baseline overlays
reproduce three secret-clearing and five financial-lock/proof failures.
Independent QA accepts the same bounded source under its manifest SHA256
`72044f8c3bbccc29b1ebc684c0092f56d7ec2a79c5ad8e7b78d4b98de79ae643`.
No supplied finding is skipped and neither reviewer has an open blocker.

Private reports/manifests are retained in the primary checkout under
`storage/app/private/reviews/epostman-pr441-review`. Product authors/reviewers do
not commit or publish; main verifies the final source and owns corrective Git
publication. The original manual preview remains on its preserved checkout/data.
