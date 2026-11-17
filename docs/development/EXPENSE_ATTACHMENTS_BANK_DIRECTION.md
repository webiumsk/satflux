# Manual expense attachments and bank direction

## Publication handoff - 2026-11-17

The owner authorizes normal Git publication. Reviewed source is committed as
`08e68fefc980ec5e8829078110255c0ef883ed29` on
`fix/expense-attachments-bank-direction`; [PR451](https://github.com/webiumsk/satflux/pull/451)
is open and unmerged against master `18729a26`. All 56 final source hashes remain
`dacf07ba8f8487ec8b70562c14838e9c5cd2d2818db5367bca554f2ce7e871b0`.
The follow-up adds publication records and a reviewed combined changelog fragment
without changing implementation. Refer to PR451 for its latest head, exact-head
CI and automated review; local evidence below is not remote CI or deployment.
No merge, deployment, public changelog release or provider operation occurs.

## Authorized scope and baseline

The owner requests larger manually uploaded expense attachments and correct
debit/credit presentation for bank movements. The isolated branch
`fix/expense-attachments-bank-direction` starts at verified remote master
`18729a26fc7f99d34364f8317b852d205a33f107`, including merged PR449 and PR450.
The primary checkout and all unrelated work remain preserved. Source merge
status does not establish deployed behavior. No publication or deployment is
part of this implementation milestone.

## Acceptance and contracts

- Manual attachment selection and saving accept files through 10 MiB, including
  the reported approximately 450 KiB case. Larger files fail before writes.
  Small files preserve their existing inline representation and limits.
- Larger files use existing encrypted owner-scoped archive chunks and strict
  canonical references. Manual references have their own version and source
  kind, MIME binding and company/expense/attachment/digest identity. They do not
  invent an eFaktura receipt or expand Evolu cell limits.
- Existing eFaktura v1 references, receipt custody and inline attachments remain
  compatible. Resolution, download, deletion, backup/restore, sharing/rekey and
  fingerprints recognize the new representation with the same integrity checks.
- An atomic initial source/reference write makes interrupted uploads visible
  and deletable. Incomplete/corrupt originals cannot be downloaded or included
  in a complete backup. Retry must not create duplicate attachments.
- Stored bank direction survives generic nonempty text. Actual explicit legacy
  text hints retain their existing correction behavior. Display, totals and
  reconciliation consume consistent directions; original recorded direction
  remains separate payment evidence. No transaction records are rewritten.
- Preserve tenant isolation, fresh browser context checks, authentication,
  encrypted local storage, payment proof guards and all five locales.

## Ownership and dependencies

| Task | Role | Writable scope | Dependencies and evidence |
| --- | --- | --- | --- |
| Manual attachment archive | frontend `manual_attachments` | Attachment CRUD/composable, archive reference/read/write/snapshot helpers, bounded client/fingerprint/eFaktura predicates and focused tests | Existing PR449 archive contract; boundary, interruption, owner/context, consumer and native-storage evidence |
| Bank direction | backend `bank_direction` | PHP model/guesser, TS guesser and bounded bank tests | Nullable actual text evidence; before/after generic debit, API/totals/action and proof regressions |
| Integration | main | Five locale files, five user guides, this report and coordination state | Reconcile both changes; existing checks and unchanged ratchets |
| Security | independent `independent_security` | None (read-only review) | Exact final source freeze, privacy, binding, corruption, recovery and payment boundaries |
| QA | independent reviewer | None (read-only review) | Exact final source freeze, behavior and coverage gaps; disposable synthetic tests |

All implementation is in Satflux. Other repositories are read-only. Specialists
do not publish Git changes or deploy.

## Verification setup

Graphify query-first ran against the primary graph and returned unrelated
nodes. This isolated worktree has no graph; none is installed or rebuilt.
Targeted searches follow. Existing Node and Composer dependencies are reused
only after identical package and lockfile checks against the reviewed prior
worktree/vendor source.

The PHP runner has no network, read-only code/vendor and disposable storage and
cache. Resolved preflight confirms `testing`, SQLite `:memory:`, array cache,
session and mail, synchronous queue and no cached configuration. Test data are
synthetic. No production accounts, funds, databases or merchant files are used.

## Results

The final 30-file source/test/locale freeze is
`b677cde350ac194bd89089339877a13c88f914c6222740bf2348a8f663083dc0`
(SHA-256 of the compact sorted JSON path/hash map). The 18 production/locale
files have aggregate
`73af3611760c23e2148694cd9fcd2a1d737c26fee100d4d8a15428a7656dca15`.

Independent `independent_security` accepts these exact files with no unresolved
blocking findings. Its former-owner same-ID probe reproduced an initial
wrong-owner write; explicit owner selection corrects it and a permanent
regression preserves the old copy. Fourteen independent adversarial cases
cover substituted MIME/source kind/company/owner/chunks/digest, noncanonical
references and mixed-owner snapshots. Its 54 bank evidence tests pass.

The inherited contradictory PDF-name/MIME preview hazard is also hardened:
preview URLs receive a PDF-only Blob when appropriate, while stored/downloaded
original bytes and MIME stay unchanged. Production CSP already restricts scripts;
arbitrary production script execution was not established by this review.

Bank-author checks pass 205 JavaScript cases and 77 PHP cases / 243 assertions.
Independent QA passes 122 bank cases including mounted red-minus/green-plus
presentation and preserved recorded-direction guards. These counts overlap.
Scoped Pint and PHPStan pass; their existing baselines remain unchanged.
The final full JavaScript run passes **3,685 tests in 257 files**, using
`npm test -- --maxWorkers=2`. This includes the inherited maximum-size archive
case and all 12 new manual attachment cases. Full lint passes with zero errors
and 46 inherited warnings. Locale parity and the production build (7,674 modules)
pass. Final typecheck passes with 110 inherited errors and nothing new; the
baseline is unchanged.
Dependency manifests are unchanged.

Concurrent exploratory attachment runs were stopped and are not counted as
passes. A new test originally deep-compared a 10-MiB Buffer through millions of
enumerable keys, taking 146.6 seconds. Two assertions now check explicit length
and native `Buffer.equals`, preserving exact-byte verification. The same maximum
case passes in 17.3 seconds under its unchanged 30-second limit; its isolated
12-case suite and the complete final suite pass. Independent security verified
the exact two-assertion change and unchanged production hashes.

Independent native browser QA verifies exact 450-KiB and 10-MiB bytes, canonical
MIME, bounded chunks, duplicate identity, oversize rejection, reload/full browser
restart persistence and complete v3 backup. Interrupted writes remain visible
and block download/complete backup. Restarted retry preserves one identity and
exact bytes. Canonical deletion intents cover absent chunks, prevent retry
resurrection and allow complete backup after cleanup. Independent
`independent_qa` accepts the final 30-file freeze: 122 independent bank tests
and 19 native assertions across three bounded runs pass. The native fixtures
disable auth/API/relay transports; no real funds or merchant data are involved.

Native fresh-target restore remains **unverified**. The exercised restore over
already-deleted same IDs reports 63 upserts and zero rejections, but subsequent
read does not revive the deleted attachment. Generic restore/row filtering and
its NULL live-flag behavior are byte-unchanged from the baseline, including v1
and inline rows; no v2-specific failure was established. This is not claimed
as a successful fresh-profile restore. The permanent snapshot restore tests
and full final suite pass; deletion resurrection semantics are not expanded.

Final native QA records zero external/API attempts and zero page errors. Its
browser is closed, the dedicated server exits successfully and its synthetic
profile is removed. A separate socket-listing check was unavailable; no
independent closed-port assertion is made. Safe source hashes and validation
summary are retained in the matching ignored private review directory.

## Compatibility and limits

The v3 backup envelope remains; old clients reject unsupported v2 references
instead of silently omitting originals. Update readers before relying on manual
archive attachments across devices. Existing preview and accountant-export
budgets remain unchanged; a file can be saved/downloaded through 10 MiB without
necessarily qualifying for an inline preview or every export format.

Interrupted manual uploads retain a visible incomplete attachment. Retry while
the pending File remains selected; after reload, delete the incomplete attachment
and reselect the original. Incomplete/corrupt archives intentionally prevent a
complete backup until repaired or deleted. All five user guides describe recovery.
Native synthetic storage tests do not establish real relay delivery, production
quota availability, multi-device races or deployed behavior.
