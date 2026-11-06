---
title: Správa Evolu relayů
category: security
order: 7
meta_description: Spravujte šifrovanou synchronizaci fakturačních dat přes více relayů.
updated: 2026-11-07
---

# Správa Evolu relayů

Satflux uchovává fakturační data na vašem zařízení a synchronizuje šifrované změny přes relaye. Výchozí seznam obsahuje `wss://evolu.satflux.io` a `wss://free.evoluhq.com`. Můžete přidat další kompatibilní relaye, nejvýše osm serverů.

1. Otevřete **Nastavení účtu > Evolu sync**. Každý řádek ukazuje adresu a aktuální stav spojení.
2. Zvolte **Přidat relay**, zadejte bezpečnou adresu, například `wss://relay.example.com:8443`, a potvrďte **Přidat relay**. Existující relaye zůstanou v seznamu.
3. Otevřete řádek relaye, zobrazte spojení a zapněte nebo vypněte **Používat tento relay**. Výchozí relaye zůstávají v seznamu a lze je vypnout. Vlastní relay lze po potvrzení odstranit.
4. Před zavřením karet si uložte [obnovovací frázi](/documentation/backup-and-recovery). Na každém zařízení se přihlaste, načtěte aktualizovaná nastavení, zavřete **všechny karty Satfluxu** a znovu otevřete Satflux. Tím se restartuje existující synchronizační worker; obnovení jedné karty nestačí.

Změny seznamu platí pro váš účet. **Vypnout synchronizaci na tomto zařízení** pozastaví všechny relaye pouze na aktuálním zařízení. Vypnutí či odstranění relaye zachová místní data a nesmaže data již uložená na serveru.

## Stavy spojení

**Připojeno** znamená otevřené skutečné synchronizační WebSocket spojení. **Připojuje se**, **Odpojeno** a **Chyba spojení** popisují toto spojení. **Neznámý** znamená, že aktuální stav není dostupný; po aktualizaci může být nutné zavřít všechny karty a znovu je otevřít. **Sync je pozastavený** znamená, že aktuální místní vlastník ještě nemá povolenou synchronizaci; obnovte fakturační data správnou obnovovací frází. Vypnuté relaye a vypnutá synchronizace zařízení se zobrazují samostatně.

Otevřené spojení nepotvrzuje kompatibilitu protokolu, úspěšné zápisy ani úplnou zálohu. Nainstalovaný klient potřebuje relay kompatibilní s Evolu 7.x. Spojení fungují nezávisle a chybějící historie se doplňuje při přihlášení k odběru nebo novém připojení. Relaye se nekopírují přímo mezi sebou; synchronizuje je klient. Novému relayi dejte čas na doplnění dat.

Adresa musí používat `wss://` bez přihlašovacích údajů, cesty, parametrů či fragmentu. Duplicitní adresy se odmítají. Existující nastavení pouze vlastního relaye zůstává zachované; výchozí přidáte přes **Přidat výchozí relaye**. Instalace s výslovně vypnutým výchozím relayem automaticky nezapíná veřejné výchozí relaye. Starší HTTPS adresy z konfigurace používají odpovídající WSS spojení. U nezabezpečeného WS/HTTP výchozího relaye musí správce před úpravou seznamu nastavit bezpečnou adresu.

## Dostupnost a obnova

Veřejný `free.evoluhq.com` je testovací relay a jeho data mohou být smazána. Viz [dokumentace Evolu](https://www.evolu.dev/docs/relay). Uchovávejte samostatný [export dat](/documentation/backup-and-recovery) a obnovovací frázi. **Obnova přes relay** potřebuje správnou obnovovací frázi i dostupnou kopii dat; fráze samotná neobsahuje faktury. **Exportovanou zálohu fakturačních dat** můžete obnovit do jiného účtu bez původní obnovovací fráze. Šifrovaná záloha vyžaduje přístupovou frázi zvolenou pro daný soubor.

Velikost místní SQLite databáze popisuje toto zařízení a statistiky využití relaye pouze první zapnutý relay. Ani jeden údaj nepotvrzuje úplnou kopii na každém relayi.
