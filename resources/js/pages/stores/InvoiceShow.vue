<template>
  <AppScrollPane>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
      <router-link
        :to="`/stores/${storeId}/invoices`"
        class="text-indigo-400 hover:text-indigo-300 text-sm"
      >
        ← {{ t('stores.back_to_invoices') }}
      </router-link>
    </div>

    <h1 class="text-3xl font-bold text-white mb-2">{{ t('stores.invoice_detail_title') }}</h1>
    <p class="text-sm text-gray-500 mb-6 font-mono break-all">{{ invoiceId }}</p>

    <InvoiceSettlementPanel :store-id="storeId" :invoice-id="invoiceId" />
  </div>
  </AppScrollPane>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import AppScrollPane from '../../components/layout/AppScrollPane.vue';
import InvoiceSettlementPanel from '../../components/stores/InvoiceSettlementPanel.vue';

const { t } = useI18n();
const route = useRoute();
const storeId = route.params.id as string;
const invoiceId = route.params.invoiceId as string;
</script>
