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
const PERSISTENT_ACCOUNT_MNEMONIC_KEY = "satflux.account.mnemonic.persistent.v1";
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

/**
 * Store the recovery phrase for the current session only (sessionStorage).
 *
 * The phrase is the Evolu owner secret that decrypts all invoicing data, so it
 * is deliberately NOT written to localStorage: a plaintext copy at rest is
 * readable by any same-origin script (XSS, hostile extension) and survives on
 * disk. It lives in sessionStorage so it survives a tab reload but is gone once
 * the browser is fully closed - the user then re-unlocks with their phrase.
 * It is never sent to the server, logs, analytics, or the URL.
 */
export function storeAccountMnemonic(mnemonic: string): void {
    const normalized = normalizeAccountMnemonic(mnemonic);
    if (!validateMnemonic(normalized, wordlist)) {
        throw new Error("invalid_mnemonic");
    }
    sessionStorage.setItem(ACCOUNT_MNEMONIC_STORAGE_KEY, normalized);
    sessionStorage.removeItem(LEGACY_GUEST_MNEMONIC_STORAGE_KEY);
    clearEvoluOwnerRestoreAttempted();
}

/**
 * One-time migration bridge for users who still have a plaintext phrase in the
 * legacy localStorage key: copy it into the session so they keep working this
 * session, WITHOUT deleting the localStorage copy yet. The localStorage copy is
 * only removed once the user has confirmed their backup
 * (finalizeSeedMigrationAfterBackupConfirmed) - deleting it before that could
 * lock out someone who relied solely on the browser copy. Data-loss-safe: the
 * phrase is a key, and the encrypted Evolu data remains recoverable with it.
 */
export function hydrateAccountMnemonicSession(): void {
    if (getStoredAccountMnemonic()) {
        return;
    }
    try {
        const persisted = localStorage.getItem(PERSISTENT_ACCOUNT_MNEMONIC_KEY);
        if (!persisted) {
            return;
        }
        const normalized = normalizeAccountMnemonic(persisted);
        if (!validateMnemonic(normalized, wordlist)) {
            localStorage.removeItem(PERSISTENT_ACCOUNT_MNEMONIC_KEY);
            return;
        }
        sessionStorage.setItem(ACCOUNT_MNEMONIC_STORAGE_KEY, normalized);
        sessionStorage.removeItem(LEGACY_GUEST_MNEMONIC_STORAGE_KEY);
    } catch {
        // Storage unavailable.
    }
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

/** True when a plaintext phrase still sits in the legacy localStorage key. */
export function hasLegacyPersistedMnemonic(): boolean {
    try {
        const persisted = localStorage.getItem(PERSISTENT_ACCOUNT_MNEMONIC_KEY);
        return !!persisted && validateMnemonic(normalizeAccountMnemonic(persisted), wordlist);
    } catch {
        return false;
    }
}

/**
 * Remove the legacy plaintext localStorage copy. Idempotent. Call only after the
 * user has confirmed their recovery phrase is backed up - after this the phrase
 * lives session-only, so a browser restart requires re-unlocking with it.
 */
export function finalizeSeedMigrationAfterBackupConfirmed(): void {
    try {
        localStorage.removeItem(PERSISTENT_ACCOUNT_MNEMONIC_KEY);
    } catch {
        // Storage unavailable - nothing persisted to remove.
    }
}

export function clearStoredAccountMnemonic(): void {
    sessionStorage.removeItem(ACCOUNT_MNEMONIC_STORAGE_KEY);
    sessionStorage.removeItem(LEGACY_GUEST_MNEMONIC_STORAGE_KEY);
    try {
        localStorage.removeItem(PERSISTENT_ACCOUNT_MNEMONIC_KEY);
    } catch {
        // Storage unavailable.
    }
}

/** Store recovery phrase on this browser and bind Evolu (logged-in users, new device). */
export async function bindRecoveryPhraseOnThisDevice(
    mnemonic: string,
): Promise<EvoluAccountSeedInitResult> {
    storeAccountMnemonic(mnemonic);
    return initEvoluFromAccountSeedIfNeeded(mnemonic);
}

export type EvoluAccountSeedInitResult =
    | "restored"
    | "already_synced"
    | "migrated_legacy_owner"
    | "relay_synced"
    | "owner_restore_failed";

const PENDING_OWNER_MIGRATION_SNAPSHOT_KEY = "satflux.evolu.pending_owner_migration_snapshot.v1";
/** Set when auto restoreAppOwner completes but owner still mismatches - prevents reload loops. */
const EVOLU_OWNER_RESTORE_ATTEMPTED_KEY = "satflux.evolu.owner_restore_attempted.v1";

function clearEvoluOwnerRestoreAttempted(): void {
    sessionStorage.removeItem(EVOLU_OWNER_RESTORE_ATTEMPTED_KEY);
}

export function markEvoluOwnerRestoreAttempted(): void {
    sessionStorage.setItem(EVOLU_OWNER_RESTORE_ATTEMPTED_KEY, "1");
}

export function hasEvoluOwnerRestoreBeenAttempted(): boolean {
    return sessionStorage.getItem(EVOLU_OWNER_RESTORE_ATTEMPTED_KEY) === "1";
}

export async function initEvoluFromAccountSeedIfNeeded(
    mnemonic: string,
): Promise<EvoluAccountSeedInitResult> {
    const evoluMnemonic = deriveEvoluOwnerMnemonic(mnemonic);
    const { evolu } = await import("@/evolu/client");
    const { isEvoluRelaySyncPending, markEvoluRelaySyncPending, waitForInvoicingRelaySync } =
        await import("@/evolu/relaySyncWait");
    const { ensureEvoluRelaySubscription } = await import("@/evolu/evoluRelaySubscription");
    const {
        restoreInvoicingSnapshotAsync,
        snapshotHasInvoicingData,
        snapshotInvoicingData,
    } = await import("@/evolu/invoicingSnapshot");
    type InvoicingDataSnapshot = Awaited<ReturnType<typeof snapshotInvoicingData>>;

    const pendingSnapshotRaw = sessionStorage.getItem(PENDING_OWNER_MIGRATION_SNAPSHOT_KEY);
    if (pendingSnapshotRaw) {
        sessionStorage.removeItem(PENDING_OWNER_MIGRATION_SNAPSHOT_KEY);
        try {
            const pendingSnapshot = JSON.parse(pendingSnapshotRaw) as InvoicingDataSnapshot;
            if (snapshotHasInvoicingData(pendingSnapshot)) {
                markEvoluRelaySyncPending();
                await restoreInvoicingSnapshotAsync(evolu, pendingSnapshot);
                await ensureEvoluRelaySubscription(evolu);
                await waitForInvoicingRelaySync(evolu);
                return "migrated_legacy_owner";
            }
        } catch {
            // Corrupt pending snapshot - continue with normal init.
        }
    }

    const owner = await evolu.appOwner;
    if (isTargetEvoluOwner(owner.mnemonic, mnemonic)) {
        clearEvoluOwnerRestoreAttempted();
        await ensureEvoluRelaySubscription(evolu);
        if (isEvoluRelaySyncPending()) {
            const synced = await waitForInvoicingRelaySync(evolu);
            return synced ? "relay_synced" : "already_synced";
        }
        return "already_synced";
    }

    if (hasEvoluOwnerRestoreBeenAttempted()) {
        const ownerAfterAttempt = await evolu.appOwner;
        if (isTargetEvoluOwner(ownerAfterAttempt.mnemonic, mnemonic)) {
            clearEvoluOwnerRestoreAttempted();
            await ensureEvoluRelaySubscription(evolu);
            return "already_synced";
        }
        return "owner_restore_failed";
    }

    const snapshot = await snapshotInvoicingData(evolu);
    const hasData = snapshotHasInvoicingData(snapshot);
    const wrongOwner = owner.mnemonic != null && owner.mnemonic !== evoluMnemonic;

    async function restoreInvoicingAfterOwnerReset(
        dataSnapshot: InvoicingDataSnapshot,
    ): Promise<void> {
        if (!snapshotHasInvoicingData(dataSnapshot)) {
            return;
        }
        markEvoluRelaySyncPending();
        await restoreInvoicingSnapshotAsync(evolu, dataSnapshot);
        await ensureEvoluRelaySubscription(evolu);
        await waitForInvoicingRelaySync(evolu);
    }

    async function finishAfterOwnerRestore(
        result: "restored" | "migrated_legacy_owner",
    ): Promise<EvoluAccountSeedInitResult> {
        const ownerAfterRestore = await evolu.appOwner;
        if (!isTargetEvoluOwner(ownerAfterRestore.mnemonic, mnemonic)) {
            markEvoluOwnerRestoreAttempted();
            return "owner_restore_failed";
        }
        clearEvoluOwnerRestoreAttempted();
        await ensureEvoluRelaySubscription(evolu);
        return result;
    }

    if (wrongOwner && hasData) {
        markEvoluRelaySyncPending();
        await evolu.restoreAppOwner(evoluMnemonic, { reload: false });
        await restoreInvoicingAfterOwnerReset(snapshot);
        return finishAfterOwnerRestore("migrated_legacy_owner");
    }

    markEvoluRelaySyncPending();
    await evolu.restoreAppOwner(evoluMnemonic, { reload: false });
    return finishAfterOwnerRestore("restored");
}
