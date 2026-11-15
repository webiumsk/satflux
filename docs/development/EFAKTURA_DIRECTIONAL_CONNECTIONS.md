# Independent eFaktura receiving and sending connections

Status: owner-authorized implementation, 2026-11-15. Base: remote-verified
master `4b00e2ee`, including merged PR446. Production deployment is unverified.

## Scope and acceptance

Support one effective receiving connection and one outgoing connection per company.
Existing accounts continue using their shared connection without a migration,
network request, registration change or credential re-entry. A customer may opt
into a separate receiving connection. Only existing SAPI adapters/presets are in
scope; another provider's operational compatibility is not implied.

- Preserve the existing `efaktura_sapi_*` fields as the outgoing/shared connection.
- Add `efaktura_inbound_use_shared_connection` (default true),
  `efaktura_inbound_provider` (sapi_sk), `efaktura_inbound_sapi_base_url`,
  `efaktura_inbound_sapi_client_id`, write-only
  `efaktura_inbound_sapi_client_secret`, encrypted storage and public secret-set
  marker. A separate incomplete connection fails closed without shared fallback.
- Retain the shared company participant identity, ownership, eligibility, global
  enable flag and independent receiving enable flag. Enterprise status credentials
  remain attached to the outgoing connection and server-only.
- Resolve incoming poll/health/test/doctor through the receiving connection;
  outgoing issue/send/retry/status through the outgoing connection. Connection
  tests accept optional `direction: inbound|outbound`, default outbound for old
  clients. The test body retains existing unprefixed one-shot credential names.
- Server-owned receiving test metadata uses
  `efaktura_inbound_connection_tested_at`; shared mode can reuse shared test proof.
  Receiving poll metadata remains in its existing fields. Unchanged readable
  credentials preserve proof; changed credentials invalidate only relevant proof.
- Receiving-only changes must not invalidate outgoing retry identity. Existing
  legacy fingerprints must remain verifiable without weakening company, provider,
  credential, document or original-byte checks. No automatic provider failover or
  duplicate send. Old uncertain attempts stay tied to their original connection.
- Preserve inbound original provenance, deduplication, encrypted staging,
  post-prepare local custody proof and ACK/recovery ordering.
- UI exposes sending/shared settings and receiving settings with a default shared
  choice, independent credential tests and clear save/retry feedback. Local-first
  save confirmation, stale-result/company fences and bridge synchronization must
  cover both directions. Five locales and user guides remain consistent.
- `/api/config` advertises server capability `efaktura_directional_connections`.
  A new client requires strict true before enabling or saving split settings,
  and verifies the directional settings echoed by the saved server/bridge.

## Ownership and dependencies

| Task | Role | Writable ownership | Dependencies and evidence |
| --- | --- | --- | --- |
| Backend | backend | eFaktura settings, requests/controllers/services/commands and related PHP tests under app/ and tests/ | Contract above; isolated PHP, Pint, PHPStan; report exact paths |
| Frontend | frontend | Affected eFaktura UI/composables/Evolu/API/types, related tests and five locales under resources/js/ | Contract above; Vitest, locale/type/lint checks; report exact paths |
| Coordination | main | This note, user guides, shared development records and generated API artifacts | Integrate and validate both halves; own Git publication |
| Reviews | security and qa | None; read-only reviewers | Independent final source review and reproducible isolated checks |

All other repositories remain read-only. No provider/account operations,
subscriptions, payment changes, tool installs, publication, merge or deployment.
Existing unrelated work and retained worktrees remain preserved.

## Validation and rollout

Cover shared defaults, split provider routing, independent replacement/removal,
non-payer receiving, missing split credentials, foreign company and cross-company
secret access, test races, local bridge confirmation, incoming original recovery,
legacy outgoing retries and status history. Run relevant existing PHP/Vitest
suites, static ratchets, locale parity, API freshness and production build.
Independent QA/security must accept the integrated result before delivery.

Deploy compatible backend before a new client. Do not run mixed old/new inbound
pollers. Direct downgrade to the prefeature backend is unsupported once separate
credentials exist: old pollers would use the shared sender, and old HTTP response
formatting does not strip the new encrypted receiving credential. Stopping
pollers alone does not restore the response boundary. A downgrade requires a
reviewed compatibility backport that preserves directional routing and secret
redaction, or a separately reviewed offline credential archive/removal procedure.
First stop/disable receiving schedulers/workers and drain active polls. Preserve
pending originals, provenance and recoverable settings; never delete the only
credential/original copy to make an old version start. Prefer a compatible backend
revision and roll back the frontend independently.

## Initial design review

Independent security reviewer `directional_security` accepts the directional
contract subject to the explicit rollback gate above. Legacy send compatibility
must compare complete legacy fingerprints for either receiving-enable value;
neither connection matching nor attempt evidence may be bypassed or rewritten.
Final source/security/QA acceptance was still pending at this design checkpoint.

## Final implementation and independent reviews

The implementation keeps the existing sender/shared fields and adds one optional
receiving override. No database migration or provider-account operation is needed.
The new client verifies the backend capability and the saved directional fields;
an older server cannot silently accept a split save. Receiving-only companies can
save a complete separate receiver without outgoing credentials. Shared defaults
retain the existing setup and credentials.

Outgoing attempts use a direction-specific fingerprint. Legacy attempts compare
the complete old fingerprint for either receiving-enable value, preserving the
original request, UUID, content, company and sender credentials. No attempt is
rewritten or retried through another provider. Polling/health/test/doctor and local
import context use the effective receiver, with no shared/global fallback for an
incomplete separate connection. Enterprise keys remain outgoing and server-only.

Reviewers are independent of implementation authors: `efaktura_remaining` (QA)
and `directional_security` (security). Both accept the final source; no blocking
source findings remain. Authors are `directional_backend`,
`directional_frontend`, and main for the final test-state integration correction.

Reviewed freezes (SHA256 aggregates of sorted source-file hashes):

- Backend: 20 files,
  `bcb551a4f8a3fc452e352f8482604df95ccff1c259642cd97ee02dec6e3bd05b`.
- Frontend: 15 files,
  `b074c1eb31184fc0b38f97bc699445280aae7737ad87cd5a18a379c976398a1c`.

Closed review findings and regression evidence:

- QA reproduced three stuck Test/Save states after editing the opposite direction
  or receiving mode during a pending test. Symmetric busy-flag invalidation fixes
  them; independent mounted probes fail before and pass after the correction.
  Permanent tests also verify that an old reply cannot clear a newer pending test.
- Incoming fresh-company guards now compare the captured owner before import,
  provider ACK and poll metadata. Independent synthetic transfers during listing,
  detail retrieval and committed-custody-before-ACK preserve originals and reject
  old-owner completion.
- Explicit clearing of an unreadable credential invalidates its proof even when
  the effective secret compares null to null. The other direction is preserved.
- Security independently reproduced an inherited Enterprise-field leak in the
  touched owner migration export. The export now strips every Enterprise private
  field and marker while retaining both owner-authorized SAPI secrets. A real
  authenticated export regression fails before the fix and passes afterward,
  including foreign-company isolation.
- Security identified the old-backend downgrade hazards documented above. These
  are compatibility constraints, not a claim that old workers support split data.

## Validation evidence

Main passes 318 frontend tests across 14 suites, full lint with zero errors and
46 inherited warnings, unchanged TypeScript ratchet (110 tolerated, no new),
five-locale parity, full PHPStan with zero errors, Pint for all 20 changed PHP
files, API export freshness and three OpenAPI schemas. Production build passes
with 7,669 modules and existing chunk-size warnings. Whitespace is clean.
Final broad PHP validation passes 293 tests / 2,054 assertions, including the
disposable independent-connection durable-ACK test.

Independent QA passes 200 PHP tests / 1,362 assertions on the previous freeze,
then all eight migration-export tests / 39 assertions on its final correction;
two private PHP matrix tests / 30 assertions; 245 frontend tests in eight suites;
and all three private mounted race probes after reproducing their failures.
Independent security passes 181 PHP tests / 1,167 assertions in seven suites and
135 frontend tests in four suites on the final freezes. Counts overlap.

PHP checks use the existing PHP 8.3.31 / Laravel 12.71.0 image and compatible
existing vendor, network disabled, read-only source/vendor, explicit testing
environment, uncached configuration, SQLite memory database, array cache/session
and synchronous queue. The existing durable-ACK test uses only a disposable
SQLite file under temporary storage and independently observes committed custody.
The harness creates required framework testing directories and uses a 1 GiB PHP
limit. Initial missing synthetic BTCPay settings/directories and the lower image
memory limit were corrected before final validation; no production environment
or real credentials were used. One inherited PHPUnit deprecation remains.

Graphify was queried from the primary baseline before exploration. The isolated
checkout has no graph; no graph rebuild or tool installation was performed.
Exact scoped source and tests supplied the implementation/review evidence.

## Delivery checkpoint before publication

The owner merged PR446 as `4b00e2ee`; this feature is retained on
`feat/efaktura-directional-connections` in the named durable worktree. Changes are
local and uncommitted. Five user guides describe the same behavior. No provider
request, account migration, installation, PR/publication, merge or deployment was
performed. Production and additional-provider compatibility remain unverified.
Next: owner reviews the local result, then scoped publication/fresh-head CI under
applicable authorization; merge and deployment remain owner decisions.

## Publication authorization - 2026-11-15

The owner explicitly authorizes committing and pushing the reviewed result to
`webiumsk/satflux`, branch `feat/efaktura-directional-connections`. Before staging,
all 35 source/test/locale files match the independently accepted manifests. The
bounded publication also contains ten related guide/development documents; no
primary-checkout edits or private artifacts are included. Read-only remote Git
confirms master remains `4b00e2ee` and the target branch does not yet exist.

Existing local test/review evidence remains applicable without rerunning unchanged
source. This repository runs CI for pull requests and main/master pushes, so a
feature-branch push alone does not produce fresh-head CI. PR creation, merge and
deployment are separate from this authorization. The coordinator verifies the
published ref and records the actual commit in the primary project state.
