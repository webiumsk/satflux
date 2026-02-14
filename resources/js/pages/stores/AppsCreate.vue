<template>
  <div class="flex bg-gray-900 overflow-hidden">
    <!-- Sidebar -->
    <StoreSidebar
      :store="store"
      :apps="apps"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
    />

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden bg-gray-900 border-l border-gray-800">
      <!-- Header -->
      <div class="sticky top-0 z-20 bg-gray-900/80 backdrop-blur-md border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <button @click="$router.back()" class="mr-4 text-gray-400 hover:text-white transition-colors">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
              </button>
              <div>
                <h1 class="text-2xl font-bold text-white mb-1">{{ t('apps.create_app_title') }}</h1>
                <p class="text-sm text-gray-400">{{ t('apps.create_app_description') }} <span class="text-indigo-400">{{ store?.name }}</span></p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Container -->
      <div class="flex-1 overflow-y-auto custom-scrollbar">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          
          <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden">
            <div class="p-6 sm:p-8">
              <form @submit.prevent="handleSubmit" class="space-y-6">
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">
                    {{ t('apps.app_type') }} <span class="text-red-400">*</span>
                  </label>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Point of Sale Option -->
                    <div 
                      @click="form.appType = 'PointOfSale'"
                      :class="[
                        'relative rounded-xl border-2 p-4 cursor-pointer transition-all duration-200 flex flex-col items-center text-center gap-3',
                        form.appType === 'PointOfSale' 
                          ? 'border-indigo-500 bg-indigo-500/10' 
                          : 'border-gray-700 bg-gray-900/50 hover:border-gray-600 hover:bg-gray-800'
                      ]"
                    >
                      <div :class="[
                        'p-3 rounded-full',
                        form.appType === 'PointOfSale' ? 'bg-indigo-500 text-white' : 'bg-gray-700 text-gray-400'
                      ]">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                      </div>
                      <div>
                        <h3 class="font-medium text-white mb-1">{{ t('apps.point_of_sale') }}</h3>
                        <p class="text-xs text-gray-400">{{ t('apps.point_of_sale_description') }}</p>
                      </div>
                      <div v-if="form.appType === 'PointOfSale'" class="absolute top-3 right-3 text-indigo-500">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                      </div>
                    </div>
                  </div>
                </div>

                <div>
                  <label for="appName" class="block text-sm font-medium text-gray-300 mb-1">
                    {{ t('apps.app_name') }} <span class="text-red-400">*</span>
                  </label>
                  <input
                    id="appName"
                    v-model="form.name"
                    type="text"
                    required
                    class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    :placeholder="t('apps.app_name_placeholder')"
                  />
                </div>

                <div v-if="error" class="rounded-xl bg-red-500/10 border border-red-500/20 p-4">
                   <div class="flex">
                      <svg class="h-5 w-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                      <div class="text-sm text-red-400">{{ error }}</div>
                  </div>
                </div>

                <div class="pt-4 border-t border-gray-700/50 flex items-center justify-end gap-3">
                  <router-link
                    :to="`/stores/${storeId}/apps`"
                    class="px-4 py-2 border border-gray-600 rounded-xl text-sm font-medium text-gray-300 hover:text-white hover:bg-gray-700 transition-colors"
                  >
                    {{ t('common.cancel') }}
                  </router-link>
                  <button
                    type="submit"
                    :disabled="saving || !form.name || !form.appType"
                    class="inline-flex justify-center px-6 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg shadow-indigo-600/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all hover:scale-105"
                  >
                     <svg v-if="saving" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                    {{ saving ? t('apps.creating') : t('apps.create_app') }}
                  </button>
                </div>

              </form>
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
import { useAppsStore } from '../../store/apps';
import { useStoresStore } from '../../store/stores';
import StoreSidebar from '../../components/stores/StoreSidebar.vue';
import api from '../../services/api';

const { t } = useI18n();

const route = useRoute();
const router = useRouter();
const appsStore = useAppsStore();
const storesStore = useStoresStore();

const storeId = computed(() => route.params.id as string);
const store = ref<any>(null);
const saving = ref(false);
const error = ref('');

const allApps = computed(() => appsStore.apps);
// We use 'apps' prop for Sidebar which expects the list of apps
const apps = computed(() => allApps.value);

const form = ref({
  name: '',
  appType: 'PointOfSale', // Default selection
});

async function loadStore() {
  try {
    store.value = await storesStore.fetchStore(storeId.value);
  } catch (err: any) {
    console.error('Failed to load store:', err);
  }
}

async function handleSubmit() {
  if (!form.value.name || !form.value.appType) return;
  
  saving.value = true;
  error.value = '';

  try {
    const response = await api.post(`/stores/${storeId.value}/apps`, {
      name: form.value.name,
      app_type: form.value.appType,
    });
    
    // Refresh apps list
    await appsStore.fetchApps(storeId.value);
    
    // Redirect to the new app's settings page
    const newAppId = response.data.data.id;
    router.push(`/stores/${storeId.value}/apps/${newAppId}`);
    
  } catch (err: any) {
    console.error('Failed to create app:', err);
    error.value = err.response?.data?.message || t('apps.failed_to_create');
  } finally {
    saving.value = false;
  }
}

function handleShowSettings() {
  router.push({ name: 'stores-show', params: { id: storeId.value }, query: { section: 'settings' } });
}

function handleShowSection(section: string) {
  router.push({ name: 'stores-show', params: { id: storeId.value }, query: { section } });
}

onMounted(async () => {
  // Check if type is provided in query params (e.g. from sidebar "Tickets" or "Point of Sale")
  const typeFromQuery = route.query.type as string;
  if (typeFromQuery) {
    const typeMap: Record<string, 'PointOfSale' | 'PaymentButton' | 'LightningAddress'> = {
      'PointOfSale': 'PointOfSale',
      'PaymentButton': 'PaymentButton',
      'LightningAddress': 'LightningAddress',
    };
    if (typeMap[typeFromQuery]) {
      form.value.appType = typeMap[typeFromQuery];
    }
  }

  await loadStore();
  await appsStore.fetchApps(storeId.value);
});

watch(() => route.params.id, async (newId) => {
    if (newId) {
        await loadStore();
        await appsStore.fetchApps(newId as string);
    }
});
</script>
