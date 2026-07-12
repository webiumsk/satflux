<template>
  <div
    v-if="visible"
    class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 flex flex-wrap items-center justify-between gap-3"
    role="status"
  >
    <div class="text-sm text-red-900">
      <p class="font-medium">{{ t("invoicing.storage_warning_title") }}</p>
      <p class="text-red-800">
        {{
          quotaError
            ? t("invoicing.storage_quota_exceeded")
            : t("invoicing.storage_warning_80", { percent: usagePercent ?? 0 })
        }}
      </p>
    </div>
    <router-link
      to="/account"
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
 * Storage pressure warning (P1 phase 5): shown when the origin uses >= 80%
 * of its browser quota or when Evolu reported a quota-like error. The CTA
 * points at the Profile backup export - saving the data comes first.
 */
const { t } = useI18n();

const usagePercent = ref<number | null>(null);
const usageCritical = ref(false);
const evoluError = evoluErrorRef();

const quotaError = computed(() => isQuotaLikeEvoluError(evoluError.value));
const visible = computed(() => usageCritical.value || quotaError.value);

onMounted(async () => {
  const info = await getStorageEstimate();
  usagePercent.value = info.usagePercent;
  usageCritical.value = isStorageUsageCritical(info);
});
</script>
