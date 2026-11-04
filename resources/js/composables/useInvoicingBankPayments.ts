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
import { ensureBridgeCompanyIdForLocalCompany } from "@/evolu/bridgeCompanyEnsure";
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
        loadRequest++;
        batchRequest++;
        loadController?.abort();
        batchController?.abort();
    });

    const inboundEmailAvailable = computed(() => companyId.value.trim().length > 0);

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
    const wiseBridgeChecked = ref(false);
    const wiseBridgeMissing = computed(() => false);
    let wiseStatusRequestSeq = 0;

    watch(companyId, () => {
        wiseStatusRequestSeq++;
        wiseStatus.value = null;
        wiseBridgeChecked.value = false;
    }, { flush: "sync" });

    /**
     * Apply Wise status only to the captured company and latest status request.
     * A stale failure must not clear the newly selected company's status.
     */
    async function fetchWiseStatus() {
        const requestSeq = ++wiseStatusRequestSeq;
        const cid = requireCompanyId();
        if (!cid) {
            wiseStatus.value = null;
            return;
        }
        try {
            const status = await invoicingApi.wise.status<WiseStatus>(cid);
            if (companyId.value === cid && requestSeq === wiseStatusRequestSeq) wiseStatus.value = status;
        } catch {
            if (companyId.value === cid && requestSeq === wiseStatusRequestSeq) wiseStatus.value = null;
        }
        if (companyId.value === cid && requestSeq === wiseStatusRequestSeq) wiseBridgeChecked.value = true;
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
    const inboundEmailAvailable = computed(() => bridgeCompanyId.value !== null);

    const wiseStatus = ref<WiseStatus | null>(null);
    const wiseBridgeChecked = ref(false);
    const wiseBridgeMissing = computed(
        () => wiseBridgeChecked.value && bridgeCompanyId.value === null,
    );
    let wiseStatusRequestSeq = 0;
    let bridgeLookupRequestSeq = 0;

    watch(companyId, () => {
        wiseStatusRequestSeq++;
        bridgeLookupRequestSeq++;
        wiseStatus.value = null;
        wiseBridgeChecked.value = false;
    }, { flush: "sync" });

    /**
     * Resolve the selected local company's server bridge without a first-company fallback.
     * Update the cache only for the latest lookup and honor caller freshness guards.
     */
    async function resolveBridgeCompanyId(stillCurrent?: () => boolean): Promise<string | null> {
        const requestSeq = ++bridgeLookupRequestSeq;
        const localCompanyId = companyId.value;
        const result = await ensureBridgeCompanyIdForLocalCompany(localCompanyId);
        if (companyId.value !== localCompanyId || (stillCurrent && !stillCurrent())) return null;
        const bridgeId = result.ok ? result.bridgeCompanyId : null;
        if (requestSeq === bridgeLookupRequestSeq) bridgeCompanyId.value = bridgeId;
        return bridgeId;
    }

    /** Fetch every email page with a per-request deadline; a failed page discards the partial set. */
    async function fetchInboundServerRows(bridgeId: string): Promise<ServerBankTransactionRow[]> {
        const rows: ServerBankTransactionRow[] = [];
        let page = 1;
        let lastPage = 1;
        do {
            const res = await invoicingApi.bankTransactions.list<ServerBankTransactionRow>(bridgeId, { source: "email", per_page: 100, page }, { timeout: 10_000, skipErrorFlash: true });
            rows.push(...res.data);
            lastPage = res.meta?.last_page ?? 1;
            page += 1;
        } while (page <= lastPage);
        return rows;
    }

    const inboundSyncs = new Map<string, Promise<void>>();

    /**
     * Share one in-flight inbound synchronization per local company.
     * Remove only the completed promise so newer work cannot be evicted.
     */
    async function syncInboundFromBridge() {
        const localCompanyId = companyId.value;
        const active = inboundSyncs.get(localCompanyId);
        if (active) return active;
        const pending = syncInboundCompany(localCompanyId);
        inboundSyncs.set(localCompanyId, pending);
        try {
            await pending;
        } finally {
            if (inboundSyncs.get(localCompanyId) === pending) inboundSyncs.delete(localCompanyId);
        }
    }

    /**
     * Fetch bridge email rows and import them only while that local company remains selected.
     * Only HTTP fetch failures are best-effort; local import failures and Unknown escape unchanged.
     */
    async function syncInboundCompany(localCompanyId: string) {
        const bridgeId = await resolveBridgeCompanyId();
        if (!bridgeId) {
            return;
        }
        let rows: ServerBankTransactionRow[];
        try {
            rows = await fetchInboundServerRows(bridgeId);
        } catch {
            // Bridge sync is best-effort; local data remains usable.
            return;
        }
        if (companyId.value === localCompanyId && rows.length > 0) {
            await importInboundServerTransactionsAsync(
                evolu,
                localCompanyId as CompanyId,
                rows,
            );
        }
    }

    async function load(filter = matchFilter.value) {
        matchFilter.value = filter;
        loading.value = true;
        try {
            await syncInboundFromBridge();
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

    async function loadBatches() {
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
        const requestSeq = ++wiseStatusRequestSeq;
        const localCompanyId = companyId.value;
        const bridgeId = await resolveBridgeCompanyId(() => requestSeq === wiseStatusRequestSeq);
        if (companyId.value !== localCompanyId || requestSeq !== wiseStatusRequestSeq) return;
        wiseBridgeChecked.value = true;
        if (!bridgeId) {
            wiseStatus.value = null;
            return;
        }
        try {
            const status = await invoicingApi.wise.status<WiseStatus>(bridgeId);
            if (companyId.value === localCompanyId && requestSeq === wiseStatusRequestSeq) wiseStatus.value = status;
        } catch {
            if (companyId.value === localCompanyId && requestSeq === wiseStatusRequestSeq) wiseStatus.value = null;
        }
    }

    async function connectWise(token: string) {
        const bridgeId = await resolveBridgeCompanyId();
        if (!bridgeId) return;
        await invoicingApi.wise.connect(bridgeId, token);
        await fetchWiseStatus();
    }

    /**
     * Fetch Wise rows through the selected company's bridge and import them locally.
     * Discard a late result after a company switch; preserve the existing Wise matching policy.
     */
    async function syncWise() {
        const localCompanyId = companyId.value;
        const bridgeId = await resolveBridgeCompanyId();
        if (!bridgeId) {
            return { imported: 0, auto_matched: 0, skipped_duplicates: 0 };
        }
        const syncResult = await invoicingApi.wise.sync<{
            rows?: WiseBankRowInput[];
            skipped_duplicates?: number;
        }>(bridgeId, {
            local_first: true,
        });
        if (companyId.value !== localCompanyId) {
            return { imported: 0, auto_matched: 0, skipped_duplicates: 0 };
        }
        const rows = syncResult?.rows || [];
        let autoMatched = 0;
        if (rows.length > 0) {
            const result = await importLocalBankRows(
                evolu,
                localCompanyId as CompanyId,
                rows,
                "wise",
                "wise-sync.json",
            );
            autoMatched = result.auto_matched;
        }
        await load(matchFilter.value);
        await loadBatches();
        return {
            imported: rows.length,
            auto_matched: autoMatched,
            skipped_duplicates: syncResult?.skipped_duplicates ?? 0,
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

    watch(companyId, () => {
        bridgeCompanyId.value = null;
        // CompanyPayments owns the awaited refresh and its visible error path.
    });

    return {
        localFirst: true,
        evolu,
        loading,
        transactions,
        summary,
        batches,
        inboundEmailAvailable,
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
