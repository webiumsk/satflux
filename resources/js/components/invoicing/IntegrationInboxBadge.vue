<template>
  <RouterLink
    v-if="companyId && pendingCount > 0"
    :to="inboxTarget"
    class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-indigo-700 transition-colors"
    :title="t('invoicing.integration_inbox_badge_title')"
  >
    {{ t('invoicing.integration_inbox_badge_label') }}
    <span
      class="flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-white/25 px-1 text-[10px] font-bold"
    >
      {{ pendingCount > 9 ? '9+' : pendingCount }}
    </span>
  </RouterLink>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useIntegrationInboxLive } from '@/evolu/integrationInboxLive';

/**
 * Pending WooCommerce inbox counter, visible from every invoicing page -
 * the count comes from the shared polling loop in integrationInboxLive.
 */
const props = defineProps<{
  companyId: string;
}>();

const { t } = useI18n();
const { pendingCount } = useIntegrationInboxLive();

const inboxTarget = computed(() => ({
  name: 'invoicing-invoices',
  params: { companyId: props.companyId },
  hash: '#woocommerce-integration-inbox',
}));
</script>
