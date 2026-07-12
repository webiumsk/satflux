<template>
  <div
    v-if="visible"
    class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 flex flex-wrap items-center justify-between gap-3"
    role="status"
  >
    <div class="text-sm text-amber-900">
      <p class="font-medium">{{ t("invoicing.backup_reminder_title") }}</p>
      <p class="text-amber-800">
        {{
          lastExportAt
            ? t("invoicing.backup_reminder_stale", { date: formatDate(lastExportAt) })
            : t("invoicing.backup_reminder_never")
        }}
      </p>
    </div>
    <div class="flex items-center gap-2">
      <button
        type="button"
        class="text-xs font-medium rounded-md px-3 py-1.5 border border-amber-300 text-amber-800 hover:bg-amber-100"
        @click="snooze"
      >
        {{ t("invoicing.backup_reminder_snooze") }}
      </button>
      <router-link
        to="/account"
        class="text-xs font-medium rounded-md px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white"
      >
        {{ t("invoicing.backup_reminder_export_now") }}
      </router-link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import {
  getLastExportMeta,
  isBackupReminderSnoozed,
  isBackupStale,
  snoozeBackupReminder,
} from "../../services/backupState";
import { sha256Hex } from "../../utils/sha256";

/**
 * Weekly backup reminder (P1 phase 3): shown on the invoicing hub when local
 * data exists and the newest export is missing or older than 7 days.
 * Dismissible for 7 days. All checks run lazily so the banner costs nothing
 * when it has nothing to say.
 */
const { t, locale } = useI18n();

const visible = ref(false);
const lastExportAt = ref<string | null>(null);

onMounted(async () => {
  try {
    if (isBackupReminderSnoozed()) return;
    const { countLocalInvoicingData } = await import("../../evolu/localDataPresence");
    const counts = await countLocalInvoicingData();
    if (!counts.hasData) return;
    const { evolu } = await import("../../evolu/client");
    const owner = await evolu.appOwner;
    const hash = await sha256Hex(String(owner.id));
    if (!hash) return;
    const meta = getLastExportMeta(hash);
    if (!isBackupStale(meta)) return;
    lastExportAt.value = meta?.exportedAt ?? null;
    visible.value = true;
  } catch {
    // Evolu unavailable - nothing to remind about.
  }
});

function snooze() {
  snoozeBackupReminder();
  visible.value = false;
}

function formatDate(iso: string): string {
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? iso : date.toLocaleDateString(locale.value);
}
</script>
