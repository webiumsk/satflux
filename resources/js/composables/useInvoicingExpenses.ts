import type { Evolu } from "@evolu/common/local-first";
import { computed, ref, watch, type ComputedRef, type Ref } from "vue";
import { useQuery } from "@evolu/vue";
import api from "@/services/api";
import { allExpensesQuery, useInvoicingEvolu } from "@/evolu/client";
import {
    evoluExpenseToApi,
    evoluExpenseToListRow,
    filterLocalExpenses,
    type EvoluExpenseRow,
    type ExpenseApiRow,
    type ExpenseListFilterOptions,
} from "@/evolu/expenseMap";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import type { CompanyId, ExpenseId, InvoicingLocalSchema } from "@/evolu/schema";
import type { ExpenseListRow } from "@/composables/useExpenseRowMeta";

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
            const res = await api.get(`/invoicing/companies/${companyId.value}/expenses`, {
                params: {
                    filter: filters.filter === "all" ? undefined : filters.filter,
                    year: filters.year,
                    per_page: 500,
                },
            });
            expenses.value = res.data.data ?? [];
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
        expenseRows: computed(() => [] as EvoluExpenseRow[]),
    };
}

function useLocalInvoicingExpenses(companyId: Ref<string>): UseInvoicingExpensesResult {
    const evolu = useInvoicingEvolu();
    const loading = ref(true);
    const filters = ref<ExpenseListFilterOptions>({});
    let lastFilters: ExpenseListFilterOptions = {};

    const expensesPromise = evolu.loadQuery(allExpensesQuery);
    const expenseRows = useQuery(allExpensesQuery, { promise: expensesPromise });

    void expensesPromise.finally(() => {
        loading.value = false;
    });

    const expenses = computed(() => {
        const companyRows = expenseRows.value.filter(
            (row) => row.companyId === companyId.value,
        ) as EvoluExpenseRow[];

        return filterLocalExpenses(companyRows, filters.value).map(evoluExpenseToListRow);
    });

    async function refresh(nextFilters: ExpenseListFilterOptions = lastFilters): Promise<void> {
        lastFilters = nextFilters;
        filters.value = nextFilters;
        loading.value = true;
        try {
            await evolu.loadQuery(allExpensesQuery);
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
        expenseRows: computed(() => expenseRows.value as EvoluExpenseRow[]),
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

        function syncExpense(): void {
            if (!expenseId.value) {
                expense.value = null;
                return;
            }
            const row = expenseRows.value.find(
                (e) => e.id === expenseId.value && e.companyId === (companyId.value as CompanyId),
            );
            expense.value = row ? evoluExpenseToApi(row as EvoluExpenseRow) : null;
        }

        watch([expenseRows, expenseId, companyId], syncExpense, { immediate: true });

        async function refresh(): Promise<void> {
            loading.value = true;
            try {
                await evolu.loadQuery(allExpensesQuery);
                syncExpense();
            } finally {
                loading.value = false;
            }
        }

        return { localFirst, expense, loading, refresh, evolu, expenseRows };
    }

    async function refresh(): Promise<void> {
        if (!expenseId.value || !companyId.value) {
            expense.value = null;
            return;
        }
        loading.value = true;
        try {
            const res = await api.get(
                `/invoicing/companies/${companyId.value}/expenses/${expenseId.value}`,
            );
            expense.value = res.data.data;
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
        expenseRows: computed(() => [] as EvoluExpenseRow[]),
    };
}
