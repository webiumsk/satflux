# BTCPay Server 2.4.5 compatibility audit

Historical baseline inventory. See [the implementation report](BTCPAY_2_4_5_IMPLEMENTATION.md) for the subsequent fixes and current staging gate.

Audit date: 2026-10-09. Satflux: `16b0abff` (master).

**Upgrade readiness: blocked by merchant Lightning setup compatibility and webhook destination review.** Plugin source builds also need dependency alignment. No production configuration, payment, wallet, API key, or plugin installation was changed during this audit.

## Scope and evidence

- Compared official BTCPay tags `v2.4.4` and `v2.4.5`, with 2.4.5 at `5d0745cae6be5d8210459e38813f317673aa97b8`.
- Reviewed Satflux Greenfield clients, wallet provisioning, store settings, invoice/payment links, subscriptions, POS/crowdfund callbacks, and plugin bridges.
- Built isolated copies of the eight Webium plugins from `BTCPayServerPlugins` at `4185325ec06969d36d9d1f9777f83dae8c58032d` (current local SEPA feature branch). Reviewed and built local Blink, Stripe, and Nostr/NIP05 from `BTCPayServerPluginsKukks` at `a5bf692e7e3e4d83fda0601eff06e78662d56e7f`.
- Reviewed and built Boltz release tag `v2.4.5` at `9168cad4ae13659006d8d6bdd43bcf485c1aa355`, and SamRock source at `99a82e72ad24c459a4989173a89c4dc14d23bcb8`. Boltz's release number is separate from the BTCPay host version.
- Read-only requests to the configured server `satflux.org`: server info, stores, payment-method configuration, and webhook configuration. The server reports **2.4.4**. Inspected all **42 stores returned to the configured API key**, with no read failures. No credentials or complete wallet connection strings were printed or saved in this report.
- Ran **107 Satflux tests / 337 assertions**, all passing. These use mocks and do not prove compatibility with a running 2.4.5 host.
- Ran **458 plugin tests**, all passing after temporary dependency alignment: Blitz 74, Flash 85, LnAddress 102, EmailConfirm 4, Raffle 16, CashuMelt 66, SEPA 111. Tickets has no standalone test project in the inspected checkout.
- Executed a local reproduction against the compiled 2.4.5 host: five merchant wallet formats are rejected; private HTTP destinations are blocked; a precise hostname/IP-and-port exception permits a loopback request.

Build copies, logs, result JSON, and the reproduction are under `/tmp/satflux-plugins-245-audit`; downloaded host source is under `/tmp/satflux-btcpay-245-audit`. These are temporary artifacts, not installed packages.

## Confirmed conflicts

### 1. Merchant wallet creation and switching rejects serverless connection strings

In 2.4.4, `Extensions.IsSafe(client, connectionString)` returns true when there is no `server` field. In 2.4.5, `Extensions.IsSafeLightningConnectionString(connectionString)` returns false in that case. `LightningLikePaymentHandler.ValidatePaymentMethodConfig` then requires `btcpay.server.canmodifyserversettings` before calling the plugin's handler.

Satflux deliberately uses merchant API keys for `PUT /api/v1/stores/{storeId}/payment-methods/BTC-LN`. Its formatters produce:

| Wallet | Current format | Actual 2.4.5 merchant validation |
|---|---|---|
| LnAddress / Coinos | `type=lnaddress;ln-address=user@domain;` | Rejected |
| Blitz | `type=blitz;ln-address=user@blitzwalletapp.com;` | Rejected |
| Flash | `type=flash;ln-address=user@flashapp.me;` | Rejected |
| Blink address mode | `type=blink;ln-address=user@blink.sv;` | Rejected |
| NWC | `type=nwc;key=nostr+walletconnect:...` | Rejected |

The reproduction exercised the actual `ValidatePaymentMethodConfig` method with a merchant authorization service and asserted the server-settings permission error for each format. An explicit public HTTPS `server=` passes the preliminary safety check; this alone does not prove the complete plugin setup succeeds.

**Blink syntax remains supported by the plugin.** Kukks' current [README and handler](https://github.com/Kukks/BTCPayServerPlugins/tree/master/Plugins/BTCPayServer.Plugins.Blink) still support `type=blink;ln-address=yourname@blink.sv;`. The conflict is the host's permission gate for a new or changed configuration submitted with a merchant key. The same syntax can work on 2.4.4, in an unchanged existing 2.4.5 configuration, or when saved by a 2.4.5 server administrator (subject to normal plugin/network validation). A working admin-UI or existing-wallet test does not exercise Satflux's new merchant-key setup path.

Relevant Satflux code: `WalletConnectionValidator` formatters, `WalletConnectionService::attemptBtcpayWalletSync`, and `BtcPay/LightningService::connectLightningNode`.

Live inventory contains **nine external address-mode configurations without `server=`**: Blink 5, LnAddress 3, Flash 1. A separate legacy/internal configuration is excluded from this count.

**Existing unchanged configurations are preserved:** `LightningPaymentMethodConfig.AllowUnsafeConnection == null` retains legacy behavior, and validation preserves the old flag when the connection string is unchanged. Recreating, switching, or changing the string triggers the rejection. This is not evidence that all existing wallets stop on restart.

Required work: resolve merchant setup for each affected plugin before upgrading. For address plugins, evaluate an explicit real public origin together with the plugin's own callback safety checks. NWC needs a dedicated solution: its URI parser consumes the remainder after `type=nwc;key=`, so appending arbitrary connection parameters is not a general fix. Do not grant merchants server-admin permissions or route arbitrary user-provided connection strings through the server key.

### 2. Enabled localhost webhooks conflict with the new HTTP guard

The 42-store inventory contains **29 enabled webhooks**: **14 point to `localhost`**, and 15 use public-looking hostnames. All localhost entries are enabled. Their intended purpose and current delivery success were not determined.

2.4.5's webhook clearnet client uses `UseSSRFProtection()`. A local test of that actual extension confirms loopback requests fail by default and succeed with an exact IP-and-port exception. Existing localhost destinations therefore conflict with the default transport policy. They may also already be stale or invalid: localhost means the BTCPay host/container, not a developer's Satflux instance.

`WebhookService::getWebhookUrl()` constructs destinations from `APP_URL`. This workspace currently has `APP_URL` pointing to localhost; this is a local configuration observation, not proof of the deployed Satflux application's setting.

Required work: identify whether these 14 entries are development leftovers or intentional local services, then repair or retire them as appropriate. For intentional private services use narrowly scoped `BTCPAY_SSRFEXCEPTIONS` entries and verify from inside the BTCPay container. Public-looking domains also require host-side DNS verification because split DNS can resolve them privately. No remote webhooks were modified.

### 3. Plugin project package pins prevent fresh builds

Unmodified Raffle, CashuMelt, Tickets, and SEPA fail restore/build against 2.4.5. Successful source compatibility checks required the following changes **only in temporary copies**:

| Project | Required alignment | Result after alignment |
|---|---|---|
| Raffle | Npgsql EF provider 10.0.3; QRCoder 1.8.0 | Builds; 16 tests pass |
| CashuMelt | Npgsql EF provider 10.0.3; QRCoder 1.8.0 | Builds; 66 tests pass after test dependency alignment |
| SEPA | Npgsql EF provider 10.0.3 | Builds; 111 tests pass |
| Tickets | Npgsql EF provider 10.0.3; QRCoder 1.8.0; Identity EF 10.0.12; Roslyn package family 5.9.0 | Builds |

Raffle tests additionally require matching logging/data-protection/EF packages and HtmlSanitizer 9.2.1039. CashuMelt tests additionally require NBXplorer.Client 5.0.11 and MailKit/MimeKit 4.18.1.

These are not all new 2.4.5 regressions: 2.4.4 already required Npgsql 10.0.3, QRCoder 1.8.0, and Roslyn 5.6.0, above the plugin pins. 2.4.5 raises Roslyn to 5.9.0 and several other package versions. Tickets' umbrella `Microsoft.CodeAnalysis` 5.3.0 creates an exact-version Workspaces conflict, so raising only one CSharp reference is insufficient.

SamRock upstream source targets net8.0 and cannot directly reference the net10.0 host. Retargeting the temporary copy to net10.0 makes its build with Boltz support pass. This framework issue predates 2.4.5; a plugin builder may override the source target, so it does not establish that an installed SamRock package is broken.

Build failures do not prove existing `.btcpay` binaries fail to load. Actual installed package versions and startup behavior remain to be verified on staging.

### 4. Store website validation differs

Satflux's `StoreUpdateRequest` uses Laravel's unrestricted `url` rule. The local application accepts `ftp://example.com`. 2.4.5's Greenfield store controller permits only absolute HTTP/HTTPS website URLs, so such updates pass local validation and then fail upstream.

Required work: restrict the website rule to HTTP/HTTPS and test the upstream-compatible boundary. Existing normal HTTP/HTTPS websites are unaffected.

### 5. POS embed example still contains a removed callback field

`resources/js/pages/stores/PointOfSaleShow.vue` generates a hidden per-request `notificationUrl` field. BTCPay removed that override in **2.4.4**, so users copying the current example can expect a callback that is ignored.

The app-level `notificationUrl` fields written by `PosUpdatePayloadBuilder` and `CrowdfundUpdatePayloadBuilder` remain supported in 2.4.5. Those deliveries are subject to the new network guard.

Required work: remove the misleading per-request field from the embed example and retain app-level configuration or webhooks.

## Other release changes and plugin review

| Area | Verified finding | Remaining runtime check |
|---|---|---|
| Invoice status permissions | `canmodifyinvoices` implies the new `canmanageinvoicestatus`. Satflux merchant/store permissions already include the former. | No rotation required for this change; test any manually restricted custom key separately. |
| Old public invoice links | Core rejects anonymous checkout/status/receipt access when archived, or after `MonitoringExpiration.AddMonths(1)`. Authenticated Greenfield invoice reads remain available. Satflux login does not authenticate a browser to BTCPay. | Old direct checkout links/receipts from exports, PDFs, or bookmarks can return 404. Satflux's server-backed `/pay/i/{token}` paid-state route remains separate; verify local-first flows too. |
| SEPA and Cashu settlement | Both record actual payments with `PaymentService.AddPayment`, rather than calling the newly restricted manual mark-status endpoint. | Complete one real/staging settlement for each. |
| Reused invoice destinations | Core conflict detection checks `TrackedDestinations`. SEPA tracks its unique reference, not the shared IBAN; Cashu tracks its quote ID. | Create successive invoices and check unique references/quotes and payment attribution. |
| Database migrations | Existing `BaseDbContextFactory` signatures remain usable. Our plugins keep their existing migration runners; the new `AddPluginDbContext` API is opt-in. Migration-history naming/search-path logic is preserved. Core factory PostgreSQL SQL-generation target changes from 12 to 14. | Verify actual host PostgreSQL version, startup, migration history, and a restart on staging. No DB migration was executed in this audit. |
| Lightning replacement/polling | Local Blink, Blitz, Flash, LnAddress, and Nostr sources compile against the updated Lightning API. Host replacement flow now persists the replacement before cancellation. | Pending invoice across restart/replacement, LUD-21 polling, NWC relay, LND WebSocket reverse proxy if LND is used. |
| Boltz/Aqua and SamRock | Boltz released source compiles; SamRock compiles after framework alignment. Boltz setup writes the wallet configuration through its own service, so the generic merchant serverless rejection is not directly applied to that setup path. | Descriptor import, QR pairing, invoice creation, settlement, daemon startup, and installed package versions. |
| Stripe | Local source builds unmodified against 2.4.5. Inbound Stripe webhooks are distinct from BTCPay's outbound HTTP guard. | Checkout and signed Stripe webhook settlement on staging. |
| Refunds/legacy tokens | Satflux's BTCPay clients do not call core refund/pull-payment or legacy BitPay token-management endpoints. New refund approval and legacy-token permissions have no identified direct Satflux call conflict. | Review any merchant workflows performed directly in BTCPay; they are outside the panel's API surface. |
| Reports, search, rates, host admin | Reports API is additive. Invoice search store scoping is consistent with Satflux's scoped reads. No inspected plugin constructs the changed invoice contexts, removed `NodeInfo` prompt property, or changed callback-generator constructor. | Verify configured BTCPay Base URL and generated email/invitation links. Browser automation using changed admin navigation may need maintenance. |

## Custom plugin HTTP protection is not automatic

BTCPay adds the transport guard to specific named clients; it does not globally protect every `IHttpClientFactory` client.

- LnAddress, Blitz, and Flash already use their own guarded handlers, with DNS address filtering and redirects disabled. Those handlers do **not** consult BTCPay's `ssrfexceptions`, so a core exception will not make these plugins accept a private LNURL service.
- CashuMelt registers ordinary HTTP clients for mint calls and `LightningAddressResolver`. The resolver requests the callback URL supplied by remote LNURL metadata without the core guard. Blink also creates ordinary HTTP clients. These paths do not automatically gain the protection advertised for the host's own clients.
- NWC relay/WebSocket traffic and Boltz daemon gRPC are separate transports; do not assume the host's HTTP exceptions govern them.
- SEPA's public FIO/NOP endpoints and certificate HTTP client have their own transport paths; core named-client protection does not rewrite their behavior.

This is a confirmed protection-coverage gap from source inspection, not a demonstrated production exploit. Review configurable mint URLs, remote LNURL callbacks, Blink endpoint overrides, redirect behavior, and NWC relays explicitly. Preserve intentional operator-configured internal services when designing plugin guards.

## Work required before upgrade approval

1. Implement and verify a safe merchant setup path for all five affected wallet formats; keep server privileges away from arbitrary merchant input.
2. Review the 14 enabled localhost webhooks and validate callback destinations/DNS from the BTCPay container.
3. Align plugin package pins/build targets in the maintained repositories, rebuild reviewable artifacts against the intended host, and identify the installed versions.
4. Correct website validation and the POS embed example; communicate the old public-link retention behavior.
5. Stage the actual 2.4.5 host and plugin artifacts. Verify startup/migrations, new guest/store provisioning, wallet creation/switching, webhook delivery, invoice lifecycle, subscriptions, ticket/raffle fulfillment, Cashu, SEPA, Stripe, and restart recovery. Include both expired/archived public links and authenticated historical invoice reads.

The audit verifies source and local tests plus read-only 2.4.4 inventory. It does **not** certify installed plugin binaries, production DNS, database migrations, external payment providers, or a running 2.4.5 deployment.

## Primary references

- [BTCPay 2.4.5 release](https://github.com/btcpayserver/btcpayserver/releases/tag/v2.4.5)
- [Exact host comparison](https://github.com/btcpayserver/btcpayserver/compare/v2.4.4...v2.4.5)
- [Lightning safety and plugin DB registration](https://github.com/btcpayserver/btcpayserver/blob/v2.4.5/BTCPayServer/Extensions.cs)
- [Lightning configuration validation](https://github.com/btcpayserver/btcpayserver/blob/v2.4.5/BTCPayServer/Payments/Lightning/LightningLikePaymentHandler.cs)
- [HTTP transport guard](https://github.com/btcpayserver/btcpayserver/blob/v2.4.5/BTCPayServer/SSRFProtectionExtensions.cs)
- [Public invoice access](https://github.com/btcpayserver/btcpayserver/blob/v2.4.5/BTCPayServer/Controllers/UIInvoiceController.UI.cs)
- [Permission inheritance](https://github.com/btcpayserver/btcpayserver/blob/v2.4.5/BTCPayServer/Hosting/BTCPayServerServices.cs)
- [Boltz release source](https://github.com/BoltzExchange/btcpay-plugin-liquid/tree/v2.4.5)
- [SamRock inspected source](https://github.com/rockstardev/SamRockProtocol/tree/99a82e72ad24c459a4989173a89c4dc14d23bcb8)
