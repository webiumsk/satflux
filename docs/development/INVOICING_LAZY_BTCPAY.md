# Deferred business-invoice BTCPay checkout

## Scope and evidence - 2026-11-16

The Product Owner authorizes creating actual BTCPay invoices only through the
customer payment flow, rather than during issuance, PDF generation or email.
The existing explicit merchant checkout action remains available.

Implementation starts on `fix/invoicing-lazy-btcpay` from merged master
`077c597df065e000991a67cb9a1b2d32b2d34739` (PR449). The reviewed PR449 head
`111f5015a67629256241e3c7f61162e897e71511` has the same Git tree. This is
repository evidence, not verification of the deployed production revision or
the historical trigger of a particular provider invoice.

The primary checkout remains on `fix/sepa-confirmation-isolation`; unrelated
work and the separate accountant-export clarity freeze are preserved. This
feature uses a separate durable worktree recorded in WORKSPACE. Other
repositories, production databases, provider accounts and wallets are out of
scope. No deployment, merge or provider state mutation is authorized here.

## Contract

| Operation | Payment effect |
| --- | --- |
| Issue or edit a legacy business document | Prepare its existing public token; no BTCPay invoice creation |
| Generate PDF, bulk PDF or email attachment | Encode a stable Satflux payment link; no BTCPay invoice creation |
| Open or preview the payment link, including HEAD | Display a payment page; no BTCPay invoice creation |
| Submit the payment page | Recheck availability, then reuse or create a BTCPay checkout |
| Explicit merchant checkout action | Use the same creation and duplicate-prevention boundary |
| Record a full bank or manual payment | Make the Satflux payment link unavailable for another payment |
| Partial payment, cancellation, deletion, disabled BTC or changed terms | Invalidate the earlier payment offer |
| Confirmed provider settlement | Report BTC payment only after fresh bound provider evidence |

Local-first PDFs need a small server payment-intent registry. Store only the
authenticated owner, local document identity, local store identity, encrypted
canonical payment terms, an opaque bearer token and lifecycle/attempt evidence.
Do not persist a full invoice, buyer, supplier, line items or browser keys.
Server encryption is distinct from Evolu end-to-end encryption. Existing
checkout mappings continue to represent actual provider invoices.

Terminal intent state cannot be reopened by a stale PDF, email, background
observer or relay copy. Payment terms and routing are bound to the intent.
Explicit owner operations use current revision checks. Public capabilities
cannot edit a document, reopen a link or declare a payment settled.

Public GET displays a page; a CSRF-protected POST starts Bitcoin payment.
Use generic public errors and no-store, noindex and no-referrer protections.
Tokens and provider credentials must not enter diagnostics or public errors.

Creation uses a shared document lock and a durable attempt recorded before
one provider POST without transport retries. A timeout, interrupted process,
malformed success or uncertain persistence blocks another automatic attempt.
Reuse known payable checkouts; only positively identified unpaid expired
checkouts permit replacement. Partial, late, invalid, manually marked and
unknown provider states need deliberate reconciliation. Processing does not
establish settlement. Preserve existing wallet-update and unresolved-invoice guards.

## Bank-payment and local-first limits

Satflux identifies bank payment through existing matching or manual evidence.
That does not turn a bank transfer into a BTCPay settlement. A central
authenticated reconciliation service reads live Evolu document state, including
explicit deletion tombstones, and updates only registered payment intents.
It retries after bootstrap, local changes, reconnect and focus. Missing local
rows alone are not deletion evidence. Discard work across account/owner changes.

An offline or closed browser cannot immediately communicate a newly recorded
local bank payment. Server protection begins when reconciliation arrives. This
limit was explicitly explained to the Product Owner during implementation.
An already-issued direct BTCPay checkout remains a separate provider capability;
this change does not automatically cancel it or rewrite its financial history.

Status discovery must work by local document identity before the merchant has
a provider invoice ID. A customer can create and pay the checkout while the
merchant detail is closed; later lookup must discover that payment safely.

The [upstream invoice guide](https://docs.btcpayserver.org/Users/invoices/)
and [Greenfield contract](https://docs.btcpayserver.org/API/Greenfield/v1/)
require considering both status and additional status: expiration alone does
not prove nonpayment, and Invalid can represent a fully paid invoice whose
settlement conditions failed. Validate actual paid amount and canonical
amount/currency binding before asserting BTC settlement. Unsupported fields
or states fail closed. These live references do not establish a deployed
host revision.

## Local Bitcoin QR rendering

BTC payment capabilities never use the inherited external QR renderer fallback.
Use the existing local QR library when present, otherwise the existing Node
runtime and installed `qrcode` dependency. The bounded helper receives data on
stdin, emits only a validated PNG data URI and suppresses sensitive errors.
No new dependency or executable is installed. The tested disposable PHP image
contains Node 20.20.2 and qrcode 1.5.4; the repository Dockerfile targets Node 22.
These are runtime/source observations, not a verified production inventory.

If local rendering is unavailable, EU and US PDFs retain the clickable Satflux
payment link. The Node CI job executes the three real encoder/input tests after
its existing dependency installation. The native PHP-to-Node probe is an
explicit opt-in; mandatory fake-process privacy checks also cover environments
without Node dependencies. The inherited external bank-QR fallback remains
outside this BTC-specific change and is a separate privacy follow-up.

Changing payment terms or partially paying invalidates the published offer.
The existing explicit merchant refresh can deliberately prepare a new checkout
with a current revision; ordinary PDF/email rendering cannot reopen an old token.
A separate dormant-link refresh action is a possible future UX improvement,
not an implicit background reactivation in this change.

## Legacy detached-store compatibility

A same-owner legacy store without a company assignment keeps its informational
GET/HEAD HTTP 200 response. The page is unavailable, with no amount or payment
form. POST/preparation/settlement retain strict company assignment. A correctly
assigned invoice remains payable after the owner's subscription expires.
Foreign-owner and assigned foreign-company bindings remain rejected.

The original controller caught a strict company-assignment failure and returned
an HTTP 200 error page. That historical response was not permission to create a
checkout through an unassigned store. The scoped subscription-test correction
covers this public-payment contract without changing billing or entitlements.

## Ownership and review

| Task | Owner | Writable scope | Dependency / gate |
| --- | --- | --- | --- |
| Backend | checkout_backend_audit | Intent/attempt model and migration, scoped controllers/requests/services, shared mark-paid and bulk locks, webhook reconciliation, QR renderer, routes, optional no-retry provider argument and PHP payment tests | Accepted architecture; coordinate API DTO with frontend |
| Frontend | checkout_frontend_audit | Minimal Evolu projection, intent synchronizer, invoicing shell, ephemeral bridge, document composable and bounded tests | Backend DTO; preserve shared/owner boundaries |
| Integration | Main | Public payment/PDF views, local QR helper/tests and CI step, five server locales, API inventory, obsolete PHPStan ignores, user guides and project records | Backend view contract; exact diff and checks |
| Security | lazy_checkout_security | Read-only review; no implementation changes | Final frozen source and independent negative cases |
| QA | lazy_checkout_qa | Read-only review; no implementation changes | Final frozen source and disposable regression evidence |

Both independent design reviews accept implementation subject to their gates.
That is not final implementation acceptance. Authors do not approve their own
security review. Main alone owns Git publication and shared project state.

## Validation and compatibility

Cover zero provider creation on issue/update/PDF/email/bulk/GET/HEAD; stable
links; public and merchant duplicate prevention; uncertain response and crash;
expired versus Processing/unknown; full/partial bank payment; stale terminal
state and changed terms; owner/store/company isolation; tombstones, retry,
reload and account switching; and status discovery before a provider ID exists.

Graphify queries run against the primary graph; the feature worktree has no
graph and none is silently installed or rebuilt. PHP uses an existing image
and matching Composer lock/vendor, read-only source, no network and temporary
storage/cache. Preflight confirms testing, SQLite `:memory:`, array cache/mail,
sync queue and no cached configuration. Node reuses the audited feature's
existing dependencies with identical package and lockfiles; no install occurs.

Historical PDFs and direct BTCPay URLs cannot be made lazy retroactively. New
intent routes and additive schema must remain available during a rollback that
needs newly issued links to work. An old binary alone cannot resolve the new
intent links. Keep terminal and uncertain-attempt evidence during recovery.

## Final local review and checks

Independent security and QA accept the final bounded implementation with no
unresolved blocking findings. Final source/test/locale/CI/API freeze: 58 files,
`1426c72b55687cf866f4ef31a04a9d4d8862e6a75351b0f76033b0612c22fb64`.
Production scope: 45 files,
`e047dc6e5f26f7ace5da0fefbb2659cdb020efe325382cc86930b6ad21b6d528`.
Before/after hash checks verify the reviewed source; documentation is outside
this source freeze. Review reports and manifests are retained privately.

Independent security evidence includes six private probes / 53 assertions,
49 focused PHP cases / 201 assertions, 41 frontend tests / three suites and
three actual Node QR cases. A final one-case / 27-assertion review covers the
GET-only compatibility delta. QA independently passes 108 PHP cases / 588
assertions and two historical-payment recovery probes, then the same bounded
compatibility case. Its frontend evidence is 151 tests / seven suites on the
preceding freeze plus 13 corrected-composable tests; these runs overlap.
Neither reviewer authored implementation or changed repository files.

| Main validation | Verified result |
| --- | --- |
| Full JavaScript suite | 3,637 passed / 254 files |
| Real local QR encoding/input tests | 3 passed |
| Full PHP suite | 3,506 passed / 69 skipped; 19,199 assertions; one inherited PHPUnit deprecation; no failures/errors |
| ESLint | 0 errors / 46 inherited warnings |
| Typecheck | No new errors against the existing 110-error baseline; no ratchet increase |
| Locale parity | EN/SK/ES/DE/CS pass |
| Production frontend build | 7,673 modules; inherited chunk-size warnings |
| PHP formatting | Full 1,182-file Pint pass; final compatibility two-file check passes |
| PHP static analysis | 0 errors on the final compatibility source |
| API artifacts / Blade / CI YAML | Current inventory, compiled views and parsed CI step pass |
| Whitespace and final source hashes | Pass |

The initial full PHP runner exposed missing native MIME dependencies, unavailable
DNS for existing account fixtures and insufficient temporary storage for the
existing 500 MiB health threshold. Independent bounded tests reproduce these
failures on the unchanged merged tree. Reusing byte-identical existing MIME
sources/lock/dependencies, providing synthetic loopback-only DNS and increasing
only the disposable storage capacity to 1 GiB resolves the bounded cases.
Account tests pass 100/539, five MIME cases pass 5/43, and both health suites pass
11/72. No application threshold, validator, network isolation or source assertion
is relaxed. The final runner uses the existing Node 22 binary read-only and
allows executable wrappers only within its disposable temporary filesystem.
No package or tool is installed. These environment checks are not deployment.

Provider responses and race interleavings are synthetic. Live Redis contention,
a deployed BTCPay host, native execution of the new Evolu projection and actual
bank/provider delivery are not established by these checks. Existing full-suite
native SDK tests are separate evidence. The optional CodeRabbit CLI is not used.

The final corrected-runner PHP execution exits successfully: 3,575 total tests,
19,199 assertions, 69 skips and one inherited PHPUnit deprecation. All mandatory
local checks and both independent reviews are complete. The result remains local
and unpublished in the durable feature worktree. Next is owner review and Git
publication when requested. Main performs no merge, deployment or real provider
action.
