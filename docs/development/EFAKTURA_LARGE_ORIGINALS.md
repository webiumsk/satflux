# Larger received eFaktura originals

## Late-sync deletion intent accepted locally - 2026-11-16

The six original review findings are resolved on `033fd831`, whose five CI jobs
pass. A [seventh finding](https://github.com/webiumsk/satflux/pull/449#discussion_r4580967386)
arrives during that verification and the owner explicitly requests its fix.
It is valid: without canonical tombstones, older source/chunk messages can later
materialize live unbound rows. Complete backup/restore preflight can then fail;
legacy v2 creation/parsing alone does not detect every orphan-chunk case.

The correction supersedes the earlier only-existing-row defense. Fresh current
parent/attachment/owner authorization and strict canonical reference parsing
derive the source and at most 40 chunk IDs from the company/receipt/expense/
attachment/digest namespace. All present structural/live-row checks remain before
archive writes. A single bounded atomic batch records explicit-owner deletion
intent for every derived target, including absent rows. It invents no payload
bytes or financial metadata and never changes foreign-owner copies. Readback
requires exactly one persisted tombstone per canonical target. Malformed/foreign
bindings, failed-batch rollback and retry-resurrection guards remain intact.

Installed SDK behavior is independently verified: update emits a deletion
without a row lookup; CRDT cell materialization inserts the missing owner/id/cell
tombstone and checks history timestamps before accepting incoming values. The
tests capture real SDK history before deletion, withhold selected archive rows
and history while retaining the clock, then replay original older cells through
the installed quarantine applicator. They do not use a newer upsert as a proxy.

Final 44-file source/test/locale freeze:
`2699055fc1a21886a68fd65d7790d35cf2f6698a4dc48ab05716f680929a9688`
(SHA-256 of the compact sorted JSON hash map). Only archive production/test
change versus the 42-file parent; the existing memory-only
`expenseSqliteWorker.mjs` and `expenseSqliteTestSupport.ts` add bounded
archive-history controls. Other 40 source files remain byte-identical. Complete
PR scope is 55 paths. No schema, protocol, global wrapper, dependency or limit
changes are made.

Independent `directional_security` and `efaktura_remaining` accept exact
hashes before/after their tests:

- Author: 57 tests / three suites pass, four-file lint clean, typecheck
  110 inherited errors / nothing new.
- Security: 70 tests / three suites pass; virtual-loading only the immutable
  parent production module independently makes both causal tests fail again.
- QA: 19 selected permanent tests pass; 16 explicitly filtered cases are not
  counted as passed.
- Immutable parent native baseline: seven expected-vulnerable checks reproduce
  live late rows and invalid restore preflight. This is defect evidence.
- Corrected native proof: ten checks pass on actual OPFS and SDK merge logic.
  Seven/35 original history cells are replayed for partial/missing-all cases;
  every held timestamp predates the canonical deletion. Metadata fills one
  source/two chunks while deletion flags remain1; backup+restore preflight pass.
  Absent-intent validation failure rolls back fully; foreign owner IDs remain
  unchanged and malformed references fail before archive writes.
- Full lint: zero errors / 46 inherited warnings; typecheck baseline unchanged.
  Locale parity, whitespace and build (7,672 modules) pass. Runs overlap.

These verify SDK/CRDT merge behavior, not real relay delivery, newer writes or
deployment. The memory-only worker rejects network access; native transports
are disabled. Owned processes and all synthetic profiles are removed, the port
is closed and safe evidence/manifests remain in the private review directory.

The installed CodeRabbit CLI was considered. Automatic permission review rejects
the attempted execution before start because it may upload uncommitted source
to an external SaaS without explicit consent for that transfer. A specific
consent question is pending. No CLI upload/findings, tool installation,
authentication/configuration change or paid-credit use is claimed; independent
local review and exact-head CI proceed separately.

Next is scoped publication and fresh CI. Older green heads remain attributed
to their own revisions. The seventh thread is resolved after verified
publication. No merge or deployment is performed.

## Earlier CodeRabbit follow-up accepted locally - 2026-11-16

All six original actionable comments are verified against parent
`3f95ad5c9ad6608d24da74f8306d1eb8574b9844`. Suggested code and embedded
agent prompts are treated as untrusted review data. Two existing corrections
and four new bounded fixes account for every finding:

| Review comment | Disposition |
| --- | --- |
| [Missing client mocks](https://github.com/webiumsk/satflux/pull/449#discussion_r4580715299) | Already fixed in `a7410fc5`; both exports are present in both mocks, with unchanged assertions. |
| [Ambiguous owner-column join](https://github.com/webiumsk/satflux/pull/449#discussion_r4580715306) | Already fixed in `3f95ad5c`; actual SQL and native startup checks pass. |
| [Archive-arrival preview refresh](https://github.com/webiumsk/satflux/pull/449#discussion_r4580715274) | Fixed: local-first source and chunk subscriptions invalidate attachment resolution as bytes and readiness arrive. |
| [Coherent export selection](https://github.com/webiumsk/satflux/pull/449#discussion_r4580715281) | Fixed: captured typed rows, range, company/name and copied options/formats are used through resolution, planning and batch preparation. Synchronous selection changes cancel before requests/downloads; held multipart ZIPs are not delivered under stale selection. |
| [Custody proof after checkpoint](https://github.com/webiumsk/satflux/pull/449#discussion_r4580715285) | Fixed within the existing bounded contract: explicit callback checkpoint precedes byte proof; private fresh company/share queries then recheck owner, fingerprint and active-share authority. Existing context-race protection remains. |
| [Delete incomplete/corrupt archives](https://github.com/webiumsk/satflux/pull/449#discussion_r4580715296) | Immediate corrupt/missing-byte cleanup is fixed. The late-sync follow-up above extends intent to every canonical ID, even absent rows, while preserving authorization, structural checks, atomicity and no retry resurrection. |

The pre-late-sync source/test/locale freeze is 42 files at
`225967876edbab39c8364a6a061aefbdd8b2e4fa100d10b1ddcefe987df86237`
(SHA-256 of the compact sorted JSON file-hash map). Compared with the accepted
41-file parent, seven authored paths change and one bank-performance test adds
only the two new mock query tokens; all its assertions remain byte-identical.
Including documentation/changelog, the complete PR contains 53 scoped paths.

Independent `directional_security` and `efaktura_remaining` accept this exact
freeze. Authors do not approve their own security review. Evidence:

- Core author: 128 tests / six suites; five-file lint clean. Nine selected
  before-failing regression groups are recorded in the author's tool transcript.
- Main consumer/planner/performance run: 31 tests / three suites pass.
- Security: 102 tests / four suites, including six private no-resurrection
  probes, pass; all 42 hashes match before/after.
- QA: 12 permanent consumer tests and six independent probes pass; 28 selected
  core tests / three suites pass, with 56 explicitly excluded by the filter.
- In that earlier revision, twelve native Chrome/Evolu/OPFS checks pass: corrupt,
  partial and missing-all deletion of existing rows, plus no retry resurrection and fresh
  post-proof authority rejection retaining simulated server staging. Auth/API/relay
  boundaries remain inert; no real provider or multi-device delivery is claimed.
- Full lint: zero errors / 46 inherited warnings. Typecheck: 110 inherited
  errors / nothing new, baseline unchanged. Locale parity, whitespace and
  production build (7,672 modules) pass. Runs overlap and are not summed.

Private company/share queries are distinct, unsubscribed and used only after
proof. Security independently verifies installed SDK behavior: repeated reads
reuse cached promises, while an awaited preceding worker marker invalidates them
and makes the next read reach the worker. Fresh actual-owner equality, exactly
one live company, unchanged fingerprint and exact active-share mapping are
required. The reconciliation wrapper still checkpoints on callback exit; the
interval from final local proof to remote completion remains outside an atomic
cross-device guarantee. No server/global-wrapper expansion is made.

Native fixture processes and private profiles are removed and their port is
closed. Safe provenance, before/after evidence and manifests are retained in
the private review directory. Some author core evidence is transcript-only;
the retained provenance identifies it without inventing log files.

Next is publication of this bounded follow-up and fresh CI on its exact head.
Earlier green CI is retained as head-specific historical evidence. Thread
resolution follows verified publication; no merge or deployment is performed.

The owner explicitly authorizes point 1 of the remaining eFaktura work on
2026-11-15: import originals above 384 KiB, including UBL/XML with embedded PDF.
Implement separately on `feat/efaktura-large-originals` over fetched master
`2201bf1bacdc9a26571f188382b5e10999bbbf55`. The accepted, uncommitted
accountant-export clarity work and unrelated primary changes remain preserved.

## Architecture evidence and chosen contract

The current attachment field allows 524,288 base64 characters (384 KiB decoded).
Installed Evolu 7.4.1 also enforces a 655,360-byte mutation limit. Raising only
the field limit cannot support a 10 MiB original. Existing owner-scoped
`migrationSource` / `migrationChunk` storage already supports 256 KiB chunks
with exact size, order, owner and SHA-256 checks.

The existing server accepts XML up to 10 MiB, subject to its parser constraints.
It stores the encrypted original before provider ACK and already implements
`post_ack_proof_v1`: preparation retains the original; completion checks the
receipt/company/connection/digest/token before clearing temporary content.
No backend API, parser, schema or provider-bound increase is required.

Reuse the existing archive tables with a distinct `efaktura-original` kind and
receipt-specific source/chunk ID namespace. Do not weaken ordinary migration
validation or apply migration batch limits as lifetime receiving limits. Keep
ordinary inline attachments at 384 KiB. Larger originals use at most 40 chunks
of 256 KiB, bounded by the existing 10 MiB admission limit.

The attachment stores an explicit invalid-base64, versioned reference beginning
`satflux:expense-original:v1:`. Strict canonical metadata binds company, receipt,
expense, attachment, source, digest, decoded byte size and chunk count. Only
verified readers may resolve it; consumers must never export it as original XML.
The original bytes are not parsed/reserialized to create storage chunks.

Write chunks under the existing company/financial lease, checkpoint persistence
and verify full bytes before attaching the reference. Source IDs do not depend
on the current owner, so share/rekey can preserve identity. Pending retries must
not clear another device's ready marker. Existing valid originals cannot be
silently overwritten, and deliberately deleted rows are not resurrected.

Reconstruct and verify all raw bytes, index/count/size, SHA-256 and current
parent/owner/company/connection context before preparation, again after its
response, and before completion. Partial, foreign, duplicate, mixed or corrupt
chunks, quota/worker failures and context changes must prevent cleanup. Reload
and uncertain completion retries use deterministic IDs and committed data.

## Consumers and compatibility

The storage follows existing Evolu owner partitioning and end-to-end encrypted
relay synchronization. The current client does not configure SQLite encryptionKey;
native OPFS persistence is not evidence of encryption at rest. Password-protected
downloadable backups are encrypted when selected. No application-wide local
at-rest keying or database-encryption migration is introduced or claimed here.

- Downloads resolve verified bytes asynchronously. The existing 2 MiB XML
  embedded-PDF preview budget and individual PDF limits remain independent;
  larger preserved originals remain downloadable without expanding parsing.
- Accountant export retains its 512 KiB attachment cap. Resolve eligible archive
  references into verified in-memory base64; explicitly count larger files as
  skipped. Do not raise API/body/PDF limits or export reference markers.
- Updated backup creation detects any reference or new source kind and emits
  version 3. Complete reference/chunk/hash/binding validation precedes backup and
  every restore write. Updated restore rejects dangling old-v1 references too.
- QA proves immutable old clients accept opaque references and unfamiliar kinds;
  a new kind alone is not a version fence. Existing tables preserve complete
  old-v2 raw snapshots better than new unknown tables. Test that preservation
  explicitly, without claiming old clients can guarantee complete backups.
- Access, backup, restore and sharing of new originals require the updated
  client. Client rollback requires a compatible reader/backport. No retrospective
  safety guard can be added to already running old clients.
- Delete/share/rekey/revocation must preserve or deliberately delete the complete
  related representation. Existing local reset preserves expenses, so it must
  retain their archives; company deletion cleans up its new-kind sources/chunks.
- Relay resend/fingerprint includes complete relevant rows. Installed Evolu
  emits same-value changes, allowing full-row resends without temporarily
  corrupting contents, indexes, filenames or reference bindings.

Custody proof means fresh local persistence, not remote attestation, completed
relay replication or immunity to later intentional deletion. Existing explicit
company reset/permanent-delete server paths can remove pending custody and are
not changed into a universal no-delete guarantee by this feature.

## Ownership and independent pre-review

`directional_frontend` owns the reference/archive helpers, attachment CRUD,
inbox importer, client queries and focused original/custody/reconciliation tests.
`large_originals_integration` owns attachment UI reading, accountant consumption,
backup/snapshot/restore, local deletion and relay integration plus bounded tests.
Their exact writable file lists and API dependencies are assigned separately;
they do not write each other's files. Main owns locales, guides, shared records,
integration checks and Git publication.

`large_originals_backend` changes only two backend feature-test files. It verifies
existing large-original custody and the unchanged 10 MiB rejection boundary.
Independent `directional_security` and QA `efaktura_remaining` pre-review identify
cross-version omission, full-readback, mixed-part, rekey and interrupted-write
requirements; neither authored implementation. Final acceptance requires a
frozen revision, not these preliminary design observations.

## Validation and runtime

Test 384 KiB/chunk/10 MiB boundaries, embedded PDF, BOM/CRLF/UTF-8, bad/missing/
extra/foreign chunks and metadata; failures at each write/prepare/confirm stage;
reload, quota and multiple devices; company/owner/connection changes; deletion,
backup/restore/share/rekey and old client/backup behavior. Verify native Evolu
storage with an isolated browser and reload, not only mocked callbacks.

Graphify query-first runs against the primary graph; this isolated worktree has
none and none is rebuilt or installed. Existing Node dependencies are reused
after package/lockfile identity checks. PHP uses an existing image/vendor with
identical Composer lock, no pulls/network, read-only source/vendor and temporary
storage/cache. Resolved preflight is testing, SQLite `:memory:`, array cache/mail,
sync queue and no cached configuration. No real wallets/accounts/provider data,
production databases, package installation or deployment.

## Implementation review before the CodeRabbit follow-up

Implementation and independent QA/security review are accepted locally on
2026-11-16 at base `2201bf1bacdc9a26571f188382b5e10999bbbf55`.
The original 38-file review is aggregate
`d0ba510c9b9617484f65d63fb9981db7673e8c9ba000de5d5318f4b5b8c1143d`.
Subsequent fixture and runtime-query corrections produce a combined 41-file
source/test/locale freeze at aggregate
`48b383cc6e879a2d8380ca3a223ada392b206bee2597a39909ddfd032e41ec22`
(SHA-256 of the compact, sorted JSON file-hash map). Only `client.ts` changes
among the original 38 files; the other 37 retain their hashes. Two existing
client-mock fixes and one new actual-SQL regression extend the reviewed scope.
Five translated user guides and these development records accompany the change.
The separate accountant-export clarity freeze remains unchanged.

Independent `directional_security` and `efaktura_remaining` accept this final
scope with no unresolved blockers. They authored no implementation. Three medium
findings were reproduced against the implementation and fixed before acceptance:

| Finding | Fix and regression evidence |
| --- | --- |
| Noncanonical company, expense or attachment live flags could pass archive snapshot validation and reach restore writes. | Only undefined, null and numeric zero represent a live row. Backup and restore reject other values before output or any write. Permanent regressions and independent actual-restore probes pass. |
| Company tax identity or receiving settings could change during post-prepare reconstruction, allowing cleanup under stale context. | The original post-proof checkpoint fix is superseded by checkpoint-before-proof plus fresh private company/share authority checks after proof. Existing late-context regressions remain, with owner/revocation/pending coverage. Server staging is retained and completion is rejected on changed context. |
| The archive chunk join conflicted with the real owner's unqualified `ownerId` selection, causing ambiguous SQL even with no archives and leaving initial account navigation unresolved. | A single outer chunk table and correlated source `EXISTS` preserve ID, owner, company, kind and live predicates. The actual production client/wrapper/serializer regression fails twice before and passes twice after. Security independently reproduces the SQL error and accepts the exact two-file correction. |

Final main validation:

- Before the query correction, 274 frontend tests in 20 suites pass serially with
  existing deadlines. After correction, 26 focused tests / six suites, two-file
  ESLint and full typecheck (110 inherited errors / nothing new) pass.
- 49 PHP tests / 605 assertions pass across inbound inbox/service, provider
  receipt and durable ACK retry suites. Both changed test files pass Pint;
  production PHP and its admission/custody contract are unchanged.
- ESLint: zero errors, 46 inherited warnings. Typecheck: 110 inherited errors,
  nothing new; the baseline is unchanged. Locale parity passes.
- Production build passes with 7,672 modules; existing large-bundle/PURE annotation
  warnings remain. No dependency or lockfile changes.
- Final source hashes and whitespace checks pass; the original 48-file
  implementation becomes 52 scoped paths after publication and test corrections.

Independent evidence supplements those overlapping runs:

- QA's 160 focused tests in eight suites pass, including serial reruns of two
  parallel contention timeouts without changing deadlines.
- 44 compatibility/integrity probes exercise the actual old-v2 writer, parser
  and restore scheduler, all chunk counts from one through 40, encrypted backup
  roundtrips, missing/corrupt/mixed parts and validation before restore writes.
- 15 private malformed-live-flag cases, four private custody-race cases and six
  final nullable-binding/relay cases pass.
- 22 native Chrome/Evolu/SQLite OPFS checks verify exact 10 MiB through page reload
  and full browser restart, interrupted write recovery, duplicate identity,
  corruption/missing-part custody rejection and atomic deletion rollback.
  Four additional native cases verify the corrected context race.
- Seven further native checks exercise the corrected production query and owner
  wrapper in Chrome/OPFS: empty read, snapshot, full refresh, fingerprint, exact
  accessible owner/company/live-source filtering and post-reload account seed
  initialization. These cover a query omitted by the earlier native archive
  proof. Immediate SDK restore returns `owner_restore_failed` without hanging;
  after the required reload, pending-sync read completes as `already_synced`
  with its pending flag cleared. Inert auth/API/relay boundaries are explicit;
  real account/server behavior is left to E2E.
- Security independently reruns archive, reconciliation, snapshot, consumer,
  relay/deletion and PHP regressions. Runs overlap and must not be summed.

Native fixtures use a private synthetic owner, actual local storage, synthetic
custody callbacks and disabled external transports. They do not establish real
provider behavior, real multi-device replication or local encryption at rest.
Both fixture processes are stopped, their port is closed and private profiles
removed. No production account, wallet, database or merchant token was used.

Private evidence is retained under `storage/app/private/reviews/efaktura-large-originals`:
the source freeze, final main logs, QA provenance and result reports, native
matrix/context reports, teardown evidence and final independent review summary.
Provenance distinguishes initial native/compatibility runs from focused reruns
on the context, strict-flag and type/nullable-binding corrections.

The owner's push instruction publishes implementation commit
`91748ce3e7208353f7584e9a323a00139a02c268` on
`feat/efaktura-large-originals` in open PR449:
https://github.com/webiumsk/satflux/pull/449 . Documentation and the PR-linked
changelog are followed by a test-only CI compatibility correction, then the
bounded runtime query correction and regression, for 52 total
scoped paths. Initial CI43773180167 on `802412ad` passes 3,550 tests in 248
files but two suites cannot load: their existing client mocks omit the two new
original-source/chunk query exports. Adding those query tokens to
`inboxReconcileGuard.test.ts` and `migrationLifecycle.test.ts` preserves all
assertions; five targeted tests and two-file ESLint pass. Independent QA accepts
both corrected mock-file hashes and verifies unchanged assertions.

CI43773954997 on `a7410fc5` subsequently passes Node (3,555 tests / 250 files),
PHP (3,501 passed / 19,624 assertions, 31 skipped), PostgreSQL and changelog,
but two E2E routes remain blank before the initial guard completes. A baseline
rerun at PR448 head `54b9eea1` passes 26 tests with two existing flaky footer
cases and three skips. Actual SQL execution identifies the owner-column
ambiguity above; the additional native checks prove the corrected query and
post-reload initialization. The combined final freeze records that lineage.
CI for the corrected final head is verified separately and reported on the PR.

No merge, provider operation, public changelog publication or deployment is
performed. New-data client compatibility, rollback/backport requirements,
viewer/accountant caps and custody limits remain applicable. Review acceptance
and a published branch do not establish production readiness.
