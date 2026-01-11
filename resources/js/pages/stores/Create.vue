<template>
  <div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
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
          <div>
            <label for="timezone" class="block text-sm font-medium text-gray-700">Timezone</label>
            <select
              id="timezone"
              v-model="form.timezone"
              required
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            >
              <option value="UTC">UTC</option>
              <option value="America/New_York">America/New_York</option>
              <option value="Europe/London">Europe/London</option>
              <option value="Asia/Tokyo">Asia/Tokyo</option>
            </select>
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
              <div class="flex justify-between">
                <dt class="text-sm text-gray-600">Wallet Type:</dt>
                <dd class="text-sm font-medium text-gray-900">{{ form.wallet_type === 'blink' ? 'Blink' : 'Aqua (Boltz)' }}</dd>
              </div>
            </dl>
          </div>
          <div v-if="error" class="rounded-md bg-red-50 p-4">
            <div class="text-sm text-red-800">{{ error }}</div>
          </div>
          <div class="flex justify-between">
            <button
              @click="currentStep = 2"
              class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
              Back
            </button>
            <button
              @click="handleSubmit"
              :disabled="loading"
              class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
              {{ loading ? 'Creating...' : 'Create Store' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useStoresStore } from '../../store/stores';

const router = useRouter();
const storesStore = useStoresStore();

const currentStep = ref(1);
const loading = ref(false);
const error = ref('');

const form = ref({
  name: '',
  default_currency: 'USD',
  timezone: 'UTC',
  wallet_type: '' as 'blink' | 'aqua_boltz' | '',
});

async function handleSubmit() {
  error.value = '';
  loading.value = true;

  try {
    const store = await storesStore.createStore(form.value);
    router.push(`/stores/${store.id}/next-steps`);
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

