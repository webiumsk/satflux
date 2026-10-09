# BTCPay 2.4.5 implementation handoff

Updated 2026-10-10. The user authorizes pushing only to their own repositories. The authenticated GitHub account is `webiumsk`; all three destinations below were verified as owned by that account with ADMIN access. Publish branches and draft PRs only to those repositories. Do not push to Kukks or any other upstream repository, merge PRs, publish releases, install production plugins, or upgrade production. The earlier interrupted push created neither proposed Kukks branch, as confirmed with read-only `git ls-remote`.

## Local repositories and commits

| Repository / checkout | Branch | Local compatibility commits |
|---|---|---|
| Satflux: `/home/peterhorvath/apps/bitcoin/satflux` | `fix/btcpay-245-wallets` | `03677d98` wallet setup/recovery; `ff025e63` webhook validation/reconciliation; `338fff46` website/POS/archived checkout; `bfaecfa1` audit and staging documentation |
| Webium plugins: `/tmp/satflux-webium-245` (worktree of the local plugin repository) | `fix/btcpay-245-compat` | `3d648d1` exact-host dependencies/builds; `eed885d` address origins; `1feb820` Cashu HTTP protection; `4cf0cb9` package/staging documentation |
| Kukks fork: `/tmp/satflux-kukks-245` (worktree of the local fork repository) | `fix/btcpay-245-wallets` | `10010a8` exact host; `25e4d4e` Blink; `a586d2c` NWC; `c4f2a8e` architecture/staging documentation; `6871d24` CI for the stacked PR |

Satflux starts from `origin/master` `16b0abff`, Webium from `origin/main` `85e1456`, and Kukks from upstream `master` `1b71357`. References were refreshed on 2026-10-10. The Kukks fork's `origin/master` is 57 upstream commits behind the implementation baseline. Local branch `chore/kukks-upstream-2026-10` points to `1b71357` for separate review of that prerequisite before the compatibility changes. Broad readiness of unrelated upstream plugins has not been verified.

Satflux's only remaining pre-existing working-tree edit is `public/og-image.webp`; it was excluded from every commit. The Webium host instruction symlink was restored to its tracked `.agents` target, preserving the materialized instruction directory under `/tmp/satflux-webium-host-instruction-backup-mejwk3he`. Its exact-host guard passes; no host source changed. The Kukks disposable host still reports the materialized instruction alias. Their committed gitlinks point to exact v2.4.5 `5d0745cae6be5d8210459e38813f317673aa97b8`.

## Completed validation

These results were recovered from the completed implementation runs; tests were not rerun solely for committing/documenting unchanged source.

- Full Satflux PHP suite: 1,551 tests / 6,554 assertions, no failures; one existing PHPUnit deprecation. Log: `/tmp/satflux-245-php-complete.log`.
- Focused wallet recovery/historical invoice suite: 115 tests / 444 assertions. Log: `/tmp/satflux-245-recovery-regressions.log`. Additional historical view regressions: 13 tests / 57 assertions, `/tmp/satflux-245-view-regressions.log`.
- Frontend: 661 existing tests plus 2 new POS tests. Logs: `/tmp/satflux-245-js-tests-2.log`, `/tmp/satflux-245-pos-tests.log`.
- PHPStan, Pint, baseline-aware TypeScript, ESLint (48 existing warnings), and Vite build passed. Logs use `/tmp/satflux-245-*`.
- Webium plugin suites: 474 passing tests; Blink/NWC: 143 passing tests; total 617. Nine changed plugins build and package against the exact host. Packages: `/tmp/satflux-245-packages`.
- Real isolated host: merchant/store authorization, address permission gates, NWC ownership/pending-invoice/private-relay denial, manual SEPA creation and settlement, archived authenticated reads, startup migrations/restart, and verified signed webhook delivery to isolated Satflux. It had no Bitcoin/NBXplorer backend or funded external wallets.

## Remaining work and readiness

Production upgrade readiness: **NO**. Funded address/NWC onboarding, payment, switch/reconnect, NWC notifications/load, Cashu mint/melt, subscriptions, paid Tickets/Raffle fulfillment, a legacy production-data migration rehearsal, and SamRock mobile pairing still need dedicated staging infrastructure and credentials. Do not run these against production to close the gap.

The implementation architecture, wallet matrix, package versions, dry-run reconciliation commands, staging deployment sequence, and rollback considerations are in [BTCPAY_2_4_5_IMPLEMENTATION.md](BTCPAY_2_4_5_IMPLEMENTATION.md). Resolve every uncertain provisioning hold before rolling Satflux back; older code does not enforce the journal safeguard. Preserve additive journal data, wallet configuration, pending invoices, webhook IDs/secrets, and Tickets migration history.

Prepared review descriptions are `/tmp/satflux-245-pr-satflux.md`, `/tmp/satflux-245-pr-webium.md`, `/tmp/satflux-245-pr-kukks-sync.md`, and `/tmp/satflux-245-pr-kukks-wallets.md`. These files are the source text for draft PRs in the verified `webiumsk` repositories.

## Published draft PRs

All pushes target only the verified user-owned repositories. No PR was merged and no release or production deployment was performed.

- [Satflux #389](https://github.com/webiumsk/satflux/pull/389), base `master`.
- [Webium plugins #32](https://github.com/webiumsk/BTCPayServerPluginsWebium/pull/32), base `main`.
- [Kukks fork upstream prerequisite #1](https://github.com/webiumsk/BTCPayServerPluginsKukks/pull/1), base `master`.
- [Kukks fork Blink/NWC #2](https://github.com/webiumsk/BTCPayServerPluginsKukks/pull/2), base `chore/kukks-upstream-2026-10`. Review the prerequisite first, then retarget this PR to `master` after its merge. CI now runs for the stacked base branch as well.

Check the PRs for current CI status; publication is not a staging-readiness sign-off.

## Blink retirement and review follow-up

Satflux now routes new Blink addresses to LNAddress Connect 1.0.3. Explicit legacy
address submissions migrate through the existing wallet replacement guard and
journal; reads do not change adapters. Webium PR #32 adds legacy BTC Blink aliases
without claiming custodial/USD configurations or competing with an installed
Kukks Blink assembly. Remove Kukks only after all monitored invoices drain and all
API-key/USD dependencies are migrated; it has no invoice-tracking blob to import.
See the updated implementation matrix and staging retirement sequence.

CodeRabbit's PR #32 findings are fixed in `49d4196`: credentials/fragments are
rejected by all three address origin checks and the exact-host build guard rejects
tracked modifications/deletions. CI also tests the guard with disposable fixtures.
Satflux PR #389's previous E2E failure was fixed in `0780667d`; CI run
`38004641805` passed PHP, frontend and all 19 browser tests.

Latest follow-up validation: 669 frontend tests, TypeScript and ESLint pass;
113 wallet/detector/validator tests (415 assertions), 13 migration/mode tests
(64 assertions), and 42 related store/alert/integrity tests (172 assertions) pass.
The three modified plugin suites pass 286 tests (Blitz 77, Flash 88, LNAddress
121); the disposable host guard regression passes. The 1.0.3.0 package is in
`/tmp/satflux-245-packages/BTCPayServer.Plugins.LnAddressConnect/1.0.3.0/`.
Webium Blink follow-up commit: `f3c293b`.
