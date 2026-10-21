# Unified ticket sales and merchant ordering

Status: implementation authorized by the Product Owner on 2026-10-21. Local development only; no merge, publication or deployment is authorized by this milestone.

## Approved scope

Extend existing Satflux event/ticket authoring rather than introduce a new management page. Persist ticket-type ordering in the owned Satflux Tickets plugin and expose it in both existing management lists. Modernize the plugin's public sales page into one overview/selection/contact page, followed by existing hosted BTCPay payment. Keep public URLs and the current physical/virtual event, email and fulfillment contracts. Public location is shown for physical events only; a virtual location may hold private joining instructions and remains confined to the existing confirmation/ticket flow.

Remata was sample content only. All titles, rich descriptions, images, physical/virtual indicators, location, dates, optional end date, currency, capacity, ticket types, redirects, email templates and raffle bundles must continue to derive from saved event data. No accommodation-specific labels, universal free-entry notice, invented programme fields, new required subtitle or stock-reservation promises. Show persisted times explicitly in UTC because the existing event model has no separate timezone field.

Zero-price fulfillment, strict inventory holds, core BTCPay changes and third-party plugin changes are outside this milestone. Existing positive-price checkout semantics remain.

## Repository baselines and ownership

Satflux branch `feat/tickets-sales-ordering` starts at merged master `fe0ace6eeeab4019a557326c4b05166b53e99f59` (PR #396 merged). Owned plugin branch `feature/tickets-sales-ordering` starts at `6bdd68828673c8a75aa744f9f3483b7a251a938d` with the prior uncommitted Tickets 2.0.4 candidate copied as a preserved dependency. The original worktrees and user image edits remain untouched. The plugin feature candidate is 2.1.0; no package is published.

| Task | Owner | Writable scope | Dependency / acceptance |
| --- | --- | --- | --- |
| Provider order | `tickets_ordering_provider` | Ordering entities, migration/snapshot, scoped service/DTOs, native/Greenfield type controllers, existing native list, DI, ordering tests | Atomic event-scoped CAS; all membership, disabled types, stable order; adversarial and database tests |
| Public sales | `tickets_public_sales_impl` | Public MVC controller/view/model, scoped sales helpers, embedded CSS/JS, public tests | Existing URLs and CSRF; generic data-driven layout; canonical server validation and existing payment semantics |
| Satflux management | `tickets_ordering_satflux_ui` | Existing Types tab, Pinia contract, ordering composable/tests, five locale files | Inline accessible drag/buttons, draft/save/cancel, old-provider and conflict recovery |
| Consumer/integration | Main | Laravel proxy/routes/tests, docs, plugin version/resources/swagger, integration and Git state | Scoped merchant key, denied guest/foreign merchant, safe failures, no uncertain retry |
| Security / QA | Independent reviewers after implementation | Read-only final diffs and verified disposable tests | No author self-approval; final revisions, findings and actual test evidence |

## Provider/consumer contract

Canonical provider route (existing legacy `satoshi-tickets` alias retained):

```text
GET /api/v1/stores/{storeId}/satflux-tickets/events/{eventId}/ticket-types/order
PUT /api/v1/stores/{storeId}/satflux-tickets/events/{eventId}/ticket-types/order
```

Snapshot: `{ "ticketTypeIds": ["type-b", "type-a"], "revision": 7 }`.
PUT body: `{ "ticketTypeIds": ["type-a", "type-b"], "expectedRevision": 7 }`.
Satflux proxies through its existing authenticated local-store UUID route and returns the usual `{ "data": snapshot }` wrapper. Neither raw BTCPay store IDs nor privileged credentials are added to browser data.

The complete permutation includes disabled types. Bounds are 1000 unique nonempty string IDs of at most 128 characters and a nonnegative JavaScript-safe integer revision. The provider validates store/event/type scope and exact membership, rejects stale revision with HTTP 409 and commits all rank changes atomically. New/create/delete membership mutations share the event-level lock and revision; new types append. Order-only saves never overwrite sold counters or other type attributes.

`DisplayOrder ASC, Id ASC` is authoritative. Explicit legacy `Name`/`Price` sorts remain available with stable ties. Backfill existing types per event by name then ID; creation order cannot be recovered because it was never stored. The public offer filters disabled types without changing relative positions. Existing attendee assignments retain stable ticket/admission identity when published order changes.

## UI and compatibility

Satflux adds controls in `TicketsShow.vue` inside the existing expanded event's Types tab and preserves current event/type forms. Native BTCPay extends its existing type list. Use an explicit edit mode, drag handle, labelled move-up/down alternatives, Save/Cancel, dirty state and live announcements. Preserve a conflicting draft and offer explicit reload on 409. Older plugin endpoint 404 shows an upgrade requirement without disrupting existing editing; never simulate persistence.

Public sales uses the configured event title and full safe rich description, bounded optional image, generic ticket cards, persistent desktop summary and safe-area-aware mobile action. Contacts reveal inline. Preserve names per admission, grouped email by default and separate recipient emails when requested. Server-side type/state/quantity/price validation and invoice amount calculation remain authoritative; browser totals are only a preview. No buyer information in URLs or persistent browser storage. A durably marked invoice-creation attempt is lookup-only on retry: uncertain absence never authorizes another invoice. If the invoice remains unconfirmed, preserve the order and require operator reconciliation rather than reset the marker automatically.

EN/SK/ES/DE/CS parity, actual document language, locale/currency precision, physical/virtual states, optional fields, light/dark themes, long content, 320px and desktop, focus, keyboard, reduced motion and 44px controls are acceptance requirements.

## Verification and release gates

Use the exact pinned BTCPay 2.4.5 host (`5d0745cae6be5d8210459e38813f317673aa97b8`) and existing SDK/dependency caches. Tests use synthetic data and dedicated local disposable databases only. Laravel uses a newly isolated no-network container, SQLite `:memory:`, array cache/mail, sync queue and no cached configuration. The original repositories, production infrastructure and real wallets are not test targets.

Required evidence: provider migration and CAS/membership/isolation tests; consumer routing, key selection, validation, old-provider, 409, uncertain transport tests; UI draft/keyboard/reorder tests; real rendered sales physical/virtual and contact tests; relevant existing suites, lint, typecheck, locale parity and build; independent security/QA review.

Release sequence after separate approval: review provider and consumer changes separately; install compatible plugin before deploying the consumer's order capability. Preserve old list clients. Roll back UI independently; retain additive columns and saved ordering data rather than run a destructive database downgrade during sales. Verify old binary/schema compatibility before proposing a package rollback. Do not mix old and new plugin workers while new public checkouts are active: older binaries ignore the creation-attempt sentinel and do not share the new retry guarantees. Pending attempted intents require read-only reconciliation and an explicit operator rollback plan; never reset their marker automatically.

## Implementation evidence

Source implementation is complete and accepted by independent security and QA reviewers. Final consumer-wide runner results are recorded below; remote merge evidence does not establish deployment state.

### Local acceptance evidence

- Consumer PHP: `php artisan test --filter='Ticket|DocumentationControllerTest'` passes **77 tests / 241 assertions**, using verified no-network SQLite memory fixtures. This includes 34 order cases / 91 assertions and preserves audited admin/support owner-key access.
- Full PHPStan: PASS without adding baseline allowances. Changed PHP Pint: PASS.
- Frontend specialist: 31 focused cases; independent QA: 20 ordering state/mounted existing-tab cases. Full lint passes with 48 existing warnings; five-locale parity, unchanged 120-diagnostic typecheck ratchet and build pass. Main full-suite runner evidence is recorded below when complete; focused success is not a claim that a full run passed.
- Exact-host plugin Release build: PASS. Full plugin suite: **100/100**, including **15 actual PostgreSQL cases** (8 ordering/migration/concurrency and 7 public recovery/display-guard cases). Prior email/logo tests remain included. All database fixtures are synthetic, local and disposable.
- Native ordering: 13 browser assertions on the actual native script with a synthetic DOM and mocked saves; native action JSON success is also covered against PostgreSQL. This is not a full authenticated production-host browser run.
- Public page: independent QA exercised the actual compiled Razor view and production JS/CSS using a disposable MVC harness and the exact host theme assets; its layout override excludes unrelated host dependencies. Initial 31 browser cases pass, including CSRF, five languages, optional/virtual fields, private virtual location exclusion and 201 listed types with selected-only submission. Final contrast/mobile checks are tracked in the QA handoff.

### Independent security disposition

Reviewer `tickets_sales_security_final` accepts the bounded source changes, independently of their authors. The review traced both repository baselines and the preserved prior 2.0.4 dependency. It found and verified corrections for incorrect invoice-tag prefix recovery, uncertain-creation duplicate risk, missing-invoice-ID settlement recovery, partial QR-link persistence, virtual-location exposure, native redirect/JSON mismatch and frozen-recipient display inconsistency.

The new public checkout validates exact immutable store/order/transaction metadata, tag, canonical host-normalized amount and currency. It durably marks a creation attempt before the external boundary; later uncertainty permits lookup only. A plugin-private reconciliation queue reloads authoritative invoice state and uses the same checkout lock before binding and existing fulfillment. It does not replay global core events or treat unknown payment state as paid. Personalized page responses are private and no-store.

The security reviewer independently ran the latest pure client checks and syntax/diff checks and inspected actual database test logs/source; they did not independently execute stateful database tests. Final critical-file digests are recorded in the review handoff, not inferred from branch HEAD alone.

### Remaining scope and release limitations

- No deployment, production wallet, real invoice payment, production database, CI publication or package release was performed.
- Browser harness success is not full authenticated host staging. Validate plugin startup upgrade with representative disposable data, real hosted checkout cancellation/settlement, configured email/QR delivery and optional raffle integration before release.
- Reconciliation tests verify persisted state/counters and lock ordering; their fixture intentionally omits an event to avoid external email/raffle effects. Existing external-dispatch failure atomicity is not proven exactly once.
- Strict inventory holds, all-zero-price fulfillment and existing late-settlement policy are unchanged.
- The inherited `GreenfieldSatfluxTicketsController.CreateInvoiceForOrder` retains its separate wrong-prefix invoice-recovery issue outside the public-layout scope. Schedule an independently tested API/WordPress purchase recovery fix; this milestone does not establish duplicate safety for all purchase paths.
- Do not mix old and new plugin workers during pending new checkouts. Older binaries do not understand the new attempt sentinel/locking contract. Plugin rollback requires read-only pending-intent reconciliation and an explicit compatibility plan; no automatic marker reset or destructive schema downgrade. Consumer/UI rollback remains separately reviewable.


### Frozen plugin and independent browser acceptance

The frozen 2.1.0 candidate passes 100/100 tests with no skips, including 15 actual local PostgreSQL cases. Build passes with no errors; existing host/transitive warnings remain and no dependency upgrades are included. Exact-host guard passes, and the pinned host source is clean.

Independent QA `tickets_sales_independent_qa` accepts the final compiled Razor view and DLL-embedded production assets: 32 browser checks and 9 visual/contrast scenarios pass, with no browser script errors. It also independently passes 47 pure .NET validation/recovery tests, 20 Vue ordering tests and 5 production-JS cases. Default and navy brand colors, light/dark, mobile/desktop and no-JavaScript fallback are covered. Supporting text contrast is at least 7.32:1 in the tested defaults; CTA text is 7.71:1 on default green and 15.44:1 on navy. Original low-contrast findings are closed with plugin-scoped changes.

The public page preserves one mobile/desktop summary node and recipient drafts. Private virtual location is absent from raw anonymous HTML, links, embedded data and accessible output. A 201-type offer posts selected rows only. A fully sold-out active future event remains viewable, while the initial purchase POST still rejects exhausted capacity and foreign/disabled/past events.

Final DLL SHA256: `a24b3e36f55545b82fa4894df608adc670bd09b92b913a8f1db327127240b2da`.

Independent final security acceptance includes the responsive-node movement and sold-out GET-only guard. Critical 13-file source scope SHA256: `7e229c74008a4896cf4df01b269c8581f7fee1c618282025a20037bf7d2ee567`. The reviewer hashes each ordered relative path plus a space, its raw-byte SHA256 and LF, then hashes the resulting UTF-8 text. The scope comprises the Greenfield/native/public type/sales controllers; ordering/checkout-lock/invoice-recovery/selection-validation/hosted services; public summary/native list views; public JS; ordering DTO; and display-order migration. This does not replace a Git commit or immutable release manifest.

Next gate: repository publication handoff and exact-head CI, under applicable publication instructions. Plugin installation must precede consumer rollout. No installer has been built, and no publication, merge or deployment has occurred.


### Final consumer verification and handoff

The frozen consumer passes the complete Vitest suite: **128 files / 850 tests**, using `npm run test -- --maxWorkers=1 --no-file-parallelism` and unchanged test timeouts/configuration. Initial parallel attempts during combined local builds timed out in unrelated tests; an isolated unchanged passkey test passes, and the final complete serial run passes without application or timeout changes. The runtime's agent reporter intentionally suppresses successful module output until the final summary; quiet output was not a hang.

Final `npm run typecheck` passes with **120 unchanged baseline diagnostics**, `npm run build` passes (existing chunk/import warnings), five-locale parity and the typecheck-gate regression pass. Full lint passed with **0 errors / 48 existing warnings**; changed-file lint had no warnings. Relevant PHP/documentation tests pass **77 / 241 assertions**, full PHPStan passes without expanding its baseline, and changed-file Pint passes. No warning, typecheck or test ratchet was raised.

Main independently recomputed the frozen critical source digest and DLL checksum and verified both original plugin tracked WIP and pinned host source remain unchanged. Root pre-existing image work is untouched. Both feature branches remain local and uncommitted; no remote PR or CI result is claimed for this milestone.

Recommended publication/dependency order: provider plugin `feature/tickets-sales-ordering` (2.1.0 candidate, including preserved prior 2.0.4 dependency), then consumer `feat/tickets-sales-ordering`. Keep separate repository reviews and histories. The provider's additive migration and public layout need representative staging validation before release; the consumer handles an old provider with an explicit upgrade state rather than a false saved-success response.


### Authorized local package and consumer publication handoff

The Product Owner subsequently requested a local installable 2.1.0 package and
commit/push of the Satflux branch. Earlier statements that no installer/publication
had occurred describe the preceding acceptance checkpoint. The scope is local
packaging plus the owned Satflux consumer branch; no plugin commit/push, release
publication, merge, installation or deployment is included.

`dotnet publish --configuration Release --no-build --no-restore` preserves the
reviewed DLL byte-for-byte. The existing PluginPacker from the exact pinned host
creates `BTCPayServer.Plugins.SatfluxTickets.btcpay`, with sidecar manifest version
`2.1.0.0`, standalone identity and `BTCPayServer >=2.4.5` dependency.

- Installer SHA256: `d892b22df02ddbe5970e2faab70489d14f05be30c056ca822b5965077c0b6df3`.
- Sidecar manifest SHA256: `c6e987fda43fb10ae6370ccd4cabe2642758ae521eb4cfb687045cd28efc0ec0`.
- Packaged DLL SHA256: `a24b3e36f55545b82fa4894df608adc670bd09b92b913a8f1db327127240b2da`.

Accepted application source fingerprints remain unchanged. No new `.env` setting
is introduced. Existing SignalR/PKCS transitive security advisories from the pinned
host remain visible in publish output and require separate host/dependency
maintenance; this package does not remediate them or alter BTCPay core.

The consumer push contains only reviewed source/tests/locales/docs. Feature pushes
alone do not run GitHub CI here; a consumer PR must obtain exact-head CI before
merge. Separate provider review and representative staging remain required before
installation/rollout, with the pending-intent rollback limitations above.

Independent delivery QA accepts the final archive: 103 entries / 12,935,025 bytes,
CRC and both sidecar checksums pass, ZIP paths are unique and safe with no symlinks.
The packaged DLL exactly matches the accepted build, migration/type markers and
embedded JS/CSS; no host/other-plugin/test DLLs, configuration, keys or databases
are included. All 20 reviewed source/artifact fingerprints remain unchanged and
the final consumer scope contains exactly 23 files. This checks artifact integrity,
not installation, live payment processing or production behavior.


### 2.1.1 language and time-zone follow-up contract

After the consumer was pushed at `a9be6a17e63f6070b4145b3509070bd8466ee900`,
the Product Owner reported that the installed 2.1.0 candidate works, but public
copy remains English and event times are displayed in UTC instead of local time.
This is operator feedback, not independently verified production behavior.

Repository evidence confirms the pinned host's `SetCultureMiddleware` resets both
current cultures to invariant; using that UI culture always selects the English
public-sales dictionary. The plugin also explicitly renders UTC and never converts
its `<time>` values. The event schema has no venue time-zone field.

Bounded fix: select EN/SK/ES/DE/CS independently from explicit allowlisted language
and weighted browser preferences; provide a visible language switch preserving
checkout drafts. A separate CSRF-protected presentation-only action may re-render
incomplete drafts without creating orders/invoices or changing frozen checkout
intent. Continue preserving chosen language across the existing flow. Render UTC
instants in the visitor's browser zone through Intl, explicitly label the zone, and
retain an accurate UTC fallback without JavaScript/Intl. Do not assume the visitor
is in the venue zone, hardcode CET, alter stored event dates or change API payloads.

Acceptance includes `2026-11-28T08:00:00Z` -> 09:00 in Europe/Bratislava, summer
DST, non-European zones, five-locale copy/date/number formatting, invalid and
quality-zero language preferences, no-JavaScript fallback, draft-preserving change
and existing checkout/CSRF/privacy regressions. Independent security and QA review
are required for this bounded delta. Candidate version is 2.1.1; the old 2.1.0
installer remains immutable. No core/third-party/production change or plugin source
publication is authorized by this local correction.


### Final 2.1.1 acceptance and local package - 2026-10-22

Independent security accepts the final eight-file presentation scope. Main
recomputed its raw-byte/path-ordered SHA256:
`7db281678fef1f6e1c48332746977e663f610a0cb8482a5e5620c84b9d790497`.
Paths, in order from plugin repository root: `Services/TicketSalesText.cs`,
`ViewModels/Models/TicketSalesPageViewModel.cs`,
`Controllers/UITicketSalesPublicController.cs`,
`Views/UITicketSalesPublic/EventSummary.cshtml`, `Resources/js/ticket-sales.js`,
`Resources/css/ticket-sales.css` (all prefixed
`Plugins/BTCPayServer.Plugins.SatfluxTickets/`), followed by
`Plugins/BTCPayServer.Plugins.SatfluxTickets.Tests/TicketSalesPresentationTests.cs`
and `Plugins/BTCPayServer.Plugins.SatfluxTickets.Tests/TicketSalesClientTests.cjs`.
For each ordered path, concatenate path, ASCII space, lowercase raw-byte SHA256
and LF, then SHA256 the resulting UTF-8 text. Version/changelog/README and consumer
documentation are outside this presentation digest.

Final exact-host Release build passes with zero errors and 19 existing warnings.
The final rebuilt DLL passes **108/108 non-database .NET tests, zero skips**,
including 23 new presentation cases. Run with `--no-build --no-restore -c Release`
and filter `FullyQualifiedName!~TicketTypeOrderDatabaseTests&FullyQualifiedName!~TicketSalesDatabaseTests`,
with `TICKETS_ORDER_TEST_CONNECTION` unset. All **7 production-JS cases** pass.
The unchanged 15 PostgreSQL cases remain preceding milestone evidence and were
not rerun. No application warning/test allowance or dependency was changed.

Independent QA reproduces the original issue offline against 2.1.0. Its new
**39 browser checks** and **32 existing regressions** pass: browser/explicit language,
quality-zero/invalid/bounded preferences, all five languages and dynamic contacts,
Bratislava winter/summer, New York/UTC/Tokyo, both DST transitions, immutable UTC
attributes, no-JavaScript/Intl-failure fallbacks and incomplete draft preservation.
A new secondary-button contrast finding (2.72:1 on white when focused) is corrected
with plugin-scoped static colors. The final rebuilt embedded CSS passes **six
light/dark viewport combinations** for default, hover, keyboard focus and active
states, associated labels and 44px targets, plus final draft/09:00 local-time smoke.
All 106 frozen functional source/project files remain unchanged across the final
CSS-only rebuild. Text contrast is above 14:1 in the corrected button.

Harness limits remain explicit: the actual compiled Razor, embedded assets and
production locale helper run locally with synthetic fixtures. The valid-session
language POST echo is synthetic; real controller session rejection/CSRF/limits are
covered by tests and independent source tracing. This is not a full deployed-host
valid-session integration test, real payment/email test or production verification.
The picker does not change hosted BTCPay invoice language.

Final installer `BTCPayServer.Plugins.SatfluxTickets.btcpay`, manifest 2.1.1.0:

- Archive SHA256: `3c173ddf1257943744b6d7a411b2d4e516b93b26580d90bf558b7211d33293b9`.
- Sidecar manifest SHA256: `0cc6fcb60fb43897ee7f632475db1569a844fafef0fa6f4f1cc8003c846e4430`.
- Packaged/tested DLL SHA256: `2e4d27ee3c9aab3bab750e8c5dfcfc70c73f6aaf3e260976bc6277cdd888bcc9`.
- Embedded JS SHA256: `872b8695e15792f921ebee55414e80b64a0652ab92e1b5bbd874ac4d97a2b709`.
- Embedded CSS SHA256: `c8191577495f5af0860b366401ae7de9cbc391a2d73a52d4d66330134692c5f4`.

No deployment, installation, merge or plugin source publication occurred. Earlier
2.1.0 and pre-contrast local candidates are preserved as evidence; only the final
archive hash above identifies the delivered candidate. Original plugin WIP and
BTCPay core remain unchanged. Existing SignalR/PKCS host/transitive warnings and
pending-intent rollback constraints from 2.1.0 still apply.

Independent final package QA accepts the delivered archive: 103 entries /
12,938,815 bytes; CRC, safe unique paths, both sidecar checksums, standalone identity
and BTCPay >=2.4.5 requirement pass. Packaged DLL and embedded assets exactly match
the final browser-tested build. No host/other-plugin/test assemblies, configuration,
keys or database artifacts are bundled; file set matches 2.1.0. The old installer
is unchanged. This closes local package acceptance, not staging or production gates.


### 2.1.2 follow-up contract - 2026-10-22

The Product Owner reports that explicit URL language works in 2.1.1, but the
manual button and local-time enhancement do not, and requests automatic header
selection. This supersedes the previous POST-based primary language interaction.
Do not reinterpret the stored 08:00 UTC instant: 09:00 CET in the Satflux browser
is the same instant for the reported winter event.

Source review confirms identical unversioned asset URLs across 2.1.0/2.1.1 and no
explicit asset cache policy. Stale 2.1.0 JS would explain both symptoms, but the
operator's actual asset bytes/cache headers are not available. Pinned default CSP
permits same-origin external scripts and inert JSON; no nonce failure is confirmed.
Previous QA used a layout override, so it did not establish complete host layout,
CSP or cache integration. New QA must reproduce the real pinned layout and CSP.

Accepted scope: a header select switches EN/SK/ES/DE/CS immediately using constant
public copy and text/attribute updates, preserving buyer DOM/drafts and nonce.
Update dates/money, dynamic contacts, accessible labels, document/form language;
never translate merchant-authored content automatically. Use versioned embedded
JS/CSS paths plus explicit cache behaviour, retaining legacy routes safely. No CSP
weakening, storage/API date changes, core changes or new payment operations.

Main owns version 2.1.2, documentation/build/package and publication boundaries.
The plugin specialist owns the bounded text/controller-asset/view/JS/CSS/test
delta; security reviews input/DOM/asset isolation read-only; independent QA owns
synthetic full-layout/CSP/cache reproduction and final browser/artifact acceptance.
Preserve preceding installers and uncommitted work. No deployment, plugin source
publication or production change is authorized by this local correction.


### Final 2.1.2 local acceptance - 2026-10-22

Operator feedback identifies Brave, working quantity buttons and an empty console.
This is consistent with the stale-script reproduction, but the actual operator
response bytes/cache source remain unknown. No browser Shields, proxy, production
cache or stored dates were modified. Satflux `TicketsShow.vue` uses browser-local
Intl formatting and UTC ISO serialization; 08:00 UTC and 09:00 CET are correct
representations of the same reported winter instant.

Independent security accepts the frozen eight-file presentation scope, using the
previously documented paths/order/algorithm:
`a82819c1b2b71127e6bd8eaa3ded0c281311571b830d9642eaee3a32a06c81d6`.
Main recomputed the same fingerprint. All public-controller changes versus 2.1.1
are asset actions; payment/session/reconciliation actions and shared layout are
unchanged. New constant translation data uses default-escaped inert JSON and
allowlisted own-key/string validation; updates use textContent/fixed aria labels,
not HTML insertion. Header switching performs no requests and retains input DOM,
delivery mode, incomplete values, CSRF token, nonce and frozen checkout href.

Exact-host Release build passes with zero errors and 19 existing warnings. Final
**115/115 non-database .NET tests pass, zero skips**, with the existing explicit
PostgreSQL class exclusions and no test connection variable. **10/10 production-JS
cases pass**; security independently reruns these and syntax/diff checks. The 15
PostgreSQL cases remain previous milestone evidence, not a rerun.

Independent QA accepts **26/26 focused browser cases** using the unchanged actual
compiled plugin/shared host layouts, pinned host CSP/taghelpers/antiforgery/static
assets and real production asset methods. The harness reflects production route
prefixes/templates/parameter shapes at startup, avoiding the old URL mapping gap.
Cases cover all three aliases with/without a path base; HTTP asset bytes/hashes and
cache policies; invalid versions; replayed stale legacy JS bypass; five immediate
languages with zero requests and retained invalid/complete drafts; DST, UTC/New
York/Bratislava; mobile light/dark 320/390 and 44px/no overflow; readonly pending
and known-checkout states; fallback/private virtual location; runtime startup
exceptions and synthetic form submission.

Pinned default CSP produces no app violations. A separate stricter test blocking
all inline scripts preserves plugin operation; expected blocked host inline code
is not reported as an app defect. Initial failing QA assertions were synthetic
locale/header mismatch and broad host noscript/absent-anchor selectors, corrected
without application changes; the final bounded cases pass.

Harness boundaries: page DTOs and unrelated DI are synthetic, production resource
methods are delegated after route-contract checks, and submission endpoints are
synthetic. This is not a full BTCPay startup, real invoice/email/DB-session test,
Brave binary test or production cache verification. Startup diagnostics handle
exceptions after the script executes; they cannot reveal an external script that
never loads. Explicit UTC/no-JavaScript fallback remains accurate.

Final local standalone installer version 2.1.2.0, BTCPay >=2.4.5:

- Archive SHA256: `58dabe1628d9350f87ca8f9eaa05f0e1c7c91efe6d27ead7a8f486374dbbb207`.
- Sidecar manifest SHA256: `72c34f324ef41e4712ac030c6ea1bef8e173c1917f3c530b4ce97d1934a1d304`.
- Tested/browser/packaged DLL SHA256: `06e3f19ddaa3592524a2bd99496e8816e873a1f921dc187a33abcd64212e2a65`.
- Embedded JS SHA256: `58dd430d763fd2c270c7155e2e22818a4592663b20303390f431189026ba1577`.
- Embedded CSS SHA256: `2e33e0140ae241a9b3d486501661bdf357833f82da5dade9ee0080417523058e`.

Independent package QA accepts all 103 entries / 12,941,682 bytes, CRC/safe paths,
sidecar checksums/manifest, exact embedded bytes and unchanged file set. No host,
other-plugin, test, configuration, key or database payload is included. The prior
2.1.1 archive and original WIP fingerprint remain unchanged. Source changes remain
local/uncommitted; no merge, publication, production access or deployment occurred.
Existing host dependency advisories and separate staging/release gates remain.

Final independent host-filter CSRF check also passes: a synthetic submit without
a token returns 400; a valid synthetic token returns 200. This is additional to
the 26 browser cases and does not invoke real invoice/session/database operations.
The local harness is stopped; no application or package changes followed review.

## Tickets 2.2.0: venue time zones (2026-10-22)

The Product Owner corrected the earlier visitor-time design: physical events must
show the venue's local time independently of the visitor's browser, VPN or location;
virtual events retain visitor time. The owner explicitly confirmed that **all
existing physical events belong to Europe/Bratislava**. This supersedes the earlier
UTC-only/visitor-time presentation decisions above; their evidence remains historical.
Implementation and local installer preparation are authorized. Production migrations,
installation, deployment and merges have not been performed or authorized here.

### Acceptance and integration contract

- Preserve UTC instants, precision, reminders, sale cutoff and payment/fulfillment
  semantics. Add nullable `Event.TimeZone` (IANA, at most 100 characters).
- Migration `20261022120000_EventVenueTimeZone` adds metadata and backfills only
  existing Physical rows with a null zone to `Europe/Bratislava`; it never changes
  dates, reminders or virtual rows. Future old-client physical creates without a
  zone remain unknown and render clearly labelled UTC. No inferred future venue.
- Provider 2.2.0 owns an immutable bounded IANA catalog and civil-to-UTC conversion.
  Authenticated store-scoped `GET .../satflux-tickets/time-zones` returns
  `{timeZones: string[]}`. Satflux proxies through its existing local-store UUID
  authorization boundary as `{data:{timeZones:string[]}}`, using the owner's key.
- Existing `startDate` / `endDate` are UTC or explicit-offset instants. New
  `startLocal` / `endLocal` are civil `YYYY-MM-DDTHH:mm[:ss]` values with explicit
  `timeZone`. A date cannot be supplied as both instant and civil. The provider
  rejects invalid zones, DST gaps and ambiguous civil hours with safe field-specific
  HTTP 422 codes. This milestone does not add an offset-choice UI; existing explicit
  instant API values still represent both occurrences of an overlapping hour.
- PUT becomes safely partial. Omitted dates preserve the exact stored instant.
  Omitted or null `timeZone` preserves metadata; empty strings fail validation.
  Explicit `endLocal:null` clears the end; omission preserves it. Clean zone/type-only
  edits never reschedule an event. Date fields are sent only when explicitly edited.
- Physical public HTML is formatted server-side in the venue zone, with the actual
  date-specific UTC offset and IANA label. All five localized strings are precomputed;
  the client uses them verbatim. No browser timezone database can alter them. No-JS
  output is already correct. Virtual public output continues visitor formatting with
  a truthful UTC fallback and never publishes private authoring zone or virtual location.
- Extend existing Satflux and native BTCPay event forms; no new page. Physical civil
  inputs avoid browser-local inverse conversion. Provider errors preserve the draft.
  Native virtual browser aliases Calcutta/Kiev/Katmandu normalize only to equivalent
  catalog-supported Kolkata/Kyiv/Kathmandu; an unknown zone has a visible UTC fallback.
- Issued physical tickets use venue time. The existing email `{{EventDate}}` remains
  a date-only token, using the physical venue calendar date. QR, recipient grouping,
  email delivery and invoice state handling remain unchanged.

### Ownership and review gates

| Scope | Owner | Files / acceptance |
| --- | --- | --- |
| Provider | `tickets_locale_timezone_fix` | Event entity/DTOs, central time service, migration/snapshot, API/native/public/issued-ticket views and scripts, date-only email mapping, Swagger and tests; no core/dependency changes |
| Consumer frontend | `tickets_ordering_satflux_ui` | Existing TicketsShow form, Pinia, scoped time helper, five locales and focused tests; no shared DatePicker changes or inverse physical conversion |
| Consumer backend / integration | Main | Store-scoped proxy/routes/time validation, safe capability/errors, PHP tests, metadata/docs/build/package; preserve prior WIP |
| Independent security | `tickets_sales_security_final` | Read-only authorization, catalog bounds, partial-write/migration/privacy/security review |
| Independent QA | `tickets_sales_independent_qa` | Disposable synthetic browser/host/native/migration tests; actual timezone/offset oracles and exact artifact identity |

### Rollout and rollback

Install and verify provider 2.2.0 before deploying the dependent consumer. An older
provider's missing catalog fails visibly and stops new-field writes; it cannot silently
ignore a venue selection. No environment variable or production timezone change is
required. The owner-approved backfill runs only through the normal provider migration
lifecycle when an operator installs the package; it was tested locally, not applied to
production. Back up the plugin database before the separately approved installation.

Roll back consumer UI independently. Retain additive metadata during binary rollback;
do not drop the timezone column or rewrite UTC dates. An older public binary may
reintroduce visitor-time display for physical events. Preserve the prior invoice-attempt
sentinel safeguards and avoid mixed old/new workers as documented above. No rollback
operation may reset payment markers, wallets or existing orders.

Verification and final independent review results are recorded after the source freeze
and exact-host runs; pending checks are not acceptance.

### 2.2.0 local verification and candidate identity

The current-source provider fingerprint covers 150 source/resource/test files:
`badc4049fe7e61b58123959bf646f935dead5196f87dcd83aa1f92ebde2a5871`.
The consumer fingerprint covers 18 changed application/test/locale files:
`109d999dac3bedcdba9c5a3c13da9438e8221ed5d14654e04103c1449dffd9b5`.
Algorithm: sort repository-relative paths; SHA256 the UTF-8 concatenation of
`path + space + raw-file-SHA256 + LF`. Documentation is excluded. The earlier 2.1.2
manifest preserves six presentation/resource hashes, not a complete prior source
snapshot; reviews attest the bounded current timezone paths and previously accepted
context rather than an exact complete 2.1.2 source-to-source reconstruction.

- Exact pinned-host Release build passes: 19 existing warnings, zero errors. Existing
  host dependency advisories remain outside this scoped change; no dependency/core
  source or baseline change was made.
- Full plugin suite passes **165/165, zero skipped**, including 20 actual PostgreSQL
  cases. A clean disposable database exposed concurrent test-fixture migrations;
  serialize the three shared-database collections without removing their explicit
  operation-concurrency tests. The initial failure evidence was preserved separately;
  acceptance is the clean rerun from another fresh database.
- Related consumer PHP tests pass **115 tests / 343 assertions**, including 38 new
  timezone cases. SQLite memory, network disabled, merchant-key HTTP fakes only.
  Full PHPStan and changed-file Pint pass without baseline additions.
- Frontend author passes 60 focused tests under America/New_York, changed-file lint,
  five-locale parity, full typecheck with 120 unchanged baselined diagnostics and the
  final production build. Independent QA additionally passes 29 focused cases and 15
  independent venue/sparse-write cases. These are focused results, not a newly claimed
  full application-suite run.
- Provider pure client tests pass 13/13; actual native ordering script passes 13 Chrome
  assertions against a synthetic DOM. No new runtime dependency was installed.

The local installer is **2.2.0.0**, 103 archive entries / 12,962,544 bytes.
Archive SHA256:
`83753a96a2147c9f7202b006d6c243e9ad195f4d16ac8cc408935de2be5bfc8d`.
Packaged DLL matches the tested Release DLL:
`d7995cb102675ccfdbad98d0b9c175ffd9cd557090bb559b757cd0cfb32e8f9d`.
Sidecar SHA256:
`fd006a8eb10ed4d3edf179bdd0cc5f5ccbc73c9f139b255962a2e6d809bed87e`.
The manifest requires BTCPay >=2.4.5. Older installers remain immutable. This is a
locally prepared artifact, not a published release or verified production installation.
Independent final compiled browser/upgrade evidence and dispositions follow below.

### Store-default clarification and final follow-up

The owner approves **store default + per-event override**. Satflux may preselect a
new physical event's zone from the matching local Store only after the provider catalog
validates it and before any user interaction. Existing events keep their saved zone;
store changes never rewrite their metadata or instants. Unknown defaults remain an
explicit selection, and an asynchronous response must not overwrite a user's choice
or cross stores. Native BTCPay creation still requires a venue selection: its pinned
standard Store API has no persisted timezone field, and this milestone adds no bridge.

`StoreSettingsController` previously ignored the local timezone on reload and defaulted
to UTC when BTCPay omitted the field. A later normal Save could overwrite the local
choice. Prefer local data consistently with `StoreResponseFormatter`; regression tests
use the actual pinned response shape without a fabricated upstream timezone. Existing
local values already overwritten to UTC cannot be reconstructed from source alone;
no automatic store-data repair or provisioning-default change is attempted.

The initial 2.2.0 candidate above is retained as baseline evidence. Its independent
QA passes 31 actual compiled public/native/issued browser cases, five grouped real
prior-to-latest EF/API/email cases, and archive identity checks. A follow-up adds the
approved Store default/read correction and detects stale native schedule baselines
before conversion, upload or save. Final acceptance/artifact identity below supersede
the initial candidate; do not install the earlier local candidate as the final result.

### Final follow-up verification (supersedes initial candidate)

Final frozen source fingerprints use the same documented path/space/hash/LF algorithm:
provider 150 files `1b2a1fe36e6c5fb425ea3e5c18018e8be93d6500ccb5ef7742bd423d623c9cb4`;
consumer 20 files `0efce0679cc1e1b9269c7b993c8be2cc8ba0b5476eff835c1a3815f3303c124e`.
Independent security recomputed all per-file hashes. The native baseline now includes
start/end UTC ticks **and original event type/venue zone**, covering the no-JavaScript
same-instant concurrent-zone scenario as well as concurrent date edits. This detects
changed schedule context observed at update load, not general event-metadata CAS.

- Final exact-host Release build passes: 19 existing warnings, zero errors.
- Final full provider suite: **172/172 passed, zero skipped**, including 21 PostgreSQL
  cases with five native stale-form scenarios in one actual-controller database case.
- Final consumer PHP: **125 tests / 386 assertions** for StoreSettings, Tickets and
  documentation; full PHPStan and changed-file Pint pass. Tests use isolated no-network
  SQLite and stubbed merchant-key HTTP; production state was not inspected.
- Final focused frontend: **74/74** under America/New_York; independent QA reruns 43
  Store/default editor cases. Final changed-file lint has zero warnings/errors; the
  earlier whole-project lint retained 48 existing warnings. Five-locale JSON parity
  remains unchanged and passed earlier in this source milestone. Root-owned final
  typecheck passes with 120 unchanged diagnostics and production build passes (2m47s).
  Incomplete agent-background build/typecheck attempts were not counted as results.
- Independent security reruns the 13 pure provider client cases successfully. The
  earlier 13 actual native-order browser assertions remain valid for unchanged code.

Final local installer: **2.2.0.0**, 103 entries / 12,964,595 bytes.
Archive SHA256 `302e206578c213b4740637df34ce8f4f5a3d96503f901e5531171251eedf82b3`.
Packaged/tested DLL SHA256 `15fbf88ce04543226540be0fe1347d8ece46cf871223eaf7a50702856c726c0c`.
Sidecar SHA256 `fd006a8eb10ed4d3edf179bdd0cc5f5ccbc73c9f139b255962a2e6d809bed87e`.
The earlier 83753a96 candidate and all prior installers remain unchanged. The final
artifact is local; no package publication, source push, merge, deployment or production
migration is claimed. Final independent browser/guard/archive disposition follows.

### Independent final disposition

Security reviewer `tickets_sales_security_final` accepts the bounded final source and
independently verifies both source manifests, owner-key/tenant boundaries, catalog
bounds, privacy, metadata-only migration and closure of both native schedule race
reproductions. Store baselines are concurrency hints, never an authorization mechanism.
Existing full-form metadata editing still has no general compare-and-swap contract;
this milestone does not claim one.

QA reviewer `tickets_sales_independent_qa` verifies the **actual final DLL and archive**:
8 native-controller database guard cases pass (including same-instant no-JS zone/type
races and missing/invalid anchors); 8 final compiled browser smoke cases pass; exact
archive/DLL/embedded-resource identity, guard fields, safe archive entries and manifest
all pass. The unchanged baseline separately records 31 compiled public/native/issued
browser cases, 5 real previous-to-latest EF/API/email groups and the independent
frontend venue/default cases. These are distinct layers, not a combined unique test
count. The final Store-default focused independent frontend rerun passes 43 cases.

Browser checks use local Chrome with the actual compiled views/resources and pinned
host layout/CSP/CSRF through a synthetic disposable harness. Native test DI/component
lookup is supplied by the harness; authenticated production sessions, Brave and real
invoices/funds were not exercised. No production cache provenance, installation or
live database state is claimed. Production acceptance remains: separately approve
provider-first installation and the consumer release, verify the configured venue/time
and Store settings reload in the deployed UI, retain backups and avoid destructive
schema rollback. Source publication, merge and deployment remain separate operations.

### PR #397 CodeRabbit error-classification follow-up

CodeRabbit reviews published head e8333505 and posts four minor findings: one confirmed
write/capability error-classification defect and three prose/spacing corrections. Keep
catalog validation in a separate safe catch. Map create/update write failures only when
the payload includes new time fields and the provider code exactly matches the existing
shared event-time code/field allowlist. Unknown, missing and prefix-lookalike codes keep
the inherited exception path; legacy UTC-only writes remain unchanged. No new provider,
frontend, retry, credential, tenant, payment or production behavior is introduced.

The old source reproduces ten failing regression cases. Final related PHP scope passes
152 tests / 513 assertions; independent QA passes all 65 focused timezone cases / 229
assertions. Full PHPStan, changed-file Pint and diff checks pass. Independent security
accepts the bounded three-file code/test fingerprint
`a58189fb91208e0d63b47e022ad88a47fd00f4ce8b635896a12b9453b88ea978`
using the existing sorted path/space/hash/LF algorithm. No additional plugin build is
needed because provider source and the installer are unchanged.

Response privacy is tested with debug=false: unrelated write errors retain generic
HTTP 500 responses without private upstream details. This narrow change does not
claim production-log redaction or change global debug-mode handling. CodeRabbit's
initial docstring-coverage warning (23.91% vs 80%) is advisory; its GitHub status and
PHP/Node/Playwright CI are successful at e8333505. Fresh published-head CI/review are
required after this follow-up; no merge or deployment is authorized.
