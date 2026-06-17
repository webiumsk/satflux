<template>
  <InvoicingPageShell content-class="pb-10">
    <template #header>
      <InvoicingAppHeader :show-filter-bar="false" />
    </template>

    <h1 class="invoicing-title mb-6">{{ t("invoicing.import_title") }}</h1>

    <nav class="flex flex-wrap gap-2 mb-8 text-sm">
      <span
        v-for="(label, idx) in stepLabels"
        :key="idx"
        class="px-3 py-1.5 rounded-full border"
        :class="
          step === idx + 1
            ? 'bg-indigo-600 text-white border-indigo-600'
            : 'bg-gray-50 text-gray-600 border-gray-200'
        "
      >
        {{ label }}
      </span>
    </nav>

    <!-- Step 1 -->
    <section v-if="step === 1" class="invoicing-card-pad space-y-6 max-w-4xl">
      <h2 class="text-lg font-semibold text-gray-800">
        {{ t("invoicing.import_step1_heading") }}
      </h2>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <button
          v-for="src in DOCUMENT_IMPORT_SOURCES"
          :key="src.id"
          type="button"
          class="rounded-lg border p-4 text-center transition-colors min-h-[120px] flex flex-col items-center justify-center gap-2"
          :class="
            src.enabled
              ? selectedSource === src.id
                ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-200'
                : 'border-gray-200 hover:border-indigo-300 hover:bg-gray-50'
              : 'border-gray-100 bg-gray-50 opacity-60 cursor-not-allowed'
          "
          :disabled="!src.enabled"
          @click="selectSource(src)"
        >
          <span class="text-2xl font-bold text-gray-400">{{
            sourceIcon(src.id)
          }}</span>
          <span class="text-sm font-medium text-gray-800">{{
            t(src.labelKey)
          }}</span>
          <span v-if="!src.enabled" class="text-xs text-gray-500">{{
            t("invoicing.import_coming_soon")
          }}</span>
        </button>
      </div>
      <div class="flex justify-end">
        <button
          type="button"
          class="invoicing-btn-primary"
          :disabled="!selectedSource"
          @click="step = 2"
        >
          {{ t("common.continue") }}
        </button>
      </div>
    </section>

    <!-- Step 2 -->
    <section v-else-if="step === 2" class="space-y-6 max-w-3xl">
      <div class="invoicing-card-pad space-y-4">
        <p class="text-sm text-gray-600">
          {{ t("invoicing.import_files_hint") }}
          <button
            type="button"
            class="text-indigo-600 hover:underline"
            @click="downloadExample"
          >
            {{ t("invoicing.import_example_link") }}
          </button>
        </p>

        <div>
          <label class="invoicing-sf-label">{{
            t("invoicing.import_date_format")
          }}</label>
          <select v-model="dateFormat" class="invoicing-sf-input max-w-md">
            <option value="dmy_dot">
              {{ t("invoicing.import_date_format_dmy_dot") }}
            </option>
            <option value="ymd_dash">
              {{ t("invoicing.import_date_format_ymd_dash") }}
            </option>
            <option value="mdy_slash">
              {{ t("invoicing.import_date_format_mdy_slash") }}
            </option>
            <option value="auto">
              {{ t("invoicing.import_date_format_auto") }}
            </option>
          </select>
          <p class="text-xs text-gray-500 mt-1">
            {{ t("invoicing.import_date_format_hint") }}
          </p>
        </div>

        <div
          class="rounded-lg border-2 border-dashed border-indigo-300 bg-indigo-50/30 p-8 text-center"
          @dragover.prevent
          @drop.prevent="onDrop"
        >
          <label class="invoicing-btn-primary cursor-pointer">
            {{ t("invoicing.contact_import_drop") }}
            <input
              ref="fileInput"
              type="file"
              class="hidden"
              accept=".xlsx,.xls,.csv"
              @change="onFileChange"
            />
          </label>
          <p v-if="selectedFile" class="mt-3 text-sm text-gray-600">
            {{ selectedFile.name }}
          </p>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input
            v-model="oneRowPerInvoice"
            type="checkbox"
            class="rounded border-gray-300"
          />
          {{ t("invoicing.import_one_row_per_invoice") }}
        </label>

        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input
            v-model="createContacts"
            type="checkbox"
            class="rounded border-gray-300"
          />
          {{ t("invoicing.import_create_contacts") }}
        </label>
        <p class="text-xs text-gray-500 -mt-2">
          {{ t("invoicing.import_create_contacts_hint") }}
        </p>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="invoicing-sf-label">{{
              t("invoicing.import_line_name")
            }}</label>
            <input v-model="lineName" type="text" class="invoicing-sf-input" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{
              t("invoicing.import_line_description")
            }}</label>
            <input
              v-model="lineDescription"
              type="text"
              class="invoicing-sf-input"
            />
          </div>
        </div>
      </div>

      <div v-if="headers.length" class="invoicing-card-pad space-y-4">
        <h3 class="font-semibold text-gray-800">
          {{ t("invoicing.import_mapping_title") }}
        </h3>
        <p class="text-sm text-gray-600">
          {{ t("invoicing.import_mapping_hint") }}
        </p>

        <div class="space-y-2 max-h-[420px] overflow-auto pr-2">
          <div
            v-for="field in DOCUMENT_IMPORT_FIELD_KEYS"
            :key="field"
            class="grid sm:grid-cols-2 gap-2 items-center text-sm"
          >
            <span class="text-gray-700">
              {{ t(`invoicing.import_field_${field}`) }}
              <span v-if="isRequired(field)" class="text-red-500">*</span>
            </span>
            <select v-model="mapping[field]" class="invoicing-sf-input">
              <option :value="null">-</option>
              <option v-for="(header, idx) in headers" :key="idx" :value="idx">
                {{ header || t("invoicing.import_column", { n: idx + 1 }) }}
              </option>
            </select>
          </div>
        </div>

        <p class="text-xs text-gray-500">
          {{ t("invoicing.import_paid_hint") }}
        </p>
      </div>

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

      <div class="flex flex-wrap gap-3">
        <button type="button" class="invoicing-btn-secondary" @click="step = 1">
          {{ t("common.back") }}
        </button>
        <button
          type="button"
          class="invoicing-btn-primary"
          :disabled="!selectedFile || loading || !mappingReady"
          @click="goToConfirm"
        >
          {{ loading ? t("common.loading") : t("common.continue") }}
        </button>
      </div>
    </section>

    <!-- Step 3 -->
    <section v-else class="space-y-6 max-w-4xl">
      <div class="invoicing-card-pad space-y-4">
        <h2 class="text-lg font-semibold text-gray-800">
          {{ t("invoicing.import_step3_heading") }}
        </h2>
        <p class="text-sm text-gray-600">
          {{ t("invoicing.import_confirm_summary", { count: rowCount }) }}
        </p>

        <div
          v-if="previewRows.length"
          class="overflow-x-auto border border-gray-200 rounded-lg"
        >
          <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 text-xs uppercase text-gray-600">
              <tr>
                <th class="px-3 py-2">{{ t("invoicing.col_number") }}</th>
                <th class="px-3 py-2">{{ t("invoicing.col_client") }}</th>
                <th class="px-3 py-2">{{ t("invoicing.col_dates") }}</th>
                <th class="px-3 py-2 text-right">
                  {{ t("invoicing.col_total") }}
                </th>
                <th class="px-3 py-2">
                  {{ t("invoicing.import_preview_status") }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in previewRows"
                :key="row.row"
                class="border-t border-gray-100"
              >
                <td class="px-3 py-2">{{ row.invoice_number || "-" }}</td>
                <td class="px-3 py-2">{{ row.client_name || "-" }}</td>
                <td class="px-3 py-2 whitespace-nowrap">
                  <template v-if="row.issue_date"
                    >{{ row.issue_date }} → {{ row.due_date }}</template
                  >
                  <span v-else class="text-red-600">{{ row.error }}</span>
                </td>
                <td class="px-3 py-2 text-right">
                  <template v-if="row.amount != null"
                    >{{ row.amount }} {{ row.currency }}</template
                  >
                </td>
                <td class="px-3 py-2 text-xs">
                  <span v-if="row.paid" class="text-green-700">{{
                    t("invoicing.filter_paid")
                  }}</span>
                  <span v-else-if="!row.error" class="text-gray-600">{{
                    t("invoicing.filter_unpaid")
                  }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

      <div
        v-if="importResult"
        class="text-sm space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4"
      >
        <p class="text-green-700 font-medium">
          {{
            t("invoicing.import_result", {
              imported: importResult.imported,
              skipped: importResult.skipped,
            })
          }}
        </p>
        <p
          v-if="importResult.contacts_created || importResult.contacts_linked"
          class="text-gray-700"
        >
          {{
            t("invoicing.import_contacts_result", {
              created: importResult.contacts_created ?? 0,
              linked: importResult.contacts_linked ?? 0,
            })
          }}
        </p>
        <div v-if="importResult.errors.length">
          <p class="font-medium text-amber-900 mb-2">
            {{
              t("invoicing.import_skipped_heading", {
                count: importResult.errors.length,
              })
            }}
          </p>
          <ul class="text-amber-800 space-y-1 max-h-48 overflow-auto">
            <li v-for="(err, idx) in importResult.errors" :key="idx">
              {{ formatSkippedError(err) }}
            </li>
          </ul>
        </div>
      </div>

      <div class="flex flex-wrap gap-3">
        <button
          type="button"
          class="invoicing-btn-secondary"
          :disabled="importing"
          @click="step = 2"
        >
          {{ t("common.back") }}
        </button>
        <button
          type="button"
          class="invoicing-btn-primary px-8"
          :disabled="importing || !selectedFile"
          @click="runImport"
        >
          {{ importing ? t("common.loading") : t("invoicing.import_submit") }}
        </button>
        <RouterLink
          v-if="importDone"
          :to="{ name: 'invoicing-invoices', params: { companyId } }"
          class="invoicing-btn-secondary"
        >
          {{ t("invoicing.import_go_invoices") }}
        </RouterLink>
      </div>
    </section>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { useI18n } from "vue-i18n";
import InvoicingAppHeader from "../../components/invoicing/InvoicingAppHeader.vue";
import InvoicingPageShell from "../../components/invoicing/InvoicingPageShell.vue";
import {
  DOCUMENT_IMPORT_FIELD_KEYS,
  DOCUMENT_IMPORT_SOURCES,
  REQUIRED_DOCUMENT_IMPORT_FIELDS,
  type DocumentImportFieldKey,
  type DocumentImportMapping,
  type DocumentImportSource,
} from "../../composables/useDocumentImportFields";
import {
  allCompaniesDetailQuery,
  allContactsQuery,
  allDocumentsQuery,
  useInvoicingEvolu,
} from "../../evolu/client";
import {
  evoluCompanyToApi,
  type EvoluCompanyRow,
} from "../../evolu/companyMap";
import {
  buildDocumentImportExampleCsvBlob,
  downloadCsvBlob,
  importDocumentImportCsv,
  isDocumentImportSpreadsheetFile,
  parseDocumentImportFile,
  previewDocumentImport,
  type DocumentImportPreviewRow,
  type ImportDateFormat,
} from "../../evolu/documentImportLocal";
import { isInvoicingLocalFirst } from "../../evolu/flags";
import type { CompanyId } from "../../evolu/schema";
import { useInvoicingLayout } from "../../composables/useInvoicingLayout";
import { useStoresStore } from "../../store/stores";
import api from "../../services/api";

const { t } = useI18n();
const localFirst = isInvoicingLocalFirst();
const evolu = localFirst ? useInvoicingEvolu() : null;
const storesStore = useStoresStore();
const { companyId, rememberCompany } = useInvoicingLayout();

const step = ref(1);
const selectedSource = ref<DocumentImportSource | null>("excel");
const selectedFile = ref<File | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const csvRows = ref<string[][]>([]);
const headers = ref<string[]>([]);
const mapping = reactive<DocumentImportMapping>({});
const previewRows = ref<DocumentImportPreviewRow[]>([]);
const rowCount = ref(0);
const oneRowPerInvoice = ref(true);
const createContacts = ref(true);
const lineName = ref("Imported item");
const lineDescription = ref("");
const dateFormat = ref<ImportDateFormat>("auto");
const loading = ref(false);
const importing = ref(false);
const error = ref("");
const importDone = ref(false);

type ImportSkippedError = {
  row: number;
  invoice_number?: string | null;
  message: string;
};

type ImportResult = {
  imported: number;
  skipped: number;
  contacts_created?: number;
  contacts_linked?: number;
  errors: ImportSkippedError[];
};

const importResult = ref<ImportResult | null>(null);

const stepLabels = computed(() => [
  t("invoicing.import_step1"),
  t("invoicing.import_step2"),
  t("invoicing.import_step3"),
]);

const mappingReady = computed(() =>
  REQUIRED_DOCUMENT_IMPORT_FIELDS.every(
    (field) => mapping[field] !== null && mapping[field] !== undefined,
  ),
);

function isRequired(field: DocumentImportFieldKey) {
  return REQUIRED_DOCUMENT_IMPORT_FIELDS.includes(field);
}

function formatSkippedError(err: ImportSkippedError) {
  if (err.invoice_number) {
    return t("invoicing.import_skipped_row_with_invoice", {
      invoice: err.invoice_number,
      row: err.row,
      message: err.message,
    });
  }

  return t("invoicing.import_skipped_row", {
    row: err.row,
    message: err.message,
  });
}

function sourceIcon(id: DocumentImportSource) {
  const icons: Record<DocumentImportSource, string> = {
    excel: "XLS",
    money: "M",
    pohoda: "P",
    isdoc: "ID",
    idoklad: "iD",
  };
  return icons[id];
}

function selectSource(src: (typeof DOCUMENT_IMPORT_SOURCES)[number]) {
  if (!src.enabled) return;
  selectedSource.value = src.id;
}

function localCompanyApi() {
  if (!evolu) return null;
  const row = evolu
    .getQueryRows(allCompaniesDetailQuery)
    .find((c) => c.id === companyId.value);
  if (!row) return null;
  return evoluCompanyToApi(row as EvoluCompanyRow, (storeId) => {
    const store = storesStore.stores.find((s) => s.id === storeId);
    return store
      ? {
          id: store.id,
          name: store.name,
          default_currency: store.default_currency,
        }
      : undefined;
  });
}

async function ensureLocalQueriesLoaded() {
  if (!evolu) return;
  await Promise.all([
    evolu.loadQuery(allCompaniesDetailQuery),
    evolu.loadQuery(allDocumentsQuery),
    evolu.loadQuery(allContactsQuery),
  ]);
}

function pickFile(file: File | undefined) {
  if (!file) return;
  selectedFile.value = file;
  csvRows.value = [];
  headers.value = [];
  previewRows.value = [];
  rowCount.value = 0;
  importResult.value = null;
  importDone.value = false;
  for (const field of DOCUMENT_IMPORT_FIELD_KEYS) {
    mapping[field] = null;
  }
  error.value = "";
  void loadPreview();
}

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement;
  pickFile(input.files?.[0]);
}

function onDrop(e: DragEvent) {
  pickFile(e.dataTransfer?.files?.[0]);
}

function buildFormData(includeMapping: boolean) {
  const form = new FormData();
  if (selectedFile.value) form.append("file", selectedFile.value);
  if (includeMapping) form.append("mapping", JSON.stringify(mapping));
  form.append("line_name", lineName.value);
  if (lineDescription.value)
    form.append("line_description", lineDescription.value);
  form.append("create_contacts", createContacts.value ? "1" : "0");
  form.append("date_format", dateFormat.value);
  return form;
}

async function loadSpreadsheetFromFile(file: File) {
  if (localFirst) {
    const parsed = await parseDocumentImportFile(file);
    csvRows.value = parsed.rows;
    headers.value = parsed.headers;
    return parsed;
  }
  return null;
}

async function loadPreview() {
  if (!selectedFile.value) return;
  loading.value = true;
  error.value = "";
  try {
    if (localFirst) {
      if (!isDocumentImportSpreadsheetFile(selectedFile.value)) {
        error.value = t("invoicing.import_file_invalid");
        headers.value = [];
        rowCount.value = 0;
        csvRows.value = [];
        return;
      }
      const parsed = await loadSpreadsheetFromFile(selectedFile.value);
      if (!parsed) return;
      const company = localCompanyApi();
      const preview = previewDocumentImport(
        parsed.headers,
        parsed.rows,
        undefined,
        {
          defaultCurrency: company?.default_currency ?? "EUR",
          dateFormat: dateFormat.value,
        },
      );
      rowCount.value = preview.row_count;
      for (const field of DOCUMENT_IMPORT_FIELD_KEYS) {
        mapping[field] = preview.suggested_mapping[field] ?? null;
      }
      return;
    }

    const { data } = await api.post(
      `/invoicing/companies/${companyId.value}/documents/import/preview`,
      buildFormData(false),
    );
    headers.value = data.data.headers ?? [];
    rowCount.value = data.data.row_count ?? 0;
    const suggested = data.data.suggested_mapping ?? {};
    for (const field of DOCUMENT_IMPORT_FIELD_KEYS) {
      mapping[field] = suggested[field] ?? null;
    }
  } catch (e: any) {
    error.value = e?.response?.data?.message || t("errors.generic");
    headers.value = [];
  } finally {
    loading.value = false;
  }
}

async function goToConfirm() {
  if (!selectedFile.value || !mappingReady.value) return;
  loading.value = true;
  error.value = "";
  try {
    if (localFirst) {
      if (!isDocumentImportSpreadsheetFile(selectedFile.value)) {
        error.value = t("invoicing.import_file_invalid");
        return;
      }
      if (!csvRows.value.length) {
        await loadSpreadsheetFromFile(selectedFile.value);
      }
      const company = localCompanyApi();
      const preview = previewDocumentImport(
        headers.value,
        csvRows.value,
        mapping,
        {
          defaultCurrency: company?.default_currency ?? "EUR",
          includePreview: true,
          dateFormat: dateFormat.value,
        },
      );
      previewRows.value = preview.preview ?? [];
      rowCount.value = preview.row_count;
      step.value = 3;
      return;
    }

    const { data } = await api.post(
      `/invoicing/companies/${companyId.value}/documents/import/preview`,
      buildFormData(true),
    );
    previewRows.value = data.data.preview ?? [];
    rowCount.value = data.data.row_count ?? 0;
    step.value = 3;
  } catch (e: any) {
    error.value = e?.response?.data?.message || t("errors.generic");
  } finally {
    loading.value = false;
  }
}

async function runImport() {
  if (!selectedFile.value) return;
  importing.value = true;
  error.value = "";
  importResult.value = null;
  try {
    if (localFirst && evolu) {
      if (!isDocumentImportSpreadsheetFile(selectedFile.value)) {
        error.value = t("invoicing.import_file_invalid");
        return;
      }
      if (!csvRows.value.length) {
        await loadSpreadsheetFromFile(selectedFile.value);
      }
      await ensureLocalQueriesLoaded();
      const company = localCompanyApi();
      const result = importDocumentImportCsv(
        evolu,
        companyId.value as CompanyId,
        csvRows.value,
        mapping,
        {
          lineName: lineName.value,
          lineDescription: lineDescription.value,
          createContacts: createContacts.value,
          defaultCurrency: company?.default_currency ?? "EUR",
          noteFooter: company?.legal_footer_note ?? null,
          company,
          dateFormat: dateFormat.value,
        },
      );
      importResult.value = {
        imported: result.imported,
        skipped: result.skipped,
        contacts_created: result.contacts_created,
        contacts_linked: result.contacts_linked,
        errors: result.errors,
      };
      importDone.value = true;
      return;
    }

    const { data } = await api.post(
      `/invoicing/companies/${companyId.value}/documents/import`,
      buildFormData(true),
    );
    const result = data.data;
    importResult.value = {
      imported: result.imported ?? 0,
      skipped: result.skipped ?? 0,
      contacts_created: result.contacts_created ?? 0,
      contacts_linked: result.contacts_linked ?? 0,
      errors: result.errors ?? [],
    };
    importDone.value = true;
  } catch (e: any) {
    error.value = e?.response?.data?.message || t("errors.generic");
  } finally {
    importing.value = false;
  }
}

async function downloadExample() {
  if (localFirst) {
    downloadCsvBlob(
      buildDocumentImportExampleCsvBlob(),
      "invoice_import_example.csv",
    );
    return;
  }

  try {
    const { data } = await api.get(
      `/invoicing/companies/${companyId.value}/documents/import/example`,
      {
        responseType: "blob",
      },
    );
    const url = URL.createObjectURL(data);
    const a = document.createElement("a");
    a.href = url;
    a.download = "invoice_import_example.xlsx";
    a.click();
    URL.revokeObjectURL(url);
  } catch {
    error.value = t("errors.generic");
  }
}

onMounted(() => {
  rememberCompany(companyId.value);
  if (localFirst && evolu) {
    void ensureLocalQueriesLoaded();
  }
});
</script>
