# Larger received eFaktura originals

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

## Current status

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
| Company tax identity or receiving settings could change during post-prepare reconstruction, allowing cleanup under stale context. | Import and reconciliation checkpoint after proof, then read and validate the current company context. Four permanent, four independent private and four native browser cases preserve server staging and call completion zero times. |
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
