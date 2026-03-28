<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8 flex flex-row items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-white">{{ t('dashboard.title') }}</h1>
        <p class="text-gray-400 mt-1">{{ t('dashboard.welcome_back', { name: userName }) }}</p>
      </div>
      <button
        type="button"
        :disabled="refreshing"
        :title="t('dashboard.refresh_stats')"
        class="p-2 rounded-lg border border-gray-600 bg-gray-800 text-gray-400 hover:text-white hover:border-indigo-500/50 hover:bg-gray-750 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        @click="handleRefresh"
      >
        <svg class="w-5 h-5" :class="{ 'animate-spin': refreshing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
      </button>
    </div>
    
    <!-- Dashboard Stats / Placeholder -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <!-- Card 1: Active stores (whole card clickable) -->
      <router-link
        :to="storeCount === 0 ? '/stores/create' : '/stores'"
        class="group block bg-gray-800 hover:bg-gray-750 border border-gray-700 hover:border-indigo-500/50 rounded-2xl p-6 transition-all duration-300 hover:shadow-2xl hover:shadow-indigo-900/10 relative overflow-hidden"
      >
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/5 to-purple-600/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
           <svg
                class="w-24 h-24 text-indigo-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                />
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

      <!-- Card 2 -->
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

      <!-- Card 3 -->
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

    <!-- All stores stats (visible to all; data only for Pro + admin/support) -->
    <div class="bg-gray-800/50 backdrop-blur-md border border-gray-700 rounded-2xl p-6 mb-8">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-2 flex-wrap">
          <h2 class="text-xl font-bold text-white">{{ t('dashboard.all_stores_stats') }}</h2>
          <button
            v-if="!canViewStats"
            type="button"
            @click="showStatsUpgradeModal = true"
            class="text-xs px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-400 border border-amber-500/30 hover:bg-amber-500/30 transition-colors"
          >
            {{ t('stores.available_in_pro') }}
          </button>
        </div>
        <div v-if="statsStores.length > 0" class="flex flex-wrap items-center gap-3">
          <label class="text-sm font-medium text-gray-400">{{ t('dashboard.filter_by_store') }}</label>
          <Select
            v-model="selectedStoreId"
            :options="storeFilterOptions"
            class="min-w-[180px]"
            @change="onStatsFilterChange"
          />
          <label class="text-sm font-medium text-gray-400">{{ t('stores.filter_by_payment_method') }}</label>
          <Select
            v-model="selectedStatsSource"
            :options="paymentMethodOptions"
            :placeholder="t('stores.payment_method_all')"
            class="min-w-[180px]"
            @change="onStatsFilterChange"
          />
        </div>
      </div>
      <div v-if="!canViewStats" class="py-12 text-center rounded-xl bg-gray-900/40 border border-gray-700/50 border-dashed">
        <p class="text-gray-400">{{ t('dashboard.upgrade_to_see_stats') }}</p>
      </div>
      <div v-else-if="statsLoading" class="py-12 flex justify-center">
        <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>
      <div v-else class="space-y-6">
        <div class="flex items-baseline gap-2">
          <span class="text-3xl font-black text-white tracking-tighter">{{ statsTotal }}</span>
          <span class="text-sm font-bold text-gray-500 uppercase tracking-widest">{{ statsPeriod === '1W' ? t('dashboard.stats_7d') : t('dashboard.stats_30d') }}</span>
        </div>
        <div class="flex gap-2 mb-4">
          <button
            v-for="p in ['1W', '1M']"
            :key="p"
            @click="statsPeriod = p"
            :class="[
              'px-4 py-2 text-xs font-bold rounded-lg transition-all',
              statsPeriod === p ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-white hover:bg-white/5'
            ]"
          >
            {{ p }}
          </button>
        </div>
        <div v-if="statsChartData.length > 0" class="relative h-64 w-full">
          <svg class="w-full h-full overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none">
            <defs>
              <linearGradient id="dashboardChartGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="#4f46e5" stop-opacity="0.3" />
                <stop offset="100%" stop-color="#4f46e5" stop-opacity="0" />
              </linearGradient>
            </defs>
            <g v-for="(item, index) in statsChartData" :key="index">
              <rect
                :x="statsGetX(index) - (90 / statsChartData.length / 2)"
                :y="statsGetY(item.count)"
                :width="90 / statsChartData.length"
                :height="100 - statsGetY(item.count)"
                fill="url(#dashboardChartGrad)"
                rx="2"
                class="transition-all duration-300"
              />
            </g>
          </svg>
        </div>
        <div v-else class="py-8 text-center text-gray-500 rounded-xl bg-gray-900/40 border border-gray-700/50 border-dashed">
          {{ t('stores.no_sales_data') }}
        </div>
      </div>
    </div>

    <UpgradeModal
      :show="showStatsUpgradeModal"
      :message="t('dashboard.stats_available_in_pro_message')"
      @close="showStatsUpgradeModal = false"
    />
    
    <!-- Quick Actions / Placeholder -->
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
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../store/auth';
import api from '../services/api';
import Select from '../components/ui/Select.vue';
import UpgradeModal from '../components/stores/UpgradeModal.vue';

const { t } = useI18n();

const authStore = useAuthStore();

const userName = computed(() => {
  if (authStore.user?.email) {
    return authStore.user.email.split('@')[0];
  }
  return 'User';
});

const loading = ref(true);
const storeCount = ref(0);
const totalRevenue = ref(0);
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
    totalRevenue.value = response.data.total_revenue || 0;
    totalRevenueByCurrency.value = response.data.total_revenue_by_currency || { sats: 0 };
    availableRevenueCurrencies.value = response.data.available_revenue_currencies || ['sats'];
    if (!availableRevenueCurrencies.value.includes('sats')) {
      availableRevenueCurrencies.value = ['sats', ...availableRevenueCurrencies.value];
    }
    btcpayPing.value = response.data.btcpay_ping ?? null;
  } catch (error: any) {
    console.error('Failed to load dashboard data:', error);
  } finally {
    loading.value = false;
  }
}

const totalRevenueDisplayValue = computed(() => {
  const v = totalRevenueByCurrency.value[selectedRevenueCurrency.value];
  return typeof v === 'number' ? v : 0;
});

const totalRevenueSats = computed(() => {
  const v = totalRevenueByCurrency.value.sats;
  return typeof v === 'number' ? Math.round(v) : 0;
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
  return new Intl.NumberFormat('en-US', {
    maximumFractionDigits: 0,
  }).format(sats);
}

function formatRevenue(value: number, currency: string): string {
  if (currency === 'sats') return formatSats(Math.round(value));
  return new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
}

// All stores stats (Pro + admin/support see data)
const statsStores = ref<Array<{ id: string; name: string }>>([]);
const canViewStats = ref(false);
const statsLoading = ref(false);
const selectedStoreId = ref('');
const selectedStatsSource = ref('all');
const sales7d = ref<Array<{ date: string; count: number }>>([]);
const sales30d = ref<Array<{ date: string; count: number }>>([]);
const total7d = ref(0);
const total30d = ref(0);
const perStore = ref<PerStoreEntry[]>([]);
const statsPeriod = ref<'1W' | '1M'>('1W');
const showStatsUpgradeModal = ref(false);
const refreshing = ref(false);

async function handleRefresh() {
  refreshing.value = true;
  try {
    await Promise.all([loadDashboardData({ refresh: true }), loadStats({ refresh: true })]);
  } finally {
    refreshing.value = false;
  }
}

interface PerStoreEntry {
  store_id: string;
  name: string;
  pos: { sales_7d: Array<{ date: string; count: number }>; sales_30d: Array<{ date: string; count: number }>; total_7d: number; total_30d: number };
  invoices: { by_source: Record<string, { sales_7d: Array<{ date: string; count: number }>; sales_30d: Array<{ date: string; count: number }>; total_7d: number; total_30d: number }> };
}

const storeFilterOptions = computed(() => {
  const options = [{ label: t('dashboard.all_stores'), value: '' }];
  statsStores.value.forEach((s: { id: string | number; name: string }) => options.push({ label: s.name, value: String(s.id) }));
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

function mergeSeriesByDay(arrays: Array<Array<{ date: string; count: number }>>): Array<{ date: string; count: number }> {
  if (arrays.length === 0) return [];
  const first = arrays[0];
  const out = first.map((row) => ({ date: row.date, count: 0 }));
  arrays.forEach((arr) => arr.forEach((row, i) => { if (out[i]) out[i].count += row.count; }));
  return out;
}

const effectiveStats = computed(() => {
  const storeId = selectedStoreId.value;
  const source = selectedStatsSource.value;
  const ps = perStore.value;
  if (!ps.length) {
    return { sales_7d: sales7d.value, sales_30d: sales30d.value, total_7d: total7d.value, total_30d: total30d.value };
  }
  if (!storeId && source === 'all') {
    return { sales_7d: sales7d.value, sales_30d: sales30d.value, total_7d: total7d.value, total_30d: total30d.value };
  }
  if (storeId && source === 'all') {
    const one = ps.find((s: PerStoreEntry) => String(s.store_id) === String(storeId));
    if (!one) return { sales_7d: sales7d.value, sales_30d: sales30d.value, total_7d: total7d.value, total_30d: total30d.value };
    const bySource = one.invoices.by_source as Record<string, { sales_7d: Array<{ date: string; count: number }>; sales_30d: Array<{ date: string; count: number }>; total_7d: number; total_30d: number }>;
    const all7 = [one.pos.sales_7d, ...Object.values(bySource).map((x) => x.sales_7d)];
    const all30 = [one.pos.sales_30d, ...Object.values(bySource).map((x) => x.sales_30d)];
    const t7 = one.pos.total_7d + Object.values(bySource).reduce((sum: number, x) => sum + x.total_7d, 0);
    const t30 = one.pos.total_30d + Object.values(bySource).reduce((sum: number, x) => sum + x.total_30d, 0);
    return { sales_7d: mergeSeriesByDay(all7), sales_30d: mergeSeriesByDay(all30), total_7d: t7, total_30d: t30 };
  }
  if (!storeId && source !== 'all') {
    if (source === 'pos') {
      const all7 = ps.map((s: PerStoreEntry) => s.pos.sales_7d);
      const all30 = ps.map((s: PerStoreEntry) => s.pos.sales_30d);
      return {
        sales_7d: mergeSeriesByDay(all7),
        sales_30d: mergeSeriesByDay(all30),
        total_7d: ps.reduce((sum: number, x: PerStoreEntry) => sum + x.pos.total_7d, 0),
        total_30d: ps.reduce((sum: number, x: PerStoreEntry) => sum + x.pos.total_30d, 0),
      };
    }
    const all7 = ps.map((s: PerStoreEntry) => (s.invoices.by_source[source]?.sales_7d ?? []) as Array<{ date: string; count: number }>).filter((a: Array<unknown>) => a.length > 0);
    const all30 = ps.map((s: PerStoreEntry) => (s.invoices.by_source[source]?.sales_30d ?? []) as Array<{ date: string; count: number }>).filter((a: Array<unknown>) => a.length > 0);
    const t7 = ps.reduce((sum: number, x: PerStoreEntry) => sum + (x.invoices.by_source[source]?.total_7d ?? 0), 0);
    const t30 = ps.reduce((sum: number, x: PerStoreEntry) => sum + (x.invoices.by_source[source]?.total_30d ?? 0), 0);
    return { sales_7d: mergeSeriesByDay(all7), sales_30d: mergeSeriesByDay(all30), total_7d: t7, total_30d: t30 };
  }
  const one = ps.find((s: PerStoreEntry) => String(s.store_id) === String(storeId));
  if (!one) return { sales_7d: sales7d.value, sales_30d: sales30d.value, total_7d: total7d.value, total_30d: total30d.value };
  if (source === 'pos') {
    return { sales_7d: one.pos.sales_7d, sales_30d: one.pos.sales_30d, total_7d: one.pos.total_7d, total_30d: one.pos.total_30d };
  }
  const inv = one.invoices.by_source[source];
  if (!inv) return { sales_7d: [], sales_30d: [], total_7d: 0, total_30d: 0 };
  return { sales_7d: inv.sales_7d, sales_30d: inv.sales_30d, total_7d: inv.total_7d, total_30d: inv.total_30d };
});

const statsChartData = computed(() => (statsPeriod.value === '1W' ? effectiveStats.value.sales_7d : effectiveStats.value.sales_30d));
const statsTotal = computed(() => (statsPeriod.value === '1W' ? effectiveStats.value.total_7d : effectiveStats.value.total_30d));

function onStatsFilterChange() {
  // Reactive effectiveStats will update; no refetch needed
}
const statsMaxCount = computed(() => {
  const max = Math.max(...statsChartData.value.map((d: { date: string; count: number }) => d.count), 0);
  return max === 0 ? 10 : max * 1.2;
});
function statsGetX(index: number): number {
  if (statsChartData.value.length === 0) return 0;
  if (statsChartData.value.length === 1) return 50;
  return (index / (statsChartData.value.length - 1)) * 80 + 10;
}
function statsGetY(count: number): number {
  if (statsMaxCount.value === 0) return 100;
  return 100 - (count / statsMaxCount.value) * 100;
}

async function loadStats(opts?: { refresh?: boolean }) {
  statsLoading.value = true;
  try {
    const response = await api.get('/dashboard/stats', { params: opts?.refresh ? { refresh: 1 } : {} });
    statsStores.value = response.data.stores || [];
    canViewStats.value = !!response.data.can_view_stats;
    sales7d.value = response.data.sales_7d || [];
    sales30d.value = response.data.sales_30d || [];
    total7d.value = response.data.total_7d ?? 0;
    total30d.value = response.data.total_30d ?? 0;
    perStore.value = response.data.per_store || [];
  } catch (e) {
    console.error('Failed to load dashboard stats', e);
  } finally {
    statsLoading.value = false;
  }
}

onMounted(() => {
  loadDashboardData();
  loadStats();
});
</script>
