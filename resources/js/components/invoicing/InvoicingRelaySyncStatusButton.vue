<template>
  <button
    type="button"
    class="inline-flex h-9 w-9 items-center justify-center rounded-full border-2 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
    :class="ringClass"
    :title="title"
    :aria-label="title"
    @click="emit('click')"
  >
    <svg
      class="h-4 w-4"
      :class="{ 'animate-spin': spinning }"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
      stroke-width="2"
      aria-hidden="true"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
      />
    </svg>
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

export type RelaySyncStatusLevel = 'green' | 'orange' | 'red';

const props = defineProps<{
  level: RelaySyncStatusLevel;
  spinning?: boolean;
}>();

const emit = defineEmits<{
  click: [];
}>();

const { t } = useI18n();

const ringClass = computed(() => {
  if (props.level === 'red') {
    return 'border-red-400 bg-red-50 text-red-700 hover:bg-red-100';
  }
  if (props.level === 'orange') {
    return 'border-amber-400 bg-amber-50 text-amber-800 hover:bg-amber-100';
  }
  return 'border-emerald-400 bg-emerald-50 text-emerald-700 hover:bg-emerald-100';
});

const title = computed(() => {
  if (props.level === 'red') {
    return t('invoicing.relay_sync_status_red');
  }
  if (props.level === 'orange') {
    return t('invoicing.relay_sync_status_orange');
  }
  return t('invoicing.relay_sync_status_green');
});
</script>
