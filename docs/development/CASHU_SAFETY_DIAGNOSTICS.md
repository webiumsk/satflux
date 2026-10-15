# Cashu safety diagnostics follow-up

Baseline: Satflux `6b289f751b535cb09a061e6f4d94c466e1425417`, which passed
CI 38809977505, including 1,656 PHP tests/7,025 assertions, 726 Vitest tests,
20 browser passes/three intentional skips, static checks and security audits.

On 2026-10-16 the owner confirms unchanged saves work, while changing the payout
address remains blocked. The owner reports CashuMelt 1.4.0.3 and shows two displayed
settled payments plus one expired unpaid payment, without recovery actions. The
actual deployed plugin commit and its safety counters remain unverified.

## Verified diagnosis

Independent backend, frontend and security reviews traced owned Webium plugin
reference `df047c98b48a0f2e2247d613029b7fa7bb4b9f98` read-only. The plugin's
`CashuMeltConfigService` checks original payment state, retained recovery fields,
change state and retained sweep intent. Its safety endpoint reports a boolean and
two counts. Counts represent payment/change records, not satoshi amounts.

Satflux currently discards the counts. Its payment formatter also normalizes
`PENDING`/missing state plus `settledAt` into `SETTLED`, losing the original state.
The payment list does not include the provider's separate change ledger. Consequently
the screenshot cannot establish whether the refusal is correct, or that any funds
exist. Some available sat change below the provider's automatic sweep threshold can
remain pending; this is a possible source condition, not a diagnosis of this account.

## Bounded Satflux implementation contract

- Keep the provider's explicit refusal authoritative. Do not infer permission from
  terminal table rows, zero counts, timestamps or a diagnostic snapshot.
- Project a read-only allowlist: `status` (`safe`, `blocked`, `unavailable`),
  `unresolved_payments`, `unresolved_change`. Validate counters as nonnegative
  integers bounded by the provider's signed 32-bit count; malformed/missing counters
  are null, never coerced to zero. Do not
  expose raw provider bodies, identifiers, URLs, addresses, errors or asset material.
- Include the snapshot in the existing blocked-save response and existing payment
  list response. Use only the current store owner's merchant key and existing
  authenticated, verified, ownership-checked routes. Add no write/recovery endpoint.
- A diagnostic read failure must not erase an otherwise available payment list.
  Unavailable evidence is explicit. A false boolean with zero/unknown counts remains
  blocked and must not be described as proof of a monetary balance.
- Preserve original provider settlement state additively alongside the existing
  normalized compatibility field. Allowlist known states; unknown values become null.
  Display a discrepancy explicitly. Do not introduce
  new recovery buttons, automatically retry payments, sweep, export or mutate funds.
- Explain all five UI locales: payment history and wallet-change readiness differ;
  a blocked change may require investigation even when no recovery action appears.
  Change counts are records, not amounts. Retain current supported recovery actions.
- Keep email grants, unresolved configuration journals, read-only no-op behavior,
  invoice safety, billing, quotas and tenant/security rules unchanged. A real save
  still performs fresh authoritative verification.

Backend owns Cashu service/exception/controller and focused API tests. Frontend owns
API types, existing Cashu connection/payment components, shared diagnostic UI, five
locales and focused component tests. Main owns contracts, state and Git publication.
Independent security and QA review the final frozen source. All provider repositories,
production configuration/data and real funds remain untouched.

The shared BTCPay client otherwise logs responses before service projection. Backend
also owns a narrowly scoped safety-endpoint logging correction so response bodies
and error notes cannot bypass the diagnostic allowlist. Preserve authentication,
retry semantics and unrelated endpoint logging.

## Acceptance and evidence

Test safe/blocked/unavailable responses, false with zero/unknown counts, malformed
counts, sensitive-extra stripping, denied tenant/verification, GET-only reads,
preserved payment lists on diagnostic failure, raw/normalized state discrepancies,
current recovery-action behavior, stale-store responses, and unchanged confirmation/
no-op protections. Run relevant backend/frontend tests, static checks, locale parity,
build and exact-head CI without ratchet expansion. No tests against live wallets.

This change supplies missing diagnostic evidence; it does not claim the owner's
address change is unblocked. If the provider still refuses, its actual counts/raw
state determine the next bounded investigation. Removing or rewriting provider
recovery material, changing provider code or initiating fund recovery needs separate
applicable authorization. No merge or deployment is part of this fix.

### Frozen source review and local evidence

Independent security accepts the 14 application/locale files with SHA-256 manifest
`39814c2282d09b60876c77c4461cec76f34e2d973534d2a722063e6549d9d2cd`
(sorted path, NUL, contents, NUL). It verifies strict projections, fresh write
checks, refusal precedence, stale-store handling and source-level log redaction.
No blocking findings remain in that source review.

Backend focused checks pass 88 tests/409 assertions across diagnostics, wallet
update safety, payments and recovery. After final test-only fixture changes,
diagnostics plus payment tests pass 52 tests/228 assertions. Complete frontend
tests pass 122 files/748 cases; independent QA separately passes all 18 new
diagnostic component cases. Independent full Pint passes 1,038 files.
Independent full PHPStan completes with no errors. QA accepts the same frozen
application/locale manifest, subject to the fresh exact-head CI gate.
Actual typecheck passes with the same 121 baselined diagnostics, lint has zero
errors/48 existing warnings, locale parity and whitespace checks pass. The final
test-only translation-mock correction retains all assertions; its 18-case suite
passes again and independent QA accepts the compatibility change.
The production build passes with existing import/chunk warnings. No ratchet or
dependency changes are part of this follow-up. The disposable PHP test network and
DNS container are removed after independent QA completes.

PHP runs in an isolated fixture using in-memory SQLite, array cache/session/mail,
synchronous queues, no cached configuration and internal-only stub DNS. No live
provider or production data is used. Local vendor remains unchanged; exact-head CI
must freshly install the existing lock, including HttpFoundation 7.4.21, and repeat
all application, browser, static and audit checks before final acceptance.
