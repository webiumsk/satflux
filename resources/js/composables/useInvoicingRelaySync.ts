import { computed, onMounted, onUnmounted, ref } from "vue";
import {
    allDocumentsQuery,
    allNumberSeriesQuery,
    useInvoicingEvolu,
} from "@/evolu/client";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import {
    isEvoluRelaySyncPending,
    waitForInvoicingDataSettled,
    waitForInvoicingRelaySync,
} from "@/evolu/relaySyncWait";

export type UseInvoicingRelaySyncOptions = {
    /** Wait for relay merge when opening document list or editor. */
    settleOnMount?: boolean;
};

/** Reactive relay-sync gate for local-first invoicing. */
export function useInvoicingRelaySync(options?: UseInvoicingRelaySyncOptions) {
    const localFirst = isInvoicingLocalFirst();
    const pending = ref(localFirst && isEvoluRelaySyncPending());
    const settling = ref(false);
    const evolu = localFirst ? useInvoicingEvolu() : null;

    let timer: ReturnType<typeof setInterval> | undefined;

    onMounted(() => {
        if (!localFirst || !evolu) return;

        void (async () => {
            settling.value = true;
            try {
                if (isEvoluRelaySyncPending()) {
                    await waitForInvoicingRelaySync(evolu);
                }
                if (options?.settleOnMount) {
                    await waitForInvoicingDataSettled(evolu);
                    await Promise.all([
                        evolu.loadQuery(allDocumentsQuery),
                        evolu.loadQuery(allNumberSeriesQuery),
                    ]);
                }
            } finally {
                settling.value = false;
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

    const isRelaySyncing = computed(() => localFirst && (pending.value || settling.value));

    return {
        localFirst,
        isRelaySyncing,
        isRelaySettling: computed(() => localFirst && settling.value),
    };
}
