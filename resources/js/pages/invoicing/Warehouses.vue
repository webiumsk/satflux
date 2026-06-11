<template>
  <InvoicingPageShell content-class="pb-8">
    <template #header>
      <InvoicingAppHeader :company-label="companyName">
        <template #actions>
          <RouterLink :to="stockListTo()" class="invoicing-btn-secondary">
            {{ t('invoicing.stock_title') }}
          </RouterLink>
          <RouterLink :to="warehouseNewTo()" class="invoicing-btn-primary">
            + {{ t('invoicing.warehouse_new') }}
          </RouterLink>
        </template>
      </InvoicingAppHeader>
    </template>

    <h1 class="invoicing-title mb-4">{{ t('invoicing.warehouses_title') }}</h1>
    <p v-if="error" class="text-sm text-red-600 mb-4">{{ error }}</p>

    <div v-if="loading" class="invoicing-muted py-8">{{ t('common.loading') }}</div>

    <div v-else-if="warehouses.length === 0" class="invoicing-card-pad text-center text-gray-600">
      {{ t('invoicing.warehouses_empty') }}
    </div>

    <div v-else class="invoicing-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-sm">
          <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 text-xs uppercase tracking-wide">
            <tr>
              <th class="text-left px-3 py-3">{{ t('invoicing.warehouse_col_name') }}</th>
              <th class="text-left px-3 py-3 w-40">{{ t('invoicing.warehouse_col_type') }}</th>
              <th class="text-left px-3 py-3 w-40">{{ t('invoicing.warehouse_col_supplier') }}</th>
              <th class="text-left px-3 py-3 w-32">{{ t('invoicing.warehouse_col_location') }}</th>
              <th class="text-center px-3 py-3 w-24">{{ t('invoicing.warehouse_col_default') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="w in warehouses"
              :key="w.id"
              class="border-t border-gray-100 hover:bg-gray-50"
            >
              <td class="px-3 py-3">
                <RouterLink :to="warehouseEditTo(w.id)" class="text-indigo-600 hover:underline font-medium">
                  {{ w.name }}
                </RouterLink>
                <p v-if="!w.is_active" class="text-xs text-amber-600 mt-0.5">{{ t('invoicing.warehouse_inactive') }}</p>
              </td>
              <td class="px-3 py-3 text-gray-700">{{ t(warehouseTypeLabelKey(w.type)) }}</td>
              <td class="px-3 py-3 text-gray-700">{{ w.contact_name || '—' }}</td>
              <td class="px-3 py-3 text-gray-700">{{ locationLine(w) }}</td>
              <td class="px-3 py-3 text-center">
                <span v-if="w.is_default" class="text-xs font-medium text-indigo-700">{{ t('common.yes') }}</span>
                <span v-else class="text-gray-400">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import { useStockRoutes } from '../../composables/useCompanyStockItem';
import {
  useWarehouseRoutes,
  warehouseTypeLabelKey,
  type WarehouseRow,
} from '../../composables/useCompanyWarehouse';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import api from '../../services/api';

const { t } = useI18n();
const route = useRoute();
const { companyId, rememberCompany } = useInvoicingLayout();
const { stockListTo } = useStockRoutes(companyId);
const { warehouseNewTo, warehouseEditTo } = useWarehouseRoutes(companyId);

const companyName = ref('');
const warehouses = ref<WarehouseRow[]>([]);
const loading = ref(true);
const error = ref('');

function locationLine(w: WarehouseRow): string {
  const parts = [w.city, w.country].filter(Boolean);
  return parts.length ? parts.join(', ') : '—';
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const companyRes = await api.get(`/invoicing/companies/${companyId.value}/summary`);
    companyName.value = companyRes.data.data?.trade_name || companyRes.data.data?.legal_name || '';

    const res = await api.get(`/invoicing/companies/${companyId.value}/warehouses`);
    warehouses.value = res.data.data ?? [];
  } catch (e: unknown) {
    warehouses.value = [];
    error.value =
      (e as { response?: { data?: { message?: string } } })?.response?.data?.message || t('errors.generic');
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  rememberCompany(companyId.value);
  void load();
});

watch(
  () => route.params.companyId,
  () => {
    rememberCompany(companyId.value);
    void load();
  }
);
</script>
