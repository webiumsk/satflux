<template>
  <div v-if="loading && exports.length === 0" class="text-center py-12">
    <svg
      class="animate-spin mx-auto h-10 w-10 text-indigo-500"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle
        class="opacity-25"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        stroke-width="4"
      ></circle>
      <path
        class="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
      ></path>
    </svg>
    <p class="text-gray-400 mt-4">{{ t("stores.loading_exports") }}</p>
  </div>

  <div v-else class="space-y-6">
    <div
      class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700"
    >
      <div class="px-6 py-5 border-b border-gray-700 bg-gray-800/50">
        <h1 class="text-2xl font-bold text-white">{{ t("stores.exports") }}</h1>
        <p class="text-sm text-gray-400 mt-1">
          {{ t("stores.download_data_exports") }}
        </p>
      </div>

      <div class="px-6 py-5">
        <div
          v-if="error"
          class="rounded-xl bg-red-500/10 border border-red-500/20 p-4 mb-4"
        >
          <div class="flex">
            <svg
              class="h-5 w-5 text-red-400 mr-2"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <div class="text-sm text-red-400">{{ error }}</div>
          </div>
        </div>

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
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
            />
          </svg>
          <h3 class="mt-2 text-sm font-medium text-white">
            {{ t("stores.no_exports_yet") }}
          </h3>
          <p class="mt-1 text-sm text-gray-400">
            {{ t("stores.exports_will_appear_here") }}
          </p>
        </div>

        <div v-else class="overflow-x-auto rounded-xl border border-gray-700">
          <table class="min-w-full divide-y divide-gray-700">
            <thead class="bg-gray-700/50">
              <tr>
                <th
                  class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
                >
                  {{ t("stores.export_id") }}
                </th>
                <th
                  class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
                >
                  {{ t("common.created") }}
                </th>
                <th
                  class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
                >
                  {{ t("stores.status") }}
                </th>
                <th
                  class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
                >
                  {{ t("stores.format") }}
                </th>
                <th
                  class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
                >
                  {{ t("common.actions") }}
                </th>
              </tr>
            </thead>
            <tbody class="bg-gray-800 divide-y divide-gray-700">
              <tr
                v-for="exportItem in exports"
                :key="exportItem.id"
                class="hover:bg-gray-700/50 transition-colors"
              >
                <td
                  class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white font-mono"
                >
                  {{ exportItem.id }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                  {{
                    exportItem.created_at
                      ? formatDate(exportItem.created_at)
                      : "-"
                  }}
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
                  {{ exportItem.format || "CSV" }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <button
                    v-if="exportItem.status === 'finished'"
                    @click="handleDownloadExport(exportItem)"
                    :disabled="downloadingExportId === exportItem.id"
                    class="text-indigo-400 hover:text-indigo-300 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  >
                    {{
                      downloadingExportId === exportItem.id
                        ? t("stores.downloading")
                        : t("stores.download")
                    }}
                  </button>
                  <button
                    v-else-if="exportItem.status === 'failed'"
                    @click="handleRetryExport(exportItem)"
                    :disabled="retryingExportId === exportItem.id"
                    class="text-orange-400 hover:text-orange-300 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  >
                    {{
                      retryingExportId === exportItem.id
                        ? t("stores.retrying")
                        : t("common.retry")
                    }}
                  </button>
                  <span
                    v-else-if="
                      exportItem.status === 'pending' ||
                      exportItem.status === 'running'
                    "
                    class="text-gray-500 flex items-center"
                  >
                    <svg
                      class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-500"
                      xmlns="http://www.w3.org/2000/svg"
                      fill="none"
                      viewBox="0 0 24 24"
                    >
                      <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                      ></circle>
                      <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                      ></path>
                    </svg>
                    {{
                      exportItem.status === "running"
                        ? t("stores.processing")
                        : t("stores.waiting")
                    }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from "vue";
import { useI18n } from "vue-i18n";
import api from "../../services/api";

const { t } = useI18n();

const props = defineProps({
  store: {
    type: Object,
    required: true,
  },
});

const loading = ref(false);
const exports = ref<any[]>([]);
const error = ref("");
const downloadingExportId = ref<number | null>(null);
const retryingExportId = ref<number | null>(null);
let exportsRefreshInterval: number | null = null;

onMounted(() => {
  fetchExports();
  startRefreshInterval();
});

onUnmounted(() => {
  stopRefreshInterval();
});

watch(
  () => props.store.id,
  () => {
    fetchExports();
  },
);

function startRefreshInterval() {
  stopRefreshInterval(); // Ensure no duplicates
  exportsRefreshInterval = window.setInterval(() => {
    const hasPendingOrRunning = exports.value.some(
      (e: any) => e.status === "pending" || e.status === "running",
    );
    if (hasPendingOrRunning) {
      fetchExports(true); // silent update
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
  // Don't clear error on refresh to avoid flashing
  if (!silent) error.value = "";

  try {
    const response = await api.get(`/stores/${props.store.id}/exports`);
    exports.value = response.data.data || [];
  } catch (err: any) {
    if (!silent) {
      error.value = err.response?.data?.message || "Failed to load exports.";
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
      error.value = "Download URL not available.";
    }
  } catch (err: any) {
    if (err.response?.status === 202) {
      error.value = "Export is not ready yet.";
    } else {
      error.value = err.response?.data?.message || "Failed to download export.";
    }
    setTimeout(() => {
      error.value = "";
    }, 5000);
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
    error.value = err.response?.data?.message || "Failed to retry export.";
    setTimeout(() => {
      error.value = "";
    }, 5000);
  } finally {
    retryingExportId.value = null;
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

function getStatusClass(status: string): string {
  const statusLower = status?.toLowerCase() || "";
  if (statusLower === "finished" || statusLower === "completed") {
    return "bg-green-500/10 text-green-400 border-green-500/20";
  } else if (
    statusLower === "pending" ||
    statusLower === "running" ||
    statusLower === "processing"
  ) {
    return "bg-blue-500/10 text-blue-400 border-blue-500/20";
  } else if (statusLower === "failed" || statusLower === "error") {
    return "bg-red-500/10 text-red-400 border-red-500/20";
  }
  return "bg-gray-700 text-gray-300 border-gray-600";
}
</script>
