# BTCPay 2.4.5 implementation and staging gate

Target: exact host tag v2.4.5, commit `5d0745cae6be5d8210459e38813f317673aa97b8`. Satflux started at latest `origin/master` `16b0abff`; Webium plugins at `origin/main` `85e1456`; Kukks changes at latest upstream `master` `1b71357`; the Webium Kukks fork is 57 commits behind that baseline, so upstream synchronization is a separate prerequisite PR. The earlier audit is a historical inventory, not the current readiness verdict.

**Production upgrade readiness: NO.** Repository fixes, builds, packages, mocks, and isolated host checks are available. Funded wallet onboarding/payment/switch/reconnect, Cashu mint/melt, subscriptions, and paid Tickets/Raffle fulfillment still require staging sign-off. No production wallet, invoice, API key, webhook, plugin installation, or configuration was changed.

## Architecture and wallet behavior

Address wallets use the existing merchant Greenfield payment-method API with their actual HTTPS LNURL metadata origin. Maintained handlers verify that an optional `server` matches that origin. No fake server, privileged wallet provisioning key, or merchant server permission is introduced. Legacy stored connection strings remain readable and are not rewritten in bulk; comparisons recognize an equivalent real origin.

NWC cannot safely acquire arbitrary HTTP parameters. Nostr/NIP05 supplies a typed store-authorized endpoint accepting only an NWC URI. It validates keys, relay syntax/count, store authorization, pending invoices, and the wallet, then persists only an NWC config with `AllowUnsafeConnection=false`. Satflux sends the merchant key from its backend. A missing endpoint can fall back to the legacy merchant API on older hosts; authorization and validation rejections never fall back to privileged credentials.

| Wallet | 2.4.5 configuration | Required package | Verification boundary |
|---|---|---|---|
| Coinos / LnAddress | `type=lnaddress;ln-address=merchant@coinos.io;server=https://coinos.io;` | LnAddress Connect 1.0.2 | Parser/mocks pass; real host accepts origin through the permission gate, invalid test address then fails wallet validation; funded wallet pending |
| Blitz | Its address domain as real HTTPS origin | Blitz 1.0.1 or LnAddress Connect 1.0.2 alias | Same boundary; legacy address parser retained |
| Flash | Its address domain as real HTTPS origin | Flash 1.0.1 or LnAddress Connect 1.0.2 alias | Same boundary; legacy address parser retained |
| Blink address | `type=blink;ln-address=name@blink.sv;server=https://blink.sv;` | Blink 1.1.3 | Existing address/custodial tests pass; real privilege gate exercised; funded wallet pending |
| NWC | Original URI through `PUT /api/v1/stores/{storeId}/nwc/connection` | Nostr/NIP05 1.1.22 | Real authorization, foreign-store denial, pending-invoice refusal and private-relay denial pass; public wallet RPC/payment/reconnect pending |

Wallet updates preserve the previous receiving configuration instead of deleting it first. Satflux serializes its wallet edits with invoice creation, checks all monitored unsettled invoices (including archived invoices and Processing), and blocks changing their receiving wallet. Core's listener consults current store configuration, so postponing a switch is safer than attempting to keep multiple receiving nodes alive without host support. External/admin configuration writers must also be quiesced during a switch.

Existing-wallet address/NWC changes have a durable encrypted provisioning journal committed before the remote write. Confirmed rejection rolls back local selection. Wallet writes are not retried after transport loss. Unknown results hold further edits and new Satflux invoices until readback confirms the intended config; an old read alone cannot prove that a write is no longer in flight. The desired secret is erased from terminal journal entries. The scheduler and wallet read endpoint can recover a confirmed remote update after process restart or local commit failure.

Run `php artisan btcpay:reconcile-wallet-updates --dry-run` to list holds without exposing secrets. Default reconciliation reads BTCPay and updates only local state for confirmed desired configurations. If the old config remains after a timeout, an operator must first verify that no remote request can still commit, then use `--store=LOCAL_ID --confirm-rejected`; the command also checks that the old config matches. This deliberately favors a temporary checkout hold over selecting a potentially wrong receiving wallet. The shared cache must support distributed locks; the lock lease covers the existing bounded HTTP/retry paths. The privileged browser bot now accepts Aqua/Boltz descriptors only and excludes address/NWC wallets.

## Webhooks and historical invoices

Set `BTCPAY_WEBHOOK_BASE_URL` explicitly to the reachable callback service before deployment. `APP_URL` is not used as a fallback. Generated URLs must be absolute HTTP(S) and contain no authority credentials, query, or fragment. Private/plain HTTP origins require an exact `BTCPAY_WEBHOOK_PRIVATE_ORIGINS` entry and a matching narrow core exception. Actual delivery-time DNS checks remain in BTCPay's SSRF transport.

Registration reuses an existing canonical ID and locally known signing secret. Orphans can be adopted in place with a new secret; duplicates, disabled subscriptions, and a known previous destination require review. Creation POSTs are not retried after an uncertain response, preventing duplicate subscriptions. Existing production destinations are never automatically migrated or deleted.

Read-only reports:

```sh
php artisan btcpay:reconcile-webhooks --dry-run --json
php artisan btcpay:reconcile-webhooks --dry-run --server-stores --deliveries --json
```

Actions are recommendations only: **retain** a verified canonical/integration entry, **update** a locally owned previous destination while preserving ID/secret, **disable** a reviewed duplicate, or **investigate** unknown/private/disabled/unreadable entries. The command never sends/redelivers callbacks. It strips credentials and query strings from destinations and classifies delivery failures without echoing credential-bearing errors. Local stores are read with their merchant keys; unowned accessible stores use the configured operator key.

The historical inventory found 29 enabled subscriptions across 42 accessible stores: 14 localhost destinations require ownership/purpose review; 15 public-looking destinations require DNS/TLS/delivery verification before retention. This inventory was not refreshed against production during implementation. The isolated real-host report recommends **retain** for its test callback and records the last successful delivery. Do not infer that the production localhost entries are safe to delete.

Store websites accept only absolute HTTP/HTTPS URLs. POS examples omit unsupported per-request `notificationUrl`; app-level notification configuration remains supported. Satflux business-document payment/status reads use authenticated Greenfield access rather than anonymous historic checkout. Old archived settled invoices still render the paid result. Archived unpaid checkouts are not reused by PDF flows or silently replaced while still monitored; the pay page shows its own failure state rather than linking to an unavailable archived checkout. Existing paid-token revocation is preserved. Subscription URLs used for new purchases remain short-lived checkout links; long-term billing records use authenticated status processing.

## Packages and network policy

Webium packages: Raffle **1.3.2.3**, CashuMelt **1.3.1.1**, SEPA **0.8.1**, Tickets **2.0.1**, LnAddress Connect **1.0.2**, Blitz **1.0.1**, Flash **1.0.1**. Kukks packages: Blink **1.1.3**, Nostr/NIP05 **1.1.22**. Host minimum is 2.4.5. Build scripts/CI pin the exact host and use its packer. NuGet changes align Npgsql 10.0.3, QRCoder 1.8.0, EF/Identity 10.0.12, Roslyn 5.9.0, and required test runtime dependencies. No plugin migration IDs, runners, history identities, fulfillment ownership, or Tickets legacy routes changed.

Cashu mint/resolver/D21 and Blink clients use HTTPS by default, no redirects, 30-second HTTP timeouts, and host DNS-pinned SSRF protection. NWC uses guarded WebSockets with cancellation and a 10-second handshake deadline. Its per-operation transport replaces the upstream pool, whose socket construction could not accept the guarded HTTP invoker; staging must assess connection load and listener recovery. Existing protocol encryption logic is preserved.

Internal services need explicit operator authorization:

- Core: `BTCPAY_SSRFEXCEPTIONS` with an exact host/IP and port; never `BTCPAY_DISABLESSRFPROTECTION`.
- Plain HTTP plugin service: exact origins in `BTCPAY_PLUGIN_HTTP_ALLOWED_HTTP_ORIGINS`.
- Plain WS NWC relay: exact origins in `BTCPAY_NWC_ALLOWED_WS_ORIGINS`.
- Private/HTTP Satflux callback: `BTCPAY_WEBHOOK_PRIVATE_ORIGINS`.

Hostname exceptions trust their resolved addresses, so use operator-controlled names and the narrowest ports. LNURL address plugins keep their existing stricter public HTTPS/DNS pinning and callback/redirect checks. Wallet previews, NWC URI bodies, relay query tokens, and malformed parameter values are no longer logged.

SamRock review: inspected upstream `99a82e72ad24c459a4989173a89c4dc14d23bcb8`. Its raw project still targets net8 and must be retargeted to net10 when compiling against the net10 host; temporary retargeting builds successfully. This is a source check, not a verified SamRock release or phone-pairing test. Use an appropriately built package and test pairing, Boltz import, pending-invoice behavior, and restart in staging. Boltz's own v2.4.5 release source builds against this host; its plugin version is independent of the host version. EmailConfirm's existing suite passes. No unrelated plugin upgrade is included.

## Validation evidence

- **Source/build:** nine changed plugins compile against exact 2.4.5. Nine staging `.btcpay` packages produced; none installed in production. Existing obsolete/nullability/schema SQL warnings remain. Tickets has no standalone unit suite; startup/migration/API checks were run instead.
- **Mock/unit:** all seven Webium suites and Blink/NWC suites pass: **617 tests**. Includes real loopback socket rejection, parser/origin, exact exception, secret-error, and existing settlement logic regressions. Satflux full PHP suite passes **1,551 tests / 6,554 assertions**; focused recovery/historical invoice suites also pass. PHPUnit reports one existing deprecation. Frontend **663 tests** pass (661 existing and 2 new POS tests). PHPStan, the baseline-aware TypeScript check, ESLint (existing warnings), Pint, and Vite build pass.
- **Real isolated 2.4.5:** disposable PostgreSQL and Satflux SQLite; all nine plugins load, migrations apply on a fresh DB and remain stable across restart. Actual Greenfield merchant/store creation without server permissions, NWC ownership denial, serverless rejection, real-origin permission acceptance followed by invalid-address validation, HTTP(S) websites, webhook creation/secret concealment, manual SEPA invoice creation and settlement, NWC pending-invoice protection/private-relay rejection, authenticated archived invoice reads, anonymous archived checkout denial, and real HMAC webhook delivery to Satflux were exercised. A private callback first failed with SSRF rejection; an exact `127.0.0.1:15447` exception on the disposable host then delivered HTTP 200 and Satflux stored a verified event. Cashu/Tickets/SEPA API reads survive restart.
- **Limitations:** no Bitcoin/NBXplorer backend or funded external wallets were connected. The isolated host's server-info sync endpoint fails when NBXplorer is absent; this is not a full deployment smoke test. Public wallet connection/payment, NWC notifications/reconnect/load, Cashu mint/melt, subscription payment flows, paid Tickets/Raffle fulfillment, legacy production-data migration, and SamRock mobile pairing remain staging requirements. Mock success or source compilation is not evidence that these paths work live.

## Staging deployment and rollback

1. Back up BTCPay core/plugin databases, Satflux DB, wallet/store configuration, secrets, and current packages. Use a private sanitized 2.4.4 database copy for migration rehearsal. Keep production untouched.
2. Configure the explicit staging webhook base and documented exact private exceptions only where required. Test callback DNS/TLS from inside the BTCPay runtime. Review the reconciliation report before changing any destination; preserve intentional private integrations, IDs, and signing secrets.
3. Apply the additive Satflux journal migration. Deploy compatible Satflux code with shared distributed locks/scheduler. Pause wallet changes and invoice creation during the host/package transition; do not rotate merchant privileges.
4. Boot exact BTCPay 2.4.5 with the matched packages listed above, retaining plugin identities/history. New packages require 2.4.5 and should not be installed into 2.4.4. Verify migration histories and restart before reopening onboarding.
5. Exercise every wallet with dedicated staging credentials: initial setup, unchanged legacy config, switch blocked by a pending/archived monitored invoice, switch after settlement, reconnect, dropped responses, local save failure, and process restart. Confirm funds go to the intended wallet and recover holds without bypassing validation.
6. Verify signed webhooks, invoice state transitions, subscription idempotency/renewals, Cashu payouts, SEPA confirmations, paid Tickets/Raffle bundles, historical/archived invoices, and SamRock/Boltz separately. Record live evidence and remove the production-upgrade block only after the critical paths pass.

Rollback under maintenance: resolve all provisioning holds first. Old Satflux code does not understand the journal, so rolling it back while a hold exists would remove its invoice safeguard. Keep the additive table unless a coordinated DB restore is required. Restore the matched host/plugin set and database backup if host/plugin migrations require it; never attempt a blind schema downgrade. Preserve wallet secrets, outstanding invoices, webhook IDs/signing secrets, and Tickets' fork history. Re-run callback and invoice checks before enabling traffic.
