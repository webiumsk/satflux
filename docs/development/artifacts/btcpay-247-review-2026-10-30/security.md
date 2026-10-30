# Independent security review - PR411 CodeRabbit follow-up

Reviewer: actual Codex security specialist `/root/cr411_security`, independent of implementation authors.
Repository: webiumsk/satflux, branch fix/btcpay-247-compatibility.
Reviewed base: 7bb1677dd51a971e4337b6e7318c1f5d97dd1a16 plus the eight frozen working-tree files hashed below. Frontend author confirmed final second source freeze after preserving optional/nullable clearing; main confirmed backend source scope. No implementation, Git publication, deployment or production state changes by reviewer.

## Disposition

Accepted for the bounded CodeRabbit source fixes, with no introduced blocking security findings. This is source-review acceptance, not merge/deployment authorization or a claim of live-host execution.

- StoreUpdateRequest line49 restricts payment_sound_url to HTTP(S), retaining nullable/string/max500 and pre-validation empty-to-null handling. The change narrows checkout asset URL policy; it does not introduce a URL fetch or establish SSRF prevention. Feature cases assert invalid schemes/relative/oversized values fail before remote writes and HTTP(S)/empty/null pass.
- StoreSettingsPayment disables invoice/monitoring controls when their tab is inactive or payment customization is inaccessible. Active editable controls retain their original optional clearing and min/max/whole-minute step validation for entered numbers. No new required constraint remains.
- StoreSettings omits only invalid inactive duration payload fields before seconds conversion. Number.isInteger excludes NaN/infinity/fractional values, and explicit bounds cover invoice1/monitoring10 through34560minutes. Valid inactive numbers and nullable empty-string/null inputs remain. The form model is not clamped or rewritten. Added duration empty-string request cases cover actual HTTP middleware normalization to null.
- Existing frontend paid-field stripping remains unchanged and applies independently of the tab. No entitlement grants, subscription changes, pricing changes, payment execution or cancellation logic are added.
- Existing route auth:sanctum, RequireVerifiedEmail, user throttle, EnsureStoreOwnership and store.updated audit remain unchanged. Ownership middleware retains the existing audited admin/support exception. Controller obtains the owner's scoped merchant API key and StoreService forwards via withUserKey. This review does not treat frontend paid-field stripping as a new server authorization boundary.
- Server duration validation remains in place if native browser constraints are bypassed: integer second bounds60/600 through2073600. Existing nullable semantics and default empty-string-to-null middleware remain unchanged; upstream merge can retain existing values for null input.
- No new raw provider ID exposure, auth/session/CSRF modification, secret handling, webhook trust, encrypted proof/key handling or browser encryption change occurs in this diff.
- Standalone browser harness uses synthetic store/API stubs, loopback ephemeral Vite, configFile:false/envFile:false, temporary cache and cleanup. It starts no Laravel app and reads no environment/credentials. It is a consumer/UI regression, not an end-to-end host test.

## Retained host limitation (informational, not introduced by these fixes)

The retained provider submodule source GreenfieldStoresController.cs lines126-133 merges a partial update with the existing store template before Validate. Lines368-373 validate all duration ranges unconditionally. Therefore omitting persisted legacy monitoring0 or an over-limit invoice duration removes the browser/local request obstruction but cannot promise a successful BTCPay2.4.7 save while the host itself still contains invalid data. The merchant must explicitly correct invalid Payment values, or a separately scoped host/data repair must be reviewed. Do not silently default/clamp the merchant's settings.

Provenance limit: the retained host C# file is readable, but its Git worktree pointer references cleaned temporary metadata; fresh `git rev-parse HEAD` fails. The source behavior is directly observed; exact host revision attribution relies on the earlier pin/manifest evidence and is not newly verified by this reviewer.

## Verification performed by this reviewer

Read-only source diff and trust-boundary trace through routes/api.php, EnsureStoreOwnership, StoreSettingsController and StoreService. Inspected existing own-store/non-owner403 tests and the new nullable/scheme/length, hidden/active/inaccessible duration regression tests. Inspected the actual browser harness isolation and cleanup. `git diff --check` passes on final frozen source.

Graphify query executed from the existing root graph for store-update ownership/feature access; it returned unrelated heuristic settings nodes. Targeted reads were used. The target worktree has no graph. No graph installation/rebuild or source mutation was performed.

No PHPUnit, Vitest or Chromium runtime execution by this reviewer; main/QA/author results remain separately attributable. No Docker, network, real store, wallet, token, database, external URL or production environment was used. Unresolved introduced security findings: none.

## Reviewed source hashes (SHA-256)

- `app/Http/Requests/StoreUpdateRequest.php`: `8967bc6ffae5289729b53d3296c871f96ca01f43f8705c18d05a68635e3094d2`
- `app/Services/BtcPay/Apps/CrowdfundRecurrence.php`: `06f600086be89e3931fa1c49054572df4853dbf2572433320b32a902233a37f9`
- `resources/js/components/stores/StoreSettings.vue`: `362d7c960ffff68614e7e68b025508789713f10674d65ee55997ec92be766461`
- `resources/js/components/stores/StoreSettingsPayment.vue`: `27e98fbc69860da3982b06c92ba792ad007f2021118ead2bffb20ecbeffcde37`
- `resources/js/__tests__/btcpay247Forms.test.ts`: `b6e04cc708636544860d76560ac3b7f53058c068a7fb01721ee0366aa0a6b70a`
- `resources/js/__tests__/btcpay247SettingsDurations.test.ts`: `460591f2c59fd0ef3c406f4fa2ea8e9a4fd7705f5b0a8c493c794ae59789439d`
- `resources/js/__tests__/btcpay247SettingsDurations.browser.mjs`: `3108bf2b236f6927cb582f47bdfe49b148e01b4fae6855d779fe7a060f1e4993`
- `tests/Feature/Btcpay247ValidationTest.php`: `5b0759d3454cdb307cbe59dd487c1e65e57425b5e623a68577677decd4229ab1`

Reviewed eight-file hash-manifest digest (SHA-256): `646f804b1a0415db08e30a0f60c8640c05d66493babdbc9008bc5f12459917c4`.
