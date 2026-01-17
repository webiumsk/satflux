<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h1 class="text-3xl font-bold text-gray-900 mb-8">Create Store</h1>

      <div class="bg-white shadow rounded-lg p-6">
        <!-- Step Indicator -->
        <div class="mb-8">
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <div :class="['w-8 h-8 rounded-full flex items-center justify-center', currentStep >= 1 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600']">
                1
              </div>
              <span :class="['ml-2', currentStep >= 1 ? 'text-indigo-600' : 'text-gray-600']">Basic Info</span>
            </div>
            <div class="flex-1 h-1 mx-4" :class="currentStep >= 2 ? 'bg-indigo-600' : 'bg-gray-200'"></div>
            <div class="flex items-center">
              <div :class="['w-8 h-8 rounded-full flex items-center justify-center', currentStep >= 2 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600']">
                2
              </div>
              <span :class="['ml-2', currentStep >= 2 ? 'text-indigo-600' : 'text-gray-600']">Wallet Type</span>
            </div>
            <div class="flex-1 h-1 mx-4" :class="currentStep >= 3 ? 'bg-indigo-600' : 'bg-gray-200'"></div>
            <div class="flex items-center">
              <div :class="['w-8 h-8 rounded-full flex items-center justify-center', currentStep >= 3 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600']">
                3
              </div>
              <span :class="['ml-2', currentStep >= 3 ? 'text-indigo-600' : 'text-gray-600']">Confirm</span>
            </div>
          </div>
        </div>

        <!-- Step 1: Basic Info -->
        <div v-if="currentStep === 1" class="space-y-6">
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
          <div class="flex justify-end">
            <button
              @click="currentStep = 2"
              :disabled="!form.name || !form.default_currency || !form.timezone"
              class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
              Next
            </button>
          </div>
        </div>

        <!-- Step 2: Wallet Type -->
        <div v-if="currentStep === 2" class="space-y-6">
          <div>
            <p class="text-sm text-gray-600 mb-4">Choose your Lightning wallet backend:</p>
            <div class="space-y-4">
              <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer" :class="form.wallet_type === 'blink' ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200'">
                <input
                  type="radio"
                  v-model="form.wallet_type"
                  value="blink"
                  class="mt-1 mr-3"
                />
                <div>
                  <div class="font-medium text-gray-900">Blink</div>
                  <div class="text-sm text-gray-500">Use Blink wallet for Lightning payments</div>
                </div>
              </label>
              <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer" :class="form.wallet_type === 'aqua_boltz' ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200'">
                <input
                  type="radio"
                  v-model="form.wallet_type"
                  value="aqua_boltz"
                  class="mt-1 mr-3"
                />
                <div>
                  <div class="font-medium text-gray-900">Aqua Wallet (via Boltz plugin)</div>
                  <div class="text-sm text-gray-500">Use Aqua wallet with Boltz plugin</div>
                </div>
              </label>
            </div>
          </div>
          
          <!-- Connection String Input -->
          <div v-if="form.wallet_type">
            <label :for="form.wallet_type === 'blink' ? 'connection_string_blink' : 'connection_string_aqua'" class="block text-sm font-medium text-gray-700 mb-2">
              {{ form.wallet_type === 'blink' ? 'Connection String' : 'Descriptor' }}
            </label>
            <textarea
              :id="form.wallet_type === 'blink' ? 'connection_string_blink' : 'connection_string_aqua'"
              v-model="form.connection_string"
              rows="4"
              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono text-sm"
              :placeholder="form.wallet_type === 'blink' 
                ? 'type=blink;server=https://api.blink.sv/graphql;api-key=blink_xxx;wallet-id=xxx'
                : 'wpkh([fingerprint/hdpath]xpub...)'"
            ></textarea>
            <p class="mt-1 text-sm text-gray-500">
              <span v-if="form.wallet_type === 'blink'">
                Format: <code class="bg-gray-100 px-1 py-0.5 rounded">type=blink;server=https://...;api-key=...;wallet-id=...</code><br>
                Paste your Blink connection string with server URL, API key, and wallet ID.
              </span>
              <span v-else>
                Paste your Bitcoin Core output descriptor (watch-only, no private keys).<br>
                Example formats: <code class="bg-gray-100 px-1 py-0.5 rounded">wpkh(...)</code>, <code class="bg-gray-100 px-1 py-0.5 rounded">tr(...)</code>
              </span>
            </p>
            <p class="mt-1 text-sm text-gray-400">
              You can also configure this later in Wallet Connection settings.
            </p>
          </div>
          
          <div class="flex justify-between">
            <button
              @click="currentStep = 1"
              class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
              Back
            </button>
            <button
              @click="currentStep = 3"
              :disabled="!form.wallet_type"
              class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
              Next
            </button>
          </div>
        </div>

        <!-- Step 3: Confirmation -->
        <div v-if="currentStep === 3" class="space-y-6">
          <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="font-medium text-gray-900 mb-4">Review your store settings:</h3>
            <dl class="space-y-2">
              <div class="flex justify-between">
                <dt class="text-sm text-gray-600">Store Name:</dt>
                <dd class="text-sm font-medium text-gray-900">{{ form.name }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-gray-600">Default Currency:</dt>
                <dd class="text-sm font-medium text-gray-900">{{ form.default_currency }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-gray-600">Timezone:</dt>
                <dd class="text-sm font-medium text-gray-900">{{ form.timezone }}</dd>
              </div>
              <div class="flex justify-between" v-if="form.preferred_exchange">
                <dt class="text-sm text-gray-600">Preferred Price Source:</dt>
                <dd class="text-sm font-medium text-gray-900">{{ exchanges.find(e => e.value === form.preferred_exchange)?.label || form.preferred_exchange }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-gray-600">Wallet Type:</dt>
                <dd class="text-sm font-medium text-gray-900">{{ form.wallet_type === 'blink' ? 'Blink' : 'Aqua (Boltz)' }}</dd>
              </div>
              <div class="flex justify-between" v-if="form.connection_string">
                <dt class="text-sm text-gray-600">Connection:</dt>
                <dd class="text-sm font-medium text-gray-900">
                  <span class="text-green-600">Configured</span>
                  <span class="text-gray-400 text-xs ml-2">({{ form.wallet_type === 'blink' ? 'Connection string' : 'Descriptor' }} provided)</span>
                </dd>
              </div>
            </dl>
          </div>
          <div v-if="error" class="rounded-md bg-red-50 p-4">
            <div class="text-sm text-red-800">{{ error }}</div>
          </div>
          <div class="flex justify-between">
            <button
              @click="currentStep = 2"
              :disabled="loading"
              class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
            >
              Back
            </button>
            <button
              @click="handleSubmit"
              :disabled="loading"
              class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
              {{ loading ? 'Creating...' : 'Go to Store' }}
            </button>
          </div>
        </div>
      </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useStoresStore } from '../../store/stores';
import { currencies } from '../../data/currencies';
import { exchanges } from '../../data/exchanges';

const router = useRouter();
const storesStore = useStoresStore();

const currentStep = ref(1);
const loading = ref(false);
const error = ref('');

const form = ref({
  name: '',
  default_currency: 'USD',
  timezone: 'UTC',
  preferred_exchange: '',
  wallet_type: '' as 'blink' | 'aqua_boltz' | '',
  connection_string: '',
});

// Common timezones - you can expand this list
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

const currencyOptions = computed(() => currencies.map(c => `${c.code} - ${c.name}`));

async function handleSubmit() {
  error.value = '';
  loading.value = true;

  try {
    const store = await storesStore.createStore(form.value);
    router.push(`/stores/${store.id}`);
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to create store. Please try again.';
    if (err.response?.data?.errors) {
      const errors = Object.values(err.response.data.errors).flat();
      error.value = errors.join(', ');
    }
  } finally {
    loading.value = false;
  }
}
</script>








