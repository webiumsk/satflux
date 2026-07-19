<template>
  <div>
    <!-- ============ Sales analytics (primary) ============ -->
    <div class="bg-gray-800/50 backdrop-blur-md border border-gray-700 rounded-2xl p-6 mb-8">
      <div class="flex flex-col gap-4 mb-6">
        <div class="flex items-center justify-between gap-2 flex-wrap">
          <div class="flex items-center gap-2">
            <h2 class="text-xl font-bold text-white">{{ t('dashboard.analytics_title') }}</h2>
            <button
              v-if="!canViewStats"
              type="button"
              @click="showStatsUpgradeModal = true"
              class="text-xs px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-400 border border-amber-500/30 hover:bg-amber-500/30 transition-colors"
            >
              <ProPlanBadge />
            </button>
          </div>
          <p class="text-xs text-gray-500 tabular-nums">{{ analytics?.from }} - {{ analytics?.to }}</p>
        </div>

        <!-- Filter bar: one row above the charts -->
        <div class="flex flex-wrap items-center gap-3">
          <div class="flex flex-wrap gap-1 bg-gray-900/50 rounded-xl p-1 border border-gray-700/50">
            <button
              v-for="p in presetOptions"
              :key="p.value"
              type="button"
              :class="[
                'px-3 py-1.5 text-xs font-bold rounded-lg transition-all',
                preset === p.value ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-gray-500 hover:text-white hover:bg-white/5',
              ]"
              @click="preset = p.value"
            >
              {{ p.label }}
            </button>
          </div>
          <template v-if="preset === 'custom'">
            <input
              v-model="customFrom"
              type="date"
              :aria-label="t('dashboard.custom_from')"
              class="rounded-lg border border-gray-600 bg-gray-900/80 px-2.5 py-1.5 text-xs text-gray-200"
            />
            <span class="text-gray-500 text-xs">-</span>
            <input
              v-model="customTo"
              type="date"
              :aria-label="t('dashboard.custom_to')"
              class="rounded-lg border border-gray-600 bg-gray-900/80 px-2.5 py-1.5 text-xs text-gray-200"
            />
          </template>
          <Select
            v-model="selectedStoreId"
            :options="storeFilterOptions"
            class="min-w-[160px]"
          />
          <Select
            v-model="selectedSource"
            :options="paymentMethodOptions"
            class="min-w-[160px]"
          />
          <div class="flex gap-1 bg-gray-900/50 rounded-xl p-1 border border-gray-700/50">
            <button
              v-for="c in currencyOptions"
              :key="c"
              type="button"
              :class="[
                'px-2.5 py-1.5 text-xs font-bold rounded-lg transition-all',
                selectedCurrency === c ? 'bg-amber-600 text-white' : 'text-gray-500 hover:text-white hover:bg-white/5',
              ]"
              @click="selectedCurrency = c"
            >
              {{ c === 'sats' ? 'sats' : c.toUpperCase() }}
            </button>
          </div>
        </div>
      </div>

      <div v-if="analyticsLoading" class="py-16 flex justify-center">
        <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>

      <template v-else-if="analytics">
        <!-- KPI row -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <div class="rounded-xl border border-gray-700 bg-gray-900/40 p-4 relative overflow-hidden">
            <p class="text-xs font-black text-gray-500 uppercase tracking-widest mb-1">{{ t('dashboard.kpi_revenue') }}</p>
            <p class="text-2xl font-black text-white tracking-tighter tabular-nums">
              {{ formatAmount(totalForCurrency(analytics.totals)) }}
              <span class="text-xs font-bold text-gray-400 uppercase">{{ currencyLabel }}</span>
            </p>
            <DeltaBadge :delta="revenueDelta" :label="t('dashboard.vs_previous')" :new-label="t('dashboard.delta_new')" />
            <div v-if="canViewStats && chartSeries.length > 1" class="absolute bottom-3 right-3 opacity-60">
              <Sparkline :values="chartSeries.map((p) => p.value)" />
            </div>
          </div>
          <div class="rounded-xl border border-gray-700 bg-gray-900/40 p-4">
            <p class="text-xs font-black text-gray-500 uppercase tracking-widest mb-1">{{ t('dashboard.kpi_paid_sales') }}</p>
            <p class="text-2xl font-black text-white tracking-tighter tabular-nums">{{ analytics.totals.paid_count }}</p>
            <DeltaBadge :delta="countDelta" :label="t('dashboard.vs_previous')" :new-label="t('dashboard.delta_new')" />
          </div>
          <div class="rounded-xl border border-gray-700 bg-gray-900/40 p-4">
            <p class="text-xs font-black text-gray-500 uppercase tracking-widest mb-1">{{ t('dashboard.kpi_avg_order') }}</p>
            <p class="text-2xl font-black text-white tracking-tighter tabular-nums">
              {{ formatSats(analytics.totals.avg_order_sats) }}
              <span class="text-xs font-bold text-gray-400 uppercase">sats</span>
            </p>
            <DeltaBadge :delta="avgDelta" :label="t('dashboard.vs_previous')" :new-label="t('dashboard.delta_new')" />
          </div>
          <div class="rounded-xl border border-gray-700 bg-gray-900/40 p-4">
            <p class="text-xs font-black text-gray-500 uppercase tracking-widest mb-1">{{ t('dashboard.kpi_best_day') }}</p>
            <template v-if="analytics.totals.best_day">
              <p class="text-2xl font-black text-white tracking-tighter tabular-nums">
                {{ formatSats(analytics.totals.best_day.amount_sats) }}
                <span class="text-xs font-bold text-gray-400 uppercase">sats</span>
              </p>
              <p class="mt-1 text-xs text-gray-400">{{ analytics.totals.best_day.date }}</p>
            </template>
            <p v-else class="text-2xl font-black text-gray-600 tracking-tighter">-</p>
          </div>
        </div>

        <!-- Pro-gated charts -->
        <div v-if="!canViewStats" class="py-12 text-center rounded-xl bg-gray-900/40 border border-gray-700/50 border-dashed">
          <p class="text-gray-400">{{ t('dashboard.upgrade_to_see_stats') }}</p>
        </div>
        <template v-else>
          <!-- Main chart -->
          <div class="mb-6">
            <div class="flex items-center justify-between gap-3 mb-3 flex-wrap">
              <div class="flex gap-2">
                <button
                  v-for="m in chartModes"
                  :key="m.value"
                  type="button"
                  :class="[
                    'px-4 py-2 text-xs font-bold rounded-lg transition-all',
                    chartMode === m.value ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-gray-500 hover:text-white hover:bg-white/5',
                  ]"
                  @click="chartMode = m.value"
                >
                  {{ m.label }}
                </button>
              </div>
            </div>
            <template v-if="hasSeriesData">
              <LineAreaChart
                v-if="chartMode === 'amount'"
                :points="chartSeries"
                :format-value="formatChartValue"
              />
              <BarChart
                v-else
                :points="chartSeries"
                :format-value="formatChartValue"
              />
            </template>
            <div v-else class="py-10 text-center text-gray-500 rounded-xl bg-gray-900/40 border border-gray-700/50 border-dashed">
              {{ t('dashboard.no_data_in_period') }}
            </div>
          </div>

          <!-- Breakdowns -->
          <div v-if="hasSeriesData" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-xl border border-gray-700 bg-gray-900/40 p-5">
              <h3 class="text-sm font-bold text-white mb-4">{{ t('dashboard.by_source_title') }}</h3>
              <DonutChart
                v-if="donutSlices.length > 0"
                :slices="donutSlices"
                :total-label="t('dashboard.donut_total_label')"
              />
              <p v-else class="text-sm text-gray-500">{{ t('dashboard.no_data_in_period') }}</p>
            </div>
            <div class="rounded-xl border border-gray-700 bg-gray-900/40 p-5">
              <h3 class="text-sm font-bold text-white mb-4">{{ t('dashboard.by_store_title') }}</h3>
              <ul v-if="storeRanking.length > 0" class="space-y-3">
                <li v-for="entry in storeRanking" :key="entry.store_id">
                  <div class="flex items-center justify-between gap-3 text-sm mb-1">
                    <span class="text-gray-200 truncate">{{ entry.name }}</span>
                    <span class="text-gray-400 tabular-nums shrink-0">
                      {{ formatAmount(entry.value) }} {{ currencyLabel }}
                      <span class="text-gray-600">·</span>
                      {{ entry.paid_count }}
                    </span>
                  </div>
                  <div class="h-1.5 rounded-full bg-gray-700/40 overflow-hidden">
                    <div
                      class="h-full rounded-full"
                      :style="{ width: entry.sharePercent + '%', backgroundColor: CHART_PRIMARY }"
                    />
                  </div>
                </li>
              </ul>
              <p v-else class="text-sm text-gray-500">{{ t('dashboard.no_data_in_period') }}</p>
            </div>
          </div>
        </template>
      </template>
    </div>

    <!-- ============ Secondary tiles (stores / all-time revenue / BTCPay) ============ -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <router-link
        :to="storeCount === 0 ? '/stores/create' : '/stores'"
        class="group block bg-gray-800 hover:bg-gray-750 border border-gray-700 hover:border-indigo-500/50 rounded-2xl p-6 transition-all duration-300 hover:shadow-2xl hover:shadow-indigo-900/10 relative overflow-hidden"
      >
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/5 to-purple-600/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
          <svg class="w-24 h-24 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
          </svg>
        </div>
        <div class="relative z-10">
          <p class="text-gray-400 text-sm font-medium mb-1">{{ t('dashboard.active_stores') }}</p>
          <h3 class="text-3xl font-bold text-white">{{ loading ? '...' : storeCount }}</h3>
          <div class="mt-4 text-sm font-medium text-indigo-400 group-hover:text-indigo-300 flex items-center">
            {{ storeCount === 0 ? t('dashboard.create_store') : t('dashboard.view_stores') }}
            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
          </div>
        </div>
      </router-link>

      <div class="group bg-gray-800 hover:bg-gray-750 border border-gray-700 hover:border-indigo-500/50 rounded-2xl p-6 transition-all duration-300 hover:shadow-2xl hover:shadow-indigo-900/10 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/5 to-purple-600/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
        <div class="absolute -top-12 -right-12 w-32 h-32 bg-green-500 rounded-full mix-blend-screen filter blur-3xl opacity-5 group-hover:opacity-10 transition-opacity"></div>
        <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
          <svg class="h-20 w-20 text-orange-500" xmlns="http://www.w3.org/2000/svg" shape-rendering="geometricPrecision" text-rendering="geometricPrecision" image-rendering="optimizeQuality" fill-rule="evenodd" clip-rule="evenodd" viewBox="0 0 512 512.001">
            <path fill="currentColor" d="M256 0c141.385 0 256 114.615 256 256 0 141.386-114.615 256.001-256 256.001S0 397.386 0 256C0 114.615 114.615 0 256 0zm84.612 201.62c-3.88-31.345-29.619-40.265-62.659-42.711l-.517-27.705-25.587.077 1.033 26.815-20.994.354-.33-27.003-26.29.266 1.41 28.22-16.4.628-35.288.417 1.158 26.805 17.994-.304c10.59.175 14.582 6.637 14.495 11.934 1.162 41.42 1.777 70.56 2.62 113.775-.464 3.892-2.146 8.86-9.739 8.637l-17.994.302-4.602 30.607 50.985-.858 1.596 28.923 24.696-.592-.518-27.707 19.399-.679 1.222 27.518 25.587-.077-.706-28.41c42.828-3.191 72.804-14.988 75.663-54.919 1.929-32.15-13.13-46.189-37.927-51.596 14.518-7.655 23.639-21.398 21.693-42.717zm-33.774 90.39c-.008 30.881-53.854 29.493-71.146 29.608l-.77-56.279c17.291-.115 71.363-6.318 71.916 26.671zm-14.386-78.993c1.018 29.102-43.315 26.671-58.312 26.922l-.858-50.983c14.997-.253 59.556-5.415 59.17 24.061zM256.002 48.49c114.604 0 207.51 92.906 207.51 207.513 0 114.604-92.906 207.509-207.51 207.509-114.606 0-207.512-92.905-207.512-207.509 0-114.607 92.906-207.513 207.512-207.513z"></path>
          </svg>
        </div>
        <div class="relative z-10">
          <p class="text-xs font-black text-gray-500 uppercase tracking-widest mb-2">{{ t('dashboard.total_revenue') }}</p>
          <div class="flex items-baseline gap-2 flex-wrap">
            <h3 class="text-4xl font-black text-white tracking-tighter">{{ loading ? '...' : formatRevenue(totalRevenueDisplayValue, selectedRevenueCurrency) }}</h3>
            <span class="text-sm font-bold text-gray-400 uppercase tracking-widest">{{ revenueCurrencyLabel }}</span>
          </div>
          <div v-if="availableRevenueCurrencies.length > 1" class="flex gap-1 mt-3">
            <button
              v-for="c in availableRevenueCurrencies"
              :key="c"
              type="button"
              :class="['px-2 py-1 text-xs font-medium rounded transition-colors', selectedRevenueCurrency === c ? 'bg-amber-600 text-white' : 'text-gray-500 hover:text-white hover:bg-white/5']"
              @click="selectedRevenueCurrency = c"
            >
              {{ c === 'sats' ? 'sats' : c.toUpperCase() }}
            </button>
          </div>
          <div class="mt-6 pt-4 border-t border-gray-700/50">
            <span v-if="totalRevenueIsZero" class="text-xs font-bold text-gray-500 flex items-center uppercase tracking-wider">
              {{ t('dashboard.no_transactions_yet') }}
            </span>
            <span v-else class="text-xs font-bold text-green-500/80 flex items-center uppercase tracking-wider">
              <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
              {{ t('dashboard.all_time_total') }}
            </span>
          </div>
        </div>
      </div>

      <div class="group bg-gray-800 hover:bg-gray-750 border border-gray-700 hover:border-indigo-500/50 rounded-2xl p-6 transition-all duration-300 hover:shadow-2xl hover:shadow-indigo-900/10 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/5 to-purple-600/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
          <svg class="w-24 h-24 text-purple-500" fill="currentColor" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
        </div>
        <div class="relative z-10">
          <p class="text-gray-400 text-sm font-medium mb-1">{{ t('dashboard.btcpay_status_title') }}</p>
          <h3 class="text-3xl font-bold text-white flex items-center">
            <span
              class="flex h-3 w-3 rounded-full mr-3 shrink-0"
              :class="btcpayStatusDotClass"
            />
            {{ btcpayStatusLabel }}
          </h3>
          <p v-if="btcpayPing?.http_status != null" class="mt-1 text-xs text-gray-500 font-mono">
            {{ t('dashboard.btcpay_http_code', { code: btcpayPing.http_status }) }}
          </p>
          <div class="mt-4">
            <router-link to="/support" class="text-sm font-medium text-purple-400 hover:text-purple-300 flex items-center">
              {{ t('dashboard.help_center') }} <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
            </router-link>
          </div>
        </div>
      </div>
    </div>

    <UpgradeModal
      :show="showStatsUpgradeModal"
      :message="t('dashboard.stats_available_in_pro_message')"
      @close="showStatsUpgradeModal = false"
    />

    <div v-if="storeCount === 0" class="group bg-gray-800 hover:bg-gray-750 border border-gray-700 hover:border-indigo-500/50 rounded-2xl p-8 text-center transition-all duration-300 hover:shadow-2xl hover:shadow-indigo-900/10 relative overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/5 to-purple-600/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
      <div class="relative z-10">
        <div class="max-w-md mx-auto">
          <div class="w-16 h-16 bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
          </div>
          <h3 class="text-xl font-bold text-white mb-2">{{ t('dashboard.get_started') }}</h3>
          <p class="text-gray-400 mb-6">{{ t('dashboard.get_started_description') }}</p>
          <router-link to="/stores/create" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors shadow-lg shadow-indigo-600/20">
            {{ t('dashboard.create_new_store') }}
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import api from '../../services/api';
import Select from '../ui/Select.vue';
import UpgradeModal from '../stores/UpgradeModal.vue';
import ProPlanBadge from '../stores/ProPlanBadge.vue';
import LineAreaChart, { type ChartPoint } from '../charts/LineAreaChart.vue';
import BarChart from '../charts/BarChart.vue';
import DonutChart, { type DonutSlice } from '../charts/DonutChart.vue';
import Sparkline from '../charts/Sparkline.vue';
import DeltaBadge from './DeltaBadge.vue';
import { CHART_PRIMARY, SOURCE_COLORS, percentDelta } from '../charts/chartScales';
import {
  isDashboardPeriodPreset,
  useDashboardPeriod,
  type DashboardPeriodPreset,
} from '../../composables/useDashboardPeriod';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

// ---- Overview (stores / all-time revenue / BTCPay ping) ----

const loading = ref(true);
const storeCount = ref(0);
const totalRevenueByCurrency = ref<Record<string, number>>({});
const availableRevenueCurrencies = ref<string[]>(['sats']);
const selectedRevenueCurrency = ref<string>('sats');

type BtcpayPing = { http_status: number | null; state: string };
const btcpayPing = ref<BtcpayPing | null>(null);

const btcpayStatusDotClass = computed(() => {
  const s = btcpayPing.value?.state ?? 'unknown';
  if (s === 'online') return 'bg-green-500';
  if (s === 'client_error') return 'bg-amber-500';
  if (s === 'server_error' || s === 'unreachable') return 'bg-red-500';
  return 'bg-gray-500';
});

const btcpayStatusLabel = computed(() => {
  const s = btcpayPing.value?.state ?? 'unknown';
  const code = btcpayPing.value?.http_status;
  if (s === 'online') return t('dashboard.btcpay_reachable');
  if (s === 'client_error') {
    return code != null ? t('dashboard.btcpay_client_error_with_code', { code }) : t('dashboard.btcpay_client_error');
  }
  if (s === 'server_error') {
    return code != null ? t('dashboard.btcpay_server_error_with_code', { code }) : t('dashboard.btcpay_server_error');
  }
  if (s === 'unreachable') return t('dashboard.btcpay_unreachable');
  return t('dashboard.btcpay_unknown');
});

async function loadDashboardData(opts?: { refresh?: boolean }) {
  loading.value = true;
  try {
    const response = await api.get('/dashboard', { params: opts?.refresh ? { refresh: 1 } : {} });
    storeCount.value = response.data.store_count || 0;
    totalRevenueByCurrency.value = response.data.total_revenue_by_currency || { sats: 0 };
    availableRevenueCurrencies.value = response.data.available_revenue_currencies || ['sats'];
    if (!availableRevenueCurrencies.value.includes('sats')) {
      availableRevenueCurrencies.value = ['sats', ...availableRevenueCurrencies.value];
    }
    btcpayPing.value = response.data.btcpay_ping ?? null;
  } catch (error: unknown) {
    console.error('Failed to load dashboard data:', error);
  } finally {
    loading.value = false;
  }
}

const totalRevenueDisplayValue = computed(() => {
  const v = totalRevenueByCurrency.value[selectedRevenueCurrency.value];
  return typeof v === 'number' ? v : 0;
});

const totalRevenueIsZero = computed(() => {
  const by = totalRevenueByCurrency.value;
  return Object.keys(by).every((k) => (by[k] ?? 0) <= 0);
});

const revenueCurrencyLabel = computed(() => {
  if (selectedRevenueCurrency.value === 'sats') return 'sats';
  if (selectedRevenueCurrency.value === 'eur') return '€';
  return selectedRevenueCurrency.value.toUpperCase();
});

function formatSats(sats: number): string {
  return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(sats);
}

function formatRevenue(value: number, currency: string): string {
  if (currency === 'sats') return formatSats(Math.round(value));
  return new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
}

// ---- Range analytics ----

type AnalyticsBucket = { paid_count: number; amount_sats: number; amount_by_currency?: Record<string, number> };
type AnalyticsDay = AnalyticsBucket & { date: string; label: string; amount_by_currency: Record<string, number> };
type AnalyticsPayload = {
  from: string;
  to: string;
  can_view_stats: boolean;
  totals: AnalyticsBucket & {
    amount_by_currency: Record<string, number>;
    avg_order_sats: number;
    best_day: { date: string; paid_count: number; amount_sats: number } | null;
  };
  previous: (AnalyticsBucket & { from: string; to: string; amount_by_currency: Record<string, number> }) | null;
  series: AnalyticsDay[] | null;
  by_source: Record<string, AnalyticsBucket> | null;
  by_store: Array<{ store_id: string; name: string; paid_count: number; amount_sats: number; amount_by_currency: Record<string, number> }> | null;
};

const { preset, customFrom, customTo, range } = useDashboardPeriod();
const selectedStoreId = ref('');
const selectedSource = ref('all');
const selectedCurrency = ref('sats');
const chartMode = ref<'amount' | 'count'>('amount');

const analytics = ref<AnalyticsPayload | null>(null);
const analyticsLoading = ref(false);
const canViewStats = computed(() => analytics.value?.can_view_stats ?? false);
const statsStores = ref<Array<{ id: string; name: string }>>([]);
const showStatsUpgradeModal = ref(false);
const refreshing = ref(false);

const presetOptions = computed(() => (
  ['7d', '30d', '90d', 'this_month', 'last_month', 'this_year', 'custom'] as DashboardPeriodPreset[]
).map((value) => ({ value, label: t(`dashboard.period_${value}`) })));

const storeFilterOptions = computed(() => {
  const options = [{ label: t('dashboard.all_stores'), value: '' }];
  statsStores.value.forEach((s) => options.push({ label: s.name, value: String(s.id) }));
  return options;
});

const paymentMethodOptions = computed(() => [
  { label: t('stores.payment_method_all'), value: 'all' },
  { label: t('stores.payment_method_pos'), value: 'pos' },
  { label: t('stores.payment_method_pay_button'), value: 'pay_button' },
  { label: t('stores.payment_method_ln_address'), value: 'ln_address' },
  { label: t('stores.payment_method_tickets'), value: 'tickets' },
  { label: t('stores.payment_method_api'), value: 'api' },
  { label: t('stores.payment_method_other'), value: 'other' },
]);

const currencyOptions = computed(() => {
  const set = new Set<string>(['sats']);
  for (const key of Object.keys(analytics.value?.totals.amount_by_currency ?? {})) {
    set.add(key);
  }
  return [...set];
});

const currencyLabel = computed(() => (selectedCurrency.value === 'sats' ? 'sats' : selectedCurrency.value.toUpperCase()));

const chartModes = computed(() => [
  { value: 'amount' as const, label: t('dashboard.chart_amount') },
  { value: 'count' as const, label: t('dashboard.chart_count') },
]);

function bucketValue(bucket: AnalyticsBucket, currency: string): number {
  if (currency === 'sats') return bucket.amount_sats;
  return bucket.amount_by_currency?.[currency] ?? 0;
}

function totalForCurrency(bucket: AnalyticsBucket): number {
  return bucketValue(bucket, selectedCurrency.value);
}

const chartSeries = computed<ChartPoint[]>(() => {
  const series = analytics.value?.series ?? [];
  return series.map((day) => ({
    label: day.label,
    value: chartMode.value === 'count' ? day.paid_count : bucketValue(day, selectedCurrency.value),
  }));
});

const hasSeriesData = computed(() => (analytics.value?.totals.paid_count ?? 0) > 0 && chartSeries.value.length > 0);

function formatAmount(value: number): string {
  if (selectedCurrency.value === 'sats') return formatSats(Math.round(value));
  return new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
}

function formatChartValue(value: number): string {
  if (chartMode.value === 'count') return String(Math.round(value));
  return `${formatAmount(value)} ${currencyLabel.value}`;
}

const revenueDelta = computed(() => {
  const prev = analytics.value?.previous;
  if (!prev || !analytics.value) return undefined;
  return percentDelta(totalForCurrency(analytics.value.totals), bucketValue(prev, selectedCurrency.value));
});

const countDelta = computed(() => {
  const prev = analytics.value?.previous;
  if (!prev || !analytics.value) return undefined;
  return percentDelta(analytics.value.totals.paid_count, prev.paid_count);
});

const avgDelta = computed(() => {
  const prev = analytics.value?.previous;
  if (!prev || !analytics.value) return undefined;
  const prevAvg = prev.paid_count > 0 ? prev.amount_sats / prev.paid_count : 0;
  return percentDelta(analytics.value.totals.avg_order_sats, prevAvg);
});

/**
 * Donut shares by received sats; when nothing has a sats value (fiat-only
 * data without payment details), fall back to counts so shares still render.
 * Colors are FIXED per source entity (SOURCE_COLORS) - filters never repaint.
 */
const donutSlices = computed<DonutSlice[]>(() => {
  const bySource = analytics.value?.by_source ?? {};
  const useSats = Object.values(bySource).some((b) => b.amount_sats > 0);
  return Object.entries(bySource)
    .map(([key, bucket]) => ({
      key,
      label: t(`stores.payment_method_${key}`),
      value: useSats ? bucket.amount_sats : bucket.paid_count,
      color: SOURCE_COLORS[key] ?? CHART_PRIMARY,
    }))
    .filter((slice) => slice.value > 0);
});

const storeRanking = computed(() => {
  const stores = analytics.value?.by_store ?? [];
  const values = stores.map((s) => ({
    ...s,
    value: bucketValue(s, selectedCurrency.value),
  }));
  const max = Math.max(...values.map((s) => s.value), 0);
  return values
    .filter((s) => s.paid_count > 0)
    .slice(0, 6)
    .map((s) => ({ ...s, sharePercent: max > 0 ? Math.round((s.value / max) * 100) : 0 }));
});

async function loadAnalytics(opts?: { refresh?: boolean }) {
  analyticsLoading.value = true;
  try {
    const params: Record<string, string | number> = { from: range.value.from, to: range.value.to };
    if (selectedStoreId.value) params.store_id = selectedStoreId.value;
    if (selectedSource.value !== 'all') params.source = selectedSource.value;
    if (opts?.refresh) params.refresh = 1;
    const response = await api.get('/dashboard/analytics', { params });
    analytics.value = response.data as AnalyticsPayload;
  } catch (e) {
    console.error('Failed to load dashboard analytics', e);
  } finally {
    analyticsLoading.value = false;
  }
}

/** Store list for the filter select (names come from the stats endpoint). */
async function loadStoreList() {
  try {
    const response = await api.get('/dashboard/stats');
    statsStores.value = response.data.stores || [];
  } catch {
    statsStores.value = [];
  }
}

// ---- URL query sync (survives reload / shareable) ----

function applyQuery(): void {
  const q = route.query;
  if (isDashboardPeriodPreset(q.range)) preset.value = q.range;
  if (typeof q.from === 'string') customFrom.value = q.from;
  if (typeof q.to === 'string') customTo.value = q.to;
  if (typeof q.store === 'string') selectedStoreId.value = q.store;
  if (typeof q.source === 'string') selectedSource.value = q.source;
}

watch([preset, customFrom, customTo, selectedStoreId, selectedSource], () => {
  const query: Record<string, string> = { ...route.query } as Record<string, string>;
  query.range = preset.value;
  if (preset.value === 'custom') {
    query.from = customFrom.value;
    query.to = customTo.value;
  } else {
    delete query.from;
    delete query.to;
  }
  if (selectedStoreId.value) query.store = selectedStoreId.value; else delete query.store;
  if (selectedSource.value !== 'all') query.source = selectedSource.value; else delete query.source;
  void router.replace({ query });
  void loadAnalytics();
});

async function handleRefresh() {
  refreshing.value = true;
  try {
    await Promise.all([loadDashboardData({ refresh: true }), loadAnalytics({ refresh: true })]);
  } finally {
    refreshing.value = false;
  }
}

onMounted(() => {
  applyQuery();
  loadDashboardData();
  loadStoreList();
  loadAnalytics();
});

defineExpose({
  handleRefresh,
  refreshing,
});
</script>
