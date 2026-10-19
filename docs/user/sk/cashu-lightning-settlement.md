---
title: Cashu Lightning vyrovnanie (CashuMelt)
category: payments
order: 3
meta_description: Cashu mint použitý len ako Lightning settlement bridge - faktúry ostávajú Lightning; plugin CashuMelt vyplatí sats na vašu Lightning adresu.
---

# Cashu Lightning vyrovnanie (CashuMelt)

Satflux **nevystavuje** Cashu faktúry a zákazníci **nikdy neplatia ecash tokenmi**. Faktúry vášho obchodu sú **Lightning**, ako každé iné. **Cashu (cez BTCPay plugin CashuMelt) je len prevodník na vyrovnanie**: na pozadí použije Cashu mint, ktorý prijaté sats skonvertuje a vyplatí na **Lightning adresu, ktorú si zvolíte**.

Skrátka: **Lightning dnu → mint konvertuje → sats von na vašu Lightning adresu.** Mint je len technická vrstva, nie zákaznícka platobná metóda.

> **Beta.** Táto cesta je experimentálna. Faktúra sa môže zobraziť ako zaplatená skôr, než vyrovnané prostriedky dorazia do vašej Lightning peňaženky; výpadok mintu alebo problém so smerovaním môže spôsobiť oneskorenie alebo stratu. Pred zapnutím musíte zaškrtnúť potvrdzovacie pole.

## Nastavenie

Otvorte **Pripojenie peňaženky** obchodu a sekciu **Cashu**. Potvrďte beta upozornenie a vyplňte:

- **Mint URL** - Cashu mint použitý na konverziu (musí byť `https://`). Predvolený mint je navrhnutý.
- **Lightning adresa** - kam sa vyrovnané sats vyplácajú. **Povinné.**
- **Jednotka faktúry** - `sat`; výplata používa Lightning satoshi.
- **Vyrovnávať platby cez Cashu → Lightning** - nechajte zapnuté, aby obchod používal túto vyrovnávaciu cestu.
- Voliteľne: **dôveryhodné minty** a **stropy rezervy poplatku** (max v sats a/alebo ako % zo sumy), ktoré ohraničujú, koľko poplatku za smerovanie (routing) Lightning platby môže výplata minúť.

Úprava nastavenej Cashu peňaženky vyžaduje šesťmiestny kód zaslaný na email účtu. Účty s heslom potvrdia aj heslo; pri prihlásení frázou alebo passkeyom sa použije existujúce overenie. Hosť musí najprv doplniť email a dokončiť registráciu. Prvé nastavenie peňaženky nevyžaduje kód na potvrdenie zmeny.

Uloženie bez zmeny hodnôt iba overí existujúcu konfiguráciu. Skutočná zmena zostáva zablokovaná, kým platby alebo zostatok vyžadujú obnovu. Samotné vyrovnané alebo expirované platby nepotvrdzujú, že nezostal žiadny zostatok. Ak sa bezpečnosť nedá overiť, skúste to neskôr alebo kontaktujte podporu; táto chyba sa odlišuje od potvrdených nevysporiadaných prostriedkov.

## Dva spôsoby použitia

- **Primárny** - obchod vyrovnáva cez mint na vašu Lightning adresu, bez samostatného pripojenia Lightning peňaženky.
- **Automatický fallback** - keď pripojíte Lightning peňaženku, CashuMelt môže ostať zapnutý paralelne s vašou Lightning adresou, takže vyrovnanie funguje, aj keď je hlavná Lightning cesta dočasne nedostupná. Prepnutie na Blink/Aqua fallback vypne.

## Sledovanie vyrovnaní

Stránka **Cashu vyrovnania** vypisuje každé vyrovnanie a jeho stav - `SETTLED`, `PENDING`, `FAILED` alebo `MELT_COMPLETE` (skonvertované, záznam BTCPay dobieha). Zaseknuté riadky viete **skúsiť znova**. Bežné dôvody zlyhania sú zobrazené zrozumiteľne (napr. routing poplatok prekročil váš strop, alebo mint nepotvrdil Lightning výplatu).

Sekcia **Bezpečnosť zmeny peňaženky** zobrazuje poslednú kontrolu pluginu a oddelené počty nevyriešených záznamov platieb a vrátených prostriedkov. Ide o počty záznamov, nie sumy v satoshi. Záznamy vrátených prostriedkov sú oddelené od histórie platieb, preto samotné dokončené platby nepotvrdzujú, či možno peňaženku zmeniť. Ak sa pôvodný stav pluginu líši od zobrazeného stavu vyrovnania, prehľad upozorní na tento rozdiel.

Ak Cashu blokuje zmenu a nie je dostupná podporovaná možnosť obnovy, kontaktujte podporu s týmito dvoma počtami a zobrazeným stavom pluginu. Nulové alebo nedostupné počty nerušia blokáciu. Kontrola zobrazuje posledný známy stav; uloženie vykoná novú bezpečnostnú kontrolu.

## Obnova platby

CashuMelt **1.4.0.1 a novší** uloží pri každej novej platbe pôvodný mint a nastavenia výplaty ešte pred zobrazením faktúry. Samostatné Cashu platby fungujú bez ďalšieho pripojenia Lightning peňaženky. Pri dočasnom výpadku sa opakovanie opiera o existujúcu platbu a doklad o výplate.

Pri čakajúcej alebo neúspešnej platbe na stránke **Cashu vyrovnania** použite kontrolu/opakovanie platby. Pri `MELT_COMPLETE` sa doplní záznam BTCPay bez druhej výplaty obchodníkovi. Stav `MANUAL_REVIEW` vyžaduje kontrolu zobrazeného dôvodu; opakovaním nemožno obísť chýbajúce dôkazy o platbe.

Ak staršej platbe chýbajú pôvodné nastavenia, zvoľte **Obnoviť platbu**. Satflux vyberie platbu automaticky. Zadajte **pôvodný mint URL** a **pôvodnú Lightning adresu výplaty**, potvrďte, že platili pri tejto platbe, a kliknite **Potvrdiť a zopakovať výplatu**. Účty s heslom potvrdia aj heslo. Navrhnuté hodnoty pochádzajú z aktuálnych nastavení obchodu, preto overte, že sa od platby nezmenili. Tento formulár vyžaduje **CashuMelt 1.4.0.2 alebo novší**.

Plugin pred uložením údajov overí, že zadaný mint identifikuje presnú pôvodnú faktúru. Obnova nemení konfiguráciu peňaženky obchodu. Faktúru zákazníka nikdy neplaťte druhýkrát. Ak sa pôvodné údaje nedajú overiť, kontaktujte podporu alebo prevádzkovateľa mintu; platba zostáva chránená a neoznačí sa ako vyrovnaná.

## Dobré vedieť

- BTCPay obchod potrebuje plugin **CashuMelt** (aktuálnu verziu). Ak vidíte chybu pluginu, aktualizujte ho.
- Prostriedky sa vyrovnajú na vašu Lightning adresu, ale počas konverzie prechádzajú **cez mint** - krátky bod dôvery - preto používajte len mint, ktorému dôverujete.
