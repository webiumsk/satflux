---
title: K vyřízení a migrace
category: invoicing
order: 35
meta_description: Zkontrolujte otevřené úkoly a bezpečně přeneste tabulkový export ze SuperFaktury včetně dodaných originálů.
---

# K vyřízení a migrace

## Zkontrolujte úkoly firmy

V navigaci firmy otevřete **K vyřízení**. Platby, Odesílání, Synchronizace
a Přijaté doklady obsahují důvod problému a odkaz na stávající místo řešení.
Obnovení znovu prověří dostupné zdroje. Neodesílá doklady, neimportuje faktury
ani nepotvrzuje úhrady. Nedostupné zdroje zůstávají viditelné.

Prázdný prověřený seznam se týká pouze zdrojů dostupných vašemu oprávnění.
Místní párování plateb a historie odesílání se kontrolují na tomto zařízení;
diagnostika synchronizace se týká zařízení a účtu. Některé kontroly příjmu
vyžadují vlastníka firmy.

## Přeneste tabulkový export

V **Nástroje > Import** vyberte SuperFakturu nebo podporovanou obecnou tabulku.
Exportujte vydané faktury do tabulky a zvolte cílovou firmu, soubor, formát dat
a přiřazení sloupců. Existující import nákladů ISDOC zůstává v importu nákladů.
Další adaptéry fakturačních služeb zatím nejsou dostupné.

Náhled kontroluje celý podporovaný soubor, zobrazuje chyby a duplicity a součty,
skutečné úhrady a zůstatky po měnách. Před potvrzením vyřešte konfliktní čísla,
neplatné hodnoty a nejasné úhrady. Samotné datum platby nedokládá plnou úhradu.
Chybějící datum úhrady se nevymýšlí a existující finanční záznamy se nepřepisují.

Volitelně přidejte nejvýše 50 původních PDF/XML/ISDOC souborů. Každý může mít
nejvýše 10 MiB, export a originály dohromady 20 MiB. Jejich uložení a zpětné
načtení se ověří před vytvořením faktur. Zůstanou s místními fakturačními daty
a šifrovanou synchronizací. Ze stránky importu je stáhnete i po novém otevření
prohlížeče.

Archiv uchová zdrojovou tabulku a vybrané originály jako jednu dávku.

U SuperFaktury vyberte soubor ISDOC nebo PDF s vloženým ISDOC. Průvodce na
vašem zařízení přečte položky a ověří číslo faktury, dodavatele, odběratele,
datum, měnu a částku proti exportu a vybrané firmě. Před potvrzením si položky
rozbalte v náhledu. Pokud údaje nelze přečíst nebo nesouhlasí, zkontrolujte
upozornění a příslušné originály. U faktur bez ověřených položek můžete
potvrdit souhrnný import. Pro původní vzhled a historické daňové údaje
použijte uložený originál PDF.

Ověřené odpočty se zachovají jako samostatné záporné položky, pokud souhlasí
částky položek, DPH a celková částka faktury.

Před opakováním přerušeného importu zkontrolujte výsledek. Dokončené přesné
duplicity se přeskočí; změněné záznamy vyžadují kontrolu. Uchovejte obnovovací
klíče a zálohy: místní uložení není nový trvalý serverový archiv.

Před obnovením zálohy s uloženými originály aktualizujte Satflux. Starší verze
tento formát zálohy odmítnou. Původní soubor zálohy neměňte.

Pokud průvodce hlásí nepřipravenou aktualizaci místního úložiště, zavřete
všechny karty Satfluxu v tomto prohlížeči a znovu otevřete aplikaci. Ze stejných
souborů vytvořte nový náhled. Dokončené přesné duplicity se přeskočí a ověřené
rozpracované záznamy mohou pokračovat. Chyba potvrzení uložení zobrazí také krok,
při kterém nastala.

Upozornění na překročený limit relay se týká synchronizace. Import může
pokračovat po ověření místního zápisu a zpětného načtení. Limit úložiště
nebo služby dotčeného relay zkontrolujete v nastavení synchronizace.

Seznam firem zobrazuje ověřené místní firmy během synchronizace relay.
Místní statistiky v profilu se načtou bez čtení originálů a čekání na využití
relay. Tlačítko Obnovit může navíc dopočítat velikost místní databáze.
Při probíhající obnově se nejprve dokončí ověření účtu a dat.


## Zaprite

V Nástroje > Import vyberte Zaprite. Použijte API klíč pouze pro čtení ze
zdrojové organizace nebo úplný Zaprite JSON export stažený ze Satfluxu. Klíč
se použije jen pro načtení a neukládá se. Zdrojový JSON si můžete stáhnout
již před importem. Zkontrolujte položky, částky, úhrady a duplicity a poté
potvrďte import do vybrané firmy.

Adaptér podporuje vybrané fiat měny se dvěma desetinnými místy a potvrzené
platby s přesnými zdrojovými údaji. BTC, LBTC a měny s jinou přesností je
nutné výslovně vynechat. Náhled ukáže počty vynechaných faktur a archivuje
se celý JSON včetně nich. Vynechané faktury se nestanou účetními záznamy.
JSON je zdrojový API export, nikoli původní PDF faktury. Koncepty, nepodporované
úpravy, nejednoznačné úhrady a konfliktní čísla faktur nejprve zkontrolujte
ve zdrojovém systému.
