import type { Evolu } from "@evolu/common/local-first";
import { computed, ref, watch, type ComputedRef, type Ref } from "vue";
import { useQuery } from "@evolu/vue";
import api from "@/services/api";
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
    importLocalBankFileAsync,
    loadBankPaymentContext,
    manualMatchLocalTransaction,
    mapLocalBankBatchesToApi,
    mapLocalBankTransactionsToApi,
    unmatchLocalBankTransaction,
    type BankPaymentContext,
} from "@/evolu/bankCrud";
import type { BankBatchApiRow, BankSummary, BankTxApiRow } from "@/evolu/bankMap";
import { isInvoicingLocalFirst } from "@/evolu/flags";
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

    const inboundEmailAvailable = computed(() => true);

    async function load(matchFilter = lastFilter) {
        lastFilter = matchFilter;
        loading.value = true;
        try {
            const params: Record<string, string | number> = { per_page: 50 };
            if (matchFilter !== "all") params.match_status = matchFilter;
            const { data } = await api.get(`/invoicing/companies/${companyId.value}/bank-transactions`, {
                params,
            });
            transactions.value = data.data || [];
            summary.value = data.meta?.summary || emptySummary(defaultCurrency.value);
        } finally {
            loading.value = false;
        }
    }

    async function loadBatches() {
        const { data } = await api.get(
            `/invoicing/companies/${companyId.value}/bank-transactions/batches`,
        );
        batches.value = data.data || [];
    }

    async function loadInbound() {
        try {
            const { data } = await api.get(
                `/invoicing/companies/${companyId.value}/bank-transactions/inbound-email`,
            );
            inboundEmail.value = data.data?.address || "";
        } catch {
            inboundEmail.value = "";
        }
    }

    async function importFile(file: File, format?: string) {
        const form = new FormData();
        form.append("file", file);
        if (format) form.append("format", format);
        const { data } = await api.post(
            `/invoicing/companies/${companyId.value}/bank-transactions/import`,
            form,
            { headers: { "Content-Type": "multipart/form-data" } },
        );
        return {
            imported: data.data.imported,
            auto_matched: data.data.auto_matched,
        };
    }

    async function autoMatchBatch(batchId: string) {
        await api.post(
            `/invoicing/companies/${companyId.value}/bank-transactions/batches/${batchId}/auto-match`,
        );
    }

    async function fetchSuggestions(transactionId: string): Promise<BankMatchSuggestion[]> {
        const { data } = await api.get(
            `/invoicing/companies/${companyId.value}/bank-transactions/${transactionId}/suggestions`,
        );
        return data.data || [];
    }

    async function matchTransaction(transactionId: string, documentId: string) {
        await api.post(
            `/invoicing/companies/${companyId.value}/bank-transactions/${transactionId}/match`,
            { business_document_id: documentId },
        );
    }

    async function ignoreTransaction(transactionId: string) {
        await api.post(
            `/invoicing/companies/${companyId.value}/bank-transactions/${transactionId}/ignore`,
        );
    }

    async function unmatchTransaction(transactionId: string) {
        await api.post(
            `/invoicing/companies/${companyId.value}/bank-transactions/${transactionId}/unmatch`,
        );
    }

    async function createExpenseFromTransaction(transactionId: string, draft: BankExpenseDraft) {
        await api.post(
            `/invoicing/companies/${companyId.value}/bank-transactions/${transactionId}/create-expense`,
            {
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

    const bankTxRows = useQuery(allBankTransactionsQuery, {
        promise: evolu.loadQuery(allBankTransactionsQuery),
    });
    const bankMatchRows = useQuery(allBankTransactionMatchesQuery, {
        promise: evolu.loadQuery(allBankTransactionMatchesQuery),
    });
    const documentRows = useQuery(allDocumentsQuery, {
        promise: evolu.loadQuery(allDocumentsQuery),
    });
    const expenseRows = useQuery(allExpensesQuery, {
        promise: evolu.loadQuery(allExpensesQuery),
    });
    const batchRows = useQuery(allBankImportBatchesQuery, {
        promise: evolu.loadQuery(allBankImportBatchesQuery),
    });

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

    const inboundEmailAvailable = computed(() => false);

    async function load(filter = matchFilter.value) {
        matchFilter.value = filter;
        loading.value = true;
        try {
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
        // Inbound b-mail is server-only; no-op locally.
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
