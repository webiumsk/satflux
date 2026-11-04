# Expense defaults and bank-mail reconciliation

## Scan-progress and indexed-feed verification - 2026-11-04

The frozen runtime-four manifest is
`0090c75404f8564b373b10dc30f5b7b475b1e5ff7fc69c619988202c73032557`;
the source/tests-six manifest is
`d075ba2ee6bcff1b82cc4cd1f84bf61a63fe4e57b8a3226fa27a5a6bfa89c589`.
Publication is limited to those six paths and four development documents.
Importer, expected-owner verification, IndexedDB store and credential queue have
no changes from a0cc390e.

Main full frontend: 1,675 tests / 173 files passed; production build passed in
69 seconds. Lint has zero errors / 48 existing warnings. Author focused tests:
147 cases / six suites passed, scoped lint is clean and typecheck has 111 existing
findings / none new. Locale keys are unchanged; all existing ratchets remain.
Backend: 41 real HTTP/SQLite tests / 264 assertions and Pint on three files passed.
Full local PHPStan has no new finding beyond the inherited optional Scramble gap;
fresh installed-dependency CI remains authoritative.

Prepared query-plan probes use 60,000 synthetic rows in SQLite 3.51.2 and
PostgreSQL 16.11. All four head/tuple-seek/null-fill/null-seek queries use the named
composite index without sort nodes. The old CASE still sorts after adding only
the index. PostgreSQL probes include residual snapshot-filter visits (18 or 16
rejections for 101 returned rows); this does not prove constant rows examined.
SQLite migration up/down/re-up and PostgreSQL index removal retain fixture rows.
The standard Laravel CREATE INDEX is not CONCURRENTLY; production build timing and
migration execution belong to a separately approved deployment. No production DDL
or data was touched; the owned temporary PostgreSQL environment was removed.

Both independent reviewers accept the exact runtime-four/source-six manifests.
QA independently passed 262 repository cases / nine suites, 31 Vue progress probes,
nine retained Evolu/native SQLite probes and 14 credential-order probes. Equivalent
SQLite plan/order/rollback probes returned 400 ordered rows without omissions or
duplicates and retained all 403 fixture rows after index removal. Security passed
221 repository cases / six suites, 31 Vue progress probes and 41 PHP tests / 264
assertions in isolated SQLite memory, with one inherited deprecation. Both reviewed
the supplied PostgreSQL plan artifacts without independently executing PostgreSQL.
Scopes overlap and are not summed. All six hashes match before and after review.
Fresh full CI and actual manual CodeRabbit review remain required before all-green.

## Scan-progress and indexed-feed repair contract - 2026-11-04

Actual review 5765381601 on a0cc390e found a missing feed-ordering index and a
known-incomplete-page retry loop. All four CI jobs and five pre-merge checks passed,
but these valid findings require a new repair and fresh verification.

Protocol 2 distinguishes scan progress from financial coverage. For an admitted
known incomplete page, the retry cursor advances only the scan schedule, records
unresolved=true and marks the current cycle incomplete. Dirty wraps retain the
unresolved obligation while starting a fresh cycle accumulator. Every full cycle
revisits surviving rows; only a complete acknowledged clean cycle can clear status.
No exact-range coverage claim, invalid-row dropping, re-auto-matching or financial
compensation is introduced. Unknown and unverified admission never save a candidate.
Strict page ACK, expected-owner proof and existing payment eligibility remain exact.

Authenticated cursor frames carry both flags with existing user/company/source/
context/page-size/order bindings. Metadata includes required protocol 2 booleans
unresolved (before the page) and unresolved_after_success (the successful next
candidate). The client latches true before import and replaces the error state
only after selected-candidate CAS succeeds. Known incomplete selects retry/true;
conflict, failed storage, stale scope or Unknown cannot establish a clean cycle.
A read failure before server metadata remains best-effort without a fresh coverage
claim. IDB still stores only opaque ciphertext and hashed context; no new plaintext
status, schema, keys or unbounded retry list. Each refresh still reads at most two
bounded chunks and never races entered financial work.

The feed gains a reversible (company_id, source, created_at, id) index and separate
non-null and null range reads, preserving timestamp-descending/UUID-descending,
null-last order and a combined page-size-plus-one result bound. Query-plan evidence
must cover head, timestamp ties and null backfill; existing balance-snapshot filters
can still require examining rejected candidates. Ordinary listing and authorization
stay unchanged. No SQL injection exists in the previous constant CASE expression;
the valid issue is its ordering plan. New client/backend must agree on protocol 2;
ordinary pagination remains compatible. Require independent exact-byte QA/security,
all fresh CI and actual manual review. No production replay, migration execution,
merge or deployment is performed.

## Progressive history repair contract - 2026-11-04

Actual manual review5764391436 completed on `b78d09b8` with three inline and one
supplemental finding despite green CI and five passing pre-merge checks88.10%.
The all-history20s walk discards completed pages when a later read expires, starving
large histories. Revise that earlier discard-all choice to bounded acknowledged
progress; also unify failed connection-check UI, cancel the address GET and retain
loading ownership with the latest invocation, including initial-query completion.

The opt-in same-route API uses `sync=1`, `source=email`, a64-hex `sync_context`,
optional opaque `sync_cursor` and page size at most100. Ordinary pagination,
filters, ordering, summary, payload formatting and authorization stay unchanged.
Cursor frames are authenticated Laravel ciphertext bound to version, authenticated
user, route company, email source, context, page size and ordering. They carry no
short expiry and grant no authorization; current route access is always enforced.
Unsupported sync filters and invalid frames use only
`errors.sync_cursor=["bank_sync_cursor_invalid"]` with422.

The frame alternates a bounded latest-head overlap with descending older backfill
using explicit NULL-created-at ordering and UUID ties. Head reads cannot jump an
existing backfill frontier. Completed backfill cycles restart, eventually revisiting
gaps/new-head overflow, same-timestamp lower IDs and late/backdated/NULL records.
Nullable timestamps and UUIDs do not prove monotone arrivals, immediate coverage
or latency under arbitrary write rates. A candidate frame advances its page only
after ACK; a retry frame changes scheduling alone and retains the exact frontier.

Each refresh performs at most two read chunks, with20s per read and10s per HTTP
request. Read cleanup precedes the financial page import. No overall timer races
financial work. Strict page ACK requires fresh admitted expected-owner equality,
every input's owner/company/hash durability or proven duplicate, and terminal
verification. `scope_verified=false` returns zero without writes or checkpoint
changes; the strict new path also refuses unproven fallback admission. Existing
legacy import/fallback/CSV/Wise contracts stay unchanged. Empty pages also require
admitted owner proof. Valid direction-evidence `unverified` rows can count as
covered but remain ineligible for automatic expense payment. Existing hashes are
not re-auto-matched; first-time historical imports retain existing eligibility.

Known invalid/uncovered inputs remain visibly incomplete; valid subsets may be
processed under existing rules. An admitted incomplete page can save only the
retry scheduling frame and service the other lane before reporting the error.
Unknown/error never advances that chunk; earlier proven committed chunks remain.
No automatic retry of Unknown/failed financial operations or compensation is
introduced. Admitted incomplete lane scheduling may overlap already-covered rows
without re-auto-matching them.

IndexedDB stores only server ciphertext and SHA-256 binding identifiers in one
owner/company slot. This protects server-known cursor metadata, without claiming
Evolu client-only encryption or adding keys/schema. Compare-and-set uses one
transaction and resolves on transaction completion; conflict stops the losing
attempt. Current context is checked before put. Dedicated checkpoint metadata may
have its own20s abort/deadline after bank proof: uncertain persistence can only
replay proven rows, never skip uncommitted financial work or imply rollback.
Only the exact cursor-invalid422 resets the captured slot by CAS once per attempt;
authorization/network failures never reset it. Missing protocol, incomplete pages
and unavailable checkpoint storage have five-language recovery messages.

Four root-owned native IndexedDB Playwright tests exercise concurrent CAS, reopen
persistence, provisional request success followed by transaction abort, stale/abort
zero-put and transaction-completion ordering. Listing/bundling passes locally;
native execution is a required CI gate because local sandbox syscalls block Chrome.
Independent QA/security accept the frozen source; fresh full CI remains required.
Deploy compatibility is backend first or together: old clients keep ordinary
pagination, while the new client reports unavailable protocol against an old backend.
No production replay, merge/deployment, server migration, owner/key/billing policy
or credential queue change is part of this repair.

## Progressive history repair verified evidence - 2026-11-04

Both independent reviewers accept canonical runtime13 SHA-256
`d4b6bcc44553b8ca43511519bc1a62ca327aca10aaea018d7517f7df228ab51a`
and source/tests21 SHA-256
`6c628d695391f39cd72a4bbf4ed4b6cb1674e919d14b55f5d718860bf2c232bf`.
Exactly25 paths form publication scope:13 runtime,8 test/support and4 documents.
Main full frontend1666cases/173files, production build70seconds and lint zeroerrors/
48knownwarnings pass. Typecheck111existing/no-new and EN/SK/ES/DE/CS parity pass;
warning/type/docstring thresholds are unchanged.

Author client176cases/8suites, root page/cancellation79/2 and backend31 HTTP/SQLite
cases/153assertions/Pint pass. PHPStan has no new findings; unchanged local optional
Scramble dependency prevents a clean local full result, so fresh CI is authoritative.
Independent security222repository/6suites +20actualVue progress +9actualEvolu/SQLite
+2ownerboundary regressions pass and independently repeats31PHP/153assertions.
Independent QA337repository/13suites +20progress +33actualEvolu/SQLite
(9new/24retained) +14retainedcredential-order probes pass. These scopes
overlap and are not summed. Four real-browser IndexedDB tests are source-reviewed,
bundled and listed only locally; fresh CI execution is required before all-green.
SQL evidence proves LIMIT/noCOUNT-or-SUM, not universal rows-examined or latency.

Confirmed draft SEC-R11-OWNER is fixed before bank writes by comparing fresh
financial owner with the captured expected owner inside admitted scope. Strict
unproven fallback cannot write or change lane/checkpoint; empty pages need admission.
Legacy CSV/Wise/fallback contracts remain. No API artifact change is needed because
the controller is outside the curated exporter; fresh CI still checks API artifacts.
Verify exact committed blobs/remote SHA, full fresh CI and actual manual review.
No production, merge or deployment action is included.

## Credential-order repair verified evidence - 2026-11-04

The runtime correction shares normalized server-bridge completion gates across
hook instances and both modes. Gates contain no credentials, are registered before
waiting, always settle in finally and prune only their own current tail. Stale queued
context rejects before HTTP; entered POST success/identical error stays awaited.
Failed predecessors cannot poison later explicit intent. Independent bridges remain
concurrent, and status reads occur outside the credential gate.

Both independent reviewers accept runtime2 manifest
`4304ae7321e8494e92004eea02067d2d7d16b6e296e5a758efbe814f750244a9`
and source/tests5 manifest
`3734a9700bb2e06f7ec5e6bf058fd4c89780b96899283c9088ce1956f7e11f44`.
All five source/test blobs match after checks. Exactly nine paths form the commit
scope: two runtime files, three tests and four development documents.

Main final frontend1623cases/171files, production build75seconds, lint48known/
zeroerrors, typecheck111existing/no-new and five-language parity pass. Author151/7
affected cases pass. Independent QA219repository/9suites,14new Vue/real-Axios probes
and47retained regressions pass. Security119repository/3suites,14 Vue/Axios and7
additional Vue runtime probes pass. Scopes overlap and are not aggregated.
Canonical889 baseline reproduces three overlap failures; actual encrypted SQLite
service and mounted page fixtures establish the original last-finishing-write
scenario. Synthetic clients cover serialized local/server/cross-mode instances,
bridge aliases, queued A-B-A/disposal, identical errors, independent bridges,
held status outside the gate and entered/queued requests beyond60seconds without
new deadlines, retries or POST signals. No real credentials/financial writes.

Backend/API/page, registry, owner routing, read budgets, allocator and financial
protocol remain unchanged. Ordering is limited to dispatch after entering this
loaded client's bridge queue. Ambiguous transport/global remote completion and
other runtimes remain outside its proof. Publish only the nine reviewed paths,
verify committed blobs/remote SHA, finish fresh full CI and actual clean manual
review before all-green completion. No merge/deployment or production action.

## Confirmed credential-order repair contract - 2026-11-04

During the manual review allowance wait, independent security confirmed Medium
SEC-WISE-CONNECT-ORDER on published `889e1e69` despite complete green CI. The page's
busy reset permits a second A connection after A-B-A while an older A credential
POST has already entered. Backend profile/balance reads precede settings update;
an older completion can overwrite newer credentials after newer UI success.
This differs from the repaired pre-metadata stale-dispatch case.

An actual mounted page probe and actual WiseBankSyncService with encrypted settings,
SQLite memory and fake HTTP reproduce the overwrite. No real credentials, production
or financial writes are involved. Existing owner-only authorization remains.

Acceptance for the narrow runtime correction:

- Share one credential-POST completion gate per normalized server bridge across
  local/server modes and hook instances; independent bridges remain concurrent.
- Store only completion promises in the runtime map, prune settled gates by identity,
  and ensure a rejected predecessor does not poison a later separate explicit intent.
- Revalidate captured queued intent immediately before dispatch, including A-B-A
  and disposal. A stale queued intent never sends a credential POST.
- Await an entered POST's actual success or identical rejection. No deadline,
  cancellation, automatic retry, compensation or rollback inference touches it.
- Keep follow-up status outside the credential queue and in its captured context;
  inherited legacy status latency must not hold the POST gate.

This controls dispatch within one loaded client runtime. It does not establish
global remote completion after ambiguous transport failure or across clients.
No backend credential revision/quarantine policy, global financial lock or owner/
key/billing change is introduced. Frontend owns composable, new queue and focused
inbound/server/queue tests; main owns documentation and Git. Independent frozen-byte
QA/security, relevant full checks, scoped publication, complete fresh CI and an
actual clean manual review remain gates. No merge/deployment or production replay.

## Ninth repair verified evidence - 2026-11-04

All six reports are addressed. Initial credential intent is captured before any
metadata await; stale intent cannot dispatch after A-B-A. Local bank resolution
and status GET share a20-second read budget with positive HTTP caps up to10seconds,
parent cancellation and post-await checks. Context-only guards survive read cleanup;
entered credential/sync POSTs and financial imports remain awaited with identical
errors/Unknown. Missing/stale sync contexts keep zero counts, while known current
lookup failure rejects. Imported/skipped counts come from the actual importer.

The current page owns one transaction refresh per semantic routing change, with
batch refresh only for company changes. Status failure retains a retry while
hiding guessed connection controls. Address-helper controls prevent pre-dispatch
continuations after scope changes. Registry/bootstrap, owner/financial modules,
allocator behavior and nonbank caller defaults remain unchanged. Legacy server
status transport and existing address GET defaults remain outside the new local
read bound; canonical metadata POST ambiguity remains explicitly recoverable.

Both independent reviewers accept runtime5 manifest
`9d0e0c913ab865b36a2c7868a100bdf407320bcc7822a0d477c57a652d668b7e`
and source/tests10 manifest
`67dabf4870601512a9afa11c0f8d035c18bc9aab33f5776b9a5d9cf3ec1d9d0b`.
All10 source/test blobs match after final checks. Exactly14 paths form the commit
scope: five runtime files, five tests and four development documents.

Main final frontend1609cases/170files, production build76seconds, full lint48known/
zeroerrors, typecheck111existing/no-new and five-language parity pass. Author151/8
focused cases pass. Independent QA205repository/8suites,22new Vue/helper/Axios
probes and25retained Vue probes pass. Security179repository/5suites plus22new and
25retained probes pass; earlier52/6 authority/default/migration checks use unchanged
protocol modules. Scopes overlap, so counts are not aggregated. Adapters are
synthetic; no new production/native-database proof or financial writes are claimed.

Publish only this accepted scope, verify committed blobs and remote SHA, finish
complete fresh CI and obtain another actual clean manual review after capacity.
Prior3ac059d1 CI/pre-merge success does not validate the unpublished repair. No
threshold, owner/key/billing policy, production replay, merge or deployment action.

## Ninth review acceptance contract - 2026-11-04

Published `3ac059d1` passed all four jobs in CI41777047822. The authorized manual
command6623273752 was accepted6623274923; actual review5763674984 completed at
05:30UTC with two inline and four supplemental findings. Five pre-merge checks
pass, including88.52% documentation at the unchanged80% threshold. The success
check does not clear the findings.

The next repair must verify and address all six reports:

- Capture Wise credential intent before the initial metadata await, so local
  company A-B-A cannot dispatch an old token after a newer connection. Unknown
  initial intent may fail conservatively with the token retained for explicit retry.
- The current page refreshes transactions once per semantic routing-context
  change, including same-company bridge changes. Batch metadata refresh stays
  limited to company changes. This revises the earlier address/status-only choice;
  otherwise new-bridge email rows remain missing until a separate page action.
- Wise synchronization reports the importer's actual added count and local skips,
  combined with independent upstream skip counts, rather than received row count.
  The inherited unlocked-import/worker-count boundary does not change.
- A separate latest-current status-failure state shows the existing localized
  retry and hides guessed connection controls/status until a successful check.
  Keep the failure visible during retry; stale errors cannot replace newer results.
- One bounded local-bank read stage covers metadata, bridge resolution and Wise status
  GET, up to20seconds overall and10seconds per HTTP request. Nested helper reads
  must share remaining controls, settle on timeout/disposal and check controls
  before and after fresh metadata/AppOwner awaits. Bank-opt-in page lookups are
  bounded even without caller controls; default nonbank helper/API behavior stays.
  Legacy server-mode status requests retain their existing one-argument API defaults.
- Known current sync lookup failures reach the localized error path through
  `bank_bridge_lookup_failed`. Confirmed missing or stale contexts keep their
  zero-count behavior; no false green result is published for a failed lookup.

Read cleanup must leave a separate context-only guard valid for subsequent
credential and financial work. Only read-only phases are raced/aborted; entered
credential/sync POSTs and local financial imports retain their exact outcomes,
including Unknown. No automatic replay, compensation, owner/key policy or default
issuance change is included. The page owns current-context refresh errors; no
hidden composable import watcher is introduced. Frontend owns the bounded helper,
composable, optional Wise-status API controls and related tests; main owns page,
locales/docs and publication. Independent frozen-byte QA/security, scoped commit,
complete fresh CI and another actual clean manual review remain required.

The page also supplies a request/CID/routing-key checked cancellation factory to
its address helper. New context, a superseding lookup or unmount aborts that read
before late metadata can dispatch more bridge HTTP. Canonical bridge metadata
creation retains the existing ambiguity under a read-stage timeout/abort: the
server may already have created the row, and explicit retry uses canonical lookup
without deletion or compensation. Credential and sync POSTs are separate operations
outside this cancellation path.
The existing inbound-address GET retains its earlier API defaults and stale-response
guard; this repair does not claim to cancel or suppress every inherited HTTP request.

## Eighth repair verified evidence - 2026-11-04

The five review items are repaired. Known unresolved Wise connection routes now
reject before any credential POST, preserving the pasted token and localized
failure. Started POSTs and financial imports remain awaited with their actual
outcome. Both composable follow-up reads and page result/token/error publication
stay in the captured routing context, including A-B-A and disposal; old completion
cannot start a fresh email import in a newly selected company or clear its busy UI.

Both independent reviewers accept runtime10 manifest
`b1f3de86ce6bb3b18f82ce0096f90a52309f18316f886801652573f4daeea26c`
and source/tests15 manifest
`e08706b281d030260d4e2072130eaa9743e71e71df19aa1363d2176045d29052`.
All15 bytes match after final checks. Exactly19 paths are the publication scope:
10 runtime/locale files, five test files and four development documents.

Main final full frontend1579cases/169files and production build73seconds pass.
Full lint48known/zeroerrors plus final changed-file lint0 pass; final typecheck
111existing/no-new and five-language parity pass. Author128cases/7files pass.
QA175repository/7suites and25mounted actual-Vue probes pass. Security independently
passes168repository/7suites on unchanged authority/migration/rekey modules, then
76final inbound cases and25mounted probes on the final delta. Scopes overlap.
The earlier1576/169 main run predates the two post-financial guards and is not
the final source evidence. Retained probes demonstrate the old failures before
repair. Storage, HTTP and financial entry-point adapters are synthetic; no real
financial writes, production access or replay occurred in these probes.

Publish only this reviewed scope, verify all committed blobs and remote SHA,
finish complete fresh CI and request another actual manual review after provider
capacity. All existing thresholds, owner policy and financial Unknown semantics
remain. Current review findings are repaired, but current-head CI/manual approval
must still be obtained; no merge or deployment is included.

## Eighth review acceptance contract - 2026-11-04

All four CI41771525497 jobs pass on publishedc0921567, independently verified by
QA. Authorized manual comment6622425495 was accepted6622426859 at04:18UTC;
actual review5763268988 completed04:34UTC. Its three inline findings and two
supplemental items remain repair work despite a success check and five passing
pre-merge checks (87.72% documentation, unchanged80% requirement).

Acceptance for the narrow follow-up:

- Initial metadata rejection must remain best-effort, then allow an explicit
  bank refresh after subscribed company rows recover. No identity means no import.
- Pending inbound attempts may share only a current company/selection/identity
  generation. Local A-B-A and identity edits must start a new explicit attempt;
  an older attempt's cleanup must not erase the newer attempt.
- Wise HTTP rows must still belong to the legal identity and generation captured
  with the resolved bridge before import, including identity and selection A-B-A.
  Imports already entered retain their exact completion or Unknown outcome.
- Failed lookup stays distinct from confirmed missing identity/bridge. Keep
  connection controls and an explicit localized retry available; stale failures
  cannot publish into another company or a disposed page.
- Bank reads and displayed inbound settings must prefer an authoritative recorded
  shared-company bridge over duplicate legal identities. Follow existing trusted
  registry/owner rules, invalidate changed mappings and preserve ordinary allocator
  defaults. Do not infer authorization from a client bridge ID.

The recorded route comes only from the existing AppOwner-filtered, secret-verified
registry bootstrap. The raw share query lacks an owner predicate despite its
comment and is not routing authority. A pinned route still requires one selected
company row under the recorded SharedOwner; a private fallback requires one row
under the actual AppOwner. Missing, malformed, unavailable or mismatched binding
metadata fails retryably without creating/guessing a duplicate identity bridge.
Observable routing context contains only selected nonsecret metadata and readiness,
including exact selected-company row uniqueness/owner so same-ID partition changes
invalidate pending reads even when legal name and recorded binding stay unchanged.

Initial bootstrap does not block page mounting. The bank read-stage budget must
therefore include the first trusted registry readiness wait, canceled by the same
abort/disposal path; no automatic financial watcher/retry is introduced. Install
the existing share subscription after initial validated application and before
marking readiness or running unchanged migration/rekey recovery. A maintenance
failure must not leave a ready registry without live invalidation. Keep existing
owner-scope policy and default helper behavior unchanged.

Frontend owns the composable and its two affected test files; main owns page,
locales, documentation and Git. Read-only mapping exploration and independent
QA/security acceptance precede scoped publication, fresh complete CI and another
actual manual review under provider capacity. Preserve existing read budgets,
disposal cleanup, owner isolation, financial quarantine, before-import-only identity
boundary and all warning/type/docstring thresholds. No production access/replay,
merge, deployment, billing/key policy change or unrelated primary publication.

## Disposed-view lifecycle follow-up - 2026-11-04

A pre-review source audit identified a remaining before-import identity gap on
published7e9af5f1. Independent QA reproduced it using actual mounted `@evolu/vue`
subscriptions: bank view disposal unsubscribes its per-hook shallow reference,
Settings sees an immutable replacement identity, but the old bank snapshot stays
unchanged. A late old-bridge response entered the importer. No financial mutation
was executed in the reproduction. The baseline failure is retained as evidence.

The local bank scope now has an explicit disposed state and generation invalidation.
Disposal cancels active fetch-stage callbacks, aborts HTTP and settles the read race
immediately even if metadata/transport ignores cancellation. Resolver/public reads
and late Wise continuations cannot restart work on a disposed instance. Read timers
and registrations are removed before financial import begins. Imports already
entered remain awaited through disposal and preserve exact success/Unknown outcomes;
no cancellation, compensation, replay or rollback inference touches financial work.

This closes a before-import guard failure. The separately recorded limit about
later company edits while a valid import waits for the origin lock still applies.
No company CRUD lock, API/allocator/default transport, owner/key or backend change
is included. Only the composable and its targeted regression file change.

Both independent reviewers accept runtime1 manifest
`29becc3a71d698c9e04e90b8ca8e62b80390d48404b875014615e83d3c70cce6`
and source/tests2 manifest
`d579efc011de5360d1aa3fdbc69e78f03c0afa6278ac7f1fa9b8fbf55e5dbc43`.
Main209 cases/10 affected bank suites pass; production build, full lint48known/zero
errors and typecheck111existing/no-new pass. Author71cases/4files pass. Independent
QA and security each pass46 repository cases and7 mounted real-Vue probes, with
synthetic cache/transport/import-entry adapters and no actual financial writes.
Pending lookup/page disposal after immutable identity replacement, ignored-abort
metadata/HTTP, zero timers/restarts, and entered-import success/Unknown are covered.

Parent7e9af5f1 has full1504frontend/167files and green CI41769392442. These parent
results are historical; the new commit must pass complete exact-head CI before
the next authorized manual review. Publish only6 reviewed paths, verify both
committed source/test blobs and remote SHA, and continue until actual review/checks
are green. No merge, deployment, production replay or unrelated primary changes.

## Sixth review contract and independent evidence - 2026-11-04

The completed manual review of7cd2b9ec at03:19UTC adds one inline finding, one
pagination follow-up and a deleted-query test nit. Its five pre-merge checks pass,
including87.27% documentation coverage at the unchanged80% threshold. CI41758379808
passes on that published head; it does not verify the new working-tree repair.

The bridge selector uses the existing normalized company identity and semantic
change generation. Initial company metadata is awaited before capture, avoiding
a false empty-to-loaded invalidation. Lookup/cache results and the final fetch
are rejected after legal-name/registration changes or A-B-A edits; outer whitespace
and case follow existing identity matching. Concurrent same-identity Wise lookups
do not invalidate a valid bank result. This is selection metadata, not a new
authorization principal or a company-CRUD/queued-import atomicity guarantee.

One20-second read-stage budget covers company metadata, bridge lookup/create/
canonical re-list and all bank pages. Every HTTP request recomputes its remaining
time, capped at10seconds, and uses the same cancellation signal. Monotonic elapsed
checks reject late results even when transport ignores cancellation; the deadline
and page yields prevent an immediately resolving endless page stream starving the
clock. Any failure discards the entire collected set, releases shared synchronization
and leaves local queries usable for an explicit retry. Cleanup completes before
financial import; the budget never races worker completion or establishes rollback.

Optional bridge-request options preserve ordinary allocator/Wise arguments,
credentials, payloads and default timeouts. A metadata POST may succeed after
client cancellation; no record is deleted, compensated or automatically recreated.
Explicit retry re-lists and converges through the existing canonical identity path.
The temporary narrow metadata-read catch preserves prior ordinary-Wise failure
behavior without swallowing local import/Unknown outcomes.

The new reconcile test imports the real client/query module without a client mock.
Only its unused browser dependency factory is inert. A separate real Evolu API
probe receives the production serialized expense query through SQL-aware synthetic
storage. A deleted stable receipt remains pending/no ACK, while a different live
control is acknowledged exactly once, with zero financial mutations. This is not
a native SQLite or browser-storage claim.

QA/security accept identical final runtime4 manifest
`927a9d92058991f54782d535d855572bd77075ebec363227def28ac8eb2427a1`
and source/tests7 manifest
`a63822f8354ac43c888f31a12252d789b88c0a78b0fca526ea35cb7def9f2406`.
Author78cases/6files pass; QA independently passes94cases/10suites and13 actual
Axios/helper probes/2suites; security passes62 repository cases, the final2-case
metadata/endless-page recheck and13probes. Scopes overlap. Typecheck111existing/
no-new, scoped lint0, diff checks and default issuer regressions pass. Main final fullfrontend1504/167, production build and full lint48known/zero errors
pass on these frozen bytes; the earlier1503/167 run began before the final narrow
catch/test change. Five-language parity and baseline budgets remain unchanged.
Publish only11 reviewed paths, verify committed bytes/remote SHA, complete new CI
and the next actual manual review. No production access/replay, merge or deployment.

## Fifth review contract - 2026-11-04

The authorized manual review completed on published `3dce0b4f` at 01:49 UTC.
All CI jobs pass on that revision and all five pre-merge checks pass, including
86.92% docstring coverage against the unchanged80% threshold. The completed review
adds five actionable findings, so the owner's repair/check loop remains active.

- Server transaction and batch reads must abort superseded requests, discard
  stale company/generation results, and clear previous-company batches. Catch
  actual cancellation inside each loader; active non-cancellation errors still
  propagate. Preserve credentials, CSRF, scoped routes and ordinary API defaults.
- Each bridge bank-list page has a finite10-second wait and opts out of global
  error flashes because fetch failures remain best-effort. The shared sync clears
  on timeout, local movements remain readable and an explicit retry can fetch
  again. Do not import partially fetched page sets after a failed fetch.
- Local import rejection, including Unknown, must reach a current-company page
  handler with existing localized recovery. Do not automatically replay, retry,
  compensate or release worker quarantine. Remove redundant background loads from
  the local company watcher only after verifying the page is the sole production
  consumer and already owns mount/company refreshes.
- A fresh same-company nondeleted stable expense permits an ACK conflict to
  complete as local already-imported success only for Axios422 with the exact
  sole `errors.inbox = ["Inbox item is not pending."]` signature. Backend source
  confirms this means a terminal inbox, not proof of remote imported status.
  Generic422, authorization/session/not-found/network failures, new imports and
  foreign/deleted targets retain their failure behavior. No server change.

Main owns page error handling, stable-inbox recovery/tests and shared state;
frontend owns composable/API request lifecycle and matching regressions. Separate
QA/security reviews must verify timeout/retry, cancellation without unhandled
rejection/global flash, stale batch contexts, visible local failure and narrow
ACK recovery before scoped publication. Then wait for full exact-head CI and
available review capacity before the next authorized manual review command.
No merge, deployment, production access or payment authority expansion is included.

## Fifth review evidence - 2026-11-04

Independent QA and security accept the same frozen runtime4 manifest
`b7887cd1c5469280eda1ed1f6c5906ace1cfb770cff231d21bc584dd9417983f`
and source/tests9 manifest
`b33cfdeb57808d06f013e4ffa552a55b99934bd1fe7dac4ea2af2999c19de663`.
Every source/test byte matches. Main full frontend passes1,485 cases/165files;
root55 and author46 focused cases pass. Full lint retains48 known warnings/zero
errors; typecheck111existing/no-new, build and five-language parity pass with
unchanged ratchets. Backend source and API route fingerprints are unchanged.

Independent QA passes112 repository cases/seven suites and14 temporary cases/two
suites. Security independently passes101 repository/five suites,14 temporary/two
suites and the unchanged backend ACK contract6PHP/64assertions in verified memory
SQLite with network disabled (one inherited deprecation). Scopes overlap.
The actual Axios and native SQLite/Evolu probes cover cancellation without global
flash/unhandled rejection, stale batches/A-B-A, failed second-page timeout with no
partial import and reusable shared sync, visible known/Unknown import outcomes,
no hidden watcher attempt, exact existing-local422 recovery and generic/auth/new/
foreign/deleted negative cases. Adapters remain synthetic, not browser/network E2E.

Both reviewers report no unresolved finding. Publish only13 reviewed paths after
verifying committed manifests, then exact remote SHA, complete fresh CI and the
actual next review-capacity notice. Resolve only the five published addressed
threads. The owner's repair/check loop continues until an actual review and all
checks are green. No production access/replay, merge or deployment is included.

## Documentation coverage follow-up - 2026-11-04

All four jobs in CI41751685173 pass on published `6ba7e908`, including full PHPStan
and Composer audit. The owner explicitly requests waiting for all checks, then a
manual `@coderabbitai review` comment after the review-capacity reset, with further
repair/check iterations until green. The bot reports the next included review in
24 minutes at 01:06 UTC; use approximately 01:31 UTC to allow a reset margin.

The retained pre-merge warning reports docstring coverage of 27.27% against an
80% threshold. A bounded AST audit identifies meaningful missing contracts in
already changed functions. Add 70 JSDoc blocks in 13 Evolu/composable files,
three API method contracts and 17 plain PHPDoc blocks in five PHP files. Document
locking/completion, owner routing, exact match qualification and failure/recovery;
add no annotation tags, executable changes, new tests or configuration exceptions.
The local audit metric is explicit and does not reproduce the bot's denominator.

The 20-path comment/metadata manifest is
`75cab06764fe739578eb1df9521988741eb8aa89dc50aa64fdcebafc01ae06b7`.
It covers 14 TypeScript and five PHP source/test files plus the parser fingerprint
in API coverage. The 453 route objects remain unchanged. PHP author proof compares
all 12 PR-changed PHP paths with `6ba7e908` after excluding comment/doc/whitespace
tokens; syntax parsing and Pint5 pass. Independent QA accepts every byte in the final20 manifest and verifies exact
parser-aware TypeScript tokens (including types), comment-free emitted JavaScript
and PHP TOKEN_PARSE tokens against independently extracted6ba Git inputs. No
annotation, directive or suppression changes occur. Full lint retains48 known
warnings/zero errors; typecheck111existing/no-new, Pint5 and API validators pass.
Prior runtime QA/security evidence carries through this proven equivalence. Main
must publish exact reviewed bytes, wait for the new complete CI and issue only
the authorized review command after capacity returns. A neutral/rate-limited check is not a completed review.

## Fourth review contract - 2026-11-04

The owner's further CodeRabbit request covers the review of published `f0849584`:
one inline availability finding and two follow-ups. Bound only the inbox detail
and imported-acknowledgment HTTP calls below the 30-second reconciliation wait
deadline. Retain the authenticated API instance, stable IDs and worker completion
proof. A detail failure creates no expense; an acknowledgment timeout preserves
the committed expense and attachment. The remote acknowledgment may already have
succeeded, so there is no rollback, compensation or automatic retry claim.

A rejected match insert must return false to automatic matching and reject the
manual action visibly. Known validation rejection must drain normally; actual
unknown worker outcomes retain quarantine. Do not change credit eligibility,
payment authority, owner routing or existing unlink/paid-edit policies.

Real Evolu regression testing exposed a missing scope refresh before newly
imported credits create match children. Use the existing scoped commit helper
for the synchronous inbound row batch, then verify every actual ID/company/hash
against that fresh worker-backed context before matching or counters. The child
owner guard remains strict; unlocked import retains its previous completion path.
This adds only the three bounded auxiliary reads needed to refresh scope metadata,
with no additional worker response packet, marker or per-row read.

Capture the selected company for batch actions, suppress errors after a company
change and refresh only the company that initiated the action. Independently
guard server transaction/summary/loading responses by company and request
generation, including A-B-A. Fully handled bank mutations and post-action list
refreshes opt out of the unscoped global error flash; ordinary API callers keep
their existing default. Page handlers retain visible current-company errors.
Independently review the four bounded source changes and their timeout,
failed-write and deferred-company regressions before main-owned PR427 publication.
Graphify remains unavailable because its local graph is absent. No production
access, dependency installation, merge or deployment is included.

## Fourth review evidence - 2026-11-04

Independent QA and security accept the same final four runtime files and ten
source/test files with no mismatches or unresolved findings. Canonical SHA256
manifests contain sorted repository-relative paths, two spaces after each hash
and LF line endings:

- Runtime4: `8f71c874b0e81eb1fde3291405d668a13d7b08b53319440d491ec8ff831bf1f0`.
- Source/tests10: `8c24ef15357f2a4268a049c6ff29ea230a3a97c1bb24b52e81658dd7587d6918`.

QA independently passes 164 repository cases across nine suites and 12 temporary
cases across three suites. These include real Evolu/native SQLite with the actual
document payment helper for private/shared owners, timeout and lost-ACK recovery,
known validation rejection versus unknown quarantine, and Axios/server-composable
context/error handling. Security independently passes 66 repository cases across
five suites and seven native cases across two suites. Scopes overlap; these counts
must not be added as distinct coverage. HTTP, locking and worker adapters remain
synthetic, with no browser/OPFS or real-network E2E claim.

Main full frontend passes 1,443 cases across 164 files on the frozen source/test
bytes; 51 focused page/composable cases also pass. Author passes 32 focused cases,
including the atomic row-batch regression: 34 query calls, five worker response
packets, eight fixed query keys, with no per-row reads. Full lint retains 48 known
warnings and zero errors; typecheck retains 111 existing findings and none new.
Production build and five-language locale parity pass with unchanged ratchets.
Local PHP is not rerun for this browser-only delta. Earlier exact-head CI
`41747417307` validates `f0849584`, not the new working tree. Main must commit only
reviewed paths, verify committed manifest bytes and remote SHA, resolve the single
new inline thread and verify new exact-head CI. No merge, deployment or production
replay is performed.

## Authorized scope and observed evidence

On 2026-11-03 the owner requested linked defaults for new expenses and diagnosis
of missing production bank movements since July 16. They subsequently confirmed
that today's Tatra notification appears as Delivered in Mailgun and should mark
an existing expense paid. Mailgun retains only one day of logs. Delivered proves
webhook acceptance, not queue completion, persistence or expense reconciliation.
No production email, account, token, database or queue has been accessed here.

PR426 is actually merged as `686c9304`; older state entries saying open are
historical. Work continues on `fix/expense-defaults-bank-import` in the retained
expense worktree. The unrelated primary checkout and other repositories remain
untouched. Graphify's graph is absent; no build or tool installation is attempted.

## Acceptance and integration contract

- New expenses: supplier document digits default VS, received date defaults
  delivery date. Manual overrides and explicit attachment fields detach the
  respective default. Existing records retain their stored values.
- Bank address and local synchronization resolve the same selected company's
  bridge. Stale responses cannot change another company's address or data;
  overlapping same-company inbound refreshes share one import.
- Existing persisted Company recipient tokens of 8-16 characters remain usable
  under generation-length configuration drift. Exact domain/prefix/DB lookup,
  Store recipients, webhook authentication and bank-origin proofs remain intact.
- The base Compose queue restarts after its configured hourly exit. Local deploy
  configuration selects standalone Compose, which already has a restart policy;
  this source defect is therefore not established as the production cause.
- Automatic existing-expense reconciliation applies only to email movements:
  explicit raw debit, unmatched, no invoice/expense link, not a balance snapshot.
  The sole eligible expense must belong to the same company, be recorded with
  no paid/cancelled date, have no other movement link, and match exact positive
  fixed-point amount and a nonempty three-letter currency. There is no tolerance,
  partial-payment accumulation, overpayment conversion or first-candidate choice.
- A present bank VS takes precedence and must contain digits. It matches the
  normalized expense VS, or external document digits only when expense VS is
  absent. Malformed nonempty VS on either side fails closed. When bank VS is
  absent, only an exact trimmed external-number/reference match is eligible.
  Leading zeros remain significant; reference substrings never auto-pay expenses.
- A valid actual calendar booking date supplies the expense paid date. PHP locks
  and rechecks current state inside one transaction. Local writes prevalidate
  both updates and submit them synchronously to the same Evolu mutation queue;
  installed Evolu7.4.1 aborts the complete queue on validation failure. Context
  and counts change only after both validation results succeed.
- New API responses add `recorded_direction` independently of the existing
  inferred display direction. New local debit auto-match requires that explicit
  recorded direction; older servers can still import movements without immediate
  expense auto-match. Import and explicit batch matching serialize context reads
  and writes per Evolu instance and refresh affected queries before releasing it.
- New imports and explicit batch auto-match use this rule. Duplicate refreshes
  do not retroactively change historical expenses. Existing unlink semantics
  preserve the expense's paid state. No provider checkout, SEPA fulfillment,
  subscription, entitlement, schema or bank verification changes are involved.

Company mail reconciliation retains its existing bookkeeping trust boundary;
Company MIME decoding does not independently attest bank origin. This feature
does not promote that evidence into Store payment confirmation. Concurrent
offline-device CRDT uniqueness is not a new guarantee.

Debit-only parser qualification prevents new Tatra/SLSP imports from inventing
missing dates/currency or inferring VS from unrelated remittance digits. Credits
and Store proof remain unchanged. Existing persisted transactions have already
lost their original parsing provenance: their reconciliation uses normalized
ledger fields and does not retroactively qualify the original bank email.

## Ownership and review gates

| Task | Role | Writable ownership | Acceptance evidence |
| --- | --- | --- | --- |
| Expense defaults | frontend | ExpenseForm.vue, expenseFormDefaults.test.ts | Real mounted local/server form, manual/edit/ISDOC regressions |
| Address and queue | backend | BankInboundAddressService.php, compatibility test, one old fixture, base Compose queue | Token drift, strict rejection, no rotation, exact one-line restart change |
| Existing expense server match | backend | BusinessExpensePaymentMatcher.php, BusinessDocumentPaymentMatcher.php, focused PHP test | Atomic exact matching, denied/ambiguous/duplicate/rollback and concurrency cases |
| Debit evidence and API | backend | TatraBankEmailParser.php, BankTransactionController.php, strict-debit unit test | No defaulted debit evidence, no partial VS, recorded/display direction split |
| Device sync and expense match | coordinator | Bank composable, CompanyPayments.vue, local matcher/bankCrud, focused tests | Correct company, stale response, exact debit matching, atomic failure |
| Shared state and guides | coordinator | This note, state/roadmap/decisions/workspace, five-language guides | Verified outcomes and operational limitations |
| Independent QA | qa | None; synthetic temporary probes only | Before/after failures, regression and final manifest |
| Independent security | security | None | Separate review of trust, company isolation, failure and final manifest |

Both independent reviewers accepted the conservative matching design before its
implementation. No specialist can commit, push, merge, publish or deploy.

## Production diagnosis still required

Privately inspect the actual deployed revision/Compose variant, queue status,
pending/failed webhooks counts, effective inbound-enabled boolean and latest
persisted email-movement date/count for the intended company. Correlate today's
Delivered event with sanitized job outcome. Never dump raw job payloads, failed
exceptions, recipient tokens, bank messages or credentials. Do not replay the
message or retry all failed jobs as diagnosis. Missing historical mail cannot be
recovered from expired Mailgun logs; already-persisted rows can be synchronized.

The owner confirms standalone Compose. A direct Docker listing shows the queue
container Up 22 minutes and healthy. The initial Compose `ps` ran without its
expected environment/project and returned no matching containers, so it was not
evidence of a missing worker. Pending/failed webhooks counts remain requested.

## Validation

Final source manifests, independent dispositions and completed check results are
recorded below when implementation is frozen. Earlier counts belong only to
their stated scope; they do not verify later matching changes or production.

### Final frozen implementation

Runtime source manifest (11 paths):
`07d8edd3af8810cbe369899dfe23c413c2b25761e1438746ef33364543198b29`.
Source and test manifest (21 paths):
`2b843d64a6cfc2ad7c63294360cd99a38657a1bff5f2586ab7570f1d73aab4ab`.
Both independent reviewers reconciled these exact final manifests. Earlier hashes
are superseded. The final annotation describes the fresh DB-query helper as
`@phpstan-impure`; removing only that PHPDoc reproduces the tested runtime body.
No baseline, ignored error count or static-analysis guard changes.

- Main: full **1,335 frontend tests across 157 files**; lint **48 existing warnings**
  within the unchanged52 ratchet; typecheck **112 existing diagnostics**, none new;
  locale parity, all10guides, production build and whitespace pass. Existing bundle
  warnings and8stale typecheck baseline entries remain unchanged.
- Author: isolated SQLite **225 tests / 740 assertions** and Pint9PHPfiles pass.
  Final PHPDoc also passes Pint. Existing PHPUnit discovery deprecation remains.
- Independent QA: **144 frontend tests / 9 files**, actual PostgreSQL16.11
  **225 cases / 730 assertions** with5expectedSQLite-only malformed-storage skips,
  and forced two-connection contention **1 test / 16 assertions** pass. Only the
  owned network-isolated memory-backed PG container was stopped/auto-removed.
- Independent security: **152 frontend cases / 9 files**, **206 PHP / 671 assertions**,
  then **64 PHP / 189 assertions** for the final raw-DB variant pass. Final PHPDoc
  is independently proven comment-only. No unresolved findings. SEC-BANK-UI-1 and
  the synthetic same-instance reconciliation race were reproduced and fixed.
- The unguarded mounted bank page fails6of9deferred context probes; the guarded
  page passes9of9. Real Evolu+real owner proxy tests verify one synchronous mutation
  batch for the actual matcher and no queued writes after validation failure.
  This does not prove durable fake-worker commits or distributed uniqueness.
- All three OpenAPI documents and the existing validator test pass. All453runtime
  inventory records and every other selected source hash are unchanged; exactly
  the final parser hash is refreshed. The bank-list endpoint remains outside the
  curated initial OpenAPI scope; its additive recorded-direction field has an
  actual authenticated owner/denied-owner API regression.

Full-project PHPStan completes against the original level5configuration and
unchanged baseline/unmatched-ignore guard with **zero new findings**. Its sole
remaining error is the known missing local optional `Dedoc\Scramble\Scramble`
class in unchanged `DocumentationExporter.php:18`. Full generator verification
is unavailable for the same dependency reason; no installation, stub, ignore or
baseline weakening was used. CI on a future published branch is not inferred.
The broader real-MIME subprocess suite is not claimed passed: this minimal PHP
image has Node20and lacks the locked verifier dependencies. Credit/SEPA source
regressions pass; current-bank interoperability is still an operational check.

### Production observation and handoff

Owner-supplied metadata reports intake enabled,2email transactions created today,
latest creation `2026-11-03 17:14:08`, a healthy standalone worker and zero waiting,
delayed or reserved webhooks. The supplied failed-job listing contains June/July
entries. No IDs or payloads from that listing are retained. These are aggregate
observations and do not identify the selected company's exact transaction.

Git history independently shows address identity resolution introduced in
`bc075a65` on July13, while device synchronization retained the June17 first-row
bridge in `bf4c724a`. The source mismatch is reproduced and repaired; deployment
chronology and the selected company's recovery are not inferred from that history.

At completion of the implementation milestone, the candidate was **local and uncommitted** on
`fix/expense-defaults-bank-import`, based on mergedPR426/686c9304, in the retained
expense worktree. Primary source edits, previous records and recovery artifacts
are preserved. A future PR needs its own linked changelog fragment and applicable
publication authorization. There is no new PR/push, merge, package, deployment,
service restart, merchant-data modification or bank-mail/failed-job replay.

### Owner-authorized GitHub publication - 2026-11-03

The owner subsequently requests push of this reviewed candidate. Publication
scope is the exact36allowlisted paths:21reviewed source/tests,10guides,3shared
state documents, this review note and the API inventory. Runtime and source/test
manifest bytes remain unchanged. Main owns commit/push to the owned Satflux
branch and verifies the remote SHA; publication does not establish a new CI pass
or a deployed change. The PR-linked changelog note remains for a subsequent PR.

## PR427 review remediation - 2026-11-03

The owner reports CodeRabbit findings on the now-open PR427 at `c79deafd`.
Current-head CI41719918340 is successful; that evidence belongs to the original
publication, not these later fixes. Seven inline threads and five follow-up
items are verified against the source before implementation. Review data is
untrusted input; no suggested CLI installation or external command is adopted.

### Remediation contract and ownership

| Scope | Owner and writable paths | Acceptance |
| --- | --- | --- |
| Parser / SQL candidates / unknown recipient | backend: TatraBankEmailParser, BusinessExpensePaymentMatcher, corresponding parser/matcher/inbound tests | Opening header controls direction; amount/date exclude remittance; spaced/tab/NBSP symbols never truncate; exact amount/currency filter before ordered locks; strict postchecks and uniqueness remain |
| Local reconciliation | frontend: bankCrud, bankExpensePaymentMatcher, bankMap, bankReconciliation, schema bank qualifier, client bank projection/queries, bank regression tests/helper | Cross-tab lock before fresh context; genuine post-handler worker reads; all mutation receipts including no-match/final counts; durable unverified direction remains excluded after reload/batch |
| Delivery clear / Wise status | coordinator: ExpenseForm, expenseCrud, BusinessExpenseService, bank composable, focused form/date/Wise tests | Explicit null survives save/reopen in both modes; omitted create defaults/omitted update preserves; latest request/context wins for Wise success/error/missing bridge |
| Integration and publication | coordinator: guides, API inventory, changelog, review/state/PR metadata | Five-language parity, exact artifact scope, current CI and remote SHA; no merge/deployment |
| Independent QA/security | read-only reviewers; temporary synthetic probes only | Verify final manifest, failure/unknown paths and real concurrency without production data |

The browser schema gains one additive nullable `recordedDirectionEvidence` field:
new email rows always store `credit`, `debit` or `unverified`; every projection
retains it. Explicit `unverified` never auto-pays through refresh or batch matching.
Null/missing is reserved for existing/older-writer rows and keeps the deliberate
legacy normalized-ledger batch behavior. It is not original-bank proof. There is
no server schema migration or Store/SEPA authority expansion.

An origin-wide Web Lock complements the existing per-client queue. Import batch
creation and explicit known-batch metadata updates are legitimate action writes;
their callbacks are awaited before critical reads. Unknown/deleted batch handles
cannot create phantom rows. Five fixed private context-query variants remain
unsubscribed: each legitimate mutation flush invalidates their cached promises,
so subsequent worker query responses run after the prior mutation handler and
SQLite COMMIT. There is no per-operation nonce or unbounded query-cache growth.

Installed Evolu posts its onComplete message before the SQLite transaction
returns. That callback alone is not commit proof. Matching keeps the bank and
expense writes synchronous in one validated owner batch, then verifies their
stored state through the private worker queries before changing context/counts.
No-match imports and final batch counts receive the same completion checks.

Timeout, worker error or missing proof is UNKNOWN. An entered operation rejects
to its public caller and permanently quarantines its internal gate/Web Lock until
the tab is reloaded/closed. A late pre-COMMIT callback cannot release quarantine.
Queued operations have bounded pre-entry cancellation and cannot mutate after
cancellation. There is no automatic replay or uncertain rollback claim. Missing
or rejected Web Locks preserve basic import while disabling automatic expense
matching. Other devices remain outside the same-browser lock guarantee.

Explicit delivery null now means clear; omission defaults only on create and
preserves storage on update. This replaces the earlier documented blank-date
save fallback. User guides and the single PR427 changelog fragment follow the
new final behavior. Final review manifests/check results are appended after freeze.


### Review disposition and compatibility

| Review item | Final behavior and regression evidence |
| --- | --- |
| 4460664772 | Only the anchored bank opening controls debit/credit direction; quoted remittance words cannot suppress a valid classic credit. |
| 4460664781 | Compact amount/date qualification ends before remittance/counterparty fields; a date found only in remittance cannot qualify a debit. |
| 4460664792 | Space, tab, NBSP and dot-plus-numeric VS continuations reject instead of truncating. A complete bank date after sentence punctuation remains supported. |
| 4460664802 | A fixed origin-wide Web Lock is acquired before critical context reads; two actual clients with warmed caches match one expense only once. |
| 4460664809 | Pre-COMMIT callbacks are followed by private worker-backed reads; real SQLite COMMIT failure rejects and keeps the gate quarantined. |
| 4460664827 | The additive stored direction qualifier survives refresh and explicit batch matching; new unverified/credit rows cannot pay an expense. |
| 4460664845 | Explicit cleared delivery dates remain null through server/local save, reopen and table display; omitted updates keep existing values. |
| Wise local/server follow-ups | Request generations reject stale success/error/A-B-A responses and clear status on company changes. A shared bridge-lookup generation prevents an older bank refresh from overwriting current Wise availability. |
| SQL-lock follow-up | Exact canonical amount and trimmed uppercase currency narrow candidates before ordered locks. Independent two-connection tests prove unrelated amounts/currencies do not wait behind the match. |
| Real-matcher failure follow-up | The actual second expense write fails Evolu validation after successful prevalidation: no worker financial mutation is sent and context/counts remain unchanged. |
| Unknown-token follow-up | A well-formed but absent recipient token reaches the real inbound service and fails lookup; malformed addresses remain separately rejected. |

Independent security review additionally found a late-callback quarantine release
and dot-plus-space VS truncation. Both are fixed with regressions. No unresolved
product/security tradeoff is introduced by these changes.

The same-browser lock guarantee requires updated cooperating clients. Reload old
tabs when rolling out the new frontend; older writers and other devices cannot
participate in this lock or supply the new qualifier. Existing null-qualified rows
keep the documented explicit legacy batch behavior. No distributed uniqueness or
original-message proof is claimed. A worker outcome that remains unknown requires
closing/reloading the owning tab before another fresh matching attempt; there is
no automatic replay of that operation.

Independent temporary actual-worker probes use two real Evolu client instances,
the installed Db/CRDT/SQLite transaction code and native in-memory SQLite. Realm,
lock and broadcast adapters are synthetic; these are not full browser/OPFS E2E
claims. They explicitly exercise warmed subscribed/private caches, fresh worker
queries, real validation rejection, COMMIT failure and missing/late receipts.


### Final independent review and checks

Both independent reviewers accept the full PR artifact relative to merged
`686c9304`. Runtime17 manifest is
`78afb3fdf73ad9f6b30f537684f711ef2914ece59c46f21845096a4aa0e04eb0`;
source/test31 manifest is
`d52b3e003a8466d669a64ed092af03a8a616843f36dbec024a1bc8972610e686`.
Each manifest hashes lexically sorted UTF-8 lines containing the file SHA256,
two spaces, repository-relative path and LF. No unresolved QA/security findings
remain. Reviewer probes did not write repository files or touch production.

- Main frontend: 1,363 cases / 158 files pass; final Wise shared-bridge scope
  separately passes19 after its last change. Full lint has48 existing warnings
  and no errors; final typecheck has112 baseline findings and no new fingerprint.
- Main disposable SQLite:237 cases /958 assertions pass, one existing PHPUnit
  deprecation. Pint passes all8 modified PHP files.
- Independent QA PostgreSQL:251 cases /805 assertions pass with5 expected
  SQLite-only malformed-storage skips and one existing deprecation; the three
  new delivery-null API cases are included. The older full expense fixture's
  nested Read Committed snapshot limitation is not reported as a new pass.
- Independent real PostgreSQL contention/narrow-lock cases:3 /42 pass. The old
  matcher overlay blocks unrelated amounts and fails the negative control.
- QA focused frontend126 and final bridge19 pass. Security independently passes
  frontend179, final bridge19, backend/date166 /736 and final parser84 /278.
  These corpora overlap and are not added together.
- Both reviewers independently pass12 temporary actual Evolu/native SQLite
  probes, including two-client warm caches and actual COMMIT failure. Their
  adapter boundary is described above; they do not establish OPFS/browser E2E.
- Final build, five-locale parity, three OpenAPI documents and validator test
  pass. All453 route inventory records and other source hashes are unchanged;
  only the parser hash is refreshed. The one PR427 changelog fragment validates.
- Full local PHPStan completes with only the known optional missing Scramble
  class in unchanged DocumentationExporter.php:18. There is no new diagnostic,
  installation, ignore, baseline or warning-budget change. Fresh locked CI must
  verify the published commit; old c79deafd CI is not reused as that evidence.

The QA-owned network-isolated temporary PostgreSQL container and tmpfs were
removed after review. Existing primary edits and all recovery artifacts remain.
Main publication is limited to this reviewed branch. No merge, deployment,
merchant-data change, historical job retry or bank-message replay is performed.


## PR427 second review remediation - 2026-11-03

The owner asks for additional CodeRabbit findings. The completed incremental
review of `06c837b3` adds four inline findings and three follow-ups. That commit's
CI41729050967 passed all four jobs; it does not validate the changes below.
Graphify remains unavailable because the local graph is absent and no graph tool
is exposed; no graph rebuild or tool installation is performed.

### Scoped contract and disposition

| Finding | Owner / bounded files | Required behavior |
| --- | --- | --- |
| 4461201972, compact date suffix | backend / parser and two parser test files | Complete compact debit date token with/without VS; malformed time cannot fall back to a date; malformed plus valid date remains ambiguous and rejects |
| Follow-up18bcf517, compact credit amount | backend / same parser/tests | Amount and currency come from bank-authored prefix; remittance-only amounts cannot qualify a credit |
| 4461201992, long derived VS | coordinator / ExpenseForm and mounted form tests, five import guides | At most32 derived digits including leading zeros; overlong automatic value is blank, number retained, shortened number resumes default, manual overrides stay intact |
| 4461202003, Spanish direction wording | coordinator / Spanish bank guide | Say recorded movement direction (cargo/abono), avoiding the payment-address ambiguity |
| 4461202025, batch error visibility | coordinator / CompanyPayments, mounted page tests, five locales/guides | Catch rejected batch matching; localized busy/unknown/generic errors, no silent failure or automatic retry; keep success refresh |
| Follow-up170a9eee, import reads | frontend / bankCrud and bank regression tests | Prevalidate/dedupe prepared rows, one synchronous row batch and one post-handler private-context read, verify each actual id/company/hash before counts/matching |
| Follow-up82f95bd4, lock regression | frontend / concurrency test | Assert the fixed literal lock name and exclusive options so a changed name cannot pass the test |
| Independent acceptance | read-only QA and security | Verify exact frozen bytes, rejection/unknown invariants, real worker boundaries and UI/parser behavior using only synthetic disposable probes |

Only validated rows reserve hashes in the current-import set. Invalid rows remain
skipped individually; a later valid row with the same hash is eligible. An empty
prepared group never waits for a nonexistent row receipt. Every actual insert is
queued synchronously without an await; a real Result-false cancels that entire
Evolu microtask batch, aborts the import explicitly and publishes no row counts or
expense matches. Initial import metadata can remain with zero counts. There is
no automatic retry. Thrown/unknown worker outcomes retain the existing permanent
quarantine. The final legitimate metadata receipt and private fresh read remain.

The five fixed private query identities, origin lock, owner scoping, direction
qualifier, financial matching rules and previous mixed-client/device limitations
are unchanged. A Map verifies stored row identities without a quadratic scan.
The actual-Evolu mixed five-input fixture imports two rows and skips three with
three context reads (15 query loads, five keys), including initial/final metadata
barriers. This measures query work; no production backlog timing is inferred.

UI messages distinguish a busy operation, an unknown result requiring tab reload,
a known rejected import and a generic matching failure. All five locales and
matching guides carry the final behavior. Known internal reconciliation codes are
mapped to localized messages; no new payment payload or raw worker error is shown.
Final manifests, independent reports and completed gates follow after review.


### Second-round frozen artifact and independent acceptance

Both independent reviewers verify every listed file and accept runtime22 manifest
`4b236b10f2ff2bd403a8287599f0594707a573ff495ab6cfd4e678357c2a7eb1`
and source/test36 manifest
`b33ad44a7e63e15235aded39654cdeb025d9c0533d3701febfdc898f35b19975`.
The manifest format is unchanged. No unresolved QA/security finding remains.
Only four previous runtime paths and five locale files change this round; the
prior browser schema, lock/query module and SQL matcher remain byte-identical.
The previous Compose delta is unchanged and outside this follow-up.

- Main full frontend:1,382 cases /158 files pass on frozen bytes. Scoped author
  bank92 and coordinator mounted UI39 pass; full typecheck retains112 existing
  findings with no new fingerprint, full lint has48 existing warnings/no errors.
- Backend author disposable SQLite262 /1,018 and Pint8 pass. Independent QA
  SQLite305 /1,160 and security177 /539 pass with one inherited deprecation.
  These corpora overlap. No PostgreSQL target is created for this round.
- Independent QA frontend131 /5 files and security bank92 plus UI39 pass.
- Each reviewer passes15 actual Evolu/native SQLite probes, including three new
  multirow/known-abort cases. The installed worker/transaction code is real;
  asynchronous realm, broadcast and lock adapters remain synthetic. No browser,
  OPFS or cross-device uniqueness claim is inferred.
- Production build, five-locale parity, all three OpenAPI documents, validator
  test and updated PR427 fragment pass. Structural inventory comparison proves
  the parser hash is its only change; all453 route records remain unchanged.
- Full local PHPStan has only the inherited optional missing Scramble class in
  unchanged DocumentationExporter.php:18. No dependency, ignore, baseline,
  warning budget or permission setting changes. New locked-dependency CI must
  verify the new published head; earlier06c837b3 success remains historical.

Main publishes only the32 reviewed follow-up paths, verifies committed manifests
and remote SHA, updates PR evidence and resolves the four addressed threads.
The primary checkout and previous recovery artifacts remain preserved. No real
bank message, production data, job retry, merge or deployment is used/performed.


## PR427 third review remediation - 2026-11-04

A later completed CodeRabbit review of3f47a24b reports three inline findings and
one outside-diff follow-up. The initial rate-limited check and green
CI41733930901 are historical observations; they are not new-source validation.
Graphify graph/tool remain unavailable; no installation or rebuild occurs.

### Accepted architecture and ownership

| Finding | Owner / scope | Acceptance contract |
| --- | --- | --- |
| 4461780133 | backend / Tatra parser and two parser test files | Preserve layout whitespace in HTML-only bodies without splitting inline numeric tokens or relaxing date/symbol/remittance qualification |
| 4461780146 | frontend / expense CRUD/import/bulk, bank compound creation/matcher, company delete/merge, snapshot and sharing maintenance | Same origin lock spans fresh preconditions, actual critical writes and post-handler proof; every production matching-critical expense writer cooperates |
| 4461780158 | coordinator / CompanyPayments, local bank composable and page tests | Match failure and refresh failure have separate scopes/messages; a saved successful match is not reported failed because refresh rejects |
| Outside-diff218c8a3e | coordinator / batch map, projection and UI/tests | Local batch action availability derives from actual same-company persisted unmatched rows, including verified counter0 after an interrupted import; never move counters before identity proof |
| Review and publication | read-only QA/security, coordinator shared docs/metadata/Git | Verify exact artifacts, real worker ordering/unknown behavior and current CI; preserve primary work; no merge/deployment |

The expense writer inventory includes stable-ID inbox upserts, spreadsheet and
bulk actions, bank expense creation, company delete/merge, snapshot restoration,
share migration/rekey/recovery and ordinary form/detail edits. Note saving uses
only its intended note field. Public writer results become awaited; callers retain
context-generation checks before navigation, attachments or success feedback.

Installed Evolu exposes no public forced refresh for warmed query keys. A small
inert `_expenseReconciliation` local-only coordination table with one fixed opaque
row supplies a genuine entry marker mutation before private worker reads. It has
no company/merchant/financial fields, stays AppOwner-only and is excluded from
explicit backup/share/relay data allowlists. Installed Evolu excludes underscore
local-only changes from CRDT sync. This is a deliberate additive browser-schema
exception needed for fresh writer preconditions, with no server migration or
payment authority expansion.

The five existing private financial query identities remain unchanged. Bounded
additional private, unsubscribed variants support only the needed maintenance/
share/tombstone decisions, without nonce keys or unbounded cache growth. Ordinary
writes must not load unrelated attachment/snapshot/stock datasets. Owner/share,
number allocation, stable-ID existence and target liveness decisions use fresh
state inside the gate. Terminal receipts and worker reads prove the completed
handler before return/release. TypeScript brands alone are insufficient: opaque
already-locked capabilities require private runtime validity and revocation.
Compound create/link and bulk groups preserve synchronous mutation batching;
known validation rejection remains distinct from Unknown and no nonexistent
callback is awaited. Unknown retains permanent quarantine and never auto-retries.

Expense writers require an origin lock and fail before any marker/financial
mutation if it is unavailable. The existing basic bank-import fallback remains,
with automatic expense payment disabled. Updated cooperating clients in the same
browser are the guarantee boundary; old tabs and other devices remain outside it.
Reload older tabs at rollout. The additive local-only marker needs no backup,
relay or server migration; the existing encrypted expense data remains intact.

Ordering is intentional: an edit to20 committed before a debit19 prevents that
match. A valid debit19 committed first may be followed by an explicit authorized
edit to20 that keeps the existing paid status. Preserve that editable-paid policy
and original bank evidence; do not invent unpayment, unlinking, compensation or
paid-expense immutability. Matching postproof additionally checks the original
critical amount/currency/hint snapshot as defense.

Five locales/guides describe visible busy/unsupported/unknown recovery and saved
match refresh failure. Server batch behavior remains unchanged; the derived local
flag is presentation/recovery information, not payment authority. Final source
manifests, independent acceptance and completed checks follow after freeze.


The inherited unlocked basic-import fallback retains its previous owner-registry
routing; the new fresh-owner proof applies to the locked cooperating scopes only.
The fallback cannot auto-pay an expense and does not acquire a new privacy/owner
guarantee in this change. No silent change to that previous fallback is claimed.

Independent review required bank Ignore/manual-match/unmatch eligibility writers
to use the same gate and fresh state. Their callers await completion and separate
saved-action refresh failures from mutation errors. Existing unlink-without-unpay
semantics remain. Fresh parent ownership also routes new bank-match/attachment
children, avoiding a stale-registry AppOwner fork. Missing/unready/ambiguous owner
state fails before a financial mutation, without installing keys or registering
new owners. A known missing-company bank-import preflight occurs outside the
mutation receipt catcher, so a proven no-financial-write refusal is not incorrectly
classified as Unknown.

Review-discovered fixes include private runtime lease revocation, bounded owner
initialization, cancelled-note rejection, pre-gate rejection of non-email debit
matching, selective auxiliary reads, owner/parent routing, and correct known-abort
classification. Expected in-memory patches from an aborted synchronous group are
discarded; previously committed groups remain. Existing known bank row rejection
keeps its localized error, and a rejected expense pair leaves the imported bank row
unmatched instead of undoing earlier valid matches. There is no financial rollback.

One unchanged drawer binding exposed a fragile existing TypeScript fingerprint
after the schema expansion: an object class was passed to an icon's string class
prop. The equivalent conditional string preserves the exact CSS behavior and
removes that diagnostic; no baseline, warning budget or type assertion is changed.
Current full typecheck tolerates111 existing findings with no new fingerprint.


### Third-round artifact and independent evidence

Final runtime41 manifest is
`a9478719b0e67ac46526bbd8e3db03657c935b656944ac2a37401aac02d76ee7`;
source/test65 manifest is
`5f74ae7b795c4c3786cf4f3123855e64aa186a9a9e725a8535d8da244dd471c1`.
The manifest format remains lexically sorted repository-relative SHA256 lines.

Main full frontend passes1,415 cases /160 files after fixing three test-adapter
mismatches without weakening financial assertions. The final additional pure
cross-owner merge preflight is separately verified by3 cases and the affected35
cases, with zero merchant prefix mutations for invalid groups and a same-owner
positive. Current full typecheck has111 existing findings/no new fingerprint;
full lint48known and final scoped lint0 preserve budgets/baselines. The build,
five locales, all3 OpenAPI documents, validator and existing PR fragment pass.
The API inventory changes only the parser hash, keeping all453 route records.
Author HTML/parser SQLite280/1145 and Pint8 pass. Local full PHPStan retains only
the known optional missing Scramble class in the unchanged exporter.

Independent QA passes311 repository cases /21 files, native40 /5 and isolated
SQLite323 /1287 (one inherited PHPUnit deprecation). Independent security passes
104 repository cases, SQLite180 /641 and50 distinct native/security cases, with
the repaired classification case rerun and10 boundary cases rechecked. These
corpora overlap. Both reviewers verified the earlier40 runtime files; the final
company-merge preflight and renewed manifests receive separate bounded review.
No PostgreSQL target or infrastructure is created this round.

Native tests use two installed Evolu clients and actual DbWorker/CRDT/SQLite code,
including a genuine worker-thread COMMIT pause with both clients responsive.
Realm/tab/lock adapters and storage remain synthetic; this does not establish
browser/OPFS or cross-device uniqueness. Cases prove edit-v-match order, stale
caches, owner routing, revocation, known abort recovery, unknown quarantine,
stable IDs/numbering, Ignore, compound bank creation, deletion and restoration.
Known failures retain earlier valid matches and permit explicit later recovery.

The final company-merge delta validates EVERY resolved duplicate group before
any mutation. No-expense and invalid-later groups cannot leave document/contact
prefix changes. Main publishes only the reviewed allowlist after both renewed
acceptances, verifies committed blobs/remote SHA and requires new locked CI;
CI41733930901 belongs to3f47a24b. No merge, deployment or real bank-message replay.
