import { computed, ref, watch, type Ref } from "vue";
import { useQuery } from "@evolu/vue";
import { useRoute } from "vue-router";
import api from "@/services/api";
import { allCompaniesDetailQuery, useInvoicingEvolu } from "@/evolu/client";
import {
    companyBankAccountLabel,
    companyHasBankAccount,
    evoluCompanyToApi,
    type EvoluCompanyRow,
} from "@/evolu/companyMap";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import { useStoresStore } from "@/store/stores";

type CompanySummaryCache = {
    name: string;
    hasBankAccount: boolean;
    bankAccountLabel: string;
    defaultCurrency: string;
};

const summaryByCompany = new Map<string, CompanySummaryCache>();
let summaryRequestSeq = 0;

function resetSummaryState(
    companyName: { value: string },
    hasBankAccount: { value: boolean },
    bankAccountLabel: { value: string },
    defaultCurrency: { value: string },
    summaryLoaded: { value: boolean },
) {
    companyName.value = "";
    hasBankAccount.value = false;
    bankAccountLabel.value = "";
    defaultCurrency.value = "EUR";
    summaryLoaded.value = false;
}

function useServerInvoicingCompanySummary() {
    const route = useRoute();
    const companyId = computed(() => (route.params.companyId as string) || "");
    const companyName = ref("");
    const hasBankAccount = ref(false);
    const bankAccountLabel = ref("");
    const defaultCurrency = ref("EUR");
    const summaryLoaded = ref(false);

    async function loadSummary(id?: string) {
        const cid = id || companyId.value;
        const requestId = ++summaryRequestSeq;

        if (!cid) {
            resetSummaryState(companyName, hasBankAccount, bankAccountLabel, defaultCurrency, summaryLoaded);
            return;
        }

        const cached = summaryByCompany.get(cid);
        if (cached) {
            if (requestId !== summaryRequestSeq) return;
            companyName.value = cached.name;
            hasBankAccount.value = cached.hasBankAccount;
            bankAccountLabel.value = cached.bankAccountLabel;
            defaultCurrency.value = cached.defaultCurrency;
            summaryLoaded.value = true;
            return;
        }

        try {
            const { data } = await api.get(`/invoicing/companies/${cid}/summary`);
            if (requestId !== summaryRequestSeq) return;

            const name = data.data?.trade_name || data.data?.legal_name || "";
            const bank = !!data.data?.has_bank_account;
            const label = data.data?.bank_account_label || "";
            const currency = data.data?.default_currency || "EUR";
            const entry = { name, hasBankAccount: bank, bankAccountLabel: label, defaultCurrency: currency };
            summaryByCompany.set(cid, entry);
            companyName.value = name;
            hasBankAccount.value = bank;
            bankAccountLabel.value = label;
            defaultCurrency.value = currency;
            summaryLoaded.value = true;
        } catch {
            if (requestId !== summaryRequestSeq) return;
            resetSummaryState(companyName, hasBankAccount, bankAccountLabel, defaultCurrency, summaryLoaded);
        }
    }

    function invalidateSummary(id?: string) {
        summaryByCompany.delete(id || companyId.value);
    }

    watch(companyId, (id) => void loadSummary(id || undefined), { immediate: true });

    return {
        companyId,
        companyName,
        hasBankAccount,
        bankAccountLabel,
        defaultCurrency,
        summaryLoaded,
        loadSummary,
        invalidateSummary,
    };
}

function useLocalInvoicingCompanySummary() {
    const route = useRoute();
    const evolu = useInvoicingEvolu();
    const storesStore = useStoresStore();
    const companyId = computed(() => (route.params.companyId as string) || "");

    const detailPromise = evolu.loadQuery(allCompaniesDetailQuery);
    const companyRows = useQuery(allCompaniesDetailQuery, { promise: detailPromise });

    const summaryLoaded = ref(false);
    void detailPromise.finally(() => {
        summaryLoaded.value = true;
    });

    const companyName = computed(() => {
        const row = companyRows.value.find((c) => c.id === companyId.value);
        if (!row) return "";
        return row.tradeName || row.legalName || "";
    });

    const hasBankAccount = computed(() => {
        const row = companyRows.value.find((c) => c.id === companyId.value);
        return row ? companyHasBankAccount(row as EvoluCompanyRow) : false;
    });

    const bankAccountLabel = computed(() => {
        const row = companyRows.value.find((c) => c.id === companyId.value);
        return row ? companyBankAccountLabel(row as EvoluCompanyRow) : "";
    });

    const defaultCurrency = computed(() => {
        const row = companyRows.value.find((c) => c.id === companyId.value);
        return row?.defaultCurrency || "EUR";
    });

    async function loadSummary(id?: string) {
        void id;
        await evolu.loadQuery(allCompaniesDetailQuery);
        summaryLoaded.value = true;
    }

    function invalidateSummary(id?: string) {
        void id;
        void evolu.loadQuery(allCompaniesDetailQuery);
    }

    watch(
        companyId,
        () => {
            if (!storesStore.stores.length) {
                void storesStore.fetchStores();
            }
        },
        { immediate: true },
    );

    return {
        companyId,
        companyName,
        hasBankAccount,
        bankAccountLabel,
        defaultCurrency,
        summaryLoaded,
        loadSummary,
        invalidateSummary,
    };
}

export function useInvoicingCompanySummary() {
    if (isInvoicingLocalFirst()) {
        return useLocalInvoicingCompanySummary();
    }
    return useServerInvoicingCompanySummary();
}
