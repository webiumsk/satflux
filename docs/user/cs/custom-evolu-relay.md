---
title: Přidání vlastního Evolu relaye
category: security
order: 7
meta_description: Synchronizujte šifrovaná fakturační data přes vlastní relay vedle výchozího.
updated: 2026-11-06
---

# Přidání vlastního Evolu relaye

Při local-first fakturaci můžete připojit vlastní Evolu relay vedle výchozího. Obnovovací fráze i šifrovací klíče zůstávají stejné. Oba relaye přijímají šifrovaná data včetně dat sdílených firem, ke kterým máte přístup.

1. Otevřete **Nastavení účtu > Evolu sync**.
2. Zadejte adresu zabezpečeného WebSocket připojení, například `wss://relay.example.com` nebo `wss://relay.example.com:8443`.
3. Ponechte zapnutou možnost používat i výchozí relay a uložte nastavení. Stránka se obnoví, aby se připojení projevila.
4. Na ostatních zařízeních po přihlášení obnovte stránku, aby načetla stejná nastavení.

Adresa musí používat `wss://` a nesmí obsahovat uživatelské jméno, heslo, cestu, parametry ani fragment. Server musí podporovat protokol Evolu kompatibilní s klientem Evolu 7.x používaným v Satfluxu; obyčejný WebSocket server nestačí.

Existující nastavení vlastního relaye jej nadále používají samostatně, dokud nezapnete možnost používat i výchozí. Vypnutím této možnosti používáte pouze vlastní relay. Vymazáním vlastní adresy se vrátíte k nastavenému výchozímu relayi. Pokud instalace výchozí relay vypíná, přidání vlastního nezapne veřejný náhradní relay.

## Dostupnost a obnova

Připojení fungují nezávisle: nedostupnost jednoho relaye nevypíná druhé připojení. Data se dorovnávají při připojení nebo opětovném připojení. Přijetí dat z jednoho relaye nepotvrzuje, že je již má i druhý. Nový relay může potřebovat čas na dosynchronizování.

Test připojení ověřuje dostupnost, nikoli kompatibilitu protokolu Evolu ani úplnost zálohy. Statistiky využití se při nastavené vlastní adrese týkají primárního vlastního relaye. Nepotvrzují, že každý relay obsahuje úplnou kopii.

Možnost **Vypnout synchronizaci na tomto zařízení** vypne všechna relay připojení na daném zařízení. Ostatní zařízení si ponechají svá nastavení. Odebrání relaye zastaví budoucí připojení; nevymaže data již uložená na jeho serveru.

Bezpečně si uložte [obnovovací frázi](/documentation/backup-and-recovery) a pravidelně exportujte data. Obnova vyžaduje správnou frázi i dostupnou kopii dat.
