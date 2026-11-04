---
title: Import nákladů ze SuperFaktury
category: invoicing
order: 12
updated: 2026-11-03
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

## Předvyplnění nového nákladu

Variabilní symbol se průběžně nastavuje podle číslic v čísle dokladu včetně úvodních nul. Datum dodání se nastavuje podle data přijetí. Ruční úpravou předvyplněné hodnoty se její propojení vypne. Výslovné hodnoty vytěžené z přílohy se zachovají; existující náklady si ponechají uložené hodnoty. Ručně vymazané datum dodání zůstane prázdné i po uložení a opětovném otevření.

Pokud číslo dokladu obsahuje více než 32 číslic, automatický variabilní symbol zůstane prázdný a číslo dokladu se zachová. Po zkrácení čísla se předvyplnění obnoví, dokud symbol neupravíte ručně.

Lokální změny nákladů před uložením počkají na probíhající párování plateb. Pokud prohlížeč nedokáže koordinovat změny, znovu načtěte kartu nebo použijte aktuální prohlížeč. Pokud výsledek nelze potvrdit, před dalším pokusem kartu znovu načtěte a zkontrolujte uložený záznam. Pozdější vědomá úprava uhrazeného nákladu zachová jeho existující stav úhrady.

## Souhrn nákladů

Souhrn pod seznamem **Nákladů** zahrnuje všechny doklady odpovídající aktuálním filtrům na všech stránkách. Zobrazuje počet, celkovou částku a součet neuhrazených nákladů. Výběr položek součet nemění. Každá měna má vlastní součty.

Součet neuhrazených nákladů zahrnuje celé částky dokladů, které nejsou označené jako uhrazené. Částečné úhrady uložené v poznámkách z importu se automaticky neodečítají. Než součet použijete jako zbývající dluh, tyto doklady zkontrolujte.

## PDF příloha k jednotlivému nákladu

Otevřete konkrétní náklad a přidejte jeho PDF. V lokálním účetnictví se přílohy i vytěžené údaje ukládají mezi vaše šifrovaná lokální data. Jedna lokální příloha může mít nejvýše **384 KiB**. Ve formuláři vytvoření nebo úpravy zvolte **Uložit**, aby se uložily připravené přílohy. V detailu nákladu se přílohy uloží ihned po dokončení nebo přeskočení volitelného zpracování ISDOC.

Můžete povolit dočasné zpracování souboru na serveru pro detekci vložených údajů ISDOC. Zrušením zůstane příloha pouze lokálně. Pokud se ISDOC najde, samostatně potvrďte extrakci údajů nebo ponechte pouze přílohu. Extrakce využívá váš stávající limit a kredity; nejde o OCR. Po vyčerpání limitu můžete soubor stále přiložit a údaje zadat ručně.
