<template>
  <!-- When store/app passed as props (SPA/Inertia): optional #toolbar stays fixed; #default scrolls -->
  <div v-if="propsStore && propsApp" class="flex min-h-0 flex-1 flex-col overflow-hidden bg-gray-900">
    <template v-if="hasToolbarSlot">
      <div class="shrink-0 bg-gray-900">
        <slot name="toolbar" :app="propsApp" :store="propsStore" />
      </div>
      <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain custom-scrollbar">
        <slot :app="propsApp" :store="propsStore" />
      </div>
    </template>
    <div v-else class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain custom-scrollbar">
      <slot :app="propsApp" :store="propsStore" />
    </div>
  </div>

  <div v-else-if="loading && !loadedApp" class="flex items-center justify-center h-screen bg-gray-900">
     <svg class="animate-spin h-10 w-10 text-indigo-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
     </svg>
    <p class="text-gray-400 ml-4">{{ t('apps.loading_app') }}</p>
  </div>

  <div v-else-if="loadedStore && loadedApp" class="flex min-h-0 flex-1 max-md:overflow-visible bg-gray-900 md:overflow-hidden">
    <!-- Sidebar -->
    <StoreSidebar
      :store="loadedStore"
      :apps="allApps"
      @create-app="handleCreateApp"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
    />

    <!-- Main Content -->
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden border-l border-gray-800 bg-gray-900">
      <template v-if="hasToolbarSlot">
        <div class="shrink-0 bg-gray-900">
          <slot name="toolbar" :app="loadedApp" :store="loadedStore" />
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain custom-scrollbar">
          <div class="px-4 sm:px-6 lg:px-8 pt-8">
            <ArchivedStoreBanner :store="loadedStore" />
          </div>
          <slot :app="loadedApp" :store="loadedStore" />
        </div>
      </template>
      <div v-else class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain custom-scrollbar">
        <div class="px-4 sm:px-6 lg:px-8 pt-8">
          <ArchivedStoreBanner :store="loadedStore" />
        </div>
        <slot :app="loadedApp" :store="loadedStore" />
      </div>
    </div>
  </div>

  <div v-else class="flex flex-col items-center justify-center h-screen bg-gray-900">
    <div class="h-16 w-16 bg-gray-800 rounded-full flex items-center justify-center mb-4">
        <svg class="h-8 w-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
    </div>
    <p class="text-gray-400 font-medium">{{ t('apps.app_not_found') }}</p>
    <button @click="goToAppsList" class="mt-4 text-indigo-400 hover:text-indigo-300 transition-colors text-sm">{{ t('apps.back_to_apps') }}</button>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, inject, useSlots } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { router as inertiaRouter } from '@inertiajs/vue3';
import { useAppsStore } from '../../store/apps';
import { useStoresStore } from '../../store/stores';
import StoreSidebar from './StoreSidebar.vue';
import ArchivedStoreBanner from './ArchivedStoreBanner.vue';

const props = defineProps<{ store?: any; app?: any }>();
const { t } = useI18n();
const slots = useSlots();
const hasToolbarSlot = computed(() => typeof slots.toolbar === 'function');
const isInertia = inject<boolean>('inertia', false);
const route = !isInertia ? useRoute() : null;
const vueRouter = !isInertia ? useRouter() : null;
const appsStore = useAppsStore();
const storesStore = useStoresStore();

const propsStore = computed(() => props.store ?? null);
const propsApp = computed(() => props.app ?? null);

const storeId = computed(() => (propsStore.value?.id ?? route?.params?.id ?? '') as string);
const appId = computed(() => (propsApp.value?.id ?? route?.params?.appId ?? '') as string);
const loading = ref(false);
const loadedStore = ref<any>(null);
const loadedApp = ref<any>(null);

const allApps = computed(() => appsStore.apps);

function goToAppsList() {
  if (isInertia) {
    inertiaRouter.visit(`/stores/${storeId.value}/apps`);
  } else {
    vueRouter!.push(`/stores/${storeId.value}/apps`);
  }
}

async function loadApp() {
  if (propsStore.value && propsApp.value) return; // Inertia: use props, no load
  loading.value = true;
  try {
    const currentStoreId = storeId.value;
    const currentAppId = appId.value;
    if (!currentStoreId || !currentAppId) {
      loading.value = false;
      return;
    }
    if (!loadedStore.value || loadedStore.value.id !== currentStoreId) {
      loadedStore.value = await storesStore.fetchStore(currentStoreId);
    }
    await appsStore.fetchApps(currentStoreId);
    loadedApp.value = await appsStore.fetchApp(currentStoreId, currentAppId);
  } catch (err: any) {
    console.error('Failed to load app:', err);
  } finally {
    loading.value = false;
  }
}

function handleCreateApp() {
  if (isInertia) {
    inertiaRouter.visit(`/stores/${storeId.value}/apps/create`);
  } else {
    vueRouter!.push(`/stores/${storeId.value}/apps/create`);
  }
}

function handleShowSettings() {
  if (isInertia) {
    inertiaRouter.visit(`/stores/${storeId.value}?section=settings`);
  } else {
    vueRouter!.push({ name: 'stores-show', params: { id: storeId.value }, query: { section: 'settings' } });
  }
}

function handleShowSection(section: string) {
  if (isInertia) {
    inertiaRouter.visit(`/stores/${storeId.value}?section=${section}`);
  } else {
    vueRouter!.push({ name: 'stores-show', params: { id: storeId.value }, query: { section } });
  }
}

onMounted(() => {
  if (propsStore.value && propsApp.value) {
    loadedStore.value = propsStore.value;
    loadedApp.value = propsApp.value;
    loading.value = false;
    return;
  }
  loadApp();
});

// Reload if route changes (SPA only)
if (route) {
  watch([() => route.params.id, () => route.params.appId], ([newStoreId, newAppId], [oldStoreId, oldAppId]) => {
    if (newAppId && (newAppId !== oldAppId || newStoreId !== oldStoreId)) {
      loadedApp.value = null;
      loadApp();
    }
  }, { immediate: false });
}

defineExpose({
  app: loadedApp,
  store: loadedStore,
  loading,
  loadApp,
});
</script>
