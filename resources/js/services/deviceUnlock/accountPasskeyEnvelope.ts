/**
 * Account-level passkey recovery envelope: the recovery phrase encrypted
 * under a key derived from the passkey's WebAuthn PRF output, stored
 * SERVER-SIDE as ciphertext so a synced passkey (iCloud/Google) can restore
 * the account on a brand-new device with one gesture.
 *
 * Design notes (docs/PASSKEY_PRF_DEVICE_UNLOCK.md):
 * - FIXED PRF eval input (vs the per-slot random salts of the local device
 *   envelope): at sign-in the credential id is only known AFTER the
 *   assertion, so the eval input cannot be per-credential. The PRF output
 *   is still unique per credential - the authenticator mixes the
 *   credential's own secret - so a fixed input costs nothing.
 * - KEK = HKDF-SHA-256(prfOutput, zero salt, fixed info). The info string
 *   deliberately differs from the local envelope's, so the same PRF output
 *   yields domain-separated keys.
 * - No account binding in the AAD: at sign-in the account is unknown until
 *   the phrase decrypts. Binding comes from the per-credential KEK itself -
 *   a swapped ciphertext simply fails to decrypt - and the decrypted phrase
 *   is validated (BIP39) before any use.
 * - The server grants nothing from this blob alone: the session comes from
 *   the existing Ed25519 guest-recovery challenge signed with the
 *   DECRYPTED phrase.
 */

import { validateMnemonic } from "@scure/bip39";
import { wordlist } from "@scure/bip39/wordlists/english.js";
import api from "../api";
import { normalizeAccountMnemonic } from "../accountSeed";
import { DeviceUnlockError } from "./envelope";
import {
    aesGcmDecrypt,
    aesGcmEncrypt,
    fromB64,
    subtleCrypto,
} from "../passphraseCrypto";

export const ACCOUNT_ENVELOPE_VERSION = 1 as const;

/**
 * SHA-256("satflux.account-envelope.prf-input.v1") - the fixed 32-byte PRF
 * evaluation input shared by every account envelope (see design notes).
 */
export const ACCOUNT_PRF_INPUT_B64 = ((): string => {
    const hex = "cecbd18f8bea953af78ce7e0650fd90eb59255a2d4b15a598ec97d73bb30c396";
    const bytes = hex.match(/.{2}/g)!.map((pair) => parseInt(pair, 16));
    return btoa(String.fromCharCode(...bytes));
})();

const KDF_INFO = "satflux.account-envelope.v1";

export type AccountEnvelopePayload = {
    v: typeof ACCOUNT_ENVELOPE_VERSION;
    ivB64: string;
    ciphertextB64: string;
};

async function deriveAccountKek(prfOutput: Uint8Array): Promise<CryptoKey> {
    try {
        const ikm = await subtleCrypto().importKey("raw", prfOutput as BufferSource, "HKDF", false, ["deriveKey"]);
        return await subtleCrypto().deriveKey(
            {
                name: "HKDF",
                hash: "SHA-256",
                salt: new Uint8Array(32),
                info: new TextEncoder().encode(KDF_INFO),
            },
            ikm,
            { name: "AES-GCM", length: 256 },
            false,
            ["encrypt", "decrypt"],
        );
    } catch {
        throw new DeviceUnlockError();
    }
}

/** Encrypt the recovery phrase for server-side storage. */
export async function encryptAccountEnvelope(recoveryPhrase: string, prfOutput: Uint8Array): Promise<string> {
    const normalized = normalizeAccountMnemonic(recoveryPhrase);
    if (!validateMnemonic(normalized, wordlist)) {
        throw new DeviceUnlockError();
    }
    const kek = await deriveAccountKek(prfOutput);
    const blob = await aesGcmEncrypt(kek, new TextEncoder().encode(normalized), new TextEncoder().encode(KDF_INFO));

    const payload: AccountEnvelopePayload = {
        v: ACCOUNT_ENVELOPE_VERSION,
        ivB64: blob.ivB64,
        ciphertextB64: blob.ciphertextB64,
    };

    return JSON.stringify(payload);
}

/**
 * Decrypt a fetched envelope. Every failure (bad payload, wrong key,
 * tampered blob, invalid phrase inside) is the same generic error.
 */
export async function decryptAccountEnvelope(payloadJson: string, prfOutput: Uint8Array): Promise<string> {
    let payload: AccountEnvelopePayload;
    try {
        payload = JSON.parse(payloadJson) as AccountEnvelopePayload;
    } catch {
        throw new DeviceUnlockError();
    }
    if (payload?.v !== ACCOUNT_ENVELOPE_VERSION || !payload.ivB64 || !payload.ciphertextB64) {
        throw new DeviceUnlockError();
    }

    const kek = await deriveAccountKek(prfOutput);
    let plaintext: Uint8Array;
    try {
        plaintext = await aesGcmDecrypt(
            kek,
            { ivB64: payload.ivB64, ciphertextB64: payload.ciphertextB64 },
            new TextEncoder().encode(KDF_INFO),
        );
    } catch {
        throw new DeviceUnlockError();
    }

    const phrase = normalizeAccountMnemonic(new TextDecoder().decode(plaintext));
    plaintext.fill(0);
    if (!validateMnemonic(phrase, wordlist)) {
        throw new DeviceUnlockError();
    }

    return phrase;
}

/** WebAuthn's APIs key credentials by base64url; server stores base64url ids. */
export function credentialIdToB64Url(credentialIdB64: string): string {
    return credentialIdB64.replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
}

export type AccountEnvelopeSummary = {
    credential_id: string;
    label: string | null;
    created_at: string;
    last_used_at: string | null;
};

export async function listAccountEnvelopes(): Promise<AccountEnvelopeSummary[]> {
    const { data } = await api.get<{ data: AccountEnvelopeSummary[] }>("/account/passkey-envelopes");
    return data.data;
}

export async function putAccountEnvelope(params: {
    credentialIdB64: string;
    payload: string;
    label: string | null;
    transports?: string[] | null;
}): Promise<void> {
    await api.put(`/account/passkey-envelopes/${credentialIdToB64Url(params.credentialIdB64)}`, {
        payload: params.payload,
        label: params.label,
        envelope_version: ACCOUNT_ENVELOPE_VERSION,
        transports: params.transports ?? null,
    });
}

export async function deleteAccountEnvelope(credentialIdB64: string): Promise<void> {
    await api.delete(`/account/passkey-envelopes/${credentialIdToB64Url(credentialIdB64)}`);
}

/** Unauthenticated sign-in fetch - generic failure, ciphertext-only response. */
export async function fetchEnvelopeForLogin(credentialIdB64: string): Promise<string> {
    try {
        const { data } = await api.post<{ data: { payload: string } }>("/auth/passkey/envelope", {
            credential_id: credentialIdToB64Url(credentialIdB64),
        });
        return data.data.payload;
    } catch {
        throw new DeviceUnlockError();
    }
}

/** The fixed PRF input as bytes, for WebAuthn eval calls. */
export function accountPrfInputBytes(): Uint8Array {
    return fromB64(ACCOUNT_PRF_INPUT_B64);
}
