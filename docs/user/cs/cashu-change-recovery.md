---
title: Obnova Cashu zůstatku
category: payments
order: 4
meta_description: Zobrazení vrácené Cashu rezervy na poplatky a bezpečná obnova do vlastní peněženky.
---

# Obnova Cashu zůstatku

CashuMelt může uchovávat nevyužitou rezervu na poplatky jako Cashu zůstatek. I po
dokončení plateb proto mohou zůstat prostředky. Automatické odeslání přes Lightning
začíná od 100 sat způsobilého zůstatku ze stejného mintu; menší zůstatek může stále
blokovat změnu peněženky.

Nastavení Cashu připojení zobrazují poslední bezpečnostní kontrolu změny peněženky
už před úpravou. Při blokaci použijte odkaz na vyrovnání a obnovu. Obnovení stavu
zachová rozpracovaná nastavení; nedostupná kontrola neznamená, že lze peněženku
bezpečně změnit.

S CashuMelt **1.4.0.5 nebo novějším** přehled bezpečnosti rozlišuje selhané platby,
ostatní nedokončené platby a dokončené záznamy s uchovanými podklady pro obnovu.
Celkový počet proto může převyšovat počet selhaných řádků. Dotčené řádky ukazují
důvod blokace a fázi obnovy. U staršího pluginu je tento rozpis neznámý.

Chyba kontroly stavu na mintu, například HTTP 400, nepotvrzuje úhradu ani neuhrazení.
Opakovaný pokus může obnovit podporované uložené podklady, ale chyba sama nepotvrdí
neuhrazenou platbu. Pokud původní mint již neposkytuje stav a neexistují použitelné
podklady pro obnovu, stav je třeba prověřit s jeho provozovatelem. Níže uvedená obnova
vráceného zůstatku tyto samostatné platební záznamy nevyřeší.

S CashuMelt **1.4.0.4 nebo novějším** otevřete **Cashu vyrovnání** obchodu a panel
obnovy zůstatku. Částky jsou v satoshi, počty záznamů se uvádějí samostatně. Tokeny
může stáhnout a obnovu ověřit pouze registrovaný vlastník obchodu s ověřeným emailem.

1. Vyberte stažení tokenů a potvrďte upozornění. Pokud je požadováno heslo, zadejte
   ho a následně zadejte šestimístný kód doručený emailem.
2. Soubor `cashumelt-change.json` uchovejte v soukromí. Obsahuje použitelné tokeny.
   Tokeny z jeho JSON pole importujte do vlastní kompatibilní Cashu peněženky.
   Soubor neposílejte podpoře ani jiné osobě.
3. Po importu vyberte ověření obnovy a potvrďte jej novým emailovým kódem. Mint
   musí potvrdit spotřebování exportovaných tokenů, aby byla obnova dokončena.

Samotné stažení neuvolní blokaci změny peněženky. Satflux po každém úkonu obnoví
zůstatek i bezpečnostní kontrolu; jiné čekající záznamy mohou změnu stále blokovat.
Pokud se stažení ztratí nebo výsledek není znám, obnovte přehled a začněte nový
ověřený pokus. Nejistá odeslání a nepodporované jednotky mohou vyžadovat podporu;
jejich záznamy zůstanou zachovány.

Starší plugin zobrazí, že tuto možnost nepodporuje. Nedostupná kontrola neznamená
nulový zůstatek. Stávající sekce **Cashu recovery** v BTCPay zůstává dostupná pro
podporované úkony obnovy přímo v pluginu.
