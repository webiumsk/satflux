---
title: Odpočtové položky, odběratel a úhrady faktury
category: invoicing
order: 5
updated: 2026-11-16
---

# Odpočtové položky, odběratel a úhrady faktury

Detail faktury zobrazuje odpočty, identitu odběratele i evidovaný způsob úhrady.

## Odpočtové položky

Odpočtovou položku na slovenské nebo české faktuře zadejte jako samostatný ruční řádek se záporným množstvím a kladnou jednotkovou cenou, například `-1 x 15,00`. Celková částka faktury musí zůstat kladná. Použijte příslušnou sazbu DPH; odečte se položka i její DPH. Odpočtová položka nesmí odkazovat na skladovou položku ani sklad. Existující skladový řádek před použitím jako odpočet odpojte od skladu.

Odpočty se záporným množstvím a kladnou cenou podporují PDF, e-mail, export UBL/ISDOC a přípravu eFaktúry při zachování stávajících podmínek odesílání. Záporná jednotková cena zůstává u strukturovaných exportů nepodporovaná. Německé hybridní faktury a externí poskytovatelé výpočtu americké daně zůstávají mimo tento postup.

Dobropis vytvořený z takové faktury zachová odpočtovou položku bez změny původní faktury. Kombinace těchto odpočtů se samostatnými procentními slevami zůstává ve strukturovaných exportech nepodporovaná.

Ručně zadané procentní sazby podporují nejvýše čtyři desetinná místa, například `8,875 %`. Uložené sazby položek, souhrny DPH, označení v PDF i strukturované exporty zachovávají tuto přesnost.

## Identifikační údaje odběratele

Pokud kontakt obsahuje IČO, DIČ nebo identifikační číslo pro DPH, zobrazí se v náhledu faktury i v PDF. Vystavená faktura zachová uloženou identitu odběratele. Pozdější úprava kontaktu vystavenou fakturu nezmění.

WooCommerce přenáší podporované identifikační údaje z objednávky. Prázdná pole objednávky zachovají údaje již uložené v kontaktu. Jiné rozšíření pokladny může potřebovat doplnění mapování polí.

PDF automaticky odeslané před importem v prohlížeči může obsahovat pouze údaje odběratele dostupné serveru z objednávky. Údaje uložené pouze v kontaktu v prohlížeči nelze zpětně doplnit do již odeslaného dokumentu.

## Evidovaný způsob úhrady

Nabízené možnosti platby se liší od evidovaného způsobu úhrady. Uhrazené objednávky WooCommerce zachovají klasifikaci dodané platební brány, spárované bankovní platby zobrazí bankovní původ a ověřené úhrady přes BTCPay zobrazí BTCPay. Ruční úhrady mají označení ruční úhrady. Starší faktury bez uloženého důkazu zobrazí neurčený způsob; historie se neodhaduje ani nepřepisuje.

## Platební odkazy pro Bitcoin

Vystavení faktury, vytvoření PDF ani odeslání e-mailem nevytvoří BTCPay fakturu. PDF a e-mail obsahují platební odkaz na Satflux, pokud je zapnutá platba Bitcoinem. QR kód otevře platební stránku v prohlížeči. BTCPay platba se vytvoří nebo znovu použije až po potvrzení platby Bitcoin / Lightning zákazníkem. Platbu může vytvořit i vystavovatel samostatnou akcí v detailu faktury.

Zaznamenaná bankovní nebo ruční úhrada zablokuje další platbu přes odkaz Satfluxu po doručení stavu na server. Částečná úhrada a změna platebních podmínek faktury zneplatní původní nabídku platby. V local-first režimu se změny provedené offline doručí až po opětovném připojení prohlížeče; při potvrzení bankovní úhrady počítejte s tímto zpožděním.

Starší přímé odkazy na BTCPay zachovají původní chování. Bankovní úhrada se nestává bitcoinovou úhradou a automaticky nezruší již vytvořenou platbu v BTCPay.
