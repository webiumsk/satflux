import type { Evolu } from "@evolu/common/local-first";
import { computed, ref, watch, type ComputedRef, type Ref } from "vue";
import { useQuery } from "@evolu/vue";
import { invoicingApi } from "@/services/api";
import {
    allContactsQuery,
    allRecurringProfileLinesQuery,
    allRecurringProfilesQuery,
    useInvoicingEvolu,
} from "@/evolu/client";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import { evoluContactToApi } from "@/evolu/contactMap";
import {
    evoluRecurringProfileToApi,
    filterRecurringProfiles,
    type EvoluRecurringProfileLineRow,
    type EvoluRecurringProfileRow,
    type RecurringProfileApiRow,
} from "@/evolu/recurringMap";
import type { CompanyId, InvoicingLocalSchema } from "@/evolu/schema";
import { toAppRows } from "../evolu/queryLoad";

export type RecurringListFilter = "all" | "active" | "inactive";

export interface UseInvoicingRecurringProfilesResult {
    localFirst: boolean;
    profiles: Ref<RecurringProfileApiRow[]> | ComputedRef<RecurringProfileApiRow[]>;
    loading: Ref<boolean>;
    refresh: (filter?: RecurringListFilter) => Promise<void>;
    evolu: Evolu<InvoicingLocalSchema> | null;
}

function useServerRecurringProfiles(companyId: Ref<string>): UseInvoicingRecurringProfilesResult {
    const profiles = ref<RecurringProfileApiRow[]>([]);
    const loading = ref(false);
    let lastFilter: RecurringListFilter = "all";

    async function refresh(filter: RecurringListFilter = lastFilter): Promise<void> {
        lastFilter = filter;
        if (!companyId.value) {
            profiles.value = [];
            return;
        }
        loading.value = true;
        try {
            profiles.value = await invoicingApi.recurringProfiles.list(companyId.value, {
                filter,
                per_page: 100,
            });
        } finally {
            loading.value = false;
        }
    }

    return { localFirst: false, profiles, loading, refresh, evolu: null };
}

function useLocalRecurringProfiles(companyId: Ref<string>): UseInvoicingRecurringProfilesResult {
    const evolu = useInvoicingEvolu();
    const loading = ref(true);
    const filter = ref<RecurringListFilter>("all");

    const profilesPromise = evolu.loadQuery(allRecurringProfilesQuery);
    const linesPromise = evolu.loadQuery(allRecurringProfileLinesQuery);
    const contactsPromise = evolu.loadQuery(allContactsQuery);

    const profileRows = useQuery(allRecurringProfilesQuery, { promise: profilesPromise });
    const lineRows = useQuery(allRecurringProfileLinesQuery, { promise: linesPromise });
    const contactRows = useQuery(allContactsQuery, { promise: contactsPromise });

    void Promise.all([profilesPromise, linesPromise, contactsPromise]).finally(() => {
        loading.value = false;
    });

    const profiles = computed(() => {
        const companyProfiles = filterRecurringProfiles(
            toAppRows<EvoluRecurringProfileRow>(profileRows.value),
            companyId.value as CompanyId,
            filter.value,
        );
        const contacts = contactRows.value.map((row) => evoluContactToApi(row));

        return companyProfiles.map((row) => {
            const contact = row.contactId
                ? contacts.find((c) => c.id === row.contactId) ?? null
                : null;
            return evoluRecurringProfileToApi(
                row,
                toAppRows<EvoluRecurringProfileLineRow>(lineRows.value),
                contact ? { id: contact.id, name: contact.name } : null,
            );
        });
    });

    async function refresh(nextFilter: RecurringListFilter = filter.value): Promise<void> {
        filter.value = nextFilter;
        loading.value = true;
        try {
            await Promise.all([
                evolu.loadQuery(allRecurringProfilesQuery),
                evolu.loadQuery(allRecurringProfileLinesQuery),
                evolu.loadQuery(allContactsQuery),
            ]);
        } finally {
            loading.value = false;
        }
    }

    return { localFirst: true, profiles, loading, refresh, evolu };
}

export function useInvoicingRecurringProfiles(companyId: Ref<string>): UseInvoicingRecurringProfilesResult {
    const localFirst = isInvoicingLocalFirst();
    const server = useServerRecurringProfiles(companyId);
    const local = localFirst ? useLocalRecurringProfiles(companyId) : null;

    if (local) {
        watch(companyId, () => {
            void local.refresh();
        });
        return local;
    }

    watch(companyId, () => {
        void server.refresh();
    });

    return server;
}
