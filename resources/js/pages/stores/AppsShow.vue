<template>
  <!-- Wait for app to be loaded for this route to avoid "reading 'id' of undefined" when navigating -->
  <AppScrollPane v-if="loadingApp" show-footer class="bg-gray-900">
    <div class="min-h-full flex items-center justify-center bg-gray-900">
      <div class="flex flex-col items-center">
        <svg class="animate-spin h-10 w-10 text-indigo-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="text-gray-400">{{ t('common.loading') }}</p>
      </div>
    </div>
  </AppScrollPane>
  <!-- SPA: wrap in layout with sidebar so Tickets/PoS/PayButton all have the store sidebar -->
  <div v-else-if="storeForRoute && app" class="flex min-h-0 flex-1 overflow-hidden bg-gray-900">
    <StoreSidebar
      :store="storeForRoute"
      :apps="appsStore.apps"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
    />
    <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden border-l border-gray-800 bg-gray-900">
      <PointOfSaleShow v-if="appType === 'PointOfSale'" :store="storeForRoute" :app="app" />
      <PayButtonShow v-else-if="appType === 'PaymentButton'" :store="storeForRoute" :app="app" />
      <TicketsShow v-else-if="appType === 'Tickets'" :store="storeForRoute" :app="app" />
      <CrowdfundShow v-else-if="appType === 'Crowdfund'" :store="storeForRoute" :app="app" />
      <AppScrollPane v-else show-footer class="bg-gray-900">
        <div class="min-h-full flex items-center justify-center bg-gray-100">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white shadow rounded-lg p-6">
              <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ t('apps.app_type_not_supported') }}</h1>
              <p class="text-sm text-gray-500">{{ t('apps.app_type_value', { type: appType }) }}</p>
              <p class="mt-4 text-gray-600">{{ t('apps.app_type_not_supported_description') }}</p>
            </div>
          </div>
        </div>
      </AppScrollPane>
    </div>
  </div>
  <AppScrollPane v-else show-footer class="bg-gray-900">
    <div class="min-h-full flex items-center justify-center bg-gray-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white shadow rounded-lg p-6">
          <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ t('apps.app_type_not_supported') }}</h1>
          <p class="text-sm text-gray-500">{{ t('apps.app_type_value', { type: appType }) }}</p>
          <p class="mt-4 text-gray-600">{{ t('apps.app_type_not_supported_description') }}</p>
        </div>
      </div>
    </div>
  </AppScrollPane>
</template>

<script setup lang="ts">
import AppScrollPane from '../../components/layout/AppScrollPane.vue';
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue';
import type { AppRef } from '../../types/btcpayApps';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAppsStore } from '../../store/apps';
import { useStoresStore } from '../../store/stores';
import { useAuthStore } from '../../store/auth';
import { useGuestUpgradeModal } from '../../composables/useGuestUpgradeModal';
import StoreSidebar from '../../components/stores/StoreSidebar.vue';
import PointOfSaleShow from './PointOfSaleShow.vue';
import PayButtonShow from './PayButtonShow.vue';
import TicketsShow from './TicketsShow.vue';
import CrowdfundShow from './CrowdfundShow.vue';

const { t } = useI18n();

const route = useRoute();
const router = useRouter();
const appsStore = useAppsStore();
const storesStore = useStoresStore();
const authStore = useAuthStore();
const { openGuestUpgradeModal } = useGuestUpgradeModal();

const storeId = computed(() => route.params.id as string);
const appId = computed(() => route.params.appId as string);
const loadingApp = ref(true);
const loadedApp = ref<AppRef | null>(null);
const loadedAppStoreId = ref('');
let loadRequestId = 0;

// Store only valid when it matches current route (avoids stale store when navigating)
const storeForRoute = computed(() => {
  const id = storeId.value;
  const current = storesStore.currentStore;
  if (!id || !current || current.id !== id) return null;
  return current;
});

// Detail responses belong to the route that requested them; list entries are only sidebar metadata.
const app = computed(() => {
  if (!storeForRoute.value || loadedAppStoreId.value !== storeId.value) return null;
  return loadedApp.value?.id === appId.value ? loadedApp.value : null;
});

const appType = computed(() => app.value?.app_type ?? null);

/** Load the selected app detail and ignore responses superseded by another route. */
async function loadApp() {
  const requestId = ++loadRequestId;
  const requestedStoreId = storeId.value;
  const requestedAppId = appId.value;
  /** Verify that an awaited response still belongs to the active route. */
  const isCurrentRequest = () => requestId === loadRequestId
    && storeId.value === requestedStoreId && appId.value === requestedAppId;
  loadedApp.value = null;
  loadedAppStoreId.value = '';
  if (!requestedStoreId || !requestedAppId) {
    loadingApp.value = false;
    return;
  }
  loadingApp.value = true;
  try {
    await storesStore.fetchStore(requestedStoreId);
    if (!isCurrentRequest()) return;
    await appsStore.fetchApps(requestedStoreId);
    if (!isCurrentRequest()) return;
    const currentApp = await appsStore.fetchApp(requestedStoreId, requestedAppId);
    if (!isCurrentRequest() || currentApp?.id !== requestedAppId) return;
    if (authStore.user?.is_guest && currentApp.app_type !== 'PointOfSale') {
      openGuestUpgradeModal('apps.title');
      router.replace({ name: 'stores-apps', params: { id: requestedStoreId } });
      return;
    }
    if (currentApp.app_type === 'Tickets') {
      router.replace({ name: 'stores-tickets', params: { id: requestedStoreId } });
      return;
    }
    loadedApp.value = currentApp;
    loadedAppStoreId.value = requestedStoreId;
  } catch (err) {
    if (isCurrentRequest()) console.error('Failed to load app:', err);
  } finally {
    if (isCurrentRequest()) loadingApp.value = false;
  }
}

onMounted(() => {
  loadApp();
});

watch([() => route.params.id, () => route.params.appId], () => {
  loadApp();
});

onBeforeUnmount(() => {
  loadRequestId++;
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
