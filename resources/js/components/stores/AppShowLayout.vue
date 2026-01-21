<template>
  <div v-if="loading && !app" class="flex items-center justify-center h-screen bg-gray-900">
     <svg class="animate-spin h-10 w-10 text-indigo-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
     </svg>
    <p class="text-gray-400 ml-4">Loading app...</p>
  </div>

  <div v-else-if="store && app" class="flex h-screen bg-gray-900 overflow-hidden">
    <!-- Sidebar -->
    <StoreSidebar
      :store="store"
      :apps="allApps"
      @create-app="handleCreateApp"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
    />

    <!-- Main Content -->
    <div class="flex-1 overflow-hidden flex flex-col bg-gray-900 border-l border-gray-800">
      <!-- Scrollable Content Area -->
      <div class="flex-1 overflow-y-auto custom-scrollbar">
        <slot :app="app" :store="store" />
      </div>
    </div>
  </div>

  <div v-else class="flex flex-col items-center justify-center h-screen bg-gray-900">
    <div class="h-16 w-16 bg-gray-800 rounded-full flex items-center justify-center mb-4">
        <svg class="h-8 w-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
    </div>
    <p class="text-gray-400 font-medium">App not found</p>
    <button @click="$router.push(`/stores/${storeId}/apps`)" class="mt-4 text-indigo-400 hover:text-indigo-300 transition-colors text-sm">Return to Apps List</button>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAppsStore } from '../../store/apps';
import { useStoresStore } from '../../store/stores';
import StoreSidebar from './StoreSidebar.vue';

const route = useRoute();
const router = useRouter();
const appsStore = useAppsStore();
const storesStore = useStoresStore();

const storeId = computed(() => route.params.id as string);
const appId = computed(() => route.params.appId as string);
const loading = ref(false);
const store = ref<any>(null);
const app = ref<any>(null);

const allApps = computed(() => appsStore.apps);

async function loadApp() {
  loading.value = true;
  try {
    const currentStoreId = storeId.value;
    const currentAppId = appId.value;
    
    // Load store and apps first
    if (!store.value || store.value.id !== currentStoreId) {
      store.value = await storesStore.fetchStore(currentStoreId);
    }
    await appsStore.fetchApps(currentStoreId);
    
    // Then load the specific app
    app.value = await appsStore.fetchApp(currentStoreId, currentAppId);
  } catch (err: any) {
    console.error('Failed to load app:', err);
  } finally {
    loading.value = false;
  }
}

function handleCreateApp() {
  // Handle create app - likely redirect to create page
  router.push(`/stores/${storeId.value}/apps/create`);
}

function handleShowSettings() {
  router.push({ name: 'stores-show', params: { id: storeId.value }, query: { section: 'settings' } });
}

function handleShowSection(section: string) {
  router.push({ name: 'stores-show', params: { id: storeId.value }, query: { section } });
}

onMounted(() => {
  loadApp();
});

// Reload if route changes
watch([() => route.params.id, () => route.params.appId], ([newStoreId, newAppId], [oldStoreId, oldAppId]) => {
  if (newAppId && (newAppId !== oldAppId || newStoreId !== oldStoreId)) {
    // Reset app state when switching
    app.value = null;
    loadApp();
  }
}, { immediate: false });

defineExpose({
  app,
  store,
  loading,
});
</script>
