import { describe, expect, it } from "vitest";
import {
    addPassphraseSlot,
    createDeviceEnvelope,
    DeviceUnlockError,
    ENVELOPE_VERSION,
    isAcceptableDevicePassphrase,
    rotatePassphrase,
    unlockWithPassphrase,
    WeakPassphraseError,
    type DeviceEnvelope,
} from "../services/deviceUnlock/envelope";

// Clearly fake, never a real recovery phrase.
const FAKE_PHRASE =
    "abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon art";
const OWNER_FP = "02".padEnd(64, "a");
const OTHER_FP = "03".padEnd(64, "b");
// Small iteration count keeps the crypto tests fast; production calibrates >= 600k.
const FAST = 1000;

async function makeEnvelope(passphrase = "correct horse battery"): Promise<DeviceEnvelope> {
    return createDeviceEnvelope({
        recoveryPhrase: FAKE_PHRASE,
        passphrase,
        ownerFingerprint: OWNER_FP,
        iterations: FAST,
    });
}

describe("device unlock envelope", () => {
    it("round-trips the recovery phrase with the correct passphrase", async () => {
        const envelope = await makeEnvelope();
        expect(envelope.version).toBe(ENVELOPE_VERSION);
        expect(envelope.ownerFingerprint).toBe(OWNER_FP);
        // The plaintext phrase never appears in the serialized envelope.
        expect(JSON.stringify(envelope)).not.toContain("abandon");

        const { recoveryPhrase } = await unlockWithPassphrase(envelope, "correct horse battery");
        expect(recoveryPhrase).toBe(FAKE_PHRASE);
    });

    it("rejects a wrong passphrase with the generic error", async () => {
        const envelope = await makeEnvelope();
        await expect(unlockWithPassphrase(envelope, "wrong passphrase")).rejects.toBeInstanceOf(DeviceUnlockError);
    });

    it("fails to decrypt a tampered owner fingerprint (AAD binding)", async () => {
        const envelope = await makeEnvelope();
        const tampered: DeviceEnvelope = { ...envelope, ownerFingerprint: OTHER_FP };
        await expect(unlockWithPassphrase(tampered, "correct horse battery")).rejects.toBeInstanceOf(DeviceUnlockError);
    });

    it("fails on a corrupt / truncated ciphertext", async () => {
        const envelope = await makeEnvelope();
        const corrupt: DeviceEnvelope = {
            ...envelope,
            encryptedSecret: { ...envelope.encryptedSecret, ciphertextB64: "AAAA" },
        };
        await expect(unlockWithPassphrase(corrupt, "correct horse battery")).rejects.toBeInstanceOf(DeviceUnlockError);
    });

    it("rejects an unknown envelope version", async () => {
        const envelope = await makeEnvelope();
        const future = { ...envelope, version: 99 } as unknown as DeviceEnvelope;
        await expect(unlockWithPassphrase(future, "correct horse battery")).rejects.toBeInstanceOf(DeviceUnlockError);
    });

    it("rejects a short numeric PIN as a device passphrase", async () => {
        expect(isAcceptableDevicePassphrase("1234")).toBe(false);
        expect(isAcceptableDevicePassphrase("12345678")).toBe(false);
        expect(isAcceptableDevicePassphrase("correct horse battery")).toBe(true);
        await expect(
            createDeviceEnvelope({
                recoveryPhrase: FAKE_PHRASE,
                passphrase: "1234",
                ownerFingerprint: OWNER_FP,
                iterations: FAST,
            }),
        ).rejects.toBeInstanceOf(WeakPassphraseError);
    });

    it("supports two independent passphrase slots unlocking the same phrase", async () => {
        const envelope = await makeEnvelope("first device passphrase");
        const { dekRaw } = await unlockWithPassphrase(envelope, "first device passphrase");
        const withSecond = await addPassphraseSlot({
            envelope,
            dekRaw,
            passphrase: "second device passphrase",
            iterations: FAST,
        });

        expect(withSecond.slots).toHaveLength(2);
        expect((await unlockWithPassphrase(withSecond, "first device passphrase")).recoveryPhrase).toBe(FAKE_PHRASE);
        expect((await unlockWithPassphrase(withSecond, "second device passphrase")).recoveryPhrase).toBe(FAKE_PHRASE);
    });

    it("rotates the passphrase without changing the recovery phrase or DEK ciphertext", async () => {
        const envelope = await makeEnvelope("old passphrase words");
        const rotated = await rotatePassphrase({
            envelope,
            oldPassphrase: "old passphrase words",
            newPassphrase: "new passphrase words",
            iterations: FAST,
        });

        // Same encrypted phrase blob (DEK unchanged), new slot wrapping.
        expect(rotated.encryptedSecret).toEqual(envelope.encryptedSecret);
        expect((await unlockWithPassphrase(rotated, "new passphrase words")).recoveryPhrase).toBe(FAKE_PHRASE);
        await expect(unlockWithPassphrase(rotated, "old passphrase words")).rejects.toBeInstanceOf(DeviceUnlockError);
    });

    it("rejects rotation when the old passphrase is wrong", async () => {
        const envelope = await makeEnvelope("real passphrase words");
        await expect(
            rotatePassphrase({
                envelope,
                oldPassphrase: "not the passphrase",
                newPassphrase: "brand new passphrase",
                iterations: FAST,
            }),
        ).rejects.toBeInstanceOf(DeviceUnlockError);
    });

    it("uses a fresh nonce per encryption (envelope secret iv != slot iv)", async () => {
        const envelope = await makeEnvelope();
        expect(envelope.encryptedSecret.ivB64).not.toBe(envelope.slots[0].wrappedDek.ivB64);
    });
});
