import { getStoredAccountMnemonic, initEvoluFromAccountSeedIfNeeded } from "@/services/accountSeed";
import { isInvoicingLocalFirst } from "./flags";

let bootstrapPromise: Promise<void> | null = null;

/**
 * Bind Evolu AppOwner to the Satflux recovery phrase (same 24 words).
 * Safe to call repeatedly; migrates legacy random/HKDF owners with local data.
 */
export function ensureEvoluBoundToAccountSeed(): Promise<void> {
    if (!isInvoicingLocalFirst()) {
        return Promise.resolve();
    }
    const mnemonic = getStoredAccountMnemonic();
    if (!mnemonic) {
        return Promise.resolve();
    }
    if (!bootstrapPromise) {
        bootstrapPromise = initEvoluFromAccountSeedIfNeeded(mnemonic)
            .then(() => undefined)
            .catch(() => undefined)
            .finally(() => {
                bootstrapPromise = null;
            });
    }
    return bootstrapPromise;
}
