<script setup lang="ts">
import { toAppRows } from "../../evolu/queryLoad";
/**
 * Deep-link target for external integrations (WP plugin order metabox):
 * /invoicing/from-inbox/:evoluId carries the integration-inbox evolu uuid;
 * the local document id is derivable only client-side (same stable-id hash
 * the import uses), so this page resolves it and forwards to the document
 * detail. Documents not imported yet fall back to the invoicing dashboard,
 * where the integration-inbox banner surfaces pending entries.
 */
import { onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { stableDocumentIdFromInboxUuid } from "@/evolu/inboxDocumentStableId";
import type { EvoluDocumentRow } from "@/evolu/documentMap";

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const notFound = ref(false);

const SHOW_ROUTE_BY_TYPE: Record<string, string> = {
    invoice: "invoicing-invoice-show",
    proforma: "invoicing-proforma-show",
};

async function loadDocuments(): Promise<EvoluDocumentRow[]> {
    const { evolu, allDocumentsQuery } = await import("@/evolu/client");
    const rows = await evolu.loadQuery(allDocumentsQuery);
    return toAppRows<EvoluDocumentRow>(rows);
}

onMounted(async () => {
    const evoluId = String(route.params.evoluId ?? "");
    const documentId = stableDocumentIdFromInboxUuid(evoluId);
    if (!documentId) {
        notFound.value = true;
        return;
    }

    let doc = (await loadDocuments()).find((row) => row.id === documentId) ?? null;
    if (!doc) {
        // Cold load: give relay sync a moment before giving up.
        const { waitForInvoicingDataSettled } = await import("@/evolu/relaySyncWait");
        const { evolu } = await import("@/evolu/client");
        await waitForInvoicingDataSettled(evolu, { minWaitMs: 1000, timeoutMs: 6000 });
        doc = (await loadDocuments()).find((row) => row.id === documentId) ?? null;
    }

    const routeName = doc ? SHOW_ROUTE_BY_TYPE[String(doc.documentType)] : undefined;
    if (doc && routeName) {
        await router.replace({
            name: routeName,
            params: { companyId: String(doc.companyId), documentId: String(doc.id) },
        });
        return;
    }

    notFound.value = true;
});
</script>

<template>
    <div class="max-w-3xl mx-auto px-4 py-16 text-center text-sm text-gray-600 dark:text-gray-300">
        <template v-if="notFound">
            <p class="font-medium">{{ t("invoicing.from_inbox_not_found") }}</p>
            <RouterLink
                :to="{ name: 'invoicing' }"
                class="mt-3 inline-block text-indigo-600 dark:text-indigo-400 underline"
            >
                {{ t("invoicing.from_inbox_open_invoicing") }}
            </RouterLink>
        </template>
        <p v-else>{{ t("invoicing.from_inbox_searching") }}</p>
    </div>
</template>
