# Private bank MIME capture: independent design QA

Reviewer: /root/bank_mime_diagnostic_qa. Date: 2026-11-01.
Final disposition: ACCEPT amended design at frozen proposal hash ba1177a5867ddae9773499091ffd540cdd51be921cfe85b3ccb28d19b669b905. QA-D1 through QA-D4 are resolved at design-contract level. This is document review only, not implementation, activation, deployment, privacy/security or runtime approval.

## Reviewed repository, revision and scope

Repository webiumsk/satflux. Current source worktree fix/bank-mail-single-route is clean at 037a6fa7e739597b6c54afb4b02d37a277dd3cc5. Primary checkout fix/sepa-confirmation-isolation remains at 885168f2b3ebaaf70b6724eebe541084c969aa00 with unrelated dirty work, preserved.

Reviewed primary-checkout proposal: docs/development/BANK_MIME_CAPTURE_PROPOSAL.md. Initial SHA-256 efcb2fcd5c24b56958903bd91ad1db44168a796678556afeb19e9444677025af; final amended SHA-256 ba1177a5867ddae9773499091ffd540cdd51be921cfe85b3ccb28d19b669b905, independently recomputed and matched. Relevant project state and integration-testing/architecture-review instructions read. Main reports primary Graphify graph stale without current MIME edges; no code exploration or graph update was needed for this bounded document review.

Only /tmp/bank-mime-capture-design-qa.md written. No repository/Git/production changes, commands against real cache/database, implementation, installs, fund transfers or runtime tests.

## Amended contract: final QA acceptance

Read-only numbered re-read and sha256sum verify the exact frozen amended proposal. All four earlier corrections are closed for design review:

| ID | Closure evidence in amended proposal |
|---|---|
| QA-D1 | Lines24-29 explicit operator arming creates one new encrypted ARMED generation, cannot replace unexpired slot or accept caller generation. Lines36-47 only matching unexpired existing ARMED can atomically become CAPTURED at exact expiry; handler never creates/re-arms missing state. Eviction/state loss fails closed. New explicit operator activation is required for a new generation. |
| QA-D2 | Lines24-26 anchor UTC deadline to at most two hours after activation; lines30-33 explicitly define processing order, including already queued mail. Lines40-47 encrypt complete owner/generation/deadline/recipient/raw envelope, retain256KiB raw cap and1MiB complete serialized encrypted cap; restart/read/repeat/disable-reenable cannot extend expiry. Lines66,71-73 restrict access to unexpired record and fixed expired status. |
| QA-D3 | Lines51-63 fix at most five signatures, tri-state facts, unavailable/not-evaluated distinct from evaluated false, unknown fallback, no raw diagnostic identifiers and separate original/current summaries. Existing policy is expressly preserved at lines110-111. |
| QA-D4 | Lines94-99 require every worker consuming the relevant webhook queue compatible before activation, with capture-disabled rollback and no unrelated cache/financial mutation. |

The future QA row at line108 explicitly requires real disposable Redis concurrency, eviction/state-loss, numerical size and regression evidence. No executable behavior is inferred from these statements. Validate all criteria below after any separately authorized implementation. In header-coverage fixtures, absent optional MIME headers must retain their existing applicability rules; reporting six fixed names must not strengthen or relax the existing trust predicate.

No outstanding design QA blocker remains for presenting this proposal for owner scope approval. Actual Redis atomicity/expiry, encryption, stdout/stderr privacy, worker compatibility and absence of financial side effects remain future implementation/review evidence, not current passes. No new transfer is a prerequisite.

## Verified design strengths

The proposal explicitly retains rejected/review payment behavior, default-off operation, one server-selected local Store, no Company/public API capture, encrypted storage, no arbitrary output, an absolute short deadline, no raw export, no financial replay and no provider/billing/package change. It correctly states that invalid_signature is broader than a cryptographic signature failure, current actual MIME remains unavailable, new capture cannot recover past consumed messages, and no new transfer is required. Redis backup/persistence residuals are acknowledged and kept separate from live TTL.

## Initial review findings, now resolved by amended contract

### QA-D1 - Medium: atomic payload NX does not alone guarantee one capture

Evidence: proposal lines31-36 promise one record total, no overwrite/TTL extension; lines54-57 say disable after capture. Payload NX only prevents replacement while that key exists. If the payload is evicted, deleted for rollback or lost and configuration remains enabled before the deadline, a later message can create a second payload. Clearing a key and restarting a worker can also silently re-arm unless activation state is explicit.

Required contract: one server-owned activation has explicit armed/consumed/disabled/expired behavior. Consumed must never return to armed from payload absence, worker restart or reading configuration. Unknown/missing activation state fails closed for capture. Payload deletion must not re-arm. Re-arm requires an explicit new activation, not ordinary retry/refresh. If auxiliary activation state is used, permit only fixed non-message control data in addition to the one encrypted payload; key loss must not initialize it automatically. Exact mechanism belongs to implementation design/security review.

Regression criteria: two simultaneous workers against a real disposable Redis yield one successful capture; repeats and restarts do not change bytes/deadline; delete/evict the payload within the window and submit a second candidate, expecting no new capture; loss of activation state disables capture. Cache failure must preserve original review outcome and queue classification.

### QA-D2 - Medium: make lifetime, encryption and size bounds measurable

Evidence: lines24-35 specify at most two hours and bounded encrypted payload, without a fixed activation-time anchor or serialized byte ceiling; grammatical scope of authenticated encryption could be read as covering only original MIME.

Required contract: immutable activation timestamp and absolute UTC deadline; 0 < deadline - activation <=7200seconds; reject invalid/past/oversized windows. Define whether first future means first invalid-signature decision processed after activation or newly received mail after activation. Use server-owned timestamps, never sender Date. A worker restart/config reload, duplicate, command read or retry cannot extend the same activation. No capture at or after deadline, and no positive-TTL rounding may outlive it. Command refuses analysis after deadline even if a stale Redis value remains.

The complete retained sensitive envelope, including original MIME and recipient, must be authenticated-encrypted. Define a numeric maximum for serialized encrypted record and bounded summary/signature count before implementation, while retaining the256KiB raw maximum. Test raw bytes exactly equal the received bytes after decrypt; no re-encoding or trimming. Wrong-key/tampered/oversized/unknown-schema payloads produce fixed sanitized statuses.

### QA-D3 - Medium: diagnostic unknown must not be reported as cryptographic failure

Evidence: lines40-46 allow booleans per bounded signature and preserve original summary, but absent/unavailable fields and recheck mismatch are unspecified.

Required contract: cap signatures explicitly (consistent with existing verifier maximum), fixed per-signature index/status, and a fixed not_evaluated/unavailable status when a predicate cannot be computed. False must not imply a failed cryptographic test that never ran. Preserve separately the original verification-time summary and later recheck summary; no replacing the historical result with a later expired/key-rotated result. CLI must not present eligibility/alignment/header/time rejection as proof that body/signature bytes are cryptographically invalid.

Test criteria: valid crypto missing a required signed header, valid crypto with failed time/alignment, actual tamper, missing/rotated DNS key and temporary DNS failure produce distinct permitted outcomes without raw library comments. Node execution retains existing bank-DNS allowlist, process/output/signature/time limits and no arbitrary resolver input. Capture and diagnostic branches must not mutate production trust predicates.

### QA-D4 - Low: activation compatibility covers every possible consumer

Evidence: lines75-80 require all selected diagnostic workers compatible. An older worker sharing the same queue can consume and permanently reject the intended first message without capture, even when designated diagnostic workers are upgraded.

Required criterion: before activation, every worker that can consume this Store's relevant webhook job must run compatible source/config, or an explicitly verified routing boundary must ensure exclusive compatible consumption. Preserve ordinary verification if incompatible/disabled; never infer successful capture from healthy containers or empty queues. All workers must agree on activation identity/deadline/keyspace and encryption key compatibility. Rollback disables capture before or with code rollback, without extending/decrypting/replaying retained data.

## Proposed validation plan after scope approval

All are planned, not executed. Freeze exact file ownership and verified disposable targets first.

| Contract | Meaningful evidence |
|---|---|
| Default off/config | No capture/cache access in default-off path; malformed, nonexistent or Company owner configuration rejected; no caller-supplied owner override |
| Scope | Other Store/Company and every reason/status except terminal invalid_signature do not consume the slot; valid payments unchanged |
| Atomicity | Real isolated Redis multi-process race, one winner, exact payload retained; fake cache alone is insufficient |
| Lifetime | Boundary before/at/after deadline; delayed processing; repeat reads/duplicates/restart; no TTL extension; expired record analysis denied |
| One shot/recovery | Payload eviction/deletion, activation loss, Redis outage/timeouts, disabled/reenabled worker and duplicate delivery cannot re-arm |
| Privacy/limits | Maximum raw/record size, Unicode/exact bytes, encryption/tamper/key/schema errors, stdout/stderr/log/audit/exception/argv scan with synthetic markers |
| CLI semantics | Original and recheck summaries separately bounded; unavailable is not false; malformed/expired/unavailable fixed statuses; repeated command read has no TTL side effect |
| Financial isolation | Spy/fail on job dispatch, bank importer and BTCPay report calls; no transaction/payment/audit settlement mutation; existing positive/rejection/retry suites pass |
| Compatibility | New PHP/Node protocol on supported Node22 and existing local runtime; old/mixed workers scenario; default-off rollback; no provider package change |

## Assumptions, dependencies and blockers

Design assumes the existing Redis target supports a genuinely atomic operation, shared keyspace and expiration semantics, plus compatible authenticated encryption/key handling. None was inspected or runtime-qualified in this review. Existing live TTL cannot promise deletion from backups/persistence; explicit residual-retention acceptance remains a proposed activation condition, not evidence of permission already granted. Independent security review remains separate.

Reported newer audit11:44:51UTC/invalid_signature and owner deployment are evidence supplied to this task; no actual MIME/root cause/deployed SHA independently established here. A normal message may never arrive during the window, or untrusted mail may consume its single slot. Do not require another real transfer or claim the capture guarantees current-bank qualification. Original raw MIME must remain untrusted during any future diagnostic processing.

Exact read-only checks include final `sha256sum docs/development/BANK_MIME_CAPTURE_PROPOSAL.md` and `nl -ba docs/development/BANK_MIME_CAPTURE_PROPOSAL.md`; initial git status --short, git branch --show-current, git rev-parse HEAD, git remote -v in source worktree; proposal/state reads, numbered proposal read and SHA-256. No tests run because only the design document was reviewed. Main owns proposal edits, coordination and any owner approval request.
