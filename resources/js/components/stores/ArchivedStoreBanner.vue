<template>
  <div v-if="store?.archived" class="mb-6 rounded-xl bg-green-500/10 border border-green-500/30 px-4 py-3 flex items-center gap-3">
    <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
    </svg>
    <p class="text-sm text-green-200">{{ t('stores.archived_store_banner') }}</p>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';

defineProps<{ store: { archived?: boolean } | null }>();
const { t } = useI18n();
</script>
