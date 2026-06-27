import { getStoredAccountMnemonic, initEvoluFromAccountSeedIfNeeded } from "@/services/accountSeed";
import { sleep, withTimeout } from "./asyncTimeout";
import { isInvoicingLocalFirst } from "./flags";

/** Defer Evolu init so login/router navigation can settle (reduces WASM worker races). */
export const EVOLU_BOOTSTRAP_DEFER_MS = 350;

/** Upper bound for owner restore + relay wait during bootstrap. */
export const EVOLU_BOOTSTRAP_TIMEOUT_MS = 60_000;

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
        bootstrapPromise = sleep(EVOLU_BOOTSTRAP_DEFER_MS)
            .then(() =>
                withTimeout(
                    initEvoluFromAccountSeedIfNeeded(mnemonic),
                    EVOLU_BOOTSTRAP_TIMEOUT_MS,
                    "evolu_bootstrap_timeout",
                ),
            )
            .then(() => undefined)
            .finally(() => {
                bootstrapPromise = null;
            });
    }
    return bootstrapPromise;
}
