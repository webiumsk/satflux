---
title: Pripojenie cez NWC (Nostr Wallet Connect)
category: wallet-connection
order: 6
meta_description: Pripojte vlastný Lightning uzol cez Nostr Wallet Connect pairing string, napr. z Alby Hub.
updated: 2026-10-28
---

> **Pripojenia na príjem cez Boltz sú pozastavené.** Nové pripojenia cez deskriptor Aqua/Bull Bitcoin a párovanie Aqua cez SamRock sú v Satfluxe pozastavené, pretože naše prijímacie prepojenie cez Boltz nie je dostupné. Bull obnovil Lightning v mobilnej aplikácii, jeho náhradná služba však nie je podporovaným backendom Satfluxu. Aqua momentálne umožňuje odosielanie cez Indra, prijímanie Lightningu nie je dostupné. Návody pre staré pripojenia neumožňujú nové nastavenie.
>
> Použite [podporovanú Lightning adresu](/documentation/connect-with-any-lightning-address) alebo [NWC z vlastného uzla](/documentation/connect-with-nwc). Ak už máte nastavenú Bitcoin on-chain peňaženku, ponechajte ju dostupnú.


# Pripojenie cez NWC (Nostr Wallet Connect)

**NWC** (Nostr Wallet Connect) umožňuje pripojiť vlastnú Lightning peňaženku alebo uzol cez pairing string - napríklad zo samohostovaného **Alby Hub**. Je určené pre pokročilých, samohostujúcich používateľov.

## Čo potrebujete

- Peňaženku, ktorá vydáva NWC pripojenia (napr. vlastný **Alby Hub**).
- NWC pairing string, ktorý vyzerá takto:

```text
nostr+walletconnect://…?relay=…&secret=…
```

## Kroky

1. Vo vašej NWC peňaženke (napr. Alby Hub → Connections → Add Connection) vytvorte pripojenie a skopírujte reťazec `nostr+walletconnect://…`.
2. V Satfluxe otvorte **Pripojenie peňaženky → Advanced** a vložte reťazec. Satflux ho rozpozná ako NWC.
3. Uložte.

## Poznámky

- Server musí mať k dispozícii **BTCPay Nostr plugin**, aby NWC pripojenia fungovali.
- NWC pairing stringy z **Cashu ecash peňaženiek** (napr. Minibits) sa tu neprijímajú. Na použitie takej peňaženky nastavte [Cashu → Lightning](/documentation/cashu-lightning-settlement) - zadajte jej mint URL a vašu Lightning adresu.
