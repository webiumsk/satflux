import { computed, ref, watch, type Ref } from "vue";
import {
    fetchIntegrationInbox,
    reconcileIntegrationInboxWithLocalDocuments,
    type IntegrationInboxEntry,
} from "./integrationInboxImport";
import { isInvoicingLocalFirst } from "./flags";
import { toAppRows } from "./queryLoad";

/**
 * Shared live state of the WooCommerce integration inbox (module singleton).
 *
 * The inbox panel used to fetch exactly once per page load, so a freshly
 * auto-issued document sat invisible until a manual refresh. This module
 * owns one polling loop for the whole invoicing SPA: the app header badge
 * and the inbox panel both read `entries` from here, and the panel keeps
 * writing its stronger (relay-synced) refresh results back into it.
 */

const POLL_INTERVAL_MS = 60_000;
/** Focus/visibility refreshes are throttled so tab-switching stays cheap. */
const REFRESH_MIN_GAP_MS = 15_000;

const entries = ref<IntegrationInboxEntry[]>([]);
const pendingCount = computed(() => entries.value.length);

let companyId = "";
let linkedStoreId: string | null = null;
let enabled = false;
let refreshing = false;
let lastRefreshAt = 0;
let pollTimer: ReturnType<typeof setInterval> | null = null;
let listenersInstalled = false;

type CompanyRowSlice = {
    id: string;
    linkedStoreId: string | null;
    appSettingsJson: string | null;
};

async function deriveCompanyParams(nextCompanyId: string): Promise<void> {
    const { evolu, allCompaniesQuery } = await import("./client");
    const rows = toAppRows<CompanyRowSlice>((await evolu.loadQuery(allCompaniesQuery)));
    const row = rows.find((r) => String(r.id) === nextCompanyId) ?? null;

    let runsEshop = false;
    if (row?.appSettingsJson) {
        try {
            const settings = JSON.parse(row.appSettingsJson) as Record<string, unknown>;
            runsEshop = Boolean(settings.runs_eshop);
        } catch {
            // Malformed settings behave like "no e-shop".
        }
    }

    companyId = nextCompanyId;
    linkedStoreId = row?.linkedStoreId?.trim() || null;
    enabled = row != null && runsEshop;
    if (!enabled) {
        entries.value = [];
    }
}

/**
 * Lightweight fetch + reconcile for the badge/poll path. The panel's own
 * refresh (which additionally pulls from the relay before importing) still
 * runs on the Invoices page and overwrites `entries` with its result.
 */
export async function refreshIntegrationInboxLive(force = false): Promise<void> {
    if (!enabled || !companyId || refreshing) {
        return;
    }
    if (!force && Date.now() - lastRefreshAt < REFRESH_MIN_GAP_MS) {
        return;
    }
    refreshing = true;
    try {
        const { evolu } = await import("./client");
        const fetched = await fetchIntegrationInbox(companyId, linkedStoreId);
        entries.value = await reconcileIntegrationInboxWithLocalDocuments(
            evolu,
            companyId,
            fetched,
            linkedStoreId,
        );
        lastRefreshAt = Date.now();
    } catch {
        // Background poll: errors stay silent, the panel surfaces its own.
    } finally {
        refreshing = false;
    }
}

function onWindowFocusOrVisible(): void {
    if (typeof document !== "undefined" && document.visibilityState === "hidden") {
        return;
    }
    void refreshIntegrationInboxLive();
}

function ensureLoopInstalled(): void {
    if (listenersInstalled || typeof window === "undefined") {
        return;
    }
    listenersInstalled = true;
    window.addEventListener("focus", onWindowFocusOrVisible);
    document.addEventListener("visibilitychange", onWindowFocusOrVisible);
    pollTimer = setInterval(() => {
        if (typeof document === "undefined" || document.visibilityState !== "hidden") {
            void refreshIntegrationInboxLive(true);
        }
    }, POLL_INTERVAL_MS);
    // SPA-lifetime loop by design; keep the handle for potential teardown.
    void pollTimer;
}

/**
 * Bind the live inbox to the active company. Called from the invoicing app
 * header (mounted on every invoicing page); duplicate calls and remounts
 * are cheap no-ops.
 */
export function initIntegrationInboxLive(activeCompanyId: Ref<string>): void {
    if (!isInvoicingLocalFirst()) {
        return;
    }
    ensureLoopInstalled();
    watch(
        activeCompanyId,
        async (id) => {
            const next = String(id ?? "").trim();
            if (!next || next === companyId) {
                return;
            }
            await deriveCompanyParams(next);
            void refreshIntegrationInboxLive(true);
        },
        { immediate: true },
    );
}

/** Reactive read access for the badge and the inbox panel. */
export function useIntegrationInboxLive() {
    return { entries, pendingCount };
}
