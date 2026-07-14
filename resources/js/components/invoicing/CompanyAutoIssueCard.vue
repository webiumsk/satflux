<template>
  <div class="invoicing-card invoicing-card-pad mt-6 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h2 class="text-base font-semibold">
        {{ t("invoicing.auto_issue_title") }}
      </h2>
      <span
        v-if="enabled && syncedAt"
        class="text-xs text-gray-500"
      >
        {{ t("invoicing.auto_issue_synced_at", { date: formatDate(syncedAt) }) }}
      </span>
    </div>

    <p class="text-sm text-gray-600">
      {{ t("invoicing.auto_issue_desc") }}
    </p>

    <div v-if="loading" class="invoicing-muted text-sm py-2">
      {{ t("common.loading") }}
    </div>

    <template v-else>
      <label class="flex items-start gap-2 text-sm">
        <input
          v-model="enabled"
          type="checkbox"
          class="mt-0.5 rounded border-gray-300"
          :disabled="busy"
          @change="onToggleEnabled"
        />
        <span>
          {{ t("invoicing.auto_issue_toggle") }}
          <span class="block text-xs text-amber-700 mt-1">
            {{ t("invoicing.auto_issue_server_warning") }}
          </span>
        </span>
      </label>

      <label v-if="enabled" class="flex items-center gap-2 text-sm">
        <input
          v-model="autoEmail"
          type="checkbox"
          class="rounded border-gray-300"
          :disabled="busy"
          @change="onSettingsChanged"
        />
        {{ t("invoicing.auto_issue_email_toggle") }}
      </label>

      <div v-if="enabled" class="flex items-center gap-3">
        <button
          type="button"
          class="invoicing-btn-secondary text-sm"
          :disabled="busy"
          @click="onSettingsChanged"
        >
          {{ busy ? t("common.loading") : t("invoicing.auto_issue_sync_now") }}
        </button>
      </div>

      <p
        v-if="message"
        role="status"
        aria-live="polite"
        class="text-sm"
        :class="messageIsError ? 'text-red-600' : 'text-emerald-700'"
      >
        {{ message }}
      </p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import {
  disableAutoIssueProfile,
  fetchAutoIssueProfileStatus,
  syncAutoIssueProfile,
} from "../../evolu/autoIssueProfileSync";

/**
 * WooCommerce headless auto-issue opt-in (P3). Enabling syncs the company
 * invoice header, e-mail settings and the local series format to the bridge
 * company so the server can issue + email paid orders without the browser.
 */
const props = defineProps<{
  companyId: string;
  company: Record<string, unknown> | null;
}>();

const { t, locale } = useI18n();

const loading = ref(true);
const busy = ref(false);
const enabled = ref(false);
const autoEmail = ref(true);
const syncedAt = ref<string | null>(null);
const message = ref("");
const messageIsError = ref(false);

onMounted(async () => {
  const result = await fetchAutoIssueProfileStatus(props.companyId);
  if (result.ok && result.status) {
    enabled.value = true;
    autoEmail.value = result.status.autoEmail;
    syncedAt.value = result.status.syncedAt;
    // Quiet refresh: changes made on other tabs (series, e-mail settings)
    // land in the profile the next time the merchant opens this page.
    void runSync(true);
  }
  loading.value = false;
});

async function runSync(quiet = false): Promise<void> {
  if (!props.company) return;
  busy.value = true;
  message.value = "";
  messageIsError.value = false;
  try {
    const result = await syncAutoIssueProfile(props.company, autoEmail.value);
    if (result.ok) {
      syncedAt.value = result.status.syncedAt;
      if (!quiet) {
        message.value = t("invoicing.auto_issue_sync_success");
      }
    } else {
      message.value = t(
        result.error === "bridge_unavailable"
          ? "invoicing.auto_issue_bridge_unavailable"
          : "invoicing.auto_issue_sync_failed",
      );
      messageIsError.value = true;
    }
  } finally {
    busy.value = false;
  }
}

async function onToggleEnabled(): Promise<void> {
  if (enabled.value) {
    await runSync();
    if (messageIsError.value) {
      enabled.value = false;
    }
    return;
  }

  busy.value = true;
  message.value = "";
  try {
    const result = await disableAutoIssueProfile(props.companyId);
    if (result.ok) {
      syncedAt.value = null;
      message.value = t("invoicing.auto_issue_disabled");
    } else {
      message.value = t("invoicing.auto_issue_sync_failed");
      messageIsError.value = true;
      enabled.value = true;
    }
  } finally {
    busy.value = false;
  }
}

async function onSettingsChanged(): Promise<void> {
  if (enabled.value) {
    await runSync();
  }
}

/** Re-sync after the parent saved company data (called via template ref). */
async function resyncIfEnabled(): Promise<void> {
  if (enabled.value && !busy.value) {
    await runSync();
  }
}

defineExpose({ resyncIfEnabled });

function formatDate(iso: string): string {
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? iso : date.toLocaleString(locale.value);
}
</script>
