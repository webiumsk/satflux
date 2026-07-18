import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { DeviceEnvelope } from "../services/deviceUnlock/envelope";
import { randomBytes, toB64 } from "../services/passphraseCrypto";

// Valid BIP39 test vector (24 words) - clearly fake, never a real phrase.
const FAKE_PHRASE =
    "abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon art";
const SESSION_KEY = "satflux.account.mnemonic.v1";
const PASSPHRASE = "correct horse battery staple";

// Real PBKDF2 calibration targets wall-clock time (600k+ iterations) and
// times the suite out under parallel load - pin a fast iteration count.
vi.mock("../services/passphraseCrypto", async (importOriginal) => {
    const original = await importOriginal<typeof import("../services/passphraseCrypto")>();
    return { ...original, calibratePbkdf2Iterations: vi.fn(async () => 1000) };
});

// The cloud-envelope layer talks to axios - keep the provider tests hermetic.
vi.mock("../services/deviceUnlock/accountPasskeyEnvelope", async (importOriginal) => {
    const original = await importOriginal<typeof import("../services/deviceUnlock/accountPasskeyEnvelope")>();
    return {
        ...original,
        putAccountEnvelope: vi.fn(async () => {}),
        listAccountEnvelopes: vi.fn(async () => []),
        fetchEnvelopeForLogin: vi.fn(async () => {
            throw new Error("not stubbed");
        }),
    };
});

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

// WebAuthn stand-in: a deterministic PRF secret per credential id.
const credentialId = randomBytes(16);
const prfSecret = randomBytes(32);

vi.mock("../services/deviceUnlock/passkeyPrf", async (importOriginal) => {
    const original = await importOriginal<typeof import("../services/deviceUnlock/passkeyPrf")>();
    return {
        ...original,
        isPasskeyPrfSupported: vi.fn(async () => true),
        createPasskeyPrfCredential: vi.fn(async () => ({
            credentialIdB64: toB64(credentialId),
            prfSaltB64: toB64(randomBytes(32)),
            prfOutput: new Uint8Array(prfSecret),
        })),
        evaluatePrfForSlots: vi.fn(async (slots: Array<{ credentialIdB64: string }>) => ({
            credentialIdB64: slots[0]!.credentialIdB64,
            prfOutput: new Uint8Array(prfSecret),
        })),
    };
});

describe("passkey unlock provider", () => {
    beforeEach(() => {
        stored = null;
        sessionStorage.clear();
        localStorage.clear();
    });

    afterEach(() => {
        sessionStorage.clear();
        localStorage.clear();
    });

    async function rememberedDevice() {
        const provider = await import("../services/deviceUnlock/provider");
        await provider.rememberDeviceWithPassphrase(FAKE_PHRASE, PASSPHRASE);
        sessionStorage.clear();
        return provider;
    }

    it("adds a passkey slot (passphrase required) and unlocks with it", async () => {
        const provider = await rememberedDevice();

        const { slots, cloudSynced } = await provider.addPasskeyToRememberedDevice(PASSPHRASE, "Test laptop");
        expect(cloudSynced).toBe(true);
        expect(slots).toHaveLength(1);
        expect(slots[0]!.label).toBe("Test laptop");
        expect(slots[0]!.lastUsedAt).toBeNull();

        const { recoveryPhrase } = await provider.unlockDeviceWithPasskey();
        expect(recoveryPhrase).toBe(FAKE_PHRASE);
        expect(sessionStorage.getItem(SESSION_KEY)).toBe(FAKE_PHRASE);

        // Unlock stamped lastUsedAt.
        const after = await provider.listDevicePasskeySlots();
        expect(after[0]!.lastUsedAt).toBeTruthy();
    });

    it("returns cloudSynced=false when the envelope upload fails, local unlock intact", async () => {
        const provider = await rememberedDevice();
        const accountEnvelope = await import("../services/deviceUnlock/accountPasskeyEnvelope");
        vi.mocked(accountEnvelope.putAccountEnvelope).mockRejectedValueOnce(new Error("offline"));

        const { slots, cloudSynced } = await provider.addPasskeyToRememberedDevice(PASSPHRASE, "Test laptop");
        expect(cloudSynced).toBe(false);
        expect(slots).toHaveLength(1);

        // Best-effort means the local slot still unlocks this device.
        const { recoveryPhrase } = await provider.unlockDeviceWithPasskey();
        expect(recoveryPhrase).toBe(FAKE_PHRASE);
    });

    it("refuses to add a passkey with a wrong passphrase", async () => {
        const provider = await rememberedDevice();
        const { DeviceUnlockError } = await import("../services/deviceUnlock/envelope");

        await expect(provider.addPasskeyToRememberedDevice("wrong-passphrase-entirely", "X")).rejects.toThrow(
            DeviceUnlockError,
        );
        expect(await provider.listDevicePasskeySlots()).toHaveLength(0);
    });

    it("removing the passkey slot keeps the passphrase unlock working", async () => {
        const provider = await rememberedDevice();
        const { slots } = await provider.addPasskeyToRememberedDevice(PASSPHRASE, "Test laptop");

        const remaining = await provider.removeDevicePasskeySlot(slots[0]!.id);
        expect(remaining).toHaveLength(0);

        const { DeviceUnlockError } = await import("../services/deviceUnlock/envelope");
        await expect(provider.unlockDeviceWithPasskey()).rejects.toThrow(DeviceUnlockError);
        // The root path survives.
        const { recoveryPhrase } = await provider.unlockDeviceWithPassphrase(PASSPHRASE);
        expect(recoveryPhrase).toBe(FAKE_PHRASE);
    });

    it("unlock fails generically when the device is not remembered", async () => {
        const provider = await import("../services/deviceUnlock/provider");
        const { DeviceUnlockError } = await import("../services/deviceUnlock/envelope");
        await expect(provider.unlockDeviceWithPasskey()).rejects.toThrow(DeviceUnlockError);
    });
});
