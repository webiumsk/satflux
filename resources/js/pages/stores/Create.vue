<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <h1 class="text-3xl font-bold text-white mb-8 text-center">{{ t('stores.create_store') }}</h1>

      <div class="bg-gray-800 shadow-2xl rounded-2xl border border-gray-700 overflow-hidden">
        <!-- Step Indicator -->
        <div class="bg-gray-900/50 border-b border-gray-700 px-8 py-6">
          <div class="flex items-center justify-between relative">
             <!-- Connecting Line -->
            <div class="absolute left-0 top-1/2 transform -translate-y-1/2 w-full h-1 bg-gray-700 -z-0"></div>
            <div class="absolute left-0 top-1/2 transform -translate-y-1/2 h-1 bg-indigo-600 transition-all duration-500 ease-in-out -z-0" :style="{ width: ((currentStep - 1) / 2) * 100 + '%' }"></div>
            
            <div class="flex flex-col items-center relative z-10 group cursor-pointer" @click="currentStep > 1 ? currentStep = 1 : null">
              <div :class="['w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg transition-all', currentStep >= 1 ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/40 ring-4 ring-gray-800' : 'bg-gray-700 text-gray-400 border-2 border-gray-600']">
                1
              </div>
              <span class="mt-2 text-xs font-semibold uppercase tracking-wider transition-colors bg-gray-800 px-2 rounded" :class="currentStep >= 1 ? 'text-indigo-400' : 'text-gray-500'">{{ t('stores.basic_info') }}</span>
            </div>
            
            <div class="flex flex-col items-center relative z-10 group cursor-pointer" @click="currentStep > 2 ? currentStep = 2 : null">
              <div :class="['w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg transition-all', currentStep >= 2 ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/40 ring-4 ring-gray-800' : 'bg-gray-700 text-gray-400 border-2 border-gray-600']">
                2
              </div>
               <span class="mt-2 text-xs font-semibold uppercase tracking-wider transition-colors bg-gray-800 px-2 rounded" :class="currentStep >= 2 ? 'text-indigo-400' : 'text-gray-500'">{{ t('stores.wallet_type') }}</span>
            </div>
            
            <div class="flex flex-col items-center relative z-10">
              <div :class="['w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg transition-all', currentStep >= 3 ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/40 ring-4 ring-gray-800' : 'bg-gray-700 text-gray-400 border-2 border-gray-600']">
                3
              </div>
               <span class="mt-2 text-xs font-semibold uppercase tracking-wider transition-colors bg-gray-800 px-2 rounded" :class="currentStep >= 3 ? 'text-indigo-400' : 'text-gray-500'">{{ t('stores.confirm') }}</span>
            </div>
          </div>
        </div>

        <div class="p-8 sm:p-10">
            <!-- Step 1: Basic Info -->
            <div v-if="currentStep === 1" class="space-y-6">
              <div>
                <label for="name" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.store_name') }}</label>
                <input
                  id="name"
                  v-model="form.name"
                  type="text"
                  required
                  placeholder="My Awesome Store"
                  class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                />
              </div>
              <div>
                <label for="default_currency" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.default_currency') }}</label>
                <input
                  id="default_currency"
                  v-model="form.default_currency"
                  type="text"
                  list="currency-selection-suggestion"
                  required
                  placeholder="Select or type currency (e.g., USD, BTC, EUR)"
                 class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                />
                <datalist id="currency-selection-suggestion">
                  <option v-for="currency in currencies" :key="currency.code" :value="currency.code">
                    {{ currency.code }} - {{ currency.name }}
                  </option>
                </datalist>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label for="timezone" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.timezone') }}</label>
                    <select
                      id="timezone"
                      v-model="form.timezone"
                      required
                      class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                    >
                      <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                    </select>
                  </div>
                  <div>
                    <label for="preferred_exchange" class="block text-sm font-medium text-gray-300 mb-1">
                      {{ t('stores.preferred_price_source') }}
                    </label>
                    <select
                      id="preferred_exchange"
                      v-model="form.preferred_exchange"
                      class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                    >
                      <option v-for="exchange in exchanges" :key="exchange.value" :value="exchange.value">
                        {{ exchange.label }}
                      </option>
                    </select>
                  </div>
              </div>
              <p class="text-xs text-gray-500">{{ t('stores.recommended_price_source') }}</p>
              
              <div class="flex justify-end pt-4">
                <button
                  @click="currentStep = 2"
                  :disabled="!form.name || !form.default_currency || !form.timezone"
                  class="inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                >
                  Next Step
                  <svg class="ml-2 -mr-1 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                </button>
              </div>
            </div>

            <!-- Step 2: Wallet Type -->
            <div v-if="currentStep === 2" class="space-y-6">
              <div>
                <p class="text-sm font-medium text-gray-300 mb-4 uppercase tracking-wider">{{ t('stores.choose_lightning_wallet') }}</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <label 
                    class="relative flex flex-col p-6 border-2 rounded-2xl cursor-pointer transition-all hover:bg-gray-700/50" 
                    :class="form.wallet_type === 'blink' ? 'border-indigo-500 bg-indigo-900/10' : 'border-gray-600 bg-gray-800'"
                  >
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-white text-lg">{{ t('stores.blink_wallet') }}</span>
                        <div v-if="form.wallet_type === 'blink'" class="w-6 h-6 rounded-full bg-indigo-500 flex items-center justify-center text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                        </div>
                        <div v-else class="w-6 h-6 rounded-full border-2 border-gray-500"></div>
                    </div>
                    <p class="text-sm text-gray-400">{{ t('stores.blink_description') }}</p>
                    <input type="radio" v-model="form.wallet_type" value="blink" class="hidden" />
                  </label>

                  <label 
                    class="relative flex flex-col p-6 border-2 rounded-2xl cursor-pointer transition-all hover:bg-gray-700/50" 
                    :class="form.wallet_type === 'aqua_boltz' ? 'border-indigo-500 bg-indigo-900/10' : 'border-gray-600 bg-gray-800'"
                  >
                     <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-white text-lg">{{ t('stores.aqua_wallet') }}</span>
                         <div v-if="form.wallet_type === 'aqua_boltz'" class="w-6 h-6 rounded-full bg-indigo-500 flex items-center justify-center text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                        </div>
                        <div v-else class="w-6 h-6 rounded-full border-2 border-gray-500"></div>
                    </div>
                    <p class="text-sm text-gray-400">{{ t('stores.aqua_description') }}</p>
                    <input type="radio" v-model="form.wallet_type" value="aqua_boltz" class="hidden" />
                  </label>
                </div>
              </div>
              
              <!-- Connection String Input -->
              <transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
              >
                  <div v-if="form.wallet_type" class="bg-gray-900/50 rounded-xl p-6 border border-gray-700">
                    <label :for="form.wallet_type === 'blink' ? 'connection_string_blink' : 'connection_string_aqua'" class="block text-sm font-medium text-indigo-300 mb-2">
                      {{ form.wallet_type === 'blink' ? t('stores.connection_string') : t('stores.descriptor') }}
                    </label>
                    <div class="relative">
                        <textarea
                          :id="form.wallet_type === 'blink' ? 'connection_string_blink' : 'connection_string_aqua'"
                          v-model="form.connection_string"
                          rows="4"
                          class="block w-full rounded-lg border-gray-600 bg-gray-800 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono text-sm placeholder-gray-600"
                          :class="{
                            'border-yellow-500': duplicateWarning?.exists && form.wallet_type === 'aqua_boltz',
                            'border-red-500': formatError
                          }"
                          :placeholder="form.wallet_type === 'blink' 
                            ? 'type=blink;server=https://api.blink.sv/graphql;api-key=blink_xxx;wallet-id=xxx'
                            : 'wpkh([fingerprint/hdpath]xpub...)'"
                        ></textarea>
                        <div v-if="checkingDuplicate" class="absolute top-2 right-2">
                            <svg class="animate-spin h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-gray-400 leading-relaxed">
                      <span v-if="form.wallet_type === 'blink'">
                        {{ t('stores.blink_format') }}<br>
                        {{ t('stores.blink_paste') }}
                      </span>
                      <span v-else>
                        {{ t('stores.descriptor_paste') }}<br>
                        {{ t('stores.descriptor_example') }}
                      </span>
                    </p>
                    
                    <!-- Format error -->
                    <p v-if="formatError" class="mt-2 text-sm text-red-400">{{ formatError }}</p>
                    
                    <p class="mt-2 text-xs text-gray-500 italic">
                      {{ t('stores.configure_later') }}
                    </p>
                    
                    <!-- Duplicate descriptor warning for Aqua -->
                    <div v-if="duplicateWarning?.exists && form.wallet_type === 'aqua_boltz'" class="mt-3 rounded-xl p-4 border border-yellow-500/20 bg-yellow-500/10">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-yellow-400">
                                    {{ t('stores.descriptor_already_in_use') }}
                                </p>
                                <p class="mt-1 text-sm text-yellow-300">
                                    {{ duplicateWarning.message }}
                                </p>
                                <p class="mt-2 text-xs text-yellow-400/80">
                                    {{ t('stores.descriptor_btcpay_once') }}
                                </p>
                            </div>
                        </div>
                    </div>
                  </div>
              </transition>
              
              <div class="flex justify-between pt-4">
                <button
                  @click="currentStep = 1"
                  class="inline-flex justify-center py-3 px-6 border border-gray-600 shadow-sm text-sm font-medium rounded-xl text-gray-300 bg-gray-700 hover:bg-gray-600 transition-all"
                >
                  <svg class="mr-2 -ml-1 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                  Back
                </button>
                <button
                  @click="currentStep = 3"
                  :disabled="!form.wallet_type"
                  class="inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                >
                  Next Step
                   <svg class="ml-2 -mr-1 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                </button>
              </div>
            </div>

            <!-- Step 3: Confirmation -->
            <div v-if="currentStep === 3" class="space-y-6">
              <div class="bg-gray-900/50 p-6 rounded-2xl border border-gray-700">
                <h3 class="font-medium text-white mb-6 text-lg border-b border-gray-700 pb-2">{{ t('stores.review_store_settings') }}</h3>
                <dl class="space-y-4">
                  <div class="flex justify-between items-center">
                    <dt class="text-sm text-gray-400">{{ t('stores.store_name') }}</dt>
                    <dd class="text-sm font-bold text-white">{{ form.name }}</dd>
                  </div>
                  <div class="flex justify-between items-center">
                    <dt class="text-sm text-gray-400">{{ t('stores.default_currency') }}</dt>
                    <dd class="text-sm font-medium text-white bg-gray-800 px-2 py-1 rounded border border-gray-600">{{ form.default_currency }}</dd>
                  </div>
                  <div class="flex justify-between items-center">
                    <dt class="text-sm text-gray-400">{{ t('stores.timezone') }}</dt>
                    <dd class="text-sm font-medium text-white">{{ form.timezone }}</dd>
                  </div>
                  <div class="flex justify-between items-center" v-if="form.preferred_exchange">
                    <dt class="text-sm text-gray-400">{{ t('stores.price_source') }}</dt>
                    <dd class="text-sm font-medium text-white">{{ exchanges.find(e => e.value === form.preferred_exchange)?.label || form.preferred_exchange }}</dd>
                  </div>
                  <div class="flex justify-between items-center">
                    <dt class="text-sm text-gray-400">{{ t('stores.wallet_type') }}</dt>
                    <dd class="text-sm font-bold text-indigo-300">{{ form.wallet_type === 'blink' ? 'Blink' : 'Aqua (Boltz)' }}</dd>
                  </div>
                  <div class="flex justify-between items-center" v-if="form.connection_string">
                    <dt class="text-sm text-gray-400">{{ t('stores.connection') }}</dt>
                    <dd class="text-sm font-medium text-white flex items-center">
                      <span class="flex items-center text-green-400">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        {{ t('stores.configured') }}
                      </span>
                    </dd>
                  </div>
                </dl>
              </div>
              
              <div v-if="error" class="rounded-xl bg-red-500/10 border border-red-500/20 p-4">
                <div class="flex">
                   <svg class="h-5 w-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                   <div class="text-sm text-red-400">{{ error }}</div>
                </div>
              </div>
              
              <div class="flex justify-between pt-4">
                <button
                  @click="currentStep = 2"
                  :disabled="loading"
                  class="inline-flex justify-center py-3 px-6 border border-gray-600 shadow-sm text-sm font-medium rounded-xl text-gray-300 bg-gray-700 hover:bg-gray-600 disabled:opacity-50 transition-all"
                >
                  <svg class="mr-2 -ml-1 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                  Back
                </button>
                <button
                  @click="handleSubmit"
                  :disabled="loading"
                  class="inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-green-600 hover:bg-green-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-green-600/20"
                >
                   <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                  {{ loading ? t('stores.creating') : t('stores.create_store') }}
                </button>
              </div>
            </div>
        </div>
      </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useStoresStore } from '../../store/stores';
import { currencies } from '../../data/currencies';
import { exchanges } from '../../data/exchanges';
import api from '../../services/api';

const { t } = useI18n();

const router = useRouter();
const storesStore = useStoresStore();

const currentStep = ref(1);
const loading = ref(false);
const error = ref('');
const checkingDuplicate = ref(false);
const duplicateWarning = ref<{ exists: boolean; message: string | null; existing_store_name: string | null } | null>(null);
const formatError = ref<string | null>(null);

const form = ref({
  name: '',
  default_currency: 'EUR',
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

// Validate Blink connection string format
function validateBlinkConnectionString(connectionString: string): boolean {
    // Check for connection string format: type=blink;server=...;api-key=...;wallet-id=...
    if (connectionString.includes(';')) {
        const parts = connectionString.split(';');
        let hasType = false;
        let hasServer = false;
        let hasApiKey = false;
        let hasWalletId = false;

        for (const part of parts) {
            const [key] = part.split('=');
            const keyLower = key.trim().toLowerCase();
            if (keyLower === 'type') hasType = true;
            if (keyLower === 'server') hasServer = true;
            if (keyLower === 'api-key' || keyLower === 'apikey') hasApiKey = true;
            if (keyLower === 'wallet-id' || keyLower === 'walletid') hasWalletId = true;
        }

        return hasType && hasServer && hasApiKey && hasWalletId;
    }

    // Legacy format: URL is also acceptable
    try {
        new URL(connectionString);
        return true;
    } catch {
        return false;
    }
}

// Validate descriptor format (basic check)
function validateDescriptor(descriptor: string): boolean {
    const trimmed = descriptor.trim();
    if (!trimmed) return false;

    // Must NOT contain private keys
    if (/prv/i.test(trimmed)) {
        return false;
    }

    // Must NOT contain private key prefixes
    if (/(xprv|yprv|zprv)/i.test(trimmed)) {
        return false;
    }

    // Check if descriptor contains at least one valid descriptor function
    const validFunctions = [
        'wpkh', 'wsh', 'tr', 'pkh', 'sh', 'addr', 'raw',  // Basic functions
        'ct', 'elsh', 'slip77',  // Complex nested functions (for Boltz/Aqua)
    ];
    
    const lower = trimmed.toLowerCase();
    let hasValidFunction = false;
    
    for (const func of validFunctions) {
        if (new RegExp(`\\b${func}\\s*\\(`).test(lower)) {
            hasValidFunction = true;
            break;
        }
    }

    if (!hasValidFunction) {
        return false;
    }

    // Basic structure validation: should have balanced parentheses
    const openParens = (trimmed.match(/\(/g) || []).length;
    const closeParens = (trimmed.match(/\)/g) || []).length;
    if (openParens !== closeParens || openParens === 0) {
        return false;
    }

    // Should contain at least one xpub/ypub/zpub (extended public key)
    if (!/(xpub|ypub|zpub|tpub|upub|vpub)/i.test(trimmed)) {
        return false;
    }

    return true;
}

// Check for duplicate descriptor (for Aqua/Boltz)
async function checkDuplicateDescriptor() {
    if (form.value.wallet_type !== 'aqua_boltz' || !form.value.connection_string.trim()) {
        duplicateWarning.value = null;
        return;
    }

    // Only check if descriptor is valid format
    if (!validateDescriptor(form.value.connection_string)) {
        duplicateWarning.value = null;
        return;
    }

    checkingDuplicate.value = true;
    duplicateWarning.value = null;

    try {
        // Use endpoint for new stores (no store ID required)
        const response = await api.post('/wallet-connection/check-duplicate', {
            descriptor: form.value.connection_string.trim(),
            type: 'aqua_descriptor',
        });

        duplicateWarning.value = {
            exists: response.data.duplicate || false,
            message: response.data.message || null,
            existing_store_name: response.data.existing_store_name || null,
        };
    } catch (err: any) {
        // If check fails, don't show warning (let backend handle it)
        duplicateWarning.value = null;
    } finally {
        checkingDuplicate.value = false;
    }
}

// Validate format matches wallet type
function validateFormat() {
    if (!form.value.connection_string.trim()) {
        formatError.value = null;
        return;
    }

    if (form.value.wallet_type === 'blink') {
        if (!validateBlinkConnectionString(form.value.connection_string)) {
            formatError.value = 'Invalid Blink connection string format. Expected: type=blink;server=https://...;api-key=...;wallet-id=...';
            return;
        }
        formatError.value = null;
    } else if (form.value.wallet_type === 'aqua_boltz') {
        if (!validateDescriptor(form.value.connection_string)) {
            formatError.value = 'Invalid descriptor format. Must be a valid Bitcoin Core output descriptor (e.g., wpkh(), tr(), wsh(), or complex formats like ct(slip77(...),elsh(wpkh(...)))) and must not contain private keys.';
            return;
        }
        formatError.value = null;
    } else {
        formatError.value = null;
    }
}

// Watch for changes in connection string to validate format and check for duplicates
let duplicateCheckTimeout: ReturnType<typeof setTimeout> | null = null;
watch(() => form.value.connection_string, () => {
    // First validate format
    validateFormat();
    
    if (duplicateCheckTimeout) {
        clearTimeout(duplicateCheckTimeout);
    }
    
    if (form.value.wallet_type === 'aqua_boltz' && !formatError.value) {
        // Debounce the duplicate check
        duplicateCheckTimeout = setTimeout(() => {
            checkDuplicateDescriptor();
        }, 500);
    } else {
        duplicateWarning.value = null;
    }
});

// Watch for type changes
watch(() => form.value.wallet_type, () => {
    duplicateWarning.value = null;
    formatError.value = null;
    if (duplicateCheckTimeout) {
        clearTimeout(duplicateCheckTimeout);
    }
    // Re-validate format when wallet type changes
    if (form.value.connection_string.trim()) {
        validateFormat();
        if (form.value.wallet_type === 'aqua_boltz' && !formatError.value) {
            duplicateCheckTimeout = setTimeout(() => {
                checkDuplicateDescriptor();
            }, 500);
        }
    }
});

async function handleSubmit() {
  error.value = '';
  loading.value = true;

  try {
    const store = await storesStore.createStore(form.value);
    router.push(`/stores/${store.id}`);
  } catch (err: any) {
    error.value = err.response?.data?.message || t('stores.failed_to_create');
    if (err.response?.data?.errors) {
      const errors = Object.values(err.response.data.errors).flat();
      error.value = errors.join(', ');
    }
  } finally {
    loading.value = false;
  }
}
</script>
