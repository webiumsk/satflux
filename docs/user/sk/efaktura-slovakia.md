---
title: Slovenská e-fakturácia - aktuálne obmedzenia
category: invoicing
order: 6
meta_description: Aktuálny stav a obmedzenia prepojenia slovenskej eFaktúry v Satfluxe.
updated: 2026-11-10
---

# Slovenská e-fakturácia - aktuálne obmedzenia

**Kompletné napojenie Satfluxu na poskytovateľa slovenskej eFaktúry zatiaľ nie je overené. Satflux aktuálne neprezentujeme ako hotové riešenie povinnej slovenskej e-fakturácie.** Vyvíja sa prepojenie na prijímanie, ktoré ešte vyžaduje overenie s poskytovateľom. Termín dokončenia nie je prisľúbený.

## Export a doručenie

Satflux dokáže exportovať faktúry ako **UBL/XML**. Stiahnutie súboru nedoručí faktúru odberateľovi ani nepotvrdzuje splnenie zákonných povinností. Poskytovateľ musí doklad prijať a zabezpečiť požadované doručenie. Manuálne nahratie je možnosťou len vtedy, ak ho vybraný poskytovateľ podporuje; tento kompletný postup zatiaľ v Satfluxe nie je overený.

## Účet u poskytovateľa a náklady

Poskytovateľ môže vyžadovať vlastný účet a osobitne platený API prístup. Bezplatná webová schránka nemusí zahŕňať prepojenie so Satfluxom. Pred zaplatením alebo používaním poskytovateľa si potvrďte cenu, podmienky používania a kompatibilitu so Satfluxom. Výber poskytovateľa na portáli Finančnej správy automaticky nevytvorí funkčné prístupové údaje v Satfluxe.

## Existujúce nastavenia a doklady

Existujúce nastavenia, história dokladov a ovládacie prvky prepojenia zostávajú zachované. Ak prepojenie už používate, v nastaveniach **e-fakturácie** firmy si môžete skontrolovať uloženého poskytovateľa a možnosti odosielania alebo prijímania. Prístupové údaje uchovávajte v súkromí.

**Otestovať pripojenie** overuje prihlásenie. Úspešný výsledok ani dokončený kontrolný zoznam nedokazuje, že poskytovateľ faktúru prijal alebo doručil, že funguje príjem, ani že prebehlo požadované hlásenie. Stav a dôkazy si overte u poskytovateľa.

Ak je panel dokladu dostupný, zobrazuje stav odovzdania a referenciu dokladu u poskytovateľa. Inbox prijatých faktúr umožňuje skontrolovať prijaté doklady a importovať ich ako náklady. Tieto prvky opisujú existujúce funkcie; ich prítomnosť nepotvrdzuje overené kompletné napojenie na poskytovateľa.

## Plánovanie fakturácie

Kým nemáte overený postup, používajte službu, ktorá vie preukázať potrebné odosielanie a prijímanie. Svoje konkrétne povinnosti a termíny si potvrďte na Finančnej správe alebo u účtovníka.

## Prijímanie cez ePostman

Keď bude pilot prijímania dostupný, vyberte ePostman v nastaveniach e-fakturácie správnej slovenskej firmy. Produkčné a testovacie účty sú oddelené. Dokončite aktiváciu u poskytovateľa a skontrolujte registráciu na prijímanie; úspešný test pripojenia overuje iba prístupové údaje. Tento konektor nezapína odosielanie cez ePostman.

Prijaté doklady sa zobrazia v inboxe na stránke **Náklady**. Doklad môžete skontrolovať alebo stiahnuť pôvodné XML. Import vytvorí náklad a uloží originál do vašich šifrovaných lokálnych dát. Pri chybe alebo príliš veľkej prílohe zostane položka dostupná na obnovu; samotná existencia nákladu import nedokončí. Dobropisy musia byť rozlíšené a spracované ako opravy, nie potichu zaúčtované ako bežné kladné náklady.

Kým sa lokálny import úspešne nedokončí, originál je na serveri šifrovaný kľúčmi servera; spracúvajú sa tam aj prístupové údaje poštára a rozparsovaný súhrn. Čakajúce originály sa automaticky nemažú podľa veku. Po overenom lokálnom prevzatí sa dočasný obsah servera odstráni. Nejde o dlhodobý serverový archív Satfluxu. Prístup do archívu, dobu uchovania a export si potvrďte s poštárom aj pre prípad zrušenia účtu.

Pripojenia jednotlivých firiem uchovávajte oddelene. Aktivácia príjmu slovenskej firmy nezapína príjem zahraničnej LLC. Tento návod nepotvrdzuje overené prijímanie v reálnej sieti ani pripravenosť hlásenia.
