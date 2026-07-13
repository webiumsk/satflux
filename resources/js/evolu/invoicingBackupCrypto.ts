import { wordlist } from "@scure/bip39/wordlists/english.js";
import {
    PASSPHRASE_MAX_ITERATIONS,
    aesGcmDecrypt,
    aesGcmEncrypt,
    calibratePbkdf2Iterations,
    deriveKekPbkdf2Sha256,
    fromB64,
    randomBytes,
    toB64,
} from "@/services/passphraseCrypto";

/**
 * Optional backup encryption (P2 phase 2). The ENCRYPTED file wraps the
 * complete plaintext backup envelope (format satflux-invoicing-backup v1,
 * including its own SHA-256 integrity hash) as an AES-256-GCM ciphertext
 * under a PBKDF2-HMAC-SHA-256 key - the same audited primitives as the
 * device unlock envelope (services/passphraseCrypto.ts).
 *
 * Deliberately NOT bound to the owner: a backup must survive the loss of the
 * recovery phrase and be restorable into a fresh account, so the AAD carries
 * only the format context. Everything sensitive (invoices, contacts, the
 * owner id hash) lives INSIDE the ciphertext - the outer envelope reveals
 * only that it is a satflux backup and when it was created.
 *
 * There is no passphrase recovery: GCM decryption with a wrong passphrase
 * fails authentication. The UI must warn that a lost passphrase means a
 * lost backup.
 */

export const ENCRYPTED_BACKUP_FORMAT = "satflux-invoicing-backup-encrypted";
export const ENCRYPTED_BACKUP_VERSION = 1;

const AAD_CONTEXT = `${ENCRYPTED_BACKUP_FORMAT}.v${ENCRYPTED_BACKUP_VERSION}`;

export type EncryptedBackupEnvelope = {
    format: typeof ENCRYPTED_BACKUP_FORMAT;
    version: typeof ENCRYPTED_BACKUP_VERSION;
    created_at: string;
    kdf: { kind: "PBKDF2-SHA256"; saltB64: string; iterations: number };
    cipher: { kind: "AES-256-GCM"; ivB64: string; ciphertextB64: string };
};

function aad(): Uint8Array {
    return new TextEncoder().encode(AAD_CONTEXT);
}

export function isEncryptedBackupEnvelope(parsed: unknown): parsed is EncryptedBackupEnvelope {
    if (!parsed || typeof parsed !== "object") return false;
    const candidate = parsed as Partial<EncryptedBackupEnvelope>;
    return (
        candidate.format === ENCRYPTED_BACKUP_FORMAT
        && typeof candidate.kdf === "object"
        && candidate.kdf !== null
        && typeof candidate.cipher === "object"
        && candidate.cipher !== null
    );
}

/**
 * Encrypt the serialized plaintext backup envelope. Iterations are calibrated
 * to this device (never below the OWASP floor) unless the caller pins them.
 */
export async function encryptBackupEnvelopeText(
    plaintextEnvelopeJson: string,
    passphrase: string,
    options: { now?: Date; iterations?: number } = {},
): Promise<EncryptedBackupEnvelope> {
    const iterations = options.iterations ?? (await calibratePbkdf2Iterations());
    const salt = randomBytes(16);
    const key = await deriveKekPbkdf2Sha256(passphrase, salt, iterations);
    const blob = await aesGcmEncrypt(key, new TextEncoder().encode(plaintextEnvelopeJson), aad());
    return {
        format: ENCRYPTED_BACKUP_FORMAT,
        version: ENCRYPTED_BACKUP_VERSION,
        created_at: (options.now ?? new Date()).toISOString(),
        kdf: { kind: "PBKDF2-SHA256", saltB64: toB64(salt), iterations },
        cipher: { kind: "AES-256-GCM", ivB64: blob.ivB64, ciphertextB64: blob.ciphertextB64 },
    };
}

/**
 * Decrypt back to the plaintext envelope JSON. Every failure - wrong
 * passphrase, tampered file, unsupported version, missing WebCrypto - throws
 * the same non-distinguishing "backup_decrypt_failed".
 */
export async function decryptBackupEnvelopeText(
    encrypted: EncryptedBackupEnvelope,
    passphrase: string,
): Promise<string> {
    try {
        if (encrypted.version !== ENCRYPTED_BACKUP_VERSION) {
            throw new Error("unsupported");
        }
        // The file is untrusted: a hostile iteration count must not dictate
        // minutes of key derivation. (A low count only weakens the attacker's
        // own file, so only the upper bound is a safety property.)
        const iterations = encrypted.kdf.iterations;
        if (!Number.isInteger(iterations) || iterations < 1 || iterations > PASSPHRASE_MAX_ITERATIONS) {
            throw new Error("unsupported");
        }
        const key = await deriveKekPbkdf2Sha256(
            passphrase,
            fromB64(encrypted.kdf.saltB64),
            iterations,
        );
        const plaintext = await aesGcmDecrypt(
            key,
            { ivB64: encrypted.cipher.ivB64, ciphertextB64: encrypted.cipher.ciphertextB64 },
            aad(),
        );
        return new TextDecoder().decode(plaintext);
    } catch {
        throw new Error("backup_decrypt_failed");
    }
}

export const BACKUP_PASSPHRASE_WORD_COUNT = 6;

/**
 * Generate a memorable-but-strong passphrase: 6 random BIP-39 words
 * (11 bits each = 66 bits of entropy - far beyond online guessing and
 * strong enough against offline PBKDF2-throttled brute force). Sampling is
 * uniform: 2048 divides 65536, so the modulo introduces no bias.
 */
export function generateBackupPassphrase(): string {
    const indexes = new Uint16Array(BACKUP_PASSPHRASE_WORD_COUNT);
    globalThis.crypto.getRandomValues(indexes);
    const words: string[] = [];
    for (const value of indexes) {
        words.push(wordlist[value % wordlist.length]);
    }
    return words.join(" ");
}
