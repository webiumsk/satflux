---
title: Pay Button
category: payments
order: 2
meta_description: Pridajte na web Bitcoin „pay“ tlačidlo pomocou krátkeho HTML úryvku.
updated: 2026-10-30
---

# Pay Button

**Pay Button** je malý úryvok, ktorý vložíte na web, aby vám návštevníci mohli zaplatiť v Bitcoine alebo cez Lightning - hodí sa na dary, produkt s pevnou cenou alebo tlačidlo „kúp mi kávu“.

## Vytvorenie Pay Buttonu

1. V obchode otvorte aplikáciu **Pay Button**.
2. Nastavte sumu (pevnú alebo zvolenú zákazníkom) a menu.
3. Skopírujte vygenerovaný HTML úryvok.

## Pridanie na web

Vložte úryvok do HTML stránky tam, kde má tlačidlo byť. Keď naň návštevník klikne, zobrazí sa platobná obrazovka a môže zaplatiť on-chain alebo cez Lightning.

## Poznámky

- Úryvok je samostatný - na vašom webe netreba účet ani plugin.
- Pre plnohodnotný obchod je namiesto jednotlivých tlačidiel vhodnejšia WooCommerce integrácia.

## Email kupujúceho a návratová adresa

Voliteľný **Email kupujúceho** identifikuje zákazníka na faktúre. Nezapína emailové oznámenia obchodníkovi. Tie nastavíte v emailových pravidlách obchodu.

Presmerovanie prehliadača musí byť úplná HTTP alebo HTTPS adresa, napríklad `https://example.com/thanks?order=123`. Relatívne cesty a iné schémy URL sa odmietnu. Pred kopírovaním vygenerovaného kódu opravte neplatnú adresu.
