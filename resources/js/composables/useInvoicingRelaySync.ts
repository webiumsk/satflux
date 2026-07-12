import { computed, onMounted, onUnmounted, ref } from "vue";
import { ensureEvoluBoundToAccountSeed } from "@/evolu/bootstrap";
import { useInvoicingEvolu } from "@/evolu/client";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import {
    isEvoluRelaySyncPending,
    pullInvoicingFromRelay,
    pushInvoicingToRelay,
    refreshAllInvoicingLocalQueries,
    type PullInvoicingFromRelayOptions,
    type PullInvoicingFromRelayResult,
    type PushInvoicingToRelayResult,
    waitForInvoicingDataSettled,
    waitForInvoicingRelaySync,
} from "@/evolu/relaySyncWait";
import {
    deriveRelaySyncState,
    lastExchangeAt as lastExchangeAtOf,
    ownerIdHashFor,
    readRelaySyncMeta,
    recordProbe,
    type RelaySyncMeta,
    type RelaySyncUiState,
} from "@/evolu/relaySyncTelemetry";
import { ensureEvoluErrorMonitor, evoluErrorRef } from "@/evolu/evoluErrorMonitor";
import { useOnlineStatus } from "./useOnlineStatus";
import {
    getResolvedEvoluRelayUrl,
    isEvoluRelayConfiguredAtRuntime,
} from "@/services/evoluRelayPreference";
import { probeRelayReachability } from "@/services/evoluRelayUsageApi";

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

    const online = useOnlineStatus();
    const evoluError = evoluErrorRef();
    const syncMeta = ref<RelaySyncMeta>(readRelaySyncMeta(null));
    const ownerHash = ref<string | null>(null);
    const probing = ref(false);

    let timer: ReturnType<typeof setInterval> | undefined;
    let onlineListener: (() => void) | undefined;

    function refreshSyncMeta(): void {
        syncMeta.value = readRelaySyncMeta(ownerHash.value);
    }

    /**
     * Reachability probe against the relay usage endpoint. Runs on mount,
     * when the browser comes back online and on demand (status button).
     */
    async function probeNow(): Promise<void> {
        if (!localFirst || probing.value) return;
        if (!isEvoluRelayConfiguredAtRuntime()) return;
        probing.value = true;
        try {
            if (!ownerHash.value && evolu) {
                ownerHash.value = await ownerIdHashFor(evolu);
            }
            if (!ownerHash.value) return;
            const reachable = await probeRelayReachability(getResolvedEvoluRelayUrl());
            recordProbe(ownerHash.value, reachable);
            refreshSyncMeta();
        } catch {
            // Probe is telemetry only.
        } finally {
            probing.value = false;
        }
    }

    onMounted(() => {
        if (!localFirst || !evolu) return;

        ensureEvoluErrorMonitor(evolu);
        void ownerIdHashFor(evolu).then((hash) => {
            ownerHash.value = hash;
            refreshSyncMeta();
            void probeNow();
        });
        onlineListener = () => {
            void probeNow();
        };
        window.addEventListener("online", onlineListener);

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
                    await pullInvoicingFromRelay(evolu, { timeoutMs: 20_000 });
                    await refreshAllInvoicingLocalQueries(evolu);
                }
            } finally {
                blockingWait.value = false;
                pending.value = isEvoluRelaySyncPending();
                refreshSyncMeta();
            }
        })();

        timer = setInterval(() => {
            pending.value = isEvoluRelaySyncPending();
        }, 400);
    });

    onUnmounted(() => {
        if (timer) clearInterval(timer);
        if (onlineListener) window.removeEventListener("online", onlineListener);
    });

    const isRelaySyncing = computed(
        () => localFirst && (pending.value || blockingWait.value),
    );

    /** Honest 6-state model (P1 phase 4) - see relaySyncTelemetry. */
    const syncState = computed<RelaySyncUiState>(() =>
        deriveRelaySyncState({
            relayConfigured: localFirst && isEvoluRelayConfiguredAtRuntime(),
            online: online.value,
            pending: isRelaySyncing.value,
            hasEvoluError: evoluError.value !== null,
            meta: syncMeta.value,
        }),
    );

    const lastExchangeAt = computed(() => lastExchangeAtOf(syncMeta.value));

    async function refreshFromRelay(
        options?: string | PullInvoicingFromRelayOptions,
    ): Promise<PullInvoicingFromRelayResult> {
        if (!evolu) {
            return {
                ownerStatus: "ok",
                changed: false,
                documentCount: 0,
                timedOut: false,
            };
        }
        await ensureEvoluBoundToAccountSeed();
        const resolved =
            typeof options === "string"
                ? { companyId: options }
                : (options ?? {});
        const result = await pullInvoicingFromRelay(evolu, {
            timeoutMs: 60_000,
            subscriptionWarmupMs: 10_000,
            ...resolved,
        });
        refreshSyncMeta();
        return result;
    }

    async function pushToRelay(options?: { force?: boolean }): Promise<PushInvoicingToRelayResult> {
        if (!evolu) {
            return {
                ownerStatus: "ok",
                companiesUpdated: 0,
                syncEvents: 0,
                ownerHint: "",
                evoluError: null,
                ok: false,
            };
        }
        await ensureEvoluBoundToAccountSeed();
        const result = await pushInvoicingToRelay(evolu, {
            timeoutMs: options?.force ? 150_000 : 45_000,
            force: options?.force,
        });
        refreshSyncMeta();
        return result;
    }

    return {
        localFirst,
        isRelaySyncing,
        isRelaySettling: computed(() => localFirst && blockingWait.value),
        syncState,
        lastExchangeAt,
        probeNow,
        refreshFromRelay,
        pushToRelay,
    };
}

export type { PullInvoicingFromRelayResult, PushInvoicingToRelayResult };
