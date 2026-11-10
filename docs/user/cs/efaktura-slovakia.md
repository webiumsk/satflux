---
title: Slovenská e-fakturace - aktuální omezení
category: invoicing
order: 6
meta_description: Aktuální stav a omezení připojení slovenské eFaktúry v Satfluxu.
updated: 2026-11-10
---

# Slovenská e-fakturace - aktuální omezení

**Kompletní napojení Satfluxu na poskytovatele slovenské eFaktúry zatím není ověřené. Satflux aktuálně neprezentujeme jako hotové řešení povinné slovenské e-fakturace.** Vyvíjí se propojení pro příjem, které ještě vyžaduje ověření s poskytovatelem. Termín dokončení není přislíbený.

## Export a doručení

Satflux umí exportovat faktury jako **UBL/XML**. Stažení souboru nedoručí fakturu odběrateli ani nepotvrzuje splnění zákonných povinností. Poskytovatel musí doklad přijmout a zajistit požadované doručení. Ruční nahrání je možností jen tehdy, pokud ho vybraný poskytovatel podporuje; tento kompletní postup zatím v Satfluxu není ověřený.

## Účet u poskytovatele a náklady

Poskytovatel může vyžadovat vlastní účet a zvlášť placený přístup k API. Bezplatná webová schránka nemusí zahrnovat propojení se Satfluxem. Před zaplacením nebo používáním poskytovatele si potvrďte cenu, podmínky používání a kompatibilitu se Satfluxem. Výběr poskytovatele na portálu slovenské Finanční správy automaticky nevytvoří funkční přístupové údaje v Satfluxu.

## Existující nastavení a doklady

Existující nastavení, historie dokladů a ovládací prvky propojení zůstávají zachované. Pokud propojení už používáte, v nastavení **e-fakturace** firmy si můžete zkontrolovat uloženého poskytovatele a možnosti odesílání nebo příjmu. Přístupové údaje uchovávejte v soukromí.

**Otestovat připojení** ověřuje přihlášení. Úspěšný výsledek ani dokončený kontrolní seznam nedokazuje, že poskytovatel fakturu přijal nebo doručil, že funguje příjem ani že proběhlo požadované hlášení. Stav a důkazy si ověřte u poskytovatele.

Pokud je panel dokladu dostupný, zobrazuje stav předání a referenci dokladu u poskytovatele. Schránka přijatých faktur umožňuje zkontrolovat přijaté doklady a importovat je jako náklady. Tyto prvky popisují existující funkce; jejich přítomnost nepotvrzuje ověřené kompletní napojení na poskytovatele.

## Plánování fakturace

Dokud nemáte ověřený postup, používejte službu, která dokáže doložit potřebné odesílání a příjem. Své konkrétní povinnosti a termíny si potvrďte u slovenské Finanční správy nebo účetního.

## Příjem přes ePostman

Až bude pilot příjmu dostupný, vyberte ePostman v nastavení e-fakturace správné slovenské firmy. Produkční a testovací účty jsou oddělené. Dokončete aktivaci u poskytovatele a zkontrolujte registraci pro příjem; úspěšný test připojení ověřuje pouze přístupové údaje. Tento konektor nezapíná odesílání přes ePostman.

Přijaté doklady se zobrazí v inboxu na stránce **Náklady**. Doklad můžete zkontrolovat nebo stáhnout původní XML. Import vytvoří náklad a uloží originál do vašich šifrovaných lokálních dat. Při chybě nebo příliš velké příloze zůstane položka dostupná pro obnovu; samotná existence nákladu import nedokončí. Dobropisy musí být rozlišeny a zpracovány jako opravy, nikoli potichu zaúčtovány jako běžné kladné náklady.

Dokud se lokální import úspěšně nedokončí, originál je na serveru šifrován klíči serveru; zpracovávají se tam také přístupové údaje poštára a rozparsovaný souhrn. Čekající originály se automaticky nemažou podle stáří. Po ověřeném lokálním převzetí se dočasný obsah serveru odstraní. Nejde o dlouhodobý serverový archiv Satfluxu. Přístup do archivu, dobu uchování a export si potvrďte s poštárem i pro případ zrušení účtu.

Připojení jednotlivých firem uchovávejte odděleně. Aktivace příjmu slovenské firmy nezapíná příjem zahraniční LLC. Tento návod nepotvrzuje ověřený příjem v reálné síti ani připravenost hlášení.
