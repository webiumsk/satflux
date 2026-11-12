---
title: Slovenská e-fakturace - propojení ePostman
category: invoicing
order: 6
meta_description: Propojte firemní účet ePostman, přijímejte e-faktury a importujte jejich originály do nákladů v Satfluxu.
updated: 2026-11-12
---

# Slovenská e-fakturace - propojení ePostman

Propojení podporuje **příjem a ruční odesílání**. Příjem a import faktury i dobropisu byly ověřeny v testovací síti Peppol. Ruční odeslání obou typů přes ePostman do Recommandu bylo také potvrzeno v této testovací síti. Produkční aktivaci, zákonné hlášení a podmínky archivu poskytovatele je třeba potvrdit samostatně. Satflux neprezentujeme jako kompletní řešení povinné slovenské e-fakturace.

## Propojení firmy

Každou firmu připojte samostatně. Zapnutí příjmu slovenské firmy ho nezapne pro zahraniční LLC.

1. Otevřete nastavení **e-fakturace** správné firmy a zapněte e-fakturaci. Vyberte **ePostman** a příslušné prostředí. Produkční a testovací účty mají oddělené přístupové údaje; při změně prostředí potřebujete údaje daného prostředí. Adresu serveru ePostman vyplní Satflux.
2. Dokončete aktivaci firmy v ePostman. Pro produkci postupujte podle [návodu poskytovatele k aktivaci](https://postman.slovakodata.com/help/zaciname/onboarding/) a potvrďte registraci pro příjem. Výběr poskytovatele v Satfluxu nezaregistruje firmu u poskytovatele ani na slovenské Finanční správě. Při testování používejte výhradně testovací portál a testovací údaje.
3. V portálu ePostman otevřete **Nastavenia > SAPI klienti** a vytvořte samostatného klienta pro Satflux. Jeho **Client ID** a **Client Secret** zkopírujte do Satfluxu. Jde o údaje SAPI klienta, nikoli REST API klíč. Poskytovatel zobrazí secret jen při vytvoření; uchovávejte ho v soukromí. Viz [návod k nastavení poskytovatele](https://postman.slovakodata.com/help/portal/nastavenia/).
4. Zkontrolujte identitu firmy zobrazenou v Satfluxu. Identifikátor pro příjem se odvozuje z firemních údajů. Pokud průvodce hlásí chybějící DIČ, nejprve doplňte správné slovenské DIČ v údajích firmy. Použijte přístupové údaje patřící této firmě.
5. Vyberte **Otestovat připojení**. Test ověřuje pouze přístupové údaje. Nepotvrzuje registraci pro příjem, doručení dokladu ani splnění zákonných povinností. Před pokračováním opravte případnou chybu.
6. Nechte příjem zapnutý a nastavení **uložte**. Samotný úspěšný test neuloží celé nastavení příjmu. Vyřešte případnou chybu uložení nebo synchronizace. Po úspěšném uložení může Satflux stahovat příchozí doklady na pozadí i při zavřeném prohlížeči. Pokud formulář po uložení změněného připojení žádá nový test, zopakujte ho pro ověření uložených přístupových údajů.

Pokud je secret již uložený pro stejné připojení, jeho pole nechte prázdné. Při změně poskytovatele nebo prostředí zadejte údaje nového připojení a test zopakujte.

## Kde najít a importovat doklady

Ve stejné firmě otevřete **Náklady > Přijaté e-faktury**. Doklad zkontrolujte nebo stáhněte původní XML a poté ho importujte do nákladů. Dobropisy se importují jako opravy s odpovídajícím znaménkem částky. Úspěšné prázdné stažení znamená, že se nestáhly nové doklady; nepotvrzuje doručení testovací faktury.

Ruční stažení spustíte přes **Stáhnout příchozí nyní** v nastavení e-fakturace firmy. Následně obnovte inbox na stránce Náklady. Obnovení inboxu zobrazí doklady, které již Satflux stáhl.

Import uloží originál do vašich šifrovaných lokálních dat. Pokud import selže nebo je originál příliš velký pro lokální přílohu, položka zůstane dostupná pro obnovu a stažení originálu. Samotná existence nákladu import nedokončí.

## Uchování originálů a náklady poskytovatele

Dokud se lokální import ověřeně nedokončí, originál je na serveru šifrován klíči serveru. Zpracovávají se tam také přístupové údaje poskytovatele a rozparsovaný souhrn dokladu. Čekající originály se automaticky nemažou podle stáří. Po ověřeném lokálním převzetí se dočasný obsah serveru odstraní. Nejde o dlouhodobý serverový archiv Satfluxu. Přístup do archivu, dobu uchování a export si potvrďte s poskytovatelem i pro případ zrušení účtu.

Účet poskytovatele může mít zvláštní poplatky za API nebo podmínky používání. Bezplatná webová schránka nemusí zahrnovat přístup ze Satfluxu. Před používáním produkčního propojení si v ePostman potvrďte příslušný balíček, přístup SAPI a podmínky archivu.

## Ruční odesílání a zákonné povinnosti

Při tomto tuzemském odesílání musí mít obě strany zemi Slovensko a adresu `0245:<DIČ>` s vlastním desetimístným DIČ.

Tato první verze odesílání odmítne doklady se slevou na položce nebo faktuře, cenami se ztrátou přesnosti při exportu nebo částkami odlišnými od vystaveného dokladu. Tyto doklady bude možné odeslat až po doplnění příslušného exportu. Částečná úhrada se započítá do zůstatku v XML.

U oprávněné slovenské firmy, která je plným plátcem DPH, otevřete vystavenou tuzemskou B2B fakturu nebo dobropis a zvolte **Odeslat e-fakturu**. Nejprve uložte správné firemní propojení. Vystavitel i přístupové údaje musí patřit stejné firmě. Automatické odesílání přes ePostman zůstává vypnuté.

Úspěšné podání znamená **přijetí ke zpracování**. Doručení a daňové hlášení se zobrazují odděleně, pokud je nastaven Enterprise API klíč. Odkaz do portálu ePostman zůstává dostupný pro stejné produkční nebo testovací prostředí.

Při nejistém výsledku opakujte pokus pouze tehdy, když jej Satflux nabídne. Opakování používá původní požadavek a identifikátor v časovém limitu poskytovatele. Po jeho uplynutí nebo změně dokladu či propojení nejprve ověřte výsledek u poskytovatele. Nevytvářejte novou fakturu pouze pro zopakování nejistého podání.

## Automatické stavy doručení

V nastavení e-fakturace stejné firmy zadejte **ePostman API klíč** z **Nastavení > API Klíče** příslušného produkčního nebo testovacího portálu a nastavení uložte. Je oddělený od SAPI Client ID a Client Secret. Satflux ho uchovává šifrovaně na serveru a nezahrnuje ho do sdílených firemních dat. Uchovávejte ho v soukromí. Odstranění API klíče vypne automatické kontroly stavů; příjem a odesílání přes SAPI používají původní přístupové údaje.

Satflux pravidelně kontroluje odeslané doklady i při zavřeném prohlížeči. Panel dokladu zobrazuje doručení, potvrzení **C3 MLS** od příjemce a potvrzení daňového hlášení **C5** odděleně. U podporovaného dokladu můžete použít **Obnovit stav**, s krátkým odstupem mezi kontrolami. Otevřený viditelný panel průběžně načítá také uložené výsledky.

**UNCONFIRMED / TIMEOUT** znamená, že přístupový bod příjemce zprávu převzal, ale konečné MLS potvrzení nepřišlo včas. Samotný tento stav neznamená neúspěšné odeslání. Kladné C5 je potvrzení daňového hlášení podle údajů poskytovatele, nikoli obchodní schválení příjemcem. Při chybě kontroly zůstane viditelný poslední platný výsledek spolu s chybou kontroly. Expirovaný nebo odvolaný API klíč nahraďte v nastavení firmy.

U starších podání nemusí být automatická kontrola dostupná. Takové doklady zkontrolujte v portálu poskytovatele. Existující fakturu neposílejte znovu pouze kvůli získání stavu.

Konkrétní povinnosti a termíny firmy si potvrďte u slovenské finanční správy nebo účetního. Samotné funkční propojení nepotvrzuje splnění všech povinností odesílání a hlášení.
