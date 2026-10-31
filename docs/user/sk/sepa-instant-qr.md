---
title: SEPA Instant QR (eurové bankové platby)
category: payments
order: 5
meta_description: Prijímajte eurové bankové prevody cez SEPA Instant QR kód, popri Bitcoine.
---

# SEPA Instant QR (eurové bankové platby)

**SEPA Instant QR** umožňuje prijímať **eurové bankové prevody** pri platbe: zákazník naskenuje QR kód v bankovej aplikácii a pošle SEPA Instant platbu. Sedí popri vašich Bitcoin a Lightning možnostiach.

## Nastavenie

1. Otvorte obchod a prejdite na **SEPA Instant QR**.
2. Zadajte bankové údaje (účet, ktorý má prijímať eurové prevody) a zapnite metódu.
3. Voliteľne nechajte banku posielať notifikácie priamo na b-mailovú adresu obchodu na kontrolu. Aktuálne e-mailové formáty vyžadujú ručné overenie a potvrdenie.

## Overovanie bankového e-mailu

Automatické potvrdenie vyžaduje overený pôvod z banky a zhodu prijímacieho účtu,
referencie platby, sumy a meny. Správy s chýbajúcimi alebo nejednoznačnými údajmi
neoznačia faktúru ako zaplatenú. Ak automatické overenie nie je dostupné,
skontrolujte prevod v bankovej aplikácii a použite ručné potvrdenie.

Operátor servera musí nastaviť dôveryhodné overovanie bankových správ. Samotné
doručenie cez poskytovateľa e-mailu neoveruje odosielajúcu banku. Nepodporované
formáty správ a staršie verzie pluginu vyžadujú ručnú kontrolu. Čas potvrdenia
závisí od doručenia bankovej notifikácie a spracovania; okamžité potvrdenie nie je
zaručené. FIO používa interval kontroly obmedzený bankou.

E-maily Tatra banky a SLSP aktuálne vyžadujú ručnú kontrolu. Referenciu platby nemožno spoľahlivo oddeliť od textu platiteľa ani pri overenom odosielateľovi z banky. Pred ručným potvrdením skontrolujte prevod v bankovej aplikácii. Automatické potvrdenie cez Fio a NOP zostáva dostupné.

## Kde je moja platba

Každá čakajúca platba alebo platba na kontrolu, ktorej referencia začína na `QR-`, má tlačidlo **Kde je moja platba**. Opýta sa verejnej diagnostiky Notifikátora okamžitých platieb (NOP) Finančnej správy SR, čo o tejto referencii vie, a ukáže časovú os: vznik ID transakcie, uloženie oznámenia banky, spárovanie s pokladnicou, sprístupnenie, prijatie.

Čo očakávať:

- **NOP toto ID pozná** sa zobrazí pri obchodoch, ktoré potvrdzujú cez NOP s eKasa certifikátom, a pri platbách, ktoré nahlásil notifikačný bankový účet (Tatra banka, SLSP).
- **NOP toto ID nevidel** je bežná odpoveď pri obchodoch s ručným potvrdzovaním, cez Fio alebo s bankovými e-mailmi na ručnú kontrolu: ich referencie sa generujú lokálne, takže NOP nemá čo ukázať, ani keď peniaze už prišli (vrátane platieb už potvrdených cez Fio). Neznamená to, že zákazník nezaplatil - skontrolujte bankový účet.
- Časová os nikdy nehovorí, na ktorý účet peniaze prišli. Pred označením platby ako zaplatenej ju vždy skontrolujte v bankovej aplikácii - satflux z tejto obrazovky nič nepotvrdzuje.

Rovnaké údaje ukazuje aj [kdejemojaplatba.sk](https://www.kdejemojaplatba.sk/), nezávislý prehliadač tej istej služby.

## Poznámky

- Dostupné pre všetky účty vrátane hostí.
- Platby prídu na **váš bankový účet** - je to eurový platobný kanál, ktorú ovládate vy, oddelená od Bitcoinu.
- Dobre sa dopĺňa s [fakturáciou](/documentation/getting-started-with-invoicing) a [párovaním bankových platieb](/documentation/bank-payment-matching), ktoré vedia spárovať prichádzajúce bankové platby s vašimi faktúrami.
