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
      <div class="flex-1 overflow-y-auto">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-3xl font-bold text-gray-900">Lightning Address</h1>
              <p v-if="addresses.length === 0" class="text-sm text-gray-500 mt-2">
                There are no Lightning Addresses yet.
              </p>
            </div>
            <button
              @click="openAddForm"
              class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500"
            >
              Add Address
            </button>
          </div>
        </div>

        <!-- Addresses List -->
        <div v-if="!loading && addresses.length > 0" class="bg-white shadow rounded-lg overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Address
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Settings
                </th>
                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="address in addresses" :key="address.username">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <input
                      :value="`${address.username}@dvadsatjeden.org`"
                      readonly
                      class="flex-1 bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm text-gray-900 focus:outline-none"
                    />
                    <button
                      @click="copyToClipboard(`${address.username}@dvadsatjeden.org`)"
                      class="ml-2 p-2 text-orange-600 hover:text-orange-700"
                      title="Copy to clipboard"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                      </svg>
                    </button>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="text-sm text-gray-900">
                    <span v-if="address.min || address.max || address.currencyCode">
                      {{ address.min || '0' }} min sats / {{ address.max || '∞' }} max sats
                      <span v-if="address.currencyCode">/ tracked in {{ address.currencyCode }}</span>
                    </span>
                    <span v-else class="text-gray-400">-</span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right">
                  <button
                    @click="confirmDelete(address)"
                    class="text-orange-600 hover:text-orange-900"
                  >
                    Remove
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="bg-white shadow rounded-lg p-12 text-center">
          <p class="text-gray-500">Loading lightning addresses...</p>
        </div>

        <!-- Empty State -->
        <div v-if="!loading && addresses.length === 0" class="bg-white shadow rounded-lg p-12 text-center">
          <p class="text-gray-500 mb-4">No lightning addresses configured yet.</p>
          <button
            @click="openAddForm"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700"
          >
            Add Address
          </button>
        </div>
      </div>
    </div>

    <!-- Add Form Modal -->
    <div
      v-if="showForm"
      class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
      @click.self="closeForm"
    >
      <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">
              Add Lightning Address
            </h3>
            <button
              @click="closeForm"
              class="text-gray-400 hover:text-gray-500"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-4">
            <div>
              <label for="username" class="block text-sm font-medium text-gray-700">
                Username <span class="text-red-500">*</span>
              </label>
              <div class="mt-1 flex rounded-md shadow-sm">
                  <input
                    id="username"
                    v-model="form.username"
                    type="text"
                    required
                    class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md border border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    placeholder="username"
                  />
                <span class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                  @dvadsatjeden.org
                </span>
              </div>
              <p class="mt-1 text-sm text-gray-500">Full address: {{ form.username || 'username' }}@dvadsatjeden.org</p>
            </div>

            <!-- Advanced Settings Accordion -->
            <div class="border-t border-gray-200 pt-4">
              <button
                type="button"
                @click="showAdvancedSettings = !showAdvancedSettings"
                class="w-full flex items-center justify-between text-left"
              >
                <h3 class="text-sm font-medium text-gray-900">Advanced Settings</h3>
                <svg
                  :class="['w-5 h-5 text-gray-500 transform transition-transform', showAdvancedSettings ? 'rotate-180' : '']"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <div v-show="showAdvancedSettings" class="mt-4 space-y-4">
                

                <div class="grid grid-cols-3 gap-4">
                  <div>
                  <label for="currencyCode" class="flex items-center text-sm font-medium text-gray-700 mb-1">
                    Currency Code
                  </label>
                  <input
                    id="currencyCode"
                    v-model="form.currencyCode"
                    type="text"
                    list="currency-selection-suggestion"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    placeholder="Select or type currency (e.g., USD, BTC, EUR)"
                  />
                  <datalist id="currency-selection-suggestion">
                    <option v-for="currency in currencies" :key="currency.code" :value="currency.code">
                      {{ currency.code }} - {{ currency.name }}
                    </option>
                  </datalist>
                  <p class="mt-1 text-xs text-gray-500">Leave empty to use store default currency ({{ store?.default_currency || 'EUR' }})</p>
                </div>
                  <div>
                    <label for="min" class="block text-sm font-medium text-gray-700">
                      Min (sats)
                    </label>
                    <input
                      id="min"
                      v-model="form.min"
                      type="text"
                      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                      placeholder="Minimum amount"
                    />
                  </div>

                  <div>
                    <label for="max" class="block text-sm font-medium text-gray-700">
                      Max (sats)
                    </label>
                    <input
                      id="max"
                      v-model="form.max"
                      type="text"
                      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                      placeholder="Maximum amount"
                    />
                  </div>
                </div>

                <div>
                  <label for="invoiceMetadata" class="flex items-center text-sm font-medium text-gray-700 mb-1">
                    Invoice Metadata
                    <InfoTooltip class="ml-1">
                      <div class="text-xs max-w-xs">
                        Metadata (in JSON) to add to the invoice when created through this lightning address.
                      </div>
                    </InfoTooltip>
                  </label>
                  <textarea
                    id="invoiceMetadata"
                    v-model="form.invoiceMetadata"
                    rows="4"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm font-mono text-sm"
                    placeholder='{"key": "value"}'
                  ></textarea>
                  <p class="mt-1 text-xs text-gray-500">Enter valid JSON object. Example: <code class="bg-gray-100 px-1 rounded">{"orderId": "12345"}</code></p>
                </div>
              </div>
            </div>

            <div v-if="error" class="rounded-md bg-red-50 p-4">
              <div class="text-sm text-red-800">{{ error }}</div>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
              <button
                type="button"
                @click="closeForm"
                class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="saving"
                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 disabled:opacity-50"
              >
                {{ saving ? 'Saving...' : 'Add' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
      v-if="showDeleteModal"
      class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
      @click.self="closeDeleteModal"
    >
      <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Remove Lightning Address</h3>
          <p class="text-sm text-gray-500 mb-4">
            Are you sure you want to remove <strong>{{ addressToDelete?.username }}@dvadsatjeden.org</strong>?
            This action cannot be undone.
          </p>
          <div v-if="deleteError" class="rounded-md bg-red-50 p-4 mb-4">
            <div class="text-sm text-red-800">{{ deleteError }}</div>
          </div>
          <div class="flex justify-end space-x-3">
            <button
              @click="closeDeleteModal"
              :disabled="deleting"
              class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              @click="handleDelete"
              :disabled="deleting"
              class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 disabled:opacity-50"
            >
              {{ deleting ? 'Removing...' : 'Remove' }}
            </button>
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
import InfoTooltip from '../../components/ui/InfoTooltip.vue';
import { currencies } from '../../data/currencies';
import api from '../../services/api';

const route = useRoute();
const router = useRouter();
const storesStore = useStoresStore();
const appsStore = useAppsStore();

const storeId = route.params.id as string;
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const addresses = ref<any[]>([]);
const store = ref<any>(null);
const error = ref('');
const deleteError = ref('');
const showForm = ref(false);
const showDeleteModal = ref(false);
const addressToDelete = ref<any>(null);

const allApps = computed(() => appsStore.apps);

const form = ref({
  username: '',
  currencyCode: '',
  min: '',
  max: '',
  invoiceMetadata: '',
});

const showAdvancedSettings = ref(false);

onMounted(async () => {
  await loadStore();
  await loadAddresses();
  await appsStore.fetchApps(storeId);
});

// Watch for route changes to reload apps if store ID changes
watch(() => route.params.id, async (newStoreId) => {
  if (newStoreId && newStoreId !== storeId) {
    await appsStore.fetchApps(newStoreId as string);
  }
}, { immediate: false });

async function loadStore() {
  try {
    const response = await api.get(`/stores/${storeId}`);
    store.value = response.data.data;
  } catch (err: any) {
    console.error('Failed to load store:', err);
    error.value = 'Failed to load store';
  }
}

async function loadAddresses() {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get(`/stores/${storeId}/lightning-addresses`);
    addresses.value = response.data.data || [];
  } catch (err: any) {
    console.error('Failed to load addresses:', err);
    error.value = err.response?.data?.message || 'Failed to load lightning addresses';
  } finally {
    loading.value = false;
  }
}

function openAddForm() {
  form.value = {
    username: '',
    currencyCode: '',
    min: '',
    max: '',
    invoiceMetadata: '',
  };
  showAdvancedSettings.value = false;
  showForm.value = true;
  error.value = '';
}

function closeForm() {
  showForm.value = false;
  error.value = '';
}

async function handleSubmit() {
  saving.value = true;
  error.value = '';

  try {
    const data: any = {
      username: form.value.username,
    };

    // Always send currencyCode, min, max - send as null if empty
    data.currencyCode = form.value.currencyCode && form.value.currencyCode.trim() ? form.value.currencyCode.trim() : null;
    data.min = form.value.min && form.value.min.trim() ? form.value.min.trim() : null;
    data.max = form.value.max && form.value.max.trim() ? form.value.max.trim() : null;
    
    // Parse invoiceMetadata JSON string
    if (form.value.invoiceMetadata && form.value.invoiceMetadata.trim()) {
      try {
        const parsed = JSON.parse(form.value.invoiceMetadata.trim());
        if (typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)) {
          data.invoiceMetadata = parsed;
        } else {
          error.value = 'Invoice Metadata must be a valid JSON object (not an array or primitive value)';
          saving.value = false;
          return;
        }
      } catch (e) {
        error.value = 'Invalid JSON format in Invoice Metadata. Please check your syntax.';
        saving.value = false;
        return;
      }
    } else {
      // Send empty object if no metadata provided
      data.invoiceMetadata = {};
    }

    await api.post(`/stores/${storeId}/lightning-addresses/${form.value.username}`, data);

    await loadAddresses();
    closeForm();
  } catch (err: any) {
    console.error('Failed to save address:', err);
    error.value = err.response?.data?.message || 'Failed to save lightning address';
  } finally {
    saving.value = false;
  }
}

function confirmDelete(address: any) {
  addressToDelete.value = address;
  showDeleteModal.value = true;
  deleteError.value = '';
}

function closeDeleteModal() {
  showDeleteModal.value = false;
  addressToDelete.value = null;
  deleteError.value = '';
}

async function handleDelete() {
  if (!addressToDelete.value) return;

  deleting.value = true;
  deleteError.value = '';

  try {
    await api.delete(`/stores/${storeId}/lightning-addresses/${addressToDelete.value.username}`);
    await loadAddresses();
    closeDeleteModal();
  } catch (err: any) {
    console.error('Failed to delete address:', err);
    deleteError.value = err.response?.data?.message || 'Failed to delete lightning address';
  } finally {
    deleting.value = false;
  }
}

async function copyToClipboard(text: string) {
  try {
    await navigator.clipboard.writeText(text);
    // You could show a toast notification here
  } catch (err) {
    console.error('Failed to copy:', err);
  }
}

function handleShowSettings() {
  router.push({ name: 'stores-settings', params: { id: storeId } });
}

function handleShowSection(section: string) {
  router.push({ name: 'stores-show', params: { id: storeId }, query: { section } });
}
</script>

