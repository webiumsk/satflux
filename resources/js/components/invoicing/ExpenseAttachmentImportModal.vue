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
        <h2 class="text-lg font-semibold">{{ t('invoicing.expense_attach_import_title') }}</h2>
        <button type="button" class="text-white/80 hover:text-white text-2xl leading-none" @click="$emit('close')">
          ×
        </button>
      </div>

      <div class="p-5 space-y-4 overflow-y-auto">
        <p class="text-sm text-gray-700">{{ t('invoicing.expense_attach_import_intro') }}</p>
        <p class="text-xs text-gray-500">{{ t('invoicing.expense_attach_import_sf_hint') }}</p>

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
            {{ loading ? t('common.loading') : t('invoicing.expense_attach_import_drop') }}
            <input
              ref="fileInput"
              type="file"
              class="hidden"
              accept=".zip,.pdf,application/zip,application/pdf"
              :disabled="importing || loading"
              @change="onFileChange"
            />
          </label>
          <p v-if="selectedFile" class="mt-3 text-xs text-gray-600 truncate max-w-full" :title="selectedFile.name">
            {{ selectedFile.name }}
          </p>
        </div>

        <div v-if="preview" class="space-y-3 rounded-lg border border-gray-200 p-4">
          <p class="text-sm text-gray-700">
            {{ t('invoicing.expense_attach_import_preview_summary', {
              matched: preview.matched,
              unmatched: preview.unmatched,
            }) }}
          </p>

          <div class="overflow-x-auto max-h-64 border border-gray-100 rounded">
            <table class="w-full text-sm text-left">
              <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                <tr>
                  <th class="px-3 py-2">{{ t('invoicing.expense_attach_import_col_file') }}</th>
                  <th class="px-3 py-2">{{ t('invoicing.expense_attach_import_col_expense') }}</th>
                  <th class="px-3 py-2">{{ t('invoicing.expense_attach_import_col_status') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, idx) in preview.files" :key="idx" class="border-t border-gray-100">
                  <td class="px-3 py-2 truncate max-w-[200px]" :title="row.filename">{{ row.filename }}</td>
                  <td class="px-3 py-2 whitespace-nowrap">
                    {{ row.internal_number || '—' }}
                    <span v-if="row.matched_by" class="text-xs text-gray-500">({{ row.matched_by }})</span>
                  </td>
                  <td class="px-3 py-2 text-xs" :class="statusClass(row.status)">
                    {{ statusLabel(row.status) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

        <div v-if="result" class="text-sm space-y-2 rounded-lg border border-gray-200 bg-gray-50 p-3">
          <p class="text-green-700 font-medium">
            {{ t('invoicing.expense_attach_import_result', { attached: result.attached, skipped: result.skipped }) }}
          </p>
          <ul v-if="result.errors.length" class="text-amber-800 space-y-1 max-h-32 overflow-auto">
            <li v-for="(err, idx) in result.errors" :key="idx">
              {{ err.filename }}: {{ err.message }}
            </li>
          </ul>
        </div>

        <div class="flex flex-wrap justify-end gap-3 pt-2">
          <button type="button" class="invoicing-btn-secondary" :disabled="importing" @click="$emit('close')">
            {{ result ? t('common.close') : t('common.cancel') }}
          </button>
          <button
            v-if="!result"
            type="button"
            class="invoicing-btn-secondary"
            :disabled="!selectedFile || loading"
            @click="loadPreview"
          >
            {{ t('invoicing.expense_attach_import_preview_btn') }}
          </button>
          <button
            type="button"
            class="invoicing-btn-primary"
            :disabled="!selectedFile || importing || loading || !preview?.matched"
            @click="submitImport"
          >
            {{ importing ? t('common.loading') : t('invoicing.expense_attach_import_submit') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../../services/api';

const props = defineProps<{
  open: boolean;
  companyId: string;
}>();

const emit = defineEmits<{
  close: [];
  imported: [];
}>();

const { t } = useI18n();

type PreviewRow = {
  filename: string;
  matched_by: string | null;
  internal_number: string | null;
  expense_id: string | null;
  status: string;
};

type PreviewData = {
  files: PreviewRow[];
  matched: number;
  unmatched: number;
};

const dragOver = ref(false);
const selectedFile = ref<File | null>(null);
const preview = ref<PreviewData | null>(null);
const loading = ref(false);
const importing = ref(false);
const error = ref('');
const result = ref<{ attached: number; skipped: number; errors: { filename: string; message: string }[] } | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      selectedFile.value = null;
      preview.value = null;
      loading.value = false;
      importing.value = false;
      error.value = '';
      result.value = null;
      dragOver.value = false;
      if (fileInput.value) fileInput.value.value = '';
    }
  }
);

function onDragLeave() {
  dragOver.value = false;
}

function pickFile(file: File | undefined) {
  if (!file) return;
  selectedFile.value = file;
  preview.value = null;
  result.value = null;
  error.value = '';
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

function statusLabel(status: string) {
  if (status === 'matched') return t('invoicing.expense_attach_import_status_matched');
  if (status === 'has_attachment') return t('invoicing.expense_attach_import_status_has_attachment');
  return t('invoicing.expense_attach_import_status_unmatched');
}

function statusClass(status: string) {
  if (status === 'matched') return 'text-green-700';
  if (status === 'has_attachment') return 'text-amber-700';
  return 'text-red-600';
}

async function loadPreview() {
  if (!selectedFile.value) return;
  loading.value = true;
  error.value = '';
  try {
    const form = new FormData();
    form.append('file', selectedFile.value);
    const { data } = await api.post(
      `/invoicing/companies/${props.companyId}/expenses/import/attachments/preview`,
      form
    );
    preview.value = data.data;
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('errors.generic');
    preview.value = null;
  } finally {
    loading.value = false;
  }
}

async function submitImport() {
  if (!selectedFile.value || !preview.value?.matched) return;
  importing.value = true;
  error.value = '';
  try {
    const form = new FormData();
    form.append('file', selectedFile.value);
    const { data } = await api.post(
      `/invoicing/companies/${props.companyId}/expenses/import/attachments`,
      form
    );
    result.value = data.data;
    if (data.data.attached > 0) {
      emit('imported');
    }
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('errors.generic');
  } finally {
    importing.value = false;
  }
}
</script>
