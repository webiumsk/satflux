---
title: Správa Evolu relayov
category: security
order: 7
meta_description: Spravujte šifrovanú synchronizáciu fakturačných dát cez viacero relayov.
updated: 2026-11-07
---

# Správa Evolu relayov

Satflux uchováva fakturačné dáta na vašom zariadení a synchronizuje šifrované zmeny cez relaye. Predvolený zoznam obsahuje `wss://evolu.satflux.io` a `wss://free.evoluhq.com`. Môžete pridať ďalšie kompatibilné relaye, najviac osem serverov.

1. Otvorte **Nastavenia účtu > Evolu sync**. Každý riadok zobrazuje adresu a aktuálny stav spojenia.
2. Vyberte **Pridať relay**, zadajte bezpečnú adresu, napríklad `wss://relay.example.com:8443`, a potvrďte **Pridať relay**. Existujúce relaye zostanú v zozname.
3. Otvorte riadok relaya, zobrazte spojenie a zapnite alebo vypnite **Používať tento relay**. Predvolené relaye zostávajú v zozname a môžete ich vypnúť. Vlastný relay môžete po potvrdení odstrániť.
4. Pred zatvorením kariet si uložte [obnovovaciu frázu](/documentation/backup-and-recovery). Na každom zariadení sa prihláste, načítajte aktualizované nastavenia, zatvorte **všetky karty Satfluxu** a otvorte Satflux znova. Tým sa reštartuje existujúci synchronizačný worker; obnovenie jednej karty nestačí.

Zmeny zoznamu platia pre váš účet. **Vypnúť synchronizáciu na tomto zariadení** pozastaví všetky relaye iba na aktuálnom zariadení. Vypnutie alebo odstránenie relaya zachová lokálne dáta a nevymaže dáta už uložené na serveri.

## Stavy spojenia

**Pripojený** znamená otvorené skutočné synchronizačné WebSocket spojenie. **Pripája sa**, **Odpojený** a **Chyba spojenia** opisujú toto spojenie. **Neznámy** znamená, že aktuálny stav nie je dostupný; po aktualizácii môže byť potrebné zatvoriť všetky karty a otvoriť ich znova. **Sync je pozastavený** znamená, že aktuálny lokálny vlastník ešte nemá povolenú synchronizáciu; obnovte fakturačné dáta správnou obnovovacou frázou. Vypnuté relaye a vypnutá synchronizácia zariadenia sa zobrazujú osobitne.

Otvorené spojenie nepotvrdzuje kompatibilitu protokolu, úspešné zápisy ani úplnú zálohu. Nainštalovaný klient potrebuje relay kompatibilný s Evolu 7.x. Spojenia fungujú nezávisle a chýbajúca história sa dopĺňa pri prihlásení na odber alebo novom pripojení. Relaye sa nekopírujú priamo medzi sebou; synchronizuje ich klient. Novému relayu doprajte čas na doplnenie dát.

Adresa musí používať `wss://` bez prihlasovacích údajov, cesty, parametrov či fragmentu. Duplicitné adresy sa odmietajú. Existujúce nastavenie iba vlastného relaya zostáva zachované; predvolené pridáte vedome cez **Pridať predvolené relaye**. Inštalácia s výslovne vypnutým predvoleným relayom automaticky nezapína verejné predvolené relaye. Staršie HTTPS adresy z konfigurácie používajú zodpovedajúce WSS spojenie. Pri nezabezpečenom WS/HTTP predvolenom relayi musí správca pred úpravou zoznamu nastaviť bezpečnú adresu.

## Dostupnosť a obnova

Verejný `free.evoluhq.com` je testovací relay a jeho dáta môžu byť zmazané. Pozrite si [dokumentáciu Evolu](https://www.evolu.dev/docs/relay). Uchovávajte samostatný [export dát](/documentation/backup-and-recovery) a obnovovaciu frázu. **Obnova cez relay** potrebuje správnu obnovovaciu frázu aj dostupnú kópiu dát; fráza samotná neobsahuje faktúry. **Exportovanú zálohu fakturačných dát** môžete obnoviť do iného účtu bez pôvodnej obnovovacej frázy. Šifrovaná záloha vyžaduje prístupovú frázu zvolenú pre daný súbor.

Veľkosť lokálnej SQLite databázy opisuje toto zariadenie a štatistiky využitia relaya iba prvý zapnutý relay. Ani jeden údaj nepotvrdzuje úplnú kópiu na každom relayi.
