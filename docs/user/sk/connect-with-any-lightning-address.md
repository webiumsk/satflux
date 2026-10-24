---
title: Pripojenie akoukoľvek Lightning adresou
category: wallet-connection
order: 2
meta_description: Nasmerujte Lightning backend obchodu na akúkoľvek adresu vy@penazenka - Blink, Blitz, Flash, Coinos alebo akúkoľvek LUD-21 peňaženku.
---

# Pripojenie akoukoľvek Lightning adresou

Otvorte **Pripojenie peňaženky**, vyberte **Lightning adresa**, zadajte `vy@penazenka.com` a pripojte ju. Satflux overí podporu overovania platieb pre **konkrétnu adresu**, vrátane Blink, Blitz, Flash a Coinos. Značka peňaženky ani spoločná doména nezaručujú podporu LUD-21.

## Priame Lightning pripojenie

Ak adresa podporuje **LUD-21**, Satflux ju pripojí cez LNAddressConnect. Staršia Coinos adresa a adresa presunutá do Coinos v3 môžu mať odlišné schopnosti aj na doméne `coinos.io`.

## Bez LUD-21 - CashuMelt (beta)

Ak adresa vráti platnú Lightning faktúru, ale nemá endpoint na overenie úhrady, Satflux skontroluje dostupnosť CashuMelt pre váš obchod. Ak je dostupný, zobrazí mint a pred uložením si vyžiada váš výslovný súhlas. Zachová existujúci výber mintu; inak ponúkne predvolený mint nastavený prevádzkovateľom.

Cashu vyrovnanie závisí od dôvery v mint a od poplatkov. Overuje Lightning výplatu poskytovateľovi adresy; nedokazuje jeho ďalší interný prevod, napríklad doručenie na Ark zostatok. Pozrite si [Cashu Lightning vyrovnanie](/documentation/cashu-lightning-settlement).

## Ak adresu nemožno overiť

Časový limit, neplatná odpoveď, chýbajúci plugin alebo nedokončená platba sa zobrazia ako chyba s možnosťou opakovania alebo vysvetlením nedostupnosti. Satflux peňaženku potichu neprepne. Zmena existujúcej peňaženky naďalej vyžaduje obvyklé potvrdenie a bezpečnostné kontroly platieb; existujúce faktúry si ponechajú pôvodné platobné nastavenia.

Na toto nastavenie potrebujete len Lightning adresu. Ďalšie možnosti nájdete v článku [Výber peňaženky](/documentation/choosing-your-wallet). Adresa na príjem hostovaná obchodom je samostatná funkcia: [Lightning adresy obchodu](/documentation/store-lightning-addresses).
