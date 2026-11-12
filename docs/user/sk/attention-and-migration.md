---
title: Na vybavenie a migrácia
category: invoicing
order: 35
meta_description: Skontrolujte otvorené úlohy a bezpečne preneste tabuľkový export zo SuperFaktúry spolu s dodanými originálmi.
---

# Na vybavenie a migrácia

## Skontrolujte úlohy firmy

V navigácii firmy otvorte **Na vybavenie**. Skupiny Platby, Odosielanie,
Synchronizácia a Prijaté doklady zobrazujú dôvod problému a odkaz na jeho
existujúce riešenie. Obnovenie zoznamu znova skontroluje dostupné zdroje.
Neodosiela doklady, neimportuje faktúry ani nepotvrdzuje úhrady.

Nedostupný zdroj zostane viditeľný. Prázdny skontrolovaný zoznam sa týka iba
zdrojov dostupných s vašimi oprávneniami. Miestne párovanie platieb a história
odosielania sa kontrolujú na tomto zariadení; diagnostika synchronizácie sa týka
zariadenia a účtu. Niektoré kontroly prijatých dokladov vyžadujú vlastníka firmy.

## Preneste tabuľkový export

V časti **Nástroje > Import** vyberte SuperFaktúru alebo podporovanú všeobecnú
tabuľku. Vydané faktúry exportujte do tabuľky a vyberte cieľovú firmu, súbor,
formát dátumu a priradenie stĺpcov. Existujúci import nákladov ISDOC zostáva
v importe nákladov. Ďalšie adaptéry fakturačných služieb zatiaľ nie sú dostupné.

Náhľad kontroluje celý podporovaný súbor, ukazuje chyby a duplicity a sumarizuje
sumy, skutočné úhrady a zostatky oddelene podľa mien. Pred potvrdením vyriešte
konfliktné čísla faktúr, neplatné hodnoty a nejasné údaje o úhradách. Samotný
dátum platby nepreukazuje plnú úhradu. Chýbajúci dátum úhrady sa nedopĺňa
odhadom. Existujúce finančné záznamy sa potichu neprepisujú.

Voliteľne pridajte najviac 50 pôvodných súborov PDF/XML/ISDOC. Limit každého
súboru je 10 MiB a exportu s originálmi spolu 20 MiB. Pred vytvorením faktúr sa
musí potvrdiť uloženie aj spätné načítanie vybraných súborov. Zostávajú s vašimi
miestnymi fakturačnými dátami a šifrovanou synchronizáciou. Neskôr ich stiahnete
zo stránky importu aj po opätovnom otvorení prehliadača.

Archív uchová zdrojovú tabuľku a vybraté originály ako jednu dávku.

Pri SuperFaktúre vyberte súbor ISDOC alebo PDF s vloženým ISDOC. Sprievodca na
vašom zariadení prečíta položky a overí číslo faktúry, dodávateľa, odberateľa,
dátum, menu a sumu voči exportu a vybratej firme. Pred potvrdením si položky
rozbaľte v náhľade. Ak sa údaje nedajú prečítať alebo sa nezhodujú,
skontrolujte upozornenie a príslušné originály. Pri faktúrach bez overených
položiek môžete potvrdiť súhrnný import. Ak potrebujete pôvodný vzhľad a
historické daňové údaje, použite uložený originál PDF.

Overené odpočty sa zachovajú ako samostatné záporné položky, ak sedia
sumy položiek, DPH a celková suma faktúry.

Pred opakovaním prerušeného importu skontrolujte výsledok. Dokončené presné
duplicity sa preskočia; zmenené záznamy vyžadujú kontrolu. Uchovajte si obnovovacie
kľúče a zálohy: miestne uloženie nie je nový trvalý serverový archív.

Pred obnovou zálohy s uloženými originálmi aktualizujte Satflux. Staršie verzie
takýto formát zálohy odmietnu. Pôvodný súbor zálohy neupravujte.

Ak sprievodca hlási nepripravenú aktualizáciu miestneho úložiska, zatvorte
všetky karty Satfluxu v tomto prehliadači a znova otvorte aplikáciu. Z rovnakých
súborov vytvorte nový náhľad. Dokončené presné duplicity sa preskočia a overené
rozpracované záznamy môžu pokračovať. Chyba potvrdenia uloženia ukáže aj krok,
pri ktorom nastala.

Upozornenie na prekročený limit relay sa týka synchronizácie. Import môže
pokračovať po overení miestneho zápisu a spätného načítania. Limit úložiska
alebo služby dotknutého relay skontrolujete v nastaveniach synchronizácie.

Zoznam firiem zobrazuje overené miestne firmy počas synchronizácie relay.
Lokálne štatistiky v profile sa načítajú bez čítania originálov a čakania na
využitie relay. Tlačidlo Obnoviť môže navyše dopočítať veľkosť miestnej databázy.
Pri prebiehajúcej obnove sa najprv dokončí overenie účtu a údajov.


## Zaprite

V Nástroje > Import vyberte Zaprite. Použite API kľúč iba na čítanie zo
zdrojovej organizácie alebo úplný Zaprite JSON export stiahnutý zo Satfluxu.
Kľúč sa použije len na načítanie a neukladá sa. Po načítaní môžete zdrojový
JSON stiahnuť aj pred importom. Skontrolujte položky, sumy, úhrady a duplicity
a potom potvrďte import do vybranej firmy.

Adaptér podporuje vybrané fiat meny s dvomi desatinnými miestami a potvrdené
platby s presnými zdrojovými údajmi. BTC, LBTC a meny s inou presnosťou je
potrebné výslovne vynechať. Náhľad ukáže počty vynechaných faktúr a archivuje
sa celý JSON vrátane nich. Z vynechaných faktúr nevzniknú účtovné záznamy.
JSON je zdrojový API export, nie pôvodné PDF faktúry. Koncepty, nepodporované
úpravy, nejednoznačné úhrady a konfliktné čísla faktúr najprv skontrolujte
v zdrojovom systéme.
