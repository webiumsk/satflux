# Compact profile guidance and consistent Tools navigation

Owner-authorized extension of the local `fix/efaktura-settings-clarity` branch,
2026-11-15, over merged PR447 `c418dcc3`. Existing unpublished eFaktura editor
corrections remain part of this branch. The owner's earlier Live confirmation
applies to merged PR447, not these local changes.

## Acceptance and integration contract

- On the company profile, Getting started and Slovak eFaktura guidance start as
  compact disclosures with their title and progress or unverified status visible.
  Opening a disclosure exposes its existing checklist and dismiss/snooze action.
  Native keyboard operation and visible focus must work.
- On wide screens, the visible guidance cards share one row and fill its width.
  A single visible card fills the row; no visible cards leave no empty gap.
  Narrow screens stack them without horizontal overflow. Company sharing stays
  below at full width. Company changes reset expansion without new persistence.
- Existing invoice-list guidance, per-company dismissal/snooze, asynchronous
  response guards, readiness criteria and warnings retain their behavior.
- VAT report, audit and accountant export render the existing main menu directly
  above the Tools submenu, without an empty filter bar. Their in-page period,
  export and other actions remain available. Real filter-bearing pages retain
  their filters.
- The WooCommerce application settings page renders the same Tools submenu as
  its application-settings siblings, with Application active and company-scoped
  links preserved. This changes navigation only, not WooCommerce integration.

No backend, data migration, authorization, provider, billing or external
repository changes. Existing EN/SK/ES/DE/CS parity is required. Use synthetic
fixtures only; owner screenshots and account data are not copied into artifacts.

## Ownership and validation

`compact_onboarding` owns the two guidance components, their focused tests and
any necessary additive locale keys. `directional_frontend` owns the three report
pages, shared navigation classification and focused navigation tests. Main owns
CompanyShow integration, the page-shell route inclusion and documentation.
Independent QA and security are read-only reviewers. No overlapping writes,
specialist Git publication, new tools, production operations or deployment.

Validate disclosure/visibility/company-switch behavior and navigation using
focused existing tests. Check desktop/mobile geometry, keyboard operation,
zero/one/two visible cards, hidden filter bars, preserved report controls and the
WooCommerce active submenu in an isolated browser fixture. Run frontend lint,
typecheck ratchet, locale parity and production build on the integrated source.
Graphify query-first ran against the primary graph; the isolated worktree has
no graph and none is installed or rebuilt.

## Delivery evidence

Both guidance components expose an optional `compact` prop, false by default.
The profile opts into native `details`/`summary`; other callers keep the existing
expanded presentation. Progress and the explicit unverified eFaktura title stay
visible in the summary. Existing checklist, disclaimer and dismiss/snooze actions
remain inside the disclosure, without interactive elements nested in its summary.
Company-keyed roots reset expansion without new persistent state.

Direct visible flex children share the profile row; hidden components render no
element, and `empty:hidden` removes an empty wrapper. Sharing remains outside
this row. The navigation fix changes only three explicit header props and two
exact WooCommerce route-list entries, preserving existing report actions.

A company-switch regression exposed an inherited readiness defect: initialization
cleared coverage, but switching between two eligible, unsnoozed companies did
not rerun the eligibility/snooze watcher. Adding companyId to its sources fixes
that reload while retaining generation guards. Two new tests fail before this
one-source correction and pass afterward. Navigation regressions reproduce five
failures before their correction and pass afterward.

Final combined source/test/locale freeze: 19 files over `c418dcc3`, aggregate
`28e1d944f3b711f361836595c2d93303bd5a4f8fd271d5163390f4424c9f7e10`
(sorted sha256sum-style entries). All seven files from the earlier editor-clarity
freeze remain byte-identical; its independent acceptance remains applicable.

Main passes 394 frontend tests in 19 relevant suites, locale parity, whitespace
and production build (7,669 modules; inherited chunk-size warnings). Authors
pass 25 guidance tests in two suites and 21 navigation/report tests in four;
full lint is zero errors / 46 inherited warnings, and the typecheck ratchet
tolerates 110 inherited diagnostics with nothing new. No baseline is changed.
Counts overlap. All five user guides describe the compact profile presentation.
No PHP source or backend contract changes, so PHP tests are not rerun.

Independent security reviewer `directional_security` accepts the final freeze
and passes 40 tests across four focused guidance/navigation/report suites.
No credential, save, authorization, tenant, custody or payment contract changes
are found. Source acceptance is separate from browser and deployment evidence.
Independent QA reviewer `efaktura_remaining` accepts the final freeze, passes
44 tests in five focused suites and 62 recorded browser checks, and renders
28 synthetic PNGs. Coverage includes actual profile zero/one/two-card geometry,
full-width sharing and no empty wrapper margin, native keyboard/focus/hidden
content, per-company dismissal/snooze, same-mode switch/refetch/reset and links,
the expanded invoice-list variant, all three report controls and adjacent menus,
WooCommerce Application navigation, German at 320px and 200% browser zoom.
Main also inspects collapsed/expanded desktop and mobile profile, report,
WooCommerce and German-focus previews; no further source changes are needed.

The isolated fixture uses actual page/card/header/submenu/sharing presentation,
repository styles and locales, with nonvisual API/store/Evolu/account boundaries
mocked. Existing system Chrome uses disposable profiles; nonlocal and app requests
are blocked. Final runs record no page errors or external/app requests. This
checks the scoped presentation, not a complete authenticated-app end-to-end flow
or multi-browser accessibility certification. Private evidence is retained in
`storage/app/private/reviews/invoicing-layout-clarity`; owner images and account
data are excluded. No tooling is installed and primary previews remain untouched.
All three fixture process generations are stopped cleanly before handoff.

Changes remain local and uncommitted in the retained worktree. No commit, push,
PR, new-head CI, provider operation or deployment is claimed for this branch.
