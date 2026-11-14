# eFaktura receiving status and recovery actions

## Authorized contract - 2026-11-15

The owner authorizes correcting misleading receiving status after an unchanged
connection is saved and exposing a meaningful recovery action from Expenses.
Extend the existing unpublished `fix/relay-status-banner` worktree over owned,
remote-verified master `ce725a99`; preserve the previously accepted relay change.
Primary checkout and other worktrees remain unchanged except main-owned records.
Primary Graphify query completed; this worktree has no graph and none is rebuilt.

Acceptance criteria:

- Resaving the same effective receiving credentials must preserve last-poll and
  test evidence. Actual credential/connection changes, removal and revocation
  must continue to invalidate evidence. Never log credential material.
- Existing imported expenses remain independent historical data, not proof of
  current receiving health or legal/provider production readiness.
- The Expenses panel offers an explicit Check mailbox action using the existing
  authorized bridge-company poll endpoint, then refreshes pending documents and
  independent server health. Refresh alone remains a read-only list/status read.
- Capture active local company, bridge and generation before asynchronous work;
  never poll a different company's bridge or apply obsolete completion state.
  Prevent duplicate requests and make failure/retry visible without hiding documents.
- Missing last-poll evidence must describe that missing check, with actionable
  wording rather than asking an already configured user to save again.
- Show that successful local connection verification requires Save; include a
  changed verification timestamp in unsaved-state presentation without silently
  saving credentials, weakening connection guards or blocking an already-saved test.
- Preserve pending-original custody, acknowledgement, idempotency, isolation,
  server authority and EN/SK/ES/DE/CS parity. No new API, schema or dependency.

| Task | Owner | Exclusive writable scope | Acceptance evidence |
| --- | --- | --- | --- |
| Backend credential equality | backend specialist | `app/Support/Invoicing/CompanyEfakturaSettings.php`; new bounded `tests/Unit/CompanyEfakturaPollEvidenceTest.php` | Equal/changed/cleared/corrupt secret and existing identity guards; PHP checks |
| UI/live-state correction | frontend specialist | `EfakturaInboxPanel.vue`, `CompanyEfakturaSettingsForm.vue`, `efakturaInboxLive.ts`; `efakturaInboxPanel.test.ts`, `efakturaInboxHealth.test.ts`, `efakturaSettingsWizard.test.ts` | Mounted action/unsaved-state, company/bridge races, error/duplicate regressions |
| Copy/docs/integration | main | Five locales, relevant user guides, this contract and project records | Consistent translations/guides, lint/type/parity/build and integration checks |
| Independent QA/security | existing reviewers | No source writes | Frozen source scope/hashes, reproduced findings, actual tests and acceptance |

Specialists must not commit, push, publish, merge or deploy. Reuse installed
dependencies only. Synthetic fixtures and verified disposable PHP targets only;
no merchant browser/provider/database access. Main owns all publication. Neither
merge/deployment nor live provider polling is authorized as part of validation.

## Implementation and regression evidence

Equal readable nonempty normalized SAPI secrets preserve trusted poll/test evidence;
changed, cleared or unreadable credentials invalidate it. The comparison uses
plaintext equality without exposing material or depending on randomized ciphertext.
Provider changes now join connection invalidation. Incoming server-owned poll/test
metadata and encrypted secret fields cannot replace trusted values. Existing HTTP
validation, locks, fingerprints and Enterprise key/identity revocation remain intact.
Author's21 new regression cases fail6 times before the fix, then pass with the
existing settings suite (26 tests /101 assertions). These counts overlap later runs.

Expenses keeps the receiving panel accessible with no pending documents. Check
mailbox uses the current singleton-derived company/bridge/generation and existing
poll endpoint, then independently refreshes retained documents and server health.
Refresh alone remains a read-only request for Satflux's stored list/status. Duplicate
checks and competing refreshes are excluded through the final health result.
Company/bridge/blank-context changes and component unmount fence obsolete actions.
A failed provider poll still reloads retained partial documents and remains retryable.
No response or empty list is labelled as production or delivery proof.

A changed local test timestamp is now visibly unsaved; successful verification asks
for explicit Save. Saving clears the indication without credential autosave. Snooze
policy, provider endpoints and original custody/acknowledgement remain unchanged.
The five translated guides explain imported expenses versus the pending inbox and
provide the actual Check mailbox and Refresh actions.

Main final validation:

- 246 frontend tests /11 suites pass, combining both banner scopes and original
 custody/import/reconciliation regressions.
- 136 PHP tests /1,018 assertions pass across new evidence/settings, HTTP settings,
 connection checks, inbound inbox/service, ePostman inbound, durable ACK retry and
 Enterprise status suites.
- Full lint passes: zero errors /46 inherited warnings. Author's full typecheck
 passes at the unchanged110-error ratchet with no new errors.
- Full PHPStan passes with zero errors; scoped Pint passes2 files.
- Five-locale parity, whitespace,16 existing changelog fragments and production
 build7,664 modules pass. Existing annotation/chunk notices remain.

Test preparation uses a verified network-disabled container with read-only source
and compatible existing vendor, PHP8.3.31/Laravel12.71.0, SQLite :memory:, synthetic
bootstrap credentials and fresh temporary storage/cache. Missing initial mountpoints
and bootstrap placeholders were corrected only in the disposable harness. The
subsequent complete selection and analysis pass. No dependency installation or real
provider/merchant operation occurred. The changelog check's Git child-process was
blocked in sandbox; normal approved execution passes. A new public changelog entry
requires the eventual real PR URL; none is fabricated here.

## Independent review and source freeze

QA `/root/relay_banner_qa` accepts120 PHP tests /945 assertions and133 frontend
tests /five suites, scoped six-file ESLint, locale parity and whitespace. Security
`/root/relay_banner_security` accepts77 PHP tests /480 assertions and123 frontend
tests /four suites plus parity/whitespace. Counts overlap main/author runs. Both
reviewers are independent of the implementation authors and make no source edits.
No blocking findings remain in the reviewed18-file eFaktura scope.

The reviewed aggregate is SHA256 over sorted path + NUL + bytes + NUL:
`4609260d8df5d14e31c16d533ef07c44d467cd650337d1e281fe21ce9bbc8811`.
All18 hashes are verified again after review and main validation. The previous
relay banner and its tests retain their exact independently accepted hashes; shared
locales gain only this reviewed eFaktura copy. Already submitted provider operations
cannot be recalled; obsolete completion paths are fenced. Live delivery, full
recovery and deployed behavior are not asserted from synthetic tests.

## Delivery state and next step

Both corrections remain local, uncommitted and unpublished in the durable
`relay-status-banner` worktree on `fix/relay-status-banner`, base `ce725a99`.
Primary unrelated changes and all other worktrees are preserved. No new PR, remote
commit, CI, merge or deployment is claimed. Owner may review the concrete result;
publication/new-head CI and owner merge/deployment gates remain separate.
