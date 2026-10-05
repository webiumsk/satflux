import { beforeEach, describe, expect, it, vi } from "vitest";
import { DeviceUnlockError, RecoveryOwnerMismatchError } from "../services/deviceUnlock/envelope";
import { randomBytes } from "../services/passphraseCrypto";
import { deriveRecoveryPublicKeyHex } from "../services/accountSeed";

// Valid BIP39 test vector (24 words) - clearly fake, never a real phrase.
const FAKE_PHRASE =
    "abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon art";
const SESSION_KEY = "satflux.account.mnemonic.v1";

const apiMock = vi.hoisted(() => ({
    get: vi.fn(),
    put: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
}));
vi.mock("../services/api", () => ({ default: apiMock }));

// WebAuthn stand-in: deterministic PRF secret per credential.
const credentialId = randomBytes(16);
const prfSecret = randomBytes(32);

vi.mock("../services/deviceUnlock/passkeyPrf", async (importOriginal) => {
    const original = await importOriginal<typeof import("../services/deviceUnlock/passkeyPrf")>();
    const { toB64 } = await import("../services/passphraseCrypto");
    return {
        ...original,
        isPasskeyPrfSupported: vi.fn(async () => true),
        evaluatePrfDiscoverable: vi.fn(async () => ({
            credentialIdB64: toB64(credentialId),
            prfOutput: new Uint8Array(prfSecret),
        })),
        evaluatePrf: vi.fn(async () => new Uint8Array(prfSecret)),
        createPasskeyPrfCredential: vi.fn(async (params: { prfSaltB64?: string }) => ({
            credentialIdB64: toB64(credentialId),
            prfSaltB64: params.prfSaltB64 ?? "unused",
            prfOutput: new Uint8Array(prfSecret),
        })),
        evaluatePrfForSlots: vi.fn(async () => ({
            credentialIdB64: (await import("../services/passphraseCrypto")).toB64(credentialId),
            prfOutput: new Uint8Array(prfSecret),
        })),
    };
});

describe("account passkey envelope", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        sessionStorage.clear();
        apiMock.get.mockResolvedValue({ data: { recovery_public_key: deriveRecoveryPublicKeyHex(FAKE_PHRASE) } });
    });

    it("round-trips the phrase under the PRF-derived key", async () => {
        const { encryptAccountEnvelope, decryptAccountEnvelope } = await import(
            "../services/deviceUnlock/accountPasskeyEnvelope"
        );
        const payload = await encryptAccountEnvelope(FAKE_PHRASE, new Uint8Array(prfSecret));

        expect(JSON.parse(payload).v).toBe(1);
        await expect(decryptAccountEnvelope(payload, new Uint8Array(prfSecret))).resolves.toBe(FAKE_PHRASE);
    });

    it("fails generically for a wrong PRF output or a tampered payload", async () => {
        const { encryptAccountEnvelope, decryptAccountEnvelope } = await import(
            "../services/deviceUnlock/accountPasskeyEnvelope"
        );
        const payload = await encryptAccountEnvelope(FAKE_PHRASE, new Uint8Array(prfSecret));

        await expect(decryptAccountEnvelope(payload, randomBytes(32))).rejects.toThrow(DeviceUnlockError);

        const parsed = JSON.parse(payload);
        parsed.ciphertextB64 = parsed.ciphertextB64.slice(0, -4) + "AAAA";
        await expect(
            decryptAccountEnvelope(JSON.stringify(parsed), new Uint8Array(prfSecret)),
        ).rejects.toThrow(DeviceUnlockError);

        await expect(decryptAccountEnvelope("not-json", new Uint8Array(prfSecret))).rejects.toThrow(
            DeviceUnlockError,
        );
    });

    it("refuses to encrypt anything that is not a valid mnemonic", async () => {
        const { encryptAccountEnvelope } = await import("../services/deviceUnlock/accountPasskeyEnvelope");
        await expect(encryptAccountEnvelope("definitely not a phrase", randomBytes(32))).rejects.toThrow(
            DeviceUnlockError,
        );
    });

    it("signs in with one discoverable gesture: fetch, decrypt, return the phrase", async () => {
        const { encryptAccountEnvelope } = await import("../services/deviceUnlock/accountPasskeyEnvelope");
        const payload = await encryptAccountEnvelope(FAKE_PHRASE, new Uint8Array(prfSecret));
        apiMock.post.mockResolvedValue({ data: { data: { payload } } });

        const { loginWithAccountPasskey } = await import("../services/deviceUnlock/provider");
        const { recoveryPhrase } = await loginWithAccountPasskey();

        expect(recoveryPhrase).toBe(FAKE_PHRASE);
        expect(apiMock.post).toHaveBeenCalledWith("/auth/passkey/envelope", expect.anything());
    });

    it("sign-in fails generically when the server has no envelope", async () => {
        apiMock.post.mockRejectedValue(new Error("404"));

        const { loginWithAccountPasskey } = await import("../services/deviceUnlock/provider");
        await expect(loginWithAccountPasskey()).rejects.toThrow(DeviceUnlockError);
    });

    it("adds an account passkey from the session phrase and uploads the envelope", async () => {
        sessionStorage.setItem(SESSION_KEY, FAKE_PHRASE);
        apiMock.put.mockResolvedValue({ data: {} });

        const { addAccountPasskeyFromSession } = await import("../services/deviceUnlock/provider");
        await addAccountPasskeyFromSession("Test laptop");

        expect(apiMock.put).toHaveBeenCalledTimes(1);
        const [url, body] = apiMock.put.mock.calls[0]!;
        expect(String(url)).toContain("/account/passkey-envelopes/");
        expect(body.recovery_public_key).toBe(deriveRecoveryPublicKeyHex(FAKE_PHRASE));
        // The uploaded payload must decrypt back with the same PRF secret.
        const { decryptAccountEnvelope } = await import("../services/deviceUnlock/accountPasskeyEnvelope");
        await expect(decryptAccountEnvelope(body.payload, new Uint8Array(prfSecret))).resolves.toBe(FAKE_PHRASE);
    });

    it("refuses to add an account passkey without the session phrase", async () => {
        const { addAccountPasskeyFromSession } = await import("../services/deviceUnlock/provider");
        await expect(addAccountPasskeyFromSession("X")).rejects.toThrow(DeviceUnlockError);
        expect(apiMock.put).not.toHaveBeenCalled();
    });

    it("refuses to create or promote a passkey when the unlocked phrase belongs to another account", async () => {
        sessionStorage.setItem(SESSION_KEY, FAKE_PHRASE);
        apiMock.get.mockResolvedValue({ data: { recovery_public_key: "b".repeat(64) } });
        const { addAccountPasskeyFromSession, upgradeAccountPasskey } = await import("../services/deviceUnlock/provider");
        const { createPasskeyPrfCredential, evaluatePrf } = await import("../services/deviceUnlock/passkeyPrf");

        await expect(addAccountPasskeyFromSession("Wrong owner")).rejects.toThrow(RecoveryOwnerMismatchError);
        await expect(upgradeAccountPasskey("credential", "Wrong owner")).rejects.toThrow(RecoveryOwnerMismatchError);
        expect(createPasskeyPrfCredential).not.toHaveBeenCalled();
        expect(evaluatePrf).not.toHaveBeenCalled();
        expect(apiMock.put).not.toHaveBeenCalled();
    });

    it("rejects a previously misfiled cloud envelope while restoring a signed-in account", async () => {
        const { encryptAccountEnvelope } = await import("../services/deviceUnlock/accountPasskeyEnvelope");
        const payload = await encryptAccountEnvelope(FAKE_PHRASE, new Uint8Array(prfSecret));
        apiMock.post.mockResolvedValue({ data: { data: { payload } } });
        apiMock.get.mockResolvedValue({ data: { recovery_public_key: "b".repeat(64) } });
        const { restoreWithAccountPasskey } = await import("../services/deviceUnlock/provider");

        await expect(restoreWithAccountPasskey([{
            credential_id: "dGVzdC1jcmVkZW50aWFs", label: null, created_at: "", last_used_at: null,
        }])).rejects.toThrow(RecoveryOwnerMismatchError);
        expect(sessionStorage.getItem(SESSION_KEY)).toBeNull();
    });

    it("restores a cloud envelope that belongs to the signed-in recovery owner", async () => {
        const { encryptAccountEnvelope } = await import("../services/deviceUnlock/accountPasskeyEnvelope");
        const payload = await encryptAccountEnvelope(FAKE_PHRASE, new Uint8Array(prfSecret));
        apiMock.post.mockResolvedValue({ data: { data: { payload } } });
        const { restoreWithAccountPasskey } = await import("../services/deviceUnlock/provider");

        await expect(restoreWithAccountPasskey([{
            credential_id: "dGVzdC1jcmVkZW50aWFs", label: null, created_at: "", last_used_at: null,
        }])).resolves.toEqual({ recoveryPhrase: FAKE_PHRASE });
    });
});
