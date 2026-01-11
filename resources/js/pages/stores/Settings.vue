<template>
  <div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="mb-6">
        <router-link
          :to="`/stores/${storeId}`"
          class="text-indigo-600 hover:text-indigo-500 text-sm"
        >
          ← Back to Store
        </router-link>
      </div>

      <div v-if="loading && !settings" class="text-center py-12">
        <p class="text-gray-500">Loading settings...</p>
      </div>

      <div v-else-if="settings" class="bg-white shadow rounded-lg">
        <div class="px-6 py-5 border-b border-gray-200">
          <h1 class="text-3xl font-bold text-gray-900">Store Settings</h1>
        </div>

        <div class="px-6 py-5 space-y-8">
          <!-- Editable Fields -->
          <div>
            <h2 class="text-lg font-medium text-gray-900 mb-4">Store Information</h2>
            <form @submit.prevent="handleSubmit" class="space-y-6">
              <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Store Name</label>
                <input
                  id="name"
                  v-model="form.name"
                  type="text"
                  required
                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                />
              </div>

              <div>
                <label for="default_currency" class="block text-sm font-medium text-gray-700">Default Currency</label>
                <select
                  id="default_currency"
                  v-model="form.default_currency"
                  required
                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                >
                  <option value="USD">USD</option>
                  <option value="EUR">EUR</option>
                  <option value="BTC">BTC</option>
                </select>
              </div>

              <div v-if="error" class="rounded-md bg-red-50 p-4">
                <div class="text-sm text-red-800">{{ error }}</div>
              </div>

              <div v-if="success" class="rounded-md bg-green-50 p-4">
                <div class="text-sm text-green-800">{{ success }}</div>
              </div>

              <div class="flex justify-end">
                <button
                  type="submit"
                  :disabled="saving"
                  class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                >
                  {{ saving ? 'Saving...' : 'Save Changes' }}
                </button>
              </div>
            </form>
          </div>

          <!-- Read-only Fields -->
          <div class="border-t border-gray-200 pt-8">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Advanced Settings</h2>
            <p class="text-sm text-gray-600 mb-4">
              The following settings can be configured in BTCPay Server:
            </p>

            <dl class="space-y-4">
              <div>
                <dt class="text-sm font-medium text-gray-500">Payment Methods</dt>
                <dd class="mt-1 text-sm text-gray-900">
                  Configure payment methods in BTCPay Server
                </dd>
                <dd class="mt-1">
                  <a
                    :href="settings.btcpay_store_url + '/PaymentMethods'"
                    target="_blank"
                    class="text-sm text-indigo-600 hover:text-indigo-500"
                  >
                    Open Payment Methods in BTCPay →
                  </a>
                </dd>
              </div>

              <div>
                <dt class="text-sm font-medium text-gray-500">Invoice Settings</dt>
                <dd class="mt-1 text-sm text-gray-900">
                  Configure invoice expiration and other invoice settings
                </dd>
                <dd class="mt-1">
                  <a
                    :href="settings.btcpay_store_url + '/Invoices'"
                    target="_blank"
                    class="text-sm text-indigo-600 hover:text-indigo-500"
                  >
                    Open Invoice Settings in BTCPay →
                  </a>
                </dd>
              </div>

              <div>
                <dt class="text-sm font-medium text-gray-500">Store Management</dt>
                <dd class="mt-1">
                  <a
                    :href="settings.btcpay_store_url"
                    target="_blank"
                    class="text-sm text-indigo-600 hover:text-indigo-500"
                  >
                    Open Store in BTCPay →
                  </a>
                </dd>
              </div>
            </dl>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../services/api';

const route = useRoute();
const storeId = route.params.id as string;

const loading = ref(false);
const saving = ref(false);
const settings = ref<any>(null);
const error = ref('');
const success = ref('');

const form = ref({
  name: '',
  default_currency: 'USD',
});

async function fetchSettings() {
  loading.value = true;
  try {
    const response = await api.get(`/stores/${storeId}/settings`);
    settings.value = response.data.data;
    form.value.name = settings.value.name;
    form.value.default_currency = settings.value.default_currency || 'USD';
  } finally {
    loading.value = false;
  }
}

async function handleSubmit() {
  error.value = '';
  success.value = '';
  saving.value = true;

  try {
    await api.put(`/stores/${storeId}/settings`, form.value);
    success.value = 'Settings updated successfully';
    await fetchSettings();
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to update settings. Please try again.';
    if (err.response?.data?.errors) {
      const errors = Object.values(err.response.data.errors).flat();
      error.value = errors.join(', ');
    }
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  fetchSettings();
});
</script>

