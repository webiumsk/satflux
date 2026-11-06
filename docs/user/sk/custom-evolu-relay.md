---
title: Pridanie vlastného Evolu relaya
category: security
order: 7
meta_description: Synchronizujte šifrované fakturačné dáta cez vlastný relay popri predvolenom.
updated: 2026-11-06
---

# Pridanie vlastného Evolu relaya

Pri local-first fakturácii môžete pripojiť vlastný Evolu relay popri predvolenom. Obnovovacia fráza aj šifrovacie kľúče zostávajú rovnaké. Oba relaye prijímajú šifrované dáta vrátane dát zdieľaných firiem, ku ktorým máte prístup.

1. Otvorte **Nastavenia účtu > Evolu sync**.
2. Zadajte adresu zabezpečeného WebSocket pripojenia, napríklad `wss://relay.example.com` alebo `wss://relay.example.com:8443`.
3. Ponechajte zapnutú možnosť používať aj predvolený relay a uložte nastavenie. Stránka sa obnoví, aby sa pripojenia prejavili.
4. Na každom zariadení sa prihláste a načítajte aktualizované nastavenia. Potom zatvorte **všetky karty Satfluxu** a znova otvorte Satflux. Tým sa reštartuje existujúci synchronizačný worker; obnovenie iba jednej karty nestačí.

Adresa musí používať `wss://` a nesmie obsahovať používateľské meno, heslo, cestu, parametre ani fragment. Server musí podporovať protokol Evolu kompatibilný s klientom Evolu 7.x používaným v Satfluxe; obyčajný WebSocket server nestačí.

Existujúce nastavenia vlastného relaya ho naďalej používajú samostatne, kým nezapnete možnosť používať aj predvolený. Vypnutím tejto možnosti používate iba vlastný relay. Vymazaním vlastnej adresy sa vrátite k nastavenému predvolenému relayu. Ak inštalácia predvolený relay vypína, pridanie vlastného nezapne verejný náhradný relay.

## Dostupnosť a obnova

Pripojenia fungujú nezávisle: nedostupnosť jedného relaya nevypína druhé pripojenie. Dáta sa dorovnávajú pri pripojení alebo opätovnom pripojení. Prijatie dát z jedného relaya nepotvrdzuje, že ich už má aj druhý. Nový relay môže potrebovať čas na dosynchronizovanie.

Test pripojenia overuje dostupnosť, nie kompatibilitu protokolu Evolu ani úplnosť zálohy. Štatistiky využitia sa pri nastavenej vlastnej adrese týkajú primárneho vlastného relaya. Nepotvrdzujú, že každý relay obsahuje úplnú kópiu.

Možnosť **Vypnúť synchronizáciu na tomto zariadení** vypne všetky relay pripojenia na danom zariadení. Ostatné zariadenia si ponechajú svoje nastavenia. Odstránenie relaya zastaví budúce pripojenia; nevymaže dáta už uložené na jeho serveri.

Bezpečne si uložte [obnovovaciu frázu](/documentation/backup-and-recovery) a pravidelne exportujte dáta. Obnova vyžaduje správnu frázu aj dostupnú kópiu dát.
