import { computed, ref, watch, type Ref } from "vue";
import { ensureBridgeCompanyIdForLocalCompany } from "./bridgeCompanyEnsure";
import {
    fetchEfakturaInbox,
    reconcileEfakturaInboxWithLocalExpenses,
    type EfakturaInboxEntry,
} from "./efakturaInboxImport";
import { isInvoicingLocalFirst } from "./flags";
import { toAppRows } from "./queryLoad";
import { invoicingApi } from "@/services/api";

/**
 * Shared live state of the e-faktura inbox (module singleton) - the
 * local-first twin of integrationInboxLive.ts. One polling loop per SPA:
 * the Expenses page panel and any badge read `entries` from here.
 *
 * Active only for companies with `efaktura_inbound_enabled` in their
 * (Evolu) app settings and a resolvable bridge company - the inbox lives
 * on the bridge row server-side.
 */

const POLL_INTERVAL_MS = 60_000;
const REFRESH_MIN_GAP_MS = 15_000;

const entries = ref<EfakturaInboxEntry[]>([]);
const pendingCount = computed(() => entries.value.length);
const bridgeCompanyIdRef = ref<string | null>(null);
export type EfakturaInboundHealth = {
    state: "not_polled" | "ok" | "failed" | "stale" | "disabled" | "unavailable";
    last_poll_at: string | null;
    failed: number;
};
const health = ref<EfakturaInboundHealth | null>(null);
const receivingEnabled = ref(false);

/** Unknown/malformed status must not make an empty inbox appear healthy. */
export function parseInboundHealth(raw: unknown): EfakturaInboundHealth {
    if (!raw || typeof raw !== "object") throw new Error("invalid_inbound_health");
    const row = raw as Record<string, unknown>;
    if (!["not_polled", "ok", "failed", "stale", "disabled"].includes(String(row.state))) throw new Error("invalid_inbound_health");
    return { state: row.state as EfakturaInboundHealth["state"],
        last_poll_at: typeof row.last_poll_at === "string" ? row.last_poll_at : null,
        failed: typeof row.failed === "number" && Number.isFinite(row.failed) ? row.failed : 0 };
}

let companyId = "";
let enabled = false;
let refreshing = false;
let lastRefreshAt = 0;
let listenersInstalled = false;
/** Bumped on every company switch / settings reload; stale async results are discarded. */
let generation = 0;

type CompanyRowSlice = { id: string; appSettingsJson: string | null };

export function inboundEnabledFromSettingsJson(json: string | null): boolean {
    if (!json) return false;
    try {
        const settings = JSON.parse(json) as Record<string, unknown>;
        return Boolean(settings.efaktura_enabled) && Boolean(settings.efaktura_inbound_enabled);
    } catch {
        return false;
    }
}

async function deriveCompanyParams(nextCompanyId: string): Promise<void> {
    // Switch synchronously first so nothing from the previous company leaks
    // into the new one while the bridge lookup below is still in flight.
    const myGeneration = ++generation;
    companyId = nextCompanyId;
    enabled = false;
    receivingEnabled.value = false;
    health.value = null;
    bridgeCompanyIdRef.value = null;
    entries.value = [];

    const { evolu, allCompaniesDetailQuery } = await import("./client");
    const rows = toAppRows<CompanyRowSlice>(await evolu.loadQuery(allCompaniesDetailQuery));
    if (myGeneration !== generation) return;
    const row = rows.find((r) => String(r.id) === nextCompanyId) ?? null;
    if (row == null || !inboundEnabledFromSettingsJson(row.appSettingsJson)) {
        return;
    }
    receivingEnabled.value = true;
    health.value = { state: "not_polled", last_poll_at: null, failed: 0 };

    const bridge = await ensureBridgeCompanyIdForLocalCompany(nextCompanyId);
    if (myGeneration !== generation) return;
    const bridgeId = bridge.ok ? bridge.bridgeCompanyId : null;
    bridgeCompanyIdRef.value = bridgeId;
    enabled = bridgeId !== null;
    if (!enabled) health.value = { state: "unavailable", last_poll_at: null, failed: 0 };
}

/**
 * Fetch + reconcile. Background callers (poll timer, focus) swallow errors;
 * an explicit user refresh passes `throwOnError` so the panel can show them.
 * Results are discarded when the company / bridge changed meanwhile.
 */
export async function refreshEfakturaInboxLive(
    force = false,
    options: { throwOnError?: boolean } = {},
): Promise<void> {
    const bridgeId = bridgeCompanyIdRef.value;
    if (!enabled || !companyId || !bridgeId || refreshing) {
        return;
    }
    if (!force && Date.now() - lastRefreshAt < REFRESH_MIN_GAP_MS) {
        return;
    }
    const myGeneration = generation;
    const myCompanyId = companyId;
    refreshing = true;
    try {
        const { evolu } = await import("./client");
        const [fetched, inboundHealth] = await Promise.all([
            fetchEfakturaInbox(bridgeId), invoicingApi.efaktura.inboundHealth(bridgeId),
        ]);
        if (myGeneration !== generation) return;
        health.value = parseInboundHealth(inboundHealth);
        const remaining = await reconcileEfakturaInboxWithLocalExpenses(evolu, myCompanyId, bridgeId, fetched);
        if (myGeneration !== generation || bridgeCompanyIdRef.value !== bridgeId) return;
        entries.value = remaining;
        lastRefreshAt = Date.now();
    } catch (error) {
        if (myGeneration !== generation || bridgeCompanyIdRef.value !== bridgeId) return;
        health.value = { state: "unavailable", last_poll_at: health.value?.last_poll_at ?? null, failed: 0 };
        if (options.throwOnError) {
            throw error;
        }
        // Background failures remain visible through shared health, even with no documents.
    } finally {
        refreshing = false;
        // A previous company's in-flight request must not delay the new inbox until the next timer tick.
        if (myGeneration !== generation && enabled) void refreshEfakturaInboxLive(true);
    }
}

/** Settings were just saved (inbound toggled) - re-derive and refresh. */
export async function reloadEfakturaInboxLive(): Promise<void> {
    if (!companyId) return;
    await deriveCompanyParams(companyId);
    await refreshEfakturaInboxLive(true);
}

function onWindowFocusOrVisible(): void {
    if (typeof document !== "undefined" && document.visibilityState === "hidden") {
        return;
    }
    void refreshEfakturaInboxLive();
}

function ensureLoopInstalled(): void {
    if (listenersInstalled || typeof window === "undefined") {
        return;
    }
    listenersInstalled = true;
    window.addEventListener("focus", onWindowFocusOrVisible);
    document.addEventListener("visibilitychange", onWindowFocusOrVisible);
    setInterval(() => {
        if (typeof document === "undefined" || document.visibilityState !== "hidden") {
            void refreshEfakturaInboxLive(true);
        }
    }, POLL_INTERVAL_MS);
}

export function initEfakturaInboxLive(activeCompanyId: Ref<string>): void {
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
            void refreshEfakturaInboxLive(true);
        },
        { immediate: true },
    );
}

export function useEfakturaInboxLive() {
    return { entries, pendingCount, bridgeCompanyId: bridgeCompanyIdRef, health, receivingEnabled };
}
