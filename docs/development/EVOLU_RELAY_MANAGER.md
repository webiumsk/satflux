# Evolu relay manager

## Protocol snapshot publication performance - 2026-11-07

Verified the review finding on `fdaeb583`: a steady burst of1,000 request/reply
pairs generated2,000 redundant BroadcastChannel snapshots. Both observer paths
still process every frame/send, but publish immediately only when their sanitized
protocol enum changes. The five-second heartbeat keeps fresh timestamps, explicit
snapshot requests still reply immediately, and quota/recovery/unsubscribe remain
immediate. No client/schema/lifecycle/provider/authority behavior changes.

Two regressions fail before the fix and pass after it: zero extra snapshots for
the same burst, with identical SDK frame delivery, fresh heartbeat and explicit
request replies. Scoped90 tests/5 files, type110 and scoped ESLint0warnings pass.
The other32 runtime/63 source entries retain prior accepted bytes. Main owns this
bounded frequency correction and publication; no new broad security audit is claimed.
Fresh exact-head CI remains a publication gate; merge/deployment remain separate.
Private sanitized evidence is in `reviews/evolu-custom-relay/snapshot-performance/`.

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

Protocol observation is bounded to current public wire headers. SDK asynchronous
storage/decryption/full-body apply failures lack a route-specific public hook and
are not claimed as comprehensively attributed. Prefix bytes may transiently include
payload/key bytes, but none are retained/published; no encrypted content is decoded.
Already submitted SDK operations cannot be retroactively cancelled. Unknown/old
worker observations remain unknown, and rollout still requires saving the phrase,
loading settings and closing all tabs before reopening. Public provider probes are
version-envelope compatibility evidence, never a production-data sync test.

## Recovery enrollment CI correction - 2026-11-07

The only failure in CI42353889750 (`4fab39c6`) is the existing multi-tab Profile
recovery enrollment scenario: expected409 account_changed, received200. An
independent real-browser run reproduces it with the same unmodified spec and CI
build flags. Background canonical profile refresh exposes a target captured only
at final submission. The server correctly checks the ID it receives; the client
must preserve the wizard-opening account instead of adopting a newer profile ID.

Each Profile opening now captures account/flow generations and an immutable done
callback. Explicit expected IDs reach the existing server guard; null intent never
falls back. Changed-account, ABA, unmounted and obsolete completions cannot bind
local phrases or affect a reopened wizard. Optional current-context predicates in
local initialization/snapshot restoration stop new owner/flag/subscription work
after waits and stop queued/next row writes. Already submitted SDK/row/setup work
cannot be cancelled or rolled back; canonical lifecycle still gates transport
identity. Existing callers without the opt-in guard retain their behavior.

The correction is eight source/test paths. Frozen runtime32 `74de89b0...5fa619`
and source62 `49872677...6499c9` are independently verified before/after review.
Security29/4 accepts; QA84/7 focused checks pass, including native snapshots and
existing default callers. Main lint48/52, type110, locale parity and CI build pass.
Local AST documents86/86 changed complexity-qualified TS/Vue named functions;
unchanged PHP evidence remains separate. No fresh external docstring pass is inferred.
Main full frontend2,179/203 and browser24pass/3existing Free-PRO conditional skips
pass without retries. All original recovery assertions remain:409 account_changed,
CSRF419, intentional enrollment, new-device restore, store cleanup and cross-tab
logout. All enabled checkout/signed-settlement cases pass too. Main owns scoped
publication and exact-head CI verification; prior CI failure is not counted as green.

This is a bounded Profile correction; other enrollment callers are not claimed as
a new complete recovery audit. No E2E assertion, dependency, baseline, locale string,
backend authorization, cryptography or relay fail-closed policy is weakened. Private
sanitized reports/fixtures/manifests stay in `reviews/evolu-custom-relay/ci-recovery/`.
No raw browser state, generated phrase or trace is retained there. Merge and
deployment remain separate pending gates.

## IP origins and canonical cache confirmation review - 2026-11-07

Both supplied outside-diff findings reproduce against `b84d82a6`. Canonicalize
accepted IPv6 forms before the existing list duplicate check and persistence;
preserve strict address/port validation. A related accepted full-IPv4 terminal-dot
alias is normalized too, matching browser origins without adding new host forms.

Persisted relay cache, including legacy formats and matching public owner keys,
is metadata rather than authority. Startup and page restoration pause transports
until a fresh canonical profile confirms the current destinations. Cache/storage/
channel notices request revalidation; unavailable or stale responses remain local.
A revision guard and bounded retry protect invalidations during a profile read;
revalidation reads do not echo broadcasts or supersede initial router auth reads.
Device resume retains its off sentinel until a fresh same-account read succeeds,
then clears and applies synchronously. No additional private material is persisted.

Backend owns the URL rule/API regressions; frontend owns lifecycle/auth and their
focused tests. Main owns Profile integration, related guides, final state and Git.
Independent security and QA review read-only with synthetic data. Preserve prior
worker/SQLite identity and unsupported dead-reference/in-flight disposal limits.
No billing, dependency, baseline, upstream, merge or deployment scope is added.


Validation on the frozen correction: runtime30 `40db84da...b8d22d` and source57
`c29b6fa7...92a622` are byte-verified by independent security and QA before/after.
Security20 actual-auth integration probes plus900 browser/PHP origin comparisons,
90 duplicate pairs and19 invalid controls pass. QA16 tests/3 files pass, including
actual SDK/SQLite routing, local reads while paused, router initialization, bounded
retries, suspension and device-off races. Both original findings are valid; none
is skipped. Independent review additionally caught the missed-Off-event edge:
resume now pauses existing handles before awaiting its canonical GET.

Main full frontend2,156/202, isolated PHP110/737 (relay/CSP/API), Profile/manager34/2,
author112/5, type110, lint48/52, locale parity and production build pass. Scoped
Pint/PHPStan, three API schemas and11 changelog fragments pass. Seven actual user
articles render,12 local links resolve, and3 established English fallbacks remain.
Local AST documents91/91 changed complexity-qualified named functions,81 runtime;
no fresh external docstring success is inferred. Private reports/manifests/fixtures/
logs stay in `reviews/evolu-custom-relay/cache-confirmation/`. The existing SDK
in-flight disposal grace and old/crashed-worker full-close restart limits remain.
Prior-head CI passed; new-head checks require their own evidence. Main owns the
scoped push to open PR432; merge/deployment remain separate pending gates.


## Recovery documentation review corrections - 2026-11-07

Both supplied findings are verified valid against published `d79191ea`. All five
relay guides now distinguish same-owner relay recovery (correct phrase plus an
available data copy) from exported invoicing-backup import into another prepared
account (no original recovery phrase; the file's backup passphrase if encrypted).
The existing EN/SK backup guides include actual sync-tab export/decrypt/preview/
merge steps and remove phrase-alone recovery promises. Other locales retain the
established English fallback for that article; no new article set is introduced.

Independent QA accepts all seven guide hashes and23 existing backup/export/import/
crypto tests across4 files. Main DocumentationController8/26 passes; all7 real
articles render,12 local links resolve, and3 English fallbacks remain intact.
Runtime/app/database/resources/tests are unchanged; prior runtime30 security/QA
acceptance remains applicable. Prior-head CI passed, and new-head checks require
separate evidence. Merge/deployment remain separate gates. Private evidence is in
`reviews/evolu-custom-relay/recovery-docs/`. Optional review CLI hints grant no
installation or manual bot-command authorization.


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

## Authorized contract - 2026-11-07

The owner rejects the single-custom/default-checkbox interaction and authorizes a
relay list inspired by the supplied Linky screenshots: add multiple relay URLs,
per-relay live connection state, and a detail view with enable/disable and custom
relay removal. Requested defaults are `wss://evolu.satflux.io` and
`wss://free.evoluhq.com`. No other public relay is added automatically.

Existing Satflux feature worktree/PR432 remains the integration target, reviewed
base `da17233a`. Primary checkout is dirty and preserved. Remote PR432 is still
open/unmerged at that head, base `b4c2270d`; actual production deployment is not
inferred from local or remote source. Graphify query completed in primary;
feature graph is absent and is not silently rebuilt.

- New nullable account JSON `evolu_relays` contains ordered `{url, enabled}` rows.
  Null/absent uses legacy settings; an explicit list is exact, including empty or
  all-disabled. At most8 configured rows and2048 total normalized URL bytes; strict
  WSS origins, boolean flags, exact keys, duplicate rejection and atomic validation.
- Both requested defaults are used when no custom legacy preference exists.
  Explicit build disablement remains off. An enabled build primary override is
  retained alongside the public secondary; explicit legacy custom-only stays exact.
  Existing rows are not migrated destructively or populated per merchant.
- Save the account list through PUT /api/user, refresh the canonical profile, then
  reconcile guarded AppOwner/SharedOwner registrations. Cache the full list and
  public account association atomically; preserve device-off and public local
  keyless-default proof. No private recovery key/phrase is newly persisted.
- List edits affect the account. Each detail toggle retains its row; the global
  device-off control remains device-only. Default rows cannot be removed in UI
  but can be disabled. Custom rows can be removed with explicit confirmation.
- Legacy clients see at most the first enabled row; old bundles cannot represent
  no enabled destination. Coordinated assets/full-tab restart is required before
  relying on the new selection/paused state; rollback compatibility is a separate
  deployment gate. New workers keep the existing application/SQLite identity.
- Live status uses actual installed SDK WebSocket handles in an application-owned
  worker assembled only from public dependency factories. A separate sanitized
  URL/status snapshot channel has freshness expiry and no protocol/data/key fields.
  Connected means socket open, never complete sync/backup. Observation failure
  degrades status to unknown and cannot grant transport authorization.
- Authenticated document CSP permits enabled, strictly validated account relay
  origins and HTTPS twins. API response CSP does not replace a document policy;
  changed lists reload the document. Workers have separate CSP behavior; no claim
  that Laravel document CSP restricts every worker socket is made.
- EN/SK/ES/DE/CS UI and user guides move together. Preserve existing theme,
  accessible names/focus/keyboard behavior, narrow screens and row-specific states.

| Owner | Exclusive writable scope |
| --- | --- |
| main | Profile integration, new relay manager component/UI tests, locales/user guides, API exports, coordination records, final integration/publication |
| relay_list_backend | AccountController, User, new migration/relay-list rule, SetSecurityHeaders, Account/SecurityHeaders tests |
| relay_list_frontend | config, storage, preference/lifecycle/auth modules, new helpers and focused tests excluding Profile/UI |
| relay_list_status | reloadGuard, application-owned DB worker, new status observer/composable and dedicated tests |
| independent security/QA | Read-only final architecture/code review and synthetic test evidence; no source/Git/publication writes |

Acceptance requires meaningful 3+ relay/disable/removal/legacy/cache/account-race
regressions, WebSocket callback/disposal/retry/freshness transparency, CSP isolation,
PHP/static/type/lint/locale/build gates and independent security/QA on frozen bytes.
No dependency/baseline changes, production relay tests, billing changes, other
repository writes, merge or deployment. Main owns scoped Git publication under
existing authorization; specialists do not commit or publish.

## Reviewed evidence and delivery limits

Final runtime30 SHA256:
`ebae93dc22086120d53e621992121bff75264a6f224d5c448d15b3580918ac3c`.
Final source/tests/user-docs54 SHA256:
`f9ca87d83965f082f6f0dd7a05cdd6c442d62699dfa97b9d26b0db4de0cd60a8`.
Both independent reviewers verified every manifest entry before and after tests.
Two final explanatory PHP test docblocks were moved ahead of their attributes;
executable text was proven unchanged and formatting/documentation audits passed.

| Verified finding | Resolution and regression evidence |
| --- | --- |
| HTTPS defaults could not be edited through the WSS-only list API | Materialize the equivalent WSS origin, preserving explicit ports. Plain WS/HTTP legacy synchronization remains supported; list editing shows an actionable secure-default requirement instead of silently upgrading insecure endpoints. |
| Suspended pages could accept an expired queued snapshot | Timestamp/prune pending nonces with monotonic time; expired observations require a fresh request/reply before Connected returns. |
| Overlapping or missed cross-tab profile reads revived removed destinations | Latest-read generations reject stale success/401/auth-transition results. Atomic cache drift pauses this tab, retries one canonical GET, and stays unavailable on repeated drift without overwriting the newest cache. |
| Device sync resume used stale cached profile rows | Keep the device-off sentinel during a fresh canonical read; require the same uninterrupted account before clear/reconcile. Failure, account ABA or unmount remains off. |
| Missing pre-auth cache authorized retained OPFS owners against new defaults | Missing cache has an explicit null owner association. AppOwner/SharedOwner history stays local until canonical identity or explicit keyless-default proof authorizes it. |
| Actual SDK socket URLs always carry ownerId query parameters | Redact sender URL queries to an origin rather than bypassing observation. Received snapshots still reject query-bearing values. Permanent regression uses actual createOwnerWebSocketTransport; Chrome verifies real worker sockets and no owner identifiers in snapshots. |

Main full frontend2,129 tests/201 files pass. Main focused UI/status57/4 and
pre-auth/lifecycle49/2 pass (overlapping selections). Isolated PHP relay/CSP/API
94/667 and user-guide8/26 pass; Pint7 and PHPStan4 runtime files pass, with one
existing PHPUnit deprecation. Typecheck retains110 tolerated baseline errors,
lint48 existing warnings remains within52; five-locale parity, production build,
three OpenAPI schemas and changelog11 pass. Existing build chunk/annotation warnings
remain. No baseline or dependency is changed.

Independent security19 TS/SFC +30 pure PHP cases pass on frozen bytes. Independent
QA3 Vitest tests/2 files use actual production helpers + installed SDK/SQLite and
actual Profile integration. Chrome151 actual-worker8 cases verify old SDK to new
worker OPFS owner/data preservation, three loopback SDK sockets, late-tab worker
reuse, disposal, origin-only snapshots and worker-death expiry (~19.9s). Actual
manager10 browser cases use synthetic services/states, final production CSS,
375px/desktop layouts, confirmation/focus/keyboard behavior and failed-switch
restoration. Separate Slovak list/detail previews are retained. Selections overlap;
no production data, external relay, real funds or hardware passkey is used.

Private reports, manifests, fixtures, logs and previews are retained under
`storage/app/private/reviews/evolu-custom-relay/relay-manager/`.
No full production TLS relay or per-relay complete-backup guarantee is claimed.
Legacy cache formats remain readable for migration, but cached selections cannot
authorize transports; startup and page restoration require fresh canonical
confirmation. Missing/malformed caches fail closed. Old/crashed worker references need the
full-tab restart, and SDK in-flight transport disposal retains its grace period.
Database migrations must precede compatible frontend assets. Older bundles cannot
represent an all-disabled/empty account list, so rollback to those bundles needs
selection compatibility review; merge and deployment are separate owner gates.
The free relay is explicitly requested but remains a test service, not a sole backup.
