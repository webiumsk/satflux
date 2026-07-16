/**
 * Device unlock envelope - opt-in "remember this device" for the Evolu recovery
 * phrase. Pure WebCrypto (audited native primitives only, no custom crypto);
 * the PBKDF2/AES-GCM building blocks live in services/passphraseCrypto.ts and
 * are shared with the encrypted backup (P2 phase 2).
 *
 * Threat model: protects the phrase at rest in browser storage against PASSIVE
 * theft (stolen/backed-up profile). It does NOT protect against active
 * same-origin XSS - once unlocked the phrase is in memory, so anything running
 * in the origin can read it. CSP + session-only default remain the frontline.
 *
 * Key-slot / envelope design (lets a passkey-PRF slot be added later without a
 * new recovery seed or re-encrypting the phrase):
 *   - a random Data Encryption Key (DEK, AES-256-GCM) encrypts the recovery
 *     phrase once,
 *   - each unlock method is a "slot" whose Key Encryption Key (KEK) encrypts
 *     ONLY the DEK. A passphrase slot derives its KEK via PBKDF2-HMAC-SHA-256.
 *   - AES-GCM AAD binds every ciphertext to the format version, a SATFLUX
 *     context string, and the expected owner fingerprint (a public value), so a
 *     tampered or cross-account envelope fails to decrypt.
 *
 * The envelope never contains the passphrase, KEK, DEK, or plaintext phrase.
 */

import {
    PASSPHRASE_MIN_ITERATIONS,
    PASSPHRASE_MIN_LENGTH,
    aesGcmDecrypt as aesGcmDecryptRaw,
    aesGcmEncrypt as aesGcmEncryptRaw,
    calibratePbkdf2Iterations,
    deriveKekPbkdf2Sha256,
    fromB64,
    isAcceptablePassphrase,
    randomBytes,
    toB64,
    type CipherBlob,
} from "../passphraseCrypto";

const CONTEXT = "satflux.device-envelope";
export const ENVELOPE_VERSION = 1 as const;

export { PASSPHRASE_MIN_ITERATIONS, PASSPHRASE_MIN_LENGTH, calibratePbkdf2Iterations };
export type { CipherBlob };

export type DeviceUnlockSlotType = "passphrase" | "passkey-prf";

export type PassphraseKdf = {
    kind: "PBKDF2-SHA256";
    saltB64: string;
    iterations: number;
};

/**
 * Passkey PRF slot: the KEK comes from the WebAuthn PRF extension output
 * (HKDF-SHA-256 over the 32-byte PRF secret with a context info string) -
 * never from a WebAuthn signature. `prfSaltB64` is the fixed evaluation
 * input stored per slot so the authenticator returns the same secret on
 * every unlock. Everything here is public metadata.
 */
export type PasskeyPrfKdf = {
    kind: "WEBAUTHN-PRF";
    credentialIdB64: string;
    prfSaltB64: string;
    /** User-facing passkey label (device/authenticator name). */
    label: string;
    lastUsedAt?: string;
};

type DeviceUnlockSlotBase = {
    id: string;
    createdAt: string;
    /** DEK encrypted under this slot's KEK. */
    wrappedDek: CipherBlob;
};

export type PassphraseSlot = DeviceUnlockSlotBase & { type: "passphrase"; kdf: PassphraseKdf };
export type PasskeyPrfSlot = DeviceUnlockSlotBase & { type: "passkey-prf"; kdf: PasskeyPrfKdf };

/** Discriminated union: a slot type can never carry the other type's kdf. */
export type DeviceUnlockSlot = PassphraseSlot | PasskeyPrfSlot;

export function isPassphraseSlot(slot: DeviceUnlockSlot): slot is PassphraseSlot {
    return slot.type === "passphrase";
}

export function isPasskeyPrfSlot(slot: DeviceUnlockSlot): slot is PasskeyPrfSlot {
    return slot.type === "passkey-prf";
}

export type DeviceEnvelope = {
    version: typeof ENVELOPE_VERSION;
    /** Public owner fingerprint (recovery public key hex) - safe to store, binds AAD. */
    ownerFingerprint: string;
    /** Recovery phrase encrypted under the DEK. */
    encryptedSecret: CipherBlob;
    slots: DeviceUnlockSlot[];
};

/** Single non-distinguishing error for every decrypt/verify failure. */
export class DeviceUnlockError extends Error {
    constructor() {
        super("device_unlock_failed");
        this.name = "DeviceUnlockError";
    }
}

export class WeakPassphraseError extends Error {
    constructor() {
        super("weak_passphrase");
        this.name = "WeakPassphraseError";
    }
}

function aad(ownerFingerprint: string): Uint8Array {
    return new TextEncoder().encode(`${CONTEXT}.v${ENVELOPE_VERSION}|${ownerFingerprint}`);
}

/** Reject numeric PINs and too-short secrets; a multi-word passphrase passes. */
export function isAcceptableDevicePassphrase(passphrase: string): boolean {
    return isAcceptablePassphrase(passphrase);
}

async function deriveKek(passphrase: string, salt: Uint8Array, iterations: number): Promise<CryptoKey> {
    try {
        return await deriveKekPbkdf2Sha256(passphrase, salt, iterations);
    } catch {
        throw new DeviceUnlockError();
    }
}

async function aesGcmEncrypt(key: CryptoKey, plaintext: Uint8Array, additional: Uint8Array): Promise<CipherBlob> {
    try {
        return await aesGcmEncryptRaw(key, plaintext, additional);
    } catch {
        throw new DeviceUnlockError();
    }
}

async function aesGcmDecrypt(key: CryptoKey, blob: CipherBlob, additional: Uint8Array): Promise<Uint8Array> {
    try {
        return await aesGcmDecryptRaw(key, blob, additional);
    } catch {
        throw new DeviceUnlockError();
    }
}

const subtle = () => {
    const c = globalThis.crypto?.subtle;
    if (!c) {
        throw new DeviceUnlockError();
    }
    return c;
};

async function buildPassphraseSlot(passphrase: string, dekRaw: Uint8Array, additional: Uint8Array, iterations: number): Promise<DeviceUnlockSlot> {
    const salt = randomBytes(16);
    const kek = await deriveKek(passphrase, salt, iterations);
    const wrappedDek = await aesGcmEncrypt(kek, dekRaw, additional);
    return {
        id: crypto.randomUUID(),
        type: "passphrase",
        createdAt: new Date().toISOString(),
        kdf: { kind: "PBKDF2-SHA256", saltB64: toB64(salt), iterations },
        wrappedDek,
    };
}

/**
 * Create a fresh envelope: random DEK encrypts the phrase, one passphrase slot
 * wraps the DEK. `ownerFingerprint` is a public identifier derived from the
 * phrase by the caller (recovery public key hex) - it is stored and AAD-bound.
 */
export async function createDeviceEnvelope(params: {
    recoveryPhrase: string;
    passphrase: string;
    ownerFingerprint: string;
    iterations?: number;
}): Promise<DeviceEnvelope> {
    if (!isAcceptableDevicePassphrase(params.passphrase)) {
        throw new WeakPassphraseError();
    }
    const additional = aad(params.ownerFingerprint);
    const iterations = params.iterations ?? (await calibratePbkdf2Iterations());

    const dek = await subtle().generateKey({ name: "AES-GCM", length: 256 }, true, ["encrypt", "decrypt"]);
    const encryptedSecret = await aesGcmEncrypt(dek, new TextEncoder().encode(params.recoveryPhrase), additional);

    const dekRaw = new Uint8Array(await subtle().exportKey("raw", dek));
    try {
        const slot = await buildPassphraseSlot(params.passphrase, dekRaw, additional, iterations);
        return {
            version: ENVELOPE_VERSION,
            ownerFingerprint: params.ownerFingerprint,
            encryptedSecret,
            slots: [slot],
        };
    } finally {
        dekRaw.fill(0);
    }
}

/**
 * Unlock with a passphrase. Returns the recovery phrase and the raw DEK (so the
 * caller can add another slot). Any failure - wrong passphrase, tampered
 * envelope, unsupported crypto - throws the same DeviceUnlockError.
 */
export async function unlockWithPassphrase(
    envelope: DeviceEnvelope,
    passphrase: string,
): Promise<{ recoveryPhrase: string; dekRaw: Uint8Array }> {
    if (envelope.version !== ENVELOPE_VERSION) {
        throw new DeviceUnlockError();
    }
    const additional = aad(envelope.ownerFingerprint);
    const slots = envelope.slots.filter(isPassphraseSlot);
    if (slots.length === 0) {
        throw new DeviceUnlockError();
    }

    // Try each passphrase slot - a device can hold more than one (rotation
    // window, a second remembered secret). The generic error never reveals
    // which, if any, slot the passphrase belongs to.
    let dekRaw: Uint8Array | null = null;
    for (const slot of slots) {
        try {
            const kek = await deriveKek(passphrase, fromB64(slot.kdf.saltB64), slot.kdf.iterations);
            dekRaw = await aesGcmDecrypt(kek, slot.wrappedDek, additional);
            break;
        } catch {
            // Wrong passphrase for this slot - try the next one.
        }
    }
    if (!dekRaw) {
        throw new DeviceUnlockError();
    }

    const dek = await subtle().importKey("raw", dekRaw as BufferSource, "AES-GCM", false, ["decrypt"]);
    const secretBytes = await aesGcmDecrypt(dek, envelope.encryptedSecret, additional);
    return { recoveryPhrase: new TextDecoder().decode(secretBytes), dekRaw };
}

/** Add another passphrase slot to an unlocked envelope (rotation, second device secret). */
export async function addPassphraseSlot(params: {
    envelope: DeviceEnvelope;
    dekRaw: Uint8Array;
    passphrase: string;
    iterations?: number;
}): Promise<DeviceEnvelope> {
    if (!isAcceptableDevicePassphrase(params.passphrase)) {
        throw new WeakPassphraseError();
    }
    const additional = aad(params.envelope.ownerFingerprint);
    const iterations = params.iterations ?? (await calibratePbkdf2Iterations());
    const slot = await buildPassphraseSlot(params.passphrase, params.dekRaw, additional, iterations);
    return { ...params.envelope, slots: [...params.envelope.slots, slot] };
}

/**
 * Change the device passphrase without touching the recovery phrase: unlock with
 * the old passphrase, re-wrap the same DEK under the new one, replace the
 * passphrase slot(s). The DEK and encryptedSecret are unchanged.
 */
export async function rotatePassphrase(params: {
    envelope: DeviceEnvelope;
    oldPassphrase: string;
    newPassphrase: string;
    iterations?: number;
}): Promise<DeviceEnvelope> {
    if (!isAcceptableDevicePassphrase(params.newPassphrase)) {
        throw new WeakPassphraseError();
    }
    const { dekRaw } = await unlockWithPassphrase(params.envelope, params.oldPassphrase);
    try {
        const additional = aad(params.envelope.ownerFingerprint);
        const iterations = params.iterations ?? (await calibratePbkdf2Iterations());
        const newSlot = await buildPassphraseSlot(params.newPassphrase, dekRaw, additional, iterations);
        const otherSlots = params.envelope.slots.filter((s) => s.type !== "passphrase");
        return { ...params.envelope, slots: [...otherSlots, newSlot] };
    } finally {
        dekRaw.fill(0);
    }
}

/**
 * KEK from a WebAuthn PRF output: HKDF-SHA-256 with a context+owner info
 * string. The PRF secret is already uniform 32-byte keying material - HKDF
 * only binds it to this envelope's context, it adds no stretching (none is
 * needed; the secret never leaves the authenticator boundary unprotected).
 */
async function deriveKekFromPrfOutput(prfOutput: Uint8Array, ownerFingerprint: string): Promise<CryptoKey> {
    try {
        const ikm = await subtle().importKey("raw", prfOutput as BufferSource, "HKDF", false, ["deriveKey"]);
        return await subtle().deriveKey(
            {
                name: "HKDF",
                hash: "SHA-256",
                salt: new Uint8Array(32),
                info: new TextEncoder().encode(`${CONTEXT}.prf.v1|${ownerFingerprint}`),
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

/** Public passkey slot metadata for management UI. */
export type PasskeySlotMetadata = {
    id: string;
    label: string;
    createdAt: string;
    lastUsedAt: string | null;
    credentialIdB64: string;
};

export function listPasskeyPrfSlots(envelope: DeviceEnvelope): PasskeySlotMetadata[] {
    return envelope.slots.filter(isPasskeyPrfSlot).map((slot) => ({
        id: slot.id,
        label: slot.kdf.label,
        createdAt: slot.createdAt,
        lastUsedAt: slot.kdf.lastUsedAt ?? null,
        credentialIdB64: slot.kdf.credentialIdB64,
    }));
}

/**
 * Add a passkey PRF slot to an unlocked envelope. Never replaces or removes
 * passphrase slots - the passkey is a convenience shortcut; the passphrase
 * (and ultimately the recovery phrase) stays the root unlock path, so losing
 * every authenticator can never lock the data.
 */
export async function addPasskeyPrfSlot(params: {
    envelope: DeviceEnvelope;
    dekRaw: Uint8Array;
    prfOutput: Uint8Array;
    credentialIdB64: string;
    prfSaltB64: string;
    label: string;
}): Promise<DeviceEnvelope> {
    const additional = aad(params.envelope.ownerFingerprint);
    const kek = await deriveKekFromPrfOutput(params.prfOutput, params.envelope.ownerFingerprint);
    const wrappedDek = await aesGcmEncrypt(kek, params.dekRaw, additional);

    const slot: DeviceUnlockSlot = {
        id: crypto.randomUUID(),
        type: "passkey-prf",
        createdAt: new Date().toISOString(),
        kdf: {
            kind: "WEBAUTHN-PRF",
            credentialIdB64: params.credentialIdB64,
            prfSaltB64: params.prfSaltB64,
            label: params.label,
        },
        wrappedDek,
    };

    return { ...params.envelope, slots: [...params.envelope.slots, slot] };
}

/**
 * Unlock with a WebAuthn PRF output for a specific credential. Same
 * non-distinguishing DeviceUnlockError on every failure.
 */
export async function unlockWithPrfOutput(
    envelope: DeviceEnvelope,
    credentialIdB64: string,
    prfOutput: Uint8Array,
): Promise<{ recoveryPhrase: string; dekRaw: Uint8Array; slotId: string }> {
    if (envelope.version !== ENVELOPE_VERSION) {
        throw new DeviceUnlockError();
    }
    const additional = aad(envelope.ownerFingerprint);
    const slot = envelope.slots
        .filter(isPasskeyPrfSlot)
        .find((s) => s.kdf.credentialIdB64 === credentialIdB64);
    if (!slot) {
        throw new DeviceUnlockError();
    }

    const kek = await deriveKekFromPrfOutput(prfOutput, envelope.ownerFingerprint);
    const dekRaw = await aesGcmDecrypt(kek, slot.wrappedDek, additional);

    // From here the unwrapped DEK exists - zero it on ANY failure.
    try {
        const dek = await subtle().importKey("raw", dekRaw as BufferSource, "AES-GCM", false, ["decrypt"]);
        const secretBytes = await aesGcmDecrypt(dek, envelope.encryptedSecret, additional);
        return { recoveryPhrase: new TextDecoder().decode(secretBytes), dekRaw, slotId: slot.id };
    } catch (error) {
        dekRaw.fill(0);
        throw error;
    }
}

/** Remove a passkey slot; passphrase slots are never removable through this. */
export function removePasskeyPrfSlot(envelope: DeviceEnvelope, slotId: string): DeviceEnvelope {
    return {
        ...envelope,
        slots: envelope.slots.filter((slot) => !(isPasskeyPrfSlot(slot) && slot.id === slotId)),
    };
}

/** Stamp lastUsedAt on a passkey slot after a successful unlock. */
export function touchPasskeyPrfSlot(envelope: DeviceEnvelope, slotId: string): DeviceEnvelope {
    return {
        ...envelope,
        slots: envelope.slots.map((slot) =>
            isPasskeyPrfSlot(slot) && slot.id === slotId
                ? { ...slot, kdf: { ...slot.kdf, lastUsedAt: new Date().toISOString() } }
                : slot,
        ),
    };
}
