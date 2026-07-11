<template>
  <div class="bg-gray-800/60 border border-gray-700 rounded-xl p-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <h3 class="text-sm font-semibold text-white">
        {{ t('stores.settlement_panel_title') }}
      </h3>
      <button
        type="button"
        class="text-xs text-gray-400 hover:text-white disabled:opacity-50"
        :disabled="syncing"
        @click="syncNow"
      >
        {{ syncing ? t('common.loading') : t('stores.settlement_sync') }}
      </button>
    </div>

    <div v-if="loading" class="text-sm text-gray-400">{{ t('common.loading') }}</div>

    <div v-else-if="!rows.length" class="text-sm text-gray-400">
      {{ t('stores.settlement_empty') }}
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="row in rows"
        :key="row.id"
        class="rounded-xl border border-gray-700 bg-gray-900/50 p-4 space-y-2"
      >
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-sm font-medium text-white">
            {{ t(`stores.settlement_category_${row.category}`) }}
          </span>
          <span
            class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-md border"
            :class="qualityBadgeClass(row.net_quality)"
          >
            {{ t(`stores.settlement_quality_${row.net_quality}`) }}
          </span>
          <span v-if="row.payment_status" class="text-xs text-gray-500">
            {{ row.payment_status }}
          </span>
          <span v-if="row.paid_at" class="text-xs text-gray-500 ml-auto">
            {{ new Date(row.paid_at).toLocaleString(locale) }}
          </span>
        </div>

        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-2 text-sm">
          <div>
            <dt class="text-xs uppercase tracking-wider text-gray-500">
              {{ t('stores.settlement_gross') }}
            </dt>
            <dd class="text-gray-200 tabular-nums">{{ formatSats(row.gross_sats, locale) }}</dd>
          </div>
          <div v-if="row.estimated_service_fee_sats !== null">
            <dt class="text-xs uppercase tracking-wider text-gray-500">
              {{ t('stores.settlement_estimated_service_fee') }}
            </dt>
            <dd class="text-gray-200 tabular-nums">{{ formatSats(row.estimated_service_fee_sats, locale) }}</dd>
          </div>
          <div v-if="row.estimated_network_fee_sats !== null">
            <dt class="text-xs uppercase tracking-wider text-gray-500">
              {{ t('stores.settlement_estimated_network_fee') }}
            </dt>
            <dd class="text-gray-200 tabular-nums">{{ formatSats(row.estimated_network_fee_sats, locale) }}</dd>
          </div>
          <div v-if="row.estimated_net_settlement_sats !== null">
            <dt class="text-xs uppercase tracking-wider text-gray-500">
              {{ t('stores.settlement_estimated_net') }}
              <span v-if="row.settlement_asset" class="normal-case">({{ row.settlement_asset }})</span>
            </dt>
            <dd class="text-gray-100 font-medium tabular-nums">
              {{ formatSats(row.estimated_net_settlement_sats, locale) }}
            </dd>
          </div>
        </dl>

        <p
          v-if="effectiveFee(row) !== null"
          class="text-xs text-gray-500"
        >
          {{ t('stores.settlement_effective_fee', {
            sats: formatSats(row.gross_sats - (row.estimated_net_settlement_sats ?? 0), locale),
            percent: effectiveFee(row),
          }) }}
        </p>

        <p v-if="row.net_quality === 'estimated'" class="text-xs text-amber-300/90">
          {{ t('stores.settlement_estimate_disclaimer') }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { settlementsApi, type StoreSettlement } from '../../services/api';
import { formatSats } from '../../utils/formatSats';

const props = defineProps<{ storeId: string; invoiceId: string }>();

const { t, locale } = useI18n();

const rows = ref<StoreSettlement[]>([]);
const loading = ref(true);
const syncing = ref(false);

function qualityBadgeClass(quality: StoreSettlement['net_quality']): string {
  switch (quality) {
    case 'final':
    case 'reported':
      return 'bg-green-500/15 text-green-300 border-green-500/40';
    case 'estimated':
      return 'bg-amber-500/15 text-amber-300 border-amber-500/40';
    case 'derived':
      return 'bg-indigo-500/15 text-indigo-200 border-indigo-500/40';
    default:
      return 'bg-gray-600/30 text-gray-300 border-gray-500/40';
  }
}

/** Effective total fee in percent (one decimal), only when an estimated net exists. */
function effectiveFee(row: StoreSettlement): string | null {
  if (row.estimated_net_settlement_sats === null || row.gross_sats <= 0) return null;
  const fee = row.gross_sats - row.estimated_net_settlement_sats;
  if (fee <= 0) return null;
  return ((fee / row.gross_sats) * 100).toFixed(2);
}

async function load(): Promise<void> {
  loading.value = true;
  try {
    const page = await settlementsApi.list(props.storeId, { invoice_id: props.invoiceId, per_page: 50 });
    rows.value = page.data ?? [];
  } catch {
    rows.value = [];
  } finally {
    loading.value = false;
  }
}

async function syncNow(): Promise<void> {
  syncing.value = true;
  try {
    await settlementsApi.sync(props.storeId, { invoice_id: props.invoiceId });
    await load();
  } catch {
    // Global error flash covers failures.
  } finally {
    syncing.value = false;
  }
}

onMounted(() => {
  void load();
});
</script>
