/**
 * WebAuthn passkey + PRF extension layer for device unlock. Native
 * navigator.credentials only - no external WebAuthn dependency.
 *
 * The PRF extension (hmac-secret) yields a stable 32-byte secret per
 * (credential, evaluation input); the envelope turns it into a KEK via
 * HKDF (envelope.ts). A WebAuthn *signature* is deliberately never used as
 * keying material - signatures are not designed to be secret or uniform.
 *
 * Support caveat: `isPasskeyPrfSupported()` can only probe for a platform
 * authenticator; whether the authenticator actually implements PRF is
 * knowable only from the create()/get() extension results - creation
 * therefore verifies `prf.enabled` and fails typed when absent.
 */

import { fromB64, randomBytes, toB64 } from "../passphraseCrypto";

export class PasskeyUnsupportedError extends Error {
    constructor() {
        super("passkey_unsupported");
        this.name = "PasskeyUnsupportedError";
    }
}

export class PasskeyPrfUnsupportedError extends Error {
    constructor() {
        super("passkey_prf_unsupported");
        this.name = "PasskeyPrfUnsupportedError";
    }
}

/** User dismissed/timed out the platform prompt - not an error state. */
export class PasskeyCancelledError extends Error {
    constructor() {
        super("passkey_cancelled");
        this.name = "PasskeyCancelledError";
    }
}

type PrfExtensionResults = {
    prf?: {
        enabled?: boolean;
        results?: { first?: ArrayBuffer | Uint8Array };
    };
};

export async function isPasskeyPrfSupported(): Promise<boolean> {
    try {
        if (typeof PublicKeyCredential === "undefined" || !navigator.credentials) {
            return false;
        }
        return await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
    } catch {
        return false;
    }
}

function mapWebAuthnError(error: unknown): Error {
    if (error instanceof DOMException) {
        if (error.name === "NotAllowedError" || error.name === "AbortError") {
            return new PasskeyCancelledError();
        }
        if (error.name === "NotSupportedError") {
            return new PasskeyUnsupportedError();
        }
    }
    return error instanceof Error ? error : new PasskeyUnsupportedError();
}

function prfResultBytes(results: PrfExtensionResults): Uint8Array | null {
    const first = results.prf?.results?.first;
    if (!first) {
        return null;
    }
    const bytes = first instanceof Uint8Array ? first : new Uint8Array(first);
    return bytes.byteLength >= 32 ? bytes : null;
}

/**
 * Create a passkey with the PRF extension and return everything the envelope
 * slot needs. Some authenticators return the PRF secret already at create();
 * the rest need one follow-up get() (a second platform prompt).
 */
export async function createPasskeyPrfCredential(params: {
    label: string;
    /** Stable public user handle (owner fingerprint) - groups credentials per account. */
    userHandle: string;
    userName: string;
    /**
     * PRF eval input for the new credential. Account-level passkeys pass the
     * FIXED input (accountPasskeyEnvelope.ts) so one create() gesture yields
     * an output usable for both the local slot and the cloud envelope;
     * omitted = random (local-only slot, original behavior).
     */
    prfSaltB64?: string;
}): Promise<{ credentialIdB64: string; prfSaltB64: string; prfOutput: Uint8Array }> {
    if (!(await isPasskeyPrfSupported())) {
        throw new PasskeyUnsupportedError();
    }

    const prfSalt = params.prfSaltB64 ? fromB64(params.prfSaltB64) : randomBytes(32);
    let credential: PublicKeyCredential | null;
    try {
        credential = (await navigator.credentials.create({
            publicKey: {
                rp: { name: "SATFLUX", id: location.hostname },
                user: {
                    id: new TextEncoder().encode(params.userHandle).slice(0, 64) as BufferSource,
                    name: params.userName,
                    displayName: params.label,
                },
                challenge: randomBytes(32) as BufferSource,
                pubKeyCredParams: [
                    { type: "public-key", alg: -7 },
                    { type: "public-key", alg: -257 },
                ],
                authenticatorSelection: {
                    residentKey: "preferred",
                    userVerification: "required",
                },
                extensions: { prf: { eval: { first: prfSalt as BufferSource } } } as AuthenticationExtensionsClientInputs,
            },
        })) as PublicKeyCredential | null;
    } catch (error) {
        throw mapWebAuthnError(error);
    }
    if (!credential) {
        throw new PasskeyCancelledError();
    }

    const results = credential.getClientExtensionResults() as PrfExtensionResults;
    if (!results.prf?.enabled && !results.prf?.results?.first) {
        // The authenticator ignored the PRF extension - this passkey can
        // never derive a key. Fail typed; the UI explains the device limit.
        throw new PasskeyPrfUnsupportedError();
    }

    const credentialIdB64 = toB64(new Uint8Array(credential.rawId));
    const prfSaltB64 = toB64(prfSalt);

    const immediate = prfResultBytes(results);
    const prfOutput = immediate ?? (await evaluatePrf(credentialIdB64, prfSaltB64));

    return { credentialIdB64, prfSaltB64, prfOutput };
}

/** WebAuthn's evalByCredential keys credentials by base64url (RFC 4648 §5). */
function b64ToB64Url(b64: string): string {
    return b64.replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
}

/**
 * Unlock-path PRF evaluation across ALL passkey slots of the envelope: one
 * get() with every credential allowed and per-credential PRF salts
 * (evalByCredential) - the platform/user picks the passkey, the assertion
 * tells us which one answered.
 */
export async function evaluatePrfForSlots(
    slots: Array<{ credentialIdB64: string; prfSaltB64: string }>,
): Promise<{ credentialIdB64: string; prfOutput: Uint8Array }> {
    if (slots.length === 0) {
        throw new PasskeyUnsupportedError();
    }

    const evalByCredential: Record<string, { first: BufferSource }> = {};
    for (const slot of slots) {
        evalByCredential[b64ToB64Url(slot.credentialIdB64)] = {
            first: fromB64(slot.prfSaltB64) as BufferSource,
        };
    }

    let assertion: PublicKeyCredential | null;
    try {
        assertion = (await navigator.credentials.get({
            publicKey: {
                rpId: location.hostname,
                challenge: randomBytes(32) as BufferSource,
                allowCredentials: slots.map((slot) => ({
                    type: "public-key" as const,
                    id: fromB64(slot.credentialIdB64) as BufferSource,
                })),
                userVerification: "required",
                extensions: { prf: { evalByCredential } } as AuthenticationExtensionsClientInputs,
            },
        })) as PublicKeyCredential | null;
    } catch (error) {
        throw mapWebAuthnError(error);
    }
    if (!assertion) {
        throw new PasskeyCancelledError();
    }

    const output = prfResultBytes(assertion.getClientExtensionResults() as PrfExtensionResults);
    if (!output) {
        throw new PasskeyPrfUnsupportedError();
    }

    return { credentialIdB64: toB64(new Uint8Array(assertion.rawId)), prfOutput: output };
}

/**
 * Discoverable sign-in evaluation: no allowCredentials - the platform lists
 * the user's resident passkeys for this rp and evaluates the PRF with the
 * FIXED account input in the same gesture. Which credential answered is
 * only known from the assertion, which is exactly why the account envelope
 * uses a fixed eval input (see accountPasskeyEnvelope.ts).
 */
export async function evaluatePrfDiscoverable(prfInput: Uint8Array): Promise<{ credentialIdB64: string; prfOutput: Uint8Array }> {
    let assertion: PublicKeyCredential | null;
    try {
        assertion = (await navigator.credentials.get({
            publicKey: {
                rpId: location.hostname,
                challenge: randomBytes(32) as BufferSource,
                allowCredentials: [],
                userVerification: "required",
                extensions: {
                    prf: { eval: { first: prfInput as BufferSource } },
                } as AuthenticationExtensionsClientInputs,
            },
        })) as PublicKeyCredential | null;
    } catch (error) {
        throw mapWebAuthnError(error);
    }
    if (!assertion) {
        throw new PasskeyCancelledError();
    }

    const output = prfResultBytes(assertion.getClientExtensionResults() as PrfExtensionResults);
    if (!output) {
        throw new PasskeyPrfUnsupportedError();
    }
    return { credentialIdB64: toB64(new Uint8Array(assertion.rawId)), prfOutput: output };
}

/**
 * Evaluate the PRF for an existing credential (unlock path). Returns the
 * 32-byte secret; typed errors for unsupported/cancelled.
 */
export async function evaluatePrf(credentialIdB64: string, prfSaltB64: string): Promise<Uint8Array> {
    let assertion: PublicKeyCredential | null;
    try {
        assertion = (await navigator.credentials.get({
            publicKey: {
                rpId: location.hostname,
                challenge: randomBytes(32) as BufferSource,
                allowCredentials: [
                    { type: "public-key", id: fromB64(credentialIdB64) as BufferSource },
                ],
                userVerification: "required",
                extensions: {
                    prf: { eval: { first: fromB64(prfSaltB64) as BufferSource } },
                } as AuthenticationExtensionsClientInputs,
            },
        })) as PublicKeyCredential | null;
    } catch (error) {
        throw mapWebAuthnError(error);
    }
    if (!assertion) {
        throw new PasskeyCancelledError();
    }

    const output = prfResultBytes(assertion.getClientExtensionResults() as PrfExtensionResults);
    if (!output) {
        throw new PasskeyPrfUnsupportedError();
    }
    return output;
}
