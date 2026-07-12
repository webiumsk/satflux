<template>
  <div class="mt-4 border-t border-gray-600 pt-4 space-y-3">
    <h5 class="text-sm font-semibold text-white">
      {{ t("account.storage_title") }}
    </h5>
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
      <div>
        <dt class="text-gray-500">{{ t("account.storage_persisted_label") }}</dt>
        <dd :class="persisted === true ? 'text-emerald-300' : 'text-gray-200'">
          {{
            persisted === true
              ? t("account.storage_persisted_yes")
              : persisted === false
                ? t("account.storage_persisted_no")
                : t("account.storage_persisted_unknown")
          }}
        </dd>
      </div>
      <div>
        <dt class="text-gray-500">{{ t("account.storage_usage_label") }}</dt>
        <dd class="text-gray-200">
          {{
            estimate.usageBytes != null && estimate.quotaBytes != null
              ? t("account.storage_usage_value", {
                  used: formatByteSize(estimate.usageBytes),
                  quota: formatByteSize(estimate.quotaBytes),
                  percent: estimate.usagePercent ?? 0,
                })
              : t("account.storage_usage_unknown")
          }}
        </dd>
      </div>
    </dl>
    <div
      v-if="estimate.usagePercent != null"
      class="h-2 rounded-full bg-gray-700 overflow-hidden"
      role="progressbar"
      :aria-valuenow="estimate.usagePercent"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-label="t('account.storage_usage_label')"
    >
      <div
        class="h-full rounded-full"
        :class="critical ? 'bg-red-500' : 'bg-indigo-500'"
        :style="{ width: `${estimate.usagePercent}%` }"
      />
    </div>
    <p v-if="critical" class="text-xs text-red-400">
      {{ t("account.storage_warning_hint") }}
    </p>
    <p v-if="persisted === false" class="text-xs text-amber-300">
      {{ t("account.storage_not_persisted_hint") }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import {
  getStorageEstimate,
  isStoragePersisted,
  isStorageUsageCritical,
  requestPersistence,
  type StorageEstimateInfo,
} from "../../services/browserStorage";
import { formatByteSize } from "../../evolu/invoicingLocalStats";

/** Local browser storage status (P1 phase 5): persistence grant + used/quota bar. */
const { t } = useI18n();

const persisted = ref<boolean | null>(null);
const estimate = ref<StorageEstimateInfo>({ usageBytes: null, quotaBytes: null, usagePercent: null });
const critical = ref(false);

onMounted(async () => {
  // Re-request is a no-op when already granted; surfaces the current state.
  await requestPersistence();
  persisted.value = await isStoragePersisted();
  estimate.value = await getStorageEstimate();
  critical.value = isStorageUsageCritical(estimate.value);
});
</script>
