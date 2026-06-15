<template>
  <router-view v-if="!localFirst" />
  <EvoluProvider v-else-if="evolu" :evolu="evolu">
    <router-view />
  </EvoluProvider>
  <div v-else class="flex flex-1 items-center justify-center py-16 text-sm text-gray-500">
    {{ t("common.loading") }}
  </div>
</template>

<script setup lang="ts">
import { shallowRef } from "vue";
import { useI18n } from "vue-i18n";
import type { Evolu } from "@evolu/common/local-first";
import { EvoluProvider } from "@evolu/vue";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import type { InvoicingLocalSchema } from "@/evolu/schema";

const { t } = useI18n();
const localFirst = isInvoicingLocalFirst();
const evolu = shallowRef<Evolu<InvoicingLocalSchema> | null>(null);

if (localFirst) {
    void import("@/evolu/client").then((mod) => {
        evolu.value = mod.evolu;
    });
}
</script>
