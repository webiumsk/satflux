# Current relay warning banner

## Invoicing status publication reconciliation verified - 2026-11-15

Owner requests push of both accepted corrections. Implementation commit `8d0b4999`
contains exactly31 reviewed paths. Fresh remote master is `4b9cc230`, the owner's
merged PR445. Reconcile it locally with a normal feature-branch merge before push.
Ten conflicts preserve both development histories, the newer five-guide date and
both required inbox imports. The combined panel is exactly the accepted receiving
panel plus the master's PDF import/component. No additional product logic is added.

Independent QA accepts all41 affected paths and162 tests /five suites; security
accepts the narrow17-file byte-preserving integration. Main passes391 combined
tests /15 suites, lint0errors/46inherited, type110/no new, parity/whitespace and
build7,669. Earlier136 PHP/1,018, PHPStan0 and Pint2 remain applicable through
unchanged backend bytes. Merged source fingerprint is
`42fa35d13d66eccc9f6e04224db322cb3066019d11eba289a6901f10097279ee`.

Main owns the pending normal push and remote-head verification. No new-head CI,
PR merge or deployment is claimed by this pre-publication record. Owner retains
PR creation and merge/deployment decisions. Earlier uncommitted/read-only notes
below are historical; both corrections are now authorized and committed locally.

## Authorized eFaktura follow-up - 2026-11-15

The owner now authorizes the eFaktura correction described in
`EFAKTURA_RECEIVING_STATUS.md`. The earlier read-only eFaktura boundary below is
historical and no longer limits this follow-up. Both corrections share the durable
`fix/relay-status-banner` worktree. The relay component and test retain their
accepted bytes; shared locale files gain the separately reviewed eFaktura copy.
The current18-file eFaktura freeze and validation are recorded in the new report.
Both scopes remain local, uncommitted and unpublished; no deployment is claimed.

## Authorized scope - 2026-11-14

The owner authorizes replacing the invoicing overview's sticky global relay-quota
warning with current per-relay observations and recommended recovery steps. The
separate eFaktura readiness notice is a read-only diagnosis in this task; do not
change its test persistence or snooze policy. No provider, payment, transport,
authentication, encryption, database or account-preference mutation is required.

Base: owned master `ce725a99`, independently verified against the remote master.
Work is isolated on `fix/relay-status-banner`; unrelated primary edits and other
feature worktrees are preserved. The primary Graphify query completed; this
worktree has no graph, and no graph is installed or rebuilt.

## Acceptance criteria and contract

- Preserve measured browser-storage pressure and local-storage errors separately.
- A historical global `ProtocolQuotaError` alone cannot create a current warning.
- Use the existing expiring worker observation channel, canonical account relay
  list and this tab's guarded active destinations. Disabled, removed, device-off,
  unconfirmed and unauthenticated routes must not expose worker observations.
- Name each affected relay and its observed protocol/connection problem. A quota
  rejection may coexist with an open WebSocket. Offline alone does not attribute
  a connection fault to every relay. Missing observations are unknown.
- Distinguish one/some affected enabled relays from all enabled relays reporting a
  problem. Other relays being configured/open/responding never proves full backup.
- Clear the warning when current evidence recovers, expires or loses permission;
  do not clear SDK errors, change preferences or initiate a push to hide it.
- Provide backup/settings links and problem-specific recovery guidance. Quota
  guidance must not suggest deleting invoices or repeatedly forcing a push.
- Preserve EN/SK/ES/DE/CS UI and user guides, keyboard access and wrapping URLs.

| Task | Owner | Exclusive writable files | Evidence |
| --- | --- | --- | --- |
| Runtime/UI and regression | Main | `LocalStorageWarningBanner.vue`, `localStorageWarningBanner.test.ts` | Mounted synthetic component/state regressions |
| Copy and guides | Main | Five locale JSONs and five `custom-evolu-relay.md` guides | Locale parity, reviewed translated actions |
| Contract/state/integration | Main | This document and project coordination records | Scoped diff, checks and review dispositions |
| eFaktura investigation | frontend specialist | None (read-only) | Source/test persistence and snooze findings |
| Independent review | QA and security specialists | None (read-only) | Bounded revision/scope, severity, tests and disposition |

Existing frontend dependencies are reused without installation. Validation uses
synthetic Vitest/jsdom state only; no database, provider or merchant operation.
Run relevant relay regressions, lint, typecheck, locale parity and production build.
Merge, deployment and package release remain separate owner decisions.

## Verified implementation and independent acceptance

The overview now separates browser-storage warnings from relay warnings. It names
current protocol/connection failures only for selected enabled destinations in the
guarded active route set. Partial failures are amber; all-enabled failures are
red. Protocol refusals remain visible on open sockets, and browser-offline alone
does not create per-relay connection failures. Recovery, expired worker evidence,
logout, route removal and suspension remove the corresponding warning. Existing
SDK errors, routing and account settings are never mutated by this presentation.

Main passes 81 tests / four relay suites and 87 tests / two existing eFaktura
suites. Full lint has zero errors / 46 inherited warnings. Typecheck preserves the
110-error ratchet with no new errors; locale parity and whitespace checks pass.
Production build passes with 7,664 modules; existing chunk/annotation notices remain.
Existing 16 changelog fragments validate. A new public fragment needs the eventual
PR URL; no URL or CI result is invented for this unpublished branch.

Independent QA `/root/relay_banner_qa` accepts 86 tests / five suites, including
actual observation-channel expiry/stale-message cases, plus locale/whitespace checks.
Independent security `/root/relay_banner_security` accepts 141 tests / seven suites,
including lifecycle/default-owner/preference guards, plus locale/whitespace checks.
Counts overlap. Neither reviewer edited implementation or accessed real data.
No blocking findings remain in the bounded source scope. Real-browser rendering,
complete backup recovery and the deployed revision are not established by these checks.

Reviewed 12-file source aggregate SHA256 (sorted path + NUL + bytes + NUL):
`0aec946d208ecd08b6808bf14e58c449c2d9fd23d7670769e5d88ff535ed833e`.
Banner SHA256: `bcc31ed323067d28465387e652db4c3c402cdb22171bdaa8ea08a6e04ef060da`.
Test SHA256: `a57bc4c4bda905967bb7598d5ee2aae830f15e8a9dd134a0d0031cbeb793c1b4`.

## Separate eFaktura readiness diagnosis

Read-only frontend reviewer `/root/efaktura_banner_audit` inspects `24b599af`.
The local-first Test updates only the current form; Save persists the connection
verification timestamp. Existing test `efakturaSettingsWizard.test.ts` explicitly
preserves this unsaved-until-save behavior. The form's unsaved indicator excludes
the changed test timestamp, which explains the confusing successful-test experience.
Provider/client/secret edits invalidate the test; receiving alone does not. A
company-prop refresh can replace an unsaved form. No evidence was found that a
successful matching Save drops its supplied timestamp. This does not diagnose the
owner's deployed data if Test followed by successful Save was already performed.

Remind later writes a company-scoped browser-local expiry for 14 days and hides
the current component. It does not change provider/receiving settings or call an
API. Expiry is checked on mount/company change, without a timer or cross-tab
listener. Other devices are unaffected; existing other-tab notices update after
remount. If localStorage is unavailable, hiding lasts only for that component.
The eFaktura component and snooze/test policies remain unchanged in this task.

## Delivery state

Changes remain uncommitted/unpublished in the durable `relay-status-banner`
worktree on `fix/relay-status-banner`. Main checkout and other feature worktrees
are preserved. No PR, new-head CI, merge, deployment or production operation is
claimed. Next allowed step is owner review of the local result, then publication
and fresh-head CI under applicable authorization; merge/deployment remain owner gates.
