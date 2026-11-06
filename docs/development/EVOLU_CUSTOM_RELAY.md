# Custom Evolu relay

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
