---
title: Výber peňaženky
category: getting-started
order: 4
meta_description: "Vyberte podporovanú Lightning adresu, Cashu alebo NWC prijímacie prepojenie; Boltz pripojenia sú pozastavené."
updated: 2026-10-28
---

> **Pripojenia na príjem cez Boltz sú pozastavené.** Nové pripojenia cez deskriptor Aqua/Bull Bitcoin a párovanie Aqua cez SamRock sú v Satfluxe pozastavené, pretože naše prijímacie prepojenie cez Boltz nie je dostupné. Bull obnovil Lightning v mobilnej aplikácii, jeho náhradná služba však nie je podporovaným backendom Satfluxu. Aqua momentálne umožňuje odosielanie cez Indra, prijímanie Lightningu nie je dostupné. Návody pre staré pripojenia neumožňujú nové nastavenie.
>
> Použite [podporovanú Lightning adresu](/documentation/connect-with-any-lightning-address) alebo [NWC z vlastného uzla](/documentation/connect-with-nwc). Ak už máte nastavenú Bitcoin on-chain peňaženku, ponechajte ju dostupnú.


# Výber peňaženky

Satflux pripája k obchodu **vašu vlastnú** peňaženku - nikdy nedrží vaše prostriedky. Máte viacero možností; tu je návod, ako si vybrať.

## Rýchle možnosti

| Možnosť | Kustódia | Ako pripojíte | Vhodné pre |
|---|---|---|---|
| **Blink adresa** (`vy@blink.sv`) | Nekustodiálne, len príjem | Vložíte adresu | Najrýchlejší štart |
| **Akákoľvek Lightning adresa** | Podľa peňaženky | Vložíte `vy@penazenka.com` | Blitz, Flash, Coinos alebo akúkoľvek LUD-21 peňaženku |
| **Cashu** (beta) | Lightning (vyrovnané cez mint) | Mint URL + Lightning adresa | Vyrovnanie Lightningu cez mint |
| **NWC** (napr. Alby Hub) | Vlastný uzol/hub | Vložíte pairing string | Pokročilí samohostitelia |

## Ako sa rozhodnúť

- **Chcete rýchly štart?** Vložte svoju **Blink** `@blink.sv` adresu alebo [akúkoľvek Lightning adresu](/documentation/connect-with-any-lightning-address) z peňaženky, ktorú už používate.
- **Chcete vyrovnávať Lightning cez Cashu mint?** Viď [Cashu Lightning vyrovnanie](/documentation/cashu-lightning-settlement) - je to beta.
- **Máte vlastný Alby Hub?** Použite [NWC](/documentation/connect-with-nwc).

Peňaženku môžete neskôr zmeniť - viď [Zrušenie alebo zmena údajov peňaženky](/documentation/revoking-or-changing-wallet-credentials). Nech si zvolíte čokoľvek, Satflux nikdy nepreberá kustódiu vašej peňaženky - viď [Nemôžeme pristupovať k vaším prostriedkom ani ich presúvať](/documentation/we-cannot-access-or-move-your-funds). (Cashu nie je zákaznícka platobná metóda - je to prevodník na vyrovnanie, ktorý smeruje Lightning cez mint na vašu Lightning adresu.)
