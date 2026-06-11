<template>
  <InvoicingPageShell>
    <template #header>
      <InvoicingAppHeader :show-filter-bar="false" />
    </template>
    <template #toolbar>
      <RouterLink :to="stockListTo()" class="invoicing-back mb-0">
        ← {{ t('invoicing.stock_title') }}
      </RouterLink>
    </template>

    <div class="flex flex-wrap items-start justify-between gap-4 mb-2">
      <h1 class="invoicing-title">
        {{ isNew ? t('invoicing.stock_new_item') : form.name || t('invoicing.stock_edit_item') }}
      </h1>
      <div v-if="!isNew && neighborIds.length > 1" class="flex items-center gap-2 text-sm text-gray-600">
        <button
          type="button"
          class="invoicing-btn-secondary py-1 px-2"
          :disabled="neighborIndex <= 0"
          @click="goNeighbor(-1)"
        >
          ‹
        </button>
        <span>{{ neighborIndex + 1 }} / {{ neighborIds.length }}</span>
        <button
          type="button"
          class="invoicing-btn-secondary py-1 px-2"
          :disabled="neighborIndex >= neighborIds.length - 1"
          @click="goNeighbor(1)"
        >
          ›
        </button>
      </div>
    </div>

    <form class="grid lg:grid-cols-[1fr_280px] gap-6" @submit.prevent="save">
      <div class="invoicing-card-pad space-y-4">
        <div>
          <label class="invoicing-sf-label">
            {{ t('invoicing.stock_field_name') }}
            <span class="text-red-500">*</span>
          </label>
          <input v-model="form.name" type="text" class="invoicing-sf-input" required />
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.stock_col_sku') }}</label>
            <input v-model="form.sku" type="text" class="invoicing-sf-input" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.stock_field_unit') }}</label>
            <input v-model="form.unit" type="text" class="invoicing-sf-input" />
          </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.track_inventory" type="checkbox" class="rounded border-gray-300" />
          {{ t('invoicing.stock_field_track_inventory') }}
        </label>

        <div v-if="form.track_inventory">
          <label class="invoicing-sf-label">
            {{ t('invoicing.stock_field_quantity') }}
            <span class="text-red-500">*</span>
          </label>
          <input v-model.number="form.quantity_on_hand" type="number" step="any" class="invoicing-sf-input" required />
        </div>

        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.stock_field_description') }}</label>
          <textarea v-model="form.description" rows="4" class="invoicing-sf-input" />
        </div>

        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.stock_field_internal_note') }}</label>
          <textarea v-model="form.internal_note" rows="3" class="invoicing-sf-input" />
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.exclude_from_suggester" type="checkbox" class="rounded border-gray-300" />
          {{ t('invoicing.stock_field_exclude_suggester') }}
        </label>
      </div>

      <div class="space-y-4">
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-4">
          <h3 class="font-semibold text-gray-800">{{ t('invoicing.stock_purchase_price') }}</h3>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.col_unit_price') }}</label>
            <input
              v-model.number="form.purchase_unit_price"
              type="number"
              min="0"
              step="0.01"
              class="invoicing-sf-input"
            />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.stock_field_currency') }}</label>
            <select v-model="form.purchase_currency" class="invoicing-sf-input">
              <option v-for="c in currencyOptions" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>

          <h3 class="font-semibold text-gray-800 pt-2 border-t border-gray-200">{{ t('invoicing.stock_sale_price') }}</h3>
          <div>
            <label class="invoicing-sf-label">
              {{ t('invoicing.col_unit_price') }}
              <span class="text-red-500">*</span>
            </label>
            <input v-model.number="form.sale_unit_price" type="number" min="0" step="0.01" class="invoicing-sf-input" />
          </div>
          <p class="text-xs text-gray-500">{{ t('invoicing.stock_sale_currency_hint', { currency: saleCurrency }) }}</p>
        </div>

        <div class="flex justify-center">
          <button type="submit" class="invoicing-btn-primary px-8" :disabled="saving">
            {{ t('invoicing.stock_save_item') }}
          </button>
        </div>
        <p v-if="error" class="text-sm text-red-600 text-center">{{ error }}</p>
      </div>
    </form>

    <section v-if="!isNew && movements.length" class="mt-8 invoicing-card overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
        <h2 class="font-semibold text-gray-800">{{ t('invoicing.stock_history_title') }}</h2>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[700px]">
          <thead class="text-xs uppercase text-gray-500 bg-white border-b border-gray-100">
            <tr>
              <th class="text-left px-3 py-2">{{ t('invoicing.stock_history_date') }}</th>
              <th class="text-right px-3 py-2">{{ t('invoicing.stock_history_quantity') }}</th>
              <th class="text-right px-3 py-2">{{ t('invoicing.stock_col_purchase') }}</th>
              <th class="text-right px-3 py-2">{{ t('invoicing.stock_col_sale') }}</th>
              <th class="text-left px-3 py-2">{{ t('invoicing.stock_history_note') }}</th>
              <th class="text-left px-3 py-2">{{ t('invoicing.stock_history_document') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in movements" :key="m.id" class="border-t border-gray-100">
              <td class="px-3 py-2 whitespace-nowrap">{{ formatDate(m.created_at) }}</td>
              <td class="px-3 py-2 text-right">{{ m.quantity_after }}</td>
              <td class="px-3 py-2 text-right">{{ m.purchase_unit_price ?? '—' }}</td>
              <td class="px-3 py-2 text-right">{{ m.sale_unit_price ?? '—' }}</td>
              <td class="px-3 py-2 text-gray-600">{{ m.note || '—' }}</td>
              <td class="px-3 py-2">{{ m.document_number || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import {
  emptyStockItemForm,
  formToStockPayload,
  stockItemToForm,
  useStockItemPage,
  useStockRoutes,
  type StockItemMovementRow,
} from '../../composables/useCompanyStockItem';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import api from '../../services/api';

const { t, locale } = useI18n();
const router = useRouter();
const { companyId, itemId, isNew } = useStockItemPage();
const { rememberCompany } = useInvoicingLayout();
const { stockListTo, stockEditTo } = useStockRoutes(companyId);

const form = reactive(emptyStockItemForm());
const saving = ref(false);
const error = ref('');
const saleCurrency = ref('EUR');
const neighborIds = ref<string[]>([]);
const movements = ref<StockItemMovementRow[]>([]);
const currencyOptions = ['EUR', 'CZK', 'USD', 'GBP', 'PLN', 'HUF'];

const neighborIndex = computed(() => {
  if (!itemId.value) return 0;
  const idx = neighborIds.value.indexOf(itemId.value);
  return idx >= 0 ? idx : 0;
});

function formatDate(iso?: string) {
  if (!iso) return '—';
  return new Date(iso).toLocaleString(locale.value);
}

async function loadCompany() {
  const res = await api.get(`/invoicing/companies/${companyId.value}/summary`);
  saleCurrency.value = res.data.data?.default_currency || 'EUR';
  Object.assign(form, emptyStockItemForm(saleCurrency.value));
}

async function loadItem() {
  if (isNew.value || !itemId.value) return;
  const res = await api.get(`/invoicing/companies/${companyId.value}/stock-items/${itemId.value}`);
  const data = res.data.data;
  Object.assign(form, stockItemToForm(data, saleCurrency.value));
  neighborIds.value = data.neighbor_ids ?? [];
  movements.value = data.movements ?? [];
}

async function save() {
  saving.value = true;
  error.value = '';
  try {
    const payload = formToStockPayload(form);
    if (isNew.value) {
      const res = await api.post(`/invoicing/companies/${companyId.value}/stock-items`, payload);
      await router.push(stockEditTo(res.data.data.id));
    } else {
      await api.patch(`/invoicing/companies/${companyId.value}/stock-items/${itemId.value}`, payload);
      await loadItem();
    }
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('errors.generic');
    const errors = e?.response?.data?.errors;
    if (errors) {
      const first = Object.values(errors)[0];
      if (Array.isArray(first) && first[0]) error.value = String(first[0]);
    }
  } finally {
    saving.value = false;
  }
}

function goNeighbor(delta: number) {
  const next = neighborIds.value[neighborIndex.value + delta];
  if (next) {
    router.push(stockEditTo(next));
  }
}

onMounted(async () => {
  rememberCompany(companyId.value);
  await loadCompany();
  await loadItem();
});

watch(itemId, async () => {
  await loadItem();
});
</script>
