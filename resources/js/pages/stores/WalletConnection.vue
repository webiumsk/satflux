<template>
  <div v-if="storeLoading && !store" class="flex items-center justify-center h-full bg-gray-100">
    <p class="text-gray-500">Loading store...</p>
  </div>

  <div v-else-if="store" class="flex h-full bg-gray-100">
    <!-- Sidebar -->
    <StoreSidebar
      :store="store"
      :apps="allApps"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
    />

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <div class="flex-1 overflow-y-auto">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Wallet Connection</h1>
            <p class="mt-2 text-sm text-gray-600">Configure your Lightning wallet connection for {{ store.name }}</p>
          </div>

          <div v-if="loading" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
          </div>

          <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
            <p class="text-sm text-red-800">{{ error }}</p>
          </div>

          <div v-else class="bg-white shadow rounded-lg">
            <div class="p-6">
              <WalletConnectionForm
                :store-id="storeId"
                :existing-connection="connection"
                @submitted="handleSubmitted"
                @cancel="handleCancel"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStoresStore } from '../../store/stores';
import { useAppsStore } from '../../store/apps';
import api from '../../services/api';
import WalletConnectionForm from '../../components/stores/WalletConnectionForm.vue';
import StoreSidebar from '../../components/stores/StoreSidebar.vue';

const route = useRoute();
const router = useRouter();
const storesStore = useStoresStore();
const appsStore = useAppsStore();

const storeId = computed(() => route.params.id as string);
const store = ref<any>(null);
const storeLoading = ref(true);
const loading = ref(true);
const error = ref<string | null>(null);
const connection = ref<any>(null);

const allApps = computed(() => appsStore.apps);

async function loadStore() {
    storeLoading.value = true;
    try {
        store.value = await storesStore.fetchStore(storeId.value);
    } catch (err) {
        console.error('Failed to load store:', err);
    } finally {
        storeLoading.value = false;
    }
}

async function loadConnection() {
    loading.value = true;
    error.value = null;
    try {
        const response = await api.get(`/stores/${storeId.value}/wallet-connection`);
        connection.value = response.data.data;
    } catch (err: any) {
        if (err.response?.status !== 404) {
            error.value = err.response?.data?.message || 'Failed to load wallet connection';
        }
    } finally {
        loading.value = false;
    }
}

function handleSubmitted() {
    router.push({ name: 'stores-show', params: { id: storeId.value } });
}

function handleCancel() {
    router.push({ name: 'stores-show', params: { id: storeId.value } });
}

function handleShowSettings() {
    router.push({ name: 'stores-show', params: { id: storeId.value }, query: { section: 'settings' } });
}

function handleShowSection(section: string) {
    router.push({ name: 'stores-show', params: { id: storeId.value }, query: { section } });
}

onMounted(async () => {
    await loadStore();
    await loadConnection();
    await appsStore.fetchApps(storeId.value);
});

watch(storeId, async () => {
    await loadStore();
    await loadConnection();
    await appsStore.fetchApps(storeId.value);
});
</script>


