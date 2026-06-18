import { shallowRef } from "vue";
import type { Evolu } from "@evolu/common/local-first";
import { ensureEvoluBoundToAccountSeed } from "@/evolu/bootstrap";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import type { InvoicingLocalSchema } from "@/evolu/schema";

type AfterReadyHook = (evolu: Evolu<InvoicingLocalSchema>) => Promise<void>;

/**
 * Shared Evolu bootstrap for invoicing shells (layout + dashboard embed).
 * Defers init, surfaces bootstrap failure, and offers full-page reload recovery.
 */
export function useInvoicingEvoluShell(afterReady?: AfterReadyHook) {
    const localFirst = isInvoicingLocalFirst();
    const ready = shallowRef(false);
    const evolu = shallowRef<Evolu<InvoicingLocalSchema> | null>(null);
    const bootstrapError = shallowRef(false);
    let runId = 0;

    async function runBootstrap(): Promise<void> {
        const id = ++runId;
        bootstrapError.value = false;
        ready.value = false;
        evolu.value = null;

        try {
            await ensureEvoluBoundToAccountSeed();
            if (id !== runId) {
                return;
            }

            const mod = await import("@/evolu/client");
            if (id !== runId) {
                return;
            }

            evolu.value = mod.evolu;
            ready.value = true;
            await afterReady?.(mod.evolu);
        } catch {
            if (id !== runId) {
                return;
            }
            bootstrapError.value = true;
        }
    }

    function retryBootstrap(): void {
        window.location.reload();
    }

    if (localFirst) {
        void runBootstrap();
    }

    return {
        localFirst,
        ready,
        evolu,
        bootstrapError,
        retryBootstrap,
    };
}
