/**
 * Device unlock envelope - opt-in "remember this device" for the Evolu recovery
 * phrase. Pure WebCrypto (audited native primitives only, no custom crypto).
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

const CONTEXT = "satflux.device-envelope";
export const ENVELOPE_VERSION = 1 as const;

/** OWASP 2023 floor for PBKDF2-HMAC-SHA-256; calibration only raises it. */
export const PASSPHRASE_MIN_ITERATIONS = 600_000;

/** A short numeric PIN is rejected - too weak for an offline-brute-forceable envelope. */
export const PASSPHRASE_MIN_LENGTH = 8;

export type DeviceUnlockSlotType = "passphrase" | "passkey-prf";

type CipherBlob = { ivB64: string; ciphertextB64: string };

export type PassphraseKdf = {
    kind: "PBKDF2-SHA256";
    saltB64: string;
    iterations: number;
};

export type DeviceUnlockSlot = {
    id: string;
    type: DeviceUnlockSlotType;
    createdAt: string;
    kdf: PassphraseKdf;
    /** DEK encrypted under this slot's KEK. */
    wrappedDek: CipherBlob;
};

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

const subtle = () => {
    const c = globalThis.crypto?.subtle;
    if (!c) {
        throw new DeviceUnlockError();
    }
    return c;
};

function toB64(bytes: ArrayBuffer | Uint8Array): string {
    const u8 = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    let s = "";
    for (const b of u8) s += String.fromCharCode(b);
    return btoa(s);
}

function fromB64(b64: string): Uint8Array {
    const s = atob(b64);
    const u8 = new Uint8Array(s.length);
    for (let i = 0; i < s.length; i++) u8[i] = s.charCodeAt(i);
    return u8;
}

function randomBytes(len: number): Uint8Array {
    const u8 = new Uint8Array(len);
    globalThis.crypto.getRandomValues(u8);
    return u8;
}

function aad(ownerFingerprint: string): Uint8Array {
    return new TextEncoder().encode(`${CONTEXT}.v${ENVELOPE_VERSION}|${ownerFingerprint}`);
}

/** Reject numeric PINs and too-short secrets; a multi-word passphrase passes. */
export function isAcceptableDevicePassphrase(passphrase: string): boolean {
    const p = passphrase ?? "";
    if (p.length < PASSPHRASE_MIN_LENGTH) return false;
    if (/^\d+$/.test(p)) return false;
    return true;
}

async function deriveKek(passphrase: string, salt: Uint8Array, iterations: number): Promise<CryptoKey> {
    const base = await subtle().importKey(
        "raw",
        new TextEncoder().encode(passphrase),
        "PBKDF2",
        false,
        ["deriveKey"],
    );
    return subtle().deriveKey(
        { name: "PBKDF2", salt: salt as BufferSource, iterations, hash: "SHA-256" },
        base,
        { name: "AES-GCM", length: 256 },
        false,
        ["encrypt", "decrypt"],
    );
}

async function aesGcmEncrypt(key: CryptoKey, plaintext: Uint8Array, additional: Uint8Array): Promise<CipherBlob> {
    const iv = randomBytes(12);
    const ciphertext = await subtle().encrypt(
        { name: "AES-GCM", iv: iv as BufferSource, additionalData: additional as BufferSource },
        key,
        plaintext as BufferSource,
    );
    return { ivB64: toB64(iv), ciphertextB64: toB64(ciphertext) };
}

async function aesGcmDecrypt(key: CryptoKey, blob: CipherBlob, additional: Uint8Array): Promise<Uint8Array> {
    try {
        const plaintext = await subtle().decrypt(
            { name: "AES-GCM", iv: fromB64(blob.ivB64) as BufferSource, additionalData: additional as BufferSource },
            key,
            fromB64(blob.ciphertextB64) as BufferSource,
        );
        return new Uint8Array(plaintext);
    } catch {
        throw new DeviceUnlockError();
    }
}

/**
 * Calibrate PBKDF2 iterations to ~targetMs on this device, never below the floor.
 * The chosen count is stored in the slot so unlock uses the same cost.
 */
export async function calibratePbkdf2Iterations(targetMs = 750, minIterations = PASSPHRASE_MIN_ITERATIONS): Promise<number> {
    const probeIterations = 100_000;
    const salt = randomBytes(16);
    const start = performance.now();
    await deriveKek("calibration-probe", salt, probeIterations);
    const elapsed = performance.now() - start;
    if (elapsed <= 0) {
        return minIterations;
    }
    const scaled = Math.round((probeIterations * targetMs) / elapsed);
    return Math.max(minIterations, scaled);
}

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
    const slots = envelope.slots.filter((s) => s.type === "passphrase");
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
