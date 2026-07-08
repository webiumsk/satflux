import type { Evolu } from "@evolu/common/local-first";
import { computed, ref, watch, type ComputedRef, type Ref } from "vue";
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
import { resolveEphemeralBridgeCompanyId } from "@/evolu/ephemeralBridge";
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
    load: (matchFilter?: string) => Promise<void>;
    loadBatches: () => Promise<void>;
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

    const inboundEmailAvailable = computed(() => companyId.value.trim().length > 0);

    function requireCompanyId(): string | null {
        return companyId.value || null;
    }

    async function load(matchFilter = lastFilter) {
        const cid = requireCompanyId();
        if (!cid) {
            transactions.value = [];
            summary.value = emptySummary(defaultCurrency.value);
            loading.value = false;
            return;
        }
        lastFilter = matchFilter;
        loading.value = true;
        try {
            const params: Record<string, string | number> = { per_page: 50 };
            if (matchFilter !== "all") params.match_status = matchFilter;
            const res = await invoicingApi.bankTransactions.list<ServerBankTransactionRow>(cid, params);
            transactions.value = res.data;
            summary.value = (res.meta?.summary as typeof summary.value) || emptySummary(defaultCurrency.value);
        } finally {
            loading.value = false;
        }
    }

    async function loadBatches() {
        const cid = requireCompanyId();
        if (!cid) {
            batches.value = [];
            return;
        }
        batches.value = await invoicingApi.bankTransactions.batches(cid);
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

    async function autoMatchBatch(batchId: string) {
        const cid = requireCompanyId();
        if (!cid) return;
        await invoicingApi.bankTransactions.autoMatchBatch(cid, batchId);
    }

    async function fetchSuggestions(transactionId: string): Promise<BankMatchSuggestion[]> {
        const cid = requireCompanyId();
        if (!cid) return [];
        return invoicingApi.bankTransactions.suggestions<BankMatchSuggestion>(cid, transactionId);
    }

    async function matchTransaction(transactionId: string, documentId: string) {
        const cid = requireCompanyId();
        if (!cid) return;
        await invoicingApi.bankTransactions.match(cid, transactionId, documentId);
    }

    async function ignoreTransaction(transactionId: string) {
        const cid = requireCompanyId();
        if (!cid) return;
        await invoicingApi.bankTransactions.ignore(cid, transactionId);
    }

    async function unmatchTransaction(transactionId: string) {
        const cid = requireCompanyId();
        if (!cid) return;
        await invoicingApi.bankTransactions.unmatch(cid, transactionId);
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

    async function fetchWiseStatus() {
        const cid = requireCompanyId();
        if (!cid) {
            wiseStatus.value = null;
            return;
        }
        try {
            wiseStatus.value = await invoicingApi.wise.status<WiseStatus>(cid);
        } catch {
            wiseStatus.value = null;
        }
        wiseBridgeChecked.value = true;
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

    const loadPromise = Promise.all([
        evolu.loadQuery(allBankTransactionsQuery),
        evolu.loadQuery(allBankTransactionMatchesQuery),
        evolu.loadQuery(allDocumentsQuery),
        evolu.loadQuery(allExpensesQuery),
        evolu.loadQuery(allBankImportBatchesQuery),
    ]);
    void loadPromise.finally(() => {
        loading.value = false;
    });

    const bankTxRows = useQuery(allBankTransactionsQuery, { promise: loadPromise });
    const bankMatchRows = useQuery(allBankTransactionMatchesQuery, { promise: loadPromise });
    const documentRows = useQuery(allDocumentsQuery, { promise: loadPromise });
    const expenseRows = useQuery(allExpensesQuery, { promise: loadPromise });
    const batchRows = useQuery(allBankImportBatchesQuery, { promise: loadPromise });

    const ctx = computed((): BankPaymentContext => ({
        batches: batchRows.value as BankPaymentContext["batches"],
        transactions: bankTxRows.value as BankPaymentContext["transactions"],
        matches: bankMatchRows.value as BankPaymentContext["matches"],
        documents: documentRows.value as BankPaymentContext["documents"],
        expenses: expenseRows.value as BankPaymentContext["expenses"],
    }));

    const filteredRows = computed(() =>
        filterLocalBankTransactions(
            bankTxRows.value as BankPaymentContext["transactions"],
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
                bankTxRows.value as BankPaymentContext["transactions"],
                companyId.value as CompanyId,
            ),
        ),
    );

    const batches = computed(() =>
        mapLocalBankBatchesToApi(
            batchRows.value as BankPaymentContext["batches"],
            companyId.value as CompanyId,
        ),
    );

    const bridgeCompanyId = ref<string | null>(null);
    const inboundEmailAvailable = computed(() => bridgeCompanyId.value !== null);

    const wiseStatus = ref<WiseStatus | null>(null);
    const wiseBridgeChecked = ref(false);
    const wiseBridgeMissing = computed(
        () => wiseBridgeChecked.value && bridgeCompanyId.value === null,
    );

    async function resolveBridgeCompanyId(): Promise<string | null> {
        const bridgeId = await resolveEphemeralBridgeCompanyId();
        bridgeCompanyId.value = bridgeId;
        return bridgeId;
    }

    async function fetchInboundServerRows(bridgeId: string): Promise<ServerBankTransactionRow[]> {
        const rows: ServerBankTransactionRow[] = [];
        let page = 1;
        let lastPage = 1;
        do {
            const res = await invoicingApi.bankTransactions.list<ServerBankTransactionRow>(bridgeId, { source: "email", per_page: 100, page });
            rows.push(...res.data);
            lastPage = res.meta?.last_page ?? 1;
            page += 1;
        } while (page <= lastPage);
        return rows;
    }

    async function syncInboundFromBridge() {
        const bridgeId = await resolveBridgeCompanyId();
        if (!bridgeId) {
            return;
        }
        try {
            const rows = await fetchInboundServerRows(bridgeId);
            if (rows.length > 0) {
                await importInboundServerTransactionsAsync(
                    evolu,
                    companyId.value as CompanyId,
                    rows,
                );
            }
        } catch {
            // Bridge sync is best-effort; local data remains usable.
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

    async function fetchWiseStatus() {
        const bridgeId = await resolveBridgeCompanyId();
        wiseBridgeChecked.value = true;
        if (!bridgeId) {
            wiseStatus.value = null;
            return;
        }
        try {
            wiseStatus.value = await invoicingApi.wise.status<WiseStatus>(bridgeId);
        } catch {
            wiseStatus.value = null;
        }
    }

    async function connectWise(token: string) {
        const bridgeId = await resolveBridgeCompanyId();
        if (!bridgeId) return;
        await invoicingApi.wise.connect(bridgeId, token);
        await fetchWiseStatus();
    }

    async function syncWise() {
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
        const rows = syncResult?.rows || [];
        let autoMatched = 0;
        if (rows.length > 0) {
            const result = await importLocalBankRows(
                evolu,
                companyId.value as CompanyId,
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
        await load(matchFilter.value);
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
        manualMatchLocalTransaction(
            evolu,
            transactionId as BankTransactionId,
            documentId as DocumentId,
            paymentCtx,
        );
        await load(matchFilter.value);
    }

    async function ignoreTransaction(transactionId: string) {
        const paymentCtx = await loadBankPaymentContext(evolu);
        ignoreLocalBankTransaction(evolu, transactionId as BankTransactionId, paymentCtx);
        await load(matchFilter.value);
    }

    async function unmatchTransaction(transactionId: string) {
        const paymentCtx = await loadBankPaymentContext(evolu);
        unmatchLocalBankTransaction(evolu, transactionId as BankTransactionId, paymentCtx);
        await load(matchFilter.value);
    }

    async function createExpenseFromTransaction(transactionId: string, draft: BankExpenseDraft) {
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
        createLocalExpenseFromBankTransaction(
            evolu,
            transactionId as BankTransactionId,
            payload,
            paymentCtx,
        );
        await load(matchFilter.value);
    }

    watch(companyId, () => {
        void load();
        void loadBatches();
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
