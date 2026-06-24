import { computed, onMounted, onUnmounted, ref } from "vue";
import { ensureEvoluBoundToAccountSeed } from "@/evolu/bootstrap";
import { useInvoicingEvolu } from "@/evolu/client";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import {
    isEvoluRelaySyncPending,
    pullInvoicingFromRelay,
    refreshInvoicingLocalQueries,
    type PullInvoicingFromRelayResult,
    waitForInvoicingDataSettled,
    waitForInvoicingRelaySync,
} from "@/evolu/relaySyncWait";

export type UseInvoicingRelaySyncOptions = {
    /** Pull latest local rows on mount (non-blocking unless phrase restore is pending). */
    refreshOnMount?: boolean;
};

/** Relay-sync gate for local-first invoicing. Blocks UI only after phrase restore / migration. */
export function useInvoicingRelaySync(options?: UseInvoicingRelaySyncOptions) {
    const localFirst = isInvoicingLocalFirst();
    const pending = ref(localFirst && isEvoluRelaySyncPending());
    const blockingWait = ref(false);
    const evolu = localFirst ? useInvoicingEvolu() : null;

    let timer: ReturnType<typeof setInterval> | undefined;

    onMounted(() => {
        if (!localFirst || !evolu) return;

        void (async () => {
            const shouldBlock = isEvoluRelaySyncPending();
            if (shouldBlock) {
                blockingWait.value = true;
            }
            try {
                if (shouldBlock) {
                    await waitForInvoicingRelaySync(evolu);
                    await waitForInvoicingDataSettled(evolu);
                } else if (options?.refreshOnMount) {
                    await refreshInvoicingLocalQueries(evolu);
                }
            } finally {
                blockingWait.value = false;
                pending.value = isEvoluRelaySyncPending();
            }
        })();

        timer = setInterval(() => {
            pending.value = isEvoluRelaySyncPending();
        }, 400);
    });

    onUnmounted(() => {
        if (timer) clearInterval(timer);
    });

    const isRelaySyncing = computed(
        () => localFirst && (pending.value || blockingWait.value),
    );

    async function refreshFromRelay(companyId?: string): Promise<PullInvoicingFromRelayResult> {
        if (!evolu) {
            return {
                ownerStatus: "ok",
                changed: false,
                documentCount: 0,
                timedOut: false,
            };
        }
        await ensureEvoluBoundToAccountSeed();
        return pullInvoicingFromRelay(evolu, { companyId });
    }

    return {
        localFirst,
        isRelaySyncing,
        isRelaySettling: computed(() => localFirst && blockingWait.value),
        refreshFromRelay,
    };
}

export type { PullInvoicingFromRelayResult };
