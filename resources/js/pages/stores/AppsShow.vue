<template>
  <div v-if="loading && !app" class="flex items-center justify-center h-full bg-gray-100">
    <p class="text-gray-500">Loading app...</p>
  </div>

  <div v-else-if="store && app" class="flex h-full bg-gray-100">
    <!-- Sidebar -->
    <StoreSidebar
      :store="store"
      :apps="allApps"
      @create-app="handleCreateApp"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
    />

    <!-- Main Content -->
    <div class="flex-1 overflow-y-auto">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <template v-if="app.app_type === 'PointOfSale'">
        <div class="space-y-6">
          <!-- App Header with Terminal Link -->
          <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ app.name || 'Point of Sale' }}</h1>
                <p class="text-sm text-gray-500">Point of Sale App</p>
              </div>
              <div v-if="app.btcpay_app_url">
                <a
                  :href="app.btcpay_app_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                  </svg>
                  Open PoS
                </a>
              </div>
            </div>
          </div>

        <!-- Settings Form -->
        <div class="bg-white shadow rounded-lg">
          <div class="px-6 py-5 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Settings</h2>
          </div>

          <form @submit.prevent="handleSubmit" class="px-6 py-5 space-y-6">
            <!-- App Name -->
            <div>
              <label for="appName" class="block text-sm font-medium text-gray-700">
                App Name
              </label>
              <input
                id="appName"
                v-model="form.appName"
                type="text"
                required
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              />
            </div>

            <!-- Title -->
            <div>
              <label for="title" class="block text-sm font-medium text-gray-700">
                Title
              </label>
              <input
                id="title"
                v-model="form.title"
                type="text"
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              />
            </div>

            <!-- Description -->
            <div>
              <label for="description" class="block text-sm font-medium text-gray-700">
                Description
              </label>
              <textarea
                id="description"
                v-model="form.description"
                rows="3"
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              ></textarea>
            </div>

            <!-- Default View -->
            <div>
              <label for="defaultView" class="block text-sm font-medium text-gray-700">
                Default View
              </label>
              <select
                id="defaultView"
                v-model="form.defaultView"
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              >
                <option value="Static">Static</option>
                <option value="Cart">Cart</option>
                <option value="Light">Light</option>
                <option value="Print">Print</option>
              </select>
            </div>

            <!-- Currency -->
            <div>
              <label for="currency" class="block text-sm font-medium text-gray-700">
                Currency
              </label>
              <input
                id="currency"
                v-model="form.currency"
                type="text"
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              />
            </div>

            <!-- Options -->
            <div class="space-y-4">
              <h3 class="text-sm font-medium text-gray-900">Display Options</h3>
              
              <div class="flex items-center">
                <input
                  id="showItems"
                  v-model="form.showItems"
                  type="checkbox"
                  class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                />
                <label for="showItems" class="ml-2 block text-sm text-gray-900">
                  Show Items
                </label>
              </div>

              <div class="flex items-center">
                <input
                  id="showCustomAmount"
                  v-model="form.showCustomAmount"
                  type="checkbox"
                  class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                />
                <label for="showCustomAmount" class="ml-2 block text-sm text-gray-900">
                  Show Custom Amount
                </label>
              </div>

              <div class="flex items-center">
                <input
                  id="showDiscount"
                  v-model="form.showDiscount"
                  type="checkbox"
                  class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                />
                <label for="showDiscount" class="ml-2 block text-sm text-gray-900">
                  Show Discount
                </label>
              </div>

              <div class="flex items-center">
                <input
                  id="showSearch"
                  v-model="form.showSearch"
                  type="checkbox"
                  class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                />
                <label for="showSearch" class="ml-2 block text-sm text-gray-900">
                  Show Search
                </label>
              </div>

              <div class="flex items-center">
                <input
                  id="showCategories"
                  v-model="form.showCategories"
                  type="checkbox"
                  class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                />
                <label for="showCategories" class="ml-2 block text-sm text-gray-900">
                  Show Categories
                </label>
              </div>

              <div class="flex items-center">
                <input
                  id="enableTips"
                  v-model="form.enableTips"
                  type="checkbox"
                  class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                />
                <label for="enableTips" class="ml-2 block text-sm text-gray-900">
                  Enable Tips
                </label>
              </div>
            </div>

            <!-- Button Text -->
            <div>
              <label for="fixedAmountPayButtonText" class="block text-sm font-medium text-gray-700">
                Fixed Amount Button Text
              </label>
              <input
                id="fixedAmountPayButtonText"
                v-model="form.fixedAmountPayButtonText"
                type="text"
                placeholder="Buy for {0}"
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              />
            </div>

            <div>
              <label for="customAmountPayButtonText" class="block text-sm font-medium text-gray-700">
                Custom Amount Button Text
              </label>
              <input
                id="customAmountPayButtonText"
                v-model="form.customAmountPayButtonText"
                type="text"
                placeholder="Pay"
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              />
            </div>

            <!-- Error/Success Messages -->
            <div v-if="error" class="rounded-md bg-red-50 p-4">
              <div class="text-sm text-red-800">{{ error }}</div>
            </div>

            <div v-if="success" class="rounded-md bg-green-50 p-4">
              <div class="text-sm text-green-800">{{ success }}</div>
            </div>

            <!-- Submit and Delete Buttons -->
            <div class="flex justify-between items-center pt-6 border-t border-gray-200">
              <button
                type="button"
                @click="showDeleteModal = true"
                :disabled="saving || deleting"
                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
              >
                Delete App
              </button>
              
              <button
                type="submit"
                :disabled="saving"
                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
              >
                {{ saving ? 'Saving...' : 'Save Settings' }}
              </button>
            </div>
          </form>
        </div>

        <!-- Delete Confirmation Modal -->
        <div
          v-if="showDeleteModal"
          class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
          @click.self="showDeleteModal = false"
        >
          <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
              <h3 class="text-lg font-medium text-gray-900 mb-4">
                Delete {{ app.name }}?
              </h3>
              <p class="text-sm text-gray-600 mb-4">
                This action cannot be undone. This will permanently delete the app and all its data.
              </p>
              <p class="text-sm font-medium text-gray-700 mb-2">
                Please type <span class="font-mono text-red-600">DELETE</span> to confirm:
              </p>
              <input
                v-model="deleteConfirmText"
                type="text"
                placeholder="Type DELETE"
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm"
                :class="{ 'border-red-500': deleteConfirmText && deleteConfirmText !== 'DELETE' }"
              />
              <div v-if="deleteError" class="mt-2 text-sm text-red-600">
                {{ deleteError }}
              </div>
              <div class="flex justify-end space-x-3 mt-6">
                <button
                  type="button"
                  @click="showDeleteModal = false; deleteConfirmText = ''; deleteError = '';"
                  :disabled="deleting"
                  class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 disabled:opacity-50"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  @click="handleDelete"
                  :disabled="deleting || deleteConfirmText !== 'DELETE'"
                  class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
                >
                  {{ deleting ? 'Deleting...' : 'Delete App' }}
                </button>
              </div>
            </div>
          </div>
        </div>
        </div>
        </template>

        <template v-else-if="app">
          <div class="bg-white shadow rounded-lg p-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ app.name }}</h1>
            <p class="text-sm text-gray-500">Type: {{ app.app_type }}</p>
            <p class="mt-4 text-gray-600">Settings for this app type are not yet implemented.</p>
          </div>
        </template>
      </div>
    </div>
  </div>

  <div v-else class="flex items-center justify-center h-full bg-gray-100">
    <p class="text-gray-500">App not found</p>
  </div>
  
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAppsStore } from '../../store/apps';
import { useStoresStore } from '../../store/stores';
import StoreSidebar from '../../components/stores/StoreSidebar.vue';

const route = useRoute();
const router = useRouter();
const appsStore = useAppsStore();
const storesStore = useStoresStore();

const storeId = route.params.id as string;
const appId = route.params.appId as string;
const loading = ref(false);
const saving = ref(false);
const store = ref<any>(null);
const app = ref<any>(null);
const error = ref('');
const success = ref('');
const showDeleteModal = ref(false);
const deleteConfirmText = ref('');
const deleteError = ref('');
const deleting = ref(false);

const allApps = computed(() => appsStore.apps);

const form = ref({
  appName: '',
  title: '',
  description: '',
  defaultView: 'Static',
  currency: 'EUR',
  showItems: false,
  showCustomAmount: false,
  showDiscount: false,
  showSearch: false,
  showCategories: false,
  enableTips: false,
  fixedAmountPayButtonText: 'Buy for {0}',
  customAmountPayButtonText: 'Pay',
});

async function loadApp() {
  loading.value = true;
  error.value = '';
  try {
    // Load store and apps first
    if (!store.value) {
      store.value = await storesStore.fetchStore(storeId);
    }
    await appsStore.fetchApps(storeId);
    
    // Then load the specific app
    app.value = await appsStore.fetchApp(storeId, appId);
    
    // Populate form with app data
    if (app.value) {
      form.value.appName = app.value.name || '';
      
      // Load config from app.config (BTCPay API response)
      if (app.value.config) {
        const config = app.value.config;
        form.value.title = config.title || '';
        form.value.description = config.description || '';
        form.value.defaultView = config.defaultView || 'Static';
        form.value.currency = config.currency || 'EUR';
        form.value.showItems = config.showItems || false;
        form.value.showCustomAmount = config.showCustomAmount || false;
        form.value.showDiscount = config.showDiscount || false;
        form.value.showSearch = config.showSearch ?? true;
        form.value.showCategories = config.showCategories ?? true;
        form.value.enableTips = config.enableTips || false;
        form.value.fixedAmountPayButtonText = config.fixedAmountPayButtonText || 'Buy for {0}';
        form.value.customAmountPayButtonText = config.customAmountPayButtonText || 'Pay';
      }
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load app';
  } finally {
    loading.value = false;
  }
}

async function handleSubmit() {
  saving.value = true;
  error.value = '';
  success.value = '';
  
  try {
    // Update app via API
    await appsStore.updateApp(storeId, appId, {
      name: form.value.appName,
      config: {
        appName: form.value.appName,
        title: form.value.title,
        description: form.value.description,
        defaultView: form.value.defaultView,
        currency: form.value.currency,
        showItems: form.value.showItems,
        showCustomAmount: form.value.showCustomAmount,
        showDiscount: form.value.showDiscount,
        showSearch: form.value.showSearch,
        showCategories: form.value.showCategories,
        enableTips: form.value.enableTips,
        fixedAmountPayButtonText: form.value.fixedAmountPayButtonText,
        customAmountPayButtonText: form.value.customAmountPayButtonText,
      },
    });
    
    success.value = 'Settings saved successfully';
    
    // Reload app to get updated data
    await loadApp();
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to save settings';
  } finally {
    saving.value = false;
  }
}

function handleCreateApp() {
  // Handle create app - not needed here but required by StoreSidebar
}

function handleShowSettings() {
  router.push({ name: 'stores-show', params: { id: storeId }, query: { section: 'settings' } });
}

function handleShowSection(section: string) {
  router.push({ name: 'stores-show', params: { id: storeId }, query: { section } });
}

async function handleDelete() {
  if (deleteConfirmText.value !== 'DELETE') {
    deleteError.value = 'Please type DELETE to confirm';
    return;
  }

  deleting.value = true;
  deleteError.value = '';
  
  try {
    await appsStore.deleteApp(storeId, appId);
    
    // Redirect to store page after successful deletion
    router.push({ name: 'stores-show', params: { id: storeId } });
  } catch (err: any) {
    deleteError.value = err.response?.data?.message || 'Failed to delete app';
  } finally {
    deleting.value = false;
  }
}

onMounted(() => {
  loadApp();
});

// Reload if route changes
watch(() => route.params.appId, () => {
  if (route.params.appId) {
    loadApp();
  }
});
</script>
