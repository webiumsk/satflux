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
    <div class="flex-1 flex flex-col overflow-hidden bg-gray-900 border-l border-gray-800">
      <!-- Header -->
      <div class="sticky top-0 z-20 bg-gray-900/80 backdrop-blur-md border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h1 class="text-2xl font-bold text-white mb-1">{{ t('stores.eshop_integration') }}</h1>
              <p class="text-sm text-gray-400">
                {{ t('stores.manage_api_keys_for') }} <span class="text-indigo-400">{{ store?.name || t('stores.this_store') }}</span>
                <span v-if="limit && limit.max != null" class="text-gray-500 ml-1 bg-gray-800 px-2 py-0.5 rounded-full text-xs">
                  {{ limit.current }} / {{ limit.max }} {{ t('stores.used') }}
                </span>
                <span v-else-if="limit && limit.unlimited" class="text-gray-500 ml-1 bg-gray-800 px-2 py-0.5 rounded-full text-xs">
                  {{ t('stores.unlimited') }}
                </span>
              </p>
            </div>
            <div class="flex items-center gap-3">
              <button
                @click="openCreateForm"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg shadow-indigo-600/20 transition-all hover:scale-105"
              >
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                {{ t('stores.create_api_key') }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Container -->
      <div class="flex-1 overflow-y-auto custom-scrollbar">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

          <!-- API Keys List -->
          <div v-if="!loading && apiKeys.length > 0" class="space-y-4">
            <ApiKeyCard
              v-for="apiKey in apiKeys"
              :key="apiKey.id"
              :api-key="apiKey"
              :store-id="storeId"
              @regenerate="handleRegenerate"
              @delete="handleDelete"
              @refresh="fetchApiKeys"
            />
          </div>

          <!-- Loading State -->
          <div v-if="loading" class="flex flex-col items-center justify-center py-24">
             <svg class="animate-spin h-10 w-10 text-indigo-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
             </svg>
             <p class="text-gray-400">{{ t('stores.loading_api_keys') }}</p>
          </div>

          <!-- Empty State -->
          <div v-if="!loading && apiKeys.length === 0" class="bg-gray-800/50 border border-gray-700 rounded-2xl p-12 text-center">
             <div class="mx-auto h-16 w-16 text-gray-600 bg-gray-800 rounded-full flex items-center justify-center mb-4">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
             </div>
            <h3 class="text-lg font-medium text-white mb-2">{{ t('stores.no_api_keys_yet') }}</h3>
            <p class="text-gray-400 mb-6 max-w-sm mx-auto">{{ t('stores.create_api_key_description') }}</p>
            <button
              @click="openCreateForm"
              class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg shadow-indigo-600/20 transition-all hover:scale-105"
            >
              {{ t('stores.create_your_first_api_key') }}
            </button>
          </div>

          <!-- Create/Edit Modal -->
          <div
            v-if="showModal"
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
          >
             <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm transition-opacity" aria-hidden="true" @click="closeModal"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-gray-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-700">
                    <div class="bg-gray-800 px-4 pt-5 pb-4 sm:p-6">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-bold text-white mb-4" id="modal-title">
                                    {{ t('stores.create_new_api_key') }}
                                </h3>

                                <form @submit.prevent="handleSubmit" class="space-y-5">
                                    <div>
                                        <label for="label" class="block text-sm font-medium text-gray-300 mb-1">
                                            {{ t('stores.api_key_label') }} <span class="text-red-400">*</span>
                                        </label>
                                        <input
                                            id="label"
                                            v-model="form.label"
                                            type="text"
                                            required
                                            :placeholder="t('stores.api_key_label_placeholder')"
                                            class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">
                                            {{ t('stores.api_key_permissions') }}
                                        </label>
                                        <div class="bg-gray-700/30 rounded-xl p-4 border border-gray-700 space-y-3 max-h-60 overflow-y-auto custom-scrollbar">
                                            <label
                                                v-for="permission in availablePermissions"
                                                :key="permission.value"
                                                class="flex items-start"
                                            >
                                                <div class="flex items-center h-5">
                                                    <input
                                                        v-model="form.permissions"
                                                        type="checkbox"
                                                        :value="permission.value"
                                                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-600 rounded bg-gray-700"
                                                    />
                                                </div>
                                                <span class="ml-3 text-sm text-gray-300">{{ permission.label }}</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="callback_url" class="block text-sm font-medium text-gray-300 mb-1">
                                            {{ t('stores.api_key_callback_url') }} <span class="text-gray-500 text-xs font-normal">({{ t('common.optional') }})</span>
                                        </label>
                                        <input
                                            id="callback_url"
                                            v-model="form.callback_url"
                                            type="url"
                                            :placeholder="t('stores.api_key_callback_placeholder')"
                                            class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        />
                                        <p class="mt-1 text-xs text-gray-400">
                                            {{ t('stores.api_key_callback_url_hint') }}
                                        </p>
                                    </div>

                                    <div v-if="error" class="rounded-xl bg-red-500/10 border border-red-500/20 p-4">
                                        <div class="flex">
                                            <svg class="h-5 w-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            <div class="text-sm text-red-400">{{ error }}</div>
                                        </div>
                                    </div>

                                    <div class="mt-6 flex justify-end gap-3">
                                        <button
                                            type="button"
                                            @click="closeModal"
                                            class="px-4 py-2 border border-gray-600 rounded-xl text-sm font-medium text-gray-300 hover:text-white hover:bg-gray-700 transition-colors"
                                        >
                                            {{ t('common.cancel') }}
                                        </button>
                                        <button
                                            type="submit"
                                            :disabled="saving"
                                            class="inline-flex justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg shadow-indigo-600/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                                        >
                                            <svg v-if="saving" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            {{ saving ? t('stores.creating') : t('stores.create_api_key') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
             </div>
          </div>

          <!-- API Key Display Modal (shown once after creation) -->
          <div
            v-if="showApiKeyModal && createdApiKey"
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
          >
             <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm transition-opacity" aria-hidden="true" @click="closeApiKeyModal"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-gray-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-700">
                    <div class="bg-gray-800 px-4 pt-5 pb-4 sm:p-6">
                         <div class="text-center mb-6">
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-500/10 mb-4 border border-green-500/20">
                                <svg class="h-6 w-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            <h3 class="text-lg leading-6 font-bold text-white" id="modal-title">
                                API Key Created Successfully
                            </h3>
                         </div>
                         
                         <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">
                                    API Key
                                </label>
                                <div class="flex items-center">
                                    <div class="relative flex-grow">
                                        <input
                                            :value="createdApiKey.api_key"
                                            readonly
                                            :type="showApiKey ? 'text' : 'password'"
                                            class="block w-full pr-10 border-gray-600 rounded-xl bg-gray-900/50 text-white font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500 py-3 px-4"
                                        />
                                        <button
                                            @click="toggleApiKeyVisibility"
                                            type="button"
                                            class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-white"
                                        >
                                            <svg v-if="!showApiKey" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                        </button>
                                    </div>
                                    <button
                                        @click="copyApiKey"
                                        type="button"
                                        class="ml-3 p-3 bg-gray-700 hover:bg-gray-600 rounded-xl text-indigo-400 hover:text-indigo-300 transition-colors border border-gray-600"
                                        title="Copy to clipboard"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                    </button>
                                </div>
                                <p class="mt-2 text-xs text-amber-500 font-medium flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    Save this API key now. You won't be able to see it again!
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">
                                    Store ID
                                </label>
                                <div class="flex items-center">
                                    <input
                                        :value="createdApiKey?.store_id || store?.btcpay_store_id"
                                        readonly
                                        class="block w-full border-gray-600 rounded-xl bg-gray-900/50 text-gray-400 font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500 py-3 px-4"
                                    />
                                    <button
                                        @click="copyToClipboard(createdApiKey?.store_id || store?.btcpay_store_id)"
                                        type="button"
                                        class="ml-3 p-3 bg-gray-700 hover:bg-gray-600 rounded-xl text-indigo-400 hover:text-indigo-300 transition-colors border border-gray-600"
                                        title="Copy to clipboard"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                    </button>
                                </div>
                            </div>
                         </div>
                    </div>
                     <div class="bg-gray-800/50 border-t border-gray-700 px-4 py-4 sm:px-6">
                        <button
                            @click="closeApiKeyModal"
                            class="w-full inline-flex justify-center px-4 py-3 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg shadow-indigo-600/20 transition-all hover:scale-105"
                        >
                            I've Saved the API Key
                        </button>
                    </div>
                </div>
             </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Upgrade Modal -->
    <UpgradeModal
      :show="showUpgradeModal"
      :message="limit ? t('stores.api_key_limit_reached', { max: limit.max ?? 1 }) : ''"
      :limits="limit ? [{ feature: 'API keys', current: limit.current, max: limit.max }] : []"
      recommended-plan="pro"
      upgrade-button-text="Upgrade to Pro"
      @close="showUpgradeModal = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useStoresStore } from '../../store/stores';
import { useAppsStore } from '../../store/apps';
import { useFlashStore } from '../../store/flash';
import StoreSidebar from '../../components/stores/StoreSidebar.vue';
import ApiKeyCard from '../../components/stores/ApiKeyCard.vue';
import UpgradeModal from '../../components/stores/UpgradeModal.vue';
import api from '../../services/api';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const storesStore = useStoresStore();
const appsStore = useAppsStore();
const flashStore = useFlashStore();

const storeId = computed(() => route.params.id as string);
const store = ref<any>(null);
const loading = ref(false);
const apiKeys = ref<any[]>([]);
const limit = ref<{ max: number | null; current: number; unlimited: boolean } | null>(null);
const showModal = ref(false);
const showApiKeyModal = ref(false);
const showUpgradeModal = ref(false);
const saving = ref(false);
const error = ref('');
const createdApiKey = ref<any>(null);
const showApiKey = ref(false);

const canAddApiKey = computed(() => {
  if (!limit.value) return true;
  if (limit.value.unlimited) return true;
  return limit.value.current < (limit.value.max ?? 0);
});

const allApps = computed(() => appsStore.apps);

const availablePermissions = [
  { value: 'btcpay.store.canviewinvoices', label: 'View invoices' },
  { value: 'btcpay.store.cancreateinvoice', label: 'Create an invoice' },
  { value: 'btcpay.store.canmodifyinvoices', label: 'Modify invoices' },
  { value: 'btcpay.store.webhooks.canmodifywebhooks', label: "Modify selected stores' webhooks" },
  { value: 'btcpay.store.canviewstoresettings', label: 'View your stores' },
  { value: 'btcpay.store.cancreatenonapprovedpullpayments', label: 'Create non-approved pull payments in selected stores' },
];

const form = ref({
  label: '',
  permissions: [
    'btcpay.store.canviewinvoices',
    'btcpay.store.cancreateinvoice',
    'btcpay.store.canmodifyinvoices',
    'btcpay.store.canmodifywebhooks',
    'btcpay.store.canviewstoresettings',
    'btcpay.store.canmodifypullpayments',
  ],
  callback_url: '',
});

async function fetchStore() {
  try {
    store.value = await storesStore.fetchStore(storeId.value);
  } catch (err) {
    console.error('Failed to fetch store:', err);
  }
}

async function fetchApiKeys() {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get(`/stores/${storeId.value}/api-keys`);
    apiKeys.value = response.data.data || [];
    if (response.data.limit) {
      limit.value = response.data.limit;
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load API keys';
    console.error('Failed to fetch API keys:', err);
  } finally {
    loading.value = false;
  }
}

function openCreateForm() {
  if (!canAddApiKey.value) {
    showUpgradeModal.value = true;
    return;
  }
  form.value = {
    label: '',
    permissions: [
      'btcpay.store.canviewinvoices',
      'btcpay.store.cancreateinvoice',
      'btcpay.store.canmodifyinvoices',
      'btcpay.store.webhooks.canmodifywebhooks',
      'btcpay.store.canviewstoresettings',
      'btcpay.store.cancreatenonapprovedpullpayments',
    ],
    callback_url: '',
  };
  error.value = '';
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  error.value = '';
}

async function handleSubmit() {
  saving.value = true;
  error.value = '';
  try {
    const response = await api.post(`/stores/${storeId.value}/api-keys`, {
      label: form.value.label,
      permissions: form.value.permissions,
      callback_url: form.value.callback_url || null,
    });

    createdApiKey.value = response.data.data;
    showModal.value = false;
    showApiKeyModal.value = true;
    showApiKey.value = false;
    flashStore.success(t('stores.api_key_created'));

    // Refresh list
    await fetchApiKeys();
  } catch (err: any) {
    const msg = err.response?.data?.message || t('stores.api_key_create_failed');
    if (err.response?.status === 403 && msg.includes('maximum number')) {
      closeModal();
      showUpgradeModal.value = true;
      if (limit.value) {
        limit.value = { ...limit.value, current: limit.value.current };
      } else {
        await fetchApiKeys();
      }
    } else {
      flashStore.error(msg);
      error.value = '';
    }
  } finally {
    saving.value = false;
  }
}

function closeApiKeyModal() {
  showApiKeyModal.value = false;
  createdApiKey.value = null;
  showApiKey.value = false;
}

function toggleApiKeyVisibility() {
  showApiKey.value = !showApiKey.value;
}

async function copyApiKey() {
  if (createdApiKey.value?.api_key) {
    await copyToClipboard(createdApiKey.value.api_key);
  }
}

async function copyToClipboard(text: string) {
  try {
    await navigator.clipboard.writeText(text);
  } catch (err) {
    console.error('Failed to copy:', err);
  }
}

function handleRegenerate() {
  fetchApiKeys();
}

function handleDelete() {
  fetchApiKeys();
}

function handleShowSettings() {
  router.push({ name: 'stores-show', params: { id: storeId.value }, query: { section: 'settings' } });
}

function handleOpenSetupWizard() {
  router.push({ name: 'stores-show', params: { id: storeId.value }, query: { setup: '1' } });
}

function handleShowSection(section: string) {
  router.push({ name: 'stores-show', params: { id: storeId.value }, query: { section } });
}

onMounted(async () => {
    await fetchStore();
    await fetchApiKeys();
    await appsStore.fetchApps(storeId.value);
});

watch(() => route.params.id, async (newId) => {
  if (newId) {
    await fetchStore();
    await fetchApiKeys();
    await appsStore.fetchApps(newId as string);
  }
});
</script>
