# BTCPay 2.4.5 implementation handoff

Target host: BTCPay Server v2.4.5, commit
`5d0745cae6be5d8210459e38813f317673aa97b8`, with .NET 10.
The implementation and staging procedure are documented in
[BTCPAY_2_4_5_IMPLEMENTATION.md](BTCPAY_2_4_5_IMPLEMENTATION.md).

## Repository changes

| Repository | Pull request | Scope |
|---|---|---|
| Satflux | [#389](https://github.com/webiumsk/satflux/pull/389) | Merchant wallet setup/recovery, webhook validation/reconciliation, archived checkout handling and CI |
| Webium plugins | [#32](https://github.com/webiumsk/BTCPayServerPluginsWebium/pull/32) | Exact-host compatibility, guarded HTTP transport and Blink addresses in LNAddressConnect |
| Kukks fork | [#1](https://github.com/webiumsk/BTCPayServerPluginsKukks/pull/1), [#2](https://github.com/webiumsk/BTCPayServerPluginsKukks/pull/2) | Upstream synchronization and legacy Blink/NWC compatibility; NWC is deferred from the current package set |

## Validation

- Satflux CI run `38006340909`: 1,556 PHP tests / 6,591 assertions,
  669 frontend tests and 19 Playwright tests pass. TypeScript, ESLint,
  the production build, Pint and PHPStan pass.
- Webium CI run `38006240766`: all seven plugin suites pass 490 tests.
  Exact-host/plugin builds and host-source guard regressions pass.
- Isolated host checks cover merchant/store authorization, startup migrations,
  restart, signed webhook delivery, manual SEPA settlement and archived reads.
  These checks do not establish funded external-wallet settlement readiness.

## Current plugin set

Prepare LNAddressConnect 1.0.3, CashuMelt 1.3.1.1, SEPA Instant QR 0.8.1,
Satflux Tickets 2.0.1 and BTCPay Raffle 1.3.2.3. Blink 1.1.3 is optional for
existing API-key configurations. NWC is outside the current package scope.

LNAddressConnect covers Blink, Blitz, Flash and Coinos addresses. Separate Blitz
and Flash packages are unnecessary for this set. Existing Blink API stores retain
their migration prompt, and Kukks Blink remains active. Explicit Blink address
resubmission uses the wallet replacement guard and durable journal; reading a
wallet does not change its adapter.

CodeRabbit's Webium origin and host-source findings are fixed in `49d4196`.
Blink address support is in `f3c293b`; Satflux routing is in `c5e8a914`.
The latest review also requires trimming whitespace around private webhook
origins while keeping exact origin matching.

## Deployment and rollback

No production upgrade or plugin installation is performed by package creation.
Funded onboarding, payment/switch/reconnect, Cashu mint/melt, paid Tickets/Raffle
fulfillment and an existing-data migration rehearsal still require staging.

Resolve every uncertain wallet update before rolling back Satflux. Preserve
journal data, wallet configuration, monitored invoices, webhook IDs/secrets
and Tickets' historical migration identity. Follow the deployment and rollback
sequence in the implementation document; local session paths and operator notes
are not part of this repository handoff.
