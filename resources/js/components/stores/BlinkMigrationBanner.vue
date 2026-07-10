<template>
  <div
    v-if="active"
    class="mb-4 rounded-xl border border-amber-500/35 bg-amber-500/10 px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-3"
  >
    <p class="text-sm text-amber-100/95 flex-1">
      {{ t("stores.blink_migration_banner_short") }}
    </p>
    <router-link
      :to="`/stores/${storeId}/wallet-connection`"
      class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold text-amber-950 bg-amber-400 hover:bg-amber-300 transition-colors whitespace-nowrap"
      @click.stop
    >
      {{ t("stores.blink_migration_go_wallet") }}
    </router-link>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from "vue-i18n";

defineProps<{
  storeId: string;
  active: boolean;
}>();

const { t } = useI18n();
</script>
