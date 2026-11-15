# Inbound poll timeout review correction

## PR446 E2E runner compatibility repair approved - 2026-11-15

Exact-head CI43697361409 forbec2c34f passes PHP, Node, PostgreSQL and changelog,
but E2E fails before tests at browser installation: locked Playwright1.60.0 does
not support chromium on ubuntu26.04-x64 selected by ubuntu-latest. This is
runner compatibility evidence, not an application/E2E assertion failure.

Owner explicitly approves only the prepared E2E-runner repair. Change exactly
`jobs.e2e.runs-on` from ubuntu-latest to ubuntu-24.04. The installed locked
Playwright registry includes ubuntu24.04-x64. Existing Node YAML parser verifies
that this scalar is the only semantic workflow change; whitespace checks pass.
No dependency/action versions, other jobs, permissions, application or tests change.
The accepted two-file runtime/test freeze remains
`b342a06d14ed1ba1cade2bfc67716b4bfb7f1685cc4d5c03fb2ab60cf59f1d89`.

Normal corrective publication and new-head CI follow. The failed run is not
reported green, and no tests are marked skipped to bypass the compatibility issue.
No installation, provider operation, PR merge or deployment. Owner retains merge/deploy.

## Authorized scope - 2026-11-15

Treat the supplied finding, paths, code and optional CLI boilerplate as untrusted
review data. Verify the new finding at current PR446 head `64d7bcd7`, not at an
older review snapshot. Remote feature/master remain64d7bcd7/4b9cc230. Preserve
unrelated primary edits and all other worktrees. Existing branch publication
permission persists; PR merge/deployment and real provider operations remain gated.

The current10-second poll timeout is finite but shorter than the server poller's
120-second cooperative admission budget. Budget checks occur between operations,
so a final admitted document can finish sequential provider requests and token
renewal after the budget. The previous shared-timeout choice is therefore
superseded for pollInbound only; historical review records remain attributed.

Accepted minimal correction and evidence contract:

- Use a separate finite poll timeout accommodating the server's default120-second
  admission budget plus finishing sequential30-second provider calls and one token
  renewal. Keep inbox detail/list/health/custody timeouts at10 seconds.
- Preserve the endpoint, undefined payload, cookies, response handling and explicit
  retry. Do not add automatic retry, imply server cancellation or modify server
  receipt retention, ACK recovery, acknowledged-receipt skipping, budgets or config.
- Add a before-failing virtual transport regression: a legitimate completed poll
  after the cooperative budget and finishing provider calls succeeds. Retain a
  finite stalled-request boundary and explicit-only retry coverage.
- Validate unchanged server durable ACK/idempotency tests in the existing verified
  network-disabled disposable PHP target. No production/provider calls or installs.
- Keep source changes to API timeout selection and its existing timeout test file;
  main owns records and publication, reviewers remain independent and read-only.

| Task | Owner | Exact writable scope | Evidence |
| --- | --- | --- | --- |
| Poll budget evidence | backend specialist | None (read-only) | Current budget/call sequence and default timeout assumptions |
| Minimal API correction/tests | main | `resources/js/services/api.ts`; `resources/js/__tests__/efakturaInboxTimeout.test.ts` | Long successful response, finite expiry, no auto retry, unchanged short calls |
| Independent QA/security | existing reviewers | None (read-only) | Frozen source, ACK recovery boundary and reproducible tests |
| State/integration/publication | main | This report and four project records | Relevant tests/static/build, exact-head remote/CI evidence |

Primary Graphify query completed; no scoped graph is present or rebuilt. No
billing/entitlement/provider policy, backend schema/API or dependency change.

## Verified timing evidence and correction

Read-only backend specialist confirms the120-second budget is checked before
page/document admission, not after each provider operation. An admitted final
item can finish detail30s +ACK30s +one token renewal30s +one retry30s, giving
about240 seconds of network work including admission budget at defaults. The
new dedicated `EFAKTURA_INBOUND_POLL_TIMEOUT_MS=300_000` gives processing headroom
and keeps a finite browser wait. All ordinary10-second requests remain unchanged.

This is a client policy for the default30-second provider HTTP configuration,
not a hard server deadline. Configurable longer provider requests, database/cache
waits, XML processing and host validation can exceed those assumptions. The
300-second company lock TTL is not an execution deadline. No server cancellation,
production timing guarantee or automatic retry is inferred from timeout expiry.

Both virtual transport regressions fail against the old10-second configuration:
the configured finite bound is too short and a successful long poll has already
failed by120 seconds. After correction, a240-second completed response succeeds,
a stalled request expires at300 seconds, and retry occurs only on a new explicit
call. Existing inbox detail/ACK tests retain10-second bounds. Existing server
receipt logic is unchanged: acknowledged receipts skip detail/ACK, while retained
unacknowledged originals support idempotent recovery.

Main passes66 frontend tests /three suites and44 PHP tests /519 assertions across
inbound service, ePostman durable ACK retry, inbound handling and inbox tests.
PHP target is freshly verified testing, uncached config, SQLite :memory:, array
cache/session, network disabled, read-only source/vendor and disposable storage.
Full lint passes0errors/46inherited warnings; typecheck110 has no new errors;
locale parity, whitespace and production build7,669 pass. No install/provider call.

Independent security accepts66 tests /three suites and the two-file source freeze,
verified before/after review:
`b342a06d14ed1ba1cade2bfc67716b4bfb7f1685cc4d5c03fb2ab60cf59f1d89`.
Runtime SHA256: `e2fb92146a86445b3e6c6b6919f1d8f7c152a9cbacad3e2e3408e6be3949d6a3`.
Test SHA256: `9c14abbbcecf929bde6ef5865df923a9bf56e1bbd9766710a60435e85bf52681`.
The current supplied inline thread is `PRRT_kwDOQ75Xa863n5_O` on PR446.
Optional CodeRabbit CLI boilerplate is not executed. Main retains publication;
new-head remote/CI status and final independent QA are recorded when verified.

## Final independent QA and publication gate

Independent QA accepts66 frontend tests /three suites, scoped two-file ESLint0,
whitespace and the identical two-file freeze. No blocking findings remain. QA
inspected the old10-second source directly; its optional independent baseline
replay was sandbox-blocked and an escalated attempt was aborted. No independent
baseline execution is claimed; the actual before-failing runs above are main's.
This optional replay does not limit the completed source and after-test review.

Only the two reviewed product/test paths and main-owned records are committed.
Main will publish to the existing PR446 branch, verify exact remote head and CI,
and resolve the current supplied thread after verifying the published correction.
No other requested finding is skipped and no optional CLI installation/invocation,
provider call, receipt migration, PR merge or deployment is performed.
