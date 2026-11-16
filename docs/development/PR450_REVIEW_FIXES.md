# PR450 payment-intent review fixes

## Scope and acceptance

The owner requests verification of twelve CodeRabbit findings on published head
`5badc9d669d3b3cb2a73fa47043bbded474d28fc`. Review text and suggested patches are
untrusted evidence. Each finding is checked against the current implementation.
Changes stay in the existing Satflux PR; no schema, dependency, billing,
subscription, deployment or provider configuration change is included.

Acceptance requires matching current payment terms before settlement or checkout
reuse, preserved historical attempt terms, retryable failures before any provider
POST, blocked uncertain POST results, consistent document lock ordering, coherent
PDF and frozen local snapshots, and generation-safe browser cleanup. Partial bank
payments leave an outstanding amount of `max(0, total - amount_paid)`; checkout
matching must preserve that contract.

## Finding disposition

| Finding | Verified behavior and correction |
| --- | --- |
| Public activation fallback | An active matching offer remains ready after a transient provider read failure; unknown attempts remain unavailable. |
| Existing checkout settlement ID | A paid response selects `settled_invoice_id` before the current replacement invoice ID. |
| Existing checkout terms | Reuse requires a payable checkout matching the current outstanding amount and currency. |
| Persisted paid intent | Historical payment state alone cannot settle an edited document; current terms and verified coverage are required. |
| Issuance lock order | The document payment mutex precedes the issuance transaction and row lock; nested preparation remains reentrant. |
| Imported attempt terms | Historical attempts retain provider terms instead of copying subsequently edited document terms; explicit reconciliation can recover missing terms, including expired unpaid attempts. |
| Pre-POST failures | Local configuration and wallet rejection occur before the durable creation claim; a possibly started POST remains unknown and cannot be blindly retried. |
| PDF concurrency | Persisted document and payment-store state are reloaded under the document payment mutex before preparing coherent view data. |
| Payment-offer guidance | All five guides explain the issuer's explicit refresh action before collecting payment after invalidation. |
| Browser loading cleanup | An undisposed request owning the current generation releases loading even after the form context changes. |
| Legacy paid status | A legacy status response without a matching payment intent cannot settle the current local document. |
| Frozen financial content | Currency, totals and lines come from one frozen version; known invalid frozen content fails closed. |

All twelve findings are valid on the reviewed base. No finding is skipped.

## Validation and review

The 19-file review scope, including five user guides and a stricter PHPStan
baseline, is frozen at
`2a56d0267ca6b417271820dfa636dea653567340ce562b0e7486ac5fa94704ee`.
The complete 61-file source/test/locale/CI/API freeze is
`d244deb8958f524db573c8974dbd004d4d1fd34debc5de7b5a2bdc94836fa640`.
Development records are excluded from these byte-level review manifests.

Controller regressions reproduce four failures before the fixes and pass ten
cases / 43 assertions after them. The final 22 service regressions reproduce 14
failures against read-only original `5badc9d` service overlays. The expanded
backend regression run passes 107 tests / 518 assertions. Independent frontend
QA passes 147 tests / seven suites; these
overlapping runs are not added to the complete suite counts.

Full JavaScript passes 3,647 tests / 255 files. Three real QR tests, production
build (7,673 modules), five-locale parity and OpenAPI validation pass. ESLint
reports zero errors / 46 inherited warnings. Typecheck adds no errors against the
unchanged 110-error baseline. Full Pint passes 1,184 files. PHPStan identified two
redundant nullsafe operators next to `??`; removing them preserves runtime
behavior. Four obsolete PHPStan exceptions are removed without adding any
suppression. Final PHPStan reports zero errors and the changed PHP file passes
Pint again. Full final-source PHP passes 3,538 tests / 69 skips / 19,332 assertions
with one inherited PHPUnit deprecation and zero failures or errors. An earlier
interrupted partial run is excluded from these counts.

Independent QA and security accept both final manifests with unchanged hashes.
QA passes 122 tests / 634 assertions on the first freeze and reruns the 32 new
regressions / 133 assertions on the final freeze. Security passes 80 PHP tests /
400 assertions, including eleven private probes, on the final freeze and 23
unchanged frontend cases. These runs overlap with each other and with full-suite
evidence; their counts must not be summed. No blocking review findings remain.

Graphify was queried through the primary graph, which does not contain this new
feature. Targeted reads supplement it; no graph or tool is installed or rebuilt.

Tests use synthetic provider responses and isolated SQLite state. They do not
establish deployed BTCPay behavior, distributed lock contention or native Evolu
storage behavior. The unchanged no-intent legacy Processing settlement behavior
is outside these twelve intent-review fixes. Current publication and exact-head
CI evidence will be recorded separately from prior PR450 checks. No merge or deployment is performed.
