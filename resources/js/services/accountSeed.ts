import { generateMnemonic, mnemonicToSeedSync, validateMnemonic } from "@scure/bip39";
import { wordlist } from "@scure/bip39/wordlists/english.js";
import { hkdf } from "@noble/hashes/hkdf.js";
import { sha512 } from "@noble/hashes/sha2.js";
import * as ed25519 from "@noble/ed25519";
import {
    deriveEvoluOwnerMnemonic,
    isTargetEvoluOwner,
} from "./evoluOwner";

ed25519.hashes.sha512 = sha512;

const RECOVERY_HKDF_INFO = new TextEncoder().encode("satflux-guest-ed25519-v1");
const ACCOUNT_MNEMONIC_STORAGE_KEY = "satflux.account.mnemonic.v1";
const LEGACY_GUEST_MNEMONIC_STORAGE_KEY = "satflux.guest.mnemonic.v1";

function bytesToHex(u8: Uint8Array): string {
    return Array.from(u8, (b) => b.toString(16).padStart(2, "0")).join("");
}

export function normalizeAccountMnemonic(mnemonic: string): string {
    return mnemonic
        .trim()
        .toLowerCase()
        .split(/\s+/)
        .join(" ");
}

export function generateAccountMnemonic24(): string {
    return generateMnemonic(wordlist, 256);
}

export function deriveRecoveryPublicKeyHex(mnemonic: string): string {
    const normalized = normalizeAccountMnemonic(mnemonic);
    if (!validateMnemonic(normalized, wordlist)) {
        throw new Error("invalid_mnemonic");
    }
    const seed = mnemonicToSeedSync(normalized, "");
    const sk = hkdf(sha512, seed, new Uint8Array(0), RECOVERY_HKDF_INFO, 32);
    const pk = ed25519.getPublicKey(sk);
    return bytesToHex(pk);
}

export function signRecoveryMessage(mnemonic: string, messageUtf8: string): string {
    const normalized = normalizeAccountMnemonic(mnemonic);
    if (!validateMnemonic(normalized, wordlist)) {
        throw new Error("invalid_mnemonic");
    }
    const seed = mnemonicToSeedSync(normalized, "");
    const sk = hkdf(sha512, seed, new Uint8Array(0), RECOVERY_HKDF_INFO, 32);
    const sig = ed25519.sign(new TextEncoder().encode(messageUtf8), sk);
    return bytesToHex(sig);
}

export { deriveEvoluOwnerMnemonic };

export function storeAccountMnemonic(mnemonic: string): void {
    const normalized = normalizeAccountMnemonic(mnemonic);
    if (!validateMnemonic(normalized, wordlist)) {
        throw new Error("invalid_mnemonic");
    }
    sessionStorage.setItem(ACCOUNT_MNEMONIC_STORAGE_KEY, normalized);
    sessionStorage.removeItem(LEGACY_GUEST_MNEMONIC_STORAGE_KEY);
}

export function getStoredAccountMnemonic(): string | null {
    const current = sessionStorage.getItem(ACCOUNT_MNEMONIC_STORAGE_KEY);
    if (current) {
        return current;
    }
    const legacy = sessionStorage.getItem(LEGACY_GUEST_MNEMONIC_STORAGE_KEY);
    if (legacy) {
        sessionStorage.setItem(ACCOUNT_MNEMONIC_STORAGE_KEY, legacy);
        sessionStorage.removeItem(LEGACY_GUEST_MNEMONIC_STORAGE_KEY);
        return legacy;
    }
    return null;
}

export function clearStoredAccountMnemonic(): void {
    sessionStorage.removeItem(ACCOUNT_MNEMONIC_STORAGE_KEY);
    sessionStorage.removeItem(LEGACY_GUEST_MNEMONIC_STORAGE_KEY);
}

export type EvoluAccountSeedInitResult =
    | "restored"
    | "already_synced"
    | "migrated_legacy_owner"
    | "relay_synced";

export async function initEvoluFromAccountSeedIfNeeded(
    mnemonic: string,
): Promise<EvoluAccountSeedInitResult> {
    const evoluMnemonic = deriveEvoluOwnerMnemonic(mnemonic);
    const { evolu } = await import("@/evolu/client");
    const { isEvoluRelaySyncPending, markEvoluRelaySyncPending, waitForInvoicingRelaySync } =
        await import("@/evolu/relaySyncWait");
    const { restoreInvoicingSnapshot, snapshotHasInvoicingData, snapshotInvoicingData } =
        await import("@/evolu/invoicingSnapshot");

    const owner = await evolu.appOwner;
    if (isTargetEvoluOwner(owner.mnemonic, mnemonic)) {
        if (isEvoluRelaySyncPending()) {
            const synced = await waitForInvoicingRelaySync(evolu);
            return synced ? "relay_synced" : "already_synced";
        }
        return "already_synced";
    }

    const snapshot = await snapshotInvoicingData(evolu);
    const hasData = snapshotHasInvoicingData(snapshot);
    const wrongOwner = owner.mnemonic != null && owner.mnemonic !== evoluMnemonic;

    if (wrongOwner && hasData) {
        await evolu.restoreAppOwner(evoluMnemonic, { reload: false });
        restoreInvoicingSnapshot(evolu, snapshot);
        markEvoluRelaySyncPending();
        evolu.reloadApp();
        return "migrated_legacy_owner";
    }

    markEvoluRelaySyncPending();
    await evolu.restoreAppOwner(evoluMnemonic, { reload: true });
    return "restored";
}
