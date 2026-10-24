# Wallet input and Cashu checkout policy repair

## Authorization, scope and acceptance

The Product Owner reports premature wallet detection while typing, CashuMelt
offered alongside a configured Lightning wallet, and refused re-enable after
disabling CashuMelt with an unresolved funding-status HTTP 400. Installed provider
version is owner-reported **1.4.0.7**. No production access or payment outcome is
verified. The owner explicitly chooses to hide Cashu for **every configured
Lightning wallet**, including API-key wallets and Boltz, not only LNURL addresses.

Satflux branch `fix/wallet-detection-cashu-checkout` starts at `5942032a`, after
the owner merged PRs #397 and #398. Plugin branch `fix/cashumelt-checkout-settings`
starts at `6bdd688`. Tickets work, the original root image edit and the prior
wallet-monitoring runbook follow-up remain separate and preserved. Registry
resolution confirms both owned remotes. The root Graphify query returned
unrelated/stale context; isolated worktrees have no graph. Targeted reads were
used without silently rebuilding a graph or installing tools.

Acceptance:

- Wallet connection settings detect on blur or explicit action. Typing alone
  must not select Cashu, expose an unreviewed fallback or send validation requests.
  Save/Test validates the actual current input; stale async results cannot replace
  newer input/store state. Existing guest and six-digit confirmation rules remain.
- Authoritative provider checks any configured BTC-LN/BTC-LNURL, even excluded or
  unhealthy. Such stores cannot create a new Cashu checkout prompt/quote or
  activate an old empty placeholder. Lightning outages do not enable Cashu.
- A real durable Cashu quote, bound to the exact store/invoice/prompt, retains its
  original destination, snapshots, polling and recovery. Hiding new checkout
  offers must not cancel or recreate old quotes or alter their evidence.
- Re-enable with identical mint, unit, payout address, trust list and both caps
  is availability restoration, not a wallet switch. The provider compares a fresh
  database row under its existing exclusive store lease. Combined re-enable and
  risk-field change retains unresolved-fund checks. Existing fee-only update
  behavior is not broadened or tightened incidentally.
- Satflux's narrow pure-Cashu enable-only path keeps ownership, email grant,
  store lock and durable applying/uncertain journal. It sends all unchanged risk
  fields explicitly, preserves Lightning methods and retains that intent during
  read-only reconciliation. Real wallet switches retain every existing guard.
- Funding-status HTTP 400 remains unknown. No paid/unpaid classification, deletion,
  quote replacement, mint change, proof disclosure or production action follows.

## Independent assignments

| Owner | Exact responsibility | Boundaries |
| --- | --- | --- |
| Frontend | WalletConnectionForm.vue, WalletConnectionSmartPaste.vue, dedicated detection tests | No backend/locales/Git publication; shared creation-wizard default preserved |
| Plugin | CashuMelt handler/config/quote lookup/backend presentation/registration and tests | No core, third-party, LNAddressConnect, Tickets or version publication |
| Main | CashuWalletUpdateService, enable-only tests, locales, docs, integration and Git lifecycle | Separate repository branches; no production or deployment |
| Security | Independent read-only design and final source review | No author self-approval or source writes |
| QA | Independent source/contract review and isolated execution | No source/Git writes; synthetic data only |

Core reference is a separate clean checkout at exact BTCPay 2.4.5 pin
`5d0745cae6be5d8210459e38813f317673aa97b8`. Generated build caches are reused
only as dependencies; no core source changes are included.

## Confirmed defects and unverified incident state

The input component watched every draft character; the form showed fallback for
any nonempty draft and lacked current-input checks on async responses. The
provider deferred Cashu behind Lightning but allowed user selection or later
activation. Provider settings treated false-to-true as a target change even when
all target fields were identical. The old backend banner classified receive-only
LN-address connections using a legacy outbound-adapter classifier.

These are source findings consistent with the screenshots. They do not establish
whether the reported payment funded its mint quote, minted ecash or reached the
receiver through Cashu. The owner later reports the actual BTCPay invoice was paid
directly through Lightning and is Settled: Cashu was active when it was created,
then was disabled after being noticed in checkout. That is consistent with an
unused parallel Cashu quote, but invoice settlement does not prove the separate
mint quote was unfunded. Retain its HTTP 400 as unknown funding status. The receiver
wallet is not available to the owner for inspection. See
[the read-only runbook](CASHUMELT_FUNDING_READONLY_RUNBOOK.md); diagnosis precedes
any separately approved active recovery. Turning Cashu on or hiding checkout does
not itself resolve the payment's outstanding safety hold.

## Compatibility, rollout and rollback

Install a separately approved tested provider candidate before enabling the new
consumer path. New Satflux with 1.4.0.7 fails closed if the provider rejects
reenabling, keeping the email grant and disabled state; it must not delete
Lightning methods. New provider with old Satflux enforces checkout suppression,
but the old consumer can still refuse an enable-only update. Native provider UI
uses the same target guard as its API.

Both new versions can restore Cashu availability while leaving it absent from new
Lightning checkout. Already materialized quotes remain separately accessible;
there is no promise to erase history or clear existing unresolved holds.

Rollback the scoped commits without deleting schema, payments, snapshots, plugin
database or Data Protection keys. Reverting the provider restores the old parallel
checkout behavior and re-enable restriction for future operations; it is not a
fund-recovery action. No schema migration or environment variable is introduced.

## Validation and remaining gates

Main's isolated PHP checks pass **260 tests / 1,794 assertions**: 61 enable-only/
wallet-compatibility cases, 191 existing Cashu recovery/payment/integrity cases,
and 8 documentation cases. Full PHPStan and changed-file Pint pass. Dependencies
were independently verified against the unchanged Composer lockfile. Tests use
network-disabled PHP, SQLite in memory, synthetic keys and fake provider HTTP.

Frontend author passes seven related wallet/creation files (88 cases), then the
final changed regression file (17 cases, overlapping the prior group). Typecheck
retains 120 baseline diagnostics; lint passes with 0 errors/48 existing warnings;
locale parity and production build pass. Independent QA passes 21 PHP cases/178
assertions, 17 checked-in Vue cases and five additional temporary lifecycle cases.
Initial QA-fixture root/button-lookup failures were corrected without changing the
application; failed fixture logs were preserved. No manual browser was run.

Versioned CashuMelt **1.4.0.8** passes author focus **29/29** and full suite
**163/163**, zero skipped, against the exact pinned host and a fresh loopback-only
PostgreSQL container with tmpfs data and synthetic credentials. Independent QA
passes **22/22** with the same DLL and no rebuild. It also executes the runbook's
exact projections in a read-only transaction: mode `on`, zero synthetic rows,
ROLLBACK. Host guard, its regression and tracked-core cleanliness pass. Inherited
host compiler/NU1902/NU1903 dependency advisory warnings remain outside this fix.

Independent source security accepts both bounded scopes without change-specific
blockers. Fingerprints use SHA-256 of string-sorted records
`relative path + space + file SHA-256 + LF`:

- Consumer five critical code/test files:
  `a56ad0da3d63fc2e0f9403e2e5af0fd2aecabadc01086bca58be49097362dcb6`.
- Provider eight production/version files:
  `3b3239d56d0dac74775db15358c17ceb1436be25fb85dbd499bb056a5e7e0e42`.
- Provider twelve source/test files:
  `2fbfdd5ef46378e6d550ae32d0579ba3120f31cb0be30bf49b0faddf6993ed2f`.
- Provider fifteen files including README/release/API docs:
  `f42a707e2c486e6a1f27fc70d8ad372888de1f1a9d65cbbac2ff8c8c3410f461`.

The locally prepared installable candidate contains **28 entries / 3,626,876 bytes**,
with metadata version **1.4.0.8** and host dependency **>=2.4.5**. Its DLL is byte
identical to the versioned test output; packaging used no-build publish and the
existing pinned-host packer, with serialized MSBuild to avoid a local parallel
publish failure. This does not establish install/runtime behavior in production.
Independent QA also verifies archive CRC, safe unique entries, sidecar/checksums,
tested DLL identity and absence of host/other-plugin DLLs, secrets, database or
test artifacts. The task-owned PostgreSQL container is stopped after all reviews;
no production container was accessed.

- Archive SHA-256:
  `ad46b3dba8a4e54217ee0946e8ee4aa1eebabb60bc93208de5d71d2da2468461`.
- Tested/packaged DLL SHA-256:
  `0cc4a92e79f517202f74074df5b4244324f913a7b98d217c8bed2a628d175c85`.
- Sidecar SHA-256:
  `c75671ea6f960277528d8aec7fafb682a8d69002859b92ec0fd1a9b8359911b2`.

Checkout eligibility uses request-time host StoreData; no transaction/CAS across
simultaneous core store-configuration changes and plugin quote creation is claimed.
Representative browser/provider-first staging, source publication and installation
approval remain separate gates. No live wallet, real-fund test, current production
configuration, deployment or incident resolution has been verified. Existing
funding HTTP 400 remains unresolved even when the BTCPay invoice settled by LN.

## PR review and CI follow-up (2026-10-22)

Initial consumer PR #399 (`eae6f32f`) passes PHP and Playwright CI. Its Node job
stops at npm's production dependency audit before lint/typecheck/tests/build:
locked DOMPurify 3.4.16 is reported critical. Update only the direct minimum and
locked package to upstream-patched 3.4.17; all other lock entries remain unchanged.
Do not lower the audit threshold or use a forced broad dependency update.
Upstream conditions concern non-default sanitizer modes; this is not evidence of
exploitable Satflux XSS. Existing moderate/high dependency advisories remain for
separate triage.

CodeRabbit correctly identifies that LIMIT 20 can hide relevant older payment rows.
The runbook now emits matching_rows and rows_truncated using window counts before
LIMIT, retaining exact store/amount filters, read-only mode, timeouts and projections
that exclude private recovery contents. Truncation is not evidence of absent funds.
The provider's LNURL-only description now acknowledges its configured payment method
while preserving Unknown/null/no-payout classification. Its persisted-snapshot test
explicitly compares before and after unchanged re-enable, rather than relying on
a consumer HTTP fake.

The provider's three-file follow-up passes author focused tests **11/11**, no skips,
on a newly verified disposable PostgreSQL fixture and the unchanged pinned host.
Independent source security accepts the provider delta and runbook. Provider
three-file fingerprint (same line-hash algorithm as above):
`c3d36beb75245a633ee1df1b5586fc02a05343997056f8e92d531e86a6eeb989`.
Earlier provider fingerprints, full-suite results and package hashes above describe
the original pre-review revision. The existing local 1.4.0.8 archive is intentionally
unchanged and does not contain this description follow-up. A refreshed package
requires a separate reviewed build; no release or deployment is performed here.

Independent follow-up QA passes **6/6** provider cases without rebuilding, against
the tested DLL. Runbook SQL smoke passes four synthetic boundaries: zero matches,
20 matches without truncation, 21 matches capped at 20 with total 21/truncation,
and three foreign-store matches excluded. Each diagnostic transaction confirms
read-only mode and rolls back; synthetic setup/cleanup is confined to the disposable
fixture. An omitted oldest row retains its synthetic proof marker in the database.
Both independent reviewers verify that every non-DOMPurify lock entry is identical.
The patched-lock production audit exits zero at the unchanged critical threshold:
**0 critical, 6 high, 35 moderate**; DOMPurify is absent from the remaining findings.
This is not a vulnerability-free dependency-tree claim. Provider follow-up is
published as `dd230ca6ecec68adb442b032fc671d896a574551` on existing PR #42.

CodeRabbit's incremental provider review after `dd230ca` is blocked by its free OSS
review limit; the green status still references the previous reviewed commit.
Do not treat that status as a new-head source review. Independent security/QA
above cover the follow-up. The original CodeRabbit findings are addressed in code;
no manual thread resolution or extra reviewer request is implied.

Patched-version validation passes seven temporary sanitizer checks, including the
actual FAQ component, the existing raffle/documentation option contracts and the
three advisory regression fixtures. Preconditions assert DOMPurify 3.4.17 from an
isolated verified tarball; the shared installation remains unchanged. Production
build passes in the isolated source copy and its module-inspection hook confirms
the patched package is bundled; existing large-chunk warnings remain. Independent
QA reviews the fixture contracts, version/integrity, audit JSON and build evidence.
No manual browser test or exhaustive exploitation assessment is claimed.
The new provider commit `dd230ca` passes compatibility CI run `39836684816`.
