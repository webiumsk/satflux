import type { Evolu } from "@evolu/common/local-first";
import { computed, ref, watch, type ComputedRef, type Ref } from "vue";
import { useQuery } from "@evolu/vue";
import { invoicingApi } from "@/services/api";
import { allExpensesQuery, allExpenseAttachmentsQuery, useInvoicingEvolu } from "@/evolu/client";
import {
    evoluExpenseToApi,
    evoluExpenseToListRow,
    filterLocalExpenses,
    type EvoluExpenseRow,
    type ExpenseApiRow,
    type ExpenseListFilterOptions,
} from "@/evolu/expenseMap";
import type { EvoluExpenseAttachmentRow } from "@/evolu/expenseAttachmentCrud";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import type { CompanyId, InvoicingLocalSchema } from "@/evolu/schema";
import type { ExpenseListRow } from "@/composables/useExpenseRowMeta";
import { toAppRows } from "../evolu/queryLoad";

export interface UseInvoicingExpensesResult {
    localFirst: boolean;
    expenses: Ref<ExpenseListRow[]> | ComputedRef<ExpenseListRow[]>;
    loading: Ref<boolean>;
    refresh: (filters?: ExpenseListFilterOptions) => Promise<void>;
    evolu: Evolu<InvoicingLocalSchema> | null;
    expenseRows: Ref<EvoluExpenseRow[]> | ComputedRef<EvoluExpenseRow[]>;
}

function useServerInvoicingExpenses(companyId: Ref<string>): UseInvoicingExpensesResult {
    const expenses = ref<ExpenseListRow[]>([]);
    const loading = ref(false);
    let lastFilters: ExpenseListFilterOptions = {};

    async function refresh(filters: ExpenseListFilterOptions = lastFilters): Promise<void> {
        lastFilters = filters;
        if (!companyId.value) {
            expenses.value = [];
            return;
        }
        loading.value = true;
        try {
            expenses.value = await invoicingApi.expenses.list(companyId.value, {
                filter: filters.filter === "all" ? undefined : filters.filter,
                year: filters.year,
                per_page: 500,
            });
        } finally {
            loading.value = false;
        }
    }

    return {
        localFirst: false,
        expenses,
        loading,
        refresh,
        evolu: null,
        expenseRows: computed(() => toAppRows<EvoluExpenseRow>([])),
    };
}

function useLocalInvoicingExpenses(companyId: Ref<string>): UseInvoicingExpensesResult {
    const evolu = useInvoicingEvolu();
    const loading = ref(true);
    const filters = ref<ExpenseListFilterOptions>({});
    let lastFilters: ExpenseListFilterOptions = {};

    const expensesPromise = evolu.loadQuery(allExpensesQuery);
    const attachmentsPromise = evolu.loadQuery(allExpenseAttachmentsQuery);
    const expenseRows = useQuery(allExpensesQuery, { promise: expensesPromise });
    const attachmentRows = useQuery(allExpenseAttachmentsQuery, { promise: attachmentsPromise });

    void Promise.all([expensesPromise, attachmentsPromise]).finally(() => {
        loading.value = false;
    });

    function attachmentsForExpense(expenseId: string): EvoluExpenseAttachmentRow[] {
        return toAppRows<EvoluExpenseAttachmentRow>(
            attachmentRows.value.filter((row) => row.expenseId === expenseId),
        );
    }

    const expenses = computed(() => {
        const companyRows = toAppRows<EvoluExpenseRow>(
            expenseRows.value.filter((row) => row.companyId === companyId.value),
        );

        return filterLocalExpenses(companyRows, filters.value).map((row) =>
            evoluExpenseToListRow(row, attachmentsForExpense(row.id)),
        );
    });

    async function refresh(nextFilters: ExpenseListFilterOptions = lastFilters): Promise<void> {
        lastFilters = nextFilters;
        filters.value = nextFilters;
        loading.value = true;
        try {
            await Promise.all([
                evolu.loadQuery(allExpensesQuery),
                evolu.loadQuery(allExpenseAttachmentsQuery),
            ]);
        } finally {
            loading.value = false;
        }
    }

    return {
        localFirst: true,
        expenses,
        loading,
        refresh,
        evolu,
        expenseRows: computed(() => toAppRows<EvoluExpenseRow>(expenseRows.value)),
    };
}

export function useInvoicingExpenses(companyId: Ref<string>): UseInvoicingExpensesResult {
    if (isInvoicingLocalFirst()) {
        return useLocalInvoicingExpenses(companyId);
    }
    return useServerInvoicingExpenses(companyId);
}

export function useInvoicingExpense(
    companyId: Ref<string>,
    expenseId: Ref<string | undefined>,
) {
    const localFirst = isInvoicingLocalFirst();
    const loading = ref(false);
    const expense = ref<ExpenseApiRow | null>(null);

    if (localFirst) {
        const evolu = useInvoicingEvolu();
        const expenseRows = useQuery(allExpensesQuery);
        const attachmentRows = useQuery(allExpenseAttachmentsQuery);

        function syncExpense(): void {
            if (!expenseId.value) {
                expense.value = null;
                return;
            }
            const row = expenseRows.value.find(
                (e) => e.id === expenseId.value && e.companyId === (companyId.value as CompanyId),
            );
            const attachments = toAppRows<EvoluExpenseAttachmentRow>(
                attachmentRows.value.filter((a) => a.expenseId === expenseId.value),
            );
            expense.value = row ? evoluExpenseToApi(row as EvoluExpenseRow, attachments) : null;
        }

        watch([expenseRows, attachmentRows, expenseId, companyId], syncExpense, { immediate: true });

        async function refresh(): Promise<void> {
            loading.value = true;
            try {
                await Promise.all([
                    evolu.loadQuery(allExpensesQuery),
                    evolu.loadQuery(allExpenseAttachmentsQuery),
                ]);
                syncExpense();
            } finally {
                loading.value = false;
            }
        }

        return { localFirst, expense, loading, refresh, evolu, expenseRows, attachmentRows };
    }

    async function refresh(): Promise<void> {
        if (!expenseId.value || !companyId.value) {
            expense.value = null;
            return;
        }
        loading.value = true;
        try {
            expense.value = await invoicingApi.expenses.get(companyId.value, expenseId.value);
        } catch {
            expense.value = null;
        } finally {
            loading.value = false;
        }
    }

    return {
        localFirst,
        expense,
        loading,
        refresh,
        evolu: null,
        expenseRows: computed(() => toAppRows<EvoluExpenseRow>([])),
    };
}
