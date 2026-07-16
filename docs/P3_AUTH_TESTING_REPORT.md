# P3: Posilnenie autentizácie a testovania - záverečný report

Program P3 nadväzuje na P0-P2 (spoľahlivosť a komfort local-first
invoicingu). Štyri fázy, každá vlastný PR: A - passkey (WebAuthn PRF)
odomykanie zapamätaného zariadenia, B - BTCPay e2e cez Greenfield stub,
C - vue-tsc baseline burn-down, D - ESLint `no-explicit-any` burn-down.

## Zhrnutie výsledkov

| Fáza | Cieľ | Výsledok |
| --- | --- | --- |
| P3-A | Passkey-PRF slot pre device unlock | Hotové - aditívny slot, passphrase zostáva povinná |
| P3-B | BTCPay store + invoice lifecycle e2e | Hotové - 6 scenárov, podpísaný webhook reťazec end-to-end |
| P3-C | vue-tsc baseline 389 → ≤240 | **389 → 195** (−194) |
| P3-D | ESLint `no-explicit-any` 359 → ≤260 | **359 → 206** (−153) |

Invarianty zadania dodržané: žiadne P0/P1/P2 regresie (vitest 273,
PHPUnit 870, e2e 13/13), všetko voliteľné/za gate, otestované,
zdokumentované, spätne kompatibilné (envelope v1), bez oslabenia krypto
vrstvy, generické chybové hlášky bez oracle.

## P3-A: Passkey (WebAuthn PRF) unlock

- KEK = HKDF-SHA-256(PRF výstup, info=`satflux.device-envelope.prf.v1|{ownerFingerprint}`);
  WebAuthn **podpis sa ako kľúčový materiál nikdy nepoužíva**.
- Slot je aditívny v envelope v1 (multi-slot dizajn z P2) - staré buildy
  neznámy slot typ ignorujú, passphrase slot je povinný koreň; strata
  passkey nikdy nezamkne dáta (fallback passphrase/recovery fráza).
- Enrollment vyžaduje passphrase (dôkaz vlastníctva → DEK), test-unlock
  pred uložením, PRF secrety a DEK sa zero-ujú na všetkých cestách.
- UI: Profile.vue - odomknutie passkeyom v unlock modale, správa slotov
  (label, lastUsedAt, pridanie/odstránenie) v bloku zapamätaného
  zariadenia; zrušenie promptu je tiché, chyby generické.
- Dizajn + browser matrix: [PASSKEY_PRF_DEVICE_UNLOCK.md](PASSKEY_PRF_DEVICE_UNLOCK.md)
  (odkaz aj z BACKUP_ENCRYPTION.md).

## P3-B: BTCPay e2e (Greenfield stub)

- `e2e/btcpay-stub/server.mjs` - node:http bez závislostí; presne tá
  podmnožina Greenfield API, ktorú `BtcPayClient` volá; zapojenie čisto
  configom (`BTCPAY_BASE_URL`). Control API settle/expire strieľa
  **reálne podpísané webhooky** (BTCPay-Sig HMAC nad raw body secretom
  minteným pri provisioningu) - panelová verifikácia beží naozaj.
- 6 scenárov v `e2e/btcpay.spec.ts` (gate `E2E_BTCPAY=1`): vytvorenie
  obchodu cez UI + registrácia webhooku, faktúra viditeľná v zozname,
  settle → webhook prijatý (200), Settled v UI, Expired v UI, zmazanie
  obchodu (aj zo stubu). Scenáre: [BTCPAY_E2E_SCENARIOS.md](BTCPAY_E2E_SCENARIOS.md),
  harness: [E2E_HARNESS.md](E2E_HARNESS.md).
- Ladenie odhalilo a opravilo **reálne produktové chyby**: zastaraná
  `user_limits` cache po vytvorení/zmazaní obchodu (60 s) a nezneplatnený
  dashboard obchodu po Invoice* webhookoch (1 h) - oboje sa teraz
  invaliduje pri zdroji zmeny; auth throttle (5/min/IP) si vynútil vzor
  jedného zdieľaného loginu pre serial suitu.

## P3-C a P3-D: baseline burn-downy

Centrálne opravy namiesto stoviek lokálnych:

- **Typy** (`chore/typecheck-burn-down`): generické evolu parse helpery
  (branded typ pretečie z validátora ku volajúcemu) a
  `evolu/queryLoad.ts#toAppRows<TRow>()` - jediný auditovateľný most
  z brandovaných `QueryRows` na aplikačné `Evolu*Row` typy; 114
  roztrúsených castov odstránených. Baseline 389 → 195, shrink-only.
- **Lint** (`chore/eslint-any-burn-down`): `utils/apiError.ts#asApiError`
  - jediné centrálne zúženie axios chýb; 152× `catch (x: any)` →
  `catch (rawError)` + typovaný prístup. Ratchet `--max-warnings`
  359 → 206. Prísnejšie typy odhalili a opravili neošetrené
  `err.response.data` reťazce a `string | undefined` priradenia.

## Konfigurácia

| Premenná | Kde | Význam |
| --- | --- | --- |
| `E2E_BTCPAY=1` | seeder + playwright + CI | zapína BTCPay scenáre a merchant fixture |
| `BTCPAY_BASE_URL` / `BTCPAY_API_KEY` | .env (CI: stub URL/kľúč) | cieľ Greenfield volaní |
| `SEED_FIRST_REGISTRATION=false` | CI e2e .env | password login pre seedovaného usera (enrolled kľúč by ho vypol; bez neho by povinný migračný wizard blokoval UI) |

Passkey nemá žiadnu serverovú konfiguráciu - je čisto klientský.

## Monitoring a logovanie

- Passkey: **žiadne nové serverové logy** - celé odomykanie je
  klientské (server frázu ani PRF výstup nikdy nevidí). Diagnóza cez
  typované chyby v UI; generické hlášky bez rozlíšenia príčiny (oracle).
- BTCPay e2e: stub loguje nepokryté endpointy nahlas (`unhandled ...`),
  CI job má fail-fast poll na stub aj app server.

## Nasadenie a rollback

- Passkey: bežný frontend deploy; rollback = nasadenie staršieho buildu
  - envelope v1 zostáva čitateľné (aditívny slot, staré buildy ho
  ignorujú), passphrase unlock funguje vždy. Migrácie žiadne.
- E2e stub: len dev/CI, do produkčného behu nevstupuje (config-only).
- Cache invalidation fixy (user_limits, dashboard): bez migrácií,
  rollback bezpečný (vráti sa len pôvodná zastaranosť cache).

## Známe obmedzenia

- **PRF podpora prehliadačov**: `isPasskeyPrfSupported()` vie zistiť len
  platformový autentikátor; či implementuje PRF, sa dozvieme až pri
  create()/get() - enrollment preto zlyháva typovane. Safari < 18
  a niektoré externé kľúče PRF nemajú (matrix v dokumente P3-A).
- **Stub nie je BTCPay parita**: implementuje len volanú podmnožinu;
  payment-methods vracia prázdny zoznam (settlement sync beží naprázdno),
  checkout je atrapa. Zmena BtcPayClient volaní = hlasné 404 v CI.
- Auth throttle (5/min/IP) limituje počet loginov v e2e sade - nové
  specy majú zdieľať session (vzor v btcpay.spec.ts), nie sa prihlasovať
  per test.

## Otvorené body (follow-up)

- Local-first invoicing BTCPay checkout e2e (QR z Evolu faktúry) -
  vyžaduje recovery-phrase flow v prehliadači; vedome mimo P3-B.
- Passkey unlock v GuestRestoreModal (v P3-A len Profile.vue).
- Zvyšné baseliny: typecheck 195 (prevažne `exactOptionalPropertyTypes`
  v komponentoch), lint 206 (any v starších stores/pages) - vhodné na
  ďalšie kolo rovnakým centrálnym štýlom.
