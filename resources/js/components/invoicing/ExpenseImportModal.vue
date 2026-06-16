<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
    role="dialog"
    aria-modal="true"
    @click.self="$emit('close')"
  >
    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
      <div class="px-5 py-3 bg-slate-800 text-white flex items-center justify-between shrink-0">
        <h2 class="text-lg font-semibold">{{ t('invoicing.expense_import_title') }}</h2>
        <button type="button" class="text-white/80 hover:text-white text-2xl leading-none" @click="$emit('close')">
          ×
        </button>
      </div>

      <div class="p-5 space-y-4 overflow-y-auto">
        <p class="text-sm text-gray-700">
          {{ t('invoicing.expense_import_intro') }}
          <button type="button" class="text-indigo-600 hover:underline" @click="downloadExample">
            {{ t('invoicing.expense_import_example_link') }}
          </button>
        </p>

        <div
          class="rounded-lg border-2 border-dashed transition-colors min-h-[120px] flex flex-col items-center justify-center p-6 text-center"
          :class="
            dragOver
              ? 'border-indigo-500 bg-indigo-50'
              : 'border-indigo-300 bg-indigo-50/30 hover:border-indigo-400'
          "
          @dragenter.prevent="dragOver = true"
          @dragover.prevent="dragOver = true"
          @dragleave.prevent="onDragLeave"
          @drop.prevent="onDrop"
        >
          <label class="invoicing-btn-primary cursor-pointer">
            {{ loadingPreview ? t('common.loading') : t('invoicing.expense_import_drop') }}
            <input
              ref="fileInput"
              type="file"
              class="hidden"
              accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
              :disabled="importing || loadingPreview"
              @change="onFileChange"
            />
          </label>
          <p v-if="selectedFile" class="mt-3 text-xs text-gray-600 truncate max-w-full" :title="selectedFile.name">
            {{ selectedFile.name }}
          </p>
        </div>

        <div v-if="headers.length" class="space-y-3 rounded-lg border border-gray-200 p-4">
          <div>
            <h3 class="font-semibold text-gray-800">{{ t('invoicing.expense_import_mapping_title') }}</h3>
            <p class="text-sm text-gray-600 mt-1">{{ t('invoicing.expense_import_mapping_hint') }}</p>
          </div>

          <div class="space-y-2 max-h-[320px] overflow-auto pr-1">
            <div
              v-for="field in EXPENSE_IMPORT_FIELD_KEYS"
              :key="field"
              class="grid sm:grid-cols-2 gap-2 items-center text-sm"
            >
              <span class="text-gray-700">
                {{ t(`invoicing.expense_import_field_${field}`) }}
                <span v-if="isRequired(field)" class="text-red-500">*</span>
              </span>
              <select v-model="mapping[field]" class="invoicing-sf-input">
                <option :value="null">—</option>
                <option v-for="(header, idx) in headers" :key="idx" :value="idx">
                  {{ header || t('invoicing.import_column', { n: idx + 1 }) }}
                </option>
              </select>
            </div>
          </div>

          <p v-if="rowCount" class="text-xs text-gray-500">
            {{ t('invoicing.expense_import_row_count', { count: rowCount }) }}
          </p>
        </div>

        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

        <div v-if="result" class="text-sm space-y-2 rounded-lg border border-gray-200 bg-gray-50 p-3">
          <p class="text-green-700 font-medium">
            {{ t('invoicing.expense_import_result', { imported: result.imported, skipped: result.skipped }) }}
          </p>
          <div v-if="result.errors.length">
            <p class="font-medium text-amber-900 mb-1">
              {{ t('invoicing.expense_import_skipped_heading', { count: result.errors.length }) }}
            </p>
            <ul class="text-amber-800 space-y-1 max-h-32 overflow-auto">
              <li v-for="(err, idx) in result.errors" :key="idx">
                {{ formatSkippedError(err) }}
              </li>
            </ul>
          </div>
        </div>

        <div class="flex flex-wrap justify-end gap-3 pt-2">
          <button type="button" class="invoicing-btn-secondary" :disabled="importing" @click="$emit('close')">
            {{ result ? t('common.close') : t('common.cancel') }}
          </button>
          <button
            type="button"
            class="invoicing-btn-primary"
            :disabled="!selectedFile || importing || loadingPreview || !mappingReady"
            @click="submitImport"
          >
            {{ importing ? t('common.loading') : t('invoicing.expense_import_submit') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, toRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
  EXPENSE_IMPORT_FIELD_KEYS,
  REQUIRED_EXPENSE_IMPORT_FIELDS,
  type ExpenseImportFieldKey,
  type ExpenseImportMapping,
} from '../../composables/useExpenseImportFields';
import { useInvoicingExpenses } from '../../composables/useInvoicingExpenses';
import api from '../../services/api';
import { useInvoicingEvolu } from '../../evolu/client';
import {
  downloadExpenseImportExampleCsv,
  importLocalExpensesFromFile,
  previewLocalExpenseImport,
} from '../../evolu/expenseImportLocal';
import type { CompanyId } from '../../evolu/schema';

const props = defineProps<{
  open: boolean;
  companyId: string;
  localFirst?: boolean;
}>();

const emit = defineEmits<{
  close: [];
  imported: [];
}>();

const { t } = useI18n();
const companyIdRef = toRef(props, 'companyId');
const invoicingExpenses = props.localFirst ? useInvoicingExpenses(companyIdRef) : null;
const evolu = props.localFirst ? useInvoicingEvolu() : null;

type ImportSkippedError = {
  row: number;
  internal_number?: string | null;
  message: string;
};

const dragOver = ref(false);
const selectedFile = ref<File | null>(null);
const headers = ref<string[]>([]);
const mapping = reactive<ExpenseImportMapping>({});
const rowCount = ref(0);
const loadingPreview = ref(false);
const importing = ref(false);
const error = ref('');
const result = ref<{ imported: number; skipped: number; errors: ImportSkippedError[] } | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const mappingReady = computed(() =>
  REQUIRED_EXPENSE_IMPORT_FIELDS.every((field) => mapping[field] !== null && mapping[field] !== undefined)
);

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      resetState();
    }
  }
);

function resetState() {
  selectedFile.value = null;
  headers.value = [];
  for (const field of EXPENSE_IMPORT_FIELD_KEYS) {
    mapping[field] = null;
  }
  rowCount.value = 0;
  loadingPreview.value = false;
  importing.value = false;
  error.value = '';
  result.value = null;
  dragOver.value = false;
  if (fileInput.value) fileInput.value.value = '';
}

function isRequired(field: ExpenseImportFieldKey) {
  return REQUIRED_EXPENSE_IMPORT_FIELDS.includes(field);
}

function formatSkippedError(err: ImportSkippedError) {
  if (err.internal_number) {
    return t('invoicing.expense_import_skipped_row_with_number', {
      number: err.internal_number,
      row: err.row,
      message: err.message,
    });
  }

  return t('invoicing.expense_import_skipped_row', {
    row: err.row,
    message: err.message,
  });
}

function onDragLeave() {
  dragOver.value = false;
}

function pickFile(file: File | undefined) {
  if (!file) return;
  selectedFile.value = file;
  error.value = '';
  result.value = null;
  void loadPreview();
}

function onDrop(e: DragEvent) {
  dragOver.value = false;
  pickFile(e.dataTransfer?.files?.[0]);
}

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement;
  pickFile(input.files?.[0]);
}

function buildFormData(includeMapping: boolean) {
  const form = new FormData();
  if (selectedFile.value) form.append('file', selectedFile.value);
  if (includeMapping) form.append('mapping', JSON.stringify(mapping));
  return form;
}

async function loadPreview() {
  if (!selectedFile.value) return;
  loadingPreview.value = true;
  error.value = '';
  try {
    if (props.localFirst) {
      const preview = await previewLocalExpenseImport(selectedFile.value);
      headers.value = preview.headers;
      rowCount.value = preview.row_count;
      for (const field of EXPENSE_IMPORT_FIELD_KEYS) {
        mapping[field] = preview.suggested_mapping[field] ?? null;
      }
      return;
    }
    const { data } = await api.post(
      `/invoicing/companies/${props.companyId}/expenses/import/excel/preview`,
      buildFormData(false)
    );
    headers.value = data.data.headers ?? [];
    rowCount.value = data.data.row_count ?? 0;
    const suggested = data.data.suggested_mapping ?? {};
    for (const field of EXPENSE_IMPORT_FIELD_KEYS) {
      mapping[field] = suggested[field] ?? null;
    }
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('errors.generic');
    headers.value = [];
    rowCount.value = 0;
  } finally {
    loadingPreview.value = false;
  }
}

async function downloadExample() {
  try {
    if (props.localFirst) {
      downloadExpenseImportExampleCsv();
      return;
    }
    const { data } = await api.get(`/invoicing/companies/${props.companyId}/expenses/import/excel/example`, {
      responseType: 'blob',
    });
    const url = URL.createObjectURL(data);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'expense_import_example.xlsx';
    a.click();
    URL.revokeObjectURL(url);
  } catch {
    error.value = t('errors.generic');
  }
}

async function submitImport() {
  if (!selectedFile.value || !mappingReady.value) return;
  importing.value = true;
  error.value = '';
  try {
    if (props.localFirst && evolu && invoicingExpenses) {
      const importResult = await importLocalExpensesFromFile(
        evolu,
        props.companyId as CompanyId,
        selectedFile.value,
        mapping,
        invoicingExpenses.expenseRows.value,
      );
      result.value = importResult;
      if (importResult.imported > 0) {
        await invoicingExpenses.refresh();
        emit('imported');
      }
      return;
    }
    const { data } = await api.post(
      `/invoicing/companies/${props.companyId}/expenses/import/excel`,
      buildFormData(true)
    );
    result.value = data.data;
    if (data.data.imported > 0) {
      emit('imported');
    }
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('errors.generic');
  } finally {
    importing.value = false;
  }
}
</script>
