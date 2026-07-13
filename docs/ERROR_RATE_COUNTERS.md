# Error-rate countery (P2 fáza 6 - overenie a sémantika)

## Zhrnutie

`App\Support\ErrorRateCounter` počíta error+ log záznamy (error, critical,
alert, emergency) do hodinových bucketov cez `MessageLogged` listener
(AppServiceProvider). Len počty - nikdy obsah správ. Číta ich health check
`errors` (prah `SYSTEM_ERROR_RATE_THRESHOLD`) a admin dashboard.

Countery používajú **default cache store** (`Cache::` facade). Produkcia aj
lokálny Docker majú `CACHE_STORE=redis`, takže:

- countery sú **zdieľané naprieč procesmi** (php-fpm, queue worker,
  scheduler, artisan) - jeden bucket vidia všetci,
- **prežívajú restart** aplikácie aj deploy (žijú v Redise, nie v procese),
- inkrement je **atomický** (redis INCR; `Cache::add` + `Cache::increment`
  vzor je bezpečný aj pri súbehu - NX add vyhrá len raz, INCR je atomický).

Známa poznámka z P1 reportu ("per-proces cache - restart ich nuluje")
platila len pre `array`/`file` driver; pre redis (produkčná konfigurácia)
neplatí - opravené v P1 reporte.

## Overenie (2026-07-13, lokálny Docker stack s redis)

```
$ php artisan tinker: ErrorRateCounter::increment(); currentHourCount() → 13
$ redis-cli -n 1 --scan --pattern '*error_rate*'
d21_panel_database_satflux.error_rate.2026-07-13-20   ← aktuálna hodina
d21_panel_database_satflux.error_rate.2026-07-13-19   ← predchádzajúca
$ redis-cli -n 1 GET ..._2026-07-13-20 → 13
$ redis-cli -n 1 TTL ..._2026-07-13-20 → 4217 s
```

Kľúč = `{CACHE_PREFIX}satflux.error_rate.YYYY-MM-DD-HH` v redis DB pre
cache (`REDIS_CACHE_DB`, default 1).

## Životný cyklus bucketu

- Prvý error v hodine založí kľúč s TTL 2 h (`Cache::add`); ďalšie
  inkrementy TTL nepredlžujú.
- 2 h TTL zaručuje, že dashboard vždy prečíta aktuálny aj predchádzajúci
  hodinový bucket; staršie buckety exspirujú samy.
- `currentHourCount()` vracia `null` pri nečitateľnej cache (rozbitá cache
  sa dá odlíšiť od čistej nuly); health check to hlási ako neznámy stav.

## Prečo bez reset tlačidla na dashboarde

Zvažované a zamietnuté: buckety exspirujú samy do 2 h, countery sú dôkazový
materiál pre alert prah a manuálny reset by umožnil potichu umlčať aktívny
`errors` health check (číta ten istý bucket). Ak treba "reset", stačí
opraviť príčinu chýb - bucket ďalšej hodiny začína od nuly.

## Prevádzkové poznámky

- Lokálny dev bez redis (napr. `CACHE_STORE=file`) countery izoluje per
  container/súborový systém - na presnosti v deve nezáleží, sémantika API
  je rovnaká.
- `optimize:clear`/`cache:clear` buckety zmaže (flushuje cache store) -
  krátkodobý výpadok počtov po deploy skripte je očakávaný a neškodný.
- Countery zámerne nemajú perzistenciu mimo cache - dlhodobú históriu
  chybovosti nesú `system_health_snapshots` (scheduler à 5 min).
