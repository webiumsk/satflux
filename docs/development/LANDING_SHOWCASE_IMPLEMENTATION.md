# Landing showcase implementation

Status: implementation independently accepted locally on 2026-11-05; source publication authorized on 2026-11-06. Merge and deployment remain separate owner gates.
Baseline: `e584490d` (current owned `origin/master`); separate durable worktree preserves unrelated primary-checkout changes.
Design input: owner-approved local `homepage-concept-v6.html` and `LANDING_PAGE_V6_PROPOSAL.md`.

## Publication handoff

The owner requests pushing this reviewed implementation. Publication contains exactly
82 paths (runtime70, source/tests77 and five development documents). Main verifies
the frozen manifests before staging, commits the reviewed scope and verifies the
owned remote branch afterward. Local test/review evidence below remains applicable;
no fresh CI, semantic bot review, merge or deployment is inferred. Implementation
acceptance sections below describe the preceding local milestone.


Publication-only normalization removes two trailing spaces from one blank CSS
line. The original accepted manifests above remain review evidence; the new
publication runtime/source manifest digests are `9a2b9ca0df03803a88c74bf459fe91f7b7879dd4c11c4d0ec2f129f59d0ae7ec` / `3e0eac6e7d64b8f61ea02129cc4a62e01e52b8e38f50cd8750ee1d8ac00883a6`.
All other source/test bytes are unchanged; CSS semantic structure is unchanged.
No product tests are repeated for this whitespace-only difference.

## Accepted scope and contracts

- Keep the existing homepage and production headers. Add the approved V6 showcase with genuine synthetic application screenshots, responsive layouts, accessible dialogs, eight tool explanations and EN/SK/ES/DE/CS copy.
- `HOMEPAGE_VARIANT=legacy|showcase` is runtime Laravel configuration. Missing/invalid values resolve to `legacy`. No admin settings, public query override, extra homepage URL or billing change.
- One server resolver supplies the escaped `data-homepage-variant` in both SPA document templates and chooses a matching public first-paint hero. Both Vue routers use the same document-stable selector; no asynchronous variant API. Refresh configuration and reload the document to switch or roll back without an asset rebuild.
- Preserve canonical metadata, auth boundaries, existing pricing/promotion data and header anchors/feature destinations. Keep the existing landing implementation intact.
- Move the desktop application footer into designated primary scroll hosts after content. Preserve header, banner, store toolbar/sidebar and mobile navigation; no duplicate nested/embedded footer or primary scrollbar.
- Landing uses the existing Vue Router application boundary. Whole-app Inertia migration, custom shared-prop registration and SSR infrastructure are separate proposals.

## Ownership and review gates

| Task | Role | Writable ownership | Dependencies / required evidence |
| --- | --- | --- | --- |
| Resolver, Blade bootstrap/shell, shared Vue entry, router integration, PHP/selector tests, documentation | Main | `config/marketing.php`, `app/Support/{HomepageVariant,HomepageDemoUrl}.php`, `resources/views/{public,app}.blade.php`, new showcase shell, `resources/js/pages/LandingEntry.vue`, `resources/js/services/homepageVariant.ts`, both router entry files, `.env.example`, focused resolver/bootstrap tests, development docs | Production headers and legacy landing remain unchanged; both entry paths and fallback/cache contracts verified |
| Showcase implementation | Frontend specialist | New `LandingShowcase.vue`, `components/landing/showcase/**`, `content/landingShowcase/**`, five locale JSON files, new showcase stylesheet, `public/img/landing-showcase/**`, focused showcase tests | V6 design input, existing pricing/auth components, copy parity, dialogs/focus, screenshot provenance, responsive evidence |
| Footer scroll placement | Frontend specialist | `AppLayout.vue`, `AppScrollPane.vue`, `InvoicingPageShell.vue`, designated routed page/layout scroll hosts and focused footer tests | Audit all AppLayout routed scroll owners before removing persistent row; preserve mobile and embedded behavior; no header writes |
| Security review | Independent security | Read-only; disposable temporary probes only | Review enum/bootstrap trust boundaries, external resources, privacy/copy/assets and authentication preservation; identify exact reviewed scope/hashes and findings |
| QA review | Independent QA | Read-only; disposable temporary harness/artifacts only | Verify safe test targets, both variants/entry paths, long/short footer geometry, 1440/768/360 widths, anchors, modal traversal/focus, locale parity, errors and unchanged headers |

Specialists do not commit, publish or deploy. Main owns shared state, integration and Git publication. No external repository changes.

## Acceptance and validation

Relevant PHPUnit resolver/view tests, Vitest selector/showcase/footer regression checks, existing frontend suite, lint, typecheck baseline, five-locale parity and production asset build. Independent browser review uses synthetic offline fixtures with external requests blocked; no production database, wallets, funds or merchant tokens. Record actual results and limitations below after review.

## Operational switch and rollback

Both variants ship together. Set `HOMEPAGE_VARIANT=showcase`, run the existing configuration-cache refresh (`php artisan config:cache`) during the authorized deployment procedure, clear any separately configured cached homepage HTML, and reload `/`. Rollback uses `HOMEPAGE_VARIANT=legacy` and the same cache refresh/reload; no frontend rebuild is needed. Missing, empty, unknown, differently cased and non-string configuration values all select legacy. Public query parameters cannot override the choice. A loaded document keeps its original selection during internal navigation.

`HOMEPAGE_DEMO_POS_URL` optionally supplies a public demonstration page. It defaults to empty. The entire accepted URL is exposed publicly in the HTML bootstrap, including its path, query parameters and fragment; never include tokens, credentials or other secrets. The server strips invalid or credentialed URLs before producing public HTML, but URL validation does not detect secrets in query parameters or other URL text. Only credential-free HTTPS URLs are accepted by both the server resolver and the showcase; no iframe is created until the visitor explicitly loads it. The existing CSP must already permit that origin (normally the configured public BTCPay origin); the implementation does not widen the policy. With no valid demo, the screenshot preview and PoS documentation remain available. Demo activation must not be confused with a payment-sandbox guarantee.

## Proposed follow-ups, separate from this implementation

1. **Unify navigation ownership for store apps.** The three store-app URLs currently have both Laravel/Inertia pages and Vue Router pages. Choose one owner for each URL and consolidate the duplicated views so direct loads, internal links, back/forward and reloads reach the same behavior. Add targeted authenticated navigation regression coverage before removing either path.
2. **Review Inertia shared props and auth initialization.** `HandleInertiaRequests` defines auth/flash/locale/error sharing but is not appended to the web middleware stack in `bootstrap/app.php`. Audit the shared payload and the Pinia bootstrap contract, then register and test deliberately in a separate change. Preserve server authorization, session/CSRF and the existing full-document boundary when leaving Inertia.
3. **Fix the existing tablet header breakpoint, then measure indexing and first paint.** Independent browser comparison found that both legacy and showcase share the pre-existing PublicHeader overflow at 768 CSS pixels (document width 904 pixels). The accepted implementation preserves that header unchanged; adjust its responsive navigation in a separately reviewed UI change. This change aligns the Blade hero shell with the selected Vue page; it does not render the complete marketing page on the server. Measure indexing, LCP/CLS and locale behavior, then choose a bounded prerender/full-HTML approach for public content. An Inertia migration or SSR service should follow a concrete requirement and a reviewed recovery/deployment plan.
4. **Make layout ownership easier to maintain.** Keep the explicit primary-scroll/footer contract and expand route-level browser coverage before gradually consolidating repeated page shells and loading/error branches. Do not move fixed store toolbars into a shared scrolling viewport.
5. **Reduce frontend debt in bounded steps.** Preserve the existing warning/typecheck ratchets, prioritize the 111 recorded type findings and duplicated app views, and keep synthetic browser fixtures for critical invoice/store/navigation flows. Public performance metrics and error reporting must exclude account keys, merchant content and payment data.

No follow-up changes authentication, billing, entitlements, relay topology or deployment by implication.

## Frozen implementation and review evidence

Reviewed baseline: `e584490d`. Final runtime70 manifest SHA-256:
`8466ca0ec0177ea5eafbd9600c995781e514607e51fe47a421b594f13ab90f3f`.
Final source/tests77 manifest SHA-256:
`fbb9075694f06381bf07ce2046ec065449354fd37e10ee87f5b298fbb4493576`.
Manifests and synthetic preview images are retained in the ignored private
`storage/app/private/landing-showcase-review/` directory. Documentation is not part
of these source fingerprints. The primary checkout and original design artifacts
remain preserved; no source publication or deployment is performed.

Independent security (`landing_security`) accepts the exact final bytes with no
unresolved findings. A concrete medium finding was reproduced and fixed: the raw
configured demo URL could contain user/password text in anonymous HTML even though
the client rejected the iframe. The new server resolver removes such values before
bootstrap publication; an identical synthetic red/green probe confirms credentials
absent in both documents. Existing authentication, CSRF, store/company authorization,
CSP, billing, entitlements and original headers are unchanged. Five-locale privacy,
legacy/server-processing, bank-confirmation, hosted-domain and Cashu qualifications
and all four original screenshot hashes were independently reviewed.

Independent QA (`landing_qa`) accepts the same final runtime/source manifests and
uses actual components, routes and production CSS with
synthetic inert fixtures and blocked external HTTP/WebSockets. This is frontend
evidence, not a live backend, authentication, payment, relay or persistence test.
Browser evidence covers 1440/768/360 widths, all five locales, preserved navigation,
all eight tools with button/arrow boundaries and focus restoration, solution dialogs,
screenshot fit/native zoom, explicit demo activation and first-paint/mount cleanup.
Long/short dashboard/store/invoice/editor/embedded/admin/Inertia component cases
verify footer position and stable headers/toolbars/mobile navigation.

Two actual duplicate-footer findings were fixed before final acceptance: an avatar
fallback and an inner Lightning-list loading indicator had incorrectly acquired
footer-enabled panes. Their original inner divs are restored. Real mounted-page
regressions and independent browser reruns cover both branches. Context/AST audit
of the footer scope finds 35 panes across 19 files, with no nested explicit footer
pane. Full changed-Vue audit covers 48 templates. The new pane opt-in stays off
unless a designated primary boundary enables it.

Validation already completed on the final behavior:

- Isolated network-disabled PHP: 44 tests / 279 assertions pass, including 25
  homepage tests covering both document templates, malformed/missing config, query
  rejection, all hero locales, safe demo escaping and unsafe URL non-disclosure.
- Actual config-cache probe: cached showcase survives a changed environment until
  refresh, refresh rolls back to legacy and cached invalid values select legacy.
- Final complete frontend suite on Node 24.22.1 with two workers: 1,882 tests /
  188 files pass, including both real-page footer regressions. The prior Node22
  four-worker run passed 1,880 tests before those two tests were added.
- Final focused selector/showcase/footer: 7 + 14 + 7 tests pass. API-doc/SEPA/wizard
  fixture selection: 27 pass, preserving all original transport/access assertions
  and timeouts. Local footer stubs isolate the optional public version request.
- Final typecheck passes with 111 existing findings and zero new findings; the
  baseline is unchanged (nine older entries no longer occur). Full lint passes
  with the unchanged 48 warnings / 52 cap; added/changed test and
  showcase scopes lint cleanly. All five locale keys have parity.
- Final production build passes; existing large-chunk warnings remain. PHP Pint and
  scoped PHPStan pass. AST-only Graphify updates complete in the worktree's own
  graph; the primary graph is preserved. No tool installation or semantic rebuild.

An initial fully parallel test run overlapped compilation and produced resource
timeouts plus the two legitimate fixture failures above. Do not treat it as a pass;
subsequent bounded-worker execution retains the existing timeout/warning/typecheck
thresholds. No CI, source publication or deployed behavior is inferred.

Known unchanged UI limitations: at 768 pixels the existing public header spans
904 pixels in both variants; its mobile opener also lacks an expanded-state
attribute and has focus/control-association gaps. These are explicit follow-ups
under the preserved-header contract.
External demo rendering/payments are blocked during review.

Final QA evidence includes 48 broad browser groups, eight first-paint/cleanup
checks, five actual locale transitions, arrow/mobile-navigation checks, three
repaired store/footer states and two final compiled-CSS promotion checks. The
shared underline utility, duplicate footers and credential-disclosure findings are
closed. Source files are still local and uncommitted on the isolated feature branch.
