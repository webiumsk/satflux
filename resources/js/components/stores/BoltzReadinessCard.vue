<template>
  <div class="bg-gray-800/60 border border-gray-700 rounded-xl p-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2.5 min-w-0">
        <h3 class="text-sm font-semibold text-white">
          {{ t('stores.boltz_readiness_title') }}
        </h3>
        <span
          class="shrink-0 text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-md border"
          :class="statusBadgeClass"
        >
          {{ t(`stores.boltz_status_${readiness?.status ?? 'loading'}`) }}
        </span>
      </div>
      <button
        type="button"
        class="text-xs text-gray-400 hover:text-white disabled:opacity-50 inline-flex items-center gap-1.5"
        :disabled="loading"
        @click="load(true)"
      >
        <svg
          class="w-3.5 h-3.5"
          :class="loading ? 'animate-spin' : ''"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        {{ t('common.refresh') }}
      </button>
    </div>

    <div v-if="loading && !readiness" class="mt-3 text-sm text-gray-400">
      {{ t('common.loading') }}
    </div>

    <template v-else-if="readiness">
      <p v-if="limitsText" class="mt-3 text-sm text-gray-300 leading-relaxed">
        {{ limitsText }}
      </p>
      <p v-else class="mt-3 text-sm text-gray-400">
        {{ t('stores.boltz_limits_unavailable_hint') }}
      </p>

      <p v-if="feeText" class="mt-1 text-xs text-gray-500">
        {{ feeText }}
      </p>

      <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
        <span v-if="readiness.limits">
          {{ t('stores.boltz_limits_fetched_at') }}:
          {{ formatTimestamp(readiness.limits.fetched_at) }}
        </span>
        <span
          v-if="readiness.limits?.is_stale"
          class="text-amber-400 font-medium"
        >
          {{ t('stores.boltz_limits_stale_badge') }}
        </span>
        <span>
          {{ t('stores.boltz_checked_at') }}:
          {{ formatTimestamp(readiness.checked_at) }}
        </span>
      </div>

      <ul v-if="visibleReasons.length" class="mt-3 space-y-1">
        <li
          v-for="reason in visibleReasons"
          :key="reason"
          class="text-xs text-amber-300/95 flex items-start gap-1.5"
        >
          <span aria-hidden="true" class="mt-0.5">&#9888;</span>
          <span>{{ t(`stores.boltz_reason_${reason}`) }}</span>
        </li>
      </ul>
    </template>

    <p v-else class="mt-3 text-sm text-red-400">
      {{ t('stores.boltz_readiness_load_failed') }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { walletApi, type BoltzReadiness } from '../../services/api';
import { formatSats } from '../../utils/formatSats';

const props = defineProps<{ storeId: string }>();

const { t, locale } = useI18n();

const readiness = ref<BoltzReadiness | null>(null);
const loading = ref(false);

/** Reason codes that have their own dedicated UI element (status badge / stale badge). */
const REASONS_SHOWN_ELSEWHERE = new Set(['limits_stale']);

const visibleReasons = computed(() =>
  (readiness.value?.reasons ?? []).filter((r) => !REASONS_SHOWN_ELSEWHERE.has(r)),
);

const statusBadgeClass = computed(() => {
  switch (readiness.value?.status) {
    case 'ready':
      return 'bg-green-500/15 text-green-300 border-green-500/40';
    case 'degraded':
    case 'stale':
      return 'bg-amber-500/15 text-amber-300 border-amber-500/40';
    case 'unavailable':
    case 'misconfigured':
      return 'bg-red-500/15 text-red-300 border-red-500/40';
    case 'unsupported':
      return 'bg-gray-600/30 text-gray-300 border-gray-500/40';
    default:
      return 'bg-gray-600/30 text-gray-300 border-gray-500/40';
  }
});

const limitsText = computed(() => {
  const limits = readiness.value?.limits;
  if (!limits) return '';
  return t('stores.boltz_limits_text', {
    min: formatSats(limits.min, locale.value),
    max: formatSats(limits.max, locale.value),
  });
});

const feeText = computed(() => {
  const fees = readiness.value?.fees;
  if (!fees) return '';
  const miner = Object.entries(fees.miner_fees ?? {})
    .map(([kind, sats]) => `${kind}: ${formatSats(sats, locale.value)}`)
    .join(', ');
  return t('stores.boltz_fee_text', { percentage: fees.percentage, miner });
});

function formatTimestamp(iso: string): string {
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? iso : d.toLocaleString(locale.value);
}

async function load(refresh = false): Promise<void> {
  loading.value = true;
  try {
    readiness.value = await walletApi.boltz.readiness(props.storeId, { refresh });
  } catch {
    // Global error flash covers network/5xx; card shows its own failed state.
    if (refresh) readiness.value = null;
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  void load();
});
</script>
