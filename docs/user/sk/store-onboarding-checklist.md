---
title: "Checklist nastavenia obchodu"
category: getting-started
order: 5
meta_description: "Dokončite podporované pripojenie peňaženky, vytvorte pokladňu a overte prijatie platby."
updated: 2026-10-28
---

> **Pripojenia na príjem cez Boltz sú pozastavené.** Nové pripojenia cez deskriptor Aqua/Bull Bitcoin a párovanie Aqua cez SamRock sú v Satfluxe pozastavené, pretože naše prijímacie prepojenie cez Boltz nie je dostupné. Bull obnovil Lightning v mobilnej aplikácii, jeho náhradná služba však nie je podporovaným backendom Satfluxu. Aqua momentálne umožňuje odosielanie cez Indra, prijímanie Lightningu nie je dostupné. Návody pre staré pripojenia neumožňujú nové nastavenie.
>
> Použite [podporovanú Lightning adresu](/documentation/connect-with-any-lightning-address) alebo [NWC z vlastného uzla](/documentation/connect-with-nwc). Ak už máte nastavenú Bitcoin on-chain peňaženku, ponechajte ju dostupnú.


Po vytvorení obchodu vám **checklist nastavenia peňaženky** (wallet onboarding) pomôže dokončiť pripojenie peňaženky a overiť, že obchod prijíma platby. Kroky sú **rovnaké** pri podporovaných pripojeniach peňaženky: zadáte údaje peňaženky, vytvoríte Point of Sale (PoS) aplikáciu a otestujete platbu. Kroky si prejdete a označíte ako dokončené sami; checklist je návod, nie automatická kontrola.

---

**Čo checklist obsahuje**

Kroky sú:

1. **Zadať údaje peňaženky** - V **Pripojení peňaženky** (obchod → LN Wallet Connection) pridajte podporovanú Lightning adresu alebo connection string. Všetka konfigurácia sa robí v paneli Satflux; nemusíte sami povoľovať pluginy ani nastavovať BTCPay.
2. **Vytvoriť Point of Sale (PoS)** - Vytvorte PoS aplikáciu pre obchod. Môžete ju použiť na vytváranie faktúr a prijímanie platieb na mieste alebo online.
3. **Otestovať platbu** - Vytvorte testovaciu faktúru (napr. z PoS), zaplaťte ju **z inej peňaženky** než tej, ktorá prijíma (napr. z iného telefónu alebo aplikácie), a overte, že prostriedky dorazia do vašej prijímajúcej peňaženky.

Pri testovaní musíte platiť **z inej** peňaženky než z tej pripojenej k obchodu; inak netestujete skutočný tok. Keď platba dorazí do vašej peňaženky, krok môžete označiť za dokončený.

---

**Kde to nájdete v aplikácii**

- **Po vytvorení obchodu** - Ste presmerovaní na stránku **Ďalšie kroky**. Použite odkaz **„View Onboarding Checklist“** na otvorenie checklistu pre daný obchod.

- **Z obchodu** - Otvorte obchod a prejdite na stránku **Wallet onboarding** / **Checklist**. URL: /stores/{id-obchodu}/checklist.

Stránka checklistu má nadpis **„Wallet onboarding“**. Každý krok môžete označiť za dokončený; stav sa ukladá. Všetky kroky sú potrebné na úplné nastavenie.

Ako pridať údaje peňaženky: Prehľad Pripojenia peňaženky. Podporované možnosti: Výber peňaženky.
