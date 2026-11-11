<template>
  <div
    v-if="visible"
    class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 flex flex-wrap items-center justify-between gap-3"
    role="status"
  >
    <div class="text-sm text-red-900">
      <p class="font-medium">
        {{ t(usageCritical ? "invoicing.storage_warning_title"
          : quotaError ? "invoicing.storage_error_title" : "invoicing.relay_quota_title") }}
      </p>
      <p v-if="usageCritical" class="text-red-800">
        {{ t("invoicing.storage_warning_80", { percent: usagePercent ?? 0 }) }}
      </p>
      <p v-if="quotaError" class="text-red-800">{{ t("invoicing.storage_quota_exceeded") }}</p>
      <p v-if="relayQuotaError" class="text-red-800">{{ t("account.evolu_relay_quota_hint") }}</p>
    </div>
    <router-link
      to="/account?tab=sync"
      class="text-xs font-medium rounded-md px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white"
    >
      {{ t("invoicing.storage_export_cta") }}
    </router-link>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import {
  getStorageEstimate,
  isStorageUsageCritical,
} from "../../services/browserStorage";
import { evoluErrorRef, isQuotaLikeEvoluError } from "../../evolu/evoluErrorMonitor";

/**
 * Show local storage pressure and relay quota refusals with their own
 * explanations. The Profile link offers backup and synchronization settings.
 */
const { t } = useI18n();

const usagePercent = ref<number | null>(null);
const usageCritical = ref(false);
const evoluError = evoluErrorRef();

const quotaError = computed(() => isQuotaLikeEvoluError(evoluError.value));
const relayQuotaError = computed(() => evoluError.value === "ProtocolQuotaError");
const visible = computed(() => usageCritical.value || quotaError.value || relayQuotaError.value);

onMounted(async () => {
  const info = await getStorageEstimate();
  usagePercent.value = info.usagePercent;
  usageCritical.value = isStorageUsageCritical(info);
});
</script>
