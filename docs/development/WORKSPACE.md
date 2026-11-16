# Local workspace

## PR449 CodeRabbit follow-up accepted locally - 2026-11-16

All six actionable comments are independently checked against published parent
`3f95ad5c9ad6608d24da74f8306d1eb8574b9844`. Missing mocks and the owner-column
SQL ambiguity were already fixed. The remaining four valid findings are now
corrected in the same branch: archive-arrival preview refresh, coherent captured
export selection, checkpoint-before-byte-proof ordering with fresh authority
checks, and authorized deletion of damaged/incomplete archives.

Export selection captures typed rows, range, company/name and copied options
(including formats), and cancels on synchronous selection changes before bridge
requests or held ZIP downloads. Source/chunk evidence refreshes previews.
Private post-proof company/share reads preserve owner, fingerprint and exact
active-share authority checks after the earlier worker marker. Deletion validates
available structural bindings, atomically tombstones only existing owned rows,
and prevents deleted attachments from recreating missing archives. Existing
limits, financial contracts, server protocol, schema, dependencies and global
owner/reconciliation wrappers remain unchanged.

Independent QA and security accept the final 42-file source/test/locale freeze:
`225967876edbab39c8364a6a061aefbdd8b2e4fa100d10b1ddcefe987df86237`.
Seven prior files change; one bank-performance mock adds two query tokens with
all assertions unchanged. The combined PR scope is 53 paths. Author core
128/6, main consumer/planner/performance 31/3, security 102/4 including six private
probes, QA consumer 12 plus six private probes, selected core 28/3 and native
12/12 pass. Runs overlap; 56 QA-filtered tests are not counted as passed.
Lint 0 errors / 46 inherited warnings, typecheck 110 inherited errors / nothing
new, locale parity, build 7,672 modules and whitespace pass.

The wrapper-exit checkpoint and final local read to remote completion interval
remain explicit non-atomic limits. Native fixtures/profile data are removed,
their port is closed, and private evidence distinguishes mocks, native storage
and earlier CI. Details and finding links: `EFAKTURA_LARGE_ORIGINALS.md`.

The reviewed follow-up is ready for normal push and CI on its exact new head.
Prior green CI43776670496 belongs to `3f95ad5c`. Primary remains
`fix/sepa-confirmation-isolation` at `885168f2`; preserve unrelated edits
and the separate accountant-export clarity freeze. No merge, provider operation,
public changelog publication or deployment is performed.

## PR449 startup query correction accepted locally - 2026-11-16

PR449's published head `a7410fc59910aa699a7a6cf10b6581e3c4ed5fd0` passes
CI43773954997 Node (3,555 tests / 250 files), PHP (3,501 passed / 19,624 assertions,
31 skipped), PostgreSQL and changelog. E2E exposes two blank initial routes.
A comparison rerun of PR448 head `54b9eea1` passes 26 tests with two existing
flaky footer cases and three skips, distinguishing the new startup regression.

The actual owner wrapper appends unqualified `ownerId`; the new archive join
makes that generated SQL ambiguous even on an empty database. Replace only the
outer join with a correlated `EXISTS`, retaining source ID, owner, company,
exact kind and live source/chunk predicates. The global wrapper is unchanged.
A new actual-client/wrapper/serializer SQLite regression fails 2/2 before and
passes 2/2 after; 26 focused tests / six suites, scoped ESLint and full typecheck
(110 inherited errors / nothing new) pass.

Independent security accepts both corrected file hashes. QA verifies seven native
Chrome/Evolu/OPFS checks: empty query, snapshot, refresh, fingerprint, exact
owner/company/live-source filtering and account seed binding after page reload.
The immediate SDK restore result and successful post-reload pending-sync read
are distinguished; no real account, provider or multi-device delivery is claimed.

The combined 41-file source/test/locale freeze is
`48b383cc6e879a2d8380ca3a223ada392b206bee2597a39909ddfd032e41ec22`.
Relative to the original 38-file review, only `client.ts` changes; 37 files
retain their hashes. Two prior QA-approved mock corrections and the new SQL
regression extend the scope to 41; with docs/changelog the total is 52 paths.
Contract and evidence: `EFAKTURA_LARGE_ORIGINALS.md`.

Next is normal push of the bounded correction and CI for that exact final head.
Primary remains `fix/sepa-confirmation-isolation` at `885168f2`; unrelated
changes and the separate accountant-export clarity freeze remain preserved.
No merge, provider operation, public changelog publication or deployment.

## PR449 published; publication checks in progress - 2026-11-16

The owner's push instruction publishes reviewed implementation
`91748ce3e7208353f7584e9a323a00139a02c268` on
`feat/efaktura-large-originals`. GitHub PR449 is open against master
`2201bf1bacdc9a26571f188382b5e10999bbbf55`:
https://github.com/webiumsk/satflux/pull/449 . Remote metadata matches that
implementation commit. This supersedes the local-only status below.

The documentation follow-up adds publication records and one PR-linked
user-facing changelog fragment. CI43773180167 on `802412ad` then passes
3,550 tests in 248 files but cannot load two other suites because their existing
client mocks lack the new original-source/chunk query exports. The correction
adds those two tokens to each mock, preserving all test assertions; all five
targeted tests and two-file ESLint pass. Independent QA accepts both exact
file hashes and confirms unchanged assertions. This is a test-fixture correction,
with no additional production-code changes.

All 38 accepted source/test/locale hashes retain aggregate
`d0ba510c9b9617484f65d63fb9981db7673e8c9ba000de5d5318f4b5b8c1143d`.
The fragment and two existing test-file corrections make 51 scoped paths.
Remote checks must be verified on the corrected final head; neither the earlier
local tests nor the failed first CI establish that head's CI or production readiness.

Primary remains `fix/sepa-confirmation-isolation` at `885168f2`, with unrelated
changes preserved. The separate accountant-export clarity freeze is unchanged.
No merge, provider operation, public changelog publication or deployment is
performed. The changelog uses the existing gated deployment publication process.

## Larger eFaktura originals accepted locally - 2026-11-16

The owner authorizes remaining point 1: received originals above 384 KiB,
including UBL/XML with embedded PDF. Implementation remains uncommitted on
`feat/efaktura-large-originals`, based on verified PR448 merge
`2201bf1bacdc9a26571f188382b5e10999bbbf55` from reviewed head `54b9eea1`.
Primary remains `fix/sepa-confirmation-isolation` at `885168f2`; unrelated work
and the separate 17-file accountant-export clarity diff remain preserved.
Merge evidence and these local checks do not establish deployed production readiness.

Owner-scoped archives store at most 40 chunks of 256 KiB under the existing
10 MiB server admission bound. Strict receipt-bound references, durable writes
and fresh context/byte/digest checks before and after custody preparation protect
the unchanged server API and post-ACK cleanup contract. Backup v3 validates the
complete archive before output or restore writes. Download, deletion, sharing,
rekey, fingerprint and resend consumers handle the representation. Ordinary
attachments remain 384 KiB; preview and accountant-export caps remain unchanged.

Independent QA and security accept all 38 source/test/locale files at aggregate
`d0ba510c9b9617484f65d63fb9981db7673e8c9ba000de5d5318f4b5b8c1143d`.
Two medium findings are fixed: noncanonical snapshot live flags now fail before
restore writes, and post-proof company/receiving-context changes prevent custody
completion. Main checks pass 274 frontend tests in 20 suites, 49 PHP tests /
605 assertions, lint with 0 errors / 46 inherited warnings, typecheck with
110 inherited errors and nothing new, locale parity and build (7,672 modules).
Pint passes for both changed PHP test files; no production PHP changes.

QA additionally verifies 44 compatibility/integrity cases, 15 malformed-flag
cases, and 22 native Chrome/Evolu/OPFS checks plus four native context-race
regressions. Exact 10 MiB survives a full browser restart; interrupted writes
resume, and atomic deletion failures retain originals. Review runs overlap.
All synthetic fixture processes are stopped and private browser profiles removed.

New originals require the updated client for access, backup, restore and sharing;
rollback needs a compatible reader/backport. Existing E2EE synchronization is
preserved; SQLite encryption at rest is not configured or certified. Tests use
synthetic custody callbacks and disabled external transports, so real provider,
multi-device synchronization and deployment remain unverified. Contract, evidence,
limits and final review dispositions: `EFAKTURA_LARGE_ORIGINALS.md`.

Source and documentation remain local, with no new commit, push, PR, head CI,
provider operation or deployment. Next is scoped publication when requested;
merge and deployment retain their approval gates.

## PR448 published; final publication checks in progress - 2026-11-15

The owner's push instruction publishes reviewed implementation commit
`373f7e1411b401563c225b6edc9068495e09d302` on
`fix/efaktura-settings-clarity`. GitHub PR448 is open against master `c418dcc3`:
https://github.com/webiumsk/satflux/pull/448 . Remote metadata matches that
reviewed implementation head. A follow-up adds the combined user-facing
changelog fragment linked to PR448 and this publication record; all19 accepted
source/test/locale files retain aggregate
`28e1d944f3b711f361836595c2d93303bd5a4f8fd271d5163390f4424c9f7e10`.

Initial CI43759061107 started for the implementation commit. Fresh-head CI must
be checked again after the metadata/changelog follow-up; no passing CI result is
claimed yet. Main394frontend/19 and independent QA/security/browser evidence
remain attributed to their reviewed source freeze. Earlier local-only and
publication-authorization checkpoints below are historical and superseded by
this observed push/PR. No merge, deployment, provider call or new implementation.
Primary remains `fix/sepa-confirmation-isolation` at `885168f2`, unrelated work
preserved. Owner retains merge/deployment decisions.

## Clarity branch publication authorized - 2026-11-15

The Product Owner explicitly requests pushing the reviewed eFaktura/settings and
profile/navigation corrections. Publish only `fix/efaktura-settings-clarity` to
the owned Satflux repository, open its review PR and verify CI for the resulting
head. Fresh fetch confirms master `c418dcc3` still matches the scoped base;
there is no existing PR for this branch. Primary unrelated work stays preserved.

All19 reviewed source/test/locale hashes still match aggregate
`28e1d944f3b711f361836595c2d93303bd5a4f8fd271d5163390f4424c9f7e10`.
The earlier394frontend/19, static/build, QA44/5+62browserchecks and security40/4
results remain applicable; no source changes or redundant product-test reruns.
Main owns scoped commits/push/PR and a user-facing changelog note linked to the
actual PR. Publication outcome and fresh-head CI must be verified separately.
Earlier local-only/owner-review checkpoints below are historical and superseded
by this authorization. Merge, deployment and larger-original implementation are
not part of this publication request.

## Profile layout and Tools fixes retained in the clarity worktree - 2026-11-15

Retain `storage/app/private/worktrees/efaktura-settings-clarity` on
`fix/efaktura-settings-clarity`, HEAD/base `c418dcc3` plus local uncommitted
source/docs. The scope now contains19 source/test/locale files, five user guides
and six development records, including both clarity notes. Primary remains
`fix/sepa-confirmation-isolation` at `885168f2`; unrelated edits are preserved.

Final19-file aggregate:
`28e1d944f3b711f361836595c2d93303bd5a4f8fd271d5163390f4424c9f7e10`.
The earlier seven-file settings freeze remains unchanged. Main394/19 and
lint/type/locales/whitespace/build7,669 pass. Independent QA44/5 plus62browser
checks/28 synthetic PNGs and security40/4 accept; counts overlap. Private evidence
is retained under `storage/app/private/reviews/invoicing-layout-clarity` without
owner images or account data. Existing tools/dependencies are reused; no worktree
Graphify graph is installed or rebuilt and primary previews remain untouched.
All synthetic layout fixture processes are stopped cleanly before handoff.

Contract, ownership, browser/mock limits: `INVOICING_LAYOUT_CLARITY.md`.
No commit/push/PR/new-head CI/provider/deployment action is claimed. Next is
applicable scoped publication/fresh-head CI, then owner merge/deployment decisions.
Earlier owner Live confirmation remains evidence for merged PR447.

## eFaktura settings clarity worktree retained - 2026-11-15

Retain `storage/app/private/worktrees/efaktura-settings-clarity` on
`fix/efaktura-settings-clarity`, base fetched master `c418dcc3` (merged PR447).
Seven source/test/locale files, five guides and coordination evidence remain
local/uncommitted; no commit/push/PR/new-head CI/deployment. Primary remains on
`fix/sepa-confirmation-isolation` at `885168f2`, unrelated edits preserved.

Independent design/QA/security accept source freeze `afe36c2da53365e9d0112bbbdaf108ab67f4e5bae8e541af3cbe4425a15a2bd8`.
Main360tests/14 and static/build7,669 pass; QA200/4+36browserchecks and16 synthetic
PNGs, security183/4 overlap. Private artifacts are retained under
`storage/app/private/reviews/efaktura-settings-clarity`. Existing dependencies are
reused; the scoped checkout has no Graphify graph and none is rebuilt.
Both loopback synthetic fixture processes are stopped. Original owner screenshots,
primary previews, databases, credentials and prior worktrees remain untouched.
Contract/limitations: `EFAKTURA_SETTINGS_CLARITY.md`. Next is owner local-result
review and applicable publication, followed by fresh-head CI and owner merge/deploy.

## PR447 review corrections independently accepted locally - 2026-11-15

Observed review baseline: open/unmerged PR447 at `a48570d11bbf73956f89f47e36d7c38f446f3389`,
base master `4b00e2ee`. The directional checkout has this HEAD plus the local
corrective diff; the primary checkout remains `fix/sepa-confirmation-isolation`
at `885168f2` with unrelated work preserved. This is remote review evidence and
local source state, not an established deployment or production/provider proof.

All three supplied findings are current and fixed: PR447 milestone evidence,
submitted-company receiving confirmation and complete bounded settings JSON.
The PHP CI failure is the missing legitimate capability in PublicConfigTest's
exact expectation; the exact assertion remains. No requested finding is skipped.
QA/security accept five frozen source/test files after before-failing regressions.
Main passes304PHP/2,223,329frontend/15, PHPStan0, Pint3, lint0/46inherited,
type110/no new, locales/API/three schemas/whitespace and build7,669. Counts and
independent overlapping probes are attributed in `PR447_REVIEW_FIXES.md`.

Baseline CI43710844183 has one PHP failure,3,489passes/31skips; its other four
jobs pass and later PHP static/audit steps are skipped. The corrections above
are not yet published or checked as a new head. Main proceeds with normal
existing-branch publication and fresh-head CI under the owner's existing
publication authorization. Owner retains PR447 merge and deployment. Earlier
local-only/publication records below are historical, superseded by this milestone.

## Independent eFaktura connection worktree retained - 2026-11-15

Retain `storage/app/private/worktrees/efaktura-directional-connections` on
`feat/efaktura-directional-connections`, based on verified merged PR446 master
`4b00e2ee`. Source/tests, five guides and design/review evidence remain local and
uncommitted; no publication, merge or deployment. Primary checkout remains on
`fix/sepa-confirmation-isolation` with unrelated tracked/untracked work preserved.

Independent QA/security accept backend20/frontend15 frozen files. Main passes
293 PHP / 2,054 assertions, 318 frontend / 14 suites, PHPStan0, Pint20,
lint/type/locales/API/whitespace and build7,669. Private hash manifests and final
logs are retained under `storage/app/private/reviews/efaktura-directional-connections`.
Existing dependencies are reused without installation; PHP uses network-disabled
read-only source/vendor and disposable runtime/SQLite targets. No .env or owner
preview/database was changed. The scoped checkout has no Graphify graph; baseline
queries were used without rebuilding. Contract and rollback restriction:
`EFAKTURA_DIRECTIONAL_CONNECTIONS.md`. Next is owner local-result review and
applicable publication authorization, followed by fresh-head CI before merge.

## PR446 E2E runner compatibility repair approved - 2026-11-15

Exact-head CI43697361409 forbec2c34f passes PHP, Node, PostgreSQL and changelog,
but E2E fails before tests at browser installation: locked Playwright1.60.0 does
not support chromium on ubuntu26.04-x64 selected by ubuntu-latest. This is
runner compatibility evidence, not an application/E2E assertion failure.

Owner explicitly approves only the prepared E2E-runner repair. Change exactly
`jobs.e2e.runs-on` from ubuntu-latest to ubuntu-24.04. The installed locked
Playwright registry includes ubuntu24.04-x64. Existing Node YAML parser verifies
that this scalar is the only semantic workflow change; whitespace checks pass.
No dependency/action versions, other jobs, permissions, application or tests change.
The accepted two-file runtime/test freeze remains
`b342a06d14ed1ba1cade2bfc67716b4bfb7f1685cc4d5c03fb2ab60cf59f1d89`.

Normal corrective publication and new-head CI follow. The failed run is not
reported green, and no tests are marked skipped to bypass the compatibility issue.
No installation, provider operation, PR merge or deployment. Owner retains merge/deploy.

## PR446 inbound poll budget correction independently accepted - 2026-11-15

The current owner-supplied finding is valid at64d7bcd7. The previous10-second poll
limit is shorter than the server120-second cooperative admission budget plus
finishing provider operations. A dedicated finite300-second poll timeout now
accommodates default30-second detail/ACK and one token-renewal/retry sequence;
ordinary inbox/health/custody requests remain10 seconds. Endpoint/auth/payload,
explicit-only retry and backend acknowledged-receipt skipping are unchanged.

Before-failing virtual regressions now accept a240-second completed poll and
expire a stalled request at300 seconds. Main66frontend/3 and44PHP/519assertions
pass; lint0errors/46inherited, type110/no new, locales/whitespace/build7,669 pass.
Independent QA/security each accept66/3 and the verified two-file freeze
`b342a06d14ed1ba1cade2bfc67716b4bfb7f1685cc4d5c03fb2ab60cf59f1d89`.
Counts overlap. The deadline is not a server-cancellation guarantee or universal
ceiling for arbitrary longer provider/DB settings. See `INBOUND_POLL_TIMEOUT_REVIEW.md`.
Main owns existing-branch publication and exact-head CI; owner retains merge/deploy.
Unrelated primary changes remain preserved; no provider operation or installation.

## PR446 review fixes independently accepted - 2026-11-15

Both owner-supplied findings against1411529a are current and reproduced; none is
skipped. Bound pollInbound with the existing10-second inbox timeout, retaining
POST/auth/payload and explicit retry. Prevent Save/Test overlap in button and
handler admission, preserving unsaved verification and exact existing dirty checks.
Only two runtime paths and two regression files change; no backend/API/schema,
locale, credential-autosave, provider or dependency expansion.

Main passes174 tests /five suites, lint0errors/46inherited, type110/no new,
parity/whitespace and build7,669. Independent QA155/5 and security145/4 accept
four-file freeze `1aa1218f2de98f82442e238caa7db611d3d004e8a3e514f987d4819815f88dd1`.
Counts overlap. Baseline PR446 CI43654838725 passes at1411529a; fresh-head CI
must be verified after normal corrective publication. Scope/dispositions:
`INVOICING_STATUS_REVIEW_FIXES.md`. Main owns existing-branch push/thread handling;
owner retains PR merge/deployment. Primary unrelated edits remain preserved.

## Invoicing status publication reconciliation verified - 2026-11-15

Owner requests push of both accepted corrections. Implementation commit `8d0b4999`
contains exactly31 reviewed paths. Fresh remote master is `4b9cc230`, the owner's
merged PR445. Reconcile it locally with a normal feature-branch merge before push.
Ten conflicts preserve both development histories, the newer five-guide date and
both required inbox imports. The combined panel is exactly the accepted receiving
panel plus the master's PDF import/component. No additional product logic is added.

Independent QA accepts all41 affected paths and162 tests /five suites; security
accepts the narrow17-file byte-preserving integration. Main passes391 combined
tests /15 suites, lint0errors/46inherited, type110/no new, parity/whitespace and
build7,669. Earlier136 PHP/1,018, PHPStan0 and Pint2 remain applicable through
unchanged backend bytes. Merged source fingerprint is
`42fa35d13d66eccc9f6e04224db322cb3066019d11eba289a6901f10097279ee`.

Main owns the pending normal push and remote-head verification. No new-head CI,
PR merge or deployment is claimed by this pre-publication record. Owner retains
PR creation and merge/deployment decisions. Earlier uncommitted/read-only notes
below are historical; both corrections are now authorized and committed locally.

## eFaktura receiving correction in relay-status-banner - 2026-11-15

The retained `storage/app/private/worktrees/relay-status-banner` on
`fix/relay-status-banner` now also contains the owner-authorized eFaktura receiving
status/action correction. Both scopes remain local/uncommitted/unpublished. The
18-file eFaktura freeze is independently accepted by QA/security; prior relay
runtime/test bytes remain accepted unchanged. Main combined/static/build checks
pass. Contract/evidence: `EFAKTURA_RECEIVING_STATUS.md`.

Existing frontend dependencies are reused. PHP validation binds existing compatible
vendor read-only inside disposable network-disabled SQLite-memory containers with
tmpfs storage/cache; no application .env is created and no service remains running.
Primary checkout remains `fix/sepa-confirmation-isolation` with unrelated edits.
No PR, remote commit, merge or deployment.

## Relay status banner worktree - 2026-11-14

Retain `storage/app/private/worktrees/relay-status-banner` on
`fix/relay-status-banner`, base remote-verified master `ce725a99`. The owner-authorized
current relay notice, five locales/guides and regression tests are independently
accepted by QA/security; main static/build checks pass. Source and coordination
records are uncommitted/unpublished; no PR, merge or deployment. The ignored
`node_modules` link reuses the existing invoicing migration dependency directory
without installation. No preview server or test infrastructure was started.
Primary remains on `fix/sepa-confirmation-isolation` with unrelated edits intact.
Contract, hashes/results and eFaktura read-only diagnosis: `RELAY_STATUS_BANNER.md`.

## PR445 master reconciliation retained - 2026-11-14

The named `storage/app/private/worktrees/efaktura-embedded-pdf` checkout retains
`feat/efaktura-embedded-pdf`, reconciling reviewed `a911dadc` with owner-merged
PR443 master `ce725a99`. Four development-record conflicts retain both histories;
five locales merge automatically. Product files match the appropriate reviewed
parent and the six PR445 review hashes remain intact. Normal merge/push and fresh
CI precede the owner's PR445 merge decision. No worktree cleanup, dependency
installation, provider operation, existing preview or owner-data change is needed.
Private merge evidence is retained under `storage/app/private/reviews/pr445-master-merge`.

## Embedded PDF feature retained - 2026-11-14

Worktree `storage/app/private/worktrees/efaktura-embedded-pdf` retains branch
`feat/efaktura-embedded-pdf`, based on merged PR444 master `f5cb7f0e`. Existing
locked frontend dependencies are reused after lockfile comparison; no installs,
account/provider operations or existing preview changes. Main 204 tests and
lint/type/build/parity plus independent QA/security pass. Private reports and
frozen manifests are retained under `storage/app/private/reviews/efaktura-embedded-pdf`.
Only the synthetic localhost Chrome fixture created by QA was stopped afterward.
Owner retains PR creation and merge/deployment; primary unrelated edits are preserved.

## PR443 review correction retained - 2026-11-14

The named `storage/app/private/worktrees/invoicing-corrections` checkout remains
on `fix/invoicing-lines-payment-identities`. Nine verified fixes and their related
QA/security corrections are prepared over `da039ded`; 22 runtime/test/view hashes
are frozen and independently accepted. Main full frontend 3,193 and related PHP
211 plus static/build/API/parity checks pass. Existing dependencies/previews and
all unrelated primary work remain preserved; source env is masked in disposable
PHP tests and never read. Private evidence is retained under
`storage/app/private/reviews/pr443-review-fixes`. New-head CI and owner merge/deploy
remain separate. Temporary npm runtime preparation was rejected by automatic review;
requested permission is pending, no install occurred. Only owned synthetic test
containers may be cleaned up; existing preview containers are untouched.

## Automatic ePostman status worktree - 2026-11-12

The scoped outbound worktree storage/app/private/worktrees/efaktura-epostman-outbound
remains on feat/efaktura-epostman-outbound over d4778fb2 with the independently
accepted status implementation passing full frontend 3,019 tests / 236 files.
Primary checkout's unrelated changes remain preserved. No push or deploy.

The new private .env.epostman-status-app-integration fixture has its own application
key and SQLite file; old RCMD state and Enterprise input are read-only. Exactly two
actual TEST GETs verify the real status service, server Invoice/local CreditNote
projections and cooldown. Responses/report and credential/binding fields are
encrypted; the SQLite file itself is not encrypted. This test-only seeding is not
product legacy backfill. Existing receiving/other localhost previews, accounts,
original attempts and captures remain untouched; no new scheduler is started there.

Final QA/security reports and frozen manifests are retained privately. Only owned
disposable PostgreSQL test projects/networks were removed after passing nine lock
cases and sixteen migration checks. Next: separate outbound publication and
deployment readiness; no owner keys are copied
into an existing preview or committed artifact.

## ePostman publication and preserved manual preview - 2026-11-12

Owner-authorized publication is prepared on `feat/efaktura-epostman-receiving`
in `storage/app/private/worktrees/efaktura-epostman-publication`, integrated with
owned master `07c1af23`. Existing reviewed implementation is committed through
`bb31eb21` on `feat/efaktura-epostman-inbound`; its separate manual localhost
checkout/account/origin and encrypted recovery artifacts remain preserved.
[Draft PR441](https://github.com/webiumsk/satflux/pull/441) publishes this branch;
exact-head CI and production gates remain separate.
The publication checkout reuses existing locked development dependencies and
never starts a web service, scheduler or provider operation. Private evidence
stays in the coordinator checkout. PR/CI status is separate from production and
provider archive/contract verification; see the receiving and onboarding runbooks.

## ePostman sandbox probe waiting for private input - 2026-11-10

Feature remains in `storage/app/private/worktrees/efaktura-epostman-inbound`.
Owner reports an active test account. Hidden capture helper is
`scripts/efaktura/prepare_sandbox.py` in that feature; run from coordinator checkout
so private input lands at `storage/app/private/epostman-sandbox/.env.credentials.json`.
Never print/read that file into model output. Once owner confirms it is saved,
read-only sandbox auth/list/detail testing is authorized; no repeated permission
is needed. Check owner/mode and isolate synthetic dotenv/configuration first.
Reviewed disposable launcher is retained for this session separately from source;
production configuration, provider sends/ACK and billing remain outside scope.

## ePostman receiving retained for handoff - 2026-11-10

`feat/efaktura-epostman-inbound` from `6e6c0587` is retained in durable worktree
`storage/app/private/worktrees/efaktura-epostman-inbound`. Independent QA/security
accept36 runtime/test paths (`b8296faf`). Runbook and private review evidence record
exact source, locked dependencies, passing targeted/browser checks and baseline
reproduction of broad PHP failures. No public Git publication or deployment yet.

Primary checkout remains on `fix/sepa-confirmation-isolation` with unrelated dirty
work intact. Existing vendor/node dependencies are not changed; a separate locked
vendor copy and disposable per-container storage support PHP verification. Synthetic
browser profiles and base snapshots are disposable test artifacts, not the sole
copy of implementation. No production data or provider credentials were used.

## Per-relay protocol status and Freedomrelay defaults - 2026-11-07

Owner requests separate connection/synchronization status and chooses Satflux plus
Freedomrelay defaults after compatibility verification. Both public endpoints reply
with wire1 in a read-only version challenge; no key, subscription or data is sent.
This does not establish quotas, write acceptance, retention or uptime. Freedomrelay
publishes fair-use/no-uptime terms without a numeric cap; free.evoluhq.com remains
a testing service whose data may be deleted and is no longer automatically offered.
Explicit saved lists/order/off flags and legacy custom-only selections remain exact.

Worker observation uses bounded public SDK header primitives, internal owner-ledger
isolation and finite origin/status/timestamp output. Socket-open and protocol quota
are separate. Only a fresh complete same-owner empty reply clears its error;
unrelated owners/reconnect/partial replies cannot. A reply never proves full backup
or replay of previously rejected history. Profile current warnings use live guarded
protocol evidence; global SDK errors are explicitly historical. Offline paused
status leakage is independently reproduced then fixed. Prior authority/SDK limits
remain; the old confirmed keyless default exception is preserved only in its prior
scope, including build/device-off/account guards.

Security35/4 accepts runtime33 `1feebae8...47cc16e`; final source65
`6626058d...da868aa` differs from tested source only in six reviewed text paths.
QA115/6, actual SDK/SQLite1 and final-CSS browser12 pass; provider proof is version
only. Main frontend2,207/204, PHP119/767, type110, lint48/52, locale/build,
Pint/PHPStan2, API3/changelog11 and7 rendered guides/12 links/3 fallbacks pass.
Local AST documents108/108 qualified named functions; no external pass is inferred.

PR432 is open/unmerged at reviewed base `6e747b30`. Main owns scoped publication
and fresh exact-head CI verification; merge/deployment remain separate gates.
Private sanitized evidence: `reviews/evolu-custom-relay/protocol-status/`.
Unrelated primary work is preserved; no dependency, baseline or production-data change.

## PR432 recovery enrollment CI correction - 2026-11-07

CI42353889750 on `4fab39c6` failed only the unchanged recovery-enrollment E2E:
a Profile wizard opened for A submitted refreshed account B (expected409, got200).
Independent browser reproduction confirms the failure. The wizard now preserves
its opening account/flow; stale completion cannot bind local phrases or update a
later flow. Optional current-context guards stop new owner/snapshot work after
invalidation, preserving existing callers. Already submitted work cannot roll back.

Independent security29/4 accepts runtime32 `74de89b0...5fa619` / source62
`49872677...6499c9`; QA84/7 focused checks pass. Every frozen byte matches.
Main lint48/52, type110, locale parity and CI-flags production build pass.
Main frontend2,179/203 and browser24pass/3existing conditional skips pass without retries.
Original409 and subsequent enrollment/restore/logout checks pass unchanged. Main owns
scoped corrective publication and new-head CI verification.
PR432 is open/unmerged; merge and deployment remain separate pending owner gates.
Evidence: `EVOLU_RELAY_MANAGER.md` and private `reviews/evolu-custom-relay/ci-recovery/`.

## PR432 relay origins and canonical cache review - 2026-11-07

Both outside-diff findings reproduce on published `b84d82a6` and are corrected.
Equivalent IPv6 origins normalize before list uniqueness/persistence; accepted
full-IPv4 trailing-dot aliases normalize consistently too. Cached selections never
authorize startup/restored owners without a fresh canonical account read. Unknown
or stale responses remain paused; device-off and account/read ordering are retained.

Reviewed runtime30 `40db84da...b8d22d` / source57 `c29b6fa7...92a622` match the
independent security/QA scope before and after checks. Security20 integration cases
plus900 origin/90 duplicate comparisons pass; QA16/3 including actual SDK/SQLite
and auth races passes. Main frontend2,156/202, PHP110/737, type110, lint48/52,
locale/build, scoped Pint/PHPStan, API/changelog11 and7 rendered guides pass.
Local AST documents91/91 changed complexity-qualified named functions (81 runtime);
this is local evidence, not a new external docstring coverage result.

PR432 remains open/unmerged; review base is `b84d82a6`. Main owns scoped corrective
publication and exact-head CI verification. Merge and deployment remain separate
pending owner gates. No dependencies, ratchets, billing or unrelated primary work
change. Contract, review limits and private evidence: `EVOLU_RELAY_MANAGER.md` and
`reviews/evolu-custom-relay/cache-confirmation/` in the feature worktree.

## Multi-relay manager independently accepted - 2026-11-07

Owner-authorized list/add/detail/enable/remove replaces the single-custom form.
Defaults are `wss://evolu.satflux.io` and `wss://free.evoluhq.com`; existing
custom-only preferences stay exact. Canonical nullable `evolu_relays` rows preserve
explicit empty/off selections, with strict bounded validation and account CSP.
Real SDK worker sockets supply per-relay state through origin-only observations;
Connected never asserts a complete backup. Same worker/SQLite identity is retained.

Independent security accepts19 TS/SFC +30 PHP cases; QA accepts3 SDK/Profile tests,
8 real Chrome worker/OPFS cases and10 responsive/keyboard UI cases. Six verified
review findings are closed, including stale relay revival, device-off resume,
missing-cache pre-auth exposure, queued status replies and SDK owner-query redaction.
Reviewed runtime30 `ebae93dc...18ac3c` / source54 `f9ca87d8...cd60a8` match before
and after independent checks. Main frontend2,129/201, PHP102/693 (relay/CSP/API +
guide selections), type110, lint48/52, locale/build, Pint/PHPStan/API/changelog pass.
Local AST documents87/87 changed complexity-qualified functions, including78 runtime
functions. No dependency, threshold, billing or external repository changes.

PR432 is open at prior published `da17233a`; main owns the scoped corrective
publication and exact-head verification. Merge/deployment remain separate pending
owner gates. Full contract, compatibility limits and private evidence are in
`EVOLU_RELAY_MANAGER.md`; unrelated primary work is preserved.

## PR432 second review accepted locally - 2026-11-06

All four findings are valid and corrected against published `ee294579`: reject
bare hexadecimal numeric hosts, require recovery backup before closing tabs,
cache successful public derivations by weak owner identity, and allow default-only
keyless profiles after explicit account-bound local owner confirmation. Retained
other-account owners, custom destinations, stale callbacks and device-off remain
guarded. The session proof contains only public account ID/key provenance.

Independent security/QA accept runtime20 `67ba4a4d...cdf5a9` / source36
`b93cdd7e...a0e814`, with all bytes verified before/after. Security56 TS +20 Profile
+23 PHP and QA26/3 pass, including actual SDK/SQLite and Profile race coverage.
Main frontend2,052/196, focused87/4, isolated PHP69/481, Pint/PHPStan, type110,
lint48/52, locales/build/API/changelog pass; local AST documents37/37 changed
complexity-qualified runtime functions. No external docstring success is inferred.

PR #432 remains open; main owns corrective publication and exact-head verification.
Prior-head CI passed; new-head checks need separate evidence. Merge/deployment are
pending owner gates. Full contracts/limits/evidence are in `EVOLU_CUSTOM_RELAY.md`
and private `reviews/evolu-custom-relay/second-review/`. Unrelated primary work,
old-worker restart guidance and existing test ratchets are preserved.

## PR432 review fixes independently accepted locally - 2026-11-06

PR #432 is open. These corrections were verified from published review base
`3b0d1e1c`; merge and deployment are separate pending gates. All five supplied inline findings and the cache-writer test nit
are verified still valid and corrected. Secure numeric-host validation rejects
trailing-dot aliases; Profile probing preserves compatible configured fallbacks.
Status records acknowledge the PR; five guides/notifications require loading
settings, closing all Satflux tabs and reopening.

New workers boot local-only and managed public owner registrations reconcile
canonical relay preferences across cooperative tabs. Atomic URL/mode/public-owner
association guards prevent cross-account or torn-cache destination routing;
unknown canonical identity pauses synchronization. No mnemonic/private key is
persisted or sent by this mechanism. Old/crashed worker references require the
close-all-tabs step; already in-flight protocol traffic has SDK100ms disposal grace.

Independent QA/security accept runtime19 `cbef0059...c8e22` and source34
`b878983d...754c84`. QA actual-source helper/real SDK+SQLite scenario1 and SDK5,
security12/1, author79/6 and main43/2 pass; selections overlap. Main full frontend
2,024/195, PHP64/458, scoped Pint/PHPStan, lint48/52, locale and build pass.
Final typecheck110 passes after two erased test-type assertions. The test's
emitted JavaScript is byte-identical; no baseline/runtime change is introduced.

Local AST audit documents all26 changed complexity-qualified named TS/Vue and
all3 PHP methods. This is local evidence, not a fresh external docstring result;
the prior CodeRabbit76.92% warning covers older source. No threshold is weakened,
CLI installed or manual bot command issued. Full findings, contracts, review
manifests and limitations are in `EVOLU_CUSTOM_RELAY.md` and private review evidence.
Main owns scoped source publication to the existing PR; no PR merge/deployment.

## PR432 master conflicts resolved and published - 2026-11-06

Integrate master `b4c2270d` (merged PoS PR431) into relay head `30a9363e`
without rewriting published history. Only PROJECT_STATE, ROADMAP and DECISION_LOG
conflict: retain both complete parent prefixes and their common history exactly
once. All30 reviewed relay source/test/doc bytes retain runtime16
`5bbc13eb...dc39d7` / source30 `4594a9d3...beb9f`; all12 nonconflicting master
paths retain master bytes. The feature diff remains exactly35 paths.

Independent QA `/root/relay_conflict_qa` accepts this content-preservation proof.
Prior relay security acceptance remains applicable to unchanged relay bytes; this
is not a new semantic/security review of unrelated source. Main PHP182/1408,
type110, lint48/52, locale parity and production build pass on the combined tree.
Full frontend1,998/194 also passes. The inspected PHP target is network-disabled,
SQLite-memory with synthetic uncached configuration. No dependencies/ratchets change.

Conflict resolution is published as `3b0d1e1c` and PR #432 is open. Its remote
head and mergeability were verified separately; new-head CI requires its own
evidence. PR merge and deployment remain pending owner approval.

## Evolu relay source publication authorized - 2026-11-06

The owner requests pushing the independently accepted additional-relay feature.
Prepare one scoped commit on `feat/evolu-custom-relay` in the owned Satflux
repository, then verify the actual remote tip. Product bytes remain runtime16
`5bbc13eb...dc39d7` / source30 `4594a9d3...beb9f`, accepted independently by QA
and security. Exactly35 paths include those30 reviewed source/test/doc files and
five coordination records; no unrelated primary edits or private fixtures belong
in the commit. Prior frontend1,940/192, PHP58/432 and static/build/API results
remain attributed to the reviewed bytes; no product checks are repeated for this
publication-only documentation update.

Source publication is complete and PR #432 is open. Merge and deployment remain
separate pending gates; fresh remote checks require actual-head verification.
The primary checkout remains on its existing branch with unrelated work preserved.

## Additional Evolu relay worktree - 2026-11-06

Retain `storage/app/private/worktrees/evolu-custom-relay` on
`feat/evolu-custom-relay`, base local origin/master `2154101e`. Source and five-locale
docs are published for review in open PR #432; the primary checkout stays on its
existing branch with unrelated changes preserved. Private fixtures, manifests,
QA/security reports and gate logs are in that worktree's
`storage/app/private/reviews/evolu-custom-relay/`.

QA/security accept runtime16 `5bbc13eb...dc39d7` / source30 `4594a9d3...beb9f`.
Full frontend1,940/192 and related PHP/API/static/build gates pass; no unresolved
finding remains. Full evidence: the worktree's `docs/development/EVOLU_CUSTOM_RELAY.md`.
Source publication is complete and PR #432 remains open. No merge, deployment or
production verification occurred. Merge and deployment need separate approval.

## PR430 review corrections ready in the retained feature worktree - 2026-11-06

`storage/app/private/worktrees/landing-showcase` retains the independently accepted
review-fix scope on `feat/landing-showcase`, based on published `665cc2f6`.
Runtime15 `f936a8ae...68369f` / source-tests22 `c5c36215...d1bed` match QA/security
acceptance. Main owns source publication to the same owned branch and verification
of the actual remote commit/checks. The dirty primary checkout, design originals,
other worktrees and external repositories are preserved. No merge/deployment.

Evidence, exact scope, original CI attribution and known unchanged header limits
are in [the review record](LANDING_SHOWCASE_REVIEW.md); private reproducible evidence
is retained beneath the feature worktree's existing review evidence directory.

## Landing showcase publication handoff - 2026-11-06

The Product Owner authorizes commit/push from the existing durable
`storage/app/private/worktrees/landing-showcase` worktree. Main owns publication
of exactly 82 accepted paths to `feat/landing-showcase` in owned Satflux; the dirty
primary checkout and other worktrees remain preserved. Reviewed runtime70 and
source/tests77 manifests retain the original accepted fingerprints; publication
records the sole two-space CSS blank-line normalization separately. Record the resulting commit,
remote verification and clean feature-worktree status in the publication receipt
and coordinator handoff; no merge or deployment follows from the push.

## Landing showcase isolated implementation - 2026-11-05

`storage/app/private/worktrees/landing-showcase` retains the authorized implementation
on `feat/landing-showcase`, based on owned `origin/master` `e584490d`. Existing primary
checkout changes and design originals are preserved. Only Satflux is modified.
Implementation is complete, local/uncommitted and independently accepted on final
runtime70 `8466ca0e...ab90f3f` / source-tests77 `fbb90756...4493576`. No push, merge
or deployment has occurred. Final frontend1,882/188 and PHP44/279 pass with the
recorded frontend/PHP/style/build/graph gates. The worktree has its own AST graph.

The worktree has its own synthetic ignored test environment and output directories;
Node dependencies are reused via a dependency symlink. PHP tests run in an isolated
network-disabled container with in-memory SQLite and an exact-lock read-only vendor
mount. Browser checks use a separate local harness, inert fixtures and blocked
external HTTP/WebSockets. Do not infer production/backend persistence or deployment
from frontend screenshots. Final reviewed scope and actual checks are recorded in
[the implementation contract](LANDING_SHOWCASE_IMPLEMENTATION.md).

## Expense reconciliation publication authorized - 2026-11-05

The owner requests commit/push of the reviewed 51-path `feat/expense-reconciliation`
worktree to owned Satflux. Publication runtime23 `620c7f00...956f2` and source/test/CI35
`c947959b...8557b` differ from reviewed hashes only by one removed EOF LF in the
bank-value helper. Main will verify the remote tip/tree and record
the actual outcome in the coordinator's current workspace. Primary checkout work,
PR creation, merge and deployment remain separate.

## Expense reconciliation implemented and reviewed - 2026-11-05

`storage/app/private/worktrees/expense-reconciliation` retains the local,
uncommitted implementation on `feat/expense-reconciliation`, based on freshly
fetched `origin/master` at `cb55aab0`. Only the owned Satflux repository changed.
The primary checkout remains on its prior `fix/sepa-confirmation-isolation` branch
with unrelated work preserved; its workspace record contains the other retained
initiatives.

Independent QA/security accept runtime23 `982868ca...5ef95` and source/test/CI35
`edf41386...46850`. Final frontend1,760/177, focused SQLite129/563 and actual
PostgreSQL2/36 pass. Lint/typecheck/locales/build/API/Pint/PHPStan pass without
raising thresholds. Six broad-PHP DNS validation failures reproduce on unchanged
base; do not claim global/remote CI green.

The ignored worktree graph was updated with AST extraction only; the original
primary graph is preserved. Disposable PostgreSQL used only synthetic tmpfs data
and was removed after review. Complete contracts, exact hashes, resolved findings
and validation limits: [implementation record](EXPENSE_RECONCILIATION.md).

No commit, push, PR, publication, merge, deployment, real payment or merchant-data
access. Next is owner review and applicable source-publication/CI/merge/deployment
approval. Relay redundancy, QR expense payments and the remaining suggestions are
separate proposals.
