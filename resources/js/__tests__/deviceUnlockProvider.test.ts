import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { DeviceEnvelope } from "../services/deviceUnlock/envelope";

// Valid BIP39 test vector (24 words) - clearly fake, never a real phrase.
const FAKE_PHRASE =
    "abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon art";
const WRONG_PHRASE =
    "legal winner thank year wave sausage worth useful legal winner thank year wave sausage worth useful legal winner thank year wave sausage worth title";
const SESSION_KEY = "satflux.account.mnemonic.v1";

// In-memory stand-in for the IndexedDB-backed envelope store.
let stored: DeviceEnvelope | null = null;

vi.mock("../services/deviceUnlock/deviceEnvelopeStore", () => ({
    loadDeviceEnvelope: vi.fn(async () => stored),
    saveDeviceEnvelope: vi.fn(async (e: DeviceEnvelope) => {
        stored = e;
    }),
    clearDeviceEnvelope: vi.fn(async () => {
        stored = null;
    }),
    hasDeviceEnvelope: vi.fn(async () => stored !== null),
}));

describe("device unlock provider", () => {
    beforeEach(() => {
        stored = null;
        sessionStorage.clear();
        localStorage.clear();
    });

    afterEach(() => {
        sessionStorage.clear();
        localStorage.clear();
    });

    it("remembers the device and unlocks with the correct passphrase", async () => {
        const { rememberDeviceWithPassphrase, unlockDeviceWithPassphrase, isDeviceRemembered } = await import(
            "../services/deviceUnlock/provider"
        );

        await rememberDeviceWithPassphrase(FAKE_PHRASE, "correct horse battery staple");
        expect(await isDeviceRemembered()).toBe(true);
        // Remembering unlocks the current session.
        expect(sessionStorage.getItem(SESSION_KEY)).toBe(FAKE_PHRASE);
        // No plaintext phrase persisted anywhere in the stored envelope.
        expect(JSON.stringify(stored)).not.toContain("abandon");

        sessionStorage.clear();
        const { recoveryPhrase } = await unlockDeviceWithPassphrase("correct horse battery staple");
        expect(recoveryPhrase).toBe(FAKE_PHRASE);
        expect(sessionStorage.getItem(SESSION_KEY)).toBe(FAKE_PHRASE);
    }, 20000);

    it("rejects the wrong passphrase and leaves the session locked", async () => {
        const { rememberDeviceWithPassphrase, unlockDeviceWithPassphrase } = await import(
            "../services/deviceUnlock/provider"
        );
        await rememberDeviceWithPassphrase(FAKE_PHRASE, "correct horse battery staple");
        sessionStorage.clear();

        await expect(unlockDeviceWithPassphrase("wrong passphrase entirely")).rejects.toThrow();
        expect(sessionStorage.getItem(SESSION_KEY)).toBeNull();
    }, 20000);

    it("forget-device removes the envelope", async () => {
        const { rememberDeviceWithPassphrase, forgetDevice, isDeviceRemembered } = await import(
            "../services/deviceUnlock/provider"
        );
        await rememberDeviceWithPassphrase(FAKE_PHRASE, "correct horse battery staple");
        await forgetDevice();
        expect(await isDeviceRemembered()).toBe(false);
    }, 20000);

    it("changes the device passphrase without changing the recovery phrase", async () => {
        const { rememberDeviceWithPassphrase, changeDevicePassphrase, unlockDeviceWithPassphrase } = await import(
            "../services/deviceUnlock/provider"
        );
        await rememberDeviceWithPassphrase(FAKE_PHRASE, "old device passphrase");
        await changeDevicePassphrase("old device passphrase", "new device passphrase");
        sessionStorage.clear();

        await expect(unlockDeviceWithPassphrase("old device passphrase")).rejects.toThrow();
        const { recoveryPhrase } = await unlockDeviceWithPassphrase("new device passphrase");
        expect(recoveryPhrase).toBe(FAKE_PHRASE);
    }, 30000);

    it("rejects an envelope whose owner fingerprint does not match the decrypted phrase", async () => {
        const { createDeviceEnvelope } = await import("../services/deviceUnlock/envelope");
        const { unlockDeviceWithPassphrase } = await import("../services/deviceUnlock/provider");
        const { deriveRecoveryPublicKeyHex } = await import("../services/accountSeed");

        // Build an envelope that wraps WRONG_PHRASE but claims FAKE_PHRASE's owner.
        stored = await createDeviceEnvelope({
            recoveryPhrase: WRONG_PHRASE,
            passphrase: "correct horse battery staple",
            ownerFingerprint: deriveRecoveryPublicKeyHex(FAKE_PHRASE),
            iterations: 1000,
        });

        // AAD binds the fingerprint, so the wrapped-phrase decrypt already fails.
        await expect(unlockDeviceWithPassphrase("correct horse battery staple")).rejects.toThrow();
        expect(sessionStorage.getItem(SESSION_KEY)).toBeNull();
    });
});
