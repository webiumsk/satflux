# Local workspace

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
