# Satoshi Tickets (BTCPay plugin)

Satflux neimplementuje tickety lokálne - volá Greenfield API pluginu **Satoshi Tickets** na BTCPay Serveri.

## Ktorý plugin nasadiť

Na produkcii používaj **Webium fork**, nie čistý build od TChukwuleta:

- Repozitár: `webiumsk/BTCPayServerPlugins` (lokálne často `BTCPayServerPluginsTChukwuleta`)
- Runbook (mesačný merge, vetvy, checklist):  
  `Plugins/BTCPayServer.Plugins.SatoshiTickets/FORK_MAINTENANCE.md`
- Zoznam fork-only zmien:  
  `Plugins/BTCPayServer.Plugins.SatoshiTickets/CHANGELOG-FORK.md`

**Ďalšia plánovaná kontrola fork + upstream:** 20. jún 2026.

## Min. verzie pluginu (doplň po release)

| Satflux release / vetva | Satoshi Tickets (fork) | BTCPay Raffle (ak bundle) |
|-------------------------|------------------------|---------------------------|
| Event raffle bundle | ≥ **1.3.6.4** (fork; reflection ValueTuple) | ≥ **1.3.1.0** (`IRaffleEventBundleService`) |

Ak create event vráti `BTCPay Raffle plugin … required`, na serveri beží starý Raffle (napr. 1.3.0.2) bez bundle API - nahraj novší `.btcpay` a reštartuj BTCPay.

## Kód v Satflux

- API proxy: `app/Http/Controllers/TicketController.php`, `app/Services/BtcPay/TicketService.php`
- UI: `resources/js/pages/stores/TicketsShow.vue`
- Event raffle bundle v UI: polia `bundledRaffleId`, `bundledRaffleTicketsPerAdmission`

## Poznámka

Upstream autor vyvíja plugin nezávisle; Satflux závisí od fork endpointov (napr. `includeInactive`, offline tickets, bundle polia). Pred upgradeom BTCPay pluginu vždy skontroluj `FORK_MAINTENANCE.md`.
