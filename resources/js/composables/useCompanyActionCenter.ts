import { computed, onScopeDispose, ref, watch, type Ref } from "vue";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import { companyShareRoutingKey } from "@/evolu/companyShareRegistry";
import { loadLocalActionCenter, type ActionCenterLocalSnapshot } from "@/evolu/actionCenterLocal";
import type { RelaySyncUiState } from "@/evolu/relaySyncTelemetry";
import {
    ACTION_CENTER_KEYS, emptyActionCenterCategories, fetchCompanyActionCenter, findActionCenterBridgeCompany,
    type ActionCenterCategory, type ActionCenterItem, type CompanyActionCenterPayload,
} from "@/services/companyActionCenter";

type SourceState = "loading" | "ready" | "unavailable" | "not_configured";
export type CompanyActionCenterDependencies = {
    localFirst?: boolean;
    relayState?: Ref<RelaySyncUiState>;
    loadLocal?: (companyId: string, signal: AbortSignal) => Promise<ActionCenterLocalSnapshot>;
    loadRemote?: (companyId: string, signal: AbortSignal) => Promise<CompanyActionCenterPayload>;
};

function aborted<T>(task: Promise<T>, signal: AbortSignal): Promise<T> {
    return new Promise<T>((resolve, reject) => {
        const cancel = () => reject(new Error("action_center_cancelled"));
        if (signal.aborted) { cancel(); return; }
        signal.addEventListener("abort", cancel, { once: true });
        task.then(resolve, reject).finally(() => signal.removeEventListener("abort", cancel));
    });
}

/** Scope all continuations, cancellation and counts to the currently selected company. */
export function useCompanyActionCenter(companyId: Ref<string>, dependencies: CompanyActionCenterDependencies = {}) {
    const localFirst = dependencies.localFirst ?? isInvoicingLocalFirst();
    const local = ref<ActionCenterLocalSnapshot | null>(null);
    const remote = ref<CompanyActionCenterPayload | null>(null);
    const localState = ref<SourceState>(localFirst ? "loading" : "not_configured");
    const remoteState = ref<SourceState>("loading");
    const loading = ref(false);
    let generation = 0;
    let disposed = false;
    let controller: AbortController | null = null;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const remoteLoader = dependencies.loadRemote ?? (async (id: string, signal: AbortSignal) => {
        const target = localFirst ? await findActionCenterBridgeCompany(id, signal) : id;
        if (signal.aborted) throw new Error("action_center_cancelled");
        return target ? fetchCompanyActionCenter(target, signal) : {
            checked_at: new Date().toISOString(), categories: emptyActionCenterCategories("not_configured"), restricted_sources: [],
        };
    });

    async function refresh(): Promise<void> {
        if (disposed) return;
        const request = ++generation;
        controller?.abort();
        clearTimeout(timer);
        controller = new AbortController();
        const signal = controller.signal;
        const selected = companyId.value;
        local.value = null;
        remote.value = null;
        localState.value = localFirst ? "loading" : "not_configured";
        remoteState.value = "loading";
        loading.value = true;
        const current = () => !disposed && generation === request && companyId.value === selected;
        if (!selected) {
            localState.value = localFirst ? "unavailable" : "not_configured";
            remoteState.value = "unavailable";
            loading.value = false;
            return;
        }
        timer = setTimeout(() => { if (current()) controller?.abort(); }, 30_000);
        const localTask = localFirst
            ? aborted((dependencies.loadLocal ?? loadLocalActionCenter)(selected, signal), signal)
                .then((snapshot) => { if (current()) { local.value = snapshot; localState.value = "ready"; } })
                .catch(() => { if (current()) localState.value = "unavailable"; })
            : Promise.resolve();
        const remoteTask = aborted(remoteLoader(selected, signal), signal)
            .then((payload) => {
                if (!current()) return;
                remote.value = payload;
                remoteState.value = payload.categories.every((category) => category.state === "not_configured") ? "not_configured" : "ready";
            })
            .catch(() => { if (current()) remoteState.value = "unavailable"; });
        await Promise.all([localTask, remoteTask]);
        if (current()) { clearTimeout(timer); loading.value = false; }
    }

    const categories = computed<ActionCenterCategory[]>(() => ACTION_CENTER_KEYS.map((key) => {
        const server = remote.value?.categories.find((category) => category.key === key);
        const category: ActionCenterCategory = server
            ? { ...server, items: [...server.items] }
            : { key, state: "unavailable", count: null, items: [], has_more: false };
        if (!localFirst) return category;
        const localCategory = local.value?.categories.find((entry) => entry.key === key);
        if (key === "unmatched_payments") {
            const serverHasMore = category.has_more;
            const hashes = new Set(local.value?.bankHashes ?? []);
            // Server copies can already be matched/ignored locally. Only trustworthy hashes
            // may suppress them; unresolved/truncated server evidence remains reviewable.
            const unseen = category.items.filter((item) => !item.bank_dedupe_hash || !hashes.has(item.bank_dedupe_hash))
                .map((item) => ({ ...item, kind: "remote_bank_review" }));
            category.items = [...(localCategory?.items ?? []), ...unseen];
            category.has_more ||= localCategory?.has_more ?? false;
            category.count = localState.value === "ready" && category.state !== "unavailable" && !serverHasMore
                ? (localCategory?.count ?? 0) + unseen.length : null;
            if (category.state === "not_configured" && localState.value === "ready") category.state = "ready";
            if (localState.value !== "ready") category.state = "unavailable";
        } else if (key === "failed_sends") {
            category.items = [...(localCategory?.items ?? []), ...category.items];
            category.has_more ||= localCategory?.has_more ?? false;
            category.count = localState.value === "ready" && category.state !== "unavailable"
                ? (localCategory?.count ?? 0) + (category.count ?? 0) : null;
            if (category.state === "not_configured" && localState.value === "ready") category.state = "ready";
            if (localState.value !== "ready") category.state = "unavailable";
        } else if (key === "synchronization") {
            const relay = dependencies.relayState?.value;
            if (relay && relay !== "ok" && relay !== "local_only") {
                const item: ActionCenterItem = { id: "device-sync", kind: `relay_${relay}`, reference: null,
                    document_id: null, occurred_at: null, amount: null, currency: null };
                category.items.push(item);
                if (category.state === "not_configured") { category.state = "ready"; category.count = 0; }
                if (category.count !== null) category.count++;
            }
        }
        return category;
    }));
    const partialError = computed(() => (localFirst && localState.value === "unavailable") || remoteState.value === "unavailable"
        || Boolean(remote.value?.categories.some((category) => category.state === "unavailable")));
    const allClear = computed(() => !loading.value && !partialError.value
        && categories.value.every((category) => category.state === "not_configured"
            || (category.state === "ready" && category.count === 0 && category.items.length === 0 && !category.has_more)));
    const checkedAt = computed(() => [local.value?.checkedAt, remote.value?.checked_at].filter((value): value is string => Boolean(value)).sort().at(-1) ?? null);
    const restrictedSources = computed(() => remote.value?.restricted_sources ?? []);

    watch(() => [companyId.value, localFirst ? companyShareRoutingKey(companyId.value) : ""], () => { void refresh(); }, { immediate: true, flush: "sync" });
    onScopeDispose(() => {
        disposed = true;
        generation++;
        controller?.abort();
        clearTimeout(timer);
        local.value = null;
        remote.value = null;
    });
    return { categories, loading, localState, remoteState, partialError, allClear, checkedAt, restrictedSources, refresh, localFirst };
}
