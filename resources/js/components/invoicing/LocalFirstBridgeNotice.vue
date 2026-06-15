<template>
  <div class="invoicing-alert-warn mb-4">
    <p class="text-sm font-medium">{{ title || t('invoicing.local_first_bridge_title') }}</p>
    <p class="text-sm mt-1 opacity-90">{{ detail || t('invoicing.local_first_bridge_detail') }}</p>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';

defineProps<{
  title?: string;
  detail?: string;
}>();

const { t } = useI18n();
</script>
