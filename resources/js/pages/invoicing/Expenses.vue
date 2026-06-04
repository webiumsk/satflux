<template>
  <InvoicingPageShell>
    <template #header>
      <InvoicingAppHeader :company-label="companyName" :show-filter-bar="false" />
    </template>
    <div class="invoicing-card-pad text-center text-gray-600 py-12">
      <p class="text-lg font-medium text-gray-800">{{ t('invoicing.main_nav_expenses') }}</p>
      <p class="mt-2 text-sm">{{ t('invoicing.expenses_coming_soon') }}</p>
      <RouterLink :to="{ name: 'invoicing-invoices', params: { companyId } }" class="invoicing-link mt-4 inline-block">
        {{ t('invoicing.doc_nav_invoice') }}
      </RouterLink>
    </div>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import api from '../../services/api';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';

const { t } = useI18n();
const route = useRoute();
const { rememberCompany } = useInvoicingLayout();
const companyId = computed(() => route.params.companyId as string);
const companyName = ref('');

onMounted(async () => {
  rememberCompany(companyId.value);
  const res = await api.get(`/invoicing/companies/${companyId.value}`);
  companyName.value = res.data.data?.trade_name || res.data.data?.legal_name || '';
});
</script>
