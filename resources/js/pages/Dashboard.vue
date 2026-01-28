<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-white">{{ t('dashboard.title') }}</h1>
      <p class="text-gray-400 mt-1">{{ t('dashboard.welcome_back', { name: userName }) }}</p>
    </div>
    
    <!-- Dashboard Stats / Placeholder -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <!-- Card 1 -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg relative overflow-hidden group">
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
          <div class="mt-4">
             <router-link v-if="storeCount === 0" to="/stores/create" class="text-sm font-medium text-indigo-400 hover:text-indigo-300 flex items-center">
               {{ t('dashboard.create_store') }} <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
             </router-link>
             <router-link v-else to="/stores" class="text-sm font-medium text-indigo-400 hover:text-indigo-300 flex items-center">
               {{ t('dashboard.view_stores') }} <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
             </router-link>
          </div>
        </div>
      </div>

       <!-- Card 2 -->
      <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-3xl p-8 border border-gray-700/50 shadow-2xl relative overflow-hidden group transition-all hover:scale-[1.02] duration-300">
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
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg relative overflow-hidden group">
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
    
    <!-- Other Currencies Stats -->
    <div v-if="!loading && revenueBreakdown.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <div v-for="item in revenueBreakdown" :key="item.currency" 
           class="bg-gray-800/80 backdrop-blur-sm rounded-2xl p-5 border border-gray-700 shadow-xl relative overflow-hidden group transition-all hover:border-gray-600">
        <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:opacity-[0.07] transition-opacity">
           <svg class="h-20 w-20 text-orange-500" xmlns="http://www.w3.org/2000/svg" shape-rendering="geometricPrecision" text-rendering="geometricPrecision" image-rendering="optimizeQuality" fill-rule="evenodd" clip-rule="evenodd" viewBox="0 0 512 512.001">
            <path fill="currentColor" d="M256 0c141.385 0 256 114.615 256 256 0 141.386-114.615 256.001-256 256.001S0 397.386 0 256C0 114.615 114.615 0 256 0zm84.612 201.62c-3.88-31.345-29.619-40.265-62.659-42.711l-.517-27.705-25.587.077 1.033 26.815-20.994.354-.33-27.003-26.29.266 1.41 28.22-16.4.628-35.288.417 1.158 26.805 17.994-.304c10.59.175 14.582 6.637 14.495 11.934 1.162 41.42 1.777 70.56 2.62 113.775-.464 3.892-2.146 8.86-9.739 8.637l-17.994.302-4.602 30.607 50.985-.858 1.596 28.923 24.696-.592-.518-27.707 19.399-.679 1.222 27.518 25.587-.077-.706-28.41c42.828-3.191 72.804-14.988 75.663-54.919 1.929-32.15-13.13-46.189-37.927-51.596 14.518-7.655 23.639-21.398 21.693-42.717zm-33.774 90.39c-.008 30.881-53.854 29.493-71.146 29.608l-.77-56.279c17.291-.115 71.363-6.318 71.916 26.671zm-14.386-78.993c1.018 29.102-43.315 26.671-58.312 26.922l-.858-50.983c14.997-.253 59.556-5.415 59.17 24.061zM256.002 48.49c114.604 0 207.51 92.906 207.51 207.513 0 114.604-92.906 207.509-207.51 207.509-114.606 0-207.512-92.905-207.512-207.509 0-114.607 92.906-207.513 207.512-207.513z"></path>
          </svg>
        </div>
        <div class="relative z-10">
          <p class="text-xs font-black text-gray-500 uppercase tracking-widest mb-1">{{ t('dashboard.total_revenue') }} ({{ item.currency }})</p>
          <div class="flex items-baseline gap-2">
            <h3 class="text-2xl font-black text-white tracking-tighter">{{ formatCurrencyValue(item.amount, item.currency) }}</h3>
            <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">{{ item.currency }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions / Placeholder -->
    <div v-if="storeCount === 0" class="bg-gray-800 rounded-xl border border-gray-700 shadow-lg p-8 text-center">
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
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../store/auth';
import api from '../services/api';

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
const revenueBreakdown = ref<Array<{ currency: string; amount: number }>>([]);

async function loadDashboardData() {
  loading.value = true;
  try {
    const response = await api.get('/dashboard');
    storeCount.value = response.data.store_count || 0;
    totalRevenue.value = response.data.total_revenue || 0;
    revenueBreakdown.value = response.data.revenue_breakdown || [];
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

function formatCurrencyValue(amount: number, currency: string): string {
  const isFiat = ['USD', 'EUR', 'CZK', 'GBP', 'PLN'].includes(currency);
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: isFiat ? 2 : 0,
    maximumFractionDigits: isFiat ? 2 : 0,
  }).format(amount);
}

onMounted(() => {
  loadDashboardData();
});
</script>
