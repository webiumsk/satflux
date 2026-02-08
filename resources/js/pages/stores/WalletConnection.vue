<template>
  <div v-if="storeLoading && !store" class="flex items-center justify-center h-full bg-gray-900">
     <div class="flex flex-col items-center">
        <svg class="animate-spin h-10 w-10 text-indigo-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="text-gray-400">{{ t('stores.loading_store') }}</p>
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
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div class="mb-8">
            <h1 class="text-3xl font-bold text-white">{{ t('stores.wallet_connection') }}</h1>
            <p class="mt-2 text-sm text-gray-400">{{ t('stores.configure_wallet_connection') }} <span class="text-indigo-400 font-semibold">{{ store.name }}</span></p>
          </div>

          <div v-if="loading" class="text-center py-24">
             <svg class="animate-spin mx-auto h-12 w-12 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
             </svg>
          </div>

          <div v-else-if="error" class="bg-red-500/10 border border-red-500/20 rounded-xl p-6 mb-6">
            <div class="flex items-center">
                 <svg class="h-6 w-6 text-red-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                 <p class="text-red-400 font-medium">{{ error }}</p>
            </div>
          </div>

          <div v-else class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden">
            <div class="p-8">
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
import { useI18n } from 'vue-i18n';
import { useStoresStore } from '../../store/stores';
import { useAppsStore } from '../../store/apps';
import api from '../../services/api';
import WalletConnectionForm from '../../components/stores/WalletConnectionForm.vue';
import StoreSidebar from '../../components/stores/StoreSidebar.vue';

const { t } = useI18n();

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

function handleCreateApp() {
    router.push({ name: 'stores-apps-create', params: { id: storeId.value } });
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
