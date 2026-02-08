<template>
  <div v-if="loading && !store" class="flex items-center justify-center h-full bg-gray-900">
    <div class="flex flex-col items-center">
        <svg class="animate-spin h-10 w-10 text-indigo-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="text-gray-400">{{ $t('stores.loading_store') }}</p>
    </div>
  </div>

  <div v-else-if="store" class="flex bg-gray-900 overflow-hidden">
    <!-- Sidebar -->
    <StoreSidebar
      :store="store"
      :apps="allApps"
      @create-app="handleCreateApp"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
    />

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden bg-gray-900 border-l border-gray-800">
      <div class="flex-1 overflow-y-auto">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Settings View -->
        <div v-if="showSettings" class="max-w-4xl mx-auto">
           <StoreSettings :store="store" @update-store="handleStoreUpdate" />
        </div>

        <!-- Invoices View -->
        <div v-else-if="showInvoices" class="max-w-7xl">
           <StoreInvoices :store="store" />
        </div>

        <!-- Exports View -->
        <div v-else-if="showExports" class="max-w-7xl">
           <StoreExports :store="store" />
        </div>

        <!-- Dashboard View -->
        <div v-else class="space-y-8">
          <!-- Store Info Card - Always visible -->
          <div class="bg-gradient-to-br from-gray-800 to-gray-900 shadow-xl rounded-2xl border border-gray-700 p-6 relative overflow-hidden">
            <!-- Decorative Background -->
            <div class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob"></div>

            <div class="flex flex-col md:flex-row items-center justify-between mb-6 relative z-10">
              <div class="flex items-center mb-4 md:mb-0">
                  <div v-if="store.logo_url" class="mr-6 bg-white/5 p-2 rounded-xl backdrop-blur-sm border border-white/10">
                    <img :src="store.logo_url" :alt="`${store.name} logo`" class="h-20 w-20 object-contain" />
                  </div>
                  <div v-else class="mr-6 h-20 w-20 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-3xl font-bold shadow-lg shadow-indigo-600/20">
                    {{ store.name.charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <h1 class="text-3xl font-bold text-white mb-1">{{ store.name }}</h1>
                    <p class="text-sm text-gray-400">Store ID: <span class="font-mono text-gray-500">{{ store.id }}</span></p>
                  </div>
              </div>
              
              <div class="flex flex-col items-end gap-2">
                 <div class="flex items-center bg-gray-800/80 rounded-lg px-3 py-1.5 border border-gray-700">
                    <span class="text-xs text-gray-500 uppercase tracking-wider mr-2 font-semibold">Wallet Type</span>
                    <span class="text-sm font-bold text-indigo-400">
                       {{ store.wallet_type === 'blink' ? 'Blink' : store.wallet_type === 'aqua_boltz' ? 'Aqua (Boltz)' : 'Not configured' }}
                    </span>
                 </div>
                 <div class="flex items-center bg-gray-800/80 rounded-lg px-3 py-1.5 border border-gray-700">
                    <span class="text-xs text-gray-500 uppercase tracking-wider mr-2 font-semibold">Connection</span>
                    <span class="text-sm font-bold" :class="getWalletConnectionStatusClass(store)">
                       {{ getWalletConnectionStatusText(store) }}
                    </span>
                 </div>
              </div>
            </div>
          </div>

          <!-- Loading state for dashboard -->
          <div v-if="loading && !dashboard" class="flex justify-center py-12">
              <svg class="animate-spin h-10 w-10 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
          </div>

          <!-- Dashboard content -->
          <div v-else-if="dashboard" class="space-y-8">
            <!-- Store Status Banner -->
            <!-- Connected -->
            <div v-if="store.wallet_connection?.status === 'connected'" class="bg-green-500/10 border border-green-500/20 rounded-xl p-4 flex items-start">
              <svg class="h-5 w-5 text-green-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
              <div>
                <h3 class="text-sm font-medium text-green-400">Connection Active</h3>
                <p class="text-sm text-green-500/80 mt-1">This store is ready to accept transactions. Good job!</p>
              </div>
            </div>
            
            <!-- Needs Support -->
            <div v-else-if="store.wallet_connection?.status === 'needs_support'" class="bg-blue-500/10 border border-blue-500/20 rounded-xl p-4 flex items-start">
              <svg class="h-5 w-5 text-blue-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
               <div>
                <h3 class="text-sm font-medium text-blue-400">Configuration in Progress</h3>
                <p class="text-sm text-blue-500/80 mt-1">Your wallet connection is being configured by our support team. You'll be notified when it's ready.</p>
              </div>
            </div>
            
            <!-- Pending -->
            <div v-else-if="store.wallet_connection?.status === 'pending'" class="bg-yellow-500/10 border border-yellow-500/20 rounded-xl p-4 flex items-start">
              <svg class="h-5 w-5 text-yellow-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
               <div>
                <h3 class="text-sm font-medium text-yellow-400">Connection Pending</h3>
                <p class="text-sm text-yellow-500/80 mt-1">Please wait while we process your wallet connection request.</p>
              </div>
            </div>
            
            <!-- Not Configured -->
            <div v-else class="bg-red-500/10 border border-red-500/20 rounded-xl p-4 flex items-start">
              <svg class="h-5 w-5 text-red-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>
               <div>
                <h3 class="text-sm font-medium text-red-400">Wallet Not Configured</h3>
                <p class="text-sm text-red-500/80 mt-1">To start accepting payments, please configure your wallet connection in <router-link :to="`/stores/${store.id}/wallet-connection`" class="underline hover:text-red-300">Wallet Connection</router-link>.</p>
              </div>
            </div>

            <!-- Dashboard Statistics -->
            <DashboardStats
              v-if="dashboard"
              :stats="{
                paid_invoices_last_7d: dashboard.paid_invoices_last_7d || 0,
                total_invoices: dashboard.total_invoices || 0
              }"
              @view-invoices="handleViewInvoices"
            />

            <!-- Sales Chart and Top Items -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
              <SalesChart
                v-if="dashboard.sales"
                :store-name="store.name"
                :sales7d="dashboard.sales?.last_7_days || []"
                :sales30d="dashboard.sales?.last_30_days || []"
                :total-sales7d="dashboard.sales?.total_7d || 0"
                :total-sales30d="dashboard.sales?.total_30d || 0"
                :manage-url="`/stores/${store.id}/apps`"
              />
              <TopItems :items="dashboard.top_items || []" />
            </div>

            <!-- Recent Invoices -->
            <div v-if="dashboard.recent_invoices.length > 0">
              <RecentInvoices
                :invoices="dashboard.recent_invoices"
                @view-all="handleViewAllInvoices"
                @view-invoice="handleViewInvoice"
              />
            </div>

            <!-- Empty State for Invoices -->
            <div v-else-if="!loading" class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 p-12 text-center">
              <div class="w-16 h-16 bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
                 <svg class="h-8 w-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
              </div>
              <h3 class="text-lg font-medium text-white mb-2">No invoices yet</h3>
              <p class="text-gray-400 mb-6">Create your first invoice to start accepting payments.</p>
              <!-- <button class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-500">
                Create Invoice
              </button> -->
            </div>
          </div>
          
          <!-- Dashboard load failed -->
          <div v-else-if="!loading" class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 p-12 text-center">
            <p class="text-red-400 mb-2 font-medium">Failed to load dashboard data.</p>
            <button 
              @click="storesStore.fetchDashboard(store.id)" 
              class="mt-4 px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors"
            >
              Retry
            </button>
          </div>
        </div>
      </div>
      </div>
    </div>
  </div>

  <div v-else class="flex items-center justify-center h-full bg-gray-900">
    <div class="text-center">
        <h2 class="text-xl font-bold text-white mb-2">Store not found</h2>
        <router-link to="/stores" class="text-indigo-400 hover:text-indigo-300">Return to Stores</router-link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStoresStore } from '../../store/stores';
import { useAppsStore } from '../../store/apps';
import StoreSidebar from '../../components/stores/StoreSidebar.vue';
import DashboardStats from '../../components/stores/DashboardStats.vue';
import RecentInvoices from '../../components/stores/RecentInvoices.vue';
import SalesChart from '../../components/stores/SalesChart.vue';
import TopItems from '../../components/stores/TopItems.vue';
import StoreSettings from '../../components/stores/StoreSettings.vue';
import StoreInvoices from '../../components/stores/StoreInvoices.vue';
import StoreExports from '../../components/stores/StoreExports.vue';

const route = useRoute();
const router = useRouter();
const storesStore = useStoresStore();
const appsStore = useAppsStore();

const loading = ref(false);
const store = ref<any>(null);
const showSettings = ref(false);
const showInvoices = ref(false);
const showExports = ref(false);

const dashboard = computed(() => storesStore.dashboard);
const allApps = computed(() => appsStore.apps);

// Watch route params for store changes (when switching stores in dropdown)
watch(() => route.params.id, async (newStoreId, oldStoreId) => {
  if (newStoreId && newStoreId !== oldStoreId) {
    loading.value = true;
    try {
      store.value = await storesStore.fetchStore(newStoreId as string);
      
      // Load appropriate view based on query
      const currSection = route.query.section as string;
      updateSection(currSection);

      if (!currSection) {
        await storesStore.fetchDashboard(newStoreId as string);
        await appsStore.fetchApps(newStoreId as string);
      }
    } finally {
      loading.value = false;
    }
  }
}, { immediate: false });

// Watch route query for section changes
watch(() => route.query.section, async (newSection, oldSection) => {
  if (newSection === oldSection) return;
  updateSection(newSection as string);
  
  // If switching to dashboard (empty section), ensure data is loaded
  if (!newSection && store.value) {
     const storeId = route.params.id as string;
     loading.value = true;
     try {
       await storesStore.fetchDashboard(storeId);
       await appsStore.fetchApps(storeId);
     } finally {
       loading.value = false;
     }
  }
});

function updateSection(section: string) {
  showSettings.value = section === 'settings';
  showInvoices.value = section === 'invoices';
  showExports.value = section === 'exports';
}

let pollInterval: ReturnType<typeof setInterval> | null = null;

onMounted(async () => {
  const storeId = route.params.id as string;
  loading.value = true;
  try {
    store.value = await storesStore.fetchStore(storeId);
    
    const section = route.query.section as string;
    updateSection(section);
    
    if (!section) {
      await storesStore.fetchDashboard(storeId);
      await appsStore.fetchApps(storeId);
    }

    // Poll store when wallet connection is in "needs_support" so merchant sees update when bot completes
    if (store.value?.wallet_connection?.status === 'needs_support') {
      pollInterval = setInterval(async () => {
        const updated = await storesStore.fetchStore(storeId);
        store.value = updated;
        if (updated?.wallet_connection?.status !== 'needs_support' && pollInterval) {
          clearInterval(pollInterval);
          pollInterval = null;
          if (!route.query.section) {
            await storesStore.fetchDashboard(storeId);
            await appsStore.fetchApps(storeId);
          }
        }
      }, 15000);
    }
  } catch (e) {
      console.error("Error loading store", e);
  } finally {
    loading.value = false;
  }
});

onUnmounted(() => {
  if (pollInterval) {
    clearInterval(pollInterval);
  }
});

function handleCreateApp() {
  router.push({ name: 'stores-apps-create', params: { id: store.value.id } });
}

function handleShowSettings() {
  handleShowSection('settings');
}

function handleShowSection(section: string) {
  updateSection(section);
  router.push({ name: 'stores-show', params: { id: store.value.id }, query: { section } });
}

function handleViewInvoices(filters?: any) {
  router.push({ name: 'stores-show', params: { id: store.value.id }, query: { section: 'invoices', ...filters } });
}

function handleViewAllInvoices() {
  handleViewInvoices();
}

function handleViewInvoice(invoice: any) {
   // Placeholder for future invoice detail view
   console.log("View Invoice", invoice);
}

// Handler for when store is updated in Settings component
async function handleStoreUpdate() {
   const storeId = route.params.id as string;
   store.value = await storesStore.fetchStore(storeId);
}

function getWalletConnectionStatusClass(store: any): string {
  if (!store.wallet_connection) {
    return 'text-red-400';
  }
  
  const status = store.wallet_connection.status;
  switch (status) {
    case 'connected':
      return 'text-green-400';
    case 'needs_support':
      return 'text-blue-400';
    case 'pending':
    default:
      return 'text-gray-400';
  }
}

function getWalletConnectionStatusText(store: any): string {
  if (!store.wallet_connection) {
    return 'Not config';
  }
  
  const status = store.wallet_connection.status;
  switch (status) {
    case 'connected':
      return 'Connected';
    case 'needs_support':
      return 'Support';
    case 'pending':
      return 'Pending';
    default:
      return 'Unknown';
  }
}
</script>
