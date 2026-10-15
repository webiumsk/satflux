---
title: Obnova Cashu zostatku
category: payments
order: 4
meta_description: Zobrazenie vrátenej Cashu rezervy na poplatky a bezpečná obnova do vlastnej peňaženky.
---

# Obnova Cashu zostatku

CashuMelt môže uchovávať nevyužitú rezervu na poplatky ako Cashu zostatok. Aj po
dokončení platieb preto môžu zostať prostriedky. Automatické odoslanie cez Lightning
začína od 100 sat oprávneného zostatku z rovnakého mintu; menší zostatok môže naďalej
blokovať zmenu peňaženky.

S CashuMelt **1.4.0.4 alebo novším** otvorte **Cashu vyrovnania** obchodu a panel
obnovy zostatku. Sumy sú v satoshi, počty záznamov sa uvádzajú samostatne. Tokeny
môže stiahnuť a obnovu overiť iba registrovaný vlastník obchodu s overeným emailom.

1. Vyberte stiahnutie tokenov a potvrďte upozornenie. Ak sa vyžaduje heslo, zadajte
   ho a následne zadajte šesťmiestny kód doručený emailom.
2. Súbor `cashumelt-change.json` uchovajte v súkromí. Obsahuje použiteľné tokeny.
   Tokeny z jeho JSON poľa importujte do vlastnej kompatibilnej Cashu peňaženky.
   Súbor neposielajte podpore ani inej osobe.
3. Po importe vyberte overenie obnovy a potvrďte ho novým emailovým kódom. Mint
   musí potvrdiť spotrebovanie exportovaných tokenov, aby sa obnova dokončila.

Samotné stiahnutie neuvoľní blokáciu zmeny peňaženky. Satflux po každom úkone obnoví
zostatok aj bezpečnostnú kontrolu; iné čakajúce záznamy môžu zmenu stále blokovať.
Ak sa stiahnutie stratí alebo výsledok nie je známy, obnovte prehľad a začnite nový
overený pokus. Neisté odoslania a nepodporované jednotky môžu vyžadovať podporu;
ich záznamy zostávajú zachované.

Starší plugin zobrazí, že túto možnosť nepodporuje. Nedostupná kontrola neznamená
nulový zostatok. Existujúca sekcia **Cashu recovery** v BTCPay zostáva dostupná na
podporované úkony obnovy priamo v plugine.
