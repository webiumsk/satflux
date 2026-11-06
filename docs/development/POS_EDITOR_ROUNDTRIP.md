# PoS editor settings and items round-trip

Status: implemented locally and independently accepted, 2026-11-06.
Baseline: owned origin/master `e584490d`; affected PoS/backend source is byte-identical to the deployed-branch candidate `76422c07`. The owner reports deployment; no production version, state or credentials are inspected.

## Accepted scope and invariants

The owner created two products in Satflux, enabled item selection in Light/Keypad PoS, and reports that BTCPay displays them correctly while reopening the Satflux editor shows unchecked item selection and no product list. Tipping is also unchecked in Satflux although BTCPay displays tips. Preserve the products, supported PoS settings and unmodeled local cache/item fields; never save a guessed empty template or false default after an unsuccessful/incomplete read. Fix only the verified settings/read/edit/save contract. No real store writes, real payment, billing, provisioning or external-repository modification.

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


## Additional settings preservation before publication - 2026-11-06

The owner conditions publication on preserving other settings. Re-audit every writable field in the pinned PointOfSaleBaseData plus inherited metadata against current serialization before pushing. Two confirmed gaps require bounded correction: customTipPercentages and formId are returned by typed GET but omitted by the request whitelist, and unchanged empty/null visible values can be replaced by rendered UI defaults during unrelated edits. Preserve explicitly known unchanged values, retain intended user edits and clears, and keep unknown server fields out of the outgoing allowlist. Existing customer-data legacy selection is not reinterpreted as formId. Archived omission is safe: pinned UpdatePointOfSaleApp only changes archived when a value is supplied.

Backend owns the existing request builder and its unit/Feature regressions; frontend owns the existing page, narrow helper and frontend regressions. Main owns docs/Git, independent QA/security remain read-only. Acceptance adds a complete non-default configuration roundtrip after a name-only edit, explicit empty/null values and deliberate edits, using replacement-style synthetic upstream behavior. Freeze new source hashes, repeat appropriate checks and independent review, then push only this owned branch. Existing review and test results above are attributed to ddc5d874, not the new correction. No merge or deployment is authorized.


## Final preservation review

The pinned model has 20 writable PoS base settings. All are mapped after adding customTipPercentages and formId, including explicit null/empty resets. Identity fields remain excluded; archived omission preserves upstream state. Unknown local cache/item fields are retained without a general outgoing-field pass-through. This assurance covers the pinned supported API contract, not arbitrary future or extension settings.

The frontend keeps an explicit allowlist of raw known scalar values and their rendered baseline. An unrelated edit preserves unchanged empty/null values and omits untouched defaults for absent optional fields. Boolean fields retain normalized types; entitlement-omitted advanced fields stay omitted. Successful saves advance the baseline using immutable submitted values and known confirmed fields, so two saves and edits during a pending request remain correct. Store/app identity, app reference and hydration generation prevent obsolete responses from affecting replacement details. Native validation permits supported blank button texts. Intentional edits and clearing still work.

Independent security accepts runtime6 `1721c2cbbcb2b129c2862c7df27143662ed82323bc1403a696051de0a6477cd7` and source/tests9 `60687a9e64b9a4a3571184a2f06d741b58aaac0c55f90a27216df6a8c7aefaa3`, with no unresolved finding. Its focused frontend37/37 and synthetic boundary probes8/8 pass. Independent QA passes five preservation scenarios through real Vue/Pinia and actual PHP StoreAppService/builder/formatter with replacement-style synthetic host semantics. Authored frontend37/37, independent PHP43/386 and main broadened PHP102/560 pass. Final full frontend passes 1,891 tests / 186 files. Lint48/52, typecheck110 with no new diagnostics, five-locale parity, production build and manifest correction pass. Targeted Pint/PHPStan retain their existing baselines. AST-only update completes with 16,891 nodes / 33,851 edges. Source hashes remain identical to the independently reviewed freeze. Independent QA replays original10 plus preservation5 browser scenarios on those exact bytes; all15 pass, with zero page errors or external requests.

Preservation is distinct from host normalization: pinned title/tip/button nulls legitimately return host defaults. The three tax fields are tested for local/cache preservation without claiming official v2.4.7 host support. The legacy customer-data selector is not reinterpreted as formId; existing formId is preserved, while selector compatibility remains a separate follow-up. Production state/version is unqueried and unchanged. The owner has authorized publication after these checks; merge and deployment remain separate.


## Integrate merged landing-page base - 2026-11-06

The owner reports PR431 conflicts after master advances to `2154101e` (merged PR430). Integrate that base into the existing published PoS branch without rewriting its history or merging the PR into master. Resolve only the three shared project-log prefixes and AppsShow import conflict. Preserve both documentation additions and their common history; retain the exact master template/scroll/footer shell and the published guarded PoS script plus its required AppScrollPane import. Other PoS source remains unchanged, and other incoming landing paths retain master bytes.

Main owns Git/index, docs and final integration. Frontend owns only AppsShow conflict resolution; independent QA/security remain read-only. Acceptance requires no conflict markers/index entries, exactly the original13 scoped paths versus the incoming master, retained PoS settings safety and footer scroll behavior, relevant isolated PHP/frontend checks, independent frozen-source approval, and remote verification after push. No PR merge, deployment, new settings scope or production state access is authorized.


Final merge validation passes: frontend1,948/192, PHP127/784, lint48/52, type110 without new diagnostics, locale parity, production build and manifest correction. AST update completes with16,977 nodes/33,938 edges. Independent QA accepts focused71/3 and Chrome18/18 (original10, preservation5 and responsive footer3); security accepts focused45 and exact parent composition. No page errors, horizontal overflow or external browser requests occur. Desktop/tablet footer is visible, mobile footer hidden, toolbar stays outside the scrolling content, and the version request is shared. PHP runtime source and locked dependencies remain unchanged, so accepted Pint/PHPStan evidence carries forward.

Merged AppsShow SHA-256: `79390574cdaf917406b76a569cbb6f090714452875bc36bbcca24a0ea17a1f98`. Sorted runtime6 manifest: `4a40f57696303d5970c961d6b451ed74a7fb94f6e9aadcf308701778b65cf9fb`; source/tests9: `42586cbbcd088707a87b673b1d60dbc38f2ec1bc8405e1243881a6733c4252e6`. Both independent reviewers bind acceptance to these bytes and master `2154101e`. Exactly13 scoped paths differ from that base, with no unmerged entries. Publication will verify the remote feature head and PR conflict status; no merge into master or deployment is performed.
