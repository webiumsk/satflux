<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
    role="dialog"
    aria-modal="true"
    aria-labelledby="expense-import-title"
    @click.self="requestClose"
    @keydown.esc.prevent="requestClose"
    @keydown.tab="trapFocus"
  >
    <div
      ref="dialogPanel"
      tabindex="-1"
      class="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden"
    >
      <div
        class="px-5 py-3 bg-slate-800 text-white flex items-center justify-between shrink-0"
      >
        <h2 id="expense-import-title" class="text-lg font-semibold">
          {{ t("invoicing.expense_import_title") }}
        </h2>
        <button
          type="button"
          class="text-white/80 hover:text-white text-2xl leading-none"
          :disabled="importing"
          :aria-label="t('common.close')"
          @click="requestClose"
        >
          ×
        </button>
      </div>

      <div class="p-5 space-y-4 overflow-y-auto">
        <p class="text-sm text-gray-700">
          {{ t("invoicing.expense_import_intro") }}
          <button
            type="button"
            class="text-indigo-600 hover:underline"
            @click="downloadExample"
          >
            {{ t("invoicing.expense_import_example_link") }}
          </button>
        </p>
        <p class="text-sm text-gray-600">
          {{ t("invoicing.expense_import_metadata_hint") }}
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
          <label class="invoicing-btn-primary cursor-pointer focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2">
            {{
              loadingPreview
                ? t("common.loading")
                : t("invoicing.expense_import_drop")
            }}
            <input
              ref="fileInput"
              type="file"
              class="sr-only"
              accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
              :disabled="importing || loadingPreview"
              @change="onFileChange"
            />
          </label>
          <p
            v-if="selectedFile"
            class="mt-3 text-xs text-gray-600 truncate max-w-full"
            :title="selectedFile.name"
          >
            {{ selectedFile.name }}
          </p>
        </div>

        <div
          v-if="headers.length"
          class="space-y-3 rounded-lg border border-gray-200 p-4"
        >
          <div>
            <h3 class="font-semibold text-gray-800">
              {{ t("invoicing.expense_import_mapping_title") }}
            </h3>
            <p class="text-sm text-gray-600 mt-1">
              {{ t("invoicing.expense_import_mapping_hint") }}
            </p>
          </div>

          <div class="space-y-2 max-h-[320px] overflow-auto pr-1">
            <div
              v-for="field in EXPENSE_IMPORT_FIELD_KEYS"
              :key="field"
              class="grid sm:grid-cols-2 gap-2 items-center text-sm"
            >
              <label :for="`expense-import-${field}`" class="text-gray-700">
                {{ t(`invoicing.expense_import_field_${field}`) }}
                <span v-if="isRequired(field)" class="text-red-500">*</span>
              </label>
              <select :id="`expense-import-${field}`" v-model="mapping[field]" class="invoicing-sf-input" :disabled="importing || loadingPreview">
                <option :value="null">-</option>
                <option
                  v-for="(header, idx) in headers"
                  :key="idx"
                  :value="idx"
                >
                  {{ header || t("invoicing.import_column", { n: idx + 1 }) }}
                </option>
              </select>
            </div>
          </div>

          <p v-if="rowCount" class="text-xs text-gray-500">
            {{ t("invoicing.expense_import_row_count", { count: rowCount }) }}
          </p>
        </div>

        <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>

        <div
          v-if="result"
          role="status"
          class="text-sm space-y-2 rounded-lg border border-gray-200 bg-gray-50 p-3"
        >
          <p class="text-green-700 font-medium">
            {{
              t("invoicing.expense_import_result", {
                imported: result.imported,
                skipped: result.skipped,
              })
            }}
          </p>
          <div v-if="result.errors.length">
            <p class="font-medium text-amber-900 mb-1">
              {{
                t("invoicing.expense_import_skipped_heading", {
                  count: result.errors.length,
                })
              }}
            </p>
            <ul class="text-amber-800 space-y-1 max-h-32 overflow-auto">
              <li v-for="(err, idx) in result.errors" :key="idx">
                {{ formatSkippedError(err) }}
              </li>
            </ul>
          </div>
          <RouterLink
            v-if="result.imported > 0"
            :to="{ name: 'invoicing-expenses', params: { companyId }, query: { year: 'all' } }"
            class="text-indigo-600 hover:underline"
            @click="requestClose"
          >
            {{ t("invoicing.expense_import_go_expenses") }}
          </RouterLink>
        </div>

        <div class="flex flex-wrap justify-end gap-3 pt-2">
          <button
            type="button"
            class="invoicing-btn-secondary"
            :disabled="importing"
            @click="requestClose"
          >
            {{ result ? t("common.close") : t("common.cancel") }}
          </button>
          <button
            type="button"
            class="invoicing-btn-primary"
            :disabled="
              !selectedFile || importing || loadingPreview || !mappingReady
            "
            @click="submitImport"
          >
            {{
              importing
                ? t("common.loading")
                : t("invoicing.expense_import_submit")
            }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { asApiError } from "../../utils/apiError";
import { computed, nextTick, onBeforeUnmount, reactive, ref, toRef, watch } from "vue";
import { useI18n } from "vue-i18n";
import {
  EXPENSE_IMPORT_FIELD_KEYS,
  REQUIRED_EXPENSE_IMPORT_FIELDS,
  type ExpenseImportFieldKey,
  type ExpenseImportMapping,
} from "../../composables/useExpenseImportFields";
import { useInvoicingExpenses } from "../../composables/useInvoicingExpenses";
import { invoicingApi } from "../../services/api";
import { useInvoicingEvolu } from "../../evolu/client";
import {
  downloadExpenseImportExampleCsv,
  importLocalExpensesFromFile,
  previewLocalExpenseImport,
} from "../../evolu/expenseImportLocal";
import type { CompanyId } from "../../evolu/schema";

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
const companyIdRef = toRef(props, "companyId");
const invoicingExpenses = props.localFirst
  ? useInvoicingExpenses(companyIdRef)
  : null;
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
const error = ref("");
const result = ref<{
  imported: number;
  skipped: number;
  errors: ImportSkippedError[];
} | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const dialogPanel = ref<HTMLElement | null>(null);
let previewVersion = 0;
let disposed = false;
let previousFocus: HTMLElement | null = null;

onBeforeUnmount(() => {
  disposed = true;
  previewVersion += 1;
  previousFocus?.focus();
});

const mappingReady = computed(() =>
  REQUIRED_EXPENSE_IMPORT_FIELDS.every(
    (field) => mapping[field] !== null && mapping[field] !== undefined,
  ),
);

watch(
  () => [props.open, props.companyId, props.localFirst] as const,
  async () => {
    if (!importing.value) resetState();
    if (props.open) {
      if (!dialogPanel.value?.contains(document.activeElement)) {
        previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
      }
      await nextTick();
      dialogPanel.value?.focus();
    } else {
      previousFocus?.focus();
    }
  },
  { immediate: true },
);

function resetState() {
  previewVersion += 1;
  selectedFile.value = null;
  headers.value = [];
  for (const field of EXPENSE_IMPORT_FIELD_KEYS) {
    mapping[field] = null;
  }
  rowCount.value = 0;
  loadingPreview.value = false;
  importing.value = false;
  error.value = "";
  result.value = null;
  dragOver.value = false;
  if (fileInput.value) fileInput.value.value = "";
}

function requestClose() {
  if (importing.value) return;
  previewVersion += 1;
  emit("close");
}

function trapFocus(event: KeyboardEvent) {
  const panel = dialogPanel.value;
  if (!panel) return;
  const focusable = Array.from(panel.querySelectorAll<HTMLElement>(
    'button:not([disabled]), select:not([disabled]), a[href], input:not([disabled])',
  ));
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (!first || !last) {
    event.preventDefault();
    panel.focus();
  } else if (event.shiftKey && (document.activeElement === first || document.activeElement === panel)) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && (document.activeElement === last || document.activeElement === panel)) {
    event.preventDefault();
    first.focus();
  }
}

function importError(raw: unknown): string {
  const code = raw instanceof Error ? raw.message : "";
  const keys: Record<string, string> = {
    unsupported_file: "expense_import_error_file",
    file_too_large: "expense_import_error_size",
    too_many_rows: "expense_import_error_rows",
    too_many_columns: "expense_import_error_columns",
    no_data_rows: "expense_import_error_empty",
    invalid_csv: "expense_import_error_file",
  };
  if (keys[code]) return t(`invoicing.${keys[code]}`);
  const error = asApiError(raw);
  return error?.response?.data?.message || t("errors.generic");
}

function isRequired(field: ExpenseImportFieldKey) {
  return REQUIRED_EXPENSE_IMPORT_FIELDS.includes(field);
}

function formatSkippedError(err: ImportSkippedError) {
  let message = err.message;
  if (message.startsWith("duplicate_internal_number:")) {
    message = t("invoicing.expense_import_error_duplicate");
  } else if (message === "internal_note_too_long") {
    message = t("invoicing.expense_import_error_note");
  } else if (message === "insert_failed") {
    message = t("invoicing.expense_import_error_insert");
  } else if (message.startsWith("invalid_")) {
    const field = message.slice("invalid_".length);
    if (EXPENSE_IMPORT_FIELD_KEYS.includes(field as ExpenseImportFieldKey)) {
      message = t("invoicing.expense_import_error_field", {
        field: t(`invoicing.expense_import_field_${field}`),
      });
    }
  }
  if (err.internal_number) {
    return t("invoicing.expense_import_skipped_row_with_number", {
      number: err.internal_number,
      row: err.row,
      message,
    });
  }

  return t("invoicing.expense_import_skipped_row", {
    row: err.row,
    message,
  });
}

function onDragLeave() {
  dragOver.value = false;
}

function pickFile(file: File | undefined) {
  if (!file || importing.value || !props.open) return;
  previewVersion += 1;
  headers.value = [];
  rowCount.value = 0;
  for (const field of EXPENSE_IMPORT_FIELD_KEYS) mapping[field] = null;
  selectedFile.value = file;
  error.value = "";
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
  if (selectedFile.value) form.append("file", selectedFile.value);
  if (includeMapping) form.append("mapping", JSON.stringify(mapping));
  return form;
}

async function loadPreview() {
  if (!selectedFile.value) return;
  const file = selectedFile.value;
  const version = ++previewVersion;
  const targetCompany = props.companyId;
  const useLocal = props.localFirst;
  const current = () => !disposed && props.open && version === previewVersion
    && targetCompany === props.companyId && useLocal === props.localFirst;
  loadingPreview.value = true;
  error.value = "";
  try {
    if (useLocal) {
      const preview = await previewLocalExpenseImport(file);
      if (!current()) return;
      headers.value = preview.headers;
      rowCount.value = preview.row_count;
      for (const field of EXPENSE_IMPORT_FIELD_KEYS) {
        mapping[field] = preview.suggested_mapping[field] ?? null;
      }
      return;
    }
    const data = await invoicingApi.expenses.import.preview<{
      headers?: string[];
      row_count?: number;
      suggested_mapping?: Record<string, number | null>;
    }>(targetCompany, buildFormData(false));
    if (!current()) return;
    headers.value = data.headers ?? [];
    rowCount.value = data.row_count ?? 0;
    const suggested = data.suggested_mapping ?? {};
    for (const field of EXPENSE_IMPORT_FIELD_KEYS) {
      mapping[field] = suggested[field] ?? null;
    }
  } catch (rawError) {
    if (!current()) return;
    error.value = importError(rawError);
    headers.value = [];
    rowCount.value = 0;
  } finally {
    if (current()) loadingPreview.value = false;
  }
}

async function downloadExample() {
  try {
    if (props.localFirst) {
      downloadExpenseImportExampleCsv();
      return;
    }
    const data = await invoicingApi.expenses.import.example(props.companyId);
    const url = URL.createObjectURL(data);
    const a = document.createElement("a");
    a.href = url;
    a.download = "expense_import_example.xlsx";
    a.click();
    URL.revokeObjectURL(url);
  } catch {
    error.value = t("errors.generic");
  }
}

async function submitImport() {
  if (!props.open || !selectedFile.value || !mappingReady.value || importing.value || loadingPreview.value) return;
  const file = selectedFile.value;
  const targetCompany = props.companyId;
  const useLocal = props.localFirst;
  const selectedMapping = { ...mapping };
  importing.value = true;
  error.value = "";
  try {
    if (useLocal) {
      if (!evolu || !invoicingExpenses) throw new Error("local_context_unavailable");
      // Load current rows before writing, including when opened from company Import.
      await invoicingExpenses.refresh();
      if (disposed || targetCompany !== props.companyId || useLocal !== props.localFirst) return;
      const importResult = await importLocalExpensesFromFile(
        evolu,
        targetCompany as CompanyId,
        file,
        selectedMapping,
        invoicingExpenses.expenseRows.value,
      );
      if (disposed || targetCompany !== props.companyId || useLocal !== props.localFirst) return;
      result.value = importResult;
      if (importResult.imported > 0) {
        emit("imported");
      }
      return;
    }
    const data = await invoicingApi.expenses.import.run<NonNullable<typeof result.value>>(targetCompany, buildFormData(true));
    if (disposed || targetCompany !== props.companyId || useLocal !== props.localFirst) return;
    result.value = data;
    if ((data.imported ?? 0) > 0) {
      emit("imported");
    }
  } catch (rawError) {
    if (!disposed && targetCompany === props.companyId) error.value = importError(rawError);
  } finally {
    importing.value = false;
    if (!props.open || targetCompany !== props.companyId || useLocal !== props.localFirst) resetState();
  }
}
</script>
