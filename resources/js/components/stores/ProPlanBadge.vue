<template>
  <span
    class="inline-flex items-center gap-1"
    :title="withTooltip ? t('stores.pro_feature_tooltip') : undefined"
  >
    <svg
      class="h-3.5 w-3.5 shrink-0 opacity-90"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.75"
      stroke-linecap="round"
      stroke-linejoin="round"
      aria-hidden="true"
    >
      <path
        d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.847a4.5 4.5 0 003.09 3.09L15.75 12l-2.847.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423L16.5 15l.394 1.183a2.25 2.25 0 001.423 1.423L19.5 18l-1.183.394a2.25 2.25 0 00-1.423 1.423z"
      />
    </svg>
    <span class="font-semibold tracking-wide">{{ t("stores.pro_badge") }}</span>
  </span>
</template>

<script setup lang="ts">
import { useI18n } from "vue-i18n";

withDefaults(
  defineProps<{
    /** Show native tooltip with longer Pro hint (e.g. on compact badges). */
    withTooltip?: boolean;
  }>(),
  { withTooltip: false },
);

const { t } = useI18n();
</script>
