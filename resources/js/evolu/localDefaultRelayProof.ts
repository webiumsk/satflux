import { deriveRecoveryPublicKeyHex, getStoredAccountMnemonic } from "@/services/accountSeed";

const STORAGE_KEY = "satflux.evolu.local_default_owner.v1";

/** Forget public confirmation when the account or its locally confirmed phrase changes. */
export function clearLocalDefaultRelayOwner(): void {
    try {
        sessionStorage.removeItem(STORAGE_KEY);
    } catch {
        // Unavailable storage cannot authorize a default-relay fallback.
    }
}

/** Persist only public provenance after an explicit local binding confirms the current phrase. */
export function confirmLocalDefaultRelayOwner(accountId: number, confirmedMnemonic: string): boolean {
    if (!Number.isSafeInteger(accountId) || accountId <= 0) return false;
    try {
        const currentMnemonic = getStoredAccountMnemonic();
        if (!currentMnemonic) return false;
        const ownerPublicKey = deriveRecoveryPublicKeyHex(confirmedMnemonic);
        if (ownerPublicKey !== deriveRecoveryPublicKeyHex(currentMnemonic)) return false;
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ accountId, ownerPublicKey }));
        return true;
    } catch {
        return false;
    }
}

/** Resolve a confirmed account association only while its exact local phrase remains present. */
export function resolveLocalDefaultRelayOwner(accountId: number): string | null {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (!raw) return null;
        const proof = JSON.parse(raw) as { accountId?: unknown; ownerPublicKey?: unknown } | null;
        const mnemonic = getStoredAccountMnemonic();
        if (Number.isSafeInteger(accountId) && accountId > 0 && proof?.accountId === accountId
            && typeof proof.ownerPublicKey === "string" && /^[0-9a-f]{64}$/.test(proof.ownerPublicKey)
            && mnemonic && deriveRecoveryPublicKeyHex(mnemonic) === proof.ownerPublicKey) {
            return proof.ownerPublicKey;
        }
    } catch {
        // Missing, malformed or unavailable provenance must fail closed.
    }
    clearLocalDefaultRelayOwner();
    return null;
}
