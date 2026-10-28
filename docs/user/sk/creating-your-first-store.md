---
title: "Vytvorenie prvého obchodu"
category: getting-started
order: 3
meta_description: "Vytvorte obchod, vyberte podporovanú prijímaciu peňaženku a dokončite nastavenie. Boltz deskriptory a SamRock sú pozastavené."
updated: 2026-10-28
---

> **Pripojenia na príjem cez Boltz sú pozastavené.** Nové pripojenia cez deskriptor Aqua/Bull Bitcoin a párovanie Aqua cez SamRock sú v Satfluxe pozastavené, pretože naše prijímacie prepojenie cez Boltz nie je dostupné. Bull obnovil Lightning v mobilnej aplikácii, jeho náhradná služba však nie je podporovaným backendom Satfluxu. Aqua momentálne umožňuje odosielanie cez Indra, prijímanie Lightningu nie je dostupné. Návody pre staré pripojenia neumožňujú nové nastavenie.
>
> Použite [podporovanú Lightning adresu](/documentation/connect-with-any-lightning-address) alebo [NWC z vlastného uzla](/documentation/connect-with-nwc). Ak už máte nastavenú Bitcoin on-chain peňaženku, ponechajte ju dostupnú.


Nový obchod v Satfluxe vytvoríte v troch krokoch: základné údaje, typ peňaženky a potvrdenie. Obchod sa potom vytvorí v BTCPay Serveri a zobrazí sa krátky checklist, aby ste mohli dokončiť nastavenie peňaženky a otestovať platbu.

**Krok 1 - Základné údaje**

- **Názov obchodu** - Názov vášho obchodu (napr. „Hlavný obchod“, „E-shop“). Guest účtom sa prvý obchod vytvorí automaticky ako `My Store - XXXXXXXX` (jedinečná prípona); kedykoľvek ho premenujete v **Nastaveniach obchodu**.

- **Predvolená mena** - Mena pre ceny a sumy (napr. EUR, USD, BTC, SATS).

- **Časové pásmo** - Používa sa pre faktúry a reporty.

- **Predvolený zdroj ceny** - Burza alebo zdroj pre prevod fiatu na BTC (napr. pre zobrazenie súm). Podľa predvolenej meny sa zvolí vhodná predvolená hodnota; môžete ju zmeniť tu alebo neskôr v nastaveniach obchodu.

Keď sú všetky polia vyplnené, kliknite na **Ďalší krok**.

**Krok 2 - Výber typu peňaženky**

Zvoľte, ako bude obchod prijímať Lightning (a on-chain) platby:

- **Blink** - Rýchle nastavenie, vhodné na rýchly štart. Peňaženku Blink pripojíte v tomto kroku alebo neskôr (napr. cez connection string). Podporované možnosti nájdete v článku Výber peňaženky.

- **Ďalšie podporované pripojenia** - Použite Lightning adresu alebo NWC pripojenie z vlastného uzla. Deskriptory Aqua/Bull a SamRock sú pozastavené.

Podporované prijímacie prepojenie môžete nastaviť v tomto kroku alebo neskôr v **Pripojení peňaženky**. Deskriptory a SamRock počas pozastavenia Boltz nie sú dostupné.

Kliknutím na **Späť** zmeníte základné údaje, alebo na **Ďalší krok** pokračujete.

**Krok 3 - Potvrdenie**

Skontrolujte názov obchodu, menu, časové pásmo, zdroj ceny a typ peňaženky. Kliknutím na **Vytvoriť obchod** sa obchod vytvorí v BTCPay. Potom budete presmerovaní na stránku „Ďalšie kroky“ s odkazom na **checklist nastavenia peňaženky** (napr. pripojiť peňaženku, zapnúť Lightning, otestovať faktúru).

**Po vytvorení**

- Ak ste v kroku 2 nevyplnili údaje peňaženky, dokončite nastavenie a Lightning v **Pripojenie peňaženky**. Postup je v článku Pripojenie peňaženky.

- Z dashboardu obchodu vytvorte **Point of Sale (PoS)** aplikáciu a podľa potreby **Lightning adresu**.
