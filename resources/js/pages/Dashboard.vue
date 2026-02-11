<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-white">{{ t('dashboard.title') }}</h1>
      <p class="text-gray-400 mt-1">{{ t('dashboard.welcome_back', { name: userName }) }}</p>
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
          <div class="flex items-baseline gap-2">
            <h3 class="text-4xl font-black text-white tracking-tighter">{{ loading ? '...' : formatSats(totalRevenue) }}</h3>
            <span class="text-sm font-bold text-gray-400 uppercase tracking-widest">sats</span>
          </div>
          <div class="mt-6 pt-4 border-t border-gray-700/50">
             <span v-if="totalRevenue === 0" class="text-xs font-bold text-gray-500 flex items-center uppercase tracking-wider">
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
          <p class="text-gray-400 text-sm font-medium mb-1">{{ t('dashboard.system_status') }}</p>
          <h3 class="text-3xl font-bold text-white flex items-center">
             <span class="flex h-3 w-3 rounded-full bg-green-500 mr-3"></span>
             {{ t('dashboard.online') }}
          </h3>
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
            type="button"
            @click="showStatsUpgradeModal = true"
            class="text-xs px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-400 border border-amber-500/30 hover:bg-amber-500/30 transition-colors"
          >
            {{ t('stores.available_in_pro') }}
          </button>
        </div>
        <div v-if="statsStores.length > 0" class="flex items-center gap-3">
          <label class="text-sm font-medium text-gray-400">{{ t('dashboard.filter_by_store') }}</label>
          <Select
            v-model="selectedStoreId"
            :options="storeFilterOptions"
            class="min-w-[180px]"
            @change="loadStats"
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

async function loadDashboardData() {
  loading.value = true;
  try {
    const response = await api.get('/dashboard');
    storeCount.value = response.data.store_count || 0;
    totalRevenue.value = response.data.total_revenue || 0;
  } catch (error: any) {
    console.error('Failed to load dashboard data:', error);
    // Keep defaults (0) on error
  } finally {
    loading.value = false;
  }
}

function formatSats(sats: number): string {
  return new Intl.NumberFormat('en-US', {
    maximumFractionDigits: 0,
  }).format(sats);
}

// All stores stats (Pro + admin/support see data)
const statsStores = ref<Array<{ id: string; name: string }>>([]);
const canViewStats = ref(false);
const statsLoading = ref(false);
const selectedStoreId = ref('');
const sales7d = ref<Array<{ date: string; count: number }>>([]);
const sales30d = ref<Array<{ date: string; count: number }>>([]);
const total7d = ref(0);
const total30d = ref(0);
const statsPeriod = ref<'1W' | '1M'>('1W');
const showStatsUpgradeModal = ref(false);

const storeFilterOptions = computed(() => {
  const options = [{ label: t('dashboard.all_stores'), value: '' }];
  statsStores.value.forEach((s: { id: string; name: string }) => options.push({ label: s.name, value: s.id }));
  return options;
});

const statsChartData = computed(() => (statsPeriod.value === '1W' ? sales7d.value : sales30d.value));
const statsTotal = computed(() => (statsPeriod.value === '1W' ? total7d.value : total30d.value));
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

async function loadStats() {
  statsLoading.value = true;
  try {
    const params: Record<string, string> = {};
    if (selectedStoreId.value) params.store_id = selectedStoreId.value;
    const response = await api.get('/dashboard/stats', { params });
    statsStores.value = response.data.stores || [];
    canViewStats.value = !!response.data.can_view_stats;
    sales7d.value = response.data.sales_7d || [];
    sales30d.value = response.data.sales_30d || [];
    total7d.value = response.data.total_7d ?? 0;
    total30d.value = response.data.total_30d ?? 0;
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
