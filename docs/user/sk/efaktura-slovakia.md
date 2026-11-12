---
title: Slovenská e-fakturácia - prepojenie ePostman
category: invoicing
order: 6
meta_description: Prepojte firemný účet ePostman, prijímajte e-faktúry a importujte ich originály do nákladov v Satfluxe.
updated: 2026-11-12
---

# Slovenská e-fakturácia - prepojenie ePostman

Prepojenie podporuje **prijímanie a manuálne odosielanie**. Príjem a import faktúry aj dobropisu boli overené v testovacej sieti Peppol. Manuálne odoslanie oboch typov cez ePostman do Recommandu bolo tiež potvrdené v tejto testovacej sieti. Produkčnú aktiváciu, zákonné hlásenie a podmienky archívu poštára treba potvrdiť samostatne. Satflux neprezentujeme ako kompletné riešenie povinnej slovenskej e-fakturácie.

## Prepojenie firmy

Každú firmu pripojte samostatne. Zapnutie prijímania slovenskej firmy ho nezapne pre zahraničnú LLC.

1. Otvorte nastavenia **e-fakturácie** správnej firmy a zapnite e-fakturáciu. Vyberte **ePostman** a príslušné prostredie. Produkčné a testovacie účty majú oddelené prístupové údaje; pri zmene prostredia potrebujete údaje daného prostredia. Adresu servera ePostman vyplní Satflux.
2. Dokončite aktiváciu firmy v ePostman. Pre produkciu postupujte podľa [návodu poštára na aktiváciu](https://postman.slovakodata.com/help/zaciname/onboarding/) a potvrďte registráciu na prijímanie. Výber poštára v Satfluxe nezaregistruje firmu u poštára ani na Finančnej správe. Pri testovaní používajte výhradne testovací portál a testovacie údaje.
3. V portáli ePostman otvorte **Nastavenia > SAPI klienti** a vytvorte samostatného klienta pre Satflux. Jeho **Client ID** a **Client Secret** skopírujte do Satfluxu. Ide o údaje SAPI klienta, nie o REST API kľúč. Poštár zobrazí secret iba pri vytvorení; uchovávajte ho v súkromí. Pozrite si [návod na nastavenia poštára](https://postman.slovakodata.com/help/portal/nastavenia/).
4. Skontrolujte identitu firmy zobrazenú v Satfluxe. Identifikátor na prijímanie sa odvodzuje z firemných údajov. Ak sprievodca hlási chýbajúce DIČ, najskôr doplňte správne slovenské DIČ v údajoch firmy. Použite prístupové údaje patriace tejto firme.
5. Vyberte **Otestovať pripojenie**. Test overuje iba prístupové údaje. Nepotvrdzuje registráciu na prijímanie, doručenie dokladu ani splnenie zákonných povinností. Pred pokračovaním opravte prípadnú chybu.
6. Nechajte prijímanie zapnuté a nastavenia **uložte**. Samotný úspešný test neuloží celé nastavenie prijímania. Vyriešte prípadnú chybu uloženia alebo synchronizácie. Po úspešnom uložení môže Satflux sťahovať prichádzajúce doklady na pozadí aj pri zatvorenom prehliadači. Ak formulár po uložení zmeneného pripojenia žiada nový test, zopakujte ho na overenie uložených prístupových údajov.

Ak je secret už uložený pre rovnaké pripojenie, jeho pole nechajte prázdne. Pri zmene poštára alebo prostredia zadajte údaje nového pripojenia a test zopakujte.

## Kde nájsť a importovať doklady

V rovnakej firme otvorte **Náklady > Prijaté e-faktúry**. Doklad skontrolujte alebo stiahnite pôvodné XML a potom ho importujte do nákladov. Dobropisy sa importujú ako opravy so zodpovedajúcim znamienkom sumy. Úspešné prázdne stiahnutie znamená, že sa nestiahli nové doklady; nepotvrdzuje doručenie testovacej faktúry.

Manuálne stiahnutie spustíte cez **Stiahnuť prichádzajúce teraz** v nastaveniach e-fakturácie firmy. Následne obnovte inbox na stránke Náklady. Obnovenie inboxu zobrazí doklady, ktoré už Satflux stiahol.

Import uloží originál do vašich šifrovaných lokálnych dát. Ak import zlyhá alebo je originál príliš veľký na lokálnu prílohu, položka zostane dostupná na obnovu a stiahnutie originálu. Samotná existencia nákladu import nedokončí.

## Uchovanie originálov a náklady poštára

Kým sa lokálny import overene nedokončí, originál je na serveri šifrovaný kľúčmi servera. Spracúvajú sa tam aj prístupové údaje poštára a rozparsovaný súhrn dokladu. Čakajúce originály sa automaticky nemažú podľa veku. Po overenom lokálnom prevzatí sa dočasný obsah servera odstráni. Nejde o dlhodobý serverový archív Satfluxu. Prístup do archívu, dobu uchovania a export si potvrďte s poštárom aj pre prípad zrušenia účtu.

Účet poštára môže mať osobitné poplatky za API alebo podmienky používania. Bezplatná webová schránka nemusí zahŕňať prístup zo Satfluxu. Pred používaním produkčného prepojenia si v ePostman potvrďte príslušný balík, SAPI prístup a podmienky archívu.

## Manuálne odosielanie a zákonné povinnosti

Pri tomto tuzemskom odosielaní musia mať obe strany krajinu Slovensko a adresu `0245:<DIČ>` s vlastným desaťmiestnym DIČ.

Táto prvá verzia odosielania odmietne doklady so zľavou na položke alebo faktúre, cenami so stratou presnosti pri exporte alebo sumami odlišnými od vystaveného dokladu. Tieto doklady bude možné odoslať až po doplnení príslušného exportu. Čiastočná úhrada sa započíta do zostatku v XML.

Pri oprávnenej slovenskej firme, ktorá je plným platiteľom DPH, otvorte vystavenú tuzemskú B2B faktúru alebo dobropis a vyberte **Odoslať e-faktúru**. Najskôr uložte správne firemné pripojenie. Vystaviteľ aj prístupové údaje musia patriť rovnakej firme. Automatické odosielanie cez ePostman zostáva vypnuté.

Úspešné podanie znamená **prijatie na spracovanie**, nie doručenie ani prijatie Finančnou správou. Doručenie a hlásenie skontrolujte cez odkaz do portálu ePostman v rovnakom produkčnom alebo testovacom prostredí. Prístupové údaje SAPI tieto stavy nevedia čítať; automatické sledovanie vyžaduje samostatnú integráciu.

Ak je výsledok neistý, zopakujte pokus iba vtedy, keď to Satflux ponúkne. Opakovaný pokus používa pôvodnú požiadavku a identifikátor v časovom limite poštára. Po uplynutí limitu alebo zmene dokladu či pripojenia najskôr overte výsledok u poštára. Nevytvárajte novú faktúru iba na zopakovanie neistého podania.

Konkrétne povinnosti a termíny firmy si potvrďte u Finančnej správy alebo účtovníka. Samotné funkčné pripojenie nepotvrdzuje splnenie všetkých povinností odosielania a hlásenia.
