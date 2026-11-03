---
title: Párovanie bankových platieb
category: invoicing
order: 5
meta_description: Importujte bankové výpisy a automaticky spárujte platby s faktúrami.
---

# Párovanie bankových platieb

Párovanie bankových platieb zosúlaďuje prichádzajúce platby s vašimi vystavenými dokladmi, takže na prvý pohľad vidíte, čo je zaplatené.

## Import transakcií

Vo firme otvorte **Platby** a importujte bankový výpis. Satflux rozumie bežným formátom:

- **CSV** exporty z banky
- **CAMT** (ISO 20022) súbory
- **Wise** výpisy
- Notifikačné e-maily z niektorých bánk (napríklad slovenských)

Duplicitné transakcie sú rozpoznané a preskočené, takže opätovný import prekrývajúceho sa výpisu je bezpečný.

## Bankové notifikačné e-maily

Použite adresu zobrazenú v **Platby > Import** pre vybranú firmu. Pri lokálnej fakturácii otvorenie alebo obnovenie Platieb prenesie e-maily, ktoré už spracoval serverový mostík tej istej firmy, do šifrovaných dát v zariadení. Chýbajúcu históriu notifikácií z banky tým spätne nezíska.

## Automatické párovanie

Ako transakcie prichádzajú, Satflux ich páruje s faktúrami podľa **variabilného symbolu**, sumy a ďalších referencií. Spárované faktúry sú označené ako zaplatené; nejednoznačné nechá na vaše potvrdenie alebo ručné spárovanie.

Nový odchádzajúci pohyb z bankového e-mailu môže označiť existujúci náklad ako uhradený, ak sa v tej istej firme nájde práve jeden neuhradený náklad so zhodnou celou kladnou sumou, menou a variabilným symbolom. Ak náklad nemá VS, použijú sa číslice z čísla dokladu. Bez bankového VS sa referencia platby musí rovnať celému číslu dokladu. Ako dátum úhrady sa použije dátum bankového pohybu. Nejednoznačné, čiastočné a preplatené úhrady zostanú nespárované. Táto automatika pre náklady platí pre bankové e-maily; CSV a Wise si ponechávajú doterajšie správanie. Historické duplicitné riadky sa pri obnovení znovu nepárujú; na ich výslovnú kontrolu použite automatické párovanie príslušnej importnej dávky.

Nové e-mailové importy bez uloženého pôvodného smeru platby nemôžu automaticky uhradiť náklad ani pri opakovanom párovaní dávky. Staršie uložené pohyby si zachovávajú doterajšie výslovné párovanie dávky.

Ak stále prebieha iné párovanie, pred ďalším pokusom počkajte na jeho dokončenie. Ak sa výsledok nepodarilo potvrdiť, pred ďalším pokusom znovu načítajte kartu.

## Náklady

Odchádzajúce platby viete premeniť na **náklady**, aby vaše účtovníctvo bolo kompletné pre export účtovníkovi.
