<template>
  <div class="flex min-h-0 flex-1 overflow-hidden bg-gray-900">
    <!-- Sidebar -->
    <StoreSidebar
      :store="store"
      :apps="allApps"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
      @open-setup-wizard="handleOpenSetupWizard"
    />

    <!-- Main Content -->
    <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden border-l border-gray-800 bg-gray-900">
      <!-- Scrollable Content Area -->
      <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain custom-scrollbar">
        <!-- Header -->
        <AppShowHeader
          :title="t('stores.pay_button')"
          :subtitle="store ? store.name : ''"
          :app-url="undefined"
          open-button-text=""
          form-id=""
          save-button-text=""
          saving-text=""
          :saving="toggleSaving"
        >
          <template #actions>
            <template v-if="store?.anyone_can_create_invoice">
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
                {{ copied ? t('stores.pay_button_copied') : t('stores.pay_button_copy_code') }}
              </button>
            </template>
          </template>
        </AppShowHeader>

        <!-- Content Container -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <!-- Pay Button disabled: show warning + Enable -->
          <template v-if="store && !store.anyone_can_create_invoice">
            <div class="mb-6 bg-yellow-500/10 border border-yellow-500/20 rounded-xl p-4">
              <div class="flex items-start">
                <svg class="w-5 h-5 text-yellow-400 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="flex-1">
                  <p class="text-sm text-yellow-400 font-medium">{{ t('stores.pay_button_warning_title') }}</p>
                  <p class="text-sm text-gray-400 mt-1">
                    {{ t('stores.pay_button_warning_text') }}
                  </p>
                </div>
              </div>
            </div>
            <p class="text-gray-400 mb-4">
              {{ t('stores.pay_button_enable_description') }}
            </p>
            <button
              type="button"
              :disabled="toggleSaving"
              @click="setPayButtonEnabled(true)"
              class="inline-flex items-center px-6 py-3 bg-orange-500 text-white rounded-xl font-medium hover:bg-orange-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ toggleSaving ? t('stores.pay_button_enabling') : t('stores.pay_button_enable') }}
            </button>
          </template>

          <!-- Pay Button enabled: show form (Disable button is inside notice box via slot) -->
          <template v-else-if="store && store.anyone_can_create_invoice">
            <PayButtonForm ref="payButtonFormRef" :store="store">
              <template #warning-action>
                <button
                  type="button"
                  :disabled="toggleSaving"
                  @click="setPayButtonEnabled(false)"
                  class="inline-flex items-center px-4 py-2 border border-red-500/50 rounded-lg text-sm font-medium text-red-400 hover:bg-red-500/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {{ toggleSaving ? t('stores.pay_button_disabling') : t('stores.pay_button_disable') }}
                </button>
              </template>
            </PayButtonForm>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useStoresStore } from '../../store/stores';
import { useAppsStore } from '../../store/apps';
import { useFlashStore } from '../../store/flash';
import api from '../../services/api';
import StoreSidebar from '../../components/stores/StoreSidebar.vue';
import AppShowHeader from '../../components/stores/AppShowHeader.vue';
import PayButtonForm from './PayButtonForm.vue';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const storesStore = useStoresStore();
const appsStore = useAppsStore();
const flashStore = useFlashStore();

const storeId = route.params.id as string;
const store = ref<any>(null);
const payButtonFormRef = ref<InstanceType<typeof PayButtonForm>>();

const allApps = computed(() => appsStore.apps);

const copied = ref(false);
const canCopy = computed(() => {
  return payButtonFormRef.value?.generatedCode && payButtonFormRef.value.generatedCode.length > 0;
});

const toggleSaving = ref(false);

onMounted(async () => {
  try {
    await storesStore.fetchStore(storeId);
    store.value = storesStore.currentStore;
    await appsStore.fetchApps(storeId);
  } catch (err) {
    console.error('Failed to load store:', err);
  }
});

async function setPayButtonEnabled(enabled: boolean) {
  if (!store.value) return;
  toggleSaving.value = true;
  flashStore.clear();
  try {
    const { data } = await api.get(`/stores/${storeId}/settings`);
    const payload = { ...data.data, anyone_can_create_invoice: enabled };
    await api.put(`/stores/${storeId}/settings`, payload);
    await storesStore.fetchStore(storeId);
    store.value = storesStore.currentStore;
    flashStore.success(enabled ? t('stores.pay_button_enabled_success') : t('stores.pay_button_disabled_success'));
  } catch (err: any) {
    flashStore.error(err.response?.data?.message || (enabled ? t('stores.pay_button_enable_failed') : t('stores.pay_button_disable_failed')));
  } finally {
    toggleSaving.value = false;
  }
}

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

