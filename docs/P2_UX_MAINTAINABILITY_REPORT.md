# P2 - Používateľský komfort a dlhodobá udržateľnosť: záverečný report

Stav k 2026-07-13. Nadväzuje na P0 (bezpečnosť) a P1 (prevádzková
spoľahlivosť, docs/P1_RELIABILITY_REPORT.md). Zadanie vyžadovalo audit-first
prístup - každý bod sa najprv overil proti reálnemu kódu; korekcie zadania
sú uvedené pri fázach.

## 1. Stručné zhrnutie P2 zmien

Šesť fáz, každá samostatný PR:

| Fáza | PR | Obsah |
|---|---|---|
| F1 | #129 feat/p2-runtime-relay | Runtime relay transport: localStorage override rebrík, device-level "sync off", test pripojenia v Profile |
| F2 | #130 feat/p2-backup-encryption | Voliteľné šifrovanie zálohy passphrase (PBKDF2 + AES-256-GCM), 6-slovný BIP-39 generátor, auto-detekcia pri obnove |
| F3 | #131 feat/p2-e2e-ci | E2E rozšírenie (7 scenárov) + Playwright CI job (sqlite, bez BTCPay) |
| F4 | #132 feat/p2-bulk-pdf-abort | Zrušiteľné bulk PDF/XLSX exporty (AbortController + cancel tlačidlo) |
| F5 | #133 docs/p2-query-duplicates | Koreňová príčina fantómových duplicít nájdená + upstream issue draft |
| F6 | #134 chore/p2-error-counters | Overenie error-rate counterov na redis + docs, korekcia P1 reportu |

Rozhodnutia usera pred štartom: **offline PWA odložená** (samostatný projekt),
produkčná cache **je redis** (F6 sa zúžila na overenie + docs), E2E **lokálne
aj CI**.

## 2. Vyriešené problémy

- **Relay zmena vyžadovala rebuild** (createEvolu transport je build-time;
  incident s mŕtvym defaultom 2026-07-13): runtime rebrík device override
  (localStorage) → VITE_EVOLU_RELAY_URL → verejný default; profilová URL sa
  po prihlásení cachuje a aplikuje reloadom; device-level vypnutie syncu;
  "Otestovať pripojenie" pred uložením.
- **Záloha bola len plaintext**: opt-in šifrovanie prístupovou frázou -
  rovnaké auditované WebCrypto primitívy ako device unlock (extrahované do
  services/passphraseCrypto.ts), AES-256-GCM nad celým plaintext obalom,
  kalibrované PBKDF2 iterácie (min. 600k, strop 10M aj proti hostilným
  súborom), zámerne bez väzby na ownera (obnoviteľné do nového účtu),
  6-slovný BIP-39 generátor (66 bitov), restore auto-detekcia + rovnaký
  validačný pipeline; explicitné varovanie, že stratená fráza = stratená
  záloha.
- **E2E harness bol zastaraný a nikde nebežal**: existujúce auth testy
  nesedeli so seed-first UI (opravené cez /login?tab=email), cookie consent
  banner blokoval kliky (pre-seed v helperi); nové scenáre: seedovaný email
  login → dashboard, Sanctum session cez reload, invoicing hard-gate bez
  recovery frázy; CI job so sqlite + file cache + sync queue, fixture
  seeder odmietajúci produkciu; fail-fast keď server nenaštartuje.
- **Bulk PDF sa nedal zrušiť**: AbortController na oboch cestách (ephemeral
  bridge aj legacy server), cancel tlačidlo v loading stave, zrušenie sa
  nikdy nezobrazí ako chyba (utils/abortError.ts); abort nespúšťa 404
  fallback route; bez resume - reštart = nový beh (server all-or-nothing).
- **Fantómové duplicity mali neznámu príčinu**: nájdená - stavový
  index-based patch protokol Evolu (makePatches/applyPatches, toSpliced)
  bez sekvenčných čísel/checksumov + reálne okná straty správ v
  BroadcastChannel SharedWebWorker transporte; deterministicky
  demonštrované 4 testami proti reálnym exportom balíka; upstream issue
  draft pripravený (docs/QUERY_LAYER_DUPLICATES_INVESTIGATION.md).
- **Nejasná sémantika error counterov**: overené naživo v Redise -
  zdieľané naprieč procesmi, atomické, prežívajú restart (deploy len bez
  cache flushu); P1 report skorigovaný.

## 3. Testy

- Frontend (vitest): 225 testov / 47 súborov (P1 koniec: 204/43). Nové:
  relay override storage + transport rebrík, backup crypto round-trip +
  tamper + hostilné iterácie + generátor + klasifikácia súborov, bulk PDF
  abort (fallback semantika), query patch protokol (4 demonštračné).
- Backend (PHPUnit): 809 testov (bez zmeny - P2 nepridala serverovú
  logiku okrem seedera).
- E2E (Playwright): 7 scenárov, overené lokálne proti Docker stacku
  (7/7); bez env gátov sa seedované scenáre čisto skipnú (4+3).
- Gates pri každej fáze: vitest, ESLint ratchet, vue-tsc baseline, build,
  locale-parity; PHP fázy: PHPUnit, PHPStan, Pint.
- Opravená nestabilita: localeMessages test (prechádza celý katalóg)
  dostal 30 s timeout - padal pod plnou paralelnou sadou.

## 4. Dokumentácia

- docs/RUNTIME_RELAY_TRANSPORT.md - rebrík, UI, validácia, obmedzenia.
- docs/BACKUP_ENCRYPTION.md - formát, kryptografia, fráza, obnova.
- docs/E2E_HARNESS.md - pokrytie, lokálny runbook, CI, konvencie.
- docs/QUERY_LAYER_DUPLICATES_INVESTIGATION.md - rozbor + upstream draft.
- docs/ERROR_RATE_COUNTERS.md - sémantika, overovací transcript, limity.
- docs/P1_RELIABILITY_REPORT.md - korekcie (bulk PDF cancel vyriešený,
  error countery na redis).

## 5. Konfigurácia

Žiadne nové povinné env premenné. Súvisiace zmeny:
- `satflux.evolu.relay_override.v1` (localStorage) - runtime relay cache;
  "off" sentinel = sync vypnutý na zariadení. VITE_EVOLU_RELAY_URL zostáva
  build-time default (deploy checklist z P1 platí).
- CI: nový job E2E (Playwright) v .github/workflows/ci.yml - sqlite,
  CACHE_STORE=file, QUEUE_CONNECTION=sync, VITE_INVOICING_LOCAL_FIRST=true,
  prázdna VITE_EVOLU_RELAY_URL; E2eTestSeeder (e2e@satflux.test) sa v
  produkcii odmietne spustiť.

## 6. Monitoring

Bez zmien v mechanike (P1 stack platí). F6 spresnila sémantiku error-rate
counterov: redis = cross-process, atomické, restart-safe; buckety TTL 2 h;
`optimize:clear` ich flushne (očakávané po deployi); dlhodobú históriu
nesú system_health_snapshots. Reset tlačidlo zvážené a zamietnuté
(umlčalo by aktívny errors health check).

## 7. Logovanie

Bez zmien; P1 pravidlá platia (LogSanitizer, žiadne PII). Nové moduly
nelogujú tajomstvá: passphrase/kľúče sa nikdy neserializujú, šifrovaný
obal nesie len KDF parametre a ciphertext; relay override loguje len
console.warn pri neplatnej URL.

## 8. Nasadenie

Fázy sú nasadené priebežne (každý PR samostatne). Pre nové prostredie nič
nové nad rámec P1 checklistu; CI E2E job beží automaticky na PR/push.
Runtime relay: existujúci používatelia dostanú profilovú URL do cache pri
prvom prihlásení/obnovení session (fetchUser), aplikuje sa reloadom.

## 9. Rollback plán

- Každá fáza = 1 PR = git revert bez kaskády; žiadne migrácie.
- F1 revert vráti build-time-only transport (override kľúč v localStorage
  zostane neškodne nečítaný).
- F2 revert: šifrované súbory sa prestanú dať vytvárať; už stiahnuté
  šifrované zálohy by po reverte nešli obnoviť - pred revertom vyzvať na
  plaintext export.
- F3/F5/F6 sú testy/docs - revert bez dopadu na runtime.
- F4 revert vráti nezrušiteľné exporty (kozmetický downgrade).

## 10. Známe obmedzenia

- Relay zmena sa aplikuje až reloadom (worker transport nejde vymeniť za
  behu); cache je per zariadenie - nové zariadenie použije build default do
  prvého prihlásenia.
- Šifrovanie zálohy nemá obnovu frázy (zámerné - GCM); plaintext zostáva
  default.
- E2E bez BTCPay flows (guest provisioning volá BTCPay; CI nemá server) -
  rozšírenie vyžaduje Greenfield stub.
- Bulk PDF cancel bez resume (server all-or-nothing).
- Fantómové duplicity: aplikácia je chránená (dedupeRowsById), ale koreň
  je upstream - do opravy v Evolu sa symptóm môže teoreticky objaviť v
  iných zoznamoch (rovnako prechodný).
- Offline PWA odložená (rozhodnutie usera; docs/OFFLINE_CAPABILITIES.md
  stále platí - cold start offline nefunguje).

## 11. Otvorené problémy

- Odoslať upstream issue do evoluhq/evolu (draft pripravený v
  docs/QUERY_LAYER_DUPLICATES_INVESTIGATION.md, sekcia 8); pri upgrade
  @evolu/* (common 7.4.1 / web 2.4.0 / vue 1.4.0) skontrolovať Query.js a
  SharedWebWorker.js.
- BTCPay E2E scenáre (Greenfield stub) - kandidát na ďalšiu fázu.
- Passkey-PRF slot pre device unlock (envelope formát je pripravený).
- Baseline burn-downs: ESLint warnings ratchet (359), vue-tsc baseline
  (392) - postupné znižovanie.

## 12. Manuál použitia

- **Relay**: Profil → Synchronizácia - vlastná URL + "Otestovať
  pripojenie" + Uložiť (pri zmene originu sa stránka sama reloadne);
  checkbox "Vypnúť synchronizáciu na tomto zariadení" pre local-only režim.
- **Šifrovaná záloha**: Profil → Záloha dát → zaškrtnúť "Zašifrovať zálohu
  prístupovou frázou" → vlastná fráza (min. 8 znakov) alebo "Vygenerovať
  frázu" (6 slov - ZAPÍSAŤ, bez nej sa záloha nedá otvoriť) → Exportovať.
  Obnova: vybrať .encrypted.json súbor → zadať frázu → Odomknúť → ďalej
  identické s plaintext obnovou (preview, potvrdenie, merge).
- **Zrušenie exportu**: počas bulk PDF/XLSX sa pri načítavaní zobrazí
  "Zrušiť export" - zrušenie je bezpečné, ďalší pokus začne odznova.
- **E2E lokálne**: `docker compose exec php php artisan db:seed
  --class=E2eTestSeeder`, potom `E2E_SEEDED_USER=1
  E2E_INVOICING_LOCAL_FIRST=1 npm run test:e2e`.

## 13. Riziká nasadenia a odporúčaný rollout

- F1: prvé prihlásenie po nasadení zapíše profilovú relay do cache -
  používatelia s vlastnou relay bez CSP záznamu na serveri by mali overiť
  CSP_EVOLU_RELAY_URL (inak worker WebSocket zablokuje CSP).
- F2: komunikovať používateľom, že fráza k zálohe je nenahraditeľná -
  podpora nemá ako pomôcť pri strate.
- F3: CI E2E job predlžuje pipeline o ~3-4 min; pri flaky behu má 2 retry
  a report artifact.
- F5: dedupeRowsById nechať na mieste aj po prípadnej upstream oprave
  (lacná poistka).
- Odporúčané poradie pre ďalšie prostredia: P1 checklist → deploy →
  overiť Profil (relay test, šifrovaný export/obnova round-trip) →
  spustiť E2E proti prostrediu s APP_URL.
