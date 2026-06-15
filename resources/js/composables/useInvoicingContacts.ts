import type { Evolu } from "@evolu/common/local-first";
import { computed, ref, watch, type ComputedRef, type Ref } from "vue";
import { useQuery } from "@evolu/vue";
import api from "@/services/api";
import { allContactsQuery, allDocumentsQuery, useInvoicingEvolu } from "@/evolu/client";
import {
    availableContactLetters,
    evoluContactToApi,
    filterContacts,
    type EvoluContactRow,
} from "@/evolu/contactMap";
import { deleteLocalContact } from "@/evolu/contactCrud";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import type { CompanyId, ContactId, InvoicingLocalSchema } from "@/evolu/schema";
import type { CompanyContactRow } from "./useCompanyContact";

export interface ContactListFilters {
    q?: string;
    letter?: string;
}

export interface UseInvoicingContactsResult {
    localFirst: boolean;
    contacts: Ref<CompanyContactRow[]> | ComputedRef<CompanyContactRow[]>;
    availableLetters: Ref<string[]> | ComputedRef<string[]>;
    loading: Ref<boolean>;
    refresh: (filters?: ContactListFilters) => Promise<void>;
    evolu: Evolu<InvoicingLocalSchema> | null;
    contactRows: Ref<EvoluContactRow[]> | ComputedRef<EvoluContactRow[]>;
    documentRows: Ref<unknown[]> | ComputedRef<unknown[]>;
}

function useServerInvoicingContacts(companyId: Ref<string>): UseInvoicingContactsResult {
    const contacts = ref<CompanyContactRow[]>([]);
    const availableLetters = ref<string[]>([]);
    const loading = ref(false);
    let lastFilters: ContactListFilters = {};

    async function refresh(filters: ContactListFilters = lastFilters): Promise<void> {
        lastFilters = filters;
        if (!companyId.value) {
            contacts.value = [];
            availableLetters.value = [];
            return;
        }
        loading.value = true;
        try {
            const res = await api.get(`/invoicing/companies/${companyId.value}/contacts`, {
                params: {
                    q: filters.q?.trim() || undefined,
                    letter: filters.letter && filters.letter !== "all" ? filters.letter : undefined,
                },
            });
            contacts.value = res.data.data ?? [];
            availableLetters.value = res.data.meta?.letters ?? [];
        } finally {
            loading.value = false;
        }
    }

    return {
        localFirst: false,
        contacts,
        availableLetters,
        loading,
        refresh,
        evolu: null,
        contactRows: computed(() => [] as EvoluContactRow[]),
        documentRows: computed(() => []),
    };
}

function useLocalInvoicingContacts(companyId: Ref<string>): UseInvoicingContactsResult {
    const evolu = useInvoicingEvolu();
    const loading = ref(true);
    const filters = ref<ContactListFilters>({});
    let lastFilters: ContactListFilters = {};

    const contactsPromise = evolu.loadQuery(allContactsQuery);
    const documentsPromise = evolu.loadQuery(allDocumentsQuery);
    const contactRows = useQuery(allContactsQuery, { promise: contactsPromise });
    const documentRows = useQuery(allDocumentsQuery, { promise: documentsPromise });

    void Promise.all([contactsPromise, documentsPromise]).finally(() => {
        loading.value = false;
    });

    const companyContacts = computed(() =>
        contactRows.value
            .filter((row) => row.companyId === companyId.value)
            .map((row) => evoluContactToApi(row as EvoluContactRow)),
    );

    const contacts = computed(() =>
        filterContacts(companyContacts.value, {
            q: filters.value.q,
            letter: filters.value.letter,
        }),
    );

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
        loading,
        refresh,
        evolu,
        contactRows: computed(() => contactRows.value as EvoluContactRow[]),
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

        function syncContact(): void {
            if (!contactId.value) {
                contact.value = null;
                return;
            }
            const row = contactRows.value.find(
                (c) => c.id === contactId.value && c.companyId === (companyId.value as CompanyId),
            );
            contact.value = row ? evoluContactToApi(row as EvoluContactRow) : null;
        }

        watch([contactRows, contactId, companyId], syncContact, { immediate: true });

        async function refresh(): Promise<void> {
            loading.value = true;
            try {
                await evolu.loadQuery(allContactsQuery);
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
            const res = await api.get(
                `/invoicing/companies/${companyId.value}/contacts/${contactId.value}`,
            );
            contact.value = res.data.data;
        } catch {
            contact.value = null;
        } finally {
            loading.value = false;
        }
    }

    async function remove(): Promise<boolean> {
        if (!contactId.value || !companyId.value) return false;
        await api.delete(`/invoicing/companies/${companyId.value}/contacts/${contactId.value}`);
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
