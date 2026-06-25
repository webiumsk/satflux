<template>
  <div
    class="flex flex-col"
    :class="embedded ? '' : 'flex-1'"
  >
    <InvoicingLoadingState
      v-if="!error"
      :message="t('invoicing.relay_sync_loading')"
    />
    <div
      v-else
      class="flex flex-col items-center justify-center gap-3 py-12 px-4 text-center text-sm flex-1"
    >
      <p class="font-medium text-amber-950">{{ t('invoicing.evolu_bootstrap_failed_title') }}</p>
      <p class="text-gray-600 max-w-md">{{ t('invoicing.evolu_bootstrap_failed_detail') }}</p>
      <button type="button" class="invoicing-btn-primary mt-2" @click="emit('retry')">
        {{ t('invoicing.evolu_reload_page') }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import InvoicingLoadingState from './ui/InvoicingLoadingState.vue';

withDefaults(
  defineProps<{
    error?: boolean;
    embedded?: boolean;
  }>(),
  { error: false, embedded: false },
);

const emit = defineEmits<{
  retry: [];
}>();

const { t } = useI18n();
</script>
