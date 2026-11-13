---
title: Odpočtové položky, odberateľ a úhrady faktúry
category: invoicing
order: 5
updated: 2026-11-13
---

# Odpočtové položky, odberateľ a úhrady faktúry

Detail faktúry zobrazuje odpočty, identitu odberateľa aj evidovaný spôsob úhrady.

## Odpočtové položky

Odpočtovú položku na slovenskej alebo českej faktúre zadajte ako samostatný ručný riadok so záporným množstvom a kladnou jednotkovou cenou, napríklad `-1 x 15,00`. Celková suma faktúry musí zostať kladná. Použite príslušnú sadzbu DPH; odpočíta sa položka aj jej DPH. Odpočtová položka nesmie odkazovať na skladovú položku ani sklad. Existujúci skladový riadok pred použitím ako odpočet odpojte od skladu.

Odpočty so záporným množstvom a kladnou cenou podporujú PDF, e-mail, export UBL/ISDOC a prípravu eFaktúry pri zachovaní existujúcich podmienok odosielania. Záporná jednotková cena zostáva pri štruktúrovaných exportoch nepodporovaná. Nemecké hybridné faktúry a externí poskytovatelia výpočtu americkej dane zostávajú mimo tohto postupu.

Dobropis vytvorený z takejto faktúry zachová odpočtovú položku bez zmeny pôvodnej faktúry. Kombinácia týchto odpočtov so samostatnými percentuálnymi zľavami zostáva v štruktúrovaných exportoch nepodporovaná.

## Identifikačné údaje odberateľa

Ak kontakt obsahuje IČO, DIČ alebo IČ DPH, zobrazia sa v náhľade faktúry aj v PDF. Vystavená faktúra zachová uloženú identitu odberateľa. Neskoršia úprava kontaktu vystavenú faktúru nezmení.

WooCommerce prenáša podporované identifikačné údaje z objednávky. Prázdne polia objednávky zachovajú údaje už uložené v kontakte. Iné rozšírenie pokladne môže potrebovať doplnenie mapovania polí.

PDF automaticky odoslané pred importom v prehliadači môže obsahovať iba údaje odberateľa dostupné serveru z objednávky. Údaje uložené iba v kontakte v prehliadači nemožno spätne doplniť do už odoslaného dokumentu.

## Evidovaný spôsob úhrady

Ponúkané možnosti platby sa líšia od evidovaného spôsobu úhrady. Uhradené objednávky WooCommerce zachovajú klasifikáciu dodanej platobnej brány, spárované bankové platby zobrazia bankový pôvod a overené úhrady cez BTCPay zobrazia BTCPay. Ručné úhrady majú označenie ručnej úhrady. Staršie faktúry bez uloženého dôkazu zobrazia neurčený spôsob; história sa neodhaduje ani neprepisuje.
