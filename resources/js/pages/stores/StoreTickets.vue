<template>
  <div v-if="!store && !error" class="flex items-center justify-center min-h-screen bg-gray-900">
    <div class="flex flex-col items-center">
      <svg class="animate-spin h-10 w-10 text-indigo-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <p class="text-gray-400">{{ t('common.loading') }}</p>
    </div>
  </div>
  <div v-else-if="error" class="flex items-center justify-center min-h-screen bg-gray-900">
    <div class="text-center px-4">
      <p class="text-red-400 mb-4">{{ error }}</p>
      <button @click="loadStore" class="text-indigo-400 hover:text-indigo-300">{{ t('common.retry') }}</button>
    </div>
  </div>
  <div v-else class="flex bg-gray-900 overflow-hidden min-h-screen">
    <StoreSidebar
      :store="store"
      :apps="appsStore.apps"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
    />
    <div class="flex-1 overflow-hidden flex flex-col bg-gray-900 border-l border-gray-800">
      <TicketsShow :store="store" :app="virtualApp" :event-limit="eventLimit" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAppsStore } from '../../store/apps';
import { useAccountLimits } from '../../composables/useAccountLimits';
import api from '../../services/api';
import StoreSidebar from '../../components/stores/StoreSidebar.vue';
import TicketsShow from './TicketsShow.vue';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const appsStore = useAppsStore();
const { limits, load: loadLimits } = useAccountLimits();

const storeId = computed(() => route.params.id as string);
const store = ref<any>(null);
const error = ref('');

const virtualApp = computed(() => ({ name: t('tickets.title') }));
const eventLimit = computed(() => limits.value?.events?.unlimited ? null : (limits.value?.events?.max ?? null));

function handleShowSettings() {
  router.push({ name: 'stores-show', params: { id: storeId.value }, query: { section: 'settings' } });
}

function handleShowSection(section: string) {
  router.push({ name: 'stores-show', params: { id: storeId.value }, query: { section } });
}

async function loadStore() {
  error.value = '';
  try {
    const response = await api.get(`/stores/${storeId.value}`);
    store.value = response.data.data;
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to load store';
  }
}

onMounted(async () => {
  await loadStore();
  if (storeId.value) {
    await loadLimits(storeId.value);
  }
  if (store.value) {
    await appsStore.fetchApps(storeId.value);
  }
});

watch(() => route.params.id, async (newId) => {
  if (newId) {
    await loadStore();
    if (store.value) {
      await appsStore.fetchApps(newId as string);
    }
  }
});
</script>
