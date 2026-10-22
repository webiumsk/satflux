---
title: Vstupenky a pořadí typů
category: payments
order: 20
updated: 2026-10-22
---

# Vstupenky a pořadí typů

Události vytváříte a upravujete ve stávající aplikaci Tickets. Veřejná nabídka používá název, formátovaný popis, fyzický nebo virtuální typ, místo, data, obrázek, měnu a nastavení kapacity.

## Řazení typů vstupenek

Rozbalte událost a otevřete **Typy vstupenek**. Zvolte úpravu pořadí, přetáhněte typ nebo použijte šipky nahoru/dolů a pořadí uložte. Zrušením zahodíte rozpracované pořadí. Řadíte i neaktivní typy; veřejná nabídka je vynechá a zachová vzájemné pořadí ostatních. Nové typy přibývají na konec. Ceny a vydané vstupenky se nemění.

Řazení vyžaduje kompatibilní plugin **Satflux Tickets 2.1.0**. Pokud událost mezitím změnil jiný správce, před dalším uložením načtěte aktuální pořadí.

## Veřejný prodej

Sdílejte stávající veřejný odkaz na událost na svém BTCPay serveru. Návštěvník vybere vstupenky a vyplní údaje účastníků na jedné stránce, poté zaplatí v BTCPay. Od verze Satflux Tickets 2.1.1 stránka používá jazyk prohlížeče a nabízí výběr jazyka (EN/SK/ES/DE/CS). Od verze 2.2.0 používají fyzické akce pásmo místa konání a virtuální akce pásmo návštěvníka; viz část o časovém pásmu. Vstupenky pro stejný e-mail přijdou společně; různí příjemci dostanou své vstupenky samostatně. Pokyny pro připojení k virtuální události zachovejte v nastavení události a e-mailů.

Od verze Satflux Tickets 2.1.2 zvolte jazyk v záhlaví stránky; texty se změní ihned. Vybrané vstupenky a rozpracované údaje příjemců zůstanou zachovány.

## Časové pásmo akce

Od Satflux Tickets 2.2.0 zobrazují fyzické akce čas místa konání a jeho posun vůči UTC bez ohledu na polohu návštěvníka. Virtuální akce používají pásmo prohlížeče návštěvníka; náhradní UTC je jasně označeno. V existujícím formuláři zvolte IANA pásmo místa konání, například Europe/Bratislava, a zadejte místní čas akce. Schválená historická migrace přiřadí existujícím fyzickým akcím Europe/Bratislava bez změny uložených UTC okamžiků. Samotná změna pásma termín neposouvá. Neexistující či nejednoznačnou hodinu při změně času je třeba před uložením opravit. Starší plugin aktualizujte; při chybě zůstane rozpracovaný formulář zachován.

U nové fyzické akce Satflux předvyplní uložené pásmo obchodu, pokud je podporované. Ve stejném formuláři můžete vybrat jiné pásmo místa konání. Volba se uloží k akci; pozdější změna pásma obchodu stávající akce nemění. Přímo v BTCPay vyberte pásmo místa konání ručně.
