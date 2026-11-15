# Clear eFaktura settings for receiving-only companies

Owner-authorized UX correction, 2026-11-15. Base: fetched master
`c418dcc3aac02df78c8e62fcba48927b05ae9163` (merged PR447). The owner has already
reported Live testing of that feature. Larger-original import remains proposed
and is not part of this correction.

The owner subsequently extends this same local branch with compact profile
guidance and consistent Tools navigation. See `INVOICING_LAYOUT_CLARITY.md` for
that combined milestone. All seven files in this editor correction retain their
accepted hashes; its earlier validation and source review remain applicable.

## Problem and acceptance

The receiving-only screen labels both the existing guided connection and its
receiving override as the receiving provider. The shared-connection choice refers
to a sending role that this company cannot use. Clearing it hides the configured
guide and replaces it with empty, less complete fields. This obscures which
connection is active and makes a non-destructive draft change look like data loss.

| Company mode and saved connection | Expected editor |
| --- | --- |
| Receiving-only, shared | One receiving heading and existing full shared-credential guide; no sharing toggle |
| Receiving-only, separate | One receiving heading and complete separate-receiver guide; no sharing toggle |
| Sending and receiving, shared | Clearly named sending and receiving sections; explicit use-sending-connection choice and shared summary |
| Sending and receiving, separate | Sending plus complete receiving setup; explanation that the receiver needs its own credentials |

Rendering or changing the company/mode must not copy credentials, migrate the
stored shared/separate preference, save settings or perform a provider call.
The effective editor retains its existing field/test handlers. Preserve the
server capability check, native validity, pending-test cancellation, company
ownership, submitted-save confirmation and synchronization-marker guards.
Receiving-only step titles must not depend on a hidden outgoing provider.

For an already-saved separate receiver with completely empty receiving fields,
offer an explicit recovery action only if the unchanged earlier shared connection
is available with a saved secret. The action selects that stored connection for
the form; applying it still requires Save. It must not copy or erase credentials,
discard a nonempty separate draft, or trigger network traffic itself. Ordinary
existing separate configurations remain separate without offering this action.

The separate editor must retain provider environment, participant identity,
activation and SAPI account help, connection testing and explicit-save guidance.
Full-mode toggle copy explains the relationship, rather than saying only shared.
Use existing layout tokens, accessible labels/focus and EN/SK/ES/DE/CS parity.
Do not expose implementation details beyond the credentials needed to connect.

## Ownership and review

| Owner | Writable responsibility |
| --- | --- |
| efaktura_settings_design | None; independent design/flow review |
| directional_frontend | CompanyEfakturaSettingsForm.vue, efakturaSettingsWizard.test.ts, five locale JSON files |
| main | This record, five user guides and shared development records; integration and Git publication |
| QA and security | None; independent final source review and disposable synthetic checks |

Design review confirms the duplicate and vague relationship in the current
template and accepts one effective editor plus explicit full-mode copy. Use only
synthetic companies and credentials for screenshots or browser checks; original
owner screenshots are not copied into prompts, fixtures or artifacts.

Validate all four mode/connection combinations, no writes on mount or mode
change, independent credential preservation, explicit recovery followed by Save,
no recovery for a nonempty separate draft, stale asynchronous responses and
submitted-company cleanup. Run existing relevant Vitest suites, lint/type
ratchets, locale parity and production build; perform synthetic desktop/mobile
and keyboard checks where existing browser tooling permits. No dependency
installation, provider operation, backend policy, real account/production data,
merge or deployment.

## Final implementation and reviews

The normal receiving-only screen now has one effective provider/account editor
and no sending/sharing choice. It keeps the stored shared or separate preference
and uses the corresponding existing fields and test handlers. Full mode retains
both clearly named roles, an explicit use-sending-connection checkbox and checked/
unchecked explanations. Separate receiving retains complete environment,
activation, participant and SAPI help. Receiving-only options have a receiving
title independently of a hidden outgoing provider.

The exceptional previous-connection action rechecks saved/current empty separate
settings, absence of any saved separate secret, unchanged available shared
credentials and compatible server capability. It changes only the form preference
through the existing cancellation handler. It requires explicit Save and moves
focus to the visible provider selector, fenced by company and generation. The
save/test/bridge/confirmation implementations remain unchanged.

Independent design reviewer `efaktura_settings_design` confirms the reported
duplicate headings and recommends this four-case flow. Independent QA
`efaktura_remaining` and security `directional_security` accept the final seven
source/test/locale files; no source/review blockers remain. Authors are
`directional_frontend` and main for the final two displayed-URL wrapping classes.
Final aggregate SHA256:
`afe36c2da53365e9d0112bbbdaf108ab67f4e5bae8e541af3cbe4425a15a2bd8`
(sorted sha256sum-style file entries).

Two review observations were corrected:

- The recovery guard now checks the immutable saved separate-secret state as well
  as its mutable display marker. Editing and clearing a URL cannot make a saved
  orphan secret eligible for recovery. Its mounted regression passes.
- QA found a long test-provider URL spilling outside its inner card at 320px,
  despite no document-level overflow. Both displayed URL paragraphs now wrap;
  final browser checks include inner bounds and long custom URLs in both editors.

An inherited shared Save-button contrast observation from the design review
belongs to a separate theme task; no global color or contrast ratchet is changed
here. The new guidance uses existing passing helper/status colors. Keyboard and
responsive checks below are not a claim of complete WCAG certification.

## Validation and visual evidence

Main passes 360 frontend tests in 14 relevant suites on the final source,
full lint with zero errors / 46 inherited warnings, whitespace and production
build (7,669 modules; existing chunk-size warnings). Author passes 183 tests in
four suites including 35 new UX cases, scoped ESLint zero/zero, five-locale parity
and unchanged typecheck ratchet (110 tolerated / no new). Five user guides are
updated with the matching receiving-only/full-mode and explicit recovery flow.

QA independently passes 200 tests in four suites and 36 recorded browser checks,
rendering 16 final synthetic screenshots. The matrix covers all four connection
cases at 320/1280px and 200% zoom, keyboard labels/focus, explicit recovery plus
Save, preserved drafts, all five locales and unsupported server capability.
Security independently passes 183 tests in four suites and verifies the final
freeze. Counts overlap. The final two CSS-only changes are independently checked
against the previously accepted form by removing just those two class tokens;
logic, guards, tests and locale bytes are otherwise identical.

Visual checks use the actual component/locales/repository styles in a temporary
loopback-only fixture with existing system Chrome, temporary profiles and mocked
API/store/Evolu boundaries. There are zero page errors or external/app requests.
Both fixture processes are stopped cleanly. Only synthetic visuals are retained
under `storage/app/private/reviews/efaktura-settings-clarity`; owner clipboard
images and account identifiers are not copied into artifacts. Existing tools and
dependencies are reused without installation. The primary preview is untouched.

No backend/API source or storage contract changes, so PHP tests are not rerun for
this UI-only correction. Graphify baseline queries preceded source reads; the
isolated checkout has no graph and no rebuild is performed.

## Delivery checkpoint

Retain local, uncommitted changes on `fix/efaktura-settings-clarity` in its named
durable worktree over merged PR447 `c418dcc3`. Primary unrelated work and previous
worktrees remain preserved. No commit, push, PR, new-head CI, provider operation
or deployment is claimed. The owner's earlier Live confirmation remains evidence
for the merged PR447 workflow, not this unpublished UI correction. Next: owner
reviews this concrete local result before scoped publication; merge/deployment
remain owner decisions. The larger-original import proposal remains separate.
