<template>
  <div v-if="loading && !app" class="flex items-center justify-center h-full bg-gray-100">
    <p class="text-gray-500">Loading app...</p>
  </div>

  <div v-else-if="store && app" class="flex h-screen bg-gray-100 overflow-hidden">
    <!-- Sidebar -->
    <StoreSidebar
      :store="store"
      :apps="allApps"
      @create-app="handleCreateApp"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
    />

    <!-- Main Content -->
    <div class="flex-1 overflow-hidden flex flex-col">
      <!-- Scrollable Content Area -->
      <div class="flex-1 overflow-y-auto">
        <slot :app="app" :store="store" />
      </div>
    </div>
  </div>

  <div v-else class="flex items-center justify-center h-full bg-gray-100">
    <p class="text-gray-500">App not found</p>
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
  // Handle create app - not needed here but required by StoreSidebar
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

