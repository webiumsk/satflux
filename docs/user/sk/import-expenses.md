---
title: Import nákladov zo SuperFaktúry
category: invoicing
order: 12
updated: 2026-11-03
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

## Predvyplnenie nového nákladu

Variabilný symbol sa priebežne nastavuje podľa číslic v čísle dokladu vrátane úvodných núl. Dátum dodania sa nastavuje podľa dátumu prijatia. Ručnou úpravou predvyplnenej hodnoty sa jej prepojenie vypne. Výslovné hodnoty vyťažené z prílohy sa zachovajú; existujúce náklady si ponechajú uložené hodnoty. Ručne vymazaný dátum dodania zostane prázdny aj po uložení a opätovnom otvorení.

Ak číslo dokladu obsahuje viac ako 32 číslic, automatický variabilný symbol zostane prázdny a číslo dokladu sa zachová. Po skrátení čísla sa predvyplnenie obnoví, pokiaľ symbol neupravíte ručne.

Lokálne zmeny nákladov pred uložením počkajú na prebiehajúce párovanie platieb. Ak prehliadač nedokáže koordinovať zmeny, znovu načítajte kartu alebo použite aktuálny prehliadač. Ak výsledok nemožno potvrdiť, pred ďalším pokusom kartu znovu načítajte a skontrolujte uložený záznam. Neskoršia vedomá úprava uhradeného nákladu zachová jeho existujúci stav úhrady.

## Sumár nákladov

Sumár pod zoznamom **Nákladov** zahŕňa všetky doklady zodpovedajúce aktuálnym filtrom na všetkých stránkach. Zobrazuje počet, celkovú sumu a sumu neuhradených nákladov. Výber položiek súčet nemení. Každá mena má vlastné súčty.

Suma neuhradených nákladov zahŕňa celé sumy dokladov, ktoré nie sú označené ako uhradené. Čiastočné úhrady uložené v poznámkach z importu sa automaticky neodpočítavajú. Pred použitím súčtu ako zostávajúceho dlhu tieto doklady skontrolujte.

## PDF príloha k jednotlivému nákladu

Otvorte konkrétny náklad a pridajte jeho PDF. V lokálnom účtovníctve sa prílohy aj vyťažené údaje ukladajú medzi vaše šifrované lokálne dáta. Jedna lokálna príloha môže mať najviac **384 KiB**. Vo formulári vytvorenia alebo úpravy stlačte **Uložiť**, aby sa uložili pripravené prílohy. V detaile nákladu sa prílohy uložia ihneď po dokončení alebo preskočení voliteľného spracovania ISDOC.

Môžete povoliť dočasné spracovanie súboru na serveri na detekciu vložených údajov ISDOC. Zrušením zostane príloha iba lokálne. Ak sa ISDOC nájde, samostatne potvrďte vyťaženie údajov alebo ponechajte iba prílohu. Vyťaženie využíva váš existujúci limit a kredity; nejde o OCR. Po vyčerpaní limitu môžete súbor stále priložiť a údaje zadať ručne.
