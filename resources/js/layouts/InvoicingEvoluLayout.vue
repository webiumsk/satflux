<template>
  <router-view v-if="!localFirst" />
  <EvoluProvider v-else-if="ready && evolu" :evolu="evolu">
    <router-view />
  </EvoluProvider>
  <InvoicingEvoluBootPlaceholder
    v-else
    :error="bootstrapError"
    @retry="retryBootstrap"
  />
</template>

<script setup lang="ts">
import { EvoluProvider } from "@evolu/vue";
import InvoicingEvoluBootPlaceholder from "@/components/invoicing/InvoicingEvoluBootPlaceholder.vue";
import { useInvoicingEvoluShell } from "@/composables/useInvoicingEvoluShell";
import { useLocalRecurringDueRunner } from "@/composables/useLocalRecurringDueRunner";
import { useLocalStoreSanitizer } from "@/composables/useLocalStoreSanitizer";

const { runDueProfiles } = useLocalRecurringDueRunner();
const { sanitizeIfNeeded } = useLocalStoreSanitizer();

const { localFirst, ready, evolu, bootstrapError, retryBootstrap } = useInvoicingEvoluShell(
    async (instance) => {
        await sanitizeIfNeeded(instance);
        await runDueProfiles(instance);
    },
);
</script>
