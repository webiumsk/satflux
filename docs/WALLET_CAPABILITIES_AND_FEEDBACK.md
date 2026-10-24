# Lightning address capabilities and feedback transport

## Scope and authorization

The Product Owner approved these Satflux fixes on 2026-10-24 after a read-only
audit. The implementation is based on `5942032a102ddcb3614319b1e65ab539245dadce`
on branch `fix/wallet-capabilities-chorala`. No plugin repository, deployed
configuration, billing history, production wallet or merchant data is changed.
Merge and deployment require separate approval.

## Address decisions

Wallet branding is presentation, not a capability guarantee. Every bare Lightning
address goes through the same per-address probe, including Coinos and known Cashu
wallet domains. Two addresses on `coinos.io` may have different capabilities after
one migrates to Coinos v3.

The store-scoped probe retains `lud21` and `reason` and adds:

| Field | Contract |
| --- | --- |
| `outcome` | `supported`, `unsupported`, `unavailable`, or `invalid` |
| `code` | Stable `lud21_*` reason for callers |
| `alternatives` | Cashu availability, block reason, selected mint and required consent |

Only a valid signed, unexpired mainnet invoice for the requested amount with no
`verify` endpoint permits the Cashu suggestion. Timeouts, invalid verification,
unsafe callbacks and malformed invoices remain errors. Callback DNS pinning and
redirect refusal remain enabled.

Cashu availability requires plugin settings, a configured existing/operator mint,
known-safe settlement state and existing wallet-change safety checks. Existing
mint selection wins over the operator default. The UI shows the mint and requires
explicit consent. Advanced entries return to the same capability review.

Native save rechecks capabilities without relying on the presentation cache before
creating a connection, journal or remote configuration. Ownership, confirmation,
outstanding-invoice checks and uncertain-write reconciliation remain authoritative.
No automatic alternative is written after an uncertain BTCPay response. Existing
invoices retain their original mint and payout snapshots. Cashu establishes payout
to the Lightning address provider, not the provider's subsequent internal transfer.

## Feedback transport and identity

The Chorala widget uses its direct API with the configured Chorala origin. Legacy
`CHORALA_USE_PROXY=true` is ignored by the SDK integration; cached configuration
and shell metadata explicitly select direct transport. The existing proxy stays
protected by CSRF. Satflux session and CSRF headers are never sent to Chorala.

The widget's MIT-licensed client is locally pinned and imported as a Vite asset.
Its upstream `identify` command destroys the UI, so it is reserved for account
boundaries. A narrow local `refreshIdentity` patch updates only the existing API
client's JWT and preserves drafts and attachments. The offline generator verifies
the upstream hash and unique patch anchors. Source provenance and license notices
are in [the vendor README](../resources/vendor/chorala/README.md).

The token lifetime remains one hour. The endpoint returns matching expiry metadata
with `Cache-Control: no-store`. Client refresh is deduplicated and bound to the
current account generation. Same-user transient refresh failures preserve the
existing client and draft and retry token retrieval; they never retry a feedback
POST automatically. Expired identity is rejected by Chorala until refresh succeeds.
Logout and account switching clear prior identity and composed text. Script load
and identity failures have translated visible retry controls.

## Validation and rollout

Use synthetic fixtures and disposable SQLite with no network for PHP checks.
Frontend tests cover capability, consent, selected mint, stale responses, advanced
legacy handoff, direct transport, expiry, identity races and script failure.
Real archived SDK browser checks must cover successful submit, validation errors,
same-user draft preservation and cross-account reset with all API calls intercepted.

Verified on 2026-10-24 at the reviewed working-tree scope:

| Check | Result |
| --- | --- |
| Complete Vitest run, four workers | 134 files, 934 tests passed |
| Relevant PHP cases | 133 selected cases passed; all 28 Cashu wallet-safety cases passed on rerun after updating a discovery fixture |
| Independent QA | 34 focused frontend tests, 34 focused PHP tests and actual Chrome checks passed |
| PHPStan | No errors |
| Pint | 1,053 files passed |
| Typecheck | No new errors; existing 120-error baseline retained |
| ESLint | No errors; 48 existing warnings within the unchanged limit |
| Locales | EN/SK/ES/DE/CS parity passed |
| Production build | Passed; pinned widget emitted as a content-hashed asset |
| SDK source/hash/patch/syntax check | Passed |

Independent security review found no remaining blocker. Independent QA accepted
the implementation after closing legacy Blitz/Flash handoff and same-user SDK
draft-loss findings. Chrome checks retained the open form, title, body and screenshot,
rotated the identity header after a mocked 401, and cleared account-bound state on
logout. No browser request reached a real feedback API.

The complete offline PHP run stopped after 337 cases on the existing
`AccountTest::test_guest_upgrade_request_stages_code_without_touching_guest_row`:
its `email:rfc,dns` validation requires public DNS, unavailable in the networkless
container. This is not a full-suite pass; unrelated authentication tests and DNS
validation were not changed. Initial environment checks also exposed the image's
128 MB memory limit and absent PDF driver selection; subsequent runs used a 1 GB
limit, the example environment and explicit isolated overrides.

A public read-only OPTIONS check returned 204 with exact
`Access-Control-Allow-Origin: https://satflux.io`, credentials enabled and the
required headers allowed. A real project-specific submission still needs staging
validation. Production feedback was not created. Graphify was queried in the
original checkout; the isolated implementation workspace had no graph, and no
graph rebuild or tool installation was performed.

Before deployment:

1. Allow exact application origins in the Chorala project, including
   `https://satflux.io`; use explicit separate staging/development origins.
2. Build the pinned asset and deploy the frontend and token-expiry endpoint together.
3. Verify credentialed CORS/preflight and a synthetic staging submission. Do not
   create production feedback as a health check.
4. Verify two Coinos address fixtures with different capabilities and unavailable
   Cashu/plugin cases on disposable infrastructure.

No database migration or BTCPay plugin protocol change is required. Rollback must
keep wallet reconciliation and the original invoice snapshots intact. Restoring
the old Chorala proxy integration restores its known CSRF failure, so prefer a
forward fix for feedback transport rather than weakening CSRF.

## Compatibility with open PR #399

GitHub review confirmed PR #399 remains open at
`a615d0431d7843e18675339a8a30ca3da7de2dd8`, based on `5942032a`. Its PHP,
frontend and Playwright checks passed. Seven files overlap this change and merge
without text conflicts, but a combined-source regression exposed stale recovery
metadata when Save classifies an address without a prior blur. Snapshotting the
submitted address after classification and validation fixes that integration.

The form also normalizes reviewed BTC Blink address shorthands before native
validation and submission. Existing API-key/non-BTC Blink restrictions and native
connection-string parameters are preserved. Raw input remains unchanged.

The new four-case regression covers Coinos, Blink, Blitz and Flash and requires
fresh capability review and explicit mint consent. Independent QA passed 57 focused
wallet cases in a combined-source preview, including PR #399's detection tests.
The tested form is byte-identical to a fresh conflict-free three-way merge.
Independent security review found no blocker in this compatibility delta.

PR #399's enable-only Cashu path is restricted to existing pure-Cashu wallets and
does not intercept an explicit native-Lightning-to-Cashu switch. Its parallel
checkout policy and this change's primary-wallet alternative are separate decisions.
Retain its DOMPurify 3.4.17 manifest/lock update. Shared test dependencies still
contain 3.4.16, so focused wallet tests do not establish full locked-release coverage.

Recommended source order is #399 first, then this branch. The associated provider
PR [#42](https://github.com/webiumsk/BTCPayServerPluginsWebium/pull/42) is merged;
installation of its tested plugin version remains a separate deployment check.
No GitHub merge, deployment, base-branch change or history rewrite is performed by
this compatibility follow-up.
