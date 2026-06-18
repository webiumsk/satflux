<template>
  <div
    class="flex flex-col items-center justify-center gap-3 py-12 px-4 text-center text-sm"
    :class="embedded ? '' : 'flex-1'"
  >
    <template v-if="error">
      <p class="font-medium text-amber-950">{{ t('invoicing.evolu_bootstrap_failed_title') }}</p>
      <p class="text-gray-600 max-w-md">{{ t('invoicing.evolu_bootstrap_failed_detail') }}</p>
      <button type="button" class="invoicing-btn-primary mt-2" @click="emit('retry')">
        {{ t('invoicing.evolu_reload_page') }}
      </button>
    </template>
    <p v-else class="text-gray-400">{{ t('invoicing.relay_sync_loading') }}</p>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';

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
