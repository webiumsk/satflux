import { ref } from "vue";
import { useI18n } from "vue-i18n";
import type { Evolu } from "@evolu/common/local-first";
import { sanitizeLocalStoreReferences } from "@/evolu/sanitizeStoreReferences";
import type { InvoicingLocalSchema } from "@/evolu/schema";
import { useStoresStore } from "@/store/stores";
import { useFlashStore } from "@/store/flash";

const sanitizedForSession = ref(false);

export function useLocalStoreSanitizer() {
    const { t } = useI18n();
    const storesStore = useStoresStore();
    const flashStore = useFlashStore();

    async function ensureStoresLoaded(): Promise<ReadonlySet<string> | null> {
        if (!storesStore.stores.length) {
            const loaded = await storesStore.fetchStores();
            if (!loaded) {
                return null;
            }
        }
        return new Set(storesStore.stores.map((store) => store.id));
    }

    async function sanitizeIfNeeded(evolu: Evolu<InvoicingLocalSchema>): Promise<void> {
        if (sanitizedForSession.value) {
            return;
        }

        const validStoreIds = await ensureStoresLoaded();
        if (!validStoreIds) {
            return;
        }
        const result = await sanitizeLocalStoreReferences(evolu, validStoreIds);
        sanitizedForSession.value = true;

        const total =
            result.clearedCompanyLinks +
            result.clearedDocumentStores +
            result.clearedRecurringStores;
        if (total > 0) {
            flashStore.warning(t("invoicing.store_link_sanitized"));
        }
    }

    function resetSanitizerSession(): void {
        sanitizedForSession.value = false;
    }

    return {
        ensureStoresLoaded,
        sanitizeIfNeeded,
        resetSanitizerSession,
    };
}
