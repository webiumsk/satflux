import { toAppRows } from "@/evolu/queryLoad";
import type { Evolu } from "@evolu/common/local-first";
import { computed, onScopeDispose, ref, watch, type ComputedRef, type Ref } from "vue";
import { isCancel } from "axios";
import { useQuery } from "@evolu/vue";
import { invoicingApi } from "@/services/api";
import {
    allBankImportBatchesQuery,
    allBankTransactionMatchesQuery,
    allBankTransactionsQuery,
    allCompaniesDetailQuery,
    allDocumentsQuery,
    allExpensesQuery,
    useInvoicingEvolu,
} from "@/evolu/client";
import {
    autoMatchLocalBatchAsync,
    bankMatchSuggestions,
    buildBankSummary,
    createLocalExpenseFromBankTransaction,
    filterLocalBankTransactions,
    ignoreLocalBankTransaction,
    importInboundServerTransactionsAsync,
    importLocalBankFileAsync,
    importLocalBankRows,
    type WiseBankRowInput,
    loadBankPaymentContext,
    manualMatchLocalTransaction,
    mapLocalBankBatchesToApi,
    mapLocalBankTransactionsToApi,
    unmatchLocalBankTransaction,
    type BankPaymentContext,
    type ServerBankTransactionRow,
} from "@/evolu/bankCrud";
import type { BankBatchApiRow, BankSummary, BankTxApiRow } from "@/evolu/bankMap";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import { ensureBridgeCompanyIdForLocalCompany, type EnsureBridgeCompanyResult } from "@/evolu/bridgeCompanyEnsure";
import { companyShareRoutingKey, waitCompanyShareRegistryReady } from "@/evolu/companyShareRegistry";
import { normalizeCompanyIdentityKey } from "@/evolu/duplicateCompanies";
import { withBankReadBudget } from "@/evolu/bankReadBudget";
import type { BridgeCompanyRequestOptions } from "@/evolu/numberAllocatorBridge";
import type { ExpenseSavePayload } from "@/evolu/expenseCrud";
import type {
    BankImportBatchId,
    BankTransactionId,
    CompanyId,
    DocumentId,
    InvoicingLocalSchema,
} from "@/evolu/schema";
import type { BankExpenseDraft } from "@/components/invoicing/BankCreateExpenseModal.vue";

export type BankMatchSuggestion = {
    document: { id: string; number?: string | null; total: string; currency: string };
    reason: string;
};

export type WiseStatus = {
    connected: boolean;
    profile_id?: number | null;
    balance_id?: number | null;
    balance_currency?: string | null;
    last_sync_at?: string | null;
    api_available?: boolean;
};

export interface UseInvoicingBankPaymentsResult {
    localFirst: boolean;
    evolu: Evolu<InvoicingLocalSchema> | null;
    loading: Ref<boolean>;
    transactions: Ref<BankTxApiRow[]> | ComputedRef<BankTxApiRow[]>;
    summary: Ref<BankSummary> | ComputedRef<BankSummary>;
    batches: Ref<BankBatchApiRow[]> | ComputedRef<BankBatchApiRow[]>;
    inboundEmailAvailable: ComputedRef<boolean>;
    bridgeLookupFailed: Ref<boolean>;
    bridgeRoutingKey: ComputedRef<string>;
    load: (matchFilter?: string, options?: { skipErrorFlash?: boolean }) => Promise<void>;
    loadBatches: (options?: { skipErrorFlash?: boolean }) => Promise<void>;
    loadInbound: () => Promise<void>;
    importFile: (file: File, format?: string) => Promise<{ imported: number; auto_matched: number }>;
    autoMatchBatch: (batchId: string) => Promise<void>;
    fetchSuggestions: (transactionId: string) => Promise<BankMatchSuggestion[]>;
    matchTransaction: (transactionId: string, documentId: string) => Promise<void>;
    ignoreTransaction: (transactionId: string) => Promise<void>;
    unmatchTransaction: (transactionId: string) => Promise<void>;
    createExpenseFromTransaction: (transactionId: string, draft: BankExpenseDraft) => Promise<void>;
    wiseStatus: Ref<WiseStatus | null>;
    wiseStatusFailed: Ref<boolean>;
    wiseBridgeChecked: Ref<boolean>;
    wiseBridgeMissing: ComputedRef<boolean>;
    fetchWiseStatus: () => Promise<void>;
    connectWise: (token: string) => Promise<void>;
    syncWise: () => Promise<{ imported: number; auto_matched: number; skipped_duplicates: number }>;
}

function emptySummary(defaultCurrency = "EUR"): BankSummary {
    return {
        credit_count: 0,
        credit_total: "0.00",
        debit_count: 0,
        debit_total: "0.00",
        balance: "0.00",
        currency: defaultCurrency,
        by_currency: [],
    };
}

function useServerInvoicingBankPayments(
    companyId: Ref<string>,
    defaultCurrency: Ref<string>,
): UseInvoicingBankPaymentsResult {
    const loading = ref(true);
    const transactions = ref<BankTxApiRow[]>([]);
    const summary = ref<BankSummary>(emptySummary(defaultCurrency.value));
    const batches = ref<BankBatchApiRow[]>([]);
    const inboundEmail = ref("");
    let lastFilter = "all";
    let loadRequest = 0;
    let batchRequest = 0;
    let loadController: AbortController | null = null;
    let batchController: AbortController | null = null;
    let disposed = false;

    watch(companyId, () => {
        loadRequest++;
        batchRequest++;
        loadController?.abort();
        batchController?.abort();
        loadController = null;
        batchController = null;
        transactions.value = [];
        batches.value = [];
        summary.value = emptySummary(defaultCurrency.value);
        loading.value = false;
    }, { flush: "sync" });

    onScopeDispose(() => {
        disposed = true;
        wiseStatusRequestSeq++;
        wiseStatusFailed.value = false;
        loadRequest++;
        batchRequest++;
        loadController?.abort();
        batchController?.abort();
    });

    const inboundEmailAvailable = computed(() => companyId.value.trim().length > 0);
    const bridgeLookupFailed = ref(false);
    const bridgeRoutingKey = computed(() => companyId.value);

    function requireCompanyId(): string | null {
        return companyId.value || null;
    }

    /**
     * Load server rows for the captured company and request generation.
     * Abort superseded transport requests but retain guards for adapters that ignore cancellation.
     * Only Axios cancellation is consumed; ordinary failures retain caller/global error handling.
     */
    async function load(matchFilter = lastFilter, options: { skipErrorFlash?: boolean } = {}) {
        const request = ++loadRequest;
        loadController?.abort();
        const controller = new AbortController();
        loadController = controller;
        const cid = requireCompanyId();
        if (!cid) {
            transactions.value = [];
            summary.value = emptySummary(defaultCurrency.value);
            loading.value = false;
            loadController = null;
            return;
        }
        lastFilter = matchFilter;
        loading.value = true;
        try {
            const params: Record<string, string | number> = { per_page: 50 };
            if (matchFilter !== "all") params.match_status = matchFilter;
            const res = await invoicingApi.bankTransactions.list<BankTxApiRow>(cid, params, { ...options, signal: controller.signal });
            if (companyId.value !== cid || request !== loadRequest) return;
            transactions.value = res.data;
            summary.value = (res.meta?.summary as typeof summary.value) || emptySummary(defaultCurrency.value);
        } catch (error) {
            if (!isCancel(error)) throw error;
        } finally {
            if (companyId.value === cid && request === loadRequest) loading.value = false;
            if (loadController === controller) loadController = null;
        }
    }

    /**
     * Load only the latest selected-company batches and abort replaced requests.
     * Keep generation checks even when transport cancellation is ignored.
     */
    async function loadBatches(options: { skipErrorFlash?: boolean } = {}) {
        const request = ++batchRequest;
        batchController?.abort();
        const controller = new AbortController();
        batchController = controller;
        const cid = requireCompanyId();
        if (!cid) {
            batches.value = [];
            batchController = null;
            return;
        }
        try {
            const rows = await invoicingApi.bankTransactions.batches<BankBatchApiRow>(cid, { ...options, signal: controller.signal });
            if (companyId.value === cid && request === batchRequest) batches.value = rows;
        } catch (error) {
            if (!isCancel(error)) throw error;
        } finally {
            if (batchController === controller) batchController = null;
        }
    }

    async function loadInbound() {
        const cid = requireCompanyId();
        if (!cid) {
            inboundEmail.value = "";
            return;
        }
        try {
            const inbound = await invoicingApi.bankTransactions.inboundEmail<{ address?: string }>(cid);
            inboundEmail.value = inbound?.address || "";
        } catch {
            inboundEmail.value = "";
        }
    }

    async function importFile(file: File, format?: string) {
        const cid = requireCompanyId();
        if (!cid) {
            return { imported: 0, auto_matched: 0 };
        }
        const form = new FormData();
        form.append("file", file);
        if (format) form.append("format", format);
        const result = await invoicingApi.bankTransactions.import<{ imported: number; auto_matched: number }>(cid, form);
        return {
            imported: result.imported,
            auto_matched: result.auto_matched,
        };
    }

    /**
     * Run the server batch action for the selected company.
     * The page owns error reporting and any subsequent refresh.
     */
    async function autoMatchBatch(batchId: string) {
        const cid = requireCompanyId();
        if (!cid) return;
        await invoicingApi.bankTransactions.autoMatchBatch(cid, batchId, { skipErrorFlash: true });
    }

    async function fetchSuggestions(transactionId: string): Promise<BankMatchSuggestion[]> {
        const cid = requireCompanyId();
        if (!cid) return [];
        return invoicingApi.bankTransactions.suggestions<BankMatchSuggestion>(cid, transactionId);
    }

    /**
     * Submit a manual server match without performing an implicit reload.
     * Suppress the global flash so the calling page can scope action errors.
     */
    async function matchTransaction(transactionId: string, documentId: string) {
        const cid = requireCompanyId();
        if (!cid) return;
        await invoicingApi.bankTransactions.match(cid, transactionId, documentId, { skipErrorFlash: true });
    }

    /**
     * Ignore the selected server movement; the caller handles errors and refresh.
     */
    async function ignoreTransaction(transactionId: string) {
        const cid = requireCompanyId();
        if (!cid) return;
        await invoicingApi.bankTransactions.ignore(cid, transactionId, { skipErrorFlash: true });
    }

    /**
     * Remove a server match; keep action failure separate from refresh failure.
     */
    async function unmatchTransaction(transactionId: string) {
        const cid = requireCompanyId();
        if (!cid) return;
        await invoicingApi.bankTransactions.unmatch(cid, transactionId, { skipErrorFlash: true });
    }

    async function createExpenseFromTransaction(transactionId: string, draft: BankExpenseDraft) {
        const cid = requireCompanyId();
        if (!cid) return;
        await invoicingApi.bankTransactions.createExpense(cid, transactionId, {
                title: draft.title,
                supplier: draft.supplier || undefined,
                category: draft.category || undefined,
                variable_symbol: draft.variable_symbol || undefined,
                total: draft.total,
                issue_date: draft.issue_date,
                internal_note: draft.internal_note || undefined,
                mark_paid: true,
            },
        );
    }

    const wiseStatus = ref<WiseStatus | null>(null);
    const wiseStatusFailed = ref(false);
    const wiseBridgeChecked = ref(false);
    const wiseBridgeMissing = computed(() => false);
    let wiseStatusRequestSeq = 0;

    watch(companyId, () => {
        wiseStatusRequestSeq++;
        wiseStatus.value = null;
        wiseStatusFailed.value = false;
        wiseBridgeChecked.value = false;
    }, { flush: "sync" });

    /**
     * Apply Wise status only to the captured company and latest status request.
     * A stale failure must not clear the newly selected company's status.
     */
    async function fetchWiseStatus() {
        if (disposed) return;
        const requestSeq = ++wiseStatusRequestSeq;
        const cid = requireCompanyId();
        if (!cid) {
            wiseStatus.value = null;
            return;
        }
        const current = () => !disposed && companyId.value === cid && requestSeq === wiseStatusRequestSeq;
        try {
            const status = await invoicingApi.wise.status<WiseStatus>(cid);
            if (current()) { wiseStatus.value = status; wiseStatusFailed.value = false; }
        } catch {
            if (current()) { wiseStatus.value = null; wiseStatusFailed.value = true; }
        }
        if (current()) wiseBridgeChecked.value = true;
    }

    async function connectWise(token: string) {
        const cid = requireCompanyId();
        if (!cid) return;
        await invoicingApi.wise.connect(cid, token);
        await fetchWiseStatus();
    }

    async function syncWise() {
        const cid = requireCompanyId();
        if (!cid) {
            return { imported: 0, auto_matched: 0, skipped_duplicates: 0 };
        }
        const result = await invoicingApi.wise.sync<{ imported?: number; auto_matched?: number; skipped_duplicates?: number }>(cid, {});
        await load(lastFilter);
        await loadBatches();
        return {
            imported: result.imported ?? 0,
            auto_matched: result.auto_matched ?? 0,
            skipped_duplicates: result.skipped_duplicates ?? 0,
        };
    }

    return {
        localFirst: false,
        evolu: null,
        loading,
        transactions,
        summary,
        batches,
        inboundEmailAvailable,
        bridgeLookupFailed,
        bridgeRoutingKey,
        load,
        loadBatches,
        loadInbound,
        importFile,
        autoMatchBatch,
        fetchSuggestions,
        matchTransaction,
        ignoreTransaction,
        unmatchTransaction,
        createExpenseFromTransaction,
        wiseStatus,
        wiseStatusFailed,
        wiseBridgeChecked,
        wiseBridgeMissing,
        fetchWiseStatus,
        connectWise,
        syncWise,
    };
}

function useLocalInvoicingBankPayments(
    companyId: Ref<string>,
    defaultCurrency: Ref<string>,
): UseInvoicingBankPaymentsResult {
    const evolu = useInvoicingEvolu();
    const loading = ref(true);
    const matchFilter = ref("all");

    // Per-query promises: useQuery's `promise` option is typed per query,
    // so hand each one its own (Promise.all only gates the loading flag).
    const companyPromise = evolu.loadQuery(allCompaniesDetailQuery);
    const companyRows = useQuery(allCompaniesDetailQuery, { promise: companyPromise.catch(() => []) });
    const selectedCompanyRows = computed(() => companyRows.value.filter((row) => row.id === companyId.value));
    const selectedMetadataKey = computed(() => {
        const rows = selectedCompanyRows.value;
        const row = rows[0];
        return JSON.stringify([rows.length, rows.length === 1 && "ownerId" in row ? row.ownerId : null]);
    });
    const bridgeIdentityKey = computed(() => {
        const company = selectedCompanyRows.value[0];
        return company?.legalName?.trim()
            ? normalizeCompanyIdentityKey(company.legalName, company.registrationNumber)
            : null;
    });
    const selectedShareRoutingKey = computed(() => companyShareRoutingKey(companyId.value));
    const bridgeRoutingKey = computed(() => JSON.stringify([companyId.value, bridgeIdentityKey.value, selectedMetadataKey.value, selectedShareRoutingKey.value]));
    const bankTxPromise = evolu.loadQuery(allBankTransactionsQuery);
    const bankMatchPromise = evolu.loadQuery(allBankTransactionMatchesQuery);
    const documentsPromise = evolu.loadQuery(allDocumentsQuery);
    const expensesPromise = evolu.loadQuery(allExpensesQuery);
    const batchesPromise = evolu.loadQuery(allBankImportBatchesQuery);
    const loadPromise = Promise.all([
        bankTxPromise,
        bankMatchPromise,
        documentsPromise,
        expensesPromise,
        batchesPromise,
    ]);
    void loadPromise.finally(() => {
        loading.value = false;
    });

    const bankTxRows = useQuery(allBankTransactionsQuery, { promise: bankTxPromise });
    const bankMatchRows = useQuery(allBankTransactionMatchesQuery, { promise: bankMatchPromise });
    const documentRows = useQuery(allDocumentsQuery, { promise: documentsPromise });
    const expenseRows = useQuery(allExpensesQuery, { promise: expensesPromise });
    const batchRows = useQuery(allBankImportBatchesQuery, { promise: batchesPromise });

    const ctx = computed((): BankPaymentContext => ({
        batches: toAppRows<BankPaymentContext["batches"][number]>(batchRows.value),
        transactions: toAppRows<BankPaymentContext["transactions"][number]>(bankTxRows.value),
        matches: toAppRows<BankPaymentContext["matches"][number]>(bankMatchRows.value),
        documents: toAppRows<BankPaymentContext["documents"][number]>(documentRows.value),
        expenses: toAppRows<BankPaymentContext["expenses"][number]>(expenseRows.value),
    }));

    const filteredRows = computed(() =>
        filterLocalBankTransactions(
            toAppRows<BankPaymentContext["transactions"][number]>(bankTxRows.value),
            companyId.value as CompanyId,
            matchFilter.value === "all" ? undefined : matchFilter.value,
        ).slice(0, 50),
    );

    const transactions = computed(() =>
        mapLocalBankTransactionsToApi(filteredRows.value, ctx.value),
    );

    const summary = computed(() =>
        buildBankSummary(
            filterLocalBankTransactions(
                toAppRows<BankPaymentContext["transactions"][number]>(bankTxRows.value),
                companyId.value as CompanyId,
            ),
        ),
    );

    const batches = computed(() =>
        mapLocalBankBatchesToApi(
            toAppRows<BankPaymentContext["batches"][number]>(batchRows.value),
            companyId.value as CompanyId,
            ctx.value.transactions,
        ),
    );

    const bridgeCompanyId = ref<string | null>(null);
    const bridgeLookupFailed = ref(false);
    const inboundEmailAvailable = computed(() => bridgeCompanyId.value !== null);

    const wiseStatus = ref<WiseStatus | null>(null);
    const wiseStatusFailed = ref(false);
    const wiseBridgeChecked = ref(false);
    const wiseBridgeMissing = computed(
        () => wiseBridgeChecked.value && !bridgeLookupFailed.value && bridgeCompanyId.value === null,
    );
    let wiseStatusRequestSeq = 0;
    let bridgeResolutionRequestSeq = 0;

    let bridgeIdentityGeneration = 0;
    let companySelectionGeneration = 0;
    let disposed = false;
    const activeBankReads = new Set<() => void>();
    onScopeDispose(() => {
        disposed = true;
        bridgeIdentityGeneration++;
        companySelectionGeneration++;
        bridgeResolutionRequestSeq++;
        wiseStatusRequestSeq++;
        bridgeCompanyId.value = null;
        bridgeLookupFailed.value = false;
        wiseStatus.value = null;
        wiseStatusFailed.value = false;
        wiseBridgeChecked.value = false;
        for (const cancelFetch of activeBankReads) cancelFetch();
        activeBankReads.clear();
    });
    watch([companyId, bridgeIdentityKey, selectedMetadataKey, selectedShareRoutingKey],
        ([currentCompany, , currentMetadata, currentRouting], [previousCompany, previousIdentity, previousMetadata, previousRouting]) => {
        bridgeIdentityGeneration++;
        bridgeCompanyId.value = null;
        bridgeLookupFailed.value = false;
        wiseStatusFailed.value = false;
        // Initial metadata readiness has no prior eligible lookup to invalidate.
        if (currentCompany !== previousCompany) companySelectionGeneration++;
        if (currentCompany !== previousCompany || previousIdentity !== null || currentRouting !== previousRouting
            || (previousMetadata !== "[0,null]" && currentMetadata !== previousMetadata)) {
            wiseStatusRequestSeq++;
            wiseStatus.value = null;
            wiseBridgeChecked.value = false;
        }
        // CompanyPayments owns the awaited refresh and its visible error path.
    }, { flush: "sync" });

    /** Register only read-stage cancellation; credential POSTs and financial imports never enter this set. */
    function registerBankRead(cancel: () => void): () => void {
        activeBankReads.add(cancel);
        return () => { activeBankReads.delete(cancel); };
    }

    type BridgeResolution = EnsureBridgeCompanyResult & { isCurrent: () => boolean };
    /**
     * Bound both metadata waits and bridge HTTP, preserving a context-only guard after read cleanup.
     * Capture selection before readiness so A-B-A navigation cannot revive an older action.
     */
    async function resolveBridgeCompanyId(
        stillCurrent?: () => boolean,
        requestOptions?: BridgeCompanyRequestOptions,
    ): Promise<BridgeResolution | null> {
        if (disposed) return null;
        const localCompanyId = companyId.value;
        const selection = companySelectionGeneration;
        const requestSeq = ++bridgeResolutionRequestSeq;
        let current = () => !disposed && companyId.value === localCompanyId
            && companySelectionGeneration === selection && (!stillCurrent || stillCurrent());
        const read = async (controls: BridgeCompanyRequestOptions): Promise<BridgeResolution | null> => {
            controls();
            if (bridgeIdentityKey.value === null) await companyPromise.catch(() => []);
            controls();
            if (!current()) return null;
            const routing = bridgeRoutingKey.value;
            const generation = bridgeIdentityGeneration;
            current = () => !disposed && companyId.value === localCompanyId && companySelectionGeneration === selection
                && bridgeRoutingKey.value === routing && bridgeIdentityGeneration === generation
                && (!stillCurrent || stillCurrent());
            const result = await ensureBridgeCompanyIdForLocalCompany(localCompanyId, controls, { preferRecordedBridgeId: true });
            controls();
            if (!current()) return null;
            if (requestSeq === bridgeResolutionRequestSeq) {
                bridgeLookupFailed.value = !result.ok;
                bridgeCompanyId.value = result.ok ? result.bridgeCompanyId : null;
            }
            return { ...result, isCurrent: () => current() };
        };
        try {
            return requestOptions ? await read(requestOptions)
                : await withBankReadBudget((controls) => read(controls), { isCurrent: () => current(), registerCancel: registerBankRead });
        } catch {
            if (!current()) return null;
            if (requestSeq === bridgeResolutionRequestSeq) {
                bridgeLookupFailed.value = true;
                bridgeCompanyId.value = null;
            }
            return { ok: false, isCurrent: () => current() };
        }
    }

    /** Fetch complete pages within the caller's budget, yielding so immediate pages cannot starve its timer. */
    async function fetchInboundServerRows(
        bridgeId: string,
        requestOptions: BridgeCompanyRequestOptions,
    ): Promise<ServerBankTransactionRow[]> {
        const rows: ServerBankTransactionRow[] = [];
        let page = 1;
        let lastPage = 1;
        do {
            const res = await invoicingApi.bankTransactions.list<ServerBankTransactionRow>(bridgeId,
                { source: "email", per_page: 100, page }, requestOptions());
            requestOptions(); // Reject late responses even when a transport ignores abort.
            rows.push(...res.data);
            lastPage = res.meta?.last_page ?? 1;
            page += 1;
            if (page <= lastPage) await new Promise<void>((resolve) => setTimeout(resolve, 0));
        } while (page <= lastPage);
        return rows;
    }

    const inboundSyncs = new Map<string, Promise<void>>();

    /** Share one attempt per selection and identity generation; obsolete completion cannot evict newer work. */
    async function syncInboundFromBridge() {
        if (disposed) return;
        const localCompanyId = companyId.value;
        const syncKey = JSON.stringify([localCompanyId, companySelectionGeneration, bridgeIdentityGeneration]);
        const active = inboundSyncs.get(syncKey);
        if (active) return active;
        const pending = syncInboundCompany(localCompanyId);
        inboundSyncs.set(syncKey, pending);
        try {
            await pending;
        } finally {
            if (inboundSyncs.get(syncKey) === pending) inboundSyncs.delete(syncKey);
        }
    }

    /**
     * Bound metadata, bridge HTTP and pagination together to twenty seconds before any financial import.
     * Disposal cancels only this read stage; started financial imports and Unknown are never raced or canceled.
     */
    async function syncInboundCompany(localCompanyId: string) {
        if (disposed) return;
        const selectionGeneration = companySelectionGeneration;
        let current = () => !disposed && companyId.value === localCompanyId
            && companySelectionGeneration === selectionGeneration;
        let rows: ServerBankTransactionRow[];
        try {
            rows = await withBankReadBudget(async (controls, signal) => {
                await companyPromise.catch(() => []);
                await waitCompanyShareRegistryReady(signal);
                controls();
                const generation = bridgeIdentityGeneration;
                const routing = bridgeRoutingKey.value;
                current = () => !disposed && companyId.value === localCompanyId
                    && companySelectionGeneration === selectionGeneration && bridgeRoutingKey.value === routing
                    && bridgeIdentityGeneration === generation;
                const bridge = await resolveBridgeCompanyId(current, controls);
                controls();
                return bridge?.ok && bridge.bridgeCompanyId && bridge.isCurrent()
                    ? await fetchInboundServerRows(bridge.bridgeCompanyId, controls) : [];
            }, { isCurrent: () => current(), registerCancel: registerBankRead });
        } catch {
            // Read-only bridge sync is best-effort; local data remains usable.
            return;
        }
        if (current() && rows.length > 0) {
            await importInboundServerTransactionsAsync(evolu, localCompanyId as CompanyId, rows);
        }
    }

    /** Await inbound completion and refresh rows only while this hook remains mounted. */
    async function load(filter = matchFilter.value) {
        if (disposed) return;
        matchFilter.value = filter;
        loading.value = true;
        try {
            await syncInboundFromBridge();
            if (disposed) return;
            await Promise.all([
                evolu.loadQuery(allBankTransactionsQuery),
                evolu.loadQuery(allBankTransactionMatchesQuery),
                evolu.loadQuery(allDocumentsQuery),
                evolu.loadQuery(allExpensesQuery),
            ]);
        } finally {
            loading.value = false;
        }
    }

    /** Refresh batch metadata for a live hook; disposed continuations perform no new reads. */
    async function loadBatches() {
        if (disposed) return;
        await evolu.loadQuery(allBankImportBatchesQuery);
    }

    async function loadInbound() {
        await resolveBridgeCompanyId();
    }

    /**
     * Resolve and query Wise for the captured local company and latest generation.
     * Ignore both successful and failed responses after a company or request change.
     */
    async function fetchWiseStatus() {
        if (disposed) return;
        const requestSeq = ++wiseStatusRequestSeq;
        const localCompanyId = companyId.value;
        const stage: { bridge: BridgeResolution | null; statusStarted: boolean } = { bridge: null, statusStarted: false };
        const current = () => !disposed && companyId.value === localCompanyId && requestSeq === wiseStatusRequestSeq;
        try {
            const status = await withBankReadBudget(async (controls) => {
                const bridge = await resolveBridgeCompanyId(current, controls);
                stage.bridge = bridge;
                controls();
                if (!bridge || !bridge.isCurrent()) return null;
                wiseBridgeChecked.value = bridge.ok;
                if (!bridge.ok || !bridge.bridgeCompanyId) { wiseStatus.value = null; return null; }
                stage.statusStarted = true;
                const result = await invoicingApi.wise.status<WiseStatus>(bridge.bridgeCompanyId, controls());
                controls();
                return result;
            }, { isCurrent: current, registerCancel: registerBankRead });
            if (current() && stage.bridge?.isCurrent() && stage.statusStarted) {
                wiseStatus.value = status;
                wiseStatusFailed.value = false;
            }
        } catch {
            if (current() && (!stage.bridge || stage.bridge.isCurrent())) {
                if (stage.statusStarted) { wiseStatus.value = null; wiseStatusFailed.value = true; }
                else { bridgeLookupFailed.value = true; wiseBridgeChecked.value = false; }
            }
        }
    }

    /** Reject unavailable routes before dispatch; preserve entered POST outcomes and refresh only their captured context. */
    async function connectWise(token: string) {
        const generation = bridgeIdentityGeneration;
        const routing = bridgeRoutingKey.value;
        const bridge = await resolveBridgeCompanyId(() => bridgeIdentityGeneration === generation && bridgeRoutingKey.value === routing);
        if (!bridge?.ok || !bridge.bridgeCompanyId || !bridge.isCurrent()) throw new Error("bank_bridge_lookup_failed");
        await invoicingApi.wise.connect(bridge.bridgeCompanyId, token);
        if (bridge.isCurrent()) await fetchWiseStatus();
    }

    /**
     * Fetch Wise rows through the selected company's bridge and import them locally.
     * Discard stale responses, await started imports, and refresh only their captured routing context.
     */
    async function syncWise() {
        const localCompanyId = companyId.value;
        const bridge = await resolveBridgeCompanyId();
        if (bridge && !bridge.ok && bridge.isCurrent()) throw new Error("bank_bridge_lookup_failed");
        if (!bridge?.ok || !bridge.bridgeCompanyId || !bridge.isCurrent()) {
            return { imported: 0, auto_matched: 0, skipped_duplicates: 0 };
        }
        const syncResult = await invoicingApi.wise.sync<{
            rows?: WiseBankRowInput[];
            skipped_duplicates?: number;
        }>(bridge.bridgeCompanyId, {
            local_first: true,
        });
        if (disposed || companyId.value !== localCompanyId || !bridge.isCurrent()) {
            return { imported: 0, auto_matched: 0, skipped_duplicates: 0 };
        }
        const rows = syncResult?.rows || [];
        let imported = 0;
        let autoMatched = 0;
        let skippedDuplicates = syncResult?.skipped_duplicates ?? 0;
        if (rows.length > 0) {
            const result = await importLocalBankRows(
                evolu,
                localCompanyId as CompanyId,
                rows,
                "wise",
                "wise-sync.json",
            );
            imported = result.imported;
            autoMatched = result.auto_matched;
            skippedDuplicates += result.skipped_duplicates ?? 0;
        }
        if (bridge.isCurrent()) {
            await load(matchFilter.value);
            if (bridge.isCurrent()) await loadBatches();
        }
        return {
            imported,
            auto_matched: autoMatched,
            skipped_duplicates: skippedDuplicates,
        };
    }

    async function importFile(file: File, format?: string) {
        const result = await importLocalBankFileAsync(
            evolu,
            companyId.value as CompanyId,
            file,
            format,
        );
        await load(matchFilter.value);
        await loadBatches();
        return { imported: result.imported, auto_matched: result.auto_matched };
    }

    async function autoMatchBatch(batchId: string) {
        await autoMatchLocalBatchAsync(evolu, batchId as BankImportBatchId);
    }

    async function fetchSuggestions(transactionId: string): Promise<BankMatchSuggestion[]> {
        const paymentCtx = await loadBankPaymentContext(evolu);
        const transaction = paymentCtx.transactions.find((row) => row.id === transactionId);
        if (!transaction) return [];
        return bankMatchSuggestions(transaction, paymentCtx.documents).map((row) => ({
            document: {
                id: row.document.id,
                number: row.document.number,
                total: row.document.total || "0",
                currency: row.document.currency || defaultCurrency.value,
            },
            reason: row.reason,
        }));
    }

    async function matchTransaction(transactionId: string, documentId: string) {
        const paymentCtx = await loadBankPaymentContext(evolu);
        await manualMatchLocalTransaction(
            evolu,
            transactionId as BankTransactionId,
            documentId as DocumentId,
            paymentCtx,
        );
    }

    async function ignoreTransaction(transactionId: string) {
        const paymentCtx = await loadBankPaymentContext(evolu);
        await ignoreLocalBankTransaction(evolu, transactionId as BankTransactionId, paymentCtx);
    }

    async function unmatchTransaction(transactionId: string) {
        const paymentCtx = await loadBankPaymentContext(evolu);
        await unmatchLocalBankTransaction(evolu, transactionId as BankTransactionId, paymentCtx);
    }

    /**
     * Await guarded local expense creation before refreshing the selected company.
     * Do not publish the refresh into a company selected while the write was pending.
     */
    async function createExpenseFromTransaction(transactionId: string, draft: BankExpenseDraft) {
        const localCompanyId = companyId.value;
        const paymentCtx = await loadBankPaymentContext(evolu);
        const payload: ExpenseSavePayload & { supplier?: string; category?: string } = {
            title: draft.title,
            supplier: draft.supplier,
            category: draft.category,
            variable_symbol: draft.variable_symbol || undefined,
            total: draft.total,
            issue_date: draft.issue_date,
            internal_note: draft.internal_note || undefined,
            mark_paid: true,
        };
        await createLocalExpenseFromBankTransaction(
            evolu,
            transactionId as BankTransactionId,
            payload,
            paymentCtx,
        );
        if (companyId.value !== localCompanyId) return;
        await load(matchFilter.value);
    }

    return {
        localFirst: true,
        evolu,
        loading,
        transactions,
        summary,
        batches,
        inboundEmailAvailable,
        bridgeLookupFailed,
        bridgeRoutingKey,
        load,
        loadBatches,
        loadInbound,
        importFile,
        autoMatchBatch,
        fetchSuggestions,
        matchTransaction,
        ignoreTransaction,
        unmatchTransaction,
        createExpenseFromTransaction,
        wiseStatus,
        wiseStatusFailed,
        wiseBridgeChecked,
        wiseBridgeMissing,
        fetchWiseStatus,
        connectWise,
        syncWise,
    };
}

export function useInvoicingBankPayments(
    companyId: Ref<string>,
    defaultCurrency: Ref<string>,
): UseInvoicingBankPaymentsResult {
    if (isInvoicingLocalFirst()) {
        return useLocalInvoicingBankPayments(companyId, defaultCurrency);
    }
    return useServerInvoicingBankPayments(companyId, defaultCurrency);
}
