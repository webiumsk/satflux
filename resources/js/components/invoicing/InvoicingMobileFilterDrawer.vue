<template>
  <InvoicingDrawer
    :open="open"
    side="right"
    :title="t('invoicing.mobile_filters')"
    @close="emit('close')"
  >
    <div class="space-y-4">
      <div v-if="activeCount > 0" class="flex items-center justify-between gap-2">
        <span class="text-sm text-gray-600">
          {{ t('invoicing.mobile_filters_active', { count: activeCount }) }}
        </span>
        <button type="button" class="text-sm text-indigo-600 hover:text-indigo-800" @click="emit('clear')">
          {{ t('invoicing.mobile_clear_filters') }}
        </button>
      </div>
      <slot />
    </div>

    <template #footer>
      <div class="flex flex-col gap-2">
        <slot name="actions" />
        <button type="button" class="invoicing-btn-primary w-full" @click="emit('apply')">
          {{ t('invoicing.mobile_apply_filters') }}
        </button>
      </div>
    </template>
  </InvoicingDrawer>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import InvoicingDrawer from './ui/InvoicingDrawer.vue';

withDefaults(
  defineProps<{
    open: boolean;
    activeCount?: number;
  }>(),
  { activeCount: 0 }
);

const emit = defineEmits<{
  close: [];
  apply: [];
  clear: [];
}>();

const { t } = useI18n();
</script>
