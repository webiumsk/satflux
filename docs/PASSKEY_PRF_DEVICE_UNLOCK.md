# Passkey-PRF device unlock (P3 fáza 1)

Passkey (WebAuthn) skratka pre odomknutie zariadenia v local-first invoicingu.
Dopĺňa passphrase unlock z P2 - nikdy ho nenahrádza.

## Dizajn

Envelope (`resources/js/services/deviceUnlock/envelope.ts`) je multi-slotový:
náhodný DEK (AES-256-GCM) šifruje obnovovaciu frázu raz; každý slot wrapuje
len DEK vlastným KEK. Passkey slot pridáva druhý typ KEK:

- **`kdf.kind: "WEBAUTHN-PRF"`** - KEK = HKDF-SHA-256(PRF výstup,
  info = `satflux.device-envelope.prf.v1|{ownerFingerprint}`) → AES-GCM-256.
- **PRF výstup** je 32-bajtový tajný výstup WebAuthn PRF extension
  (hmac-secret) pre pevný evaluačný vstup (`prfSaltB64`, 32 B náhodných,
  uložený per slot). Rovnaký credential + rovnaký vstup → rovnaký výstup.
- **Nikdy WebAuthn podpis ako KDF vstup** - podpisy nie sú navrhnuté ako
  tajný/uniformný keying material (pozri poznámku v provider.ts z P2).
- AAD binding, verzia envelope (v1) a generická `DeviceUnlockError` ostávajú
  z P2 nezmenené. Envelope s passkey slotom prečíta aj starší build
  (unlock filtruje `type === "passphrase"`).

## Invarianty

1. **Passphrase slot je povinný, passkey voliteľný.** Slot sa dá pridať len
   cez `addPasskeyToRememberedDevice(passphrase, label)` - vyžaduje heslo
   zariadenia (dôkaz vlastníctva + DEK); `removePasskeyPrfSlot` odstraňuje
   výhradne passkey sloty. Strata všetkých authenticatorov nikdy nezamkne
   dáta - heslo zariadenia a obnovovacia fráza sú koreňové cesty.
2. **Test-unlock pred uložením** - rovnaká crash/idempotency poistka ako pri
   `rememberDeviceWithPassphrase`.
3. **Multi-device**: envelope unesie viac passkey slotov; odomknutie robí
   jeden `navigator.credentials.get()` so všetkými credentialmi
   (`prf.evalByCredential`), platforma/užívateľ si vyberie. Synced passkeys
   (iCloud/Google) fungujú na ďalšom zariadení až po pridaní slotu na ŇOM
   (envelope je per-zariadenie v IndexedDB).
4. Chybové stavy: zrušenie promptu je ticho (žiadna chyba), authenticator
   bez PRF → typovaná hláška, všetko ostatné → generická hláška bez oracle.

## Súbory

- `services/deviceUnlock/envelope.ts` - PasskeyPrfKdf, addPasskeyPrfSlot,
  unlockWithPrfOutput, listPasskeyPrfSlots, removePasskeyPrfSlot,
  touchPasskeyPrfSlot (lastUsedAt).
- `services/deviceUnlock/passkeyPrf.ts` - WebAuthn vrstva (natívne API, bez
  závislostí): createPasskeyPrfCredential, evaluatePrfForSlots, evaluatePrf,
  isPasskeyPrfSupported; typované chyby.
- `services/deviceUnlock/provider.ts` - addPasskeyToRememberedDevice,
  unlockDeviceWithPasskey, listDevicePasskeySlots, removeDevicePasskeySlot;
  `passkeyPrfUnlockProvider.isSupported()` už nie je stub.
- `pages/account/Profile.vue` - tlačidlo "Odomknúť passkeyom" v unlock modali
  + správa slotov (zoznam s lastUsedAt, pridanie s heslom, odstránenie
  s potvrdením) v bloku zapamätaného zariadenia.

## Podpora prehliadačov (snapshot 07/2026)

| Platforma | PRF podpora |
|---|---|
| Chrome/Edge 116+ (desktop, Android) | áno (platform authenticator / security key s hmac-secret) |
| Safari 18+ (macOS/iOS) | áno (iCloud Keychain passkeys) |
| Firefox | čiastočná/za flagom - `isSupported()` prompt vôbec neponúkne |

`isPasskeyPrfSupported()` vie overiť len prítomnosť platform authenticatora;
či authenticator PRF reálne implementuje sa zistí až pri vytvorení - preto
vytvorenie kontroluje `prf.enabled` a pri absencii typovane zlyhá (slot sa
nevytvorí, žiadny polovičný stav). Niektoré authenticatory nevrátia PRF výstup
už pri create() - vtedy nasleduje jeden follow-up get() (druhý prompt).

## Manuál

1. Profil → zapamätané zariadenie → **Pridať passkey** → zadaj heslo
   zariadenia → potvrď passkey prompt (prípadne dva).
2. Odomykanie: v unlock modali **Odomknúť passkeyom** (primárne, keď slot
   existuje); heslo a obnovovacia fráza ostávajú pod tým.
3. Reset passkey = odstrániť + pridať nový.
4. "Záloha" passkey = len verejné metadáta v envelope (label, credential id,
   PRF salt, dátumy) - privátny kľúč nikdy neopustí authenticator.

## Testy

- `__tests__/passkeyPrfSlot.test.ts` - krypto vrstva (mockovaný PRF výstup):
  round-trip, koexistencia s passphrase slotom, zlý výstup/credential →
  generická chyba, multi-slot, remove/touch, čitateľnosť starým buildom.
- `__tests__/passkeyUnlockProvider.test.ts` - provider s mockovaným WebAuthn
  + envelope store: add (vyžaduje heslo), unlock + lastUsedAt, remove
  so zachovaným passphrase unlockon, generické chyby.

Manuálne overenie: Chrome desktop + Android, Safari iOS; strata passkey →
passphrase fallback; zrušenie promptu bez chybovej hlášky.
