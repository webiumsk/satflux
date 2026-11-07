---
title: Záloha a obnova (24 slov)
category: security
order: 4
meta_description: Chráňte obnovovaciu frázu a exportujte zálohy fakturácie; zistite rozdiel medzi obnovou cez relay a zo súboru.
updated: 2026-11-07
---

# Záloha a obnova (24 slov)

Vaša **24-slovná obnovovacia fráza** obnovuje prístup k vašej Satflux identite a odvodzujú sa z nej kľúče k šifrovaným fakturačným dátam. Neobsahuje faktúry a nenahrádza zálohu dát.

> **Satflux obnovovaciu frázu neukladá a nevie ju resetovať.** Uložte si ju na bezpečné miesto pred odhlásením alebo zatvorením kariet.

## Zálohujte si ju poriadne

- Napíšte 24 slov **v poradí** na papier (alebo oceľ) a uložte na bezpečné, offline miesto.
- Nevkladajte ich do poznámkových aplikácií, chatov ani screenshotov.
- Zvážte druhú kópiu na inom mieste.

Satflux zobrazuje **pripomienku zálohy**, kým nepotvrdíte, že ste frázu uložili - nezavrite ju skôr, než to naozaj spravíte.

## Obnova na inom zariadení

Na novom zariadení zvoľte **Obnoviť pomocou frázy** a zadajte 24 slov na obnovenie prístupu k účtu. **Obnova fakturačných dát cez relay vyžaduje správnu obnovovaciu frázu aj dostupnú kópiu dát.** Fráza samotná nedokáže zrekonštruovať dáta vymazané zo všetkých zariadení a relayov. Pred vytvorením firiem nechajte synchronizáciu dobehnúť, aby nevznikli duplicity. Pre pohodlie si na novom zariadení pridajte [passkey](/documentation/passkeys).

## Export fakturačnej zálohy

1. Na zariadení, ktoré ešte má vaše fakturačné dáta, otvorte **Nastavenia účtu > Evolu sync > Záloha dát**.
2. Ak chcete súbor zašifrovať, zapnite **Zašifrovať zálohu prístupovou frázou** a zvoľte alebo vygenerujte frázu k zálohe. Uložte si ju oddelene od súboru; ide o frázu pre túto zálohu, nemusí to byť vaša 24-slovná obnovovacia fráza.
3. Vyberte **Exportovať dáta** a stiahnutý JSON súbor uložte mimo prehliadača. Bez zapnutého šifrovania obsahuje súbor čitateľné faktúry; uchovávajte ho bezpečne.

## Obnova fakturačných dát zo súboru

Exportovanú fakturačnú zálohu môžete importovať do iného účtu **bez obnovovacej frázy pôvodného účtu**. Obnovuje fakturačné dáta, nie prihlasovanie do pôvodného účtu ani prihlasovacie údaje BTCPay.

1. Prihláste sa do cieľového účtu, odomknite jeho lokálne fakturačné dáta a otvorte **Nastavenia účtu > Evolu sync > Záloha dát**. Ak si chcete uchovať samostatnú kópiu existujúcich dát, najprv ich exportujte.
2. Vyberte **Obnoviť zo súboru** a zvoľte JSON súbor zálohy.
3. Ak je súbor šifrovaný, zadajte jeho **prístupovú frázu k zálohe** a vyberte **Odomknúť zálohu**. Obnovovacia fráza pôvodného účtu túto frázu nenahrádza.
4. Skontrolujte náhľad a prípadné upozornenie na iný účet, vyberte **Obnoviť zálohu** a potvrďte. Nové riadky sa pridajú a riadky s rovnakým ID prepíše verzia zo zálohy; ostatné lokálne riadky sa nemažú. Skontrolujte výsledok a prípadné zlyhania.

## Local-first dáta

Vymazanie dát prehliadača odstráni lokálnu fakturačnú kópiu na danom zariadení. Obnova potom potrebuje buď správnu obnovovaciu frázu a dostupnú kópiu na relayi, alebo exportovaný súbor zálohy (pri šifrovaní aj jeho prístupovú frázu). Ak nezostala žiadna kópia dát ani export, obnovovacia fráza samotná faktúry neobnoví.
