<template>
  <div class="flex bg-gray-900 overflow-hidden">
    <!-- Sidebar -->
    <StoreSidebar
      :store="store"
      :apps="allApps"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
    />

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden bg-gray-900 border-l border-gray-800">
      <!-- Header -->
      <div class="sticky top-0 z-20 bg-gray-900/80 backdrop-blur-md border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h1 class="text-2xl font-bold text-white mb-1">{{ showArchived ? t('apps.archived_apps_title') : t('apps.title') }}</h1>
              <p class="text-sm text-gray-400">{{ showArchived ? t('apps.archived_apps_description') : t('apps.manage_apps') }} <span class="text-indigo-400">{{ store?.name || 'this store' }}</span></p>
            </div>
            <div class="flex items-center gap-3">
              <router-link
                v-if="!showArchived"
                :to="`/stores/${storeId}/apps/create`"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg shadow-indigo-600/20 transition-all hover:scale-105"
              >
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                {{ t('apps.create_app') }}
              </router-link>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Container -->
      <div class="flex-1 overflow-y-auto custom-scrollbar">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <ArchivedStoreBanner :store="store" />

          <!-- Loading State -->
          <div v-if="loading" class="flex flex-col items-center justify-center py-24">
             <svg class="animate-spin h-10 w-10 text-indigo-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
             </svg>
             <p class="text-gray-400">{{ t('apps.loading_apps') }}</p>
          </div>

          <!-- Empty State -->
          <div v-else-if="apps.length === 0" class="bg-gray-800/50 border border-gray-700 rounded-2xl p-12 text-center">
             <div class="mx-auto h-16 w-16 text-gray-600 bg-gray-800 rounded-full flex items-center justify-center mb-4">
                <svg class="h-8 w-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
             </div>
            <template v-if="showArchived">
              <h3 class="text-lg font-medium text-white mb-2">{{ t('apps.no_archived_apps') }}</h3>
              <p class="text-gray-400 mb-6 max-w-sm mx-auto">{{ t('apps.no_archived_apps_description') }}</p>
              <router-link
                :to="`/stores/${storeId}/apps`"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg shadow-indigo-600/20 transition-all hover:scale-105"
              >
                {{ t('apps.back_to_apps') }}
              </router-link>
            </template>
            <template v-else>
              <h3 class="text-lg font-medium text-white mb-2">{{ t('apps.no_apps_yet') }}</h3>
              <p class="text-gray-400 mb-6 max-w-sm mx-auto">{{ t('apps.no_apps_description') }}</p>
              <router-link
                :to="`/stores/${storeId}/apps/create`"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg shadow-indigo-600/20 transition-all hover:scale-105"
              >
                {{ t('apps.create_first_app') }}
              </router-link>
            </template>
          </div>

          <!-- Archived Apps Table (BTCPay-style) -->
          <div v-else-if="showArchived" class="bg-gray-800/50 border border-gray-700 rounded-2xl overflow-hidden">
            <table class="min-w-full divide-y divide-gray-700">
              <thead class="bg-gray-800">
                <tr>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('apps.name') }}</th>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('apps.app_type') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <tr
                  v-for="app in apps"
                  :key="app.id"
                  class="hover:bg-gray-700/50 cursor-pointer transition-colors"
                  @click="$router.push(`/stores/${storeId}/apps/${app.id}`)"
                >
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                      <span class="text-white font-medium">{{ app.name }}</span>
                      <span class="px-2 py-0.5 text-xs font-medium rounded bg-sky-500/20 text-sky-400 border border-sky-500/30">{{ t('apps.archived') }}</span>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-400">{{ app.app_type }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Apps Grid (non-archived) -->
          <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div
              v-for="app in apps"
              :key="app.id"
              class="group bg-gray-800 overflow-hidden shadow-lg rounded-2xl cursor-pointer hover:shadow-indigo-500/10 hover:border-indigo-500/50 border border-gray-700 transition-all duration-300 relative"
              @click="$router.push(`/stores/${storeId}/apps/${app.id}`)"
            >
              <div class="absolute top-0 right-0 p-4 opacity-0 group-hover:opacity-100 transition-opacity">
                 <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
              </div>

              <div class="p-6">
                <div class="flex items-center gap-4 mb-4">
                   <div class="p-3 rounded-xl bg-gray-700/50 text-indigo-400 group-hover:bg-indigo-500/10 group-hover:text-indigo-400 transition-colors">
                      <svg v-if="app.app_type === 'PointOfSale'" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                      <svg v-else-if="app.app_type === 'Crowdfund'" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                      <svg v-else class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                   </div>
                   <div>
                       <h3 class="text-lg font-bold text-white group-hover:text-indigo-400 transition-colors">{{ app.name }}</h3>
                       <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">{{ app.app_type }}</p>
                   </div>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-700/50 flex justify-between items-center">
                    <span class="text-xs text-gray-500">{{ t('apps.view_details') }}</span>
                    <span class="text-indigo-400 text-sm group-hover:translate-x-1 transition-transform">→</span>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAppsStore } from '../../store/apps';
import { useStoresStore } from '../../store/stores';
import StoreSidebar from '../../components/stores/StoreSidebar.vue';
import ArchivedStoreBanner from '../../components/stores/ArchivedStoreBanner.vue';
import api from '../../services/api';

const { t } = useI18n();

const route = useRoute();
const router = useRouter();
const appsStore = useAppsStore();
const storesStore = useStoresStore();

const storeId = route.params.id as string;
const loading = ref(false);
const store = ref<any>(null);

const showArchived = computed(() => route.query.archived === '1');
const allApps = computed(() => appsStore.apps);
const apps = computed(() =>
  showArchived.value
    ? allApps.value.filter((a: any) => a.archived)
    : allApps.value.filter((a: any) => !a.archived)
);

async function loadStore() {
  try {
    const response = await api.get(`/stores/${storeId}`);
    store.value = response.data.data;
  } catch (err: any) {
    console.error('Failed to load store:', err);
  }
}

async function loadApps() {
  loading.value = true;
  try {
    await appsStore.fetchApps(storeId);
  } catch (err) {
    console.error('Failed to load apps:', err);
  } finally {
    loading.value = false;
  }
}

function handleShowSettings() {
  router.push({ name: 'stores-show', params: { id: storeId }, query: { section: 'settings' } });
}

function handleShowSection(section: string) {
  router.push({ name: 'stores-show', params: { id: storeId }, query: { section } });
}

onMounted(async () => {
  await loadStore();
  await loadApps();
});

watch(() => route.params.id, async (newId) => {
    if (newId) {
        await loadStore();
        await loadApps();
    }
});
</script>
