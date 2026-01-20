<template>
  <div class="flex h-full bg-gray-100">
    <!-- Sidebar -->
    <StoreSidebar
      :store="store"
      :apps="allApps"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
    />

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Header -->
      <div class="sticky top-0 z-20 bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 pb-4">
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-xl font-bold text-gray-900 mb-2">E-shop Integration</h1>
              <p class="text-sm text-gray-500">Manage API keys for connecting your e-shop (WooCommerce, etc.) to {{ store?.name || 'this store' }}</p>
            </div>
            <div class="flex items-center gap-2">
              <button
                @click="openCreateForm"
                class="inline-flex items-center px-2 md:px-4 py-2 border border-transparent text-xs md:text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500"
              >
                Create API Key
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Container -->
      <div class="flex-1 overflow-y-auto">
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
        <div v-if="loading" class="bg-white shadow rounded-lg p-12 text-center">
          <p class="text-gray-500">Loading API keys...</p>
        </div>

        <!-- Empty State -->
        <div v-if="!loading && apiKeys.length === 0" class="bg-white shadow rounded-lg p-12 text-center">
          <p class="text-gray-500 mb-4">No API keys created yet.</p>
          <button
            @click="openCreateForm"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700"
          >
            Create Your First API Key
          </button>
        </div>

        <!-- Create/Edit Modal -->
        <div
          v-if="showModal"
          class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
          @click.self="closeModal"
        >
          <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
              <h3 class="text-lg font-medium text-gray-900 mb-4">
                {{ editingApiKey ? 'Edit API Key' : 'Create New API Key' }}
              </h3>

              <form @submit.prevent="handleSubmit" class="space-y-4">
                <div>
                  <label for="label" class="block text-sm font-medium text-gray-700">
                    Label <span class="text-red-500">*</span>
                  </label>
                  <input
                    id="label"
                    v-model="form.label"
                    type="text"
                    required
                    placeholder="e.g., WooCommerce Production"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500 sm:text-sm"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Permissions
                  </label>
                  <div class="space-y-2">
                    <label
                      v-for="permission in availablePermissions"
                      :key="permission.value"
                      class="flex items-center"
                    >
                      <input
                        v-model="form.permissions"
                        type="checkbox"
                        :value="permission.value"
                        class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded"
                      />
                      <span class="ml-2 text-sm text-gray-700">{{ permission.label }}</span>
                    </label>
                  </div>
                </div>

                <div>
                  <label for="callback_url" class="block text-sm font-medium text-gray-700">
                    Callback URL (optional)
                  </label>
                  <input
                    id="callback_url"
                    v-model="form.callback_url"
                    type="url"
                    placeholder="https://your-eshop.com/api/callback"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500 sm:text-sm"
                  />
                  <p class="mt-1 text-xs text-gray-500">
                    If provided, the API key will be automatically sent to this URL upon creation.
                  </p>
                </div>

                <div v-if="error" class="rounded-md bg-red-50 p-4">
                  <div class="text-sm text-red-800">{{ error }}</div>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                  <button
                    type="button"
                    @click="closeModal"
                    class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    :disabled="saving"
                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 disabled:opacity-50"
                  >
                    {{ saving ? 'Creating...' : 'Create API Key' }}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- API Key Display Modal (shown once after creation) -->
        <div
          v-if="showApiKeyModal && createdApiKey"
          class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
          @click.self="closeApiKeyModal"
        >
          <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
              <h3 class="text-lg font-medium text-gray-900 mb-4">
                API Key Created Successfully
              </h3>
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    API Key
                  </label>
                  <div class="flex items-center">
                    <input
                      :value="createdApiKey.api_key"
                      readonly
                      type="password"
                      ref="apiKeyInput"
                      class="flex-1 bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm text-gray-900 focus:outline-none font-mono"
                    />
                    <button
                      @click="toggleApiKeyVisibility"
                      type="button"
                      class="ml-2 p-2 text-gray-600 hover:text-gray-800"
                      :title="showApiKey ? 'Hide' : 'Show'"
                    >
                      <svg v-if="!showApiKey" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                      <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                      </svg>
                    </button>
                    <button
                      @click="copyApiKey"
                      type="button"
                      class="ml-2 p-2 text-orange-600 hover:text-orange-700"
                      title="Copy to clipboard"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                      </svg>
                    </button>
                  </div>
                  <p class="mt-2 text-xs text-red-600 font-medium">
                    ⚠️ Save this API key now. You won't be able to see it again!
                  </p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Store ID
                  </label>
                  <div class="flex items-center">
                    <input
                      :value="createdApiKey?.store_id || store?.btcpay_store_id"
                      readonly
                      class="flex-1 bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm text-gray-900 focus:outline-none font-mono"
                    />
                    <button
                      @click="copyToClipboard(createdApiKey?.store_id || store?.btcpay_store_id)"
                      type="button"
                      class="ml-2 p-2 text-orange-600 hover:text-orange-700"
                      title="Copy to clipboard"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                      </svg>
                    </button>
                  </div>
                </div>

                <div class="pt-4 border-t">
                  <button
                    @click="closeApiKeyModal"
                    class="w-full px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700"
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
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStoresStore } from '../../store/stores';
import { useAppsStore } from '../../store/apps';
import StoreSidebar from '../../components/stores/StoreSidebar.vue';
import ApiKeyCard from '../../components/stores/ApiKeyCard.vue';
import api from '../../services/api';

const route = useRoute();
const router = useRouter();
const storesStore = useStoresStore();
const appsStore = useAppsStore();

const storeId = computed(() => route.params.id as string);
const store = ref<any>(null);
const loading = ref(false);
const apiKeys = ref<any[]>([]);
const showModal = ref(false);
const showApiKeyModal = ref(false);
const saving = ref(false);
const error = ref('');
const createdApiKey = ref<any>(null);
const showApiKey = ref(false);
const apiKeyInput = ref<HTMLInputElement | null>(null);

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
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load API keys';
    console.error('Failed to fetch API keys:', err);
  } finally {
    loading.value = false;
  }
}

function openCreateForm() {
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
    
    // Refresh list
    await fetchApiKeys();
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to create API key';
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
  if (apiKeyInput.value) {
    apiKeyInput.value.type = showApiKey.value ? 'text' : 'password';
  }
}

async function copyApiKey() {
  if (createdApiKey.value?.api_key) {
    await copyToClipboard(createdApiKey.value.api_key);
  }
}

async function copyToClipboard(text: string) {
  try {
    await navigator.clipboard.writeText(text);
    // Could show a toast notification here
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

