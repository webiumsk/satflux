---
title: "Změnu peněženky blokuje sledování faktur"
category: wallet-connection
order: 8
meta_description: "Proč může mít nový obchod automatickou ověřovací fakturu a kdy lze změnu peněženky zkusit znovu."
---

Satflux může při ověřování přijímající peněženky automaticky vytvořit malou
Lightning fakturu, a to ještě před vaší první zákaznickou fakturou.
Není určena k zaplacení a po přečtení ji Satflux archivuje.

**Archivace neukončí sledování ani nezruší Lightning platební požadavek.**
Peněženku nelze nahradit, dokud se faktura sleduje nebo se platba zpracovává.

Nové ověřovací faktury požadují minutu platnosti a další minutu sledování.
Starší mohou mít původní delší lhůtu obchodu, obvykle přibližně den.
Rozhoduje skutečná lhůta v BTCPay a stav platby; odblokování po dvou minutách
není zaručeno pro každý obchod.

V BTCPay otevřete faktury příslušného obchodu a zahrňte i archivované.
Zkontrolujte stav a konec sledování, pokud jej rozhraní zobrazuje. Počkejte na konec
sledování a změnu zkuste znovu. Zpracovávané platby, nevyjasněné změny peněženky
nebo obnova prostředků Cashu mohou blokaci prodloužit. Pokud důvod zůstává nejasný,
kontaktujte podporu. Nemažte záznamy ani neoznačujte faktury jako zaplacené kvůli
obejití blokace.

Podpoře posílejte pouze počty, stavy a lhůty. Platební požadavky, platební odkazy,
identifikátory, přihlašovací údaje a údaje zákazníků nesdílejte.
