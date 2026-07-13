# Runtime relay transport (P2 fáza 1)

## Problém

`createEvolu` prijíma transporty len pri vytvorení inštancie, ktoré beží pri
inicializácii modulu - PRED prihlásením. Do P2 preto worker WebSocket vždy
používal build-time `VITE_EVOLU_RELAY_URL` (a pri jej chýbaní verejný
default), zatiaľ čo profilová relay URL platila len pre manuálne push/pull.
Zmena relay = rebuild + redeploy; zabudnutá build premenná = worker na
mŕtvom defaulte (incident 2026-07-13).

## Riešenie: runtime rebrík cez localStorage cache

`evoluTransports()` číta synchrónne pri boote tento rebrík:

1. **device override** (`satflux.evolu.relay_override.v1`):
   - `off` sentinel → prázdne transporty (sync vypnutý na zariadení),
   - normalizovaná URL → použije sa (cache profilovej relay),
2. **build env** `VITE_EVOLU_RELAY_URL` (prázdna = sync vypnutý buildom),
3. **verejný default** `wss://free.evoluhq.com`.

Cache sa plní automaticky: `fetchUser()` po každom prihlásení/obnovení
zapíše normalizovanú profilovú URL do override storage (nikdy neprepíše
device-level "off"). Uloženie relay v Profile zapíše server + cache a pri
zmene originu reloadne stránku - reload aplikuje transport aj čerstvú CSP
hlavičku.

Rovnaký rebrík používa `getEvoluRelayRuntimeInfo()` (probe, subscription,
usage) - "off" override vypína aj tieto cesty.

## UI (Profil → Synchronizácia)

- vstup pre relay URL + Uložiť / Použiť predvolený (existujúce),
- **Otestovať pripojenie** - probe zadanej URL pred uložením (akákoľvek
  HTTP odpoveď usage endpointu = dostupný),
- **Vypnúť synchronizáciu na tomto zariadení** - device-level checkbox
  (localStorage sentinel), aplikuje sa reloadom; opätovné zapnutie vráti
  profilovú/build URL.

## Validácia

`normalizeEvoluRelayBaseUrl` odmieta iné než ws(s)/http(s) URL (console
warning + sync vypnutý) - neplatná cache teda nikdy nevytvorí rozbitý
transport; rebrík padá na build env.

## Obmedzenia

- Zmena sa aplikuje až reloadom (worker transport nejde vymeniť za behu) -
  UI reload robí automaticky pri zmene originu.
- Cache je per zariadenie/prehliadač; nové zariadenie použije build default
  do prvého prihlásenia (potom sa profil zosynchronizuje do cache).

## Testy

`resources/js/__tests__/relayOverride.test.ts` - storage round-trip,
sentinel, rebrík transportov (override > build), invalid cache fallback.
