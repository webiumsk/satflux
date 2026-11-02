---
title: Import nákladů ze SuperFaktury
category: invoicing
order: 12
updated: 2026-11-02
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
