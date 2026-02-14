<template>
  <div v-if="loading && !settings" class="text-center py-12">
    <svg class="animate-spin mx-auto h-10 w-10 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    <p class="text-gray-400 mt-4">{{ t('common.loading') }}</p>
  </div>

  <div v-else-if="settings" class="space-y-6">
    <!-- Main card with tabs -->
    <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700">
      <div class="px-6 py-5 border-b border-gray-700 bg-gray-800/50">
        <h1 class="text-2xl font-bold text-white">{{ t('stores.store_settings') }}</h1>
        <p class="text-sm text-gray-400 mt-1">{{ t('stores.manage_store_config') }}</p>
      </div>

      <!-- Tabs -->
      <nav class="flex border-b border-gray-700 overflow-x-auto" aria-label="Store settings tabs">
        <button
          v-for="tab in settingsTabs"
          :key="tab.id"
          type="button"
          @click="activeSettingsTab = tab.id"
          :class="[
            'px-5 py-4 text-sm font-medium whitespace-nowrap border-b-2 transition-colors',
            activeSettingsTab === tab.id
              ? 'border-indigo-500 text-indigo-400'
              : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-600',
          ]"
        >
          {{ t(tab.labelKey) }}
        </button>
      </nav>

      <!-- Tabs: General, Payment, Rates, Checkout (one form) -->
      <div v-show="['settings', 'payment', 'rates', 'checkout'].includes(activeSettingsTab)" class="px-6 py-8">
        <form @submit.prevent="handleSettingsSubmit" class="space-y-8">
          <StoreSettingsGeneral
            v-show="activeSettingsTab === 'settings'"
            :form="settingsForm"
            :settings="settings"
            :can-edit-branding="canEditBranding"
            :can-edit-archived-option="canEditArchivedOption"
            :store-logo-url="storeLogoUrl"
            @show-upgrade="showUpgradeModal = true"
            :logo-error="logoError"
            :logo-success="logoSuccess"
            :deleting-logo="deletingLogo"
            @logo-upload="handleLogoUpload"
            @logo-delete="handleDeleteLogo"
          />
          <StoreSettingsPayment
            v-show="activeSettingsTab === 'payment'"
            :form="settingsForm"
            :can-edit-payment-options="canEditPaymentOptions"
            :timezone-options="timezoneOptions"
            @show-upgrade="showUpgradeModal = true"
            :speed-policy-options="speedPolicyOptions"
            :network-fee-mode-options="networkFeeModeOptions"
          />
          <StoreSettingsRates
            v-show="activeSettingsTab === 'rates'"
            :form="settingsForm"
            :can-edit-rates-options="canEditRatesOptions"
            @show-upgrade="showUpgradeModal = true"
          />
          <StoreSettingsCheckout
            v-show="activeSettingsTab === 'checkout'"
            :form="settingsForm"
            :can-edit-checkout-options="canEditCheckoutOptions"
            :default-payment-method-options="defaultPaymentMethodOptions"
            @show-upgrade="showUpgradeModal = true"
            :payment-method-criteria-type-options="paymentMethodCriteriaTypeOptions"
            :default-lang-options="defaultLangOptions"
          />

          <template v-if="['settings', 'payment', 'rates', 'checkout'].includes(activeSettingsTab)">
          <div class="flex justify-end pt-4">
            <button
              type="submit"
              :disabled="saving"
              class="inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-indigo-600/20"
            >
              <svg v-if="saving" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>

              </svg>
              {{ saving ? t('auth.saving') : t('stores.save_changes') }}
            </button>
          </div>
          </template>
        </form>
      </div>
    </div>

    <!-- Danger Zone (shown under every tab) -->
    <div class="bg-red-900/10 shadow-xl rounded-2xl border border-red-900/30 overflow-hidden">
      <div class="px-6 py-5 border-b border-red-900/20 bg-red-900/20">
        <h2 class="text-lg font-bold text-red-400">{{ t('stores.settings_danger_zone') }}</h2>
      </div>
      <div class="px-6 py-6">
        <h3 class="text-sm font-bold text-white mb-2">{{ t('stores.settings_delete_store') }}</h3>
        <p class="text-sm text-gray-400 mb-6 max-w-xl">
          {{ t('stores.settings_delete_store_desc', { name: store?.name || '' }) }}
        </p>
        <button
          type="button"
          @click="showDeleteStoreModal = true"
          class="inline-flex items-center px-4 py-2 border border-red-500/50 text-sm font-medium rounded-lg text-red-400 bg-red-500/10 hover:bg-red-500/20 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 focus:ring-offset-gray-900 transition-colors"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
          {{ t('stores.settings_delete_store') }}
        </button>
      </div>
    </div>
  </div>

  <!-- Upgrade to Pro Modal -->
  <UpgradeModal
    :show="showUpgradeModal"
    :message="t('stores.pos_advanced_options_pro_only')"
    @close="showUpgradeModal = false"
  />

  <!-- Delete Store Confirmation Modal -->
  <div
    v-if="showDeleteStoreModal"
    class="fixed inset-0 z-50 overflow-y-auto"
    aria-labelledby="modal-title"
    role="dialog"
    aria-modal="true"
  >
     <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm transition-opacity" aria-hidden="true" @click="showDeleteStoreModal = false"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-gray-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-700">
           <div class="bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div class="sm:flex sm:items-start">
                 <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-900/20 sm:mx-0 sm:h-10 sm:w-10">
                    <svg class="h-6 w-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                 </div>
                 <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <h3 class="text-lg leading-6 font-medium text-white" id="modal-title">{{ t('stores.settings_delete_confirm_title') }}</h3>
                    <div class="mt-2">
                       <p class="text-sm text-gray-400">
                          {{ t('stores.settings_delete_confirm_desc', { name: store?.name || '' }) }}
                       </p>
                       <p class="text-sm text-gray-400 mt-2">
                          {{ t('stores.settings_delete_type_confirm', { word: 'DELETE' }) }}
                       </p>
                       <input
                          v-model="deleteStoreConfirmText"
                          type="text"
                          class="mt-3 block w-full border border-gray-600 rounded-lg shadow-sm py-2 px-3 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm bg-gray-700 text-white placeholder-gray-500"
                          :placeholder="t('stores.settings_delete_placeholder')"
                           @keyup.enter="handleDeleteStore"
                       />
                       <p v-if="deleteStoreError" class="mt-2 text-sm text-red-500">{{ deleteStoreError }}</p>
                    </div>
                 </div>
              </div>
           </div>
           <div class="bg-gray-800/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-700/50">
              <button
                 type="button"
                 @click="handleDeleteStore"
                 :disabled="deletingStore || deleteStoreConfirmText !== 'DELETE'"
                 class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              >
                 {{ deletingStore ? 'Deleting...' : 'Delete Store' }}
              </button>
              <button
                 type="button"
                 @click="showDeleteStoreModal = false; deleteStoreConfirmText = ''"
                 class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-600 shadow-sm px-4 py-2 bg-transparent text-base font-medium text-gray-300 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
              >
                 Cancel
              </button>
           </div>
        </div>
     </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../store/auth';
import { useStoresStore } from '../../store/stores';
import { useFlashStore } from '../../store/flash';
import api from '../../services/api';
import StoreSettingsGeneral from './StoreSettingsGeneral.vue';
import StoreSettingsPayment from './StoreSettingsPayment.vue';
import StoreSettingsRates from './StoreSettingsRates.vue';
import StoreSettingsCheckout from './StoreSettingsCheckout.vue';
import UpgradeModal from './UpgradeModal.vue';

const { t } = useI18n();

const props = defineProps({
  store: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['update-store']);

const router = useRouter();
const authStore = useAuthStore();
const storesStore = useStoresStore();
const flashStore = useFlashStore();

const planCode = computed(() => (authStore.user?.plan?.code ?? 'free') as string);
const userRole = computed(() => authStore.user?.role ?? '');
const hasPaidAccess = computed(() =>
  planCode.value === 'pro' || planCode.value === 'enterprise' || userRole.value === 'admin' || userRole.value === 'support'
);
const canEditBranding = computed(() => hasPaidAccess.value);
const canEditPaymentOptions = computed(() => hasPaidAccess.value);
const canEditRatesOptions = computed(() => hasPaidAccess.value);
const canEditCheckoutOptions = computed(() => hasPaidAccess.value);
const canEditArchivedOption = computed(() => hasPaidAccess.value);
const showUpgradeModal = ref(false);

const activeSettingsTab = ref<'settings' | 'payment' | 'rates' | 'checkout'>('settings');
const settingsTabs = [
  { id: 'settings' as const, labelKey: 'stores.settings_tab_general' },
  { id: 'payment' as const, labelKey: 'stores.settings_tab_payment' },
  { id: 'rates' as const, labelKey: 'stores.settings_tab_rates' },
  { id: 'checkout' as const, labelKey: 'stores.settings_tab_checkout' },
];

const loading = ref(false);
const saving = ref(false);
const settings = ref<any>(null);

const settingsForm = ref<Record<string, any>>({
  name: '',
  website: '',
  support_url: '',
  css_url: '',
  payment_sound_url: '',
  brand_color: '#F7931A',
  apply_brand_color_to_backend: false,
  default_currency: 'EUR',
  additional_tracked_rates: [] as string[],
  invoice_expiration: 15,
  refund_bolt11_expiration: 30,
  display_expiration_timer: 5,
  monitoring_expiration: 1440,
  speed_policy: 'HighSpeed',
  lightning_description_template: '',
  payment_tolerance: 0,
  archived: false,
  anyone_can_create_invoice: true,
  receipt: { enabled: true, show_qr: null, show_payments: null },
  lightning_amount_in_satoshi: false,
  lightning_private_route_hints: false,
  on_chain_with_ln_invoice_fallback: false,
  redirect_automatically: false,
  show_recommended_fee: true,
  recommended_fee_block_target: 1,
  default_lang: 'en',
  html_title: '',
  network_fee_mode: 'Always',
  pay_join_enabled: false,
  auto_detect_language: false,
  show_pay_in_wallet_button: true,
  show_store_header: true,
  celebrate_payment: true,
  play_sound_on_payment: false,
  lazy_payment_methods: false,
  default_payment_method: 'BTC-LN',
  payment_method_criteria: [{ payment_method: 'BTC-LN', type: 'GreaterThan', value: '' }],
  timezone: 'UTC',
  preferred_exchange: '',
});

// Logo management
const storeLogoUrl = ref<string | null>(null);
const uploadingLogo = ref(false);
const deletingLogo = ref(false);
const logoError = ref('');
const logoSuccess = ref('');

// Delete store modal
const showDeleteStoreModal = ref(false);
const deleteStoreConfirmText = ref('');
const deletingStore = ref(false);
const deleteStoreError = ref('');

// Timezones (same list as before)
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

const timezoneOptions = timezones.map(tz => ({ label: tz, value: tz }));

const speedPolicyOptions = [
  { label: 'High speed (1 confirmation)', value: 'HighSpeed' },
  { label: 'Medium speed (2–3 confirmations)', value: 'MediumSpeed' },
  { label: 'Low speed (6+ confirmations)', value: 'LowSpeed' },
];

const defaultPaymentMethodOptions = [
  { label: 'BTC-LN', value: 'BTC-LN' },
  { label: 'BTC-LNURL', value: 'BTC-LNURL' },
  { label: 'BTC-CHAIN', value: 'BTC-CHAIN' },
];

const paymentMethodCriteriaTypeOptions = [
  { label: 'Greater than', value: 'GreaterThan' },
  { label: 'Less than', value: 'LessThan' },
];

const networkFeeModeOptions = [
  { label: 'Always add network fee to invoice', value: 'Always' },
  { label: 'Only if customer makes more than one payment for the invoice', value: 'MultiplePaymentsOnly' },
  { label: 'Never', value: 'Never' },
];

// BTCPay DefaultLang options (checkout appearance)
const defaultLangOptions = [
  { label: 'Azərbaycanca', value: 'az' },
  { label: 'Bahasa Indonesia', value: 'id' },
  { label: 'Bosanski', value: 'bs-BA' },
  { label: 'Català', value: 'ca-ES' },
  { label: 'Česky', value: 'cs-CZ' },
  { label: 'Dansk', value: 'da-DK' },
  { label: 'Deutsch', value: 'de-DE' },
  { label: 'English', value: 'en' },
  { label: 'Español', value: 'es-ES' },
  { label: 'Français', value: 'fr-FR' },
  { label: 'Hrvatski', value: 'hr-HR' },
  { label: 'Íslenska', value: 'is-IS' },
  { label: 'Italiano', value: 'it-IT' },
  { label: 'Latviešu', value: 'lv' },
  { label: 'Magyar', value: 'hu-HU' },
  { label: 'Nederlands', value: 'nl-NL' },
  { label: 'Norsk', value: 'no' },
  { label: 'Polski', value: 'pl' },
  { label: 'Português (Brasil)', value: 'pt-BR' },
  { label: 'Portuguese', value: 'pt-PT' },
  { label: 'Română', value: 'ro' },
  { label: 'Slovenčina', value: 'sk-SK' },
  { label: 'Slovenščina', value: 'sl-SI' },
  { label: 'Srpski', value: 'sr' },
  { label: 'Suomi', value: 'fi-FI' },
  { label: 'Svenska', value: 'sv' },
  { label: 'Tiếng Việt', value: 'vi-VN' },
  { label: 'Türkçe', value: 'tr' },
  { label: 'Zulu', value: 'zu' },
  { label: 'Ελληνικά', value: 'el-GR' },
  { label: 'български', value: 'bg-BG' },
  { label: 'қазақ', value: 'kk-KZ' },
  { label: 'Русский', value: 'ru-RU' },
  { label: 'Українська', value: 'uk-UA' },
  { label: 'ქართული', value: 'ka' },
  { label: 'Հայերեն', value: 'hy' },
  { label: 'עברית', value: 'he' },
  { label: 'العربية', value: 'ar' },
  { label: 'فارسی', value: 'fa' },
  { label: 'አማርኛ', value: 'am-ET' },
  { label: 'नेपाली', value: 'np-NP' },
  { label: 'हिंदी', value: 'hi' },
  { label: 'ไทย', value: 'th-TH' },
  { label: '한국어', value: 'ko' },
  { label: '中文 (华语)', value: 'zh-SG' },
  { label: '中文 (台語)', value: 'zh-TW' },
  { label: '日本語', value: 'ja-JP' },
  { label: '英文', value: 'zh-SP' },
];

onMounted(async () => {
  await fetchSettings();
});

async function fetchSettings() {
  if (!props.store) return;

  loading.value = true;
  try {
    const response = await api.get(`/stores/${props.store.id}/settings`);
    const d = response.data.data;
    settings.value = d;
    storeLogoUrl.value = d.logo_url ?? null;

    const arr = (v: unknown) => (Array.isArray(v) ? v : []);
    settingsForm.value.name = d.name ?? '';
    settingsForm.value.website = d.website ?? '';
    settingsForm.value.support_url = d.support_url ?? '';
    settingsForm.value.css_url = d.css_url ?? '';
    settingsForm.value.payment_sound_url = d.payment_sound_url ?? '';
    settingsForm.value.brand_color = d.brand_color ?? '#F7931A';
    settingsForm.value.apply_brand_color_to_backend = !!d.apply_brand_color_to_backend;
    settingsForm.value.default_currency = d.default_currency ?? 'EUR';
    settingsForm.value.additional_tracked_rates = arr(d.additional_tracked_rates);
    // invoice_expiration: API returns seconds; UI shows minutes (15 min default)
    settingsForm.value.invoice_expiration = Math.round((d.invoice_expiration ?? 900) / 60);
    settingsForm.value.refund_bolt11_expiration = d.refund_bolt11_expiration ?? 30;
    // API returns seconds; UI shows minutes (BTCPay default 300s = 5min)
    settingsForm.value.display_expiration_timer = Math.round((d.display_expiration_timer ?? 300) / 60);
    // monitoring_expiration: API returns seconds; UI shows minutes (1440 min = 24h default)
    settingsForm.value.monitoring_expiration = Math.round((d.monitoring_expiration ?? 86400) / 60);
    settingsForm.value.speed_policy = d.speed_policy ?? 'HighSpeed';
    settingsForm.value.lightning_description_template = d.lightning_description_template ?? '';
    settingsForm.value.payment_tolerance = Number(d.payment_tolerance) || 0;
    settingsForm.value.archived = !!d.archived;
    settingsForm.value.anyone_can_create_invoice = !!d.anyone_can_create_invoice;
    settingsForm.value.receipt = {
      enabled: d.receipt?.enabled !== false,
      show_qr: d.receipt?.show_qr ?? d.receipt?.showQR ?? (d.receipt?.enabled !== false),
      show_payments: d.receipt?.show_payments ?? d.receipt?.showPayments ?? (d.receipt?.enabled !== false),
    };
    settingsForm.value.lightning_amount_in_satoshi = !!d.lightning_amount_in_satoshi;
    settingsForm.value.lightning_private_route_hints = !!d.lightning_private_route_hints;
    settingsForm.value.on_chain_with_ln_invoice_fallback = !!d.on_chain_with_ln_invoice_fallback;
    settingsForm.value.redirect_automatically = !!d.redirect_automatically;
    settingsForm.value.show_recommended_fee = d.show_recommended_fee !== false;
    settingsForm.value.recommended_fee_block_target = d.recommended_fee_block_target ?? 1;
    settingsForm.value.default_lang = d.default_lang ?? 'en';
    settingsForm.value.html_title = d.html_title ?? '';
    settingsForm.value.network_fee_mode = d.network_fee_mode ?? 'Always';
    settingsForm.value.pay_join_enabled = !!d.pay_join_enabled;
    settingsForm.value.auto_detect_language = !!d.auto_detect_language;
    settingsForm.value.show_pay_in_wallet_button = d.show_pay_in_wallet_button !== false;
    settingsForm.value.show_store_header = d.show_store_header !== false;
    settingsForm.value.celebrate_payment = d.celebrate_payment !== false;
    settingsForm.value.play_sound_on_payment = !!d.play_sound_on_payment;
    settingsForm.value.lazy_payment_methods = !!d.lazy_payment_methods;
    settingsForm.value.default_payment_method = d.default_payment_method ?? 'BTC-LN';
    const rawCriteria = d.payment_method_criteria;
    if (Array.isArray(rawCriteria) && rawCriteria.length > 0) {
      settingsForm.value.payment_method_criteria = rawCriteria.map((c: any) => ({
        payment_method: c.paymentMethod ?? c.payment_method ?? 'BTC-LN',
        type: c.type ?? 'GreaterThan',
        value: c.value ?? '',
      }));
    } else {
      settingsForm.value.payment_method_criteria = [{ payment_method: 'BTC-LN', type: 'GreaterThan', value: '' }];
    }
    settingsForm.value.timezone = d.timezone ?? 'UTC';
    settingsForm.value.preferred_exchange = d.preferred_exchange ?? '';
  } catch (err) {
    console.error('Failed to fetch settings:', err);
  } finally {
    loading.value = false;
  }
}

async function handleSettingsSubmit() {
  if (!props.store) return;
  flashStore.clear();
  saving.value = true;

  try {
    const payload = { ...settingsForm.value };
    // Convert empty URL strings to null to avoid 422 validation errors
    const urlFields = ['website', 'support_url', 'logo_url', 'css_url', 'payment_sound_url'];
    urlFields.forEach((key) => {
      if (payload[key] === '') payload[key] = null;
    });
    // Time fields: UI uses minutes, API expects seconds
    if (typeof payload.display_expiration_timer === 'number') {
      payload.display_expiration_timer = Math.round(payload.display_expiration_timer * 60);
    }
    if (typeof payload.invoice_expiration === 'number') {
      payload.invoice_expiration = Math.round(payload.invoice_expiration * 60);
    }
    if (typeof payload.monitoring_expiration === 'number') {
      payload.monitoring_expiration = Math.round(payload.monitoring_expiration * 60);
    }
    if (!canEditBranding.value) {
      delete payload.brand_color;
      delete payload.css_url;
      delete payload.payment_sound_url;
    }
    if (!canEditPaymentOptions.value) {
      const paymentPaidFields = [
        'network_fee_mode', 'invoice_expiration', 'payment_tolerance', 'refund_bolt11_expiration',
        'monitoring_expiration', 'speed_policy',
        'lightning_description_template',
      ];
      paymentPaidFields.forEach((key) => delete payload[key]);
    }
    if (!canEditArchivedOption.value) {
      delete payload.archived;
    }
    if (!canEditRatesOptions.value) {
      delete payload.additional_tracked_rates;
    }
    if (!canEditCheckoutOptions.value) {
      const checkoutFields = [
        'default_lang', 'html_title', 'receipt', 'support_url', 'display_expiration_timer',
        'lightning_amount_in_satoshi', 'lightning_private_route_hints', 'on_chain_with_ln_invoice_fallback',
        'redirect_automatically', 'pay_join_enabled', 'auto_detect_language', 'show_pay_in_wallet_button',
        'show_store_header', 'celebrate_payment', 'play_sound_on_payment', 'lazy_payment_methods',
        'default_payment_method', 'payment_method_criteria',
      ];
      checkoutFields.forEach((key) => delete payload[key]);
    }
    await api.put(`/stores/${props.store.id}/settings`, payload);
    flashStore.success(t('settings.settings_updated'));
    emit('update-store'); // Notify parent to refresh store data if needed
  } catch (err: any) {
    const msg = err.response?.data?.message || t('settings.failed_to_update');
    const errors = err.response?.data?.errors ? Object.values(err.response.data.errors).flat() : [];
    flashStore.error(errors.length ? errors.join(', ') : msg);
  } finally {
    saving.value = false;
  }
}

async function handleLogoUpload(event: Event) {
  if (!props.store) return;
  
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file) return;

  uploadingLogo.value = true;
  logoError.value = '';
  logoSuccess.value = '';

  try {
    const formData = new FormData();
    formData.append('file', file);

    const response = await api.post(`/stores/${props.store.id}/logo`, formData);

    const data = response.data?.data ?? response.data;
    storeLogoUrl.value = data?.logo_url ?? data?.logoUrl ?? data?.imageUrl ?? storeLogoUrl.value;

    flashStore.success(t('stores.logo_uploaded') || 'Logo uploaded successfully');
    emit('update-store');
  } catch (err: any) {
    logoError.value = err.response?.data?.message || 'Failed to upload logo';
    flashStore.error(logoError.value);
  } finally {
    uploadingLogo.value = false;
  }
}

async function handleDeleteLogo() {
  if (!props.store) return;
  
  if (!confirm('Are you sure you want to delete the store logo?')) return;

  deletingLogo.value = true;
  logoError.value = '';
  logoSuccess.value = '';

  try {
    await api.delete(`/stores/${props.store.id}/logo`);
    storeLogoUrl.value = null;
    flashStore.success(t('stores.logo_deleted') || 'Logo deleted successfully');
    emit('update-store');
  } catch (err: any) {
    logoError.value = err.response?.data?.message || 'Failed to delete logo';
    flashStore.error(logoError.value);
  } finally {
    deletingLogo.value = false;
  }
}

async function handleDeleteStore() {
  if (!props.store) return;
  
  if (deleteStoreConfirmText.value !== 'DELETE') {
    flashStore.error('Please type DELETE to confirm');
    return;
  }

  deletingStore.value = true;
  deleteStoreError.value = '';

  try {
    await storesStore.deleteStore(props.store.id);
    showDeleteStoreModal.value = false;
    router.push({ name: 'home' });
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Failed to delete store';
    deleteStoreError.value = msg;
    flashStore.error(msg);
    deletingStore.value = false;
  }
}
</script>
