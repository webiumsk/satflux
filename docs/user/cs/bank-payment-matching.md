---
title: Párování bankovních plateb
category: invoicing
order: 5
meta_description: Importujte bankovní výpisy a automaticky spárujte platby s fakturami.
---

# Párování bankovních plateb

Párování bankovních plateb porovnává příchozí platby s vystavenými doklady, abyste viděli, co je zaplaceno.

## Import transakcí

Ve firmě otevřete **Platby** a importujte bankovní výpis. Satflux podporuje běžné formáty:

- **CSV** exporty z banky
- **CAMT** (ISO 20022) soubory
- **Wise** výpisy
- Notifikační e-maily některých bank

Duplicitní transakce se rozpoznají a přeskočí, takže můžete znovu importovat překrývající se výpis.

## Bankovní notifikační e-maily

Použijte adresu zobrazenou v **Platby > Import** pro vybranou firmu. Při lokální fakturaci otevření nebo obnovení Plateb přenese e-maily, které už zpracoval serverový můstek téže firmy, do šifrovaných dat v zařízení. Chybějící historii notifikací z banky tím zpětně nezíská.

## Automatické párování

Satflux páruje příchozí transakce s fakturami podle **variabilního symbolu**, částky a dalších referencí. Spárované faktury označí jako zaplacené; nejednoznačné případy ponechá k potvrzení nebo ručnímu spárování.

Nový odchozí pohyb z bankovního e-mailu může označit existující náklad jako uhrazený, pokud se v téže firmě najde právě jeden neuhrazený náklad se shodnou celou kladnou částkou, měnou a variabilním symbolem. Pokud náklad nemá VS, použijí se číslice z čísla dokladu. Bez bankovního VS se reference platby musí rovnat celému číslu dokladu. Datem úhrady bude datum bankovního pohybu. Nejednoznačné, částečné a přeplacené úhrady zůstanou nespárované. Tato automatika pro náklady platí pro bankovní e-maily; CSV a Wise si ponechávají dosavadní chování. Historické duplicitní řádky se při obnovení znovu nepárují; k jejich výslovné kontrole použijte automatické párování příslušné importní dávky.

Nové e-mailové importy bez uloženého původního směru platby nemohou automaticky uhradit náklad ani při opakovaném párování dávky. Starší uložené pohyby si zachovávají dosavadní výslovné párování dávky.

Pokud stále probíhá jiné párování, před dalším pokusem počkejte na jeho dokončení. Pokud se výsledek nepodařilo potvrdit, před dalším pokusem znovu načtěte kartu.

## Náklady

Odchozí platby můžete převést na **náklady** pro úplný export účetnímu.
