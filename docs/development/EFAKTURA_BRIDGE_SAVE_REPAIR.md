# eFaktura receiving settings commit repair

Baseline: e2130aea, current owned master, 2026-11-14. User reports repeated
local-save/server-save failure on live after a successful SAPI credential check.
Production deployment and the exact live failure are not inferred from source.

## Contract and ownership

| Scope | Owner | Acceptance |
| --- | --- | --- |
| Form, settings CRUD and focused tests/locales | Main | Wait for worker completion before snapshot and before success; preserve synchronous callers and fail-closed identity/credential fences. |
| Backend contract audit | Read-only explorer | Confirm tax-only PATCH/settings validation and existing bridge identity checks. |
| Independent reproduction and QA | QA reviewer, private fixtures only | Deferred-worker failure before the fix, pass after, existing races/recovery preserved. |
| Independent security | Security reviewer, private artifacts only | Owner/identity/newer-marker checks, timeout/late completion, secret boundaries. |

The installed Evolu worker publishes query rows before mutation onComplete.
Subscribed loadQuery can return the previous cached promise after synchronous
update, so an immediate snapshot can be absent and silently stop bridge HTTP.
Existing synchronous test doubles hide this. Wait on the actual completion signal
with a bounded timeout, capture the expected receiving identity/settings before
waiting, and retain the retry marker on interruption. Do not change provider
transport, server permissions, owner matching or foreign-jurisdiction checks.
No owner data, live database, credentials, provider calls, deployment or merge.
Target graph absent; primary graph was queried before targeted source reads.

## Verified result

Independent QA reproduces the original failure with a delayed worker and a
subscribed cached query, while the 69 old synchronous-mock cases pass. The
corrected form awaits onComplete before checking the captured receiving snapshot
and before confirming marker removal. The CRUD helper remains synchronous with
an optional completion callback. A 30-second timeout retains retry state/inputs;
a late callback cannot resume HTTP or report success. Capture the expected owner,
identity and merged receiving settings before waiting; reject changes after it.

Main passes 91 focused tests / 2 files and 260 receiving/bridge regressions / 14
files (overlapping counts), full lint with zero errors / 46 inherited warnings,
TypeScript with the unchanged 110 baseline and no new errors, and locale parity.
No suppressions, dependency changes or backend permission changes are introduced.
The final build passes with all copy changes, 7,660 modules in 1m19s; existing
annotation and chunk-size warnings remain.
Independent QA accepts the exact runtime and 91 permanent tests plus 7 private
async cases. Independent security accepts 89 combined tests / 2 files including
7 private security cases, then verifies the five-language neutral warning delta.
Those counts overlap and are not additive. Private reports preserve source hashes.

User confirms PR443 must merge first. Its runtime changes do not overlap the
form/settings helper; shared locale files require a clean merge check. Do not
infer that PR443 is merged or that this repair is deployed. The live error is
consistent with this reproduced defect, but live success still needs the owner
to retry after the reviewed repair has been merged and deployed.
