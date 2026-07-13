/**
 * Shared passphrase-based crypto primitives (pure WebCrypto, no custom
 * crypto). Extracted from deviceUnlock/envelope.ts (P2 phase 2) so the backup
 * encryption reuses the exact same audited building blocks: PBKDF2-HMAC-SHA-256
 * key derivation + AES-256-GCM with additional authenticated data.
 *
 * Errors are thrown raw here - each caller wraps them into its own
 * non-distinguishing domain error (DeviceUnlockError, backup_decrypt_failed).
 */

/** OWASP 2023 floor for PBKDF2-HMAC-SHA-256; calibration only raises it. */
export const PASSPHRASE_MIN_ITERATIONS = 600_000;

/** A short numeric PIN is rejected - too weak for an offline-brute-forceable ciphertext. */
export const PASSPHRASE_MIN_LENGTH = 8;

export type CipherBlob = { ivB64: string; ciphertextB64: string };

export function subtleCrypto(): SubtleCrypto {
    const c = globalThis.crypto?.subtle;
    if (!c) {
        throw new Error("webcrypto_unavailable");
    }
    return c;
}

export function toB64(bytes: ArrayBuffer | Uint8Array): string {
    const u8 = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    let s = "";
    for (const b of u8) s += String.fromCharCode(b);
    return btoa(s);
}

export function fromB64(b64: string): Uint8Array {
    const s = atob(b64);
    const u8 = new Uint8Array(s.length);
    for (let i = 0; i < s.length; i++) u8[i] = s.charCodeAt(i);
    return u8;
}

export function randomBytes(len: number): Uint8Array {
    const u8 = new Uint8Array(len);
    globalThis.crypto.getRandomValues(u8);
    return u8;
}

/** Reject numeric PINs and too-short secrets; a multi-word passphrase passes. */
export function isAcceptablePassphrase(passphrase: string): boolean {
    const p = passphrase ?? "";
    if (p.length < PASSPHRASE_MIN_LENGTH) return false;
    if (/^\d+$/.test(p)) return false;
    return true;
}

export async function deriveKekPbkdf2Sha256(
    passphrase: string,
    salt: Uint8Array,
    iterations: number,
): Promise<CryptoKey> {
    const base = await subtleCrypto().importKey(
        "raw",
        new TextEncoder().encode(passphrase),
        "PBKDF2",
        false,
        ["deriveKey"],
    );
    return subtleCrypto().deriveKey(
        { name: "PBKDF2", salt: salt as BufferSource, iterations, hash: "SHA-256" },
        base,
        { name: "AES-GCM", length: 256 },
        false,
        ["encrypt", "decrypt"],
    );
}

export async function aesGcmEncrypt(
    key: CryptoKey,
    plaintext: Uint8Array,
    additional: Uint8Array,
): Promise<CipherBlob> {
    const iv = randomBytes(12);
    const ciphertext = await subtleCrypto().encrypt(
        { name: "AES-GCM", iv: iv as BufferSource, additionalData: additional as BufferSource },
        key,
        plaintext as BufferSource,
    );
    return { ivB64: toB64(iv), ciphertextB64: toB64(ciphertext) };
}

/** Throws raw on any failure (wrong key, tampered blob) - callers wrap. */
export async function aesGcmDecrypt(
    key: CryptoKey,
    blob: CipherBlob,
    additional: Uint8Array,
): Promise<Uint8Array> {
    const plaintext = await subtleCrypto().decrypt(
        { name: "AES-GCM", iv: fromB64(blob.ivB64) as BufferSource, additionalData: additional as BufferSource },
        key,
        fromB64(blob.ciphertextB64) as BufferSource,
    );
    return new Uint8Array(plaintext);
}

/**
 * Calibrate PBKDF2 iterations to ~targetMs on this device, never below the floor.
 * The chosen count is stored alongside the ciphertext so decrypt uses the same cost.
 */
export async function calibratePbkdf2Iterations(
    targetMs = 750,
    minIterations = PASSPHRASE_MIN_ITERATIONS,
): Promise<number> {
    const probeIterations = 100_000;
    const salt = randomBytes(16);
    const start = performance.now();
    await deriveKekPbkdf2Sha256("calibration-probe", salt, probeIterations);
    const elapsed = performance.now() - start;
    if (elapsed <= 0) {
        return minIterations;
    }
    const scaled = Math.round((probeIterations * targetMs) / elapsed);
    return Math.max(minIterations, scaled);
}
