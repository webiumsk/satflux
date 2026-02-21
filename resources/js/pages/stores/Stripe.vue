<template>
  <div v-if="!store && !error" class="flex items-center justify-center min-h-screen bg-gray-900">
    <div class="flex flex-col items-center">
      <svg class="animate-spin h-10 w-10 text-indigo-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <p class="text-gray-400">{{ t('common.loading') }}</p>
    </div>
  </div>
  <div v-else-if="error" class="flex items-center justify-center min-h-screen bg-gray-900">
    <div class="text-center px-4">
      <p class="text-red-400 mb-4">{{ error }}</p>
      <button @click="loadStore" class="text-indigo-400 hover:text-indigo-300">{{ t('common.retry') }}</button>
    </div>
  </div>
  <div v-else class="flex bg-gray-900 overflow-hidden">
    <StoreSidebar
      :store="store"
      :apps="allApps"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
      @open-setup-wizard="handleOpenSetupWizard"
    />

    <div class="flex-1 flex flex-col overflow-hidden bg-gray-900 border-l border-gray-800">
      <div class="sticky top-0 z-20 bg-gray-900/80 backdrop-blur-md border-b border-gray-800">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h1 class="text-2xl font-bold text-white mb-1">{{ t('stores.stripe_settings_title') }}</h1>
              <p class="text-sm text-gray-400">
                {{ t('stores.stripe_settings_for') }} <span class="text-indigo-400">{{ store?.name || t('stores.this_store') }}</span>
              </p>
            </div>
            <div class="flex items-center gap-3">
              <button
                type="button"
                @click="testConnection"
                :disabled="!settings?.isConfigured || testingConnection"
                class="inline-flex items-center px-4 py-2 border border-gray-600 text-sm font-medium rounded-xl text-gray-300 hover:bg-gray-700 hover:text-white transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <svg v-if="testingConnection" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                {{ testingConnection ? t('common.loading') : t('stores.stripe_test_connection') }}
              </button>
              <button
                type="button"
                @click="saveSettings"
                :disabled="saving"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-emerald-600 hover:bg-emerald-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <svg v-if="saving" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                {{ saving ? t('common.loading') : t('common.save') }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="flex-1 overflow-y-auto custom-scrollbar">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
          <div v-if="flashMessage" :class="flashType === 'error' ? 'bg-red-500/10 border-red-500/30 text-red-400' : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400'" class="rounded-xl border p-4 mb-6">
            {{ flashMessage }}
          </div>

          <form @submit.prevent="saveSettings" class="space-y-8">
            <!-- Enable Stripe -->
            <div class="bg-gray-800/50 rounded-2xl border border-gray-700 p-6">
              <div class="flex items-center justify-between">
                <div>
                  <h2 class="text-lg font-semibold text-white">{{ t('stores.stripe_enable') }}</h2>
                  <p class="text-sm text-gray-400 mt-1">{{ t('stores.stripe_enable_description') }}</p>
                </div>
                <button
                  type="button"
                  role="switch"
                  :aria-checked="form.enabled"
                  @click="form.enabled = !form.enabled"
                  :class="[
                    form.enabled ? 'bg-emerald-600' : 'bg-gray-600',
                    'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors'
                  ]"
                >
                  <span
                    :class="[
                      form.enabled ? 'translate-x-5' : 'translate-x-1',
                      'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition'
                    ]"
                  />
                </button>
              </div>
            </div>

            <!-- API Configuration -->
            <div class="bg-gray-800/50 rounded-2xl border border-gray-700 p-6">
              <h2 class="text-lg font-semibold text-white mb-4">{{ t('stores.stripe_api_config') }}</h2>
              <div class="space-y-5">
                <div>
                  <label for="publishableKey" class="block text-sm font-medium text-gray-300 mb-1">
                    {{ t('stores.stripe_publishable_key') }} <span v-if="form.enabled" class="text-red-400">*</span>
                  </label>
                  <input
                    id="publishableKey"
                    v-model="form.publishableKey"
                    type="password"
                    autocomplete="off"
                    :placeholder="settings?.publishableKey ? t('stores.stripe_key_keep_placeholder') : 'pk_live_... or pk_test_...'"
                    class="block w-full px-4 py-3 rounded-xl border border-gray-600 bg-gray-700/50 text-white placeholder-gray-500 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-mono text-sm"
                  />
                  <p class="mt-1.5 text-xs text-gray-500">{{ t('stores.stripe_publishable_key_help') }} <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener" class="text-indigo-400 hover:text-indigo-300">{{ t('stores.stripe_get_from_dashboard') }}</a></p>
                  <p v-if="fieldErrors.publishableKey" class="mt-1 text-sm text-red-400">{{ fieldErrors.publishableKey }}</p>
                </div>
                <div>
                  <label for="secretKey" class="block text-sm font-medium text-gray-300 mb-1">
                    {{ t('stores.stripe_secret_key') }} <span v-if="form.enabled" class="text-red-400">*</span>
                  </label>
                  <input
                    id="secretKey"
                    v-model="form.secretKey"
                    type="password"
                    autocomplete="off"
                    :placeholder="settings?.secretKey ? t('stores.stripe_key_keep_placeholder') : 'sk_live_... or sk_test_...'"
                    class="block w-full px-4 py-3 rounded-xl border border-gray-600 bg-gray-700/50 text-white placeholder-gray-500 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-mono text-sm"
                  />
                  <p class="mt-1.5 text-xs text-gray-500">{{ t('stores.stripe_secret_key_help') }} <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener" class="text-indigo-400 hover:text-indigo-300">{{ t('stores.stripe_get_from_dashboard') }}</a></p>
                  <p v-if="fieldErrors.secretKey" class="mt-1 text-sm text-red-400">{{ fieldErrors.secretKey }}</p>
                </div>
                <div>
                  <label for="settlementCurrency" class="block text-sm font-medium text-gray-300 mb-1">
                    {{ t('stores.stripe_settlement_currency') }} <span v-if="form.enabled" class="text-red-400">*</span>
                  </label>
                  <select
                    id="settlementCurrency"
                    v-model="form.settlementCurrency"
                    class="block w-full px-4 py-3 rounded-xl border border-gray-600 bg-gray-700/50 text-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                  >
                    <option value="">—</option>
                    <option v-for="c in settlementCurrencies" :key="c.code" :value="c.code">{{ c.code }} – {{ c.name }}</option>
                  </select>
                  <p class="mt-1.5 text-xs text-gray-500">{{ t('stores.stripe_settlement_currency_help') }}</p>
                  <p v-if="fieldErrors.settlementCurrency" class="mt-1 text-sm text-red-400">{{ fieldErrors.settlementCurrency }}</p>
                </div>
              </div>
            </div>

            <!-- Webhook Configuration -->
            <div class="bg-gray-800/50 rounded-2xl border border-gray-700 p-6">
              <h2 class="text-lg font-semibold text-white mb-4">{{ t('stores.stripe_webhook_config') }}</h2>
              <div v-if="!settings?.isConfigured" class="rounded-xl bg-gray-700/30 border border-gray-600 p-4 text-gray-400 text-sm">
                {{ t('stores.stripe_save_keys_first') }}
              </div>
              <div v-else class="space-y-3">
                <div class="rounded-xl bg-gray-700/30 border border-gray-600 p-4 text-sm">
                  <p class="text-gray-300">{{ webhookStatus?.message || t('common.loading') }}</p>
                  <p v-if="webhookStatus?.webhookUrl" class="mt-2 text-gray-500 font-mono text-xs break-all">{{ webhookStatus.webhookUrl }}</p>
                </div>
                <button
                  type="button"
                  @click="registerWebhook"
                  :disabled="registeringWebhook"
                  class="inline-flex items-center px-4 py-2 border border-gray-600 text-sm font-medium rounded-xl text-gray-300 hover:bg-gray-700 hover:text-white transition-all disabled:opacity-50"
                >
                  <svg v-if="registeringWebhook" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                  {{ registeringWebhook ? t('common.loading') : t('stores.stripe_register_webhook') }}
                </button>
              </div>
            </div>

            <!-- Advanced Settings -->
            <div class="bg-gray-800/50 rounded-2xl border border-gray-700 p-6">
              <h2 class="text-lg font-semibold text-white mb-4">{{ t('stores.stripe_advanced_config') }}</h2>
              <div>
                <label for="advancedConfig" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.stripe_advanced_config_json') }}</label>
                <textarea
                  id="advancedConfig"
                  v-model="form.advancedConfig"
                  rows="5"
                  placeholder='{"payment_method_types":["card"], "metadata":{"custom_field":"value"}}'
                  class="block w-full px-4 py-3 rounded-xl border border-gray-600 bg-gray-700/50 text-white placeholder-gray-500 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-mono text-sm"
                ></textarea>
                <p class="mt-1.5 text-xs text-gray-500">{{ t('stores.stripe_advanced_config_help') }} <a href="https://stripe.com/docs/api/payment_intents/create" target="_blank" rel="noopener" class="text-indigo-400 hover:text-indigo-300">Stripe API docs</a></p>
                <p v-if="fieldErrors.advancedConfig" class="mt-1 text-sm text-red-400">{{ fieldErrors.advancedConfig }}</p>
              </div>
            </div>

            <!-- Clear Credentials -->
            <div class="bg-gray-800/50 rounded-2xl border border-gray-700 p-6">
              <h2 class="text-lg font-semibold text-white mb-2">{{ t('stores.stripe_danger_zone') }}</h2>
              <p class="text-sm text-gray-400 mb-4">{{ t('stores.stripe_clear_credentials_description') }}</p>
              <button
                type="button"
                @click="showDeleteConfirm = true"
                :disabled="!settings?.isConfigured"
                class="inline-flex items-center px-4 py-2 border border-red-500/50 text-sm font-medium rounded-xl text-red-400 hover:bg-red-500/10 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ t('stores.stripe_clear_credentials') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Delete confirmation modal -->
    <div
      v-if="showDeleteConfirm"
      class="fixed inset-0 z-50 overflow-y-auto"
      role="dialog"
      aria-modal="true"
    >
      <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm" @click="showDeleteConfirm = false"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom bg-gray-800 rounded-2xl text-left overflow-hidden shadow-xl transform sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-gray-700">
          <div class="px-4 pt-5 pb-4 sm:p-6">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-500/20 sm:mx-0 sm:h-10 sm:w-10">
                <svg class="h-6 w-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                <h3 class="text-lg font-bold text-white">{{ t('stores.stripe_clear_confirm_title') }}</h3>
                <p class="mt-2 text-sm text-gray-400">{{ t('stores.stripe_clear_confirm_message') }}</p>
              </div>
            </div>
          </div>
          <div class="bg-gray-800/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-3 border-t border-gray-700">
            <button
              type="button"
              @click="clearCredentials"
              :disabled="deleting"
              class="w-full sm:w-auto inline-flex justify-center px-4 py-2 border border-transparent rounded-xl text-sm font-medium text-white bg-red-600 hover:bg-red-700 disabled:opacity-50"
            >
              {{ deleting ? t('common.loading') : t('stores.stripe_clear_confirm_button') }}
            </button>
            <button
              type="button"
              @click="showDeleteConfirm = false"
              class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center px-4 py-2 border border-gray-600 rounded-xl text-sm font-medium text-gray-300 hover:bg-gray-700"
            >
              {{ t('common.cancel') }}
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
import { useI18n } from 'vue-i18n';
import { useStoresStore } from '../../store/stores';
import { useAppsStore } from '../../store/apps';
import { useFlashStore } from '../../store/flash';
import StoreSidebar from '../../components/stores/StoreSidebar.vue';
import api from '../../services/api';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const storesStore = useStoresStore();
const appsStore = useAppsStore();
const flashStore = useFlashStore();

const storeId = computed(() => route.params.id as string);
const store = ref<any>(null);
const error = ref('');
const settings = ref<any>(null);
const webhookStatus = ref<any>(null);
const loading = ref(true);
const saving = ref(false);
const testingConnection = ref(false);
const registeringWebhook = ref(false);
const deleting = ref(false);
const showDeleteConfirm = ref(false);
const flashMessage = ref('');
const flashType = ref<'success' | 'error'>('success');
const fieldErrors = ref<Record<string, string>>({});

const settlementCurrencies = [
  { code: 'USD', name: 'US Dollar' },
  { code: 'EUR', name: 'Euro' },
  { code: 'GBP', name: 'British Pound' },
  { code: 'CHF', name: 'Swiss Franc' },
  { code: 'CAD', name: 'Canadian Dollar' },
  { code: 'AUD', name: 'Australian Dollar' },
  { code: 'JPY', name: 'Japanese Yen' },
];

const form = ref({
  enabled: false,
  publishableKey: '',
  secretKey: '',
  settlementCurrency: '',
  advancedConfig: '',
});

const allApps = computed(() => appsStore.apps);

function setFlash(msg: string, type: 'success' | 'error' = 'success') {
  flashMessage.value = msg;
  flashType.value = type;
  setTimeout(() => { flashMessage.value = ''; }, 5000);
}

async function loadStore() {
  error.value = '';
  try {
    const res = await api.get(`/stores/${storeId.value}`);
    store.value = res.data.data;
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load store';
  }
}

async function loadSettings() {
  loading.value = true;
  fieldErrors.value = {};
  try {
    const [settingsRes, webhookRes] = await Promise.all([
      api.get(`/stores/${storeId.value}/stripe/settings`),
      api.get(`/stores/${storeId.value}/stripe/webhook/status`),
    ]);
    settings.value = settingsRes.data;
    webhookStatus.value = webhookRes.data;
    form.value = {
      enabled: settings.value.enabled ?? false,
      publishableKey: '',
      secretKey: '',
      settlementCurrency: settings.value.settlementCurrency || '',
      advancedConfig: settings.value.advancedConfig ? (typeof settings.value.advancedConfig === 'string' ? settings.value.advancedConfig : JSON.stringify(settings.value.advancedConfig, null, 2)) : '',
    };
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Failed to load Stripe settings';
    if (err.response?.status === 403) {
      setFlash(msg, 'error');
    } else {
      setFlash(msg, 'error');
    }
  } finally {
    loading.value = false;
  }
}

function buildPayload() {
  const p: Record<string, any> = {};
  if (form.value.enabled !== undefined) p.enabled = form.value.enabled;
  if (form.value.publishableKey?.trim()) p.publishableKey = form.value.publishableKey.trim();
  if (form.value.secretKey?.trim()) p.secretKey = form.value.secretKey.trim();
  if (form.value.settlementCurrency?.trim()) p.settlementCurrency = form.value.settlementCurrency.trim();
  if (form.value.advancedConfig?.trim()) {
    try {
      JSON.parse(form.value.advancedConfig.trim());
      p.advancedConfig = form.value.advancedConfig.trim();
    } catch {
      fieldErrors.value = { advancedConfig: 'Invalid JSON' };
      return null;
    }
  }
  return p;
}

async function saveSettings() {
  saving.value = true;
  fieldErrors.value = {};
  setFlash('');
  const payload = buildPayload();
  if (payload === null) {
    saving.value = false;
    return;
  }
  try {
    const res = await api.put(`/stores/${storeId.value}/stripe/settings`, payload);
    settings.value = res.data;
    setFlash(t('stores.stripe_settings_saved'), 'success');
    await loadSettings();
  } catch (err: any) {
    const data = err.response?.data;
    if (err.response?.status === 422 && data?.errors) {
      const errors: Record<string, string> = {};
      for (const e of Array.isArray(data.errors) ? data.errors : []) {
        const path = (e.path || e.field || '').replace(/^([A-Z])/, (m: string) => m.toLowerCase());
        errors[path] = e.message || e.msg || String(e);
      }
      fieldErrors.value = errors;
      setFlash(data.message || 'Validation failed', 'error');
    } else {
      setFlash(data?.message || 'Failed to save settings', 'error');
    }
  } finally {
    saving.value = false;
  }
}

async function testConnection() {
  testingConnection.value = true;
  setFlash('');
  try {
    const res = await api.post(`/stores/${storeId.value}/stripe/test`);
    const d = res.data;
    setFlash(d.success ? d.message : (d.message || 'Connection failed'), d.success ? 'success' : 'error');
  } catch (err: any) {
    setFlash(err.response?.data?.message || 'Connection test failed', 'error');
  } finally {
    testingConnection.value = false;
  }
}

async function registerWebhook() {
  registeringWebhook.value = true;
  setFlash('');
  try {
    const res = await api.post(`/stores/${storeId.value}/stripe/webhook/register`);
    setFlash(res.data?.message || t('stores.stripe_webhook_registered'), 'success');
    const webhookRes = await api.get(`/stores/${storeId.value}/stripe/webhook/status`);
    webhookStatus.value = webhookRes.data;
  } catch (err: any) {
    setFlash(err.response?.data?.message || 'Webhook registration failed', 'error');
  } finally {
    registeringWebhook.value = false;
  }
}

async function clearCredentials() {
  deleting.value = true;
  setFlash('');
  try {
    await api.delete(`/stores/${storeId.value}/stripe/settings`);
    setFlash(t('stores.stripe_credentials_cleared'), 'success');
    showDeleteConfirm.value = false;
    await loadSettings();
  } catch (err: any) {
    setFlash(err.response?.data?.message || 'Failed to clear credentials', 'error');
  } finally {
    deleting.value = false;
  }
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
  await loadStore();
  if (store.value) await loadSettings();
  await appsStore.fetchApps(storeId.value);
});

watch(() => route.params.id, async (newId) => {
  const id = Array.isArray(newId) ? newId[0] : newId;
  if (id) await appsStore.fetchApps(id);
}, { immediate: false });
</script>
