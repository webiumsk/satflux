<template>
  <InvoicingPageShell :title="title" :subtitle="subtitle">
    <LocalFirstBridgeNotice :detail="resolvedDetail" />
    <div class="mt-4">
      <RouterLink :to="backTo" class="invoicing-back">{{ t('invoicing.title') }}</RouterLink>
    </div>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import LocalFirstBridgeNotice from './LocalFirstBridgeNotice.vue';
import InvoicingPageShell from './InvoicingPageShell.vue';

const props = defineProps<{
  title: string;
  subtitle?: string;
  detail?: string;
  detailKey?: string;
}>();

const { t } = useI18n();

const resolvedDetail = computed(() => {
  if (props.detail) return props.detail;
  if (props.detailKey) return t(props.detailKey);
  return t('invoicing.local_first_bridge_detail');
});
const route = useRoute();

const backTo = computed(() => ({
  name: 'invoicing-invoices',
  params: { companyId: route.params.companyId as string },
}));
</script>
