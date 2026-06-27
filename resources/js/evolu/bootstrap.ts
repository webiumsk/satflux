import { getStoredAccountMnemonic, initEvoluFromAccountSeedIfNeeded } from "@/services/accountSeed";
import { sleep, withTimeout } from "./asyncTimeout";
import { isInvoicingLocalFirst } from "./flags";

/** Defer Evolu init so login/router navigation can settle (reduces WASM worker races). */
export const EVOLU_BOOTSTRAP_DEFER_MS = 350;

/** Upper bound for owner restore + relay wait during bootstrap. */
export const EVOLU_BOOTSTRAP_TIMEOUT_MS = 60_000;

let bootstrapPromise: Promise<void> | null = null;
let bootstrapState: "idle" | "running" | "done" | "failed" = "idle";

/** Allow a fresh bootstrap after manual recovery phrase restore on Profile. */
export function resetEvoluBootstrapForRetry(): void {
    bootstrapState = "idle";
    bootstrapPromise = null;
}

/**
 * Bind Evolu AppOwner to the Satflux recovery phrase (same 24 words).
 * Runs at most once per page load; does not full-page reload on owner restore.
 */
export function ensureEvoluBoundToAccountSeed(): Promise<void> {
    if (!isInvoicingLocalFirst()) {
        return Promise.resolve();
    }
    const mnemonic = getStoredAccountMnemonic();
    if (!mnemonic) {
        return Promise.resolve();
    }
    if (bootstrapState === "done" || bootstrapState === "failed") {
        return Promise.resolve();
    }
    if (!bootstrapPromise) {
        bootstrapState = "running";
        bootstrapPromise = sleep(EVOLU_BOOTSTRAP_DEFER_MS)
            .then(() =>
                withTimeout(
                    initEvoluFromAccountSeedIfNeeded(mnemonic),
                    EVOLU_BOOTSTRAP_TIMEOUT_MS,
                    "evolu_bootstrap_timeout",
                ),
            )
            .then(() => {
                bootstrapState = "done";
            })
            .catch((error) => {
                bootstrapState = "failed";
                if (import.meta.env.DEV) {
                    console.warn("[evolu] bootstrap failed:", error);
                }
            })
            .finally(() => {
                bootstrapPromise = null;
            });
    }
    return bootstrapPromise;
}
