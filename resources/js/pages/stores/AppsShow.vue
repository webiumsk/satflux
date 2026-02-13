<template>
  <PointOfSaleShow v-if="appType === 'PointOfSale'" />
  <PayButtonShow v-else-if="appType === 'PaymentButton'" />
  <TicketsShow v-else-if="appType === 'Tickets'" />
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
</template>

<script setup lang="ts">
import { computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAppsStore } from '../../store/apps';
import { useStoresStore } from '../../store/stores';
import PointOfSaleShow from './PointOfSaleShow.vue';
import PayButtonShow from './PayButtonShow.vue';
import TicketsShow from './TicketsShow.vue';

const { t } = useI18n();

const route = useRoute();
const appsStore = useAppsStore();
const storesStore = useStoresStore();

const storeId = computed(() => route.params.id as string);
const appId = computed(() => route.params.appId as string);

// Get app type from store
const app = computed(() => {
  if (!storeId.value || !appId.value) return null;
  return appsStore.apps.find(a => a.id === appId.value);
});

const appType = computed(() => {
  return app.value?.app_type || null;
});

// Load app when route changes
async function loadApp() {
  if (!storeId.value || !appId.value) return;
  
  try {
    await storesStore.fetchStore(storeId.value);
    await appsStore.fetchApps(storeId.value);
  } catch (err) {
    console.error('Failed to load app:', err);
  }
}

onMounted(() => {
  loadApp();
});

watch([() => route.params.id, () => route.params.appId], () => {
  loadApp();
});
</script>
