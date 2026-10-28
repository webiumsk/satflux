---
title: Prehľad pripojenia peňaženky
category: wallet-connection
order: 1
meta_description: "Pripojte podporovanú Lightning adresu alebo použite Advanced pre connection strings a NWC. Boltz nastavenie je pozastavené."
updated: 2026-10-28
---

> **Pripojenia na príjem cez Boltz sú pozastavené.** Nové pripojenia cez deskriptor Aqua/Bull Bitcoin a párovanie Aqua cez SamRock sú v Satfluxe pozastavené, pretože naše prijímacie prepojenie cez Boltz nie je dostupné. Bull obnovil Lightning v mobilnej aplikácii, jeho náhradná služba však nie je podporovaným backendom Satfluxu. Aqua momentálne umožňuje odosielanie cez Indra, prijímanie Lightningu nie je dostupné. Návody pre staré pripojenia neumožňujú nové nastavenie.
>
> Použite [podporovanú Lightning adresu](/documentation/connect-with-any-lightning-address) alebo [NWC z vlastného uzla](/documentation/connect-with-nwc). Ak už máte nastavenú Bitcoin on-chain peňaženku, ponechajte ju dostupnú.


# Prehľad pripojenia peňaženky

Skôr než obchod začne prijímať platby, potrebuje peňaženku. V obchode otvorte **Pripojenie peňaženky**. Satflux nikdy nedrží vaše prostriedky - pripájate **vlastnú** peňaženku a všetky platby idú priamo do nej.

**Pripojenie peňaženky** nájdete v obchode a rovnaké nastavenie beží aj pri vytváraní obchodu.

## Dva spôsoby pripojenia

Satflux ponúka dva aktívne taby. Väčšina ľudí použije prvý.

1. **Lightning adresa** (predvolené, najjednoduchšie) - vložte jednu Lightning adresu ako `vy@penazenka.com` a Satflux zvyšok vyrieši sám. Viď [Pripojenie akoukoľvek Lightning adresou](/documentation/connect-with-any-lightning-address).
2. **Advanced (connection strings)** - vložte podporovaný Blink connection string, NWC pairing string alebo Lightning adresu. Deskriptory počas pozastavenia Boltz nie sú dostupné.

Vstavaný **sprievodca peňaženkami** v aplikácii vypisuje každú podporovanú peňaženku s krokovými pokynmi.

## Čo Satflux ukladá

Pri pripojení Satflux uchováva len to, čo je potrebné na nastavenie BTCPay, a každé tajomstvo je **šifrované**. Pri neskoršom zobrazení vidíte **maskovanú** hodnotu a jej odhalenie vyžaduje opätovné overenie. Pri SamRock/Aqua sa v Satfluxe neukladá žiadny privátny descriptor - kľúče ostávajú v Aqua; BTCPay používa len watch-only descriptor. Viď [Čo ukladáme a ako je to chránené](/documentation/what-we-store-and-how-its-protected).

## Po pripojení

Satflux sa pokúsi nastaviť BTCPay automaticky a označí pripojenie ako **connected**. Ak niečo vyžaduje pozornosť, je to označené, aby mohla pomôcť podpora. Keď je peňaženka pripravená, obchod sa odomkne a môžete pridávať pokladne, Pay Buttony a Lightning adresy.

Na neskoršiu zmenu alebo odpojenie viď [Zrušenie alebo zmena údajov peňaženky](/documentation/revoking-or-changing-wallet-credentials).
