# Independent QA: Satflux PR #411 CodeRabbit fixes

Reviewer: actual `cr411_qa` specialist, independent of implementation authors.
Repository: Satflux isolated `fix/btcpay-247-compatibility` worktree.
Base revision: `7bb1677dd51a971e4337b6e7318c1f5d97dd1a16` plus bounded working diff reviewed 2026-10-30.

## Disposition

No blocking finding in the reviewed change. Backend payment sound validation is narrowed to HTTP(S), retaining nullable/max500. Feature tests cover accepted HTTP/S/null/empty and rejected FTP/javascript/data/relative/overlong values, asserting no outbound requests on rejection. Backend execution is owned by the coordinator; this QA reviewer does not claim PHP execution.

Frontend duration controls are disabled when inactive or inaccessible; invalid inactive invoice/monitoring fields are omitted without model mutation, valid inactive fields retained, seconds/minutes conversions unchanged. Active editable fields retain range/step validation and preserve optional clearing; nullable empty values are retained on active and inactive tabs. Existing inaccessible payment field stripping remains.

## Reproducible and historical evidence

- `node node_modules/vitest/vitest.mjs run resources/js/__tests__/btcpay247Forms.test.ts resources/js/__tests__/btcpay247SettingsDurations.test.ts resources/js/__tests__/btcpay247Crowdfund.test.ts`: Final frozen source: 3 files, 40 tests passed, 5.73s.
- Historical one-off evidence, not reproducible from this repository: the independent component fixture compiled the published-base Payment component and reproduced inactive monitoring zero invalidity (`form.checkValidity=false`, `disabled=false`, `willValidate=true`). The uncommitted `browser.cjs` fixture is held only in reviewer `cr411_qa`'s disposable workspace; it has no durable repository location and may be removed during cleanup.
- The same historical fixture passed 74 assertions against the reviewed component across 24 active/inactive, permitted/inaccessible and value combinations. It covered zero, max+1, min/max valid, fractions and NaN; inactive native submit succeeded, active invalid numeric native submit was blocked, and blank remained optional. Other Select/Badge UI was stubbed. These are recorded observations, not runnable commands; use the committed shared-form harness below to reproduce current behavior. No Laravel, environment file, wallet, merchant credentials or live network API was used.
- `node resources/js/__tests__/btcpay247SettingsDurations.browser.mjs`: final frozen actual parent/child shared-form browser harness, 121 assertions passed, exit 0. Includes optional active/inactive clearing and payload preservation. Temporary Vite cache only, explicit synthetic API and access stubs.
- `git diff --check`: passed.
- Initial Chromium launch failed sandbox socket creation. Same synthetic harness succeeded using normal approved escalated execution; no browser installed.

## Exact-host limitation

Pinned upstream `GreenfieldStoresController.UpdateStore` merges omitted request fields with persisted settings before unconditional duration validation. Omitting consumer-invalid legacy zero removes native/local validation obstruction but cannot guarantee BTCPay accepts an update while persisted upstream settings themselves violate its bounds. Merchant must explicitly correct Payment durations; no automatic clamp/remote rewrite is appropriate. Tests using the API stub establish client behavior, not that upstream-invalid store save succeeds.

## Review boundaries

No source writes, dependency installation, production environment access, container changes, Git publication or deployment. Root graph query was already completed by coordinator; target worktree graph absent, no graph build installed or run. Full remote CI remains coordinator-owned and must be attributed to exact new head, separately from this working-tree review.

## Historical reviewed frontend SHA-256 at 0e0098db

- StoreSettings.vue: `362d7c960ffff68614e7e68b025508789713f10674d65ee55997ec92be766461`
- StoreSettingsPayment.vue: `27e98fbc69860da3982b06c92ba792ad007f2021118ead2bffb20ecbeffcde37`
- btcpay247Forms.test.ts: `b6e04cc708636544860d76560ac3b7f53058c068a7fb01721ee0366aa0a6b70a`
- btcpay247SettingsDurations.test.ts: `460591f2c59fd0ef3c406f4fa2ea8e9a4fd7705f5b0a8c493c794ae59789439d`
- btcpay247SettingsDurations.browser.mjs: `3108bf2b236f6927cb582f47bdfe49b148e01b4fae6855d779fe7a060f1e4993`

Second freeze supersedes earlier test counts from an interim required-field variant. Coordinator caught and removed that unintended behavior restriction before final acceptance. Reviewed scoped state/roadmap/decision and implementation follow-up text accurately records exact-host limitation and stale-vs-fresh CI separation.
