import {
    createDeviceEnvelope,
    DeviceUnlockError,
    rotatePassphrase,
    unlockWithPassphrase,
    type DeviceEnvelope,
    type DeviceUnlockSlotType,
} from "./envelope";
import {
    clearDeviceEnvelope,
    hasDeviceEnvelope,
    loadDeviceEnvelope,
    saveDeviceEnvelope,
} from "./deviceEnvelopeStore";
import {
    deriveRecoveryPublicKeyHex,
    normalizeAccountMnemonic,
    storeAccountMnemonic,
} from "../accountSeed";

/**
 * Device unlock abstraction. Today only the passphrase provider exists; a
 * PasskeyPrfUnlockProvider can be added as another slot type later WITHOUT a
 * new recovery seed or re-encrypting the dataset (see envelope.ts key-slot
 * design). Passkey PRF must go through runtime capability detection and the
 * standard PRF extension - never a WebAuthn signature used as an ad-hoc KDF.
 */
export interface DeviceUnlockProvider {
    readonly type: DeviceUnlockSlotType;
    isSupported(): Promise<boolean>;
}

export const passphraseUnlockProvider: DeviceUnlockProvider = {
    type: "passphrase",
    async isSupported() {
        return typeof globalThis.crypto?.subtle !== "undefined";
    },
};

/** Runtime capability probe for the future passkey PRF slot (not yet implemented). */
export const passkeyPrfUnlockProvider: DeviceUnlockProvider = {
    type: "passkey-prf",
    async isSupported() {
        return false;
    },
};

export async function isDeviceRemembered(): Promise<boolean> {
    return hasDeviceEnvelope();
}

/**
 * Opt-in "remember this device": wrap the recovery phrase in a passphrase
 * envelope and persist it. Verifies by test-decrypt that the envelope restores
 * the same owner fingerprint before it is stored, then puts the phrase in the
 * session so the current tab is unlocked.
 */
export async function rememberDeviceWithPassphrase(recoveryPhrase: string, passphrase: string): Promise<void> {
    const normalized = normalizeAccountMnemonic(recoveryPhrase);
    const ownerFingerprint = deriveRecoveryPublicKeyHex(normalized);

    const envelope = await createDeviceEnvelope({ recoveryPhrase: normalized, passphrase, ownerFingerprint });

    // Test-decrypt before persisting: never store an envelope that cannot
    // reproduce the expected owner (crash/idempotency safety).
    const check = await unlockWithPassphrase(envelope, passphrase);
    if (deriveRecoveryPublicKeyHex(check.recoveryPhrase) !== ownerFingerprint) {
        throw new DeviceUnlockError();
    }
    check.dekRaw.fill(0);

    await saveDeviceEnvelope(envelope);
    storeAccountMnemonic(normalized);
}

/**
 * Unlock this device with the passphrase. On success the phrase is placed in
 * the session-only store; the caller then boots Evolu. The owner fingerprint is
 * re-verified from the decrypted phrase before it is trusted.
 */
export async function unlockDeviceWithPassphrase(passphrase: string): Promise<{ recoveryPhrase: string }> {
    const envelope = await loadDeviceEnvelope();
    if (!envelope) {
        throw new DeviceUnlockError();
    }
    const { recoveryPhrase, dekRaw } = await unlockWithPassphrase(envelope, passphrase);
    dekRaw.fill(0);

    if (deriveRecoveryPublicKeyHex(recoveryPhrase) !== envelope.ownerFingerprint) {
        throw new DeviceUnlockError();
    }
    storeAccountMnemonic(recoveryPhrase);
    return { recoveryPhrase };
}

/** Change the device passphrase; the recovery phrase and DEK are untouched. */
export async function changeDevicePassphrase(oldPassphrase: string, newPassphrase: string): Promise<void> {
    const envelope = await loadDeviceEnvelope();
    if (!envelope) {
        throw new DeviceUnlockError();
    }
    const rotated = await rotatePassphrase({ envelope, oldPassphrase, newPassphrase });
    await saveDeviceEnvelope(rotated);
}

/** "Forget this device": drop the encrypted envelope. Does not touch the session. */
export async function forgetDevice(): Promise<void> {
    await clearDeviceEnvelope();
}

export type { DeviceEnvelope };
