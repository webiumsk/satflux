# Agent prompt: BTCPay Server plugin BTCPayRaffle (integrátor-friendly)

Použi tento dokument ako zadanie pri úpravách pluginu **BTCPayServer.Plugins.BTCPayRaffle** v repozitári BTCPay Server. Cieľová integrácia: **satflux.io** - multi-tenant panel, kde obchodník **nemá prihlásenie do BTCPay UI**, len Greenfield API key s `CanModifyStoreSettings`.

---

## Kontext

- Satflux proxyuje Greenfield `GET|POST /api/v1/stores/{storeId}/raffle/...` cez Laravel (`RaffleService`, merchant API key server-side).
- Kupujúci kupujú lístky na **verejnej** stránke BTCPay: `{origin}/raffle/{raffleId}` (nie v Satflux).
- Satflux už implementuje: list, create (draft), open, close, draw, complete, tickets, drawings.
- **Problém:** Admin „live draw“ UI je dnes na `{origin}/stores/{storeId}/plugins/raffle/{raffleId}/draw` a vyžaduje **cookie session BTCPay Server** (prihlásený používateľ BTCPay). Satflux merchant to nemá a mať nebude.

Referencie v plugine (existujúci kód):

- `Plugins/BTCPayServer.Plugins.BTCPayRaffle/Controllers/RaffleApiController.cs` - Greenfield API
- `Plugins/BTCPayServer.Plugins.BTCPayRaffle/Controllers/RafflePublicController.cs` - verejné `/raffle/...`
- `Plugins/BTCPayServer.Plugins.BTCPayRaffle/ViewModels/RaffleViewModels.cs`
- `Plugins/BTCPayServer.Plugins.BTCPayRaffle/Data/Entities/Raffle.cs` - `RaffleStatus`
- Vzor dokumentácie: `Plugins/BTCPayServer.Plugins.CashuMelt/docs/AGENT_API.md`

---

## Požiadavky (priorita)

### P0 - Dokumentácia integrátora

Vytvor `Plugins/BTCPayServer.Plugins.BTCPayRaffle/docs/AGENT_API.md` (štruktúra ako CashuMelt AGENT_API.md):

1. **Kto čo používa**
   - Integrátor (Satflux): výhradne Greenfield + merchant API key; **žiadny BTCPay UI login** pre obchodníka.
   - Kupujúci: verejné URL (buy, receipt, verify ticket).
   - BTCPay admin UI: voliteľné pre hostiteľa inštancie, nie pre koncového merchant-a v Satflux.

2. **Greenfield endpoints** (už existujúce - popísať presne):
   - Base: `/api/v1/stores/{storeId}/raffle`
   - CRUD lifecycle: list, POST create, GET detail, POST open/close/draw/complete, GET tickets, GET drawings
   - Auth: `Authorization: Bearer {apiKey}` alebo `token {apiKey}` (ako ostatné Greenfield)
   - Permission: `CanModifyStoreSettings` na store
   - Request/response JSON (camelCase) - skopírovať z `RaffleViewModels`
   - Chyby: 400 text z `InvalidOperationException`, 404 ak raffle nepatrí store

3. **Verejné URL** (bez auth):
   - Buy: `GET/POST {origin}/raffle/{raffleId}`
   - Receipt: `{origin}/raffle/receipt/{invoiceId}`
   - Verify ticket: `{origin}/raffle/ticket/{ticketGuid}` (ak existuje)

4. **Explicitne označiť ako NEPOUŽÍVATEĽNÉ pre Satflux merchantov**
   - `{origin}/stores/{storeId}/plugins/raffle/{raffleId}/draw` - vyžaduje BTCPay Server user session.

5. **CashuMelt:** pri Cashu checkout po kúpe lístkov odporučiť CashuMelt ≥ 1.2.0.2 (RELEASE_NOTES).

---

### P1 - Verejná / tokenizovaná draw presentation (voliteľné pre projektor)

Satflux po žrebovaní zobrazí výsledok v paneli; pre „veľkú obrazovku“ chceme URL bez BTCPay login.

**Navrhované API:**

```
POST /api/v1/stores/{storeId}/raffle/{raffleId}/presenter-token
Authorization: merchant API key (CanModifyStoreSettings)
Response 200:
{
  "token": "<opaque>",
  "expiresAt": "ISO-8601",
  "presenterUrl": "/raffle/{raffleId}/present?token=<opaque>"
}
```

- Token: jednorazový alebo krátka TTL (napr. 15–60 min), viazaný na `storeId` + `raffleId`.
- `GET {origin}/raffle/{raffleId}/present?token=...` - HTML stránka (slot machine / reveal), **bez** `[Authorize]` BTCPay účtu.
- Stránka **nesmie** sama volať `POST .../draw` bez auth - len zobrazuje posledný výsledok alebo animáciu po draw vykonanom cez API (Satflux tlačidlo). Alternatíva: token povolí len „replay“ posledného drawing z DB.

**Bezpečnosť:**

- Neuhádať token (dĺžka ≥ 32 bytes random).
- Rate limit na vydávanie tokenov.
- Invalidácia po expirácii.

Satflux neskôr zavolá `presenter-token` a zobrazí odkaz „Otvoriť prezentáciu“ (nový tab) - implementácia v Satflux až po tom, čo plugin endpoint existuje.

---

### P2 - Greenfield: úprava draft raffle

```
PUT /api/v1/stores/{storeId}/raffle/{raffleId}
Body: { name, description, ticketPriceSats, maxTickets }  // ako POST create
```

- Povolené **iba** keď `status === Draft`.
- 400 ak nie Draft.
- Satflux dnes nemá edit formulár; API umožní budúcu iteráciu bez BTCPay UI.

---

### P3 - (Nice to have) Draw state JSON

```
GET /api/v1/stores/{storeId}/raffle/{raffleId}/draw-state
```

Response:

```json
{
  "status": "Closed",
  "eligibleTicketsRemaining": 42,
  "drawingsCount": 3,
  "canDraw": true,
  "canComplete": false
}
```

Headless integrátori môžu stavať vlastné UI bez parsovania celej raffle entity.

---

## Nesmie sa zmeniť (kompatibilita so Satflux)

- Existujúce Greenfield cesty a camelCase polia odpovedí (`id`, `ticketPriceSats`, `ticketsSold`, `status`, …).
- `POST .../draw` response: `{ drawOrder, winningTicketNumber, winnerName, winnerEmail, drawnAt }`.
- Tickets list: `ticketNumber`, `buyerName`, `buyerEmail`, `allocatedAt`, `receiptUrl` (relatívna cesta `/raffle/receipt/...`).
- Verejná buy stránka `/raffle/{raffleId}`.

---

## Testy v plugine

- Unit testy: presenter token validation, PUT draft only when Draft, draw-state.
- Integračné: Greenfield draw + GET drawings obsahuje nový záznam.

---

## Po dokončení pluginu - Satflux (implementované)

1. `POST /api/stores/{store}/raffles/{raffleId}/presenter-token` → proxy; UI „Otvoriť prezentáciu“ používa absolútnu `presenterUrl` z BTCPay.
2. `PUT /api/stores/{store}/raffles/{raffleId}` → proxy; formulár úpravy draft na detaile tomboly.
3. Žrebovanie + reveal modal v paneli; BTCPay admin draw link odstránený.

---

## Acceptance criteria (plugin)

- [ ] `docs/AGENT_API.md` existuje a rozlišuje integrátor / kupujúci / BTCPay admin UI.
- [ ] Dokumentované, že admin draw URL nie je pre Satflux merchantov.
- [ ] (P1) `presenter-token` + verejná `present` stránka bez BTCPay login.
- [ ] (P2) `PUT` draft raffle cez Greenfield.
- [ ] Spätná kompatibilita so existujúcim Satflux `RaffleService` proxy.
