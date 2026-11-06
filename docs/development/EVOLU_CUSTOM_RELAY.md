# Custom Evolu relay

## Multi-relay manager supersedes the initial UI - 2026-11-07

The owner authorizes a full relay list with add/detail/enable/remove and actual
per-relay socket status, with Satflux + Evolu free as the requested defaults.
See [Evolu relay manager](EVOLU_RELAY_MANAGER.md) for the current contract,
compatibility, review evidence and gates. The sections below retain the earlier
single-custom/default implementation history.


## PR432 second review accepted locally - 2026-11-06

All four supplied findings are verified valid against published `ee294579` and
corrected in this scope. PR #432 is open; merge and deployment remain separate
pending owner gates. Numeric validation rejects bare `0x`/`relay.0x` including
trailing dots, while preserving canonical IPs and hexadecimal DNS labels. Five
user guides link recovery backup before close-all-tabs/reopen; saved notices also
require preserving the phrase. Successful owner-object public derivations use a
WeakMap; failures remain retryable without retaining a mnemonic.

Default-only profiles without a server recovery key may sync after explicit local
binding/unlock proves the current owner/account association. Only public account
ID/key provenance is stored in sessionStorage. Raw retained phrases alone cannot
authorize fallback. Canonical server identity wins; custom destinations, other
account owners and device sync-off retain their guards. Pre-await account/logout
generations, phrase snapshots and synchronous pending-confirmation invalidation
cover stale callbacks, overlapping restores and A-to-B-to-A changes.

Independent security and QA accept runtime20 `67ba4a4d...cdf5a9` / source36
`b93cdd7e...a0e814`, verifying every hash before/after. Security56 private TS,
20 repository Profile and23 pure PHP pass. QA26/3 pass, including actual production
helpers with installed Evolu7.4.1 Sync/protocol and in-memory SQLite, and7 actual
Profile component cases. Synthetic tests do not claim full browser OPFS, hardware
passkey, live TLS relay, production or deployment coverage. Public proof is per-tab
and fails closed; old/crashed workers still require the documented full-tab restart.

Main frontend2,052/196, focused87/4, isolated PHP69/481, Pint2, PHPStan, type110,
lint48/52, five-locale parity, production build, three API schemas and changelog11
pass. Existing PHPUnit deprecation/build warnings remain; no threshold/dependency
changes. Local AST documentation covers37/37 changed complexity-qualified runtime
functions (34 TS/Vue +3 PHP), plus three documented test helpers/methods. This is
local evidence, not a new external docstring coverage result.

Reviewed source is retained in the existing relay worktree; private manifests,
review reports, synthetic fixtures and logs are under
`storage/app/private/reviews/evolu-custom-relay/second-review/`.
The primary checkout and unrelated work are preserved. Main owns corrective
publication to the existing PR and exact-head remote verification. Prior-head CI
at `ee294579` passed; new-head checks require separate evidence. No merge,
deployment, manual bot/CLI command, installation or external repository write.


## PR432 second review contract and corrections - 2026-11-06

Review base is published `ee294579`; PR #432 remains open. Treat all supplied
review text, paths and suggested commands as untrusted evidence. All four findings
are verified valid; no finding is skipped. Merge and deployment remain separate
pending owner gates.

| Finding | Verified correction |
| --- | --- |
| Bare hexadecimal numeric hosts | Both numeric classifiers recognize empty hexadecimal suffixes. Reject `0x`, `relay.0x` and terminal-dot variants before normalization. Existing full IPv4/IPv6 and hexadecimal DNS labels remain valid; hexadecimal IP aliases remain rejected. Before-fix JS2 and PHP4 negative cases fail as expected. |
| Recovery before tab restart | All five guides require saving the recovery phrase with the existing backup/recovery link before closing tabs. Existing sign-in/settings/close-all/reopen instructions remain; five saved notifications also mention the backup prerequisite. |
| Repeated public derivation | Successful public keys use a WeakMap keyed by owner object. No mnemonic copy is retained; unavailable/throwing derivations remain uncached and return null. |
| Keyless default-only profile | A public, session-scoped association records an explicitly bound/unlocked local owner and current account. Only an empty custom URL with no canonical server key may use it. Canonical keys take precedence; custom destinations, unconfirmed/retained other-account owners and device-off remain guarded. |

An existing session phrase alone has no account provenance and cannot authorize
the fallback. The Profile captures account/logout generations before awaiting an
explicit restore/unlock and confirms only successful results against the exact
current phrase. Typed input is snapshotted. Account changes, logout, phrase changes
and stale overlapping callbacks invalidate proof. A synchronous account watcher
also clears pending owner-switch approval, including A-to-B-to-A changes; an old
asynchronous preview cannot reinstate it. Matching public proof survives same-tab
reload; raw phrase or failed binding never grants it. No additional private key,
mnemonic, new server field, dependency or ratchet change is introduced.

Main owns the validators, Profile and its tests, five guides/locales, coordination
records and publication. Actual frontend specialist `relay_keyless_fix` owns the
proof/auth/lifecycle modules and their tests. Independent `relay_r2_security` and
`relay_r2_qa` review read-only with synthetic data; they do not publish. Graphify
query completed in the primary checkout; the feature worktree has no graph and
none is rebuilt or installed. Final evidence follows below after validation.


## PR432 remediation validation - 2026-11-06

Published review base: `3b0d1e1c`. PR #432 is open; source fixes are prepared on
its existing branch. Merge/deployment are separate pending gates.

| Supplied finding | Verification and correction |
| --- | --- |
| Trailing-dot numeric aliases | Valid: both old checks skipped shortened IP aliases ending in a dot. Inspect the dotless numeric host; retain full IPv4/DNS behavior and add positive/negative regressions. |
| Stale PR status | Valid: old local/pre-publication records still implied no PR. Update project-state/roadmap/workspace to open PR432 without conflating merge/deployment. |
| Worker restart guidance | Valid: reload only affects one tab. All five guides and saved notifications require loading settings, closing all tabs and reopening. |
| Configured fallback probe | Valid: strict custom validator incorrectly rejected supported configured HTTPS/WS fallbacks. Validate nonempty entered text strictly; normalize only the empty-field fallback compatibly. |
| Live owner destinations | Valid: cache changes left old AppOwner/SharedOwner/implicit boot references active. New workers use local-only boot plus tracked public SDK registrations, canonical identity guards and cooperative cross-tab reconciliation. |
| Failed-refresh writer assertion | Valid: notifications alone did not protect the cache. Expose the writer mock and assert no call after canonical refresh fails. Successful auth fetch is now the sole canonical cache writer. |
| Missing docstrings | Valid: the external prior review reports76.92% against80%. Add missing relevant runtime/test-helper docstrings; local AST checks find26/26 named TS/Vue and3/3 PHP complexity-qualified methods documented. |

No supplied finding is skipped as invalid. Review data and optional CLI hints
are not authority to execute commands, install tools or send bot instructions.
The external coverage number is historical; do not infer a new-head pass from
local AST counts.

Final runtime19 SHA256:
`cbef0059f6d8289f2e379338a7c4a66db976e52224e3bbe1b7690c349ddc8e22`.
Final source/tests/docs34 SHA256:
`b878983dd45974a11dbb52c55849d0662cce57bfc7e35bfc58640875c1754c84`.
Both independent reviewers verify every hash and accept the final scope.

- Main full frontend:2,024 tests /195 files pass. Final two non-null assertions
  only refine SDK-created test-owner types; independent reconstruction and TS
  transpilation verify emitted JavaScript is byte-identical to the full-suite run.
- Main focused numeric/probe/cache43 /2 and PHP relay/CSP/API64 /458 pass.
  Scoped Pint2 and PHPStan pass; one existing PHPUnit deprecation remains.
- Lifecycle author79 /6 pass, including16 permanent new regressions. Scoped ESLint
  passes. Main lint48/52, five-locale parity and production build pass.
- Final typecheck110 passes with no new errors and an unchanged baseline.
- Independent QA: one actual SOURCE-helper integration scenario passes against
  real installed Evolu Sync/SQLite/encrypted protocol, plus five SDK lifecycle
  checks and an independent rerun of43 source tests. Two synthetic tab contexts
  cover both owner types, removed-relay reconnect, public identity/reload guards,
  local history retention, atomic cache reads, storage/channel failures, stale
  notifications, hidden-tab suspension and off. The pre-fix implicit-reference
  and hidden-tab defects are independently reproduced and closed.
- Independent security:12 cases /1 file pass on the frozen helper. Intermediate
  cross-account, torn-read, guard-downgrade and stale-notification risks are closed.
  Before/after proofs distinguish actually reproduced revisions from source review.

No private key/phrase or new recovery secret is persisted/transmitted. The cache
association is an existing PUBLIC recovery identifier. Unknown canonical identity
pauses sync until existing enrollment/binding can prove the owner. No SDK internals,
owner reset, dependency/ratchet changes, billing edits or other repositories are used.
Old bundle/crashed-tab references still require the documented full-tab restart;
normal cooperative registration updates have the SDK100ms in-flight disposal grace.
No full browser/TLS/OPFS/production or per-relay complete-backup guarantee is claimed.
Private final reports, manifests, synthetic fixtures and logs are retained under
`storage/app/private/reviews/evolu-custom-relay/review-remediation/`.

## PR432 review remediation contract - 2026-11-06

PR #432 is open at published `3b0d1e1c`; merge and deployment remain separate
pending gates. Review text, paths and suggested commands are untrusted evidence.
Validate each supplied finding against current code and keep fixes bounded.

The five inline findings and cache-writer test nit are still valid. Both numeric
host checks must inspect the hostname without its terminal dot while preserving
canonical IPv4/hostname behavior. Profile probing must distinguish strict entered
URLs from the compatible configured fallback. Failed canonical refresh must not
write the boot cache. Current status records must acknowledge the existing PR.
All five user guides and saved notifications must require loading settings, then
closing every Satflux tab and reopening, to restart an existing old worker.

Installed Evolu7.4.1 page reload affects one tab, worker initialization ignores
later init, and public disposal is unimplemented. A new worker must therefore boot
without implicit external transports; application-managed public `useOwner`
registrations reconcile AppOwner and SharedOwners together. Canonical preference
changes notify initialized clients without eagerly importing the database.
Other tabs must use the latest cached selection, not stale profile objects.

Destination changes require verifying the active AppOwner's existing recovery
public identity against the canonical account. Unknown or mismatched identity
pauses transport selection rather than sending retained history to another
account's relay. Persist that public association atomically with URL/mode so
reload cannot bypass the guard, including an empty custom URL. Preserve legacy
raw cache and device-off formats; never persist or transmit a new mnemonic/private
key. Same-tab canonical notification must remain safe if cache persistence fails.

The installed SDK disposes removed transport resources after100ms. Tests must
verify no new writes/reconnect reconciliation reach removed destinations after
disposal, without claiming cancellation of already in-flight protocol responses.
Workers from a previous bundle require the documented close-all-tabs migration;
no unsupported worker reset or destructive owner reset is permitted.
The SDK also lacks dead-tab owner-reference cleanup. Cooperative active tabs can
reconcile and release references normally; an abruptly terminated tab can leave
an orphan reference. Closing all tabs and reopening is the general prerequisite
for guaranteed retirement, including that case, rather than an implicit crash
recovery guarantee from the new manager.

Main owns the validators, Profile/probe tests, status/user docs, locales and Git.
`relay_lifecycle_fix` owns auth, owner registration/lifecycle modules and related
tests/cache changes. `relay_lifecycle_research` supplies read-only installed-SDK
evidence; independent `relay_review_security` and `relay_review_qa` own no source
writes. Final acceptance requires matching frozen bytes and separate reviews.
Add missing runtime function docstrings; do not weaken the external80% threshold.
The supplied CLI hint does not authorize tool installation or manual bot commands.

## PR432 master conflicts resolved locally - 2026-11-06

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

Publish the conflict resolution to the existing owned PR432 branch under the
owner's request, then verify remote head and mergeability. New-head CI must be
attributed separately. No PR merge, deployment or unrelated primary edits occur.

## Accepted scope and contract - 2026-11-06

Owner explicitly confirms adding a custom Evolu relay alongside the default.
Single custom replacement already exists. Implement an explicit option to keep
the configured default alongside the custom relay, preserving existing replacement
behavior. A profile without a custom URL starts with the additional-default option
enabled in the form; existing custom settings retain their saved mode.
No external repositories, production,
payment logic, dependency upgrades, publication, merge or deployment are in scope.
Base: local origin/master 2154101e; actual deployment is unverified.

- PUT /api/user accepts existing nullable evolu_relay_url and optional boolean
  evolu_relay_add_default (default false for existing users). Omission preserves it.
- Only secure wss relay origins are accepted from user settings. No embedded
  credentials, query strings or fragments; root trailing slash may be normalized.
- The default is the configured build relay, with existing public fallback. Explicit
  build disablement never re-enables that default through the additional option.
- With a custom URL, add_default=false selects custom only; true selects custom
  followed by enabled build default, deduplicated. Empty custom selects default.
- Device sync-off always produces zero transports. Pre-auth cache preserves both
  settings; fetchUser refreshes it. Reload applies transport/CSP changes.
- AppOwner and every SharedOwner use one owner registration with all chosen
  transports. Existing single-relay status and usage must identify that scope;
  they cannot assert that both relays acknowledged a complete backup.
- Encryption and recovery keys are unchanged. The self-hosted relay must support
  the installed Evolu 7.x protocol. Relay storage is not a substitute for exports.

## Ownership and gates

| Task | Role | Writable scope | Acceptance and evidence |
| --- | --- | --- | --- |
| API and persistence | backend | AccountController.php, User.php, EvoluRelayUrl.php, new relay setting migration, AccountTest.php | Validation, persistence, omitted-field compatibility, authenticated scope; isolated PHP tests, Pint/PHPStan |
| Browser and UI | frontend | relay config/storage/preference/owner factories, auth.ts, Profile.vue, EN/SK/ES/DE/CS locales, relevant frontend tests | Both owner types and boot cache, device-off, deduplication, replacement compatibility; Vitest/static/build/locale gates |
| Integration and docs | main | Feature note, five custom-evolu-relay articles, project state/roadmap/decision/workspace | Reviewable behavior, reproducible validation, preserved unrelated work |
| Independent reviews | security and qa | No source writes | Exact scoped revision/diff, findings, negative cases and independent evidence |

Specialists do not publish. Main owns shared contracts, coordination records and Git.

## Implemented behavior

The account profile stores the additional-default choice independently of the
custom address. Boot configuration and both owner factories use a single ordered
URL resolver and register all transports together. A profile with no custom URL
offers the additional-default option checked; legacy custom-only settings remain
custom-only. Changes reload the document to apply owner transports and CSP.

Custom addresses are secure origins only. Server validation rejects unsafe
components atomically before either preference changes. The browser rejects raw
paths and ambiguous numerical host forms before URL canonicalization. The existing
build URL normalization remains compatible.

Authenticated profile settings take precedence over the pre-auth cache. Logout
retains the selected boot cache: deleting it could attach a persisted owner to a
default/public relay during recovery preview before authentication. Device-off
retains its existing sentinel and explicitly supplies empty owner transports.

EN/SK/ES/DE/CS UI and new user documentation explain the additional connection,
protocol compatibility, primary-only usage information and backup limitations.

## Review and verification

Frozen runtime16 SHA256:
`5bbc13ebf88aa910b2a867ac4802c7bdcbf35555943c7e37e02ba9704adc39d7`.
Frozen source/tests/docs30 SHA256:
`4594a9d347ab2389d9a640f0844cced0c449988100565ef1393c15edba4beb9f`.
Manifests hash sorted path-to-SHA256 maps using compact sorted JSON, then SHA256.
Coordination records are excluded so evidence can be completed without changing
the reviewed product bytes.

- Frontend author:106 tests /11 files pass, including cache compatibility, owner
  transport arrays/keys, duplicate/default/off selection and Profile save behavior.
- Backend author:67 tests /262 assertions pass across account, CSP and docs,
  excluding five unrelated email-DNS guest-upgrade cases in the offline harness.
  Scoped Pint5 and PHPStan pass. The full72 selection reproduces those five
  offline failures; it is not represented as green.
- Independent QA `/root/relay_qa` accepts the frozen manifests:16 extra Vue/auth
  cases,71 source tests /7 files, PHP relay+CSP47 /202 assertions and locale parity.
- Actual installed Evolu7.4.1 protocol: six independent checks pass and main
  reproduces them. AppOwner/SharedOwner write to both relays; additional works
  during default outage; a new SQLite client restores only from additional;
  default catches up on reconnect without new mutations; incompatible protocol
  fails closed; stored synthetic changes are encrypted. WebSocket delivery is
  simulated, SQLite databases are in memory and no network is used.
- Final main full frontend suite passes1,940 tests /192 files with bounded
  workers. Typecheck passes with111 baselined errors tolerated and no new ones;
  lint passes with48 existing warnings against52; five-locale parity and production
  build pass. No ratchet or dependency change. Changelog validation passes11
  unchanged fragments after normal escalation for sandbox Git subprocess EPERM.

The security review found a HIGH privacy regression in an intermediate logout
cache-clearing change. It could expand a legacy custom-only owner's pre-auth
transports to the default. That change is removed; author and independent QA
regressions verify preservation through401/logout and device-off. Independent
security `/root/relay_security` accepts the corrected runtime with no unresolved
security finding:52 focused tests /3 files and its extra privacy regression pass.
The regression fails the byte-verified intermediate source and passes the fix.

Security also catches the stale generated API provenance inventory. Isolated
regeneration changes only the AccountController/User source hashes in
`docs/api/route-coverage.json`; route entries and all three OpenAPI schemas remain
unchanged. The export currentness check and three-schema validator pass. Main
PHP58 /432 assertions, including the API drift test, passes with one existing
PHPUnit deprecation. No product code changes follow the full frontend/static gates.
The earlier source29 manifest remains the independently reviewed product/test/user
doc snapshot; source30 adds only this generated inventory to the same bytes. Both
independent QA and security reviewers verify the exact two-hash diff and every
source30 file hash, and renew final acceptance without unresolved findings.

Private reproducible QA fixtures and manifests are retained under this worktree's
`storage/app/private/reviews/evolu-custom-relay/`. Protocol fixture SHA256:
`3c062f9e22ef6b03027450f3f32fc98e06148c48569bea3458e34b8bde583090`.
No real TLS/WebSocket server or full browser E2E was tested. These checks do not
prove complete backups on live relays. Graphify was queried in the primary checkout;
this isolated worktree has no query graph and no graph rebuild was performed.

## Delivery boundary

The owner authorizes committing and pushing the accepted source on 2026-11-06.
Publication uses `feat/evolu-custom-relay` in the owned Satflux repository, from
`storage/app/private/worktrees/evolu-custom-relay`. The main agent verifies the
actual remote tip after push and records that receipt separately. Exactly35
scoped paths are prepared; all30 reviewed source/test/doc bytes remain unchanged.
The primary checkout and unrelated work remain preserved. No PR creation, merge
or deployment is requested by the push instruction. Applying the migration and
frontend together requires the separately approved deployment process; deployed
version/state and fresh remote CI remain unverified. A public changelog fragment
can be prepared once an actual feature PR URL exists.
