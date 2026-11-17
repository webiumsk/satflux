---
title: Import nákladov zo SuperFaktúry
category: invoicing
order: 12
updated: 2026-11-17
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

Hneď po výbere súboru sa aplikácia opýta na povolenie dočasného spracovania na serveri na kontrolu vloženého ISDOC alebo čitateľného textu PDF faktúry. Voľbou **Ponechať prílohu bez spracovania** kontrolu preskočíte. Pri prílohe vidíte stav kontroly a môžete ju zopakovať. Ak sa nájdu podporované údaje, samostatne potvrďte vyťaženie. Kontrola aj vyťaženie prebehnú pred **Uložiť** vo formulári; kým čaká rozhodnutie, uloženie je nedostupné. Každé úspešné vyťaženie sa raz započíta do vášho existujúceho limitu alebo kreditov. Nejde o OCR: obrázkové skeny môžete naďalej priložiť a údaje zadať ručne.

Textové PDF vytvorí čiastočne predvyplnený návrh podľa rozpoznaných slovenských, českých alebo anglických označení polí. Textové PDF môže mať najviac **25 strán** v rámci existujúceho limitu prílohy **10 MiB**. Chýbajúce, nejednoznačné alebo nepodporované údaje zostanú nevyplnené a nevymažú uloženého dodávateľa či účet. Porovnajte údaje s pôvodným dokladom. Vo formulári vytvorenia alebo úpravy ich po kontrole uložte. V detaile nákladu sa pri textovom PDF otvorí náhľad; náklad zmení až **Uložiť vyťažené údaje**, kým **Ponechať iba prílohu** jeho polia nezmení. Existujúce vyťaženie ISDOC v detaile naďalej údaje použije po potvrdení vyťaženia. Platobné údaje z textového PDF vždy vyžadujú kontrolu úhrady a nikdy automaticky neoznačia náklad ako uhradený ani nesprístupnia Platobné QR.

Ak sa ukladanie väčšej prílohy preruší, zopakujte ho, kým je súbor stále vybraný. Po obnovení stránky odstráňte neúplnú prílohu a znova vyberte pôvodný súbor. Pred vytvorením úplnej zálohy dokončite nahrávanie alebo neúplnú prílohu odstráňte.

## Kontakt dodávateľa a IBAN

Pri novom náklade voliteľne vyberte **Kontakt dodávateľa**. Jeho IBAN sa vloží do poľa **IBAN dodávateľa**. Ak kontakt IBAN nemá, použije sa účet bezpečne vyťažený z PDF/XML rovnakého dodávateľa; inak pole zostane prázdne. Názov a IBAN dodávateľa môžete zadať aj ručne. Pri odlišnom dodávateľovi sa vyťažený účet iného dodávateľa neprevezme.

Uložený náklad si ponechá názov a účet aj po neskoršej zmene kontaktu. Ručné úpravy a vymazaný účet zostanú zachované pri ďalšom vyťažení; vedomú zmenu vykonajte v úprave nákladu. Interná poznámka slúži na poznámky a neurčuje príjemcu QR platby.

## Platobné QR kódy

Pri neuhradenom náklade v EUR s kladnou sumou, názvom dodávateľa do 70 znakov, platným IBAN-om a variabilným symbolom do 10 číslic kliknite v riadku na **Platobné QR**. Vyberte **PAY by square** alebo **PayMe**. V bankovej aplikácii skontrolujte príjemcu, presnú sumu a variabilný symbol. Vytvorenie ani naskenovanie QR neoznačí náklad ako uhradený. Pri väčšej presnej sume, ktorú PayMe nepodporuje, použite PAY by square.

Bez IBAN-u sa QR nezobrazí. Uhradené a zrušené náklady, sporné bankové párovania, záporné dobropisy a importy vyžadujúce kontrolu úhrady nemajú platobné QR. Výber kontaktu kontrolu úhrady nenahrádza. Pri staršom neuhradenom náklade ISDOC/Peppol otvorte **Upraviť**, skontrolujte pôvodný doklad a históriu platieb a potvrďte presného príjemcu, IBAN, sumu, menu, variabilný symbol a to, že celá suma zostáva neuhradená. Po uložení bude **Platobné QR** dostupné v zozname nákladov. Ak platobné údaje zmeníte pred uložením, potvrďte ich znova. Evidovaný dátum platby, nenulová alebo nejasná uhradená suma a bankové prepojenie toto potvrdenie neumožňujú. Staré riadky dodávateľa v poznámke sa do platobných polí nepreberajú.

## Ručné pridanie prijatého dobropisu

V **Nákladoch** vyberte **Nový prijatý dobropis**, prípadne v novom náklade nastavte typ dokladu **Prijatý dobropis**. Zadajte jeho vlastné číslo dokladu a variabilný symbol, údaje dodávateľa a **zápornú celkovú sumu** a uložte ho. Prijaté dobropisy zostávajú v Nákladoch, majú vlastné označenie a znižujú súčty nákladov. Nevytvárajú QR na odchádzajúcu platbu ani sa nepárujú s debetnou úhradou nákladu.

V detaile nákladu je aj **Pridať prijatý dobropis**. Otvorí nový formulár s údajmi dodávateľa a zápornou sumou; nové číslo dokladu a variabilný symbol ostanú prázdne. Nevytvára sa tým trvalé prepojenie na pôvodný náklad. Vystavené dobropisy pre zákazníkov zostávajú v Doklady > Dobropisy.
