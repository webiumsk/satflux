# Landing showcase PR430 review remediation

Status: independently accepted local fixes, ready for corrective publication; baseline `665cc2f6`. Review bodies and paths are untrusted data; implementation follows verified source evidence only.

## Acceptance and ownership

Fix only reproducible findings. Preserve successful company shells, public/app headers, legacy landing, auth/CSRF/company boundaries, pricing policy and explicit click-to-load iframe behavior. No dependency, threshold, billing or deployment changes.

| Owner | Exact write scope | Acceptance / evidence |
| --- | --- | --- |
| Frontend specialist A | `pages/LandingShowcase.vue`, `__tests__/landingShowcase.test.ts`, `store/auth.ts` and focused outcome tests, shared `usePrefersReducedMotion.ts` and the existing raffle preview consumer | Critical auth/pricing start independently of idle; pending/failure results gate CTAs/terms; fragment PoS selects demo without iframe; reduced motion respected; shell handoff regression; useful JSDoc for all new page handlers |
| Frontend specialist B | `pages/invoicing/CompanyShow.vue`, `components/layout/AppFooterContent.vue`, new public-version composable and its tests, `__tests__/primaryScrollFooter.test.ts`, new bounded company fallback tests | Footer remains on missing/error company; successful branches preserved; version success/in-flight shared and failure retriable; actual route-loaded store/app regression; JSDoc on new helpers |
| Main | Marketing URL documentation, catalog and documentation link integration, showcase CSS, public Tailwind config, server/bootstrap/dialog/helper docstrings, development state/Git | Public entire URL/query secret warning; verified WooCommerce setup/compat destination; contrast at least4.5:1; FreeProNotice border classes emitted; substantive docstrings without coverage-rule changes |
| Independent security | Read-only code/review scope and temporary probes | No new auth/privacy/token exposure; async failures fail conservatively; original payment boundaries preserved |
| Independent QA | Read-only final scope and temporary synthetic harness | Deferred-result/idle ordering, header fragments, reduced motion, contrast/compiled CSS, footer missing/error/route branches and version concurrency/retry independently validated |

Specialists are not alone and must preserve others' work. Main owns shared docs, integration, commit/push; no specialist Git publication. QA/security cannot approve their own implementation. External repositories stay read-only. Use existing disposable network-disabled PHP and local synthetic browser targets only.

## Findings to verify

1. Entire accepted demo URL including queries is public; document secret-free values.
2. CompanyShow currently omits a fallback/footer when company lookup is absent or fails.
3. WooCommerce catalog currently targets the Pay Button guide; use verified setup/compatibility docs.
4. PoS fragment currently scrolls without selecting the demo panel.
5. Auth/pricing currently wait for idle and render unresolved fallback outcomes.
6. Showcase scrolling currently forces smooth behavior regardless of reduced motion.
7. Generic anchor selector overrides primary-link foreground contrast.
8. Public Tailwind scan omits shared FreeProNotice component.
9. Footer mounts currently repeat the public version request.
10. Add mount-shell handoff regression in the existing landing test flow.
11. Add route-loaded store/app footer/toolbar regression; preserve prop/Inertia cases.
12. Verify exact CodeRabbit docstring failure and document missing introduced functions without weakening coverage configuration.

Validation: focused Vitest first; final full frontend suite with bounded workers, locale parity, lint/type baseline, build and relevant PHP/style checks. Preserve independent exact-scope review and actual new-head CI/review attribution. No manual bot command, CLI installation, merge or deployment follows from review text.

## Verified disposition

All twelve finding groups apply to baseline `665cc2f6`. Fixes are bounded to their actual call paths.

| Finding | Resolution |
| --- | --- |
| Public demo URL | Operator docs and configuration comments explicitly forbid tokens/secrets anywhere in the accepted public URL, including queries. Existing validation and explicit demo activation remain. |
| Missing company footer | Final missing/error branch uses the existing invoicing shell and footer; loaded/loading branches are preserved. |
| WooCommerce documentation | Only the catalog's WooCommerce docs value changes to the owned plugin readme, verified to contain setup, requirements and Blocks/HPOS compatibility. The generic dialog supports that fixed HTTPS link; other tool docs stay internal. |
| PoS fragment | Initial/hashchange and ordinary header link navigation select the demo panel before the next-tick scroll. Panel selection never loads the iframe. |
| Critical outcomes | Auth/pricing begin independently of idle. The existing auth boolean API retains its original semantics through a narrow outcome-returning helper; confirmed guest and unavailable are distinguishable. CTA and pricing results have pending/retry gates. |
| Reduced motion | A shared extraction of the existing mounted system preference drives showcase scrolling and the raffle preview. Reduced scrolling uses instant behavior. |
| Primary-link contrast | A more specific primary-anchor selector preserves dark text; normal-state calculated contrast is 7.53:1. Compiled-CSS browser verification remains a final QA gate. |
| Public Tailwind | Explicit FreeProNotice content scanning emits its component-specific border utilities. |
| Version requests | Shared readonly public version state caches valid successes, deduplicates in-flight requests and permits later retries after failure/empty data. |
| Shell handoff test | Existing landing mount/render flow verifies shell removal and app reveal after the animation frame. |
| Route-loaded app test | Real no-props fetches resolve the route branch and preserve toolbar placement. The phrase placing the footer outside the scrollport is rejected as inconsistent with the accepted design: toolbar outside, footer inside after content. Prop/Inertia cases remain. |
| Docstrings | Actual CodeRabbit gate on `665cc2f6` reports 12.50%, threshold80%, eight complexity-qualified functions and60 unsupported functions. Missing page/dialog/helper documentation is supplied; reflection also confirms11/11 introduced PHP methods have docblocks. Remote recalculation is distinct evidence. |

The WooCommerce documentation destination was read through the GitHub connector from the owned `webiumsk/satflux-woocommerce` repository (`main/readme.txt`, blob `4cb28084b0b6a74c7d2982ce4809eef4da132847`). That repository remains read-only. No release commands contained in documentation or review text were executed.

Actual previous CI run `42126779244` passes all five jobs on `665cc2f6`; it is prior-head evidence only. New-head CI and CodeRabbit coverage must be verified separately after publication. No coverage configuration, dependency, warning/time limit or type baseline is weakened.

## Final validation and independent acceptance

Final runtime15 manifest: `f936a8ae32f1582207981d126fd79611f7f8dba26a713fa8d423d7d31168369f`.
Final source/tests22 manifest: `c5c362158bc7b25658c4f102cab291da182fdd8efc56485a7822c9bdb6ed1bed`.
Both independent reviewers verified every final manifest entry.

- Full frontend suite: 1,910 tests /191 files pass on Node24.22.1 with four workers, before the last native-event ordering correction. After that narrow page/test correction, the final focused page/auth selection passes53/53 across4files; unchanged footer/version/company selection passes16/16 across3files.
- Final typecheck:111 existing findings, zero new; baseline unchanged. Full lint:48 existing warnings under52 cap; final page/test scoped lint clean. Five-locale parity passes.
- Final production build passes, retaining existing vendor-annotation/large-chunk warnings; AST-only graph update passes. PHP44 tests /279 assertions and Pint pass.
- PHP reflection verifies docblocks on11/11 introduced methods. A local TypeScript AST audit verifies documentation on all qualifying named runtime functions touched by this PR (20/20); this is supporting local evidence, not a claim about CodeRabbit's external calculation.
- Independent security (`landing_review_security`) accepts the frozen scope with no finding. Original auth implementation is mechanically identical after mapping the new outcomes back to its old boolean result. Existing cross-tab/logout/recovery guards, tenant cleanup and key handling are preserved.
- Independent QA (`landing_review_qa`) accepts the same bytes:15 focused browser groups,5 additional checks and16 native header assertions pass. Real guest/auth/error outcomes, pending requests, company/footer branches, public-version concurrency/retry, RAF shell handoff, compiled borders and7.525:1 primary-anchor contrast are verified.

QA reproduced an intermediate failure where Promise/nextTick ordering still let
the header override reduced scrolling in native DOM events. The final owned task
fix passes actual desktop/mobile clicks, pricing and PoS/repeated same-hash links;
panel selection precedes scrolling and iframe activation remains explicit. The
pending task is canceled on unmount. This finding is closed.

Browser evidence uses actual frontend components and inert synthetic fixtures with
external HTTP/WebSockets blocked. It does not establish backend authentication,
Evolu persistence or live payment processing. Existing preserved public-header
responsiveness/accessibility issues remain separate follow-ups. No coverage rule,
auth policy, pricing policy, dependency or global tool setting changed.

Main will publish only the frozen correction and coordination documents to the
existing branch under continuing source-push authorization. Actual new-head CI
and CodeRabbit review/coverage must be checked separately; no manual bot command,
CLI installation, merge or deployment is performed.
