<template>
  <div class="mt-4 border-t border-gray-600 pt-4 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h5 class="text-sm font-semibold text-white">
        {{ t("account.backup_title") }}
      </h5>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="text-xs font-medium rounded-md px-3 py-1.5 border border-gray-600 text-gray-300 hover:text-white"
          @click="showRestoreModal = true"
        >
          {{ t("account.backup_restore_button") }}
        </button>
        <button
          type="button"
          class="text-xs font-medium rounded-md px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white disabled:opacity-50"
          :disabled="exporting"
          @click="runExport"
        >
          {{ exporting ? t("common.loading") : t("account.backup_export_button") }}
        </button>
      </div>
    </div>
    <p class="text-xs text-gray-400">
      {{ t("account.backup_desc") }}
    </p>
    <p v-if="!encryptEnabled" class="text-xs text-amber-300">
      {{ t("account.backup_plaintext_warning") }}
    </p>

    <label class="flex items-center gap-2 text-xs text-gray-300">
      <input
        v-model="encryptEnabled"
        type="checkbox"
        class="rounded border-gray-600 bg-gray-700 text-indigo-500 focus:ring-indigo-500"
      />
      {{ t("account.backup_encrypt_toggle") }}
    </label>

    <div v-if="encryptEnabled" class="space-y-2">
      <div class="flex gap-2">
        <input
          v-model="encryptPassphrase"
          type="text"
          autocomplete="off"
          spellcheck="false"
          class="flex-1 rounded-md border border-gray-600 bg-gray-900/60 px-3 py-1.5 text-xs font-mono text-gray-200 placeholder-gray-500"
          :placeholder="t('account.backup_encrypt_passphrase_placeholder')"
          :aria-label="t('account.backup_encrypt_toggle')"
        />
        <button
          type="button"
          class="text-xs font-medium rounded-md px-3 py-1.5 border border-gray-600 text-gray-300 hover:text-white whitespace-nowrap"
          @click="fillGeneratedPassphrase"
        >
          {{ t("account.backup_encrypt_generate") }}
        </button>
      </div>
      <p v-if="passphraseTooWeak" class="text-xs text-red-400">
        {{ t("account.backup_encrypt_passphrase_weak") }}
      </p>
      <p class="text-xs text-amber-300">
        {{ t("account.backup_encrypt_loss_warning") }}
      </p>
    </div>
    <p v-if="!lastExportUnknown" class="text-xs" :class="lastExport ? 'text-gray-300' : 'text-gray-500'">
      {{
        lastExport
          ? t("account.backup_last_export_at", { date: formatDate(lastExport.exportedAt) })
          : t("account.backup_never_exported")
      }}
    </p>
    <p
      v-if="message"
      role="status"
      aria-live="polite"
      class="text-xs"
      :class="messageIsError ? 'text-red-400' : 'text-emerald-300'"
    >
      {{ message }}
    </p>

    <InvoicingBackupRestoreModal
      :open="showRestoreModal"
      :owner-id-hash="ownerIdHash"
      @close="showRestoreModal = false"
      @restored="refreshLastExport"
    />
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
import InvoicingBackupRestoreModal from "./InvoicingBackupRestoreModal.vue";

/**
 * Full-dataset backup export (P1 phase 2). Lives in the Profile next to the
 * local Evolu stats; the Evolu client is imported lazily on click so the
 * Profile chunk stays WASM-free until the user actually exports.
 */
const props = defineProps<{ ownerId: string }>();

const { t, locale } = useI18n();

const exporting = ref(false);
const encryptEnabled = ref(false);
const encryptPassphrase = ref("");
const passphraseTooWeak = ref(false);
const message = ref("");
const messageIsError = ref(false);
const lastExport = ref<LastExportMeta | null>(null);
// Hashing the owner id failed (no WebCrypto) - the export history is
// unknown, which must not be presented as "never exported".
const lastExportUnknown = ref(false);
const showRestoreModal = ref(false);
const ownerIdHash = ref<string | null>(null);

async function refreshLastExport() {
  const hash = await sha256Hex(props.ownerId);
  ownerIdHash.value = hash;
  lastExportUnknown.value = hash === null;
  lastExport.value = hash ? getLastExportMeta(hash) : null;
}

onMounted(refreshLastExport);
watch(() => props.ownerId, refreshLastExport);

async function fillGeneratedPassphrase() {
  const { generateBackupPassphrase } = await import("../../evolu/invoicingBackupCrypto");
  encryptPassphrase.value = generateBackupPassphrase();
  passphraseTooWeak.value = false;
}

async function runExport() {
  passphraseTooWeak.value = false;
  if (encryptEnabled.value) {
    const { isAcceptablePassphrase } = await import("../../services/passphraseCrypto");
    if (!isAcceptablePassphrase(encryptPassphrase.value)) {
      passphraseTooWeak.value = true;
      return;
    }
  }
  exporting.value = true;
  message.value = "";
  messageIsError.value = false;
  try {
    const { evolu } = await import("../../evolu/client");
    const { exportInvoicingBackup } = await import("../../evolu/invoicingBackup");
    const passphrase = encryptEnabled.value ? encryptPassphrase.value : null;
    const meta = await exportInvoicingBackup(evolu, props.ownerId, passphrase);
    lastExport.value = meta;
    lastExportUnknown.value = false;
    message.value = t("account.backup_export_success");
  } catch (error) {
    console.error("Invoicing backup export failed:", error);
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
