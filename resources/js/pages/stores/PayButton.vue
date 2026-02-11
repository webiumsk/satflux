<template>
  <div class="flex bg-gray-900 overflow-hidden">
    <!-- Sidebar -->
    <StoreSidebar
      :store="store"
      :apps="allApps"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
      @open-setup-wizard="handleOpenSetupWizard"
    />

    <!-- Main Content -->
    <div class="flex-1 overflow-hidden flex flex-col bg-gray-900 border-l border-gray-800">
      <!-- Scrollable Content Area -->
      <div class="flex-1 overflow-y-auto custom-scrollbar">
        <!-- Header -->
        <AppShowHeader
          title="Pay Button"
          :subtitle="store ? store.name : ''"
          :app-url="undefined"
          open-button-text=""
          form-id=""
          save-button-text=""
          saving-text=""
          :saving="false"
          :error="undefined"
          :success="undefined"
        >
          <template #actions>
            <button
              @click="handleCopyCode"
              :disabled="!canCopy"
              class="inline-flex items-center px-4 py-2 border border-gray-600 rounded-xl text-sm font-medium text-gray-300 hover:text-white hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <svg v-if="copied" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
              {{ copied ? 'Copied!' : 'Copy Code' }}
            </button>
          </template>
        </AppShowHeader>

        <!-- Content Container -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <PayButtonForm v-if="store" ref="payButtonFormRef" :store="store" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStoresStore } from '../../store/stores';
import { useAppsStore } from '../../store/apps';
import StoreSidebar from '../../components/stores/StoreSidebar.vue';
import AppShowHeader from '../../components/stores/AppShowHeader.vue';
import PayButtonForm from './PayButtonForm.vue';

const route = useRoute();
const router = useRouter();
const storesStore = useStoresStore();
const appsStore = useAppsStore();

const storeId = route.params.id as string;
const store = ref<any>(null);
const payButtonFormRef = ref<InstanceType<typeof PayButtonForm>>();

const allApps = computed(() => appsStore.apps);

const copied = ref(false);
const canCopy = computed(() => {
  return payButtonFormRef.value?.generatedCode && payButtonFormRef.value.generatedCode.length > 0;
});

onMounted(async () => {
  try {
    await storesStore.fetchStore(storeId);
    store.value = storesStore.currentStore;
    await appsStore.fetchApps(storeId);
  } catch (err) {
    console.error('Failed to load store:', err);
  }
});

function handleCopyCode() {
  if (!payButtonFormRef.value) return;
  
  payButtonFormRef.value.copyCode();
  copied.value = true;
  setTimeout(() => {
    copied.value = false;
  }, 2000);
}

function handleShowSettings() {
  router.push({ name: 'stores-show', params: { id: storeId }, query: { section: 'settings' } });
}

function handleOpenSetupWizard() {
  router.push({ name: 'stores-show', params: { id: storeId }, query: { setup: '1' } });
}

function handleShowSection(section: string) {
  router.push({ name: 'stores-show', params: { id: storeId }, query: { section } });
}
</script>

