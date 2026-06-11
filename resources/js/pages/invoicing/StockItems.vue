<template>
  <InvoicingPageShell content-class="pb-8">
    <template #header>
      <InvoicingAppHeader :company-label="companyName">
        <template #filters>
          <input
            v-model="searchQuery"
            type="search"
            class="invoicing-sf-input max-w-xs min-w-[200px]"
            :placeholder="t('invoicing.stock_search')"
            @input="onSearchInput"
          />
        </template>
        <template #actions>
          <button type="button" class="invoicing-btn-secondary" @click="showImportModal = true">
            {{ t('invoicing.stock_bulk_import') }}
          </button>
          <RouterLink :to="stockNewTo()" class="invoicing-btn-primary">
            + {{ t('invoicing.stock_new_item') }}
          </RouterLink>
        </template>
      </InvoicingAppHeader>
    </template>

    <p v-if="success" class="text-sm text-green-700 mb-4">{{ success }}</p>
    <p v-if="error" class="text-sm text-red-600 mb-4">{{ error }}</p>

    <div v-if="selectionCount > 0" class="invoicing-bulk-bar">
      <span class="text-sm text-indigo-800 font-medium">
        {{ t('invoicing.bulk_selected', { count: selectionCount }) }}
      </span>
      <button type="button" class="invoicing-btn-secondary text-sm py-1.5 text-red-600" @click="bulkDelete">
        {{ t('invoicing.stock_bulk_delete') }}
      </button>
      <button type="button" class="invoicing-btn-secondary text-sm py-1.5" @click="clearSelection">
        {{ t('invoicing.bulk_clear') }}
      </button>
    </div>

    <div v-if="loading" class="invoicing-muted py-8">{{ t('common.loading') }}</div>

    <div v-else-if="items.length === 0" class="invoicing-card-pad text-center text-gray-600">
      {{ t('invoicing.stock_empty') }}
    </div>

    <div v-else class="space-y-4">
      <div class="invoicing-card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[900px] text-sm">
            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 text-xs uppercase tracking-wide">
              <tr>
                <th class="w-12 px-2 py-3 text-center">
                  <input
                    type="checkbox"
                    class="rounded border-gray-300"
                    :checked="allSelected"
                    @change="toggleSelectAll"
                  />
                </th>
                <th class="text-left px-3 py-3 min-w-[220px]">{{ t('invoicing.stock_col_name') }}</th>
                <th class="text-left px-3 py-3 w-28">{{ t('invoicing.stock_col_sku') }}</th>
                <th class="text-right px-3 py-3 w-32">{{ t('invoicing.stock_col_purchase') }}</th>
                <th class="text-right px-3 py-3 w-32">{{ t('invoicing.stock_col_sale') }}</th>
                <th class="text-right px-3 py-3 w-28">{{ t('invoicing.stock_col_on_hand') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in items"
                :key="item.id"
                class="border-t border-gray-100 hover:bg-gray-50"
                :class="selectedIds.has(item.id) ? 'bg-indigo-50' : 'bg-white'"
              >
                <td class="px-2 py-3 text-center">
                  <input
                    type="checkbox"
                    class="rounded border-gray-300"
                    :checked="selectedIds.has(item.id)"
                    @change="toggleRow(item.id)"
                  />
                </td>
                <td class="px-3 py-3 align-top">
                  <RouterLink :to="stockEditTo(item.id)" class="text-indigo-600 hover:underline font-medium">
                    {{ item.name }}
                  </RouterLink>
                  <p v-if="item.description" class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ item.description }}</p>
                </td>
                <td class="px-3 py-3 text-gray-700">{{ item.sku || '—' }}</td>
                <td class="px-3 py-3 text-right whitespace-nowrap">
                  {{ formatStockPrice(item.purchase_unit_price, item.purchase_currency) }}
                </td>
                <td class="px-3 py-3 text-right whitespace-nowrap">
                  {{ formatStockPrice(item.sale_unit_price, summaryCurrency) }}
                </td>
                <td class="px-3 py-3 text-right whitespace-nowrap">
                  <template v-if="item.track_inventory">
                    {{ formatStockQuantity(item.quantity_on_hand, item.unit) }}
                  </template>
                  <span v-else class="text-gray-400">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div
        v-if="summary"
        class="ml-auto max-w-xs w-full sm:w-auto rounded-lg border border-gray-200 bg-white shadow-sm p-4 text-sm space-y-1"
      >
        <p class="font-medium text-gray-800">
          {{ t('invoicing.stock_summary_count', { count: summary.item_count }) }}
        </p>
        <p class="text-gray-600">
          {{ t('invoicing.stock_summary_purchase') }}:
          <span class="font-medium text-gray-900">
            {{ formatStockPrice(summary.purchase_value_total, summary.summary_currency) }}
          </span>
        </p>
        <p class="text-gray-600">
          {{ t('invoicing.stock_summary_sale') }}:
          <span class="font-medium text-gray-900">
            {{ formatStockPrice(summary.sale_value_total, summary.summary_currency) }}
          </span>
        </p>
      </div>
    </div>

    <StockImportModal
      :open="showImportModal"
      :company-id="companyId"
      @close="showImportModal = false"
      @imported="onImported"
    />
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import StockImportModal from '../../components/invoicing/StockImportModal.vue';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import {
  formatStockPrice,
  formatStockQuantity,
  useStockRoutes,
  type StockItemRow,
  type StockSummaryMeta,
} from '../../composables/useCompanyStockItem';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import api from '../../services/api';

const { t } = useI18n();
const route = useRoute();
const { companyId, rememberCompany } = useInvoicingLayout();
const { stockNewTo, stockEditTo } = useStockRoutes(companyId);

const companyName = ref('');
const items = ref<StockItemRow[]>([]);
const summary = ref<StockSummaryMeta | null>(null);
const loading = ref(true);
const searchQuery = ref('');
const selectedIds = ref(new Set<string>());
const showImportModal = ref(false);
const success = ref('');
const error = ref('');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const summaryCurrency = computed(() => summary.value?.summary_currency ?? 'EUR');
const selectionCount = computed(() => selectedIds.value.size);
const allSelected = computed(() => items.value.length > 0 && selectedIds.value.size === items.value.length);

function onSearchInput() {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    clearSelection();
    void load();
  }, 300);
}

function toggleRow(id: string) {
  const next = new Set(selectedIds.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  selectedIds.value = next;
}

function toggleSelectAll() {
  if (allSelected.value) {
    selectedIds.value = new Set();
  } else {
    selectedIds.value = new Set(items.value.map((i) => i.id));
  }
}

function clearSelection() {
  selectedIds.value = new Set();
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const companyRes = await api.get(`/invoicing/companies/${companyId.value}/summary`);
    companyName.value = companyRes.data.data?.trade_name || companyRes.data.data?.legal_name || '';

    const res = await api.get(`/invoicing/companies/${companyId.value}/stock-items`, {
      params: { q: searchQuery.value.trim() || undefined },
    });
    items.value = res.data.data ?? [];
    summary.value = res.data.meta ?? null;
  } catch (e: unknown) {
    items.value = [];
    summary.value = null;
    error.value =
      (e as { response?: { data?: { message?: string } } })?.response?.data?.message || t('errors.generic');
  } finally {
    loading.value = false;
  }
}

function onImported() {
  success.value = t('invoicing.stock_import_success');
  void load();
}

async function bulkDelete() {
  if (selectionCount.value === 0) return;
  if (!confirm(t('invoicing.stock_bulk_delete_confirm', { count: selectionCount.value }))) return;

  const ids = Array.from(selectedIds.value);
  let deleted = 0;
  for (const id of ids) {
    try {
      await api.delete(`/invoicing/companies/${companyId.value}/stock-items/${id}`);
      deleted++;
    } catch {
      /* skip items that cannot be deleted */
    }
  }
  success.value = t('invoicing.stock_bulk_deleted', { count: deleted });
  clearSelection();
  await load();
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
