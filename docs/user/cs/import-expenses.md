---
title: Import nákladů ze SuperFaktury
category: invoicing
order: 12
updated: 2026-11-17
---

# Import nákladů ze SuperFaktury

Ve firmě otevřete **Nástroje > Import**, vyberte **Náklady** a zvolte **Import nákladů**. Import je dostupný také přes **Náklady > Import**.

1. Vyberte export XLSX ze SuperFaktury nebo soubor XLS/CSV.
2. Zkontrolujte mapování sloupců. Datum vystavení a částka jsou povinné.
3. Spusťte import a zkontrolujte počty importovaných a přeskočených řádků i důvody přeskočení.
4. Přes **Zobrazit náklady** otevřete záznamy ze všech let.

Aktuální export s 33 sloupci se rozpozná automaticky. Zachovají se původní interní čísla, čísla dokladů, data, částky a symboly. Údaje dodavatele, kategorií, úhrad a položek se uloží do interní poznámky. Nevytvářejí se kontakty dodavatelů ani samostatné položky nákladu.

Úhrada s datem a dostatečnou částkou v měně nákladu označí náklad jako uhrazený. Částečné úhrady a úhrady v jiné měně zůstanou neuhrazené ke kontrole se zachovanými údaji úhrady. Starší soubory obsahující pouze datum úhrady zachovávají původní označení jako uhrazené.

Existující interní čísla se v dané firmě přeskočí. Bez interního čísla se přidělí nové; opakovaný import takových řádků vytvoří nové náklady.

Limity: **10 MB, 2 000 řádků a 256 sloupců** na soubor. Neplatné nebo příliš dlouhé řádky se přeskočí bez zkrácení. Vzorce se zpracují jako text.

Při lokální fakturaci se soubor zpracuje v prohlížeči a uloží mezi stávající šifrované údaje. Zálohujte si obnovovací frázi. Přílohy PDF se importují samostatně.


## Vyhledávání a filtrování nákladů

Seznam zúžíte tlačítky stavu a obdobím podle data vystavení. Na začátku se zobrazuje aktuální rok; můžete zvolit všechna data, předvolené období nebo vlastní meze. V dalších filtrech nastavíte částku, splatnost a hledaný text. Vyhledává se v interním čísle, čísle dodavatelského dokladu, názvu, variabilním symbolu a poznámkách.

Rozšířené podmínky potvrďte tlačítkem **Filtrovat**. Na mobilu použijte panel filtrů. **Vymazat filtry** (na mobilu **Vymazat vše**) odstraní vyhledávání i ostatní podmínky. Změna použitých filtrů zruší aktuální výběr. Součty i **vybrat vše** používají celý filtrovaný výsledek včetně záznamů na dalších stránkách.

## Předvyplnění nového nákladu

Variabilní symbol se průběžně nastavuje podle číslic v čísle dokladu včetně úvodních nul. Datum dodání se nastavuje podle data přijetí. Ruční úpravou předvyplněné hodnoty se její propojení vypne. Výslovné hodnoty vytěžené z přílohy se zachovají; existující náklady si ponechají uložené hodnoty. Ručně vymazané datum dodání zůstane prázdné i po uložení a opětovném otevření.

Pokud číslo dokladu obsahuje více než 32 číslic, automatický variabilní symbol zůstane prázdný a číslo dokladu se zachová. Po zkrácení čísla se předvyplnění obnoví, dokud symbol neupravíte ručně.

Lokální změny nákladů před uložením počkají na probíhající párování plateb. Pokud prohlížeč nedokáže koordinovat změny, znovu načtěte kartu nebo použijte aktuální prohlížeč. Pokud výsledek nelze potvrdit, před dalším pokusem kartu znovu načtěte a zkontrolujte uložený záznam. Pozdější vědomá úprava uhrazeného nákladu zachová jeho existující stav úhrady.

Před změnou částky nebo měny propojeného nákladu odpojte bankovní platbu. Offline párování se po synchronizaci znovu ověří. Pokud je třeba propojení zkontrolovat, v Platbách i u nákladu se zobrazí upozornění; před opětovným párováním nevyřešené bankovní propojení odpojte. Ostatní párování a samostatně zaznamenané úhrady se zachovají. Na všech zařízeních používejte aktuální verzi aplikace.

## Souhrn nákladů

Souhrn pod seznamem **Nákladů** zahrnuje všechny doklady odpovídající aktuálním filtrům na všech stránkách. Zobrazuje počet, celkovou částku a součet neuhrazených nákladů. Výběr položek součet nemění. Každá měna má vlastní součty.

Součet neuhrazených nákladů zahrnuje celé částky dokladů, které nejsou označené jako uhrazené. Částečné úhrady uložené v poznámkách z importu se automaticky neodečítají. Než součet použijete jako zbývající dluh, tyto doklady zkontrolujte.

## PDF příloha k jednotlivému nákladu

Otevřete konkrétní náklad a přidejte jeho PDF. V lokálním účetnictví se přílohy i vytěžené údaje ukládají mezi vaše šifrovaná lokální data. Jedna lokální příloha může mít nejvýše **10 MiB**. Ve formuláři vytvoření nebo úpravy zvolte **Uložit**, aby se uložily připravené přílohy. V detailu nákladu se přílohy uloží ihned po dokončení nebo přeskočení volitelného zpracování ISDOC.

Můžete povolit dočasné zpracování souboru na serveru pro detekci vložených údajů ISDOC. Zrušením zůstane příloha pouze lokálně. Pokud se ISDOC najde, samostatně potvrďte extrakci údajů nebo ponechte pouze přílohu. Extrakce využívá váš stávající limit a kredity; nejde o OCR. Po vyčerpání limitu můžete soubor stále přiložit a údaje zadat ručně.

Pokud se ukládání větší přílohy přeruší, opakujte ho, dokud je soubor stále vybraný. Po obnovení stránky odstraňte neúplnou přílohu a znovu vyberte původní soubor. Před vytvořením úplné zálohy dokončete nahrávání nebo neúplnou přílohu odstraňte.

## Kontakt dodavatele a IBAN

U nového nákladu volitelně vyberte **Kontakt dodavatele**. Jeho IBAN se vloží do pole **IBAN dodavatele**. Pokud kontakt IBAN nemá, použije se účet bezpečně vytěžený z PDF/XML stejného dodavatele; jinak pole zůstane prázdné. Název a IBAN můžete zadat také ručně. U jiného dodavatele se vytěžený účet původního dodavatele nepřevezme.

Uložený náklad si ponechá název a účet i po pozdější změně kontaktu. Ruční úpravy a vymazaný účet zůstanou zachovány při dalším vytěžení; vědomou změnu proveďte v úpravě nákladu. Interní poznámka neurčuje příjemce QR platby.

## Platební QR kódy

U neuhrazeného nákladu v EUR s kladnou částkou, názvem dodavatele, platným IBANem a variabilním symbolem do 10 číslic klikněte na **Platební QR**. Vyberte **PAY by square** nebo **PayMe**. V bankovní aplikaci ověřte příjemce, přesnou částku a variabilní symbol. Vytvoření ani naskenování QR neoznačí náklad jako uhrazený. Pro větší přesné částky nepodporované PayMe použijte PAY by square.

Bez IBANu se QR nezobrazí. Uhrazené a zrušené náklady, sporná bankovní párování, záporné dobropisy a importy vyžadující kontrolu úhrady nemají platební QR. Výběr kontaktu neobejde částečné či zálohové platby ani nejednoznačné údaje z importu. Starší záznamy ISDOC/Peppol bez platebních podkladů zůstávají blokované. Údaje dodavatele z poznámky se do platebních polí nepřebírají.

## Ruční přidání přijatého dobropisu

V **Nákladech** vyberte **Nový přijatý dobropis**, nebo v novém nákladu zvolte typ **Přijatý dobropis**. Zadejte vlastní číslo dokladu a variabilní symbol, dodavatele a **zápornou celkovou částku**, pak uložte. Dobropisy zůstávají v Nákladech s označením a snižují součty. Nemají QR pro odchozí platbu ani párování s debetní úhradou nákladu.

Detail nákladu nabízí také **Přidat přijatý dobropis**. Předvyplní dodavatele a zápornou částku, vlastní číslo a variabilní symbol ponechá prázdné. Nevytváří trvalé propojení s původním nákladem. Vystavené zákaznické dobropisy zůstávají v Doklady > Dobropisy.
