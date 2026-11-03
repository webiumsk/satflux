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

## Sumár nákladov

Sumár pod zoznamom **Nákladov** zahŕňa všetky doklady zodpovedajúce aktuálnym filtrom na všetkých stránkach. Zobrazuje počet, celkovú sumu a sumu neuhradených nákladov. Výber položiek súčet nemení. Každá mena má vlastné súčty.

Suma neuhradených nákladov zahŕňa celé sumy dokladov, ktoré nie sú označené ako uhradené. Čiastočné úhrady uložené v poznámkach z importu sa automaticky neodpočítavajú. Pred použitím súčtu ako zostávajúceho dlhu tieto doklady skontrolujte.
