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
3. Voliteľne nastavte e-mailové potvrdzovanie prichádzajúcich platieb, aby ich obchod vedel spárovať.

## Kde je moja platba

Každá čakajúca platba alebo platba na kontrolu, ktorej referencia začína na `QR-`, má tlačidlo **Kde je moja platba**. Opýta sa verejnej diagnostiky Notifikátora okamžitých platieb (NOP) Finančnej správy SR, čo o tejto referencii vie, a ukáže časovú os: vznik ID transakcie, uloženie oznámenia banky, spárovanie s pokladnicou, sprístupnenie, prijatie.

Čo očakávať:

- **NOP toto ID pozná** sa zobrazí pri obchodoch, ktoré potvrdzujú cez NOP s eKasa certifikátom, a pri platbách, ktoré nahlásil notifikačný bankový účet (Tatra banka, SLSP).
- **NOP toto ID nevidel** je bežná odpoveď pri obchodoch s manuálnym, Fio alebo e-mailovým potvrdzovaním: ich referencie sa generujú lokálne, takže NOP nemá čo ukázať, kým banka platbu nenahlási. Znamená to aj, že zákazník ešte nezaplatil.
- Časová os nikdy nehovorí, na ktorý účet peniaze prišli. Pred označením platby ako zaplatenej ju vždy skontrolujte v bankovej aplikácii - satflux z tejto obrazovky nič nepotvrdzuje.

Rovnaké údaje ukazuje aj [kdejemojaplatba.sk](https://www.kdejemojaplatba.sk/), nezávislý prehliadač tej istej služby.

## Poznámky

- Dostupné pre všetky účty vrátane hostí.
- Platby prídu na **váš bankový účet** - je to eurový platobný kanál, ktorú ovládate vy, oddelená od Bitcoinu.
- Dobre sa dopĺňa s [fakturáciou](/documentation/getting-started-with-invoicing) a [párovaním bankových platieb](/documentation/bank-payment-matching), ktoré vedia spárovať prichádzajúce bankové platby s vašimi faktúrami.
