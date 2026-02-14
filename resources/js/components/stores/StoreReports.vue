<template>
  <div class="space-y-6">
    <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700">
      <div class="px-6 py-5 border-b border-gray-700 bg-gray-800/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-white">{{ t("stores.reports") }}</h1>
          <p class="text-sm text-gray-400 mt-1">
            {{ t("stores.reports_subtitle_monthly") }}
          </p>
        </div>        
      </div>

      <div class="px-6 py-5">
        <div
          v-if="loading && exports.length === 0"
          class="text-center py-12"
        >
          <svg
            class="animate-spin mx-auto h-10 w-10 text-indigo-500"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <p class="text-gray-400 mt-4">{{ t("stores.loading_reports") }}</p>
        </div>

        <template v-else>
          <!-- Automatic report settings -->
          <div class="mb-6 rounded-xl border border-gray-700 bg-gray-800/50 p-5">
            <h2 class="text-lg font-semibold text-white mb-4">{{ t("stores.auto_report_settings") }}</h2>
            <div class="space-y-4 max-w-md">
              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  v-model="settingsForm.auto_report_enabled"
                  type="checkbox"
                  :disabled="!canUseReports"
                  class="rounded border-gray-600 bg-gray-700 text-indigo-500 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                />
                <span class="text-sm text-gray-300">{{ t("stores.auto_report_generate_1st") }}</span>
              </label>
              <div v-if="settingsForm.auto_report_enabled">
                <label class="block text-sm font-medium text-gray-400 mb-1">{{ t("stores.auto_report_email_label") }}</label>
                <input
                  v-model="settingsForm.auto_report_email"
                  type="email"
                  :placeholder="userEmail || t('stores.auto_report_email_placeholder')"
                  :disabled="!canUseReports"
                  class="w-full rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                />
              </div>
              <div v-if="settingsForm.auto_report_enabled">
                <label class="block text-sm font-medium text-gray-400 mb-1">{{ t("stores.auto_report_format_label") }}</label>
                <select
                  v-model="settingsForm.auto_report_format"
                  :disabled="!canUseReports"
                  class="w-full rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <option value="standard">{{ t("stores.auto_report_format_csv") }}</option>
                  <option value="xlsx">{{ t("stores.auto_report_format_xlsx") }}</option>
                </select>
              </div>
              <button
                v-if="canUseReports"
                @click="saveSettings"
                :disabled="savingSettings"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                {{ savingSettings ? "..." : t("stores.auto_report_save") }}
              </button>
              <button
                v-else
                @click="showUpgradeModal = true"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/30 hover:bg-amber-500/30 transition-colors"
              >
                {{ t("stores.available_in_pro") }}                
              </button>
            </div>
          </div>

          <!-- PDF Report -->
          <div class="mb-6 rounded-xl border border-gray-700 bg-gray-800/50 p-5">
            <h2 class="text-lg font-semibold text-white mb-4">{{ t("stores.pdf_report_section") }}</h2>
            <div class="space-y-4 max-w-md">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">{{ t("stores.pdf_report_date_from") }}</label>
                  <input
                    v-model="pdfDateFrom"
                    type="date"
                    :disabled="!canUseReports"
                    class="w-full rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">{{ t("stores.pdf_report_date_to") }}</label>
                  <input
                    v-model="pdfDateTo"
                    type="date"
                    :disabled="!canUseReports"
                    class="w-full rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                  />
                </div>
              </div>
              <div v-if="canUseReports">
                <button
                  @click="generatePdfReport"
                  :disabled="generatingPdf"
                  class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  <svg v-if="generatingPdf" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  {{ generatingPdf ? t("stores.pdf_report_generating") : t("stores.pdf_report_generate") }}
                </button>
              </div>
              <button
                v-else
                @click="showUpgradeModal = true"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/30 hover:bg-amber-500/30 transition-colors"
              >
                {{ t("stores.available_in_pro") }}                
              </button>
            </div>
          </div>

          <h3 class="text-base font-semibold text-white mb-3">{{ t("stores.report_history") }}</h3>

          <div
            v-if="exports.length === 0 && !loading"
            class="text-center py-16 bg-gray-700/30 rounded-xl border border-dashed border-gray-600"
          >
            <svg
              class="mx-auto h-12 w-12 text-gray-500"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-white">
              {{ t("stores.no_reports_yet") }}
            </h3>
            <p class="mt-1 text-sm text-gray-400">
              {{ t("stores.reports_monthly_will_appear_here") }}
            </p>
          </div>

          <div v-else class="overflow-x-auto rounded-xl border border-gray-700">
            <table class="min-w-full divide-y divide-gray-700">
              <thead class="bg-gray-700/50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                    {{ t("stores.report_id") }}
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                    {{ t("common.created") }}
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                    {{ t("stores.status") }}
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                    {{ t("stores.format") }}
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                    {{ t("common.actions") }}
                  </th>
                  <th v-if="canUseReports" class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider w-20">
                  </th>
                </tr>
              </thead>
              <tbody class="bg-gray-800 divide-y divide-gray-700">
                <tr
                  v-for="exportItem in exports"
                  :key="exportItem.id"
                  class="hover:bg-gray-700/50 transition-colors"
                >
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white font-mono">
                    {{ exportItem.id }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                    {{ exportItem.created_at ? formatDate(exportItem.created_at) : "-" }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <span
                      class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full border"
                      :class="getStatusClass(exportItem.status)"
                    >
                      {{ exportItem.status }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                    {{ formatLabel(exportItem.format) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex items-center gap-2">
                      <button
                        v-if="exportItem.status === 'finished' && canUseReports"
                        @click="handleDownloadExport(exportItem)"
                        :disabled="downloadingExportId === exportItem.id"
                        class="text-indigo-400 hover:text-indigo-300 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                      >
                        {{ downloadingExportId === exportItem.id ? t("stores.downloading") : t("stores.download") }}
                      </button>
                      <button
                        v-else-if="exportItem.status === 'finished' && !canUseReports"
                        @click="showUpgradeModal = true"
                        class="text-gray-500 hover:text-gray-400 transition-colors"
                      >
                        {{ t("stores.upgrade_to_download") }}
                      </button>
                      <button
                        v-else-if="(exportItem.status === 'failed' || exportItem.status === 'pending' || exportItem.status === 'running') && canUseReports"
                        @click="handleRetryExport(exportItem)"
                        :disabled="retryingExportId === exportItem.id"
                        class="text-orange-400 hover:text-orange-300 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                      >
                        {{ retryingExportId === exportItem.id ? t("stores.retrying") : t("common.retry") }}
                      </button>
                      <button
                        v-else-if="(exportItem.status === 'failed' || exportItem.status === 'pending' || exportItem.status === 'running') && !canUseReports"
                        @click="showUpgradeModal = true"
                        class="text-gray-500 hover:text-gray-400 transition-colors"
                      >
                        {{ t("stores.upgrade_to_retry") }}
                      </button>
                    </div>
                  </td>
                  <td v-if="canUseReports" class="px-6 py-4 whitespace-nowrap text-right text-sm">
                    <button
                      @click="handleDeleteExport(exportItem)"
                      :disabled="deletingExportId === exportItem.id"
                      class="text-red-400 hover:text-red-300 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                      :title="t('common.delete')"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>
    </div>
  </div>

  <UpgradeModal
    :show="showUpgradeModal"
    :message="t('stores.reports_upgrade_message')"
    :limits="[]"
    recommended-plan="pro"
    :upgrade-button-text="t('stores.upgrade_to_pro')"
    @close="showUpgradeModal = false"
  />
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useAuthStore } from "../../store/auth";
import { useFlashStore } from "../../store/flash";
import api from "../../services/api";
import UpgradeModal from "./UpgradeModal.vue";

const { t } = useI18n();
const authStore = useAuthStore();
const flashStore = useFlashStore();

const planCode = computed(() => (authStore.user?.plan?.code ?? "free") as string);
const userRole = computed(() => authStore.user?.role ?? "");
const canUseReports = computed(
  () =>
    planCode.value === "pro" ||
    planCode.value === "enterprise" ||
    userRole.value === "admin" ||
    userRole.value === "support"
);

const props = defineProps({
  store: {
    type: Object,
    required: true,
  },
});

const loading = ref(false);
const exports = ref<any[]>([]);
const downloadingExportId = ref<number | null>(null);
const retryingExportId = ref<number | null>(null);
const deletingExportId = ref<number | null>(null);
const showUpgradeModal = ref(false);
const savingSettings = ref(false);
const userEmail = ref("");
const settingsForm = ref({
  auto_report_enabled: false,
  auto_report_email: "",
  auto_report_format: "standard",
});
const generatingPdf = ref(false);
const pdfDateFrom = ref("");
const pdfDateTo = ref("");
let exportsRefreshInterval: number | null = null;

function setDefaultPdfDates() {
  const to = new Date();
  const from = new Date();
  from.setDate(from.getDate() - 30);
  pdfDateFrom.value = from.toISOString().slice(0, 10);
  pdfDateTo.value = to.toISOString().slice(0, 10);
}

onMounted(() => {
  setDefaultPdfDates();
  fetchExports();
  fetchReportSettings();
  startRefreshInterval();
});

onUnmounted(() => {
  stopRefreshInterval();
});

watch(
  () => props.store.id,
  () => {
    fetchExports();
    fetchReportSettings();
  }
);

async function fetchReportSettings() {
  if (!props.store) return;
  try {
    const response = await api.get(`/stores/${props.store.id}/report-settings`);
    const data = response.data.data || {};
    userEmail.value = response.data.user_email || "";
    settingsForm.value = {
      auto_report_enabled: data.auto_report_enabled ?? false,
      auto_report_email: data.auto_report_email || "",
      auto_report_format: data.auto_report_format || "standard",
    };
  } catch {
    // Ignore - user may not have access
  }
}

async function saveSettings() {
  if (!props.store || !canUseReports.value) return;
  savingSettings.value = true;
  flashStore.clear();
  try {
    await api.put(`/stores/${props.store.id}/report-settings`, {
      auto_report_enabled: settingsForm.value.auto_report_enabled,
      auto_report_email: settingsForm.value.auto_report_email || null,
      auto_report_format: settingsForm.value.auto_report_format,
    });
    flashStore.success(t("stores.auto_report_saved"));
  } catch (err: any) {
    flashStore.error(err.response?.data?.message || t("stores.auto_report_save_failed"));
  } finally {
    savingSettings.value = false;
  }
}

function startRefreshInterval() {
  stopRefreshInterval();
  exportsRefreshInterval = window.setInterval(() => {
    const hasPendingOrRunning = exports.value.some(
      (e: any) => e.status === "pending" || e.status === "running"
    );
    if (hasPendingOrRunning) {
      fetchExports(true);
    }
  }, 5000);
}

function stopRefreshInterval() {
  if (exportsRefreshInterval) {
    clearInterval(exportsRefreshInterval);
    exportsRefreshInterval = null;
  }
}

async function fetchExports(silent = false) {
  if (!props.store) return;

  if (!silent) loading.value = true;
  if (!silent) flashStore.clear();

  try {
    const response = await api.get(`/stores/${props.store.id}/exports`);
    exports.value = response.data.data || [];
  } catch (err: any) {
    if (!silent) {
      flashStore.error(err.response?.data?.message || "Failed to load reports.");
      exports.value = [];
    }
  } finally {
    if (!silent) loading.value = false;
  }
}

async function handleDownloadExport(exportItem: any) {
  downloadingExportId.value = exportItem.id;
  try {
    const response = await api.get(`/exports/${exportItem.id}/download`);
    if (response.data.data?.download_url) {
      window.open(response.data.data.download_url, "_blank");
    } else {
      flashStore.error("Download URL not available.");
    }
  } catch (err: any) {
    if (err.response?.status === 202) {
      flashStore.error("Export is not ready yet.");
    } else {
      flashStore.error(err.response?.data?.message || "Failed to download export.");
    }
  } finally {
    downloadingExportId.value = null;
  }
}

async function handleRetryExport(exportItem: any) {
  retryingExportId.value = exportItem.id;
  try {
    await api.post(`/exports/${exportItem.id}/retry`);
    await fetchExports();
  } catch (err: any) {
    flashStore.error(err.response?.data?.message || "Failed to retry export.");
  } finally {
    retryingExportId.value = null;
  }
}

async function generatePdfReport() {
  if (!props.store?.id || !canUseReports.value) return;
  if (!pdfDateFrom.value || !pdfDateTo.value) {
    flashStore.error(t("stores.pdf_report_select_dates"));
    return;
  }
  generatingPdf.value = true;
  flashStore.clear();
  try {
    const response = await api.get(`/stores/${props.store.id}/report/pdf`, {
      params: { date_from: pdfDateFrom.value, date_to: pdfDateTo.value },
      responseType: "blob",
    });
    const blob = new Blob([response.data], { type: "application/pdf" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `report-${pdfDateFrom.value}-${pdfDateTo.value}.pdf`;
    a.click();
    window.URL.revokeObjectURL(url);
  } catch (err: any) {
    if (err.response?.status === 401) {
      flashStore.error(t("stores.pdf_report_unauth"));
    } else if (err.response?.status === 422) {
      flashStore.error(err.response?.data?.message || t("stores.pdf_report_select_dates"));
    } else {
      flashStore.error(err.response?.data?.message || t("stores.pdf_report_failed"));
    }
  } finally {
    generatingPdf.value = false;
  }
}

async function handleDeleteExport(exportItem: any) {
  if (!confirm(t("stores.confirm_delete_report"))) return;
  deletingExportId.value = exportItem.id;
  try {
    await api.delete(`/exports/${exportItem.id}`);
    await fetchExports();
  } catch (err: any) {
    flashStore.error(err.response?.data?.message || "Failed to delete export.");
  } finally {
    deletingExportId.value = null;
  }
}

function formatDate(dateString: string): string {
  if (!dateString) return "-";
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return "-";
  const day = String(date.getDate()).padStart(2, "0");
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const year = date.getFullYear();
  return `${day}.${month}.${year}`;
}

function formatLabel(format: string): string {
  if (!format) return "CSV";
  const f = (format || "").toLowerCase();
  if (f === "xlsx" || f === "excel") return "XLSX";
  if (f === "standard" || f === "csv") return "CSV";
  return format;
}

function getStatusClass(status: string): string {
  const statusLower = status?.toLowerCase() || "";
  if (statusLower === "finished" || statusLower === "completed") {
    return "bg-green-500/10 text-green-400 border-green-500/20";
  }
  if (statusLower === "pending" || statusLower === "running" || statusLower === "processing") {
    return "bg-blue-500/10 text-blue-400 border-blue-500/20";
  }
  if (statusLower === "failed" || statusLower === "error") {
    return "bg-red-500/10 text-red-400 border-red-500/20";
  }
  return "bg-gray-700 text-gray-300 border-gray-600";
}
</script>
