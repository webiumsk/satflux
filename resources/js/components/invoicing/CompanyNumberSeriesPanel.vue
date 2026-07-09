<template>
  <section class="invoicing-card-pad">
    <CompanyAppTabsNav :company-id="companyId" active-tab="series" />

    <div v-if="loading" class="text-sm text-gray-500 py-6">{{ t('common.loading') }}</div>

    <div v-else class="space-y-4">
      <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="w-full min-w-[800px] text-sm text-left">
          <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3">{{ t('invoicing.series_col_name') }}</th>
              <th class="px-4 py-3">{{ t('invoicing.series_col_type') }}</th>
              <th class="px-4 py-3">{{ t('invoicing.series_col_format') }}</th>
              <th class="px-4 py-3">{{ t('invoicing.series_col_next') }}</th>
              <th class="px-4 py-3">{{ t('invoicing.series_col_period') }}</th>
              <th class="px-4 py-3 w-16">{{ t('invoicing.series_col_id') }}</th>
              <th class="px-4 py-3 w-24"></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in series"
              :key="String(row.id)"
              class="border-t border-gray-100 hover:bg-indigo-50/40"
            >
              <td class="px-4 py-3 font-medium text-gray-900">
                {{ row.name }}
                <span v-if="row.is_default" class="ml-1 text-xs text-indigo-600">★</span>
              </td>
              <td class="px-4 py-3 text-gray-600">{{ documentTypeLabel(row.document_type) }}</td>
              <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ row.format }}</td>
              <td class="px-4 py-3 font-mono text-gray-900">{{ row.next_number_preview }}</td>
              <td class="px-4 py-3 text-gray-600">{{ periodLabel(row.reset_period) }}</td>
              <td class="px-4 py-3 text-gray-400 text-xs">{{ row.id }}</td>
              <td class="px-4 py-3">
                <div class="flex gap-1">
                  <button
                    type="button"
                    class="row-action-btn"
                    :title="t('common.edit')"
                    @click="openEdit(row)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                  </button>
                  <button
                    type="button"
                    class="row-action-btn row-action-btn--danger"
                    :title="t('common.delete')"
                    @click="remove(row)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!series.length">
              <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                {{ t('invoicing.series_empty') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <button type="button" class="invoicing-link text-sm font-medium" @click="openCreate">
        + {{ t('invoicing.series_add') }}
      </button>

      <p v-if="saveError" class="text-sm text-red-600">{{ saveError }}</p>
    </div>

    <div
      v-if="modalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
      @click.self="closeModal"
    >
      <form class="bg-white rounded-xl shadow-xl w-full max-w-md p-5 space-y-4" @submit.prevent="saveModal">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">
            {{ editingId ? t('invoicing.series_edit_title') : t('invoicing.series_add_title') }}
          </h3>
          <button type="button" class="text-gray-400 hover:text-gray-700 text-xl" @click="closeModal">×</button>
        </div>

        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.series_field_name') }}</label>
          <input v-model="form.name" type="text" required class="invoicing-sf-input" />
        </div>

        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.series_field_type') }} *</label>
          <select v-model="form.document_type" class="invoicing-sf-input" required>
            <option v-for="opt in documentTypeOptions" :key="opt.value" :value="opt.value">
              {{ t(opt.labelKey) }}
            </option>
          </select>
        </div>

        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.series_field_format') }} *</label>
          <select v-model="form.format" class="invoicing-sf-input mb-2">
            <option v-for="preset in FORMAT_PRESETS" :key="preset" :value="preset">{{ preset }}</option>
          </select>
          <input v-model="form.format" type="text" class="invoicing-sf-input font-mono text-sm uppercase" required />
          <p class="text-xs text-gray-500 mt-1">{{ t('invoicing.series_format_hint') }}</p>
        </div>

        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.series_field_period') }} *</label>
          <select v-model="form.reset_period" class="invoicing-sf-input" required>
            <option value="yearly">{{ t('invoicing.series_period_yearly') }}</option>
            <option value="monthly">{{ t('invoicing.series_period_monthly') }}</option>
            <option value="never">{{ t('invoicing.series_period_never') }}</option>
          </select>
        </div>

        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.series_field_next') }}</label>
          <input v-model.number="form.last_number" type="number" min="0" class="invoicing-sf-input" />
          <p class="text-xs text-gray-600 mt-1">
            {{ t('invoicing.series_next_preview') }}: <strong class="font-mono">{{ nextPreview }}</strong>
          </p>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.is_default" type="checkbox" class="rounded border-gray-300" />
          {{ t('invoicing.series_field_default') }}
        </label>

        <div class="flex justify-center pt-2">
          <button type="submit" class="invoicing-btn-primary px-10" :disabled="saving">
            {{ t('invoicing.series_confirm') }}
          </button>
        </div>
      </form>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useQuery } from '@evolu/vue';
import { invoicingApi } from '../../services/api';
import { useInvoicingSaveFeedback } from '../../composables/useInvoicingSaveFeedback';
import CompanyAppTabsNav from './CompanyAppTabsNav.vue';
import {
  DOCUMENT_TYPE_OPTIONS,
  FORMAT_PRESETS,
  emptySeriesForm,
  seriesToForm,
  type NumberSeriesRow,
} from '../../composables/useCompanyNumberSeries';
import { allNumberSeriesQuery, useInvoicingEvolu } from '../../evolu/client';
import { isInvoicingLocalFirst } from '../../evolu/flags';
import {
  createNumberSeries,
  deleteNumberSeries,
  seedDefaultNumberSeries,
  localizedDefaultSeries,
  updateNumberSeries,
} from '../../evolu/numberSeriesCrud';
import { previewNextNumber } from '../../evolu/numberSeriesFormat';
import {
  evoluNumberSeriesToApi,
  type EvoluNumberSeriesRow,
} from '../../evolu/numberSeriesMap';
import type { CompanyId, NumberSeriesId } from '../../evolu/schema';

const props = defineProps<{ companyId: string }>();

const { t } = useI18n();
const { notifySaved } = useInvoicingSaveFeedback();
const localFirst = isInvoicingLocalFirst();
const evolu = localFirst ? useInvoicingEvolu() : null;
const seriesRows = localFirst ? useQuery(allNumberSeriesQuery) : ref([]);

const series = ref<NumberSeriesRow[]>([]);
const loading = ref(true);
const saving = ref(false);
const saveError = ref('');
const modalOpen = ref(false);
const editingId = ref<number | string | null>(null);
const form = reactive(emptySeriesForm());

const documentTypeOptions = computed(() =>
  localFirst
    ? DOCUMENT_TYPE_OPTIONS.filter((opt) => opt.value !== 'order_issued')
    : DOCUMENT_TYPE_OPTIONS,
);

const nextPreview = computed(() => {
  const counter = Math.max(0, Number(form.last_number) || 0) + 1;
  return previewNextNumber(
    {
      format: form.format,
      resetPeriod: form.reset_period,
      periodKey: null,
      lastNumber: String(Math.max(0, Number(form.last_number) || 0)),
    },
    counter,
  );
});

function documentTypeLabel(type: string) {
  const opt = DOCUMENT_TYPE_OPTIONS.find((o) => o.value === type);
  return opt ? t(opt.labelKey) : type;
}

function periodLabel(period: string) {
  if (period === 'monthly') return t('invoicing.series_period_monthly');
  if (period === 'never') return t('invoicing.series_period_never');
  return t('invoicing.series_period_yearly');
}

function localSeriesRows(): EvoluNumberSeriesRow[] {
  return (seriesRows.value as EvoluNumberSeriesRow[]).filter(
    (row) => row.companyId === props.companyId,
  );
}

function syncLocalSeriesList() {
  series.value = localSeriesRows()
    .map(evoluNumberSeriesToApi)
    .sort((a, b) => {
      const typeCmp = a.document_type.localeCompare(b.document_type);
      if (typeCmp !== 0) return typeCmp;
      if (a.is_default !== b.is_default) return a.is_default ? -1 : 1;
      return a.name.localeCompare(b.name, 'sk');
    });
}

async function loadLocal() {
  if (!evolu) return;
  loading.value = true;
  saveError.value = '';
  try {
    await evolu.loadQuery(allNumberSeriesQuery);
    if (localSeriesRows().length === 0) {
      seedDefaultNumberSeries(
        evolu,
        props.companyId as CompanyId,
        seriesRows.value as EvoluNumberSeriesRow[],
        localizedDefaultSeries(t),
      );
      await evolu.loadQuery(allNumberSeriesQuery);
    }
    syncLocalSeriesList();
  } finally {
    loading.value = false;
  }
}

async function loadServer() {
  loading.value = true;
  saveError.value = '';
  try {
    series.value = await invoicingApi.numberSeries.list(props.companyId);
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? t('common.error_generic');
  } finally {
    loading.value = false;
  }
}

async function load() {
  if (localFirst) await loadLocal();
  else await loadServer();
}

function openCreate() {
  editingId.value = null;
  Object.assign(form, emptySeriesForm());
  modalOpen.value = true;
}

function openEdit(row: NumberSeriesRow) {
  editingId.value = row.id;
  Object.assign(form, seriesToForm(row));
  modalOpen.value = true;
}

function closeModal() {
  modalOpen.value = false;
  editingId.value = null;
}

async function saveModalLocal() {
  if (!evolu) return;
  saving.value = true;
  saveError.value = '';
  const payload = { ...form, format: form.format.toUpperCase() };
  try {
    const rows = localSeriesRows();
    if (editingId.value) {
      const existing = rows.find((row) => row.id === editingId.value);
      if (!existing) {
        saveError.value = t('common.error_generic');
        return;
      }
      const result = updateNumberSeries(
        evolu,
        editingId.value as NumberSeriesId,
        props.companyId as CompanyId,
        rows,
        payload,
        existing,
      );
      if (!result.ok) {
        saveError.value = t('common.error_generic');
        return;
      }
    } else {
      const result = createNumberSeries(
        evolu,
        props.companyId as CompanyId,
        rows,
        payload,
      );
      if (!result.ok) {
        saveError.value = t('common.error_generic');
        return;
      }
    }
    closeModal();
    await loadLocal();
    notifySaved('invoicing.series_saved');
  } finally {
    saving.value = false;
  }
}

async function saveModalServer() {
  saving.value = true;
  saveError.value = '';
  const payload = { ...form, format: form.format.toUpperCase() };
  try {
    if (editingId.value) {
      await invoicingApi.numberSeries.update(props.companyId, editingId.value, payload);
    } else {
      await invoicingApi.numberSeries.create(props.companyId, payload);
    }
    closeModal();
    await loadServer();
    notifySaved('invoicing.series_saved');
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? t('common.error_generic');
  } finally {
    saving.value = false;
  }
}

async function saveModal() {
  if (localFirst) await saveModalLocal();
  else await saveModalServer();
}

async function removeLocal(row: NumberSeriesRow) {
  if (!evolu) return;
  const result = deleteNumberSeries(
    evolu,
    row.id as NumberSeriesId,
    props.companyId as CompanyId,
    localSeriesRows(),
  );
  if (!result.ok) {
    saveError.value = t('common.error_generic');
    return;
  }
  await loadLocal();
  notifySaved('invoicing.series_saved');
}

async function removeServer(row: NumberSeriesRow) {
  try {
    await invoicingApi.numberSeries.delete(props.companyId, row.id);
    await loadServer();
    notifySaved('invoicing.series_saved');
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? t('common.error_generic');
  }
}

async function remove(row: NumberSeriesRow) {
  if (!window.confirm(t('invoicing.series_confirm_delete', { name: row.name }))) return;
  if (localFirst) await removeLocal(row);
  else await removeServer(row);
}

if (localFirst) {
  watch(seriesRows, syncLocalSeriesList, { deep: true });
}

onMounted(load);
</script>

<style scoped>
.row-action-btn {
  @apply p-2 text-black/90 hover:text-white hover:bg-indigo-500/80 rounded transition-colors inline-flex items-center justify-center;
}

.row-action-btn--danger {
  @apply hover:bg-red-500/80;
}
</style>
