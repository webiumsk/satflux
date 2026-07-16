import {
    addPasskeyPrfSlot,
    createDeviceEnvelope,
    DeviceUnlockError,
    listPasskeyPrfSlots,
    removePasskeyPrfSlot,
    rotatePassphrase,
    touchPasskeyPrfSlot,
    unlockWithPassphrase,
    unlockWithPrfOutput,
    type DeviceEnvelope,
    type DeviceUnlockSlotType,
    type PasskeySlotMetadata,
} from "./envelope";
import {
    createPasskeyPrfCredential,
    evaluatePrfForSlots,
    isPasskeyPrfSupported,
} from "./passkeyPrf";
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

/**
 * Passkey PRF provider: platform-authenticator probe only - whether the
 * authenticator actually implements PRF is knowable only at create() time
 * (addPasskeyToRememberedDevice fails typed when it does not).
 */
export const passkeyPrfUnlockProvider: DeviceUnlockProvider = {
    type: "passkey-prf",
    async isSupported() {
        return isPasskeyPrfSupported();
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

/** Passkey slots on this device's envelope (empty when device not remembered). */
export async function listDevicePasskeySlots(): Promise<PasskeySlotMetadata[]> {
    const envelope = await loadDeviceEnvelope();
    return envelope ? listPasskeyPrfSlots(envelope) : [];
}

/**
 * Add a passkey unlock shortcut to an already remembered device. Requires the
 * device passphrase (proves possession + yields the DEK) - the passphrase
 * slot always stays; the passkey can only ever be an ADDITIONAL slot.
 */
export async function addPasskeyToRememberedDevice(passphrase: string, label: string): Promise<PasskeySlotMetadata[]> {
    const envelope = await loadDeviceEnvelope();
    if (!envelope) {
        throw new DeviceUnlockError();
    }
    const { dekRaw } = await unlockWithPassphrase(envelope, passphrase);
    try {
        const credential = await createPasskeyPrfCredential({
            label,
            userHandle: envelope.ownerFingerprint,
            userName: "satflux-device-unlock",
        });

        // From here the PRF secret exists - zero it on every path.
        try {
            const updated = await addPasskeyPrfSlot({
                envelope,
                dekRaw,
                prfOutput: credential.prfOutput,
                credentialIdB64: credential.credentialIdB64,
                prfSaltB64: credential.prfSaltB64,
                label,
            });

            // Test-unlock before persisting - same crash/idempotency safety
            // as rememberDeviceWithPassphrase. The unwrapped DEK is zeroed
            // immediately; the fingerprint check only needs the phrase.
            const check = await unlockWithPrfOutput(updated, credential.credentialIdB64, credential.prfOutput);
            check.dekRaw.fill(0);
            if (deriveRecoveryPublicKeyHex(check.recoveryPhrase) !== envelope.ownerFingerprint) {
                throw new DeviceUnlockError();
            }

            await saveDeviceEnvelope(updated);
            return listPasskeyPrfSlots(updated);
        } finally {
            credential.prfOutput.fill(0);
        }
    } finally {
        dekRaw.fill(0);
    }
}

/**
 * Unlock this device with a passkey: one platform prompt across all passkey
 * slots, PRF output unwraps the DEK, phrase goes to the session store and
 * the used slot gets a lastUsedAt stamp.
 */
export async function unlockDeviceWithPasskey(): Promise<{ recoveryPhrase: string }> {
    const envelope = await loadDeviceEnvelope();
    if (!envelope) {
        throw new DeviceUnlockError();
    }
    const slots = listPasskeyPrfSlots(envelope);
    if (slots.length === 0) {
        throw new DeviceUnlockError();
    }

    const { credentialIdB64, prfOutput } = await evaluatePrfForSlots(
        slots.map((slot) => ({ credentialIdB64: slot.credentialIdB64, prfSaltB64: findPrfSalt(envelope, slot.id) })),
    );

    try {
        const { recoveryPhrase, dekRaw, slotId } = await unlockWithPrfOutput(envelope, credentialIdB64, prfOutput);
        dekRaw.fill(0);

        if (deriveRecoveryPublicKeyHex(recoveryPhrase) !== envelope.ownerFingerprint) {
            throw new DeviceUnlockError();
        }

        storeAccountMnemonic(recoveryPhrase);
        await saveDeviceEnvelope(touchPasskeyPrfSlot(envelope, slotId));
        return { recoveryPhrase };
    } finally {
        prfOutput.fill(0);
    }
}

/** Remove a passkey slot; the passphrase slot(s) are untouched. */
export async function removeDevicePasskeySlot(slotId: string): Promise<PasskeySlotMetadata[]> {
    const envelope = await loadDeviceEnvelope();
    if (!envelope) {
        throw new DeviceUnlockError();
    }
    const updated = removePasskeyPrfSlot(envelope, slotId);
    await saveDeviceEnvelope(updated);
    return listPasskeyPrfSlots(updated);
}

function findPrfSalt(envelope: DeviceEnvelope, slotId: string): string {
    for (const slot of envelope.slots) {
        if (slot.id === slotId && slot.kdf.kind === "WEBAUTHN-PRF") {
            return slot.kdf.prfSaltB64;
        }
    }
    throw new DeviceUnlockError();
}

export type { DeviceEnvelope, PasskeySlotMetadata };
