---
title: Import nákladov zo SuperFaktúry
category: invoicing
order: 12
updated: 2026-11-16
---

# Import nákladov zo SuperFaktúry

Vo firme otvorte **Nástroje > Import**, vyberte **Náklady** a kliknite na **Import nákladov**. Import nájdete aj cez **Náklady > Import**.

1. Vyberte export XLSX zo SuperFaktúry alebo súbor XLS/CSV.
2. Skontrolujte navrhnuté mapovanie stĺpcov. Dátum vystavenia a suma sú povinné.
3. Spustite import a skontrolujte počet importovaných a preskočených riadkov aj dôvody preskočenia.
4. Cez **Zobraziť náklady** otvoríte záznamy zo všetkých rokov.

Aktuálny export SuperFaktúry s 33 stĺpcami sa rozpozná automaticky. Zachovajú sa pôvodné interné čísla, čísla dokladov, dátumy, sumy a symboly. Údaje dodávateľa, kategórie, úhrad a položiek sa uložia do internej poznámky. Import nevytvára kontakty dodávateľov ani samostatné položky nákladu.

Úhrada s dátumom a dostatočnou sumou v mene nákladu označí náklad ako uhradený. Čiastočné úhrady a úhrady v inej mene zostanú neuhradené na kontrolu so zachovanými údajmi úhrady. Pri starších súboroch obsahujúcich iba dátum úhrady zostáva pôvodné označenie ako uhradené.

Existujúce interné čísla sa v danej firme preskočia. Bez interného čísla sa pridelí nové; opakovaný import takých riadkov vytvorí nové náklady.

Limity sú **10 MB, 2 000 riadkov a 256 stĺpcov** na súbor. Neplatné alebo príliš dlhé riadky sa preskočia bez skracovania. Vzorce sa spracujú ako text.

Pri lokálnej fakturácii sa súbor spracuje vo vašom prehliadači a uloží medzi existujúce šifrované údaje. Zálohujte si obnovovaciu frázu. Prílohy PDF sa importujú samostatne.


## Vyhľadávanie a filtrovanie nákladov

Zoznam zúžite tlačidlami stavu a obdobím podľa dátumu vystavenia. Na začiatku sa zobrazuje aktuálny rok; môžete zvoliť všetky dátumy, predvolené obdobie alebo vlastné hranice. V ďalších filtroch nastavíte sumu, splatnosť a hľadaný text. Vyhľadáva sa v internom čísle, čísle dodávateľského dokladu, názve, variabilnom symbole a poznámkach.

Rozšírené podmienky potvrďte tlačidlom **Filtrovať**. Na mobile použite panel filtrov. **Vymazať filtre** (na mobile **Vymazať všetko**) odstráni vyhľadávanie aj ostatné podmienky. Zmena použitých filtrov zruší aktuálny výber. Súčty aj **vybrať všetky** používajú celý filtrovaný výsledok vrátane záznamov na ďalších stranách.

## Predvyplnenie nového nákladu

Variabilný symbol sa priebežne nastavuje podľa číslic v čísle dokladu vrátane úvodných núl. Dátum dodania sa nastavuje podľa dátumu prijatia. Ručnou úpravou predvyplnenej hodnoty sa jej prepojenie vypne. Výslovné hodnoty vyťažené z prílohy sa zachovajú; existujúce náklady si ponechajú uložené hodnoty. Ručne vymazaný dátum dodania zostane prázdny aj po uložení a opätovnom otvorení.

Ak číslo dokladu obsahuje viac ako 32 číslic, automatický variabilný symbol zostane prázdny a číslo dokladu sa zachová. Po skrátení čísla sa predvyplnenie obnoví, pokiaľ symbol neupravíte ručne.

Lokálne zmeny nákladov pred uložením počkajú na prebiehajúce párovanie platieb. Ak prehliadač nedokáže koordinovať zmeny, znovu načítajte kartu alebo použite aktuálny prehliadač. Ak výsledok nemožno potvrdiť, pred ďalším pokusom kartu znovu načítajte a skontrolujte uložený záznam. Neskoršia vedomá úprava uhradeného nákladu zachová jeho existujúci stav úhrady.

Pred zmenou sumy alebo meny prepojeného nákladu odpojte bankovú platbu. Offline párovania sa po synchronizácii znovu overia. Ak treba prepojenie skontrolovať, v Platbách aj pri náklade sa zobrazí upozornenie; pred opätovným párovaním nevyriešené bankové prepojenie odpojte. Ostatné párovania a samostatne zaznamenané úhrady sa zachovajú. Na všetkých zariadeniach používajte aktuálnu verziu aplikácie.

## Sumár nákladov

Sumár pod zoznamom **Nákladov** zahŕňa všetky doklady zodpovedajúce aktuálnym filtrom na všetkých stránkach. Zobrazuje počet, celkovú sumu a sumu neuhradených nákladov. Výber položiek súčet nemení. Každá mena má vlastné súčty.

Suma neuhradených nákladov zahŕňa celé sumy dokladov, ktoré nie sú označené ako uhradené. Čiastočné úhrady uložené v poznámkach z importu sa automaticky neodpočítavajú. Pred použitím súčtu ako zostávajúceho dlhu tieto doklady skontrolujte.

## PDF príloha k jednotlivému nákladu

Otvorte konkrétny náklad a pridajte jeho PDF. V lokálnom účtovníctve sa prílohy aj vyťažené údaje ukladajú medzi vaše šifrované lokálne dáta. Jedna lokálna príloha môže mať najviac **10 MiB**. Vo formulári vytvorenia alebo úpravy stlačte **Uložiť**, aby sa uložili pripravené prílohy. V detaile nákladu sa prílohy uložia ihneď po dokončení alebo preskočení voliteľného spracovania ISDOC.

Môžete povoliť dočasné spracovanie súboru na serveri na detekciu vložených údajov ISDOC. Zrušením zostane príloha iba lokálne. Ak sa ISDOC nájde, samostatne potvrďte vyťaženie údajov alebo ponechajte iba prílohu. Vyťaženie využíva váš existujúci limit a kredity; nejde o OCR. Po vyčerpaní limitu môžete súbor stále priložiť a údaje zadať ručne.

Ak sa ukladanie väčšej prílohy preruší, zopakujte ho, kým je súbor stále vybraný. Po obnovení stránky odstráňte neúplnú prílohu a znova vyberte pôvodný súbor. Pred vytvorením úplnej zálohy dokončite nahrávanie alebo neúplnú prílohu odstráňte.

## Platobné QR kódy

Pri neuhradenom náklade v EUR s názvom dodávateľa, platným IBAN-om dodávateľa a variabilným symbolom do 10 číslic kliknite v riadku na **Platobné QR**. Suma musí byť väčšia ako nula a mať najviac 12 číslic pred desatinnou čiarkou; názov dodávateľa môže mať najviac 70 znakov. Môžete prepnúť medzi **PAY by square** a **PayMe**. Pred potvrdením v bankovej aplikácii skontrolujte príjemcu, sumu a variabilný symbol. Kód sa tvorí vo vašom prehliadači. Tlačidlo Otvoriť PayMe vedome otvorí jeho platobný odkaz. Naskenovanie kódu neoznačí náklad ako uhradený. Pri sumách, ktoré PayMe nedokáže zapísať, použite PAY by square; suma sa nikdy nezaokrúhľuje.

Kód sa nezobrazí pri uhradených a zrušených nákladoch, spornom bankovom párovaní ani pri importe s čiastočnou alebo nevyriešenou úhradou. Platobné údaje pochádzajú z výslovne uložených údajov dodávateľa; vlastný účet firmy sa nepoužije. Nové vhodné importy eFaktúr a ISDOC zachovajú účet dodávateľa. Pri starších importoch bez týchto údajov treba najprv doplniť platobné údaje.

Pri vyťažení dokladu bez platobnej referencie zostane variabilný symbol prázdny a účet dodávateľa sa neimportuje. Overte pôvodný doklad a doplňte správny variabilný symbol. Platobné QR zostane nedostupné, kým nebudú doplnené a overené aj meno a IBAN dodávateľa.

Overené údaje doplníte úpravou nákladu: variabilný symbol zadajte do jeho poľa a do poľa **Interná poznámka** pridajte alebo nahraďte tieto dva riadky. Každý riadok ponechajte presne raz a zachovajte uvedené anglické názvy: Pred uložením tiež overte, že suma a mena nákladu presne zodpovedajú celej neuhradenej platbe v pôvodnom doklade a suma má najviac dve desatinné miesta.

```text
Supplier: VERIFIED_NAME
Supplier bank account: VERIFIED_IBAN
```

Hodnoty `VERIFIED_NAME` a `VERIFIED_IBAN` nahraďte názvom dodávateľa a IBAN-om overenými podľa pôvodného dokladu. Ostatné poznámky a údaje o platbách zachovajte, náklad uložte a vráťte sa do zoznamu. Postup platí aj vtedy, keď extrakcia účet vynechala; naďalej platia podmienky uvedené vyššie.
