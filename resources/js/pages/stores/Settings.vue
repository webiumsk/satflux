<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
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
                <input
                  id="default_currency"
                  v-model="form.default_currency"
                  type="text"
                  list="currency-selection-suggestion"
                  required
                  placeholder="Select or type currency (e.g., USD, BTC, EUR)"
                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                />
                <datalist id="currency-selection-suggestion">
                  <option v-for="currency in currencies" :key="currency.code" :value="currency.code">
                    {{ currency.code }} - {{ currency.name }}
                  </option>
                </datalist>
              </div>

              <div>
                <label for="timezone" class="block text-sm font-medium text-gray-700">Timezone</label>
                <select
                  id="timezone"
                  v-model="form.timezone"
                  required
                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                >
                  <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                </select>
              </div>

              <div>
                <label for="preferred_exchange" class="block text-sm font-medium text-gray-700">
                  Preferred Price Source
                </label>
                <select
                  id="preferred_exchange"
                  v-model="form.preferred_exchange"
                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                >
                  <option v-for="exchange in exchanges" :key="exchange.value" :value="exchange.value">
                    {{ exchange.label }}
                  </option>
                </select>
                <p class="mt-2 text-sm text-gray-500">The recommended price source gets chosen based on the default currency.</p>
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
            <h2 class="text-lg font-medium text-gray-900 mb-4">Additional Settings</h2>
            <p class="text-sm text-gray-600 mb-4">
              Additional store configuration options are managed through the UZOL21 interface.
              All settings that can be configured are available above or through other UZOL21 sections.
            </p>
          </div>
        </div>
      </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../services/api';
import { currencies } from '../../data/currencies';
import { exchanges } from '../../data/exchanges';

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
  timezone: 'UTC',
  preferred_exchange: '',
});

// Common timezones - same as in Create.vue
const timezones = [
  'UTC',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'America/Phoenix',
  'America/Toronto',
  'America/Vancouver',
  'America/Sao_Paulo',
  'America/Argentina/Buenos_Aires',
  'America/Santiago',
  'America/Mexico_City',
  'Europe/London',
  'Europe/Paris',
  'Europe/Berlin',
  'Europe/Rome',
  'Europe/Madrid',
  'Europe/Amsterdam',
  'Europe/Brussels',
  'Europe/Vienna',
  'Europe/Prague',
  'Europe/Warsaw',
  'Europe/Stockholm',
  'Europe/Copenhagen',
  'Europe/Helsinki',
  'Europe/Athens',
  'Europe/Istanbul',
  'Europe/Moscow',
  'Europe/Kiev',
  'Asia/Dubai',
  'Asia/Tokyo',
  'Asia/Shanghai',
  'Asia/Hong_Kong',
  'Asia/Singapore',
  'Asia/Seoul',
  'Asia/Bangkok',
  'Asia/Jakarta',
  'Asia/Manila',
  'Asia/Kolkata',
  'Asia/Karachi',
  'Asia/Dhaka',
  'Asia/Tehran',
  'Asia/Jerusalem',
  'Australia/Sydney',
  'Australia/Melbourne',
  'Australia/Brisbane',
  'Australia/Perth',
  'Pacific/Auckland',
  'Pacific/Honolulu',
];

async function fetchSettings() {
  loading.value = true;
  try {
    const response = await api.get(`/stores/${storeId}/settings`);
    settings.value = response.data.data;
    form.value.name = settings.value.name;
    form.value.default_currency = settings.value.default_currency || 'USD';
    form.value.timezone = settings.value.timezone || 'UTC';
    form.value.preferred_exchange = settings.value.preferred_exchange || '';
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








