# Raffle creation limit loading

## Problem and contract

Both raffle pages instantiated `useAccountLimits()` without calling its `load`
method. That composable holds its reactive snapshot per invocation; loading it
in the sidebar does not populate the pages. The list CTA interpreted unknown
limits as denial, while the direct-create page immediately opened an upgrade
modal. This blocked administrators as well as ordinary accounts before a create
request reached the server.

Independent QA reproduced both paths using the original Vue pages at Satflux
`9ec30cdcdcff6cb4477747cf433905375073229e`, first in component mounts and then in
Chrome with Slovak translations and the actual upgrade modal. Neither path sent
`GET /user/limits`.

Existing server policy remains unchanged:

- Admin/support and other unlimited effective plans have no raffle quota.
- Registered Free accounts have one raffle per store unless an existing eligible
  effective entitlement provides unlimited access.
- True guests remain restricted by `guest.restrict`; registration does not
  implicitly activate promotional policy.
- Creation checks the store owner's quota and server-side current count.
  Administrative access to another owner's store retains that owner's quota.
- Purchase availability is separate from raffle creation permission.

Older bootstrap instructions describe free PRO as proposed. Current source and
`FREE_PRO_IMPLEMENTATION.md` contain a subsequently authorized effective-access
policy. This fix consumes its existing result; it does not activate promotion,
reopen purchases or alter billing, quotas, roles or ownership.

## Change

`useRaffleCreationLimits` explicitly loads the current store's account limits and
the actual raffle list. The successful list supplies the count because the
existing limits endpoint returns `current=0` on provider failure. A missing or
malformed successful list is an error, not an empty collection. The index uses
this same list rather than fetching a duplicate page list.

Loading, failure with retry and confirmed quota denial are separate states.
Unknown access cannot enable the CTA or programmatic submit. Confirmed guest
denial avoids guest-forbidden list requests. Neutral messages explain guest/cap
restrictions in all five locales; the existing upgrade modal is available only
for a confirmed finite cap when PRO purchases are actually enabled.

Store/account/guest/role/plan/effective-policy changes synchronously invalidate
the snapshot. Request tokens and scope checks discard late results. Shared
account-cache invalidation is allowed to flush before reloading, and an explicit
guest check prevents reusing a same-account cached non-guest grant.

The two raffle pages and their dedicated helper change. Backend controllers,
middleware, configuration, the shared account-limit composable and shared
purchase modal do not change. The backend continues to authorize every write.

## Verification and rollout

The focused regression suite mounts the actual pages with the real scoped
account-limit composable. It covers delayed limits, unlimited/Free/capped users,
purchase availability, guests, quota/list errors and retry, malformed list
responses, store/account/policy races, cached account return and same-ID guest
transitions. Review and final validation results are recorded in project state.

Local checks reuse an existing dependency cache: Vue, Pinia, Vite, Vitest and Vue
Test Utils match the lock; DOMPurify is 3.4.16 versus locked 3.4.17. These checks
do not replace frozen-lock CI. No production APIs, wallets, funds or databases
are used. No PHP changes or migrations are needed.

Deploy a newly built Satflux frontend through the normal approved process.
No BTCPay update, plugin package, environment change or subscription mutation is
required. Reverting this frontend change restores the previous UI bug but does
not change server authority or persisted payment data. Source preparation is
separate from merge and production deployment approval.

## Review evidence

Implementation author: `raffle_frontend`. Independent source security reviewer:
`raffle_backend`, acting under the security role. Independent QA: `raffle_qa`.
Both reviewers accept the bounded scope with no remaining blocking finding.
The reviewed helper SHA256 is
`6d405dccdb5383d18d3de3e629e78cc4c21a0f68e8aaf47526c0637ab75f57e4`.
Only a number-neutral Slovak message was corrected after behavioral review.

- Author focus: 23 tests pass across the 15 new raffle component regressions,
  four existing account-cache cases and four purchase-presentation cases.
- Independent QA repeats 19 raffle/cache cases, reproduces both original pages
  in Chrome and passes 13 candidate browser scenarios without page errors.
  Layout/editor modules are stubbed; actual pages, scoped account limits and
  upgrade modal are exercised with synthetic service transports.
- Full ESLint passes with 48 inherited warnings and no new errors/warnings.
  Full typecheck passes its unchanged 120-error baseline; no ratchet is raised.
  All five locales pass parity and scoped whitespace checks pass.
- Production build passes. The final Slovak text is included in the final build.
- No full-suite pass is claimed. The first unrestricted Vitest run hit timeouts
  in unrelated login/document/store-wizard cases while broad checks ran together
  and was interrupted. A two-worker retry produced no completed-file result
  before interruption; the full-suite result remains unverified. Unrelated tests
  or timeout settings were not changed.

Synthetic QA reports, browser outcomes and original screenshots are retained in
the private `raffle-creation-2026-10-26` handoff. No backend test suite, real
BTCPay HTTP integration or production deployment is claimed for this UI fix.
