<template>
  <div class="mt-4 border-t border-gray-600 pt-4 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h5 class="text-sm font-semibold text-white">
        {{ t("account.backup_title") }}
      </h5>
      <button
        type="button"
        class="text-xs font-medium rounded-md px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white disabled:opacity-50"
        :disabled="exporting"
        @click="runExport"
      >
        {{ exporting ? t("common.loading") : t("account.backup_export_button") }}
      </button>
    </div>
    <p class="text-xs text-gray-400">
      {{ t("account.backup_desc") }}
    </p>
    <p class="text-xs text-amber-300">
      {{ t("account.backup_plaintext_warning") }}
    </p>
    <p class="text-xs" :class="lastExport ? 'text-gray-300' : 'text-gray-500'">
      {{
        lastExport
          ? t("account.backup_last_export_at", { date: formatDate(lastExport.exportedAt) })
          : t("account.backup_never_exported")
      }}
    </p>
    <p v-if="message" class="text-xs" :class="messageIsError ? 'text-red-400' : 'text-emerald-300'">
      {{ message }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import {
  getLastExportMeta,
  type LastExportMeta,
} from "../../services/backupState";
import { sha256Hex } from "../../utils/sha256";

/**
 * Full-dataset backup export (P1 phase 2). Lives in the Profile next to the
 * local Evolu stats; the Evolu client is imported lazily on click so the
 * Profile chunk stays WASM-free until the user actually exports.
 */
const props = defineProps<{ ownerId: string }>();

const { t, locale } = useI18n();

const exporting = ref(false);
const message = ref("");
const messageIsError = ref(false);
const lastExport = ref<LastExportMeta | null>(null);

async function refreshLastExport() {
  const hash = await sha256Hex(props.ownerId);
  lastExport.value = hash ? getLastExportMeta(hash) : null;
}

onMounted(refreshLastExport);
watch(() => props.ownerId, refreshLastExport);

async function runExport() {
  exporting.value = true;
  message.value = "";
  messageIsError.value = false;
  try {
    const { evolu } = await import("../../evolu/client");
    const { exportInvoicingBackup } = await import("../../evolu/invoicingBackup");
    const meta = await exportInvoicingBackup(evolu, props.ownerId);
    lastExport.value = meta;
    message.value = t("account.backup_export_success");
  } catch {
    message.value = t("account.backup_export_failed");
    messageIsError.value = true;
  } finally {
    exporting.value = false;
  }
}

function formatDate(iso: string): string {
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? iso : date.toLocaleString(locale.value);
}
</script>
