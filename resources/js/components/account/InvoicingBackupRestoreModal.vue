<template>
  <div
    v-if="open"
    class="fixed z-50 inset-0 overflow-y-auto"
    @click.self="close"
  >
    <div class="fixed inset-0 bg-gray-900/90 backdrop-blur-sm" @click.self="close" />
    <div class="flex min-h-full items-center justify-center p-4">
      <div
        class="relative w-full max-w-lg rounded-2xl border border-gray-700 bg-gray-800 shadow-xl"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
      >
        <div class="p-6 sm:p-8 space-y-4">
          <div class="flex justify-between items-start gap-4">
            <h3 :id="titleId" class="text-lg font-bold text-white">
              {{ t("account.backup_restore_title") }}
            </h3>
            <button
              type="button"
              class="text-gray-400 hover:text-white text-sm"
              @click="close"
            >
              {{ t("common.close") }}
            </button>
          </div>

          <template v-if="!report">
            <p class="text-sm text-gray-400">
              {{ t("account.backup_restore_desc") }}
            </p>
            <input
              ref="fileInputRef"
              type="file"
              accept="application/json,.json"
              class="block w-full text-sm text-gray-300 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-600 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white hover:file:bg-indigo-500"
              :aria-label="t('account.backup_restore_pick_file')"
              @change="onFilePicked"
            />
            <p v-if="validationError" class="text-sm text-red-400" role="status" aria-live="polite">
              {{ validationErrorText }}
            </p>

            <div
              v-if="validated"
              class="rounded-xl border border-gray-700 bg-gray-900/50 p-4 space-y-2 text-sm"
            >
              <p class="text-gray-300">
                {{ t("account.backup_restore_preview_created", { date: formatDate(validated.envelope.created_at) }) }}
              </p>
              <p class="text-gray-400">
                {{ t("account.backup_restore_preview_counts", previewCounts) }}
              </p>
              <p v-if="validated.ownerMatches === false" class="text-amber-300">
                {{ t("account.backup_restore_owner_mismatch") }}
              </p>
              <p class="text-gray-500 text-xs">
                {{ t("account.backup_restore_merge_note") }}
              </p>
            </div>

            <button
              v-if="validated"
              type="button"
              class="w-full py-3 rounded-xl bg-red-600 hover:bg-red-500 text-white text-sm font-semibold disabled:opacity-50"
              :disabled="restoring"
              @click="showApplyConfirm = true"
            >
              {{ restoring ? t("common.loading") : t("account.backup_restore_apply") }}
            </button>
          </template>

          <template v-else>
            <p
              class="text-sm"
              :class="report.failed.length ? 'text-amber-300' : 'text-emerald-300'"
              role="status"
              aria-live="polite"
            >
              {{
                report.failed.length
                  ? t("account.backup_restore_report_failures", { upserted: report.upserted, failed: report.failed.length })
                  : t("account.backup_restore_report_ok", { upserted: report.upserted })
              }}
            </p>
            <ul v-if="report.failed.length" class="text-xs text-gray-400 space-y-1 max-h-40 overflow-y-auto">
              <li v-for="(failure, index) in report.failed.slice(0, 20)" :key="index">
                {{ failure.table }}: {{ failure.id ?? "?" }}
              </li>
            </ul>
            <button
              type="button"
              class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold"
              @click="close"
            >
              {{ t("common.close") }}
            </button>
          </template>
        </div>
      </div>
    </div>

    <DangerConfirmModal
      :open="showApplyConfirm"
      variant="dark"
      :title="t('account.backup_restore_confirm_title')"
      :body="t('account.backup_restore_confirm_body')"
      :confirm-label="t('account.backup_restore_apply')"
      :busy="restoring"
      @close="showApplyConfirm = false"
      @confirm="applyRestore"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import DangerConfirmModal from "../ui/DangerConfirmModal.vue";
import type { SnapshotRestoreReport } from "../../evolu/invoicingSnapshot";
import type {
  BackupValidationError,
  ValidatedBackup,
} from "../../evolu/invoicingBackupRestore";

/**
 * Backup restore flow (P1 phase 3): pick file -> validate (schema + SHA-256)
 * -> preview counts and owner pairing -> confirmed MERGE into the current
 * owner -> per-table report. The Evolu client and restore module are imported
 * lazily so the Profile chunk stays WASM-free.
 */
const props = defineProps<{ open: boolean; ownerIdHash: string | null }>();

const emit = defineEmits<{ close: []; restored: [] }>();

const { t, locale } = useI18n();

const titleId = `backup-restore-title-${Math.random().toString(36).slice(2, 10)}`;
const fileInputRef = ref<HTMLInputElement | null>(null);
const validated = ref<ValidatedBackup | null>(null);
const validationError = ref<BackupValidationError | null>(null);
const restoring = ref(false);
const showApplyConfirm = ref(false);
const report = ref<SnapshotRestoreReport | null>(null);

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      validated.value = null;
      validationError.value = null;
      restoring.value = false;
      showApplyConfirm.value = false;
      report.value = null;
    }
  },
);

const validationErrorText = computed(() => {
  switch (validationError.value) {
    case "invalid_json":
    case "invalid_format":
      return t("account.backup_restore_invalid_file");
    case "unsupported_version":
      return t("account.backup_restore_unsupported_version");
    case "hash_missing":
    case "hash_mismatch":
      return t("account.backup_restore_hash_mismatch");
    case "hash_unavailable":
      return t("account.backup_restore_failed");
    default:
      return "";
  }
});

const previewCounts = computed(() => {
  const counts = validated.value?.tableCounts ?? {};
  return {
    documents: counts.document ?? 0,
    contacts: counts.contact ?? 0,
    companies: counts.company ?? 0,
    expenses: counts.expense ?? 0,
  };
});

async function onFilePicked(event: Event) {
  validated.value = null;
  validationError.value = null;
  const file = (event.target as HTMLInputElement).files?.[0];
  if (!file) return;
  try {
    const text = await file.text();
    const { parseAndValidateBackup } = await import("../../evolu/invoicingBackupRestore");
    const result = await parseAndValidateBackup(text, props.ownerIdHash);
    if (result.ok) {
      validated.value = result.value;
    } else {
      validationError.value = result.error;
    }
  } catch (error) {
    console.error("Backup file validation failed:", error);
    validationError.value = "invalid_json";
  }
}

async function applyRestore() {
  // Re-entrancy guard: the confirm button disables via busy, but a queued
  // second invocation (e.g. Enter keypress) must not start a parallel restore.
  if (!validated.value || restoring.value) return;
  restoring.value = true;
  try {
    const { evolu } = await import("../../evolu/client");
    const { restoreInvoicingBackup } = await import("../../evolu/invoicingBackupRestore");
    report.value = await restoreInvoicingBackup(evolu, validated.value);
    showApplyConfirm.value = false;
    emit("restored");
  } catch (error) {
    console.error("Backup restore failed:", error);
    validationError.value = "hash_unavailable";
    showApplyConfirm.value = false;
  } finally {
    restoring.value = false;
  }
}

function close() {
  if (restoring.value) return;
  emit("close");
}

function formatDate(iso: string): string {
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? iso : date.toLocaleString(locale.value);
}
</script>
