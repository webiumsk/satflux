# PoS editor settings and items round-trip

Status: implemented locally and independently accepted, 2026-11-06.
Baseline: owned origin/master `e584490d`; affected PoS/backend source is byte-identical to the deployed-branch candidate `76422c07`. The owner reports deployment; no production version, state or credentials are inspected.

## Accepted scope and invariants

The owner created two products in Satflux, enabled item selection in Light/Keypad PoS, and reports that BTCPay displays them correctly while reopening the Satflux editor shows unchecked item selection and no product list. Tipping is also unchecked in Satflux although BTCPay displays tips. Preserve the products and all unsupported/unknown settings; never save a guessed empty template or false default after an unsuccessful/incomplete read. Fix only the verified settings/read/edit/save contract. No real store writes, real payment, billing, provisioning or external-repository modification.

## Acceptance

- Reproduce the exact synthetic sequence: enable Light item selection and tipping, add two products, save, fully remount/reload through the actual AppsShow route, and show both saved checkboxes and both products.
- Verify real supported BTCPay detail/update response shape against pinned upstream/local evidence, including aliases, false/empty values and item templates.
- Preserve existing Static/Cart/Print behavior, tenant/store credentials and server authorization. Unsupported or missing data cannot erase existing products on a later edit.
- Focused frontend and PHP contract regressions plus appropriate existing gates pass. Independent QA/security verify the final frozen scope with synthetic fixtures only.

## Ownership

Main owns the contract, docs, integration and Git publication. Frontend owns `AppsShow.vue`, `PointOfSaleShow.vue`, `utils/posEditorConfig.ts`, necessary `types/btcpayApps.ts` changes and focused editor tests. Backend owns `AppResponseFormatter.php`, `StoreAppService.php`, `BtcPay/Apps/PosUpdatePayloadBuilder.php` and focused formatter/service/builder tests. Independent QA/security are read-only except disposable synthetic harnesses. Specialists never commit, push, merge or deploy and must preserve concurrent work.

## Verified cause and contract

The SPA wrapper loaded the store and apps list, then passed a list entry as an app prop. AppShowLayout correctly skipped another fetch when both props were present. The editor therefore received metadata rather than detailed PoS settings and displayed its false/empty defaults. The wrapper now requires the selected detail response, separately from sidebar metadata, and rejects obsolete navigation responses.

Pinned upstream evidence is BTCPay v2.4.7, commit `2868deedd9d911b715fec7306ea9cada36d86bff`: list/generic app models carry metadata; the typed PoS detail/update response includes six core flags, defaultView and items. Updates accept template rather than items and replace settings. Actual production host/version has not been queried. Evidence: [PoS response model](https://github.com/btcpayserver/btcpayserver/blob/2868deedd9d911b715fec7306ea9cada36d86bff/BTCPayServer.Client/Models/PointOfSaleAppData.cs), [Greenfield controller](https://github.com/btcpayserver/btcpayserver/blob/2868deedd9d911b715fec7306ea9cada36d86bff/BTCPayServer/Controllers/GreenField/GreenfieldAppsController.cs).

PoS cache overlays preserve omitted fields, while explicit false and empty arrays remain authoritative. Product items/template aliases represent one value and replace whole arrays. Malformed or null product data cannot replace a usable cached list. Optional top-level `config_read_errors` is transient response metadata: products errors or a failed live settings read block editor Save; the marker is neither persisted in merchant config nor sent upstream. Editor completeness checks also prevent missing detail fields from becoming false/empty saves. A flagged update response also blocks another Save until fresh settings are obtained; a populated stale cache is not proof of a successful live read. Unsupported tax fields remain omitted unless originally known or edited.

Canonical `tipText` supersedes the older local `tipsMessage` alias and survives partial updates and unrelated editor saves, including deliberate empty values. The existing customer-data request/formId mismatch is not changed: pinned host evidence does not support treating those fields as aliases. That contract needs a separate audit.


## Validation and independent review

The real AppsShow-parent regression failed before the detail-fetch correction (zero detail requests) and passes afterward. Authored frontend regressions pass 25/25. Backend regressions were red before correction; final focused service/formatter/builder plus existing AppTest pass 37 tests / 146 assertions. Main broadens this to app compatibility, scoped route binding, store dashboard and PoS cash/card gating: 96 tests / 320 assertions pass with no skips. PHP runs in a network-disabled disposable container, SQLite in memory, synthetic keys and array cache/session, without cached application configuration.

Independent QA accepts all nine frozen source/test paths. Actual Chrome with real Vue/Pinia/Axios and a synthetic adapter passes 10 scenarios plus an additional stable product-ID check, including creating two products, enabling tipping, saving and fully reloading the browser. There are no page errors or external requests. Independent QA also reruns PHP37/146. Independent security confirms no blocking finding in static review and passes frontend25/25 against the same frozen source. Independent security accepts the exact frozen scope with no confirmed blocking finding. Main full frontend passes 1,879 tests / 186 files; production build and manifest correction pass. The build retains its existing large-chunk advisory; no chunk limits are changed.

Targeted Pint (five PHP files) and PHPStan (three runtime files) pass with the existing baseline. Global lint passes with 48 warnings within the existing 52 limit; typecheck passes with 110 tolerated existing diagnostics and no new errors. Five-locale parity passes. Graphify AST-only update completes in the isolated worktree (16,878 nodes / 33,834 edges); the primary checkout graph is preserved. No baseline, warning limit, dependency or tool installation changes are made.

Main sorted runtime6 manifest SHA-256: `db71578adfbe8c2e3928af70990c79a0cb81e59977c2f1c4ffffdab9c0710df5`. Source/tests9: `57e944e5eca7c30ce8c07ad72f0020b2e9ea3f24f698e8e87b0095fb6f7165e9`. Independent QA's differently ordered manifest contains exactly the same nine path hashes. Browser reports, test logs and manifests remain private disposable evidence, outside committed source.

This validates the source and synthetic contracts. Actual production deployment/version, real merchant state and live BTCPay behavior are not queried or modified. Publication, merge and deployment have not occurred for this correction.
