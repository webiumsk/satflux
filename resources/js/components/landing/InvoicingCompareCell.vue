<template>
  <span
    class="inline-flex items-center gap-1.5 font-medium"
    :class="colorClass"
  >
    <svg
      v-if="value === 'yes'"
      class="h-4 w-4 shrink-0"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        d="M5 13l4 4L19 7"
      />
    </svg>
    <svg
      v-else-if="value === 'no'"
      class="h-4 w-4 shrink-0"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        d="M6 18L18 6M6 6l12 12"
      />
    </svg>
    <svg
      v-else
      class="h-4 w-4 shrink-0"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
      />
    </svg>
    <span>{{ t(labelKey) }}</span>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { CompareValue } from '../../constants/invoicingComparison';

const props = withDefaults(
  defineProps<{
    value: CompareValue;
    highlight?: boolean;
  }>(),
  { highlight: false },
);

const { t } = useI18n();

const labelKey = computed(
  () => `landing.invoicing_section.compare.values.${props.value}`,
);

const colorClass = computed(() => {
  if (props.value === 'yes') {
    return props.highlight ? 'text-emerald-400' : 'text-green-400/90';
  }
  if (props.value === 'partial') return 'text-amber-400/90';
  if (props.value === 'pro') return 'text-indigo-300';
  return 'text-gray-500';
});
</script>
