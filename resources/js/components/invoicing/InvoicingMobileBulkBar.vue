<template>
  <div v-if="open" class="invoicing-mobile-bottom-bar md:hidden">
    <div class="flex items-center justify-between gap-3 px-4 py-3">
      <span class="text-sm font-medium text-gray-900">
        {{ t('invoicing.mobile_selected_count', { count: selectionCount }) }}
      </span>
      <button type="button" class="text-sm text-gray-600 hover:text-gray-900" @click="emit('clear')">
        {{ t('invoicing.bulk_clear') }}
      </button>
    </div>
    <div class="flex gap-2 overflow-x-auto px-4 pb-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';

defineProps<{
  open: boolean;
  selectionCount: number;
}>();

const emit = defineEmits<{ clear: [] }>();

const { t } = useI18n();
</script>
