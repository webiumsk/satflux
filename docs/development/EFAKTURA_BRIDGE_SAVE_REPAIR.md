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

## PR444 PHP audit correction - 2026-11-14

At b60ed0bb, CI 43528695660 PHP job 132240725763 passes 3,372 tests / 18,404
assertions / 31 skips, Pint 1,166 files and full PHPStan. Only the final Composer
security audit fails: Laravel 12.69.3 is affected by GHSA-63hm-g96r-2m2q.
The [reviewed advisory](https://github.com/advisories/GHSA-63hm-g96r-2m2q)
identifies 12.71.0 as patched. This is a dependency finding, not evidence that
Satflux reaches the vulnerable application pattern.

Copy only the exact already-reviewed PR443 Laravel lock update: 12.69.3 to 12.71.0,
source/dist 64dc24f5d965add4d402b4b8b64f59b561112af5. All other 167 package records,
root lock metadata/content-hash, composer.json and CI gates remain unchanged.
Lock SHA256: b08a4a25f392f42287eec9a6382079174bb5876e2063914b57e83deb21f06f17.
No PR443 application change or review finding is included. PR444 is technically
independent of that PR's unresolved application findings and merge ordering.

All 168 existing isolated vendor versions/source/dist references match the lock.
Fresh strict Composer validation and locked audit pass without ignores; runtime
reports 12.71.0. Main passes 210 PHP tests / 1,637 assertions and full-project PHPStan.
Independent QA passes 114 tests / 820 assertions for receiving/settings/auth/isolation;
independent security accepts the exact lock and fresh empty audit. Counts overlap;
one inherited PHPUnit deprecation remains. The first local harness run lacked
its temporary testing directory; correcting only tmpfs setup makes the full
selection pass. No source change or test suppression hides that harness error.

Earlier frontend acceptance remains unchanged; it is not represented as rerun.
Publication and exact new-head CI verification follow local acceptance. No merge,
deployment, provider request, production operation or preview change occurs.
