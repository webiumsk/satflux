import { computed, onMounted, onUnmounted, ref } from "vue";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import { isEvoluRelaySyncPending } from "@/evolu/relaySyncWait";

/** Reactive relay-sync gate for local-first invoicing (session flag after phrase restore). */
export function useInvoicingRelaySync() {
    const localFirst = isInvoicingLocalFirst();
    const pending = ref(localFirst && isEvoluRelaySyncPending());

    let timer: ReturnType<typeof setInterval> | undefined;

    onMounted(() => {
        if (!localFirst) return;
        timer = setInterval(() => {
            pending.value = isEvoluRelaySyncPending();
        }, 400);
    });

    onUnmounted(() => {
        if (timer) clearInterval(timer);
    });

    const isRelaySyncing = computed(() => localFirst && pending.value);

    return {
        localFirst,
        isRelaySyncing,
    };
}
