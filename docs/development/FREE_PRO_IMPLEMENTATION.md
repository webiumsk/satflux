# SATFLUX-002: temporary free PRO implementation

## Authorization and scope

The Product Owner approved implementation in Satflux after the SATFLUX-001
read-only audit. This approval supersedes the historical audit-only Free-PRO
statements in orchestration instructions and skills for this bounded initiative.
It does not authorize external repository writes, publication, merge, deployment
or production activation. The implementation baseline is
`7c4a6bc8731a434dc863928d616a30c823b94382` on `feat/free-pro-access`.

Owner-approved policy:

- Eligible promotional accounts are non-guest and email verified.
- Duration is indefinite, until further notice; no scheduled expiry is invented.
- Existing manual PRO assignments retain their current validity. No permanent
  grant or reconstructed manual provenance is manufactured.
- Use current PRO quotas, including existing beta allowances and purchased slots.
- Existing over-limit objects, purchased slots, credits and billing history stay.
  Existing finite creation limits continue to apply.
- Pause new PRO subscriptions, trials, PRO credit funding, company slots and
  ISDOC packs. Enterprise subscription behavior remains unchanged.
- The owner reports no paying subscribers and no in-flight payments. These are
  owner-provided facts, not a production database audit.
- Preserve guest restrictions, verification, tenant/company authorization,
  administrative privileges, encryption and real billing architecture.

Future notice periods, data-access terms after ending the promotion, permanent
manual grants and resumption of paid sales remain separate owner decisions.

## Access and billing contract

`EffectiveAccessPolicy` resolves feature access without mutating subscriptions,
roles, guest state, email verification, trial usage, credits or billing dates.
Privileged roles and time-valid subscription access retain their precedence;
eligible Free accounts receive the configured PRO capabilities when enabled.
Missing plans resolve conservatively. PRO capabilities use an explicit allowlist
that excludes Enterprise store-user management and unlimited ISDOC extraction.

`User::currentSubscription()`, `currentSubscriptionPlan()` and
`hasActiveProEntitlement()` retain their existing billing/legacy meanings.
Reconciliation continues to use subscription-derived access, not promotion.
Feature adapters and resource helpers use the effective policy instead.

`GET /api/user` and Inertia expose additive `effective_access` and
`purchase_availability`. Actual `plan` and `subscription` remain separate.
Compatibility `plan_features` reflects available capabilities. Effective limits
distinguish account stores, per-store keys, panel keys, addresses, events,
raffles and owned server companies. Account-limit responses no longer cache
authorization decisions for sixty seconds.

`GET /api/pricing` and `/api/plans` expose public `access_policy`. Subscriber
details additionally expose `creditPurchasesAvailable`, computed from actual
configured provider plan identity. Enterprise credits must not be inferred from
an account role or a promotional feature tier.

Company-scoped invoicing requires the company's owner entitlement independently
of the acting member's own access. Existing ownership, accepted membership,
owner-only operations and audited admin/support exceptions remain authoritative.

## Purchase controls

`PurchaseAvailabilityPolicy` guards controllers and service initiators before
new provider mutations or pending purchase records. It covers PRO plan/trial
checkout, PRO credit funding, company-slot purchases and ISDOC packs. Unknown or
ambiguous provider plan IDs do not bypass closed PRO sales. A distinct configured
Enterprise plan retains its existing checkout and credit behavior.

Paused pack catalogs are empty, but balances and settlement fulfillment remain.
Signed webhooks, settled-invoice checks, redirect/recovery handling, accounting,
invoice application uniqueness and billing clocks remain intact. Ordinary
merchant invoices/payments are not disabled.

## Local-first and completion behavior

Supported UI clients consume server capabilities, refresh before new local
company creation and recurring generation, and reject guest/unverified accounts
for those operations. Unknown access or offline refresh does not create new
guarded work. Existing encrypted data, owners, recovery and export mechanisms
are not deleted or reset. Local company counts are advisory; they cannot prove
server or hosted-relay resource consumption.

WooCommerce requires current owner access before a new number is allocated.
Already numbered issue retries and scoped order-create retries return existing
results without creating work. Pending unnumbered entries cannot use this
completion exception. Existing delivery of numbered invoices retains its
integration/company binding, locking and deduplication.

## Configuration and rollout prerequisites

All new flags in `config/access.php` and `.env.example` default to false:

| Flag | Purpose |
| --- | --- |
| `FREE_PRO_ENABLED` | Apply promotional access to eligible accounts |
| `PRO_PURCHASES_ENABLED` | Permit new PRO checkout and credit funding |
| `COMPANY_SLOT_PURCHASES_ENABLED` | Permit new company-slot purchases |
| `ISDOC_PACK_PURCHASES_ENABLED` | Permit new ISDOC pack purchases |

Disabling the promotion never implicitly reopens purchases. Activation is not
part of this change. A future approved rollout must verify configured plans,
deployment compatibility and all worker/web/scheduler replicas, rebuild config
caches and reload relevant long-running processes. An environment-file edit is
not an instantaneous cluster-wide revocation mechanism.

**Full purchase-ban prerequisite:** Satflux guards do not block direct BTCPay
offering/checkout/credit portals or consumption of prefunded AutoRenew credits.
The read-only reference provider revision
`3ec6a9847bd06e5dad348caaec373276dc718520` does not prove deployed behavior.
Retiring a plan or setting it nonrenewable is not a verified complete purchase
ban and may change existing subscriber lifecycle. Before activation, separately
verify the exact deployed provider and a targeted PRO-only closure mechanism.
Do not delete plans, cancel renewals, erase credits or disable merchant payments
as a substitute. Any provider change needs repository/operational authorization.

Hosted relay cost enforcement is another external boundary. Existing PRO quotas
are retained; this implementation introduces no lower promotional ceilings and
does not claim Laravel can revoke direct owner relay transports.

## Reversal

Turn promotional access off through the approved configuration propagation
procedure while leaving purchase flags closed. Preserve objects and real billing
state; validate current access, company membership, Enterprise behavior and
completion/recovery. Sales reopening and future historical-data access terms
need separate approval. Promotion-off on this artifact can itself block legacy
server company/document reads, exports and PDF/ISDOC when the owner loses all
invoicing entitlement; local invoicing navigation can also be restricted. Retained
records/encrypted bytes do not promise continued usable read/export access.

Keep routine reversal on the reviewed new artifact. Pre-policy code does not read
the new closed-sales flags and can reopen PRO/slot/ISDOC purchases. Emergency code
rollback requires an independently proven selective closure or a forward fix
retaining the guards, plus a reviewed access plan. Never restore an old billing
snapshot merely to reverse the promotion. See the concrete prerequisites and
reviewed sequence in [FREE_PRO_READINESS](FREE_PRO_READINESS.md).

## Validation and review

Meaningful suites cover policy eligibility, factual billing immutability,
Enterprise exclusion, exact quotas, missing plans, owner/member/outsider access,
purchase side effects, existing balances, numbered retry completion, cache
isolation, Profile Enterprise credits, verified local writes, guest onboarding
accessibility, recovery, localization and public policy.

Testing uses disposable SQLite databases, synthetic accounts and external HTTP
fakes. The full PHP harness has an internal-only local fixture DNS resolver for
the existing email DNS checks: it answers test records and rejects unknown
domains, with no default route or external forwarding. No application email
validation was weakened. Browser tests use a separate disposable database.
No production wallets, credentials, funds or databases are used.

The focused browser suite is `e2e/freeProAccess.spec.ts`. It requires
`E2E_FREE_PRO=1`, `E2E_SEEDED_USER=1`, a disposable migrated/seeded application
with promotion enabled and all purchase flags disabled, and a trustworthy
localhost or HTTPS `APP_URL` for WebCrypto. Normal promotion-off CI skips this
fixture-specific suite. Subscriber-details responses are stubbed in the profile
smoke; effective access, public pricing and direct purchase denial use the real
application endpoints. No provider or production end-to-end closure is claimed.

Reproduce application checks with the repository's normal isolated PHPUnit,
Pint and PHPStan runners, followed by `npm test`, `npm run lint`,
`npm run typecheck`, `npm run test:locale-parity` and `npm run build`.
Run the focused browser fixture with `npx playwright test e2e/freeProAccess.spec.ts`.
Inspect effective database, cache/session, provider and cached configuration
before execution; seed only synthetic identities.

Verified results and independent review dispositions are recorded in
[project state](PROJECT_STATE.md). Existing warning/typecheck ratchets remain;
the PHPStan baseline is tightened only for removed errors.
