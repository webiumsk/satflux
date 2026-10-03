---
title: Firmy a branding
category: invoicing
order: 2
meta_description: Nastavte údaje firmy, logo, číslovanie a bankový účet.
---

# Firmy a branding

**Firma** obsahuje údaje o podniku, ktoré sa zobrazujú na vašich dokladoch. Firiem môžete mať viac (podľa plánu) - napríklad živnostníka a s.r.o.

## Profil firmy

Otvorte **profil** firmy a vyplňte:

- **Obchodné meno a názov**
- **Adresa** (ulica, mesto, PSČ, krajina)
- **Daňové identifikátory** - IČO, DIČ a IČ DPH podľa vašej krajiny
- **Bankový účet** (IBAN/BIC) pre platobné pokyny na faktúrach

Jurisdikcia (krajina) určuje pravidlá špecifické pre krajinu, ako spracovanie DPH a na Slovensku e-fakturáciu.

## Branding

Na tabe **Logo a podpis** v profile nahráte **logo** a voliteľne obrázok **podpisu/pečiatky**. Zobrazia sa na vygenerovaných PDF.

## Číslovanie

V **Nastavenia aplikácie → Číslovanie** definujete číselné rady pre každý typ dokladu (faktúry, proformy, dobropisy a pod.), vrátane formátu a spôsobu resetovania (ročne, mesačne, nikdy). Satflux pri vystavení dokladu priradí ďalšie číslo za vás.

- **Formát:** `YYYY` je rok, `MM` mesiac, rad `N` (aspoň dve) je poradové číslo, všetko ostatné je pevný text - napr. `INVYYYYNNNN` dá `INV20260001`.
- **Resetovanie:** ročný rad začína znova od 1 prvým dokladom nového roka, mesačný každým novým mesiacom. Rok a mesiac sa riadia kalendárnym dátumom vášho zariadenia v momente vystavenia.
- **Nasledujúce číslo (posledné použité):** počítadlo, od ktorého pokračuje ďalší doklad v aktuálnom období. Použite ho pri prechode z iného systému počas roka - napr. zadajte `120` a ďalšia faktúra dostane `…0121`. Satflux nikdy nepôjde pod číslo, ktoré je už použité.
- **Vystavenie vyžaduje pripojenie:** číslo sa rezervuje na serveri, takže dve zariadenia (ani kolegovia so zdieľanou firmou) nikdy nedostanú rovnaké číslo. Offline zostane doklad konceptom.

### Mazanie očíslovaných dokladov

Zmazať sa dá len **posledný očíslovaný doklad** radu (vystavený, uhradený aj stornovaný) a jeho číslo potom dostane ďalší doklad - rad zostane bez medzier. Ak chcete ísť ďalej dozadu, zmažte najprv novšie doklady. Starší doklad sa dá len **stornovať**. Doklady so spárovanou bankovou platbou alebo nadväzným dokladom (napr. dobropisom) sa zmazať nedajú. Koncepty sa dajú zmazať vždy.

## Ďalší krok

Pridajte zákazníkov v [Kontaktoch](/documentation/contacts).
