import { onUnmounted, shallowRef, watch } from "vue";
import type { Evolu } from "@evolu/common/local-first";
import { ensureEvoluBoundToAccountSeed } from "@/evolu/bootstrap";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import { ensureEvoluRelaySubscription } from "@/evolu/evoluRelaySubscription";
import type { InvoicingLocalSchema } from "@/evolu/schema";
import { acquirePaymentIntentSync } from "@/evolu/paymentIntentSync";
import { useAuthStore } from "@/store/auth";

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
    let disposed = false;
    let releasePaymentSync: (() => void) | undefined;
    const auth = useAuthStore();
    const stopAuthWatch = watch(() => auth.user?.id, () => {
        releasePaymentSync?.();
        releasePaymentSync = undefined;
        runId++;
    }, { flush: "sync" });

    async function runBootstrap(): Promise<void> {
        const id = ++runId;
        const accountId = auth.user?.id;
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
            await ensureEvoluRelaySubscription(mod.evolu);
            if (disposed || id !== runId || auth.user?.id !== accountId) return;
            if (accountId !== undefined) {
                releasePaymentSync = acquirePaymentIntentSync(mod.evolu, accountId,
                    () => !disposed && id === runId && auth.user?.id === accountId);
            }
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

    onUnmounted(() => {
        disposed = true;
        runId++;
        releasePaymentSync?.();
        stopAuthWatch();
    });

    return {
        localFirst,
        ready,
        evolu,
        bootstrapError,
        retryBootstrap,
    };
}
