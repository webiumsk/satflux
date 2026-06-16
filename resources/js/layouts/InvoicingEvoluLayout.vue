<template>
  <router-view v-if="!localFirst" />
  <EvoluProvider v-else-if="ready && evolu" :evolu="evolu">
    <router-view />
  </EvoluProvider>
  <div v-else class="flex flex-1 items-center justify-center py-16 text-sm text-gray-500">
    {{ t("invoicing.relay_sync_loading") }}
  </div>
</template>

<script setup lang="ts">
import { shallowRef } from "vue";
import { useI18n } from "vue-i18n";
import type { Evolu } from "@evolu/common/local-first";
import { EvoluProvider } from "@evolu/vue";
import { ensureEvoluBoundToAccountSeed } from "@/evolu/bootstrap";
import { useLocalRecurringDueRunner } from "@/composables/useLocalRecurringDueRunner";
import { useLocalStoreSanitizer } from "@/composables/useLocalStoreSanitizer";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import type { InvoicingLocalSchema } from "@/evolu/schema";

const { t } = useI18n();
const localFirst = isInvoicingLocalFirst();
const evolu = shallowRef<Evolu<InvoicingLocalSchema> | null>(null);
const ready = shallowRef(false);
const { runDueProfiles } = useLocalRecurringDueRunner();
const { sanitizeIfNeeded } = useLocalStoreSanitizer();

if (localFirst) {
    void (async () => {
        await ensureEvoluBoundToAccountSeed();
        const mod = await import("@/evolu/client");
        evolu.value = mod.evolu;
        ready.value = true;
        await sanitizeIfNeeded(mod.evolu);
        await runDueProfiles(mod.evolu);
    })();
}
</script>
