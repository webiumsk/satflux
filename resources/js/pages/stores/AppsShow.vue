<template>
  <!-- Wait for app to be loaded for this route to avoid "reading 'id' of undefined" when navigating -->
  <div v-if="loadingApp" class="flex items-center justify-center h-screen bg-gray-900">
    <div class="flex flex-col items-center">
      <svg class="animate-spin h-10 w-10 text-indigo-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <p class="text-gray-400">{{ t('common.loading') }}</p>
    </div>
  </div>
  <!-- SPA: wrap in layout with sidebar so Tickets/PoS/PayButton all have the store sidebar -->
  <div v-else-if="storeForRoute && app" class="flex bg-gray-900 overflow-hidden min-h-screen">
    <StoreSidebar
      :store="storeForRoute"
      :apps="appsStore.apps"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
    />
    <div class="flex-1 overflow-hidden flex flex-col bg-gray-900 border-l border-gray-800">
      <PointOfSaleShow v-if="appType === 'PointOfSale'" />
      <PayButtonShow v-else-if="appType === 'PaymentButton'" />
      <TicketsShow v-else-if="appType === 'Tickets'" :store="storeForRoute" :app="app" />
      <div v-else-if="appType === 'Crowdfund'" class="flex items-center justify-center h-full bg-gray-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div class="bg-gray-800 shadow rounded-lg p-6 border border-gray-700">
            <h1 class="text-3xl font-bold text-white mb-4">{{ t('apps.crowdfund_not_available') }}</h1>
            <p class="text-sm text-gray-400">Type: {{ appType }}</p>
            <p class="mt-4 text-gray-300">{{ t('apps.crowdfund_unavailable_description') }}</p>
          </div>
        </div>
      </div>
      <div v-else class="flex items-center justify-center h-full bg-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div class="bg-white shadow rounded-lg p-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ t('apps.app_type_not_supported') }}</h1>
            <p class="text-sm text-gray-500">Type: {{ appType }}</p>
            <p class="mt-4 text-gray-600">{{ t('apps.app_type_not_supported_description') }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div v-else class="flex items-center justify-center h-full bg-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="bg-white shadow rounded-lg p-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ t('apps.app_type_not_supported') }}</h1>
        <p class="text-sm text-gray-500">Type: {{ appType }}</p>
        <p class="mt-4 text-gray-600">{{ t('apps.app_type_not_supported_description') }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAppsStore } from '../../store/apps';
import { useStoresStore } from '../../store/stores';
import StoreSidebar from '../../components/stores/StoreSidebar.vue';
import PointOfSaleShow from './PointOfSaleShow.vue';
import PayButtonShow from './PayButtonShow.vue';
import TicketsShow from './TicketsShow.vue';

const { t } = useI18n();

const route = useRoute();
const router = useRouter();
const appsStore = useAppsStore();
const storesStore = useStoresStore();

const storeId = computed(() => route.params.id as string);
const appId = computed(() => route.params.appId as string);
const loadingApp = ref(true);

// Store only valid when it matches current route (avoids stale store when navigating)
const storeForRoute = computed(() => {
  const id = storeId.value;
  const current = storesStore.currentStore;
  if (!id || !current || current.id !== id) return null;
  return current;
});

// App only valid when it belongs to current store's apps (avoids wrong app when navigating)
const app = computed(() => {
  if (!storeId.value || !appId.value) return null;
  const apps = appsStore.apps;
  // Only use app if store is loaded for this route (apps are for this store)
  if (!storeForRoute.value) return null;
  return apps.find((a: any) => a.id === appId.value) ?? null;
});

const appType = computed(() => app.value?.app_type ?? null);

async function loadApp() {
  if (!storeId.value || !appId.value) {
    loadingApp.value = false;
    return;
  }
  loadingApp.value = true;
  try {
    await storesStore.fetchStore(storeId.value);
    await appsStore.fetchApps(storeId.value);
  } catch (err) {
    console.error('Failed to load app:', err);
  } finally {
    loadingApp.value = false;
  }
}

onMounted(() => {
  loadApp();
});

watch([() => route.params.id, () => route.params.appId], () => {
  loadApp();
});

function handleShowSettings() {
  if (!storeForRoute.value) return;
  router.push({ name: 'stores-show', params: { id: storeForRoute.value.id }, query: { section: 'settings' } });
}

function handleShowSection(section: string) {
  if (!storeForRoute.value) return;
  router.push({ name: 'stores-show', params: { id: storeForRoute.value.id }, query: { section } });
}
</script>
