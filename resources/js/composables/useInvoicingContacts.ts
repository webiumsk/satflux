import type { Evolu } from "@evolu/common/local-first";
import { computed, ref, watch, type ComputedRef, type Ref } from "vue";
import { useQuery } from "@evolu/vue";
import { invoicingApi } from "@/services/api";
import { allContactsQuery, allDocumentsQuery, useInvoicingEvolu } from "@/evolu/client";
import {
    availableContactLetters,
    evoluContactToApi,
    filterContacts,
    type EvoluContactRow,
} from "@/evolu/contactMap";
import { deleteLocalContact } from "@/evolu/contactCrud";
import { computeContactStats } from "@/evolu/contactStatsLocal";
import type { EvoluDocumentRow } from "@/evolu/documentMap";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import type { CompanyId, ContactId, InvoicingLocalSchema } from "@/evolu/schema";
import type { CompanyContactRow } from "./useCompanyContact";
import { toAppRows } from "../evolu/queryLoad";

export interface ContactListFilters {
    q?: string;
    letter?: string;
    page?: number;
    per_page?: number;
}

export interface UseInvoicingContactsResult {
    localFirst: boolean;
    contacts: Ref<CompanyContactRow[]> | ComputedRef<CompanyContactRow[]>;
    availableLetters: Ref<string[]> | ComputedRef<string[]>;
    totalCount: Ref<number> | ComputedRef<number>;
    lastPage: Ref<number> | ComputedRef<number>;
    loading: Ref<boolean>;
    refresh: (filters?: ContactListFilters) => Promise<void>;
    evolu: Evolu<InvoicingLocalSchema> | null;
    contactRows: Ref<EvoluContactRow[]> | ComputedRef<EvoluContactRow[]>;
    documentRows: Ref<unknown[]> | ComputedRef<unknown[]>;
}

function useServerInvoicingContacts(companyId: Ref<string>): UseInvoicingContactsResult {
    const contacts = ref<CompanyContactRow[]>([]);
    const availableLetters = ref<string[]>([]);
    const totalCount = ref(0);
    const lastPage = ref(1);
    const loading = ref(false);
    let lastFilters: ContactListFilters = { page: 1, per_page: 25 };

    async function refresh(filters: ContactListFilters = lastFilters): Promise<void> {
        lastFilters = filters;
        if (!companyId.value) {
            contacts.value = [];
            availableLetters.value = [];
            totalCount.value = 0;
            lastPage.value = 1;
            return;
        }
        loading.value = true;
        try {
            const res = await invoicingApi.contacts.list<CompanyContactRow>(companyId.value, {
                q: filters.q?.trim() || undefined,
                letter: filters.letter && filters.letter !== "all" ? filters.letter : undefined,
                page: filters.page ?? 1,
                per_page: filters.per_page ?? 25,
            });
            contacts.value = res.data;
            availableLetters.value = res.meta.letters ?? [];
            totalCount.value = res.meta.total ?? contacts.value.length;
            lastPage.value = res.meta.last_page ?? 1;
        } finally {
            loading.value = false;
        }
    }

    return {
        localFirst: false,
        contacts,
        availableLetters,
        totalCount,
        lastPage,
        loading,
        refresh,
        evolu: null,
        contactRows: computed(() => toAppRows<EvoluContactRow>([])),
        documentRows: computed(() => []),
    };
}

function useLocalInvoicingContacts(companyId: Ref<string>): UseInvoicingContactsResult {
    const evolu = useInvoicingEvolu();
    const loading = ref(true);
    const filters = ref<ContactListFilters>({ page: 1, per_page: 25 });
    let lastFilters: ContactListFilters = { page: 1, per_page: 25 };

    const contactsPromise = evolu.loadQuery(allContactsQuery);
    const documentsPromise = evolu.loadQuery(allDocumentsQuery);
    const contactRows = useQuery(allContactsQuery, { promise: contactsPromise });
    const documentRows = useQuery(allDocumentsQuery, { promise: documentsPromise });

    void Promise.all([contactsPromise, documentsPromise]).finally(() => {
        loading.value = false;
    });

    const companyContacts = computed(() => {
        const docs = toAppRows<EvoluDocumentRow>(documentRows.value);
        return toAppRows<EvoluContactRow>(contactRows.value)
            .filter((row) => row.companyId === companyId.value)
            .map((row) => {
                const apiRow = evoluContactToApi(row);
                apiRow.stats = computeContactStats(row.id, docs);
                return apiRow;
            });
    });

    const filteredContacts = computed(() =>
        filterContacts(companyContacts.value, {
            q: filters.value.q,
            letter: filters.value.letter,
        }),
    );

    const totalCount = computed(() => filteredContacts.value.length);

    const lastPage = computed(() => {
        const perPage = filters.value.per_page ?? 25;
        return Math.max(1, Math.ceil(filteredContacts.value.length / perPage));
    });

    const contacts = computed(() => {
        const perPage = filters.value.per_page ?? 25;
        const page = Math.min(filters.value.page ?? 1, lastPage.value);
        const start = (page - 1) * perPage;
        return filteredContacts.value.slice(start, start + perPage);
    });

    const availableLetters = computed(() => availableContactLetters(companyContacts.value));

    async function refresh(nextFilters: ContactListFilters = lastFilters): Promise<void> {
        lastFilters = nextFilters;
        filters.value = nextFilters;
        loading.value = true;
        try {
            await Promise.all([
                evolu.loadQuery(allContactsQuery),
                evolu.loadQuery(allDocumentsQuery),
            ]);
        } finally {
            loading.value = false;
        }
    }

    return {
        localFirst: true,
        contacts,
        availableLetters,
        totalCount,
        lastPage,
        loading,
        refresh,
        evolu,
        contactRows: computed(() => toAppRows<EvoluContactRow>(contactRows.value)),
        documentRows,
    };
}

export function useInvoicingContacts(companyId: Ref<string>): UseInvoicingContactsResult {
    if (isInvoicingLocalFirst()) {
        return useLocalInvoicingContacts(companyId);
    }
    return useServerInvoicingContacts(companyId);
}

export function useInvoicingContact(
    companyId: Ref<string>,
    contactId: Ref<string | undefined>,
) {
    const localFirst = isInvoicingLocalFirst();
    const loading = ref(false);
    const contact = ref<CompanyContactRow | null>(null);

    if (localFirst) {
        const evolu = useInvoicingEvolu();
        const contactRows = useQuery(allContactsQuery);
        const documentRows = useQuery(allDocumentsQuery);

        function syncContact(): void {
            if (!contactId.value) {
                contact.value = null;
                return;
            }
            const row = toAppRows<EvoluContactRow>(contactRows.value).find(
                (c) => c.id === contactId.value && c.companyId === (companyId.value as CompanyId),
            );
            if (!row) {
                contact.value = null;
                return;
            }
            const apiRow = evoluContactToApi(row);
            apiRow.stats = computeContactStats(
                row.id,
                toAppRows<EvoluDocumentRow>(documentRows.value),
            );
            contact.value = apiRow;
        }

        watch([contactRows, documentRows, contactId, companyId], syncContact, { immediate: true });

        async function refresh(): Promise<void> {
            loading.value = true;
            try {
                await Promise.all([
                    evolu.loadQuery(allContactsQuery),
                    evolu.loadQuery(allDocumentsQuery),
                ]);
                syncContact();
            } finally {
                loading.value = false;
            }
        }

        async function remove(): Promise<boolean> {
            if (!contactId.value) return false;
            const result = deleteLocalContact(evolu, contactId.value as ContactId);
            return result.ok;
        }

        return { localFirst, contact, loading, refresh, remove, evolu };
    }

    async function refresh(): Promise<void> {
        if (!contactId.value || !companyId.value) {
            contact.value = null;
            return;
        }
        loading.value = true;
        try {
            contact.value = await invoicingApi.contacts.get<CompanyContactRow>(
                companyId.value,
                contactId.value,
            );
        } catch {
            contact.value = null;
        } finally {
            loading.value = false;
        }
    }

    async function remove(): Promise<boolean> {
        if (!contactId.value || !companyId.value) return false;
        await invoicingApi.contacts.delete(companyId.value, contactId.value);
        return true;
    }

    return {
        localFirst,
        contact,
        loading,
        refresh,
        remove,
        evolu: null,
    };
}
