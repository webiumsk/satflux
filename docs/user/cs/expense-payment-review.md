---
title: Kontrola úhrad a duplicitních nákladů
category: invoicing
order: 13
updated: 2026-11-05
---

# Kontrola úhrad a duplicitních nákladů

## Přiřazení platby k existujícímu nákladu

V **Platbách** firmy zvolte párování nákladu u nespárovaného odchozího pohybu.
Zkontrolujte navržený náklad a potvrďte přiřazení. Musí se shodovat celá kladná
částka, měna a variabilní symbol nebo jednoznačná reference dokladu. Částečné
úhrady, přeplatky a e-mailové pohyby s neověřeným směrem platby nejsou vhodné.
Pokud odpovídá více nákladů, po kontrole dokladů vyberte správný záznam.

Potvrzení označí existující náklad jako uhrazený s datem bankovního pohybu; nový
náklad nevytvoří. Zastaralé nebo konfliktní přiřazení se odmítne. Před dalším
pokusem obnovte seznam a zkontrolujte aktuální údaje.

Zrušení přiřazení nemění stav úhrady nákladu. Pokud potřebujete opravit také stav
úhrady, zkontrolujte náklad samostatně.

## Možné duplicitní náklady

Při zadávání nebo úpravě nákladu zkontrolujte případné upozornění na duplicitu.
Porovnává číslo dokladu, částku a měnu nebo název, datum přijetí, částku a měnu.
Neověřuje, zda jde o stejného dodavatele.

Otevřete navržený existující záznam a porovnejte jej. Upozornění nic neslučuje ani
nemaže a nebrání uložení samostatného oprávněného nákladu.

V lokální fakturaci se návrhy počítají v zařízení bez odesílání lokálních údajů
o nákladech na server.

Po přiřazení sloupců tabulky v **Importu nákladů** náhled porovná také existující
náklady a opakující se řádky v souboru. Zobrazí nejvýše dvacet upozornění.
Před importem je zkontrolujte; samotná podobnost řádek nepřeskočí ani nemění
dosavadní pravidla konfliktů interních čísel. Selhání kontroly duplicit se zobrazí
samostatně a neznamená, že žádné duplicity neexistují.
