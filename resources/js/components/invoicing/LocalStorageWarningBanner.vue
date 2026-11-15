<template>
  <div v-if="visible" class="space-y-3">
    <div
      v-if="usageCritical || quotaError"
      class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 flex flex-wrap items-center justify-between gap-3"
      role="status"
    >
    <div class="text-sm text-red-900">
      <p class="font-medium">
        {{ t(usageCritical ? "invoicing.storage_warning_title" : "invoicing.storage_error_title") }}
      </p>
      <p v-if="usageCritical" class="text-red-800">
        {{ t("invoicing.storage_warning_80", { percent: usagePercent ?? 0 }) }}
      </p>
      <p v-if="quotaError" class="text-red-800">{{ t("invoicing.storage_quota_exceeded") }}</p>
    </div>
    <router-link
      to="/account?tab=sync"
      class="text-xs font-medium rounded-md px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white"
    >
      {{ t("invoicing.storage_export_cta") }}
    </router-link>
    </div>

    <div
      v-if="relayProblems.length"
      class="rounded-lg border px-4 py-3 text-sm"
      :class="allRelaysAffected ? 'border-red-300 bg-red-50 text-red-900' : 'border-amber-300 bg-amber-50 text-amber-900'"
      role="status"
    >
      <p class="font-medium">{{ t(allRelaysAffected ? 'invoicing.relay_warning_all' : 'invoicing.relay_warning_some') }}</p>
      <p class="mt-1">{{ t('invoicing.relay_warning_backup_hint') }}</p>
      <ul class="mt-3 space-y-3">
        <li v-for="problem in relayProblems" :key="problem.url">
          <p class="font-medium"><span class="break-all">{{ problem.url }}</span> - {{ t(problem.labelKey) }}</p>
          <p class="mt-1">{{ t(problem.guidanceKey) }}</p>
        </li>
      </ul>
      <p class="mt-3">{{ t('invoicing.relay_warning_next_steps') }}</p>
      <div class="mt-3 flex flex-wrap gap-3">
        <router-link to="/account?tab=sync" class="rounded-md border border-current px-3 py-1.5 text-xs font-medium hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">
          {{ t('invoicing.storage_export_cta') }}
        </router-link>
        <router-link to="/account?tab=sync" class="rounded-md px-3 py-1.5 text-xs font-medium underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">
          {{ t('invoicing.relay_warning_settings') }}
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import { useAuthStore } from "../../store/auth";
import { useEvoluRelayConnections } from "../../composables/useEvoluRelayConnections";
import { useOnlineStatus } from "../../composables/useOnlineStatus";
import { resolveConfiguredEvoluRelays } from "../../evolu/config";
import { getActiveEvoluRelayUrls, subscribeActiveEvoluRelayUrls } from "../../evolu/relayPreferenceLifecycle";
import { isRelayProtocolError } from "../../evolu/relayProtocolStatus";
import {
  getStorageEstimate,
  isStorageUsageCritical,
} from "../../services/browserStorage";
import { evoluErrorRef, isQuotaLikeEvoluError } from "../../evolu/evoluErrorMonitor";

/**
 * Keep local storage failures separate from fresh, permitted relay observations.
 * A historical SDK error never establishes a current relay failure or backup.
 */
const { t } = useI18n();
const auth = useAuthStore();
const { connections, protocols } = useEvoluRelayConnections();
const online = useOnlineStatus();
const activeUrls = ref<readonly string[]>(getActiveEvoluRelayUrls());
let unsubscribeActiveUrls: (() => void) | undefined;

const usagePercent = ref<number | null>(null);
const usageCritical = ref(false);
const evoluError = evoluErrorRef();

const quotaError = computed(() => isQuotaLikeEvoluError(evoluError.value));
const enabledRelays = computed(() => auth.user
  ? resolveConfiguredEvoluRelays(auth.user).filter(relay => relay.enabled)
  : []);
type RelayProblem = { url: string; labelKey: string; guidanceKey: string };
const relayProblems = computed<RelayProblem[]>(() => enabledRelays.value.flatMap(relay => {
  if (!activeUrls.value.includes(relay.url)) return [];
  const protocol = protocols.value[relay.url]?.state;
  if (protocol && isRelayProtocolError(protocol)) {
    const guidance = protocol === "quota_exceeded" ? "quota"
      : protocol === "write_key_rejected" ? "key"
      : protocol === "version_mismatch" ? "version" : "retry";
    return [{ url: relay.url, labelKey: `account.evolu_relay_protocol_${protocol}`, guidanceKey: `invoicing.relay_warning_${guidance}_steps` }];
  }
  const connection = connections.value[relay.url];
  if (online.value && (connection === "error" || connection === "disconnected")) {
    return [{ url: relay.url, labelKey: `account.evolu_relay_state_${connection}`, guidanceKey: "invoicing.relay_warning_retry_steps" }];
  }
  return [];
}));
const allRelaysAffected = computed(() => relayProblems.value.length > 0
  && relayProblems.value.length === enabledRelays.value.length);
const visible = computed(() => usageCritical.value || quotaError.value || relayProblems.value.length > 0);

onMounted(() => {
  const refresh = () => { activeUrls.value = getActiveEvoluRelayUrls(); };
  unsubscribeActiveUrls = subscribeActiveEvoluRelayUrls(refresh);
  refresh();
});
onUnmounted(() => unsubscribeActiveUrls?.());

onMounted(async () => {
  const info = await getStorageEstimate();
  usagePercent.value = info.usagePercent;
  usageCritical.value = isStorageUsageCritical(info);
});
</script>
