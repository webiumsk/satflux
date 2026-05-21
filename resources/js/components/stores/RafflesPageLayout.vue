<template>
  <div v-if="!store && !error" class="flex min-h-0 flex-1 items-center justify-center bg-gray-900">
    <div class="flex flex-col items-center">
      <svg class="animate-spin h-10 w-10 text-indigo-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <p class="text-gray-400">{{ t('common.loading') }}</p>
    </div>
  </div>
  <div v-else-if="error" class="flex min-h-0 flex-1 items-center justify-center bg-gray-900">
    <div class="text-center px-4">
      <p class="text-red-400 mb-4">{{ error }}</p>
      <button type="button" class="text-indigo-400 hover:text-indigo-300" @click="emit('retry')">{{ t('common.retry') }}</button>
    </div>
  </div>
  <div v-else class="flex min-h-0 flex-1 overflow-hidden bg-gray-900">
    <StoreSidebar
      :store="store"
      :apps="apps"
      @show-settings="emit('show-settings')"
      @show-section="emit('show-section', $event)"
    />
    <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden border-l border-gray-800 bg-gray-900">
      <slot :store="store" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import StoreSidebar from './StoreSidebar.vue';
import { useAccountLimits } from '../../composables/useAccountLimits';

const props = defineProps<{
  store: Record<string, unknown> | null;
  apps: Array<{ id: string; name: string; app_type: string }>;
  error?: string;
}>();

const { load: loadLimits } = useAccountLimits();

watch(
  () => props.store?.id,
  (id) => {
    if (typeof id === 'string' && id) {
      void loadLimits(id);
    }
  },
  { immediate: true },
);

const emit = defineEmits<{
  retry: [];
  'show-settings': [];
  'show-section': [section: string];
}>();

const { t } = useI18n();
</script>
