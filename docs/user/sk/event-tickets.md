---
title: Vstupenky a poradie typov
category: payments
order: 20
updated: 2026-10-22
---

# Vstupenky a poradie typov

Podujatia vytvárate a upravujete v existujúcej aplikácii Tickets. Verejná ponuka používa názov, formátovaný popis, fyzický alebo virtuálny typ, miesto, dátumy, obrázok, menu a nastavenia kapacity.

## Zoradenie typov lístkov

Rozbaľte podujatie a otvorte **Typy lístkov**. Zvoľte **Upraviť poradie**, presuňte typ alebo použite šípky hore/dole a **Uložiť poradie**. **Zrušiť** zahodí rozpracované poradie. Zoraďujete aj neaktívne typy; verejná ponuka ich vynechá a zachová vzájomné poradie ostatných. Nové typy pribúdajú na koniec. Ceny a vydané lístky sa nemenia.

Zoraďovanie vyžaduje kompatibilný plugin **Satflux Tickets 2.1.0**. Ak podujatie medzitým zmenil iný správca, pred ďalším uložením načítajte aktuálne poradie.

## Verejný predaj

Zdieľajte existujúci verejný odkaz na podujatie na svojom BTCPay serveri. Návštevník vyberie lístky a vyplní údaje účastníkov na jednej stránke, potom zaplatí v BTCPay. Od verzie Satflux Tickets 2.1.1 stránka používa jazyk prehliadača a ponúka výber jazyka (EN/SK/ES/DE/CS). Od verzie Satflux Tickets 2.2.0 sa fyzické podujatia zobrazujú v časovom pásme miesta konania s jeho UTC posunom bez ohľadu na polohu návštevníka. Virtuálne podujatia používajú pásmo prehliadača návštevníka; náhradné zobrazenie je jasne označené UTC. Lístky pre rovnaký e-mail prídu spoločne; rôzni príjemcovia dostanú svoje lístky samostatne. Pokyny na pripojenie k virtuálnemu podujatiu ponechajte v nastavení podujatia a e-mailov.

Od verzie Satflux Tickets 2.1.2 zvoľte jazyk v hlavičke stránky; texty sa zmenia ihneď. Vybrané lístky a rozpracované údaje príjemcov zostanú zachované.

## Časové pásmo podujatia

Pri fyzickom podujatí vyberte v existujúcom formulári IANA pásmo miesta konania, napríklad Europe/Bratislava. Zadajte čas platný na mieste podujatia. Existujúce fyzické podujatia získajú Europe/Bratislava pri schválenej historickej migrácii; uložené UTC okamihy sa nemenia. Samotná zmena pásma mení zobrazenie, nie termín. Úprava dátumu mení termín; neexistujúcu alebo nejednoznačnú hodinu pri zmene času treba pred uložením opraviť. Pred použitím nových časových polí treba aktualizovať starší plugin. Po chybe zostane formulár rozpracovaný.

Pri novom fyzickom podujatí Satflux predvyplní uložené pásmo obchodu, ak je podporované. V rovnakom formulári môžete vybrať iné pásmo miesta konania. Výber sa uloží k podujatiu; neskoršia zmena pásma obchodu existujúce podujatia nemení. Priamo v BTCPay vyberte pásmo miesta konania ručne.
