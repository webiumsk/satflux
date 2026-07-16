import { describe, expect, it } from "vitest";
import {
    addPasskeyPrfSlot,
    createDeviceEnvelope,
    DeviceUnlockError,
    listPasskeyPrfSlots,
    removePasskeyPrfSlot,
    touchPasskeyPrfSlot,
    unlockWithPassphrase,
    unlockWithPrfOutput,
    type DeviceEnvelope,
} from "../services/deviceUnlock/envelope";
import { randomBytes, toB64 } from "../services/passphraseCrypto";

// Clearly fake, never a real recovery phrase.
const FAKE_PHRASE =
    "abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon art";
const OWNER_FP = "02".padEnd(64, "a");
const FAST = 1000;
const PASSPHRASE = "correct horse battery";

async function makeEnvelopeWithPasskey(): Promise<{
    envelope: DeviceEnvelope;
    credentialIdB64: string;
    prfOutput: Uint8Array;
}> {
    const base = await createDeviceEnvelope({
        recoveryPhrase: FAKE_PHRASE,
        passphrase: PASSPHRASE,
        ownerFingerprint: OWNER_FP,
        iterations: FAST,
    });
    const { dekRaw } = await unlockWithPassphrase(base, PASSPHRASE);
    const prfOutput = randomBytes(32);
    const credentialIdB64 = toB64(randomBytes(16));
    const envelope = await addPasskeyPrfSlot({
        envelope: base,
        dekRaw,
        prfOutput,
        credentialIdB64,
        prfSaltB64: toB64(randomBytes(32)),
        label: "Test passkey",
    });
    return { envelope, credentialIdB64, prfOutput };
}

describe("passkey PRF envelope slot", () => {
    it("unlocks the same phrase via the PRF output without touching passphrase slots", async () => {
        const { envelope, credentialIdB64, prfOutput } = await makeEnvelopeWithPasskey();

        // Both unlock paths coexist on the same envelope.
        const viaPrf = await unlockWithPrfOutput(envelope, credentialIdB64, prfOutput);
        expect(viaPrf.recoveryPhrase).toBe(FAKE_PHRASE);
        const viaPassphrase = await unlockWithPassphrase(envelope, PASSPHRASE);
        expect(viaPassphrase.recoveryPhrase).toBe(FAKE_PHRASE);

        // The passphrase slot is mandatory and untouched.
        expect(envelope.slots.filter((s) => s.type === "passphrase")).toHaveLength(1);
        expect(envelope.slots.filter((s) => s.type === "passkey-prf")).toHaveLength(1);
    });

    it("rejects a wrong PRF output and an unknown credential with the generic error", async () => {
        const { envelope, credentialIdB64 } = await makeEnvelopeWithPasskey();

        await expect(unlockWithPrfOutput(envelope, credentialIdB64, randomBytes(32))).rejects.toThrow(
            DeviceUnlockError,
        );
        await expect(unlockWithPrfOutput(envelope, toB64(randomBytes(16)), randomBytes(32))).rejects.toThrow(
            DeviceUnlockError,
        );
    });

    it("supports multiple passkey slots (multi-device) unlocking the same phrase", async () => {
        const { envelope, credentialIdB64, prfOutput } = await makeEnvelopeWithPasskey();
        const { dekRaw } = await unlockWithPassphrase(envelope, PASSPHRASE);
        const secondOutput = randomBytes(32);
        const secondId = toB64(randomBytes(16));
        const twoSlots = await addPasskeyPrfSlot({
            envelope,
            dekRaw,
            prfOutput: secondOutput,
            credentialIdB64: secondId,
            prfSaltB64: toB64(randomBytes(32)),
            label: "Second device",
        });

        expect((await unlockWithPrfOutput(twoSlots, credentialIdB64, prfOutput)).recoveryPhrase).toBe(FAKE_PHRASE);
        expect((await unlockWithPrfOutput(twoSlots, secondId, secondOutput)).recoveryPhrase).toBe(FAKE_PHRASE);
        expect(listPasskeyPrfSlots(twoSlots).map((s) => s.label)).toEqual(["Test passkey", "Second device"]);
    });

    it("removes only passkey slots and stamps lastUsedAt on touch", async () => {
        const { envelope, credentialIdB64, prfOutput } = await makeEnvelopeWithPasskey();
        const slot = listPasskeyPrfSlots(envelope)[0]!;
        expect(slot.lastUsedAt).toBeNull();

        const touched = touchPasskeyPrfSlot(envelope, slot.id);
        expect(listPasskeyPrfSlots(touched)[0]!.lastUsedAt).toBeTruthy();
        // Touch must not break unlockability.
        expect((await unlockWithPrfOutput(touched, credentialIdB64, prfOutput)).recoveryPhrase).toBe(FAKE_PHRASE);

        const removed = removePasskeyPrfSlot(touched, slot.id);
        expect(listPasskeyPrfSlots(removed)).toHaveLength(0);
        // Passphrase slot survives removal - the root path can never be lost here.
        expect(removed.slots.filter((s) => s.type === "passphrase")).toHaveLength(1);
        expect((await unlockWithPassphrase(removed, PASSPHRASE)).recoveryPhrase).toBe(FAKE_PHRASE);
        // Removing a passphrase slot id through the passkey remover is a no-op.
        const passphraseSlotId = removed.slots[0]!.id;
        expect(removePasskeyPrfSlot(removed, passphraseSlotId).slots).toHaveLength(1);
    });

    it("keeps the envelope readable by passphrase-only (old build) logic", async () => {
        const { envelope } = await makeEnvelopeWithPasskey();
        // Old builds filter type === 'passphrase' and ignore unknown slots -
        // simulated here by the passphrase unlock on the mixed envelope.
        const { recoveryPhrase } = await unlockWithPassphrase(envelope, PASSPHRASE);
        expect(recoveryPhrase).toBe(FAKE_PHRASE);
        expect(envelope.version).toBe(1);
    });
});
