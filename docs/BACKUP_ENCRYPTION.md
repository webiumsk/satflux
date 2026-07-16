# Šifrovanie zálohy (P2 fáza 2)

## Prehľad

Export zálohy v Profile (Synchronizácia → Záloha dát) má voliteľné šifrovanie
prístupovou frázou. Predvolený export zostáva plaintext (rozhodnutie v1 -
záloha musí prežiť stratu recovery frázy, preto nie je viazaná na účet);
šifrovanie je opt-in checkbox.

## Formát súboru

Šifrovaný súbor (`satflux-backup-RRRR-MM-DD.encrypted.json`) je vonkajší obal:

```json
{
  "format": "satflux-invoicing-backup-encrypted",
  "version": 1,
  "created_at": "...",
  "kdf": { "kind": "PBKDF2-SHA256", "saltB64": "...", "iterations": 600000 },
  "cipher": { "kind": "AES-256-GCM", "ivB64": "...", "ciphertextB64": "..." }
}
```

Ciphertext je kompletný plaintext obal (`satflux-invoicing-backup` v1 vrátane
vlastného SHA-256 integrity hashu a `owner_id_hash`). Von z ciphertextu
neuniká nič okrem faktu, že ide o satflux zálohu, a času vytvorenia.

## Kryptografia

- Rovnaké auditované primitívy ako device unlock envelope - extrahované do
  `resources/js/services/passphraseCrypto.ts` (čisté WebCrypto, žiadna
  vlastná kryptografia): PBKDF2-HMAC-SHA-256 → AES-256-GCM kľúč.
- Iterácie sa kalibrujú na ~750 ms na zariadení exportu, nikdy pod OWASP
  floor 600 000; použitý počet je uložený v `kdf.iterations`.
- AAD viaže ciphertext na formát a verziu (`satflux-invoicing-backup-encrypted.v1`).
  Zámerne NIE na ownera - záloha musí byť obnoviteľná do čerstvého účtu.
- Zlá fráza, poškodený súbor aj chýbajúce WebCrypto zlyhajú tou istou
  nerozlišujúcou chybou (`backup_decrypt_failed`) - GCM autentizácia.

## Prístupová fráza

- Minimálne 8 znakov, čisto číselná sa odmietne (rovnaké pravidlo ako device
  unlock).
- Tlačidlo "Vygenerovať frázu" vytvorí 6 náhodných BIP-39 slov (66 bitov
  entropie, uniformné vzorkovanie). Fráza sa zobrazuje ako text, aby si ju
  používateľ mohol zapísať.
- **Neexistuje žiadna obnova frázy.** UI na to explicitne upozorňuje pri
  exporte; stratená fráza = nepoužiteľná záloha.

## Obnova

Restore modál automaticky rozpozná šifrovaný súbor (`classifyBackupText`),
vyžiada frázu, dešifruje a vnútorný obal pošle cez ten istý validačný
pipeline ako plaintext súbor (schéma + SHA-256 + owner párovanie). Zvyšok
toku (preview počtov, potvrdenie, merge sémantika, report) je identický.

## Testy

`resources/js/__tests__/invoicingBackupCrypto.test.ts` - round-trip s plnou
validáciou, zlá fráza, tamper detekcia, klasifikácia súborov, generátor
(6 slov z wordlistu). Testy používajú nízke iterácie; produkcia kalibruje.

## Súvisiace

- Passkey-PRF slot pre device unlock: [PASSKEY_PRF_DEVICE_UNLOCK.md](PASSKEY_PRF_DEVICE_UNLOCK.md)
