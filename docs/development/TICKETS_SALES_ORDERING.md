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
