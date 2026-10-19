# Cashu payment recovery after funding lookup failures

## Scope and observed evidence

Continue the owner-authorized repair in Satflux and the owned CashuMelt plugin.
The owner confirms CashuMelt 1.4.0.4 and exactly two FAILED payments whose native
BTCPay retry also reports HTTP 400. A separate safety counter reports three payment
obligations and zero change obligations. This does not establish a third failed
payment, retained funds, or the actual cause of either failure. No production data
or mint requests have been inspected. Never persist merchant identifiers or assets.

Baseline Satflux is `94a3f593e326bd551586b3eb2818ee01951d5a75`, PR #394,
with independently verified green CI 38829804278: 1,744 PHP tests/7,657 assertions,
793 Vitest cases, 20 browser passes/three expected skips, static/build gates and a
fresh locked HttpFoundation 7.4.21 installation. Existing npm production findings
remain 40 (36 moderate/four high); the critical-only threshold is unchanged.
Provider baseline is `b86e619380fbbbf1427a3e47124d30b369b78d94`, Webium PR #37,
version 1.4.0.4, with green exact-host CI 38824816578 and 129 CashuMelt tests.
BTCPay core and third-party plugins remain read-only. No merge, release publication,
deployment, production configuration/data change or real recovery is authorized.

## Verified defects and limits

The generic HTTP 400 originates from the funding-quote status lookup; its error
body is discarded. It cannot establish unpaid, paid, spent or recoverable state.
Current orchestration polls funding before restoring durable issuance or reconciling
the original payout. That can prevent otherwise supported recovery after mint
funding history becomes unavailable. Some legacy failures can return a stored error
without a fresh lookup. The change-token API operates on separate change records.

The existing worker increments attempts but its manual-retry entry point resets
them on an asset-bearing branch, defeating the documented automatic retry cap.
The safety predicate includes nonterminal records and terminal records retaining
recovery material; displayed FAILED rows are not a complete blocker inventory.

## Provider contract, candidate 1.4.0.5

Preserve existing routes, authentication, store permissions, durable formats and
schema. Add metadata only to existing payment list/detail and safety responses.
Centralize the existing exact blocker predicate without weakening any condition.

Safety adds three nonnegative integer counters, each signed-32-bit bounded:
`failedPayments`, `otherNonTerminalPayments`, `terminalRecoveryPayments`.
FAILED blockers belong to the first category; other states except SETTLED and
EXPIRED_UNPAID belong to the second; terminal records retaining recovery material
belong to the third. Categories are disjoint and sum to `unresolvedPayments`.
Use a consistent aggregate query and project structural facts rather than assets.

Each payment adds `blocksWalletChange` (boolean), `walletChangeBlockReasons`
(unique array of allowlisted strings), and `recoveryPhase` (allowlisted string).
Reasons are all applicable unchanged-predicate conditions:

- `non_terminal_settlement`
- `retained_minted_proofs`
- `retained_issuance_intent`
- `retained_change_outputs`
- `retained_payout_quote` (only while not SETTLED)

Phases, in classifier precedence, are `complete`, `expired_unpaid` for unblocked
terminal records; `terminal_recovery_review` for blocked terminal records;
`original_settings_required` for missing original snapshot; `accounting_pending`
for MELT_COMPLETE; `payout_reconciliation` for retained payout quote;
`proofs_retained`; `issuance_restore`; `recovery_evidence_review` for other retained
material; otherwise `funding_verification`. These are structural classifications,
not authorization, recovery promises or evidence that a balance exists.

Do not expose new identifiers, mint URLs, private quote IDs, proofs, encrypted
material, intent contents, arbitrary mint errors or authentication data. Existing
payment identifiers are unchanged. No new fund action or payment-token export.

## Recovery invariants

Preserve quote advisory locks, original-invoice existence and settled/invalid
overpayment review, original configuration, exact payout validation, change
recovery and accounting idempotency. No HTTP status, age, local FAILED state or
diagnostic flag may establish unpaid/settled status or release a wallet hold.

After validating original settings and durable evidence, a phase-aware path may:

1. Load valid stored proofs bound to the original store, mint, funding quote,
   amount and satoshi unit, then reach the existing original-payout recovery.
   The proof payload alone does not bind the funding quote: require a valid saved
   issuance intent and match every stored proof's secret, keyset and amount to its
   original output. Missing, corrupt or mismatched intent keeps legacy proof-only
   records in manual review rather than authorizing payout from opaque provenance.
   Without an existing payout quote, require complete fresh NUT-07 UNSPENT evidence
   before initial payout to the original snapshot destination. Never replace an
   unknown/pending/expired or mismatched existing payout intent.
2. Restore a valid saved issuance intent using existing `IssueAsync(alreadyIssued:
   true)` only. Do not create new blinding material or call mint issuance on empty,
   partial, invalid or unknown restoration. Continue only with complete valid proofs.
3. Retain manual review for missing/corrupt/mismatched evidence, legacy missing
   snapshots, blank-output-only or payout-only material without usable proofs.

The normal funding path and strict expired-unpaid verification remain unchanged
when usable durable evidence is absent. MELT_COMPLETE accounting remains unchanged.
Unknown proofs remain held; SPENT alone does not establish destination/payment.
All interrupted paths retain recovery evidence. No new payout destination selection.

Separate explicit manual retry from automatic retry internally. Only the existing
authorized manual retry may reset the counter. Automatic count/check/dispatch must
serialize under the existing quote lock, preserve escalation and stop at the cap.

## Satflux projection and UX

Use existing authenticated, ownership-checked routes and merchant credentials.
Project safety counts as nullable snake_case fields `failed_payments`,
`other_non_terminal_payments`, `terminal_recovery_payments`. Accept the partition
only if all three are bounded integers and sum exactly to a valid unresolved count;
otherwise all three are null. Preserve existing status/count semantics independently.

Payment fields become `blocks_wallet_change` (boolean or null),
`wallet_change_block_reasons` (bounded unique allowlisted list or null), and
`recovery_phase` (allowlisted string or null). Reject malformed/unknown metadata;
legacy absence is unknown, not an unblocked claim. Do not grant or enable actions
based on diagnostics. Existing settlement normalization and raw-state display stay.

Explain that FAILED count differs from total obligations and that completed records
can retain recovery material. Display a blocker badge and translated phase/reasons
on affected rows, including displayed settled rows. Funding verification failure
gets actionable text that repeated retry can remain refused and the original mint
may be needed; do not imply export of change resolves payment obligations.
All five locales and recovery guides remain in parity. No automatic retry or code
request, new mutation, checkout/billing/configuration change or new environment keys.

## Ownership, tests and handoff

Main owns this contract, state/roadmap/decisions, user guides, integration and Git.
Plugin specialist owns only CashuMelt service/worker/config/API classifier/version,
plugin-local docs and tests. Backend owns CashuService/CashuController projection
and focused PHP tests. Frontend owns API types, safety summary/payment UI, five
locales and focused component tests. Independent security and QA have no write scope.
Do not revert concurrent work, touch core or other plugins, or independently publish.

Required regression evidence includes funding 400/404 with and without durable
evidence; restore-only empty/partial outcomes with no new issuance; corrupt,
mismatched or missing original data; original payout PAID/UNPAID/PENDING/unknown and
wrong receipt; interrupted/concurrent recovery; settled/invalid invoice review;
monotonic automatic escalation and explicit manual reset; exact predicate/partition
parity including terminal retained material; privacy and legacy/malformed consumer
metadata. Verify disposable runtime configuration before all tests. Use synthetic
mints, throwaway PostgreSQL and SQLite, never production wallets/funds/credentials.

Run native CashuMelt tests/build on unchanged exact BTCPay 2.4.5, host guards,
relevant PHP/Vitest suites, Pint/PHPStan, existing typecheck/lint ratchets, locale
parity/build, AST graph/docs checks, frozen-source independent reviews and exact-head
CI in both repositories. Package only the independently tested plugin DLL.
Update plugin before Satflux; older-provider diagnostics stay unknown. Keep DB and
Data Protection keys together. No schema change; downgrade cannot undo recovery
already performed and must not clear evidence or manufacture terminal status.

Completion remains source/package readiness, not recovery of the owner's payments.
If funding history and usable durable evidence are both unavailable, the obligation
remains held and requires investigation with the original mint. Report that limit.

## Frozen source and validation snapshot

Independent security accepts both production manifests, computed by sorted relative
path + NUL + file bytes + NUL. Satflux's 11 files (two PHP projections, API types,
payment table, safety summary, connection projection and five locales) hash to
`6866913178facfcea23cc5bcfa71d6871328c1781a23a5c61edf7e9f5183d296`.
CashuMelt's six files (project version, existing API controller, configuration,
payment service, reconciliation worker and new payment-safety classifier) hash to
`1ee6a9ace00056766d1401be382abe93c4b1fe3f1a10a1d62cb1004af360ed8e`.
No core or other-plugin changes. Independent UX source review accepts compact
native disclosures for blocked rows, one-line clear/unknown states and shared
explanations without claiming a third failure or available funds.

Main and independent QA each pass 124 focused PHP tests/900 assertions on the
verified disposable memory database. Four-file Pint and two-file PHPStan pass.
Author full Vitest passes 815 cases before final disclosure markup and compatibility
corrections; the final three-file focus passes 76 cases. Actual typecheck retains
121 baseline diagnostics after replacing unsupported `Object.hasOwn` with the
equivalent own-property check. Lint has zero errors/48 existing warnings; five-locale
parity passes. No dependency, workflow or baseline change. Plugin and test-assembly
compilation pass against cached exact-host outputs with existing warnings only.

The final consumer production build passes; independent QA repeats the three-file
76-case focus successfully and accepts the frozen consumer source. At this
pre-publication snapshot, native regressions/independent QA, reviewed package
identity and exact-head CI remain required. Existing repository PRs record final
revisions, measured results and artifact hashes. Source acceptance does not
establish a live payment outcome.
