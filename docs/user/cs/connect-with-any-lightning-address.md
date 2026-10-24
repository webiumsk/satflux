---
title: Připojení jakoukoli Lightning adresou
category: wallet-connection
order: 2
---

# Připojení jakoukoli Lightning adresou

Otevřete **Připojení peněženky**, vyberte **Lightning adresa** a zadejte `vy@penezenka.com`. Satflux ověří **konkrétní adresu**, včetně Blink, Blitz, Flash a Coinos. Značka ani společná doména nezaručují LUD-21.

## Přímé Lightning připojení

Pokud adresa podporuje **LUD-21**, připojí se přes LNAddressConnect. Starší Coinos adresa a adresa přesunutá do Coinos v3 mohou mít odlišné možnosti i na doméně `coinos.io`.

## Bez LUD-21 - CashuMelt (beta)

Vrátí-li adresa platnou Lightning fakturu bez endpointu pro ověření úhrady, Satflux zkontroluje dostupnost CashuMelt. Zobrazí mint a před uložením vyžádá výslovný souhlas. Zachová existující mint; jinak nabídne výchozí mint nastavený provozovatelem.

Vypořádání závisí na důvěře v mint a na poplatcích. Ověřuje Lightning výplatu poskytovateli adresy, nikoli jeho další interní převod, například na Ark zůstatek. Viz [Cashu Lightning vypořádání](/documentation/cashu-lightning-settlement).

## Pokud adresu nelze ověřit

Časový limit, neplatná odpověď, chybějící plugin nebo nedokončená platba zobrazí možnost opakování či důvod nedostupnosti. Satflux peněženku potichu nepřepne. Změna existující peněženky stále vyžaduje potvrzení a bezpečnostní kontroly; existující faktury zachovají původní platební nastavení.

Další možnosti: [Výběr peněženky](/documentation/choosing-your-wallet). Adresa pro příjem hostovaná obchodem je samostatná funkce: [Lightning adresy obchodu](/documentation/store-lightning-addresses).
