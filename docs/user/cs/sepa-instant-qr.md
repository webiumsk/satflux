---
title: SEPA Instant QR (bankovní platby v eurech)
category: payments
order: 5
meta_description: Přijímejte bankovní převody v eurech pomocí SEPA Instant QR kódu vedle Bitcoinu.
---

# SEPA Instant QR (bankovní platby v eurech)

**SEPA Instant QR** umožňuje při platbě přijímat **bankovní převody v eurech**: zákazník naskenuje QR kód v bankovní aplikaci a odešle platbu SEPA Instant. Tuto možnost můžete používat vedle Bitcoinu a Lightningu.

## Nastavení

1. Otevřete obchod a přejděte na **SEPA Instant QR**.
2. Zadejte bankovní údaje účtu, který má přijímat převody v eurech, a metodu zapněte.
3. Volitelně nastavte e-mailové potvrzování příchozích plateb, aby je obchod mohl spárovat.

## Ověřování bankovního e-mailu

Automatické potvrzení vyžaduje ověřený původ z banky a shodu přijímacího účtu, reference platby, částky a měny. Zprávy s chybějícími nebo nejednoznačnými údaji fakturu neoznačí jako zaplacenou. Pokud automatické ověření není dostupné, zkontrolujte převod v bankovní aplikaci a použijte ruční potvrzení.

Provozovatel serveru musí nastavit důvěryhodné ověřování bankovních zpráv. Samotné doručení přes poskytovatele e-mailu neověřuje odesílající banku. Nepodporované formáty zpráv a starší verze pluginu vyžadují ruční kontrolu. Doba potvrzení závisí na doručení bankovního oznámení a zpracování; okamžité potvrzení není zaručeno. FIO používá interval kontroly omezený bankou.

Automatické ověřování bankovního e-mailu aktuálně podporuje klasický kreditní formát Tatra banky. Kompaktní zprávy a oznámení SLSP vyžadují ruční kontrolu.

## Kde je moje platba

Každá čekající platba nebo platba ke kontrole, jejíž reference začíná na `QR-`, má tlačítko **Kde je moje platba**. Dotáže se veřejné diagnostiky Notifikátoru okamžitých plateb (NOP) Finanční správy SR na tuto referenci a zobrazí časovou osu: vytvoření ID transakce, uložení oznámení banky, spárování s pokladnou, zpřístupnění a přijetí.

Co očekávat:

- **NOP toto ID zná** se zobrazí u obchodů, které potvrzují přes NOP s certifikátem eKasa, a u plateb nahlášených bankovním účtem s aktivními notifikacemi (Tatra banka, SLSP).
- **NOP toto ID neviděl** je běžná odpověď u obchodů s ručním potvrzováním nebo potvrzováním přes Fio či e-mail: jejich reference se generují lokálně, takže NOP nemá co zobrazit, i když peníze již dorazily a faktura je potvrzená. Neznamená to, že zákazník nezaplatil - zkontrolujte bankovní účet.
- Časová osa nikdy neříká, na který účet peníze dorazily. Před označením platby jako zaplacené ji ověřte v bankovní aplikaci - satflux z této obrazovky nic nepotvrzuje.

Stejné údaje zobrazuje [kdejemojaplatba.sk](https://www.kdejemojaplatba.sk/), nezávislý prohlížeč stejné služby.

## Poznámky

- Dostupné pro všechny účty včetně hostů.
- Platby přicházejí na **váš bankovní účet** - jde o platební kanál v eurech, který ovládáte vy, oddělený od Bitcoinu.
- Doplňuje [fakturaci](/documentation/getting-started-with-invoicing) a [párování bankovních plateb](/documentation/bank-payment-matching), které mohou příchozí převody spárovat s fakturami.
