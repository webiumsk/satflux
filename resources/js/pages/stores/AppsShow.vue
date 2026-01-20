<template>
  <PointOfSaleShow v-if="appType === 'PointOfSale'" />
  <CrowdfundShow v-else-if="appType === 'Crowdfund'" />
  <div v-else class="flex items-center justify-center h-full bg-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="bg-white shadow rounded-lg p-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">App Type Not Supported</h1>
        <p class="text-sm text-gray-500">Type: {{ appType }}</p>
        <p class="mt-4 text-gray-600">Settings for this app type are not yet implemented.</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAppsStore } from '../../store/apps';
import { useStoresStore } from '../../store/stores';
import PointOfSaleShow from './PointOfSaleShow.vue';
import CrowdfundShow from './CrowdfundShow.vue';

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
