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
              :key="row.id"
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
                    class="invoicing-btn-secondary px-2 py-1.5"
                    :title="t('common.edit')"
                    @click="openEdit(row)"
                  >
                    ✎
                  </button>
                  <button
                    type="button"
                    class="invoicing-btn-secondary px-2 py-1.5 text-red-700"
                    :title="t('common.delete')"
                    @click="remove(row)"
                  >
                    🗑
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
            <option v-for="opt in DOCUMENT_TYPE_OPTIONS" :key="opt.value" :value="opt.value">
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
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../../services/api';
import CompanyAppTabsNav from './CompanyAppTabsNav.vue';
import {
  DOCUMENT_TYPE_OPTIONS,
  FORMAT_PRESETS,
  emptySeriesForm,
  seriesToForm,
  type NumberSeriesRow,
} from '../../composables/useCompanyNumberSeries';

const props = defineProps<{ companyId: string }>();

const { t } = useI18n();

const series = ref<NumberSeriesRow[]>([]);
const loading = ref(true);
const saving = ref(false);
const saveError = ref('');
const modalOpen = ref(false);
const editingId = ref<number | null>(null);
const form = reactive(emptySeriesForm());

const nextPreview = computed(() => {
  const counter = Math.max(0, Number(form.last_number) || 0) + 1;
  return previewFormat(form.format, counter);
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

/** Client-side preview (same rules as backend). */
function previewFormat(pattern: string, counter: number) {
  const p = (pattern || 'RRRRCCCC').toUpperCase();
  const now = new Date();
  let out = '';
  let i = 0;
  while (i < p.length) {
    const ch = p[i];
    if (ch === 'R' || ch === 'M' || ch === 'C') {
      let run = 0;
      while (i < p.length && p[i] === ch) run++, i++;
      if (ch === 'R') {
        const y = String(now.getFullYear()).padStart(run, '0').slice(-run);
        out += y;
      } else if (ch === 'M') {
        const m = String(now.getMonth() + 1).padStart(run, '0').slice(-run);
        out += m;
      } else {
        out += String(counter).padStart(run, '0');
      }
    } else {
      out += ch;
      i++;
    }
  }
  return out;
}

async function load() {
  loading.value = true;
  saveError.value = '';
  try {
    const res = await api.get(`/invoicing/companies/${props.companyId}/number-series`);
    series.value = res.data.data ?? [];
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? t('common.error_generic');
  } finally {
    loading.value = false;
  }
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

async function saveModal() {
  saving.value = true;
  saveError.value = '';
  const payload = { ...form, format: form.format.toUpperCase() };
  try {
    if (editingId.value) {
      await api.patch(`/invoicing/companies/${props.companyId}/number-series/${editingId.value}`, payload);
    } else {
      await api.post(`/invoicing/companies/${props.companyId}/number-series`, payload);
    }
    closeModal();
    await load();
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? t('common.error_generic');
  } finally {
    saving.value = false;
  }
}

async function remove(row: NumberSeriesRow) {
  if (!window.confirm(t('invoicing.series_confirm_delete', { name: row.name }))) return;
  try {
    await api.delete(`/invoicing/companies/${props.companyId}/number-series/${row.id}`);
    await load();
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? t('common.error_generic');
  }
}

onMounted(load);
</script>
