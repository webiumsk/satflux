---
title: "Čo je Satflux"
category: getting-started
order: 1
meta_description: "Prijímajte Bitcoin a Lightning vlastnou peňaženkou. Nekustodiálny ovládací panel BTCPay s multi-store, PoS a Lightning (Lightning adresa / NWC)"
updated: 2026-10-28
---

> **Pripojenia na príjem cez Boltz sú pozastavené.** Nové pripojenia cez deskriptor Aqua/Bull Bitcoin a párovanie Aqua cez SamRock sú v Satfluxe pozastavené, pretože naše prijímacie prepojenie cez Boltz nie je dostupné. Bull obnovil Lightning v mobilnej aplikácii, jeho náhradná služba však nie je podporovaným backendom Satfluxu. Aqua momentálne umožňuje odosielanie cez Indra, prijímanie Lightningu nie je dostupné. Návody pre staré pripojenia neumožňujú nové nastavenie.
>
> Použite [podporovanú Lightning adresu](/documentation/connect-with-any-lightning-address) alebo [NWC z vlastného uzla](/documentation/connect-with-nwc). Ak už máte nastavenú Bitcoin on-chain peňaženku, ponechajte ju dostupnú.


> Keď je aktívny [dočasný PRO prístup zdarma](/documentation/free-pro-access), registrované nehosťovské účty s overeným e-mailom môžu používať PRO funkcie bez nákupu predplatného. Bežné limity a vlastnícke oprávnenia naďalej platia. Aktuálny režim nájdete v Cenníku a Účte.

Satflux ([satflux.io](http://satflux.io)) je **nekustodiálny ovládací panel** pre **BTCPay Server**. Obchodníkom dáva jedno miesto na správu jedného alebo viacerých Bitcoin a Lightning obchodov bez toho, aby sa vzdali kontroly nad svojimi prostriedkami. Všetky platby idú do peňaženky obchodníka; Satflux poskytuje len dashboard, nastavenia a nástroje okolo BTCPay.

**V skratke**

- **Nekustodiálny ovládací panel pre BTCPay** - Svoje kľúče a mince si držíte vy; Satflux vám pomáha prevádzkovať obchody.

- **Multi-store** - Z jedného účtu môžete viesť viac obchodov (e-shopy, kamenné obchody, služby) a prepínať medzi nimi v jednom rozhraní.

- **Lightning** - Vyberte podporovanú Lightning adresu alebo NWC pripojenie k vlastnému uzlu. Nastavením vás prevedie checklist peňaženky.

- **Pre koho je** - Pre obchody, obchodníkov, služby a e-shopy, ktorí chcú prijímať Bitcoin a Lightning vlastnou peňaženkou a bez kustóda.

- **Výhody pre obchodníkov** - Jeden dashboard pre obchody a reporty; Point of Sale (PoS) aplikácie pre platby na mieste alebo z mobilu; tvorba a sledovanie faktúr; exporty do CSV a (na vyšších plánoch) XLSX pre účtovníctvo; voliteľné Lightning adresy (napr. [meno@satflux.org](mailto:meno@satflux.org)) pre dary alebo jednoduché platobné odkazy; REST API a webhooky pre integrácie; žiadna závislosť - BTCPay môžete neskôr presunúť alebo hostiť sami.

**Nekustodiálnosť v praxi**

Satflux nedrží ani nekontroluje vaše prostriedky. Pripájate **vlastnú** Lightning peňaženku (podporovaná Lightning adresa alebo NWC). BTCPay Server generuje faktúry a prijíma platby; peniaze idú do peňaženky, ktorú kontrolujete vy. Limity predplatného a funkcií v Satfluxe sa týkajú len funkcií správy (počet obchodov, exporty, Lightning adresy), nie schopnosti prijímať platby s existujúcim nastavením.

**Zhrnutie**

Satflux je ovládací panel a vrstva prevádzky nad BTCPay: multi-store, Lightning (podporovaná Lightning adresa alebo NWC) a nástroje pre obchodníkov, ktorí chcú jednoduchý, nekustodiálny spôsob prijímania Bitcoinu a Lightningu.
