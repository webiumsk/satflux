# P1 - Prevádzková spoľahlivosť a odolnosť: záverečný report

Stav k 2026-07-13. Nadväzuje na P0 (bezpečnosť: seed storage, CSP, serverové
číslovanie, issued snapshoty). Plán a audit: pozri históriu PR #113-#125.

## 1. Stručné zhrnutie P1 zmien

Osem fáz, každá samostatný PR, plus štyri opravné PR-ka z overovania na
produkcii:

| Fáza | PR branch | Obsah |
|---|---|---|
| F1 | feat/p1-data-loss-guards | DangerConfirmModal, logout guard, owner-switch varovanie, backupState |
| F2 | feat/p1-backup-export | Export celého datasetu s SHA-256 integritou, evidencia posledného exportu |
| F3 | feat/p1-backup-restore | Obnova zálohy s verifikáciou hashu, 7-dňová pripomienka |
| F4 | feat/p1-honest-sync-state | 6-stavový sync model založený na dôkazoch, relay probe, error monitor |
| F5 | feat/p1-local-storage | navigator.storage persist/estimate, 80 % varovanie, quota klasifikácia |
| F6 | feat/p1-long-ops-offline | Import progress, offline poctivosť (docs + banner), relay URL validácia |
| F7 | feat/p1-log-hygiene-health | LogSanitizer, health checky + endpoint + command, boot env validácia |
| F8 | feat/p1-monitoring-dashboard | Scheduler + snapshoty, error-rate countery, e-mail alerting, admin dashboard |
| hotfix | fix/bank-inbound-per-company | b-mail per firma podľa identity, bridge on demand |
| hotfix | feat/health-history-polish | História snapshotov ako intervaly, relay detail |
| hotfix | fix/csp-reverb-wss | wss dvojča app originu (Reverb/broadcasting) |
| hotfix | fix/phantom-duplicate-company-rows | dedupe fantómových riadkov, merge onComplete+verify (v čase reportu nemergnuté) |

## 2. Vyriešené problémy

- Logout mohol nenávratne odstrihnúť guest účet od dát (jediná kópia frázy v
  localStorage) - blokujúci typed-word guard s backup evidenciou.
- Obnova cudzej frázy prevzala lokálne dáta bez varovania - preview dopadu
  (počty dokladov/kontaktov/firiem) + explicitné potvrdenie.
- Neexistovala žiadna záloha datasetu - export/obnova všetkých 18 Evolu
  tabuliek s deterministickou serializáciou a SHA-256 (poškodený súbor sa
  odmietne pred zápisom).
- Sync ikona klamala (zelená = "práve nesynchronizujem") - stav sa odvodzuje
  len z dôkazov: probe dosiahnuteľnosti, zaznamenané úspešné výmeny,
  evolu.subscribeError, online flag; zelená len pre overené "ok".
- Local-first databáza bola evikovateľná bez varovania - persist() žiadosť,
  used/quota v Profile, banner pri ≥80 % a pri quota chybách.
- CSV importy blokovali UI bez spätnej väzby - async s počítadlom N / M.
- Prílohové chyby zahadzovali dôvod - cause s Evolu validáciou.
- Nezmyselná relay URL polo-nakonfigurovala transport - validácia, fallback
  na local-only; build info nikdy nevráti {url:"", enabled:true}.
- Surové e-maily v logoch (12 miest) - LogSanitizer maskovanie.
- Chýbal health monitoring - 7 checkov, scheduler à 5 min, snapshoty,
  throttled e-mail alerting s recovery notifikáciou, admin dashboard.
- Chýbala produkčná boot validácia - fail-fast pre HTTP pri chýbajúcich
  kľúčových env, forceScheme https.
- b-mail rovnaký pre všetky firmy - bridge company per identita, vytváraná
  on demand (funguje bez predchádzajúceho serverového záznamu).
- Reverb websocket blokovaný CSP - wss dvojča app originu.
- Evolu worker mieril na mŕtvy default relay - identifikované, opravené
  nastavením VITE_EVOLU_RELAY_URL v build prostredí (infra).

## 3. Testy

- Frontend (vitest): 204 testov / 43 súborov; nové: backupState + logout
  policy, backup envelope round-trip + tamper detection, restore validácia,
  staleness/snooze, deriveRelaySyncState truth table, telemetria, storage
  estimate/persist/quota klasifikácia, import progress, relay URL validácia,
  bridgeCompanyEnsure kontrakt, dedupe fantómov.
- Backend (PHPUnit): 809+ testov / 2743+ assertions; nové: LogSanitizer,
  ProductionConfigValidator (vrátane [''] prípadu), SystemHealth endpoint +
  command, SystemMonitoring (snapshot+prune, alert throttle+recovery, error
  counter, history bez detailov, authz).
- Gates pri každej fáze: vitest, ESLint ratchet, vue-tsc baseline, build,
  locale-parity; PHPUnit, PHPStan, Pint.

## 4. Dokumentácia

- docs/OFFLINE_CAPABILITIES.md - poctivé vymedzenie offline schopností
  (cold start offline NEfunguje - bez service workera).
- docs/P1_RELIABILITY_REPORT.md - tento report.
- In-app: OfflineNoticeBanner, backup karta s varovaním o plaintext obsahu,
  merge poznámka pri obnove zálohy.

## 5. Konfigurácia

Nové env premenné (config/monitoring.php):
- SYSTEM_ALERT_EMAIL - adresa pre health alerty (prázdna = len log)
- SYSTEM_ALERT_THROTTLE_MINUTES (default 60)
- SYSTEM_ERROR_RATE_THRESHOLD (default 25 chýb/hod)
- SYSTEM_HEALTH_RETENTION_DAYS (default 7)

Boot validácia v produkcii vyžaduje: APP_KEY, BTCPAY_BASE_URL,
BTCPAY_API_KEY, SANCTUM_STATEFUL_DOMAINS (HTTP fail-fast, konzola len loguje).

Súvisiace (existujúce, dôležité pre správnu funkciu):
- CSP_EVOLU_RELAY_URL - server (CSP + health relay check)
- VITE_EVOLU_RELAY_URL - BUILD TIME transport Evolu workera; bez neho padne
  na verejný default (free.evoluhq.com)!

## 6. Monitoring

- SystemHealthService: database, queue (failed_jobs ≤ 10), btcpay
  (/api/v1/health), relay (usage probe), disk (≥ 500 MB), webhooks
  (≤ 48 h), errors (počet za hodinu < prah).
- php artisan system:health-check - à 5 min cez scheduler; snapshot do
  system_health_snapshots + prune; persist zlyhanie neblokuje alerting.
- Admin dashboard /admin/system-health: živé checky, error-rate karta,
  história ako intervaly rovnakého stavu; 30 s polling.
- ErrorRateCounter: MessageLogged listener, hodinové cache buckety,
  len počty - nikdy obsah správ.

## 7. Logovanie

- LogSanitizer::email ("s***@example.com") a ::iban ("SK31***7541") -
  aplikované na všetky miesta s PII v Log kontextoch; korelácia cez user_id.
- Overené: mnemonic/DEK/passphrase sa nelogujú nikde (vrátane P1 modulov).
- Rotácia: daily driver, 14 dní (config/logging.php) - bez zmeny.
- História health snapshotov nesie len mená zlyhaných checkov, nie detaily
  (môžu obsahovať exception messages).

## 8. Nasadenie

Poradie bolo dodržané po fázach (každý PR samostatne revertovateľný).
Pre nové prostredie:
1. .env: doplniť monitoring premenné (bod 5) + overiť CSP_EVOLU_RELAY_URL.
2. Build env: VITE_EVOLU_RELAY_URL=wss://evolu.satflux.io (kriticky dôležité).
3. php artisan migrate (system_health_snapshots) + optimize:clear.
4. Overiť scheduler (cron * * * * * php artisan schedule:run).

## 9. Rollback plán

- Každá fáza = 1 PR = git revert bez kaskády (jediná migrácia
  system_health_snapshots má down()).
- F4 rollback vráti "klamlivú" zelenú - kozmetický downgrade.
- F7 boot validácia: pri probléme stačí docasne doplniť chýbajúcu env,
  fail-fast sa navrhovo vyhýba konzole (deploy tooling beží vždy).
- Zálohy (F2/F3) sú čisto klientske - žiadny serverový stav na rollback.

## 10. Známe obmedzenia

- Žiadny service worker: cold start offline sa k lokálnym dátam nedostane.
- Žiadny pending-changes count (Evolu API neexistuje).
- createEvolu transport je build-time; profilová relay URL platí len pre
  manuálne push/pull (dokumentovaný mismatch, P2).
- Záloha je plaintext na vlastnom disku (vedomé rozhodnutie v1).
- Bulk PDF bez cancel (all-or-nothing).
- Error-rate countery sú per-proces cache - restart ich nuluje.

## 11. Otvorené problémy

- Duplicitné firmy z pre-stable-id éry: po oprave relay transportu sa
  zliali paralelné kópie datasetov. Diagnostika (id v alerte, merge
  onComplete+verify) je na fix/phantom-duplicate-company-rows; ak sa
  potvrdí plná paralelná kópia (duplicitné doklady), treba navrhnúť
  dokladový dedupe (číslo+typ+suma). PREBIEHA.
- P2 témy: backup šifrovanie (passphrase), offline PWA, e2e harness
  (Playwright), AbortController na bulk PDF, runtime relay transport.

## 12. Manuál použitia

- Záloha: Profil → Synchronizácia → "Exportovať dáta" (JSON so SHA-256;
  uchovávať ako papierový archív - je čitateľný). Obnova: "Obnoviť zo
  súboru" → preview → potvrdenie (merge do aktuálneho účtu).
- Sync ikona (fakturácia): zelená = relay dostupný + overená výmena (čas v
  tooltipe); šedá = zatiaľ neoverené/local-only; červená = nedostupný alebo
  chyba; oranžová = prebieha sync/migrácie. Klik = modal + probe.
- Úložisko: Profil ukazuje persistenciu a used/quota; banner pri ≥80 %.
- Health dashboard: Admin → Zdravie systému; alerty na SYSTEM_ALERT_EMAIL.
- Odhlásenie guest účtu s nezálohovanou frázou vyžaduje napísať potvrdenie -
  najprv frázu zapísať/exportovať dáta.

## 13. Riziká nasadenia a odporúčaný rollout

- F4 mení význam sync ikony (zelená je teraz vzácnejšia a pravdivá) -
  komunikovať používateľom.
- F7 fail-fast: pred nasadením overiť produkčné .env (chýbajúca premenná
  = 500 pre HTTP). Konzolové príkazy bežia vždy.
- VITE_EVOLU_RELAY_URL je build-time: zabudnutie = worker na mŕtvom
  defaulte; zaradiť do deploy checklistu.
- Alerting: nastaviť SYSTEM_ALERT_EMAIL až po overení SMTP, inak len logy.
- Odporúčané poradie pre ďalšie prostredia: konfigurácia (bod 8) → deploy →
  ručná kontrola /admin/system-health → zapnúť SYSTEM_ALERT_EMAIL.
