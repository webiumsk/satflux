<template>
  <section class="invoicing-card-pad">
    <nav class="invoicing-tabs" role="tablist">
      <button
        type="button"
        role="tab"
        class="invoicing-tab"
        :class="{ 'invoicing-tab--active': activeTab === 'contact' }"
        @click="activeTab = 'contact'"
      >
        {{ t('invoicing.tab_contact_billing') }}
      </button>
      <button
        type="button"
        role="tab"
        class="invoicing-tab"
        :class="{ 'invoicing-tab--active': activeTab === 'bank' }"
        @click="activeTab = 'bank'"
      >
        {{ t('invoicing.tab_bank_accounts') }}
      </button>
      <button
        type="button"
        role="tab"
        class="invoicing-tab"
        :class="{ 'invoicing-tab--active': activeTab === 'branding' }"
        @click="activeTab = 'branding'"
      >
        {{ t('invoicing.tab_logo_signature') }}
      </button>
    </nav>

    <!-- Kontaktné a fakturačné údaje -->
    <form v-show="activeTab === 'contact'" class="space-y-6" @submit.prevent="saveContact">
      <RegistryLookupField
        v-model="contactForm.legal_name"
        v-model:registry-country="registryCountry"
        :label="t('invoicing.legal_name')"
        required
        :suggestions="suggestions"
        :registry-options="registryOptions"
        :registry-loading="registryLoading"
        :registry-error="registryError"
        :show-suggestions="showSuggestions"
        @input="onLegalNameInput"
        @focus="onLegalNameFocus"
        @blur="onLegalNameBlur"
        @registry-country-change="onRegistryCountryChange"
        @pick="pickRegistrySuggestion"
      />

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
        <div class="space-y-4">
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.address') }}</label>
            <textarea v-model="contactForm.street" rows="2" class="invoicing-sf-input" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="invoicing-sf-label">{{ isUs ? t('invoicing.us_zip') : t('invoicing.postal_code') }}</label>
              <input v-model="contactForm.postal_code" class="invoicing-sf-input" />
            </div>
            <div>
              <label class="invoicing-sf-label">{{ t('invoicing.city') }}</label>
              <input v-model="contactForm.city" class="invoicing-sf-input" />
            </div>
          </div>
          <div v-if="isUs">
            <label class="invoicing-sf-label">{{ t('invoicing.state_region') }}</label>
            <input
              v-model="contactForm.state_region"
              class="invoicing-sf-input uppercase"
              maxlength="2"
              placeholder="WY"
            />
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="invoicing-sf-label">{{ t('invoicing.country') }}</label>
              <select v-model="contactForm.country" class="invoicing-sf-input">
                <option v-for="code in countryOptions" :key="code" :value="code">
                  {{ countryLabel(code) }}
                </option>
              </select>
            </div>
            <div>
              <label class="invoicing-sf-label">{{ t('invoicing.btcpay_store') }}</label>
              <select v-model="linkedStoreId" class="invoicing-sf-input" :disabled="userStores.length === 0">
                <option value="">{{ t('invoicing.no_store') }}</option>
                <option v-for="s in userStores" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
          </div>
          <p class="text-xs text-gray-500 -mt-2">{{ t('invoicing.company_store_link_hint') }}</p>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.jurisdiction') }}</label>
            <InvoicingJurisdictionSelect v-model="contactForm.jurisdiction" />
          </div>
        </div>

        <div class="space-y-4">
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.company_phone') }}</label>
            <input v-model="contactForm.issuer_phone" type="tel" class="invoicing-sf-input" />
            <p class="text-xs text-gray-500 mt-1">{{ t('invoicing.company_phone_hint') }}</p>
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.website') }}</label>
            <input v-model="contactForm.website" type="url" class="invoicing-sf-input" placeholder="https://" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.commercial_register') }}</label>
            <input v-model="contactForm.commercial_register" class="invoicing-sf-input" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.issuer_name') }}</label>
            <input v-model="contactForm.issuer_name" class="invoicing-sf-input" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.issuer_email') }}</label>
            <input v-model="contactForm.issuer_email" type="email" class="invoicing-sf-input" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.legal_footer_note') }}</label>
            <textarea v-model="contactForm.legal_footer_note" rows="2" class="invoicing-sf-input" />
          </div>
        </div>

        <div class="space-y-4">
          <template v-if="isUs">
            <div>
              <label class="invoicing-sf-label">{{ t('invoicing.us_ein') }}</label>
              <input
                v-model="contactForm.tax_id"
                class="invoicing-sf-input font-mono"
                placeholder="12-3456789"
              />
            </div>
            <div>
              <label class="invoicing-sf-label">{{ t('invoicing.us_state_file_number') }}</label>
              <input
                v-model="contactForm.registration_number"
                class="invoicing-sf-input"
                :placeholder="t('invoicing.us_state_file_number_ph')"
              />
            </div>
            <p class="text-xs text-indigo-800 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2">
              {{ t('invoicing.company_settings_us_tax_hint') }}
            </p>
          </template>
          <template v-else>
            <div>
              <label class="invoicing-sf-label">{{ t('invoicing.registration_number') }} *</label>
              <input v-model="contactForm.registration_number" required class="invoicing-sf-input" />
            </div>
            <div>
              <label class="invoicing-sf-label">{{ t('invoicing.tax_id_dic') }}</label>
              <input v-model="contactForm.tax_id" class="invoicing-sf-input" />
            </div>
            <div>
              <label class="invoicing-sf-label">{{ t('invoicing.vat_number_ic_dph') }}</label>
              <div class="flex gap-2 items-start">
                <input
                  v-model="contactForm.vat_number"
                  class="invoicing-sf-input flex-1 min-w-0"
                  placeholder="SK2023980035"
                />
                <button
                  type="button"
                  class="shrink-0 px-2 py-2 text-xs border border-gray-300 rounded-lg hover:bg-gray-50"
                  :disabled="viesLoading || !contactForm.vat_number.trim()"
                  @click="checkCompanyVies"
                >
                  {{ viesLoading ? t('invoicing.vies_checking') : t('invoicing.vies_validate') }}
                </button>
              </div>
              <p v-if="viesFeedback" class="text-xs mt-1" :class="viesFeedbackClass">{{ viesFeedback }}</p>
            </div>
            <fieldset class="space-y-2">
              <legend class="invoicing-sf-label mb-2">{{ t('invoicing.vat_status') }}</legend>
              <label class="flex items-start gap-2 text-sm text-gray-800 cursor-pointer">
                <input v-model="vatStatus" type="radio" value="none" class="mt-0.5" />
                {{ t('invoicing.vat_status_none') }}
              </label>
              <label class="flex items-start gap-2 text-sm text-gray-800 cursor-pointer">
                <input v-model="vatStatus" type="radio" value="payer" class="mt-0.5" />
                {{ t('invoicing.vat_status_payer') }}
              </label>
              <label class="flex items-start gap-2 text-sm text-gray-800 cursor-pointer">
                <input v-model="vatStatus" type="radio" value="partial" class="mt-0.5" />
                {{ t('invoicing.vat_status_partial') }}
              </label>
            </fieldset>
          </template>
        </div>
      </div>

      <div class="pt-2 border-t border-gray-100">
        <button type="submit" class="invoicing-btn-primary" :disabled="savingContact">
          {{ t('invoicing.save_my_details') }}
        </button>
        <p class="text-xs text-gray-500 mt-2">{{ t('invoicing.profile_save_note') }}</p>
      </div>
    </form>

    <!-- Bankové účty -->
    <form v-show="activeTab === 'bank'" class="space-y-6 max-w-3xl" @submit.prevent="saveBank">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.bank_name') }}</label>
          <input v-model="bankForm.bank_name" class="invoicing-sf-input" />
        </div>
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.currency') }}</label>
          <select v-model="bankForm.default_currency" class="invoicing-sf-input">
            <option v-for="code in currencyOptions" :key="code" :value="code">{{ code }}</option>
          </select>
        </div>
        <div :class="isUs ? 'sm:col-span-2' : ''">
          <label class="invoicing-sf-label">{{ t('invoicing.bank_account') }}</label>
          <input
            v-model="bankForm.bank_account"
            class="invoicing-sf-input"
            :placeholder="isUs ? t('invoicing.us_routing_or_account') : undefined"
          />
          <p class="text-xs text-gray-500 mt-1">{{ t('invoicing.bank_account_hint') }}</p>
        </div>
        <div v-if="!isUs">
          <label class="invoicing-sf-label">{{ t('invoicing.bank_code') }}</label>
          <input v-model="bankForm.bank_code" class="invoicing-sf-input" maxlength="8" />
        </div>
        <div class="sm:col-span-2">
          <label class="invoicing-sf-label">IBAN</label>
          <input
            v-model="bankForm.iban"
            class="invoicing-sf-input font-mono text-sm"
            :placeholder="isUs ? t('invoicing.us_iban_optional') : undefined"
          />
        </div>
        <div class="sm:col-span-2">
          <label class="invoicing-sf-label">{{ t('invoicing.swift') }} / BIC</label>
          <input v-model="bankForm.bic" class="invoicing-sf-input font-mono text-sm" maxlength="11" />
        </div>
      </div>

      <div class="pt-2 border-t border-gray-100">
        <button type="submit" class="invoicing-btn-primary" :disabled="savingBank">
          {{ t('invoicing.save_my_details') }}
        </button>
        <p class="text-xs text-gray-500 mt-2">{{ t('invoicing.profile_save_note') }}</p>
      </div>
    </form>

    <!-- Logo a podpis -->
    <div v-show="activeTab === 'branding'" class="grid grid-cols-1 md:grid-cols-2 gap-8">
      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.company_logo') }}</label>
        <p class="text-xs text-gray-500 mb-2">{{ t('invoicing.company_logo_hint') }}</p>
        <div v-if="logoPreviewUrl" class="mb-3 p-3 border border-gray-200 rounded-lg bg-gray-50 inline-block">
          <img :src="logoPreviewUrl" alt="" class="max-h-16 max-w-[200px] object-contain" />
        </div>
        <div class="flex flex-wrap gap-2">
          <label class="invoicing-btn-secondary cursor-pointer">
            {{ t('invoicing.upload_image') }}
            <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" @change="uploadLogo" />
          </label>
          <button
            v-if="logoPreviewUrl"
            type="button"
            class="invoicing-btn-secondary text-red-700"
            :disabled="uploadingLogo"
            @click="removeLogo"
          >
            {{ t('common.delete') }}
          </button>
        </div>
      </div>

      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.company_signature_stamp') }}</label>
        <p class="text-xs text-gray-500 mb-2">{{ t('invoicing.company_signature_stamp_hint') }}</p>
        <div v-if="signaturePreviewUrl" class="mb-3 p-3 border border-gray-200 rounded-lg bg-gray-50 inline-block">
          <img :src="signaturePreviewUrl" alt="" class="max-h-20 max-w-[240px] object-contain" />
        </div>
        <div class="flex flex-wrap gap-2">
          <label class="invoicing-btn-secondary cursor-pointer">
            {{ t('invoicing.upload_image') }}
            <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" @change="uploadSignature" />
          </label>
          <button
            v-if="signaturePreviewUrl"
            type="button"
            class="invoicing-btn-secondary text-red-700"
            :disabled="uploadingSignature"
            @click="removeSignature"
          >
            {{ t('common.delete') }}
          </button>
        </div>
      </div>
    </div>

    <p v-if="saveError" class="mt-4 text-sm text-red-600">{{ saveError }}</p>

    <div class="mt-8 rounded-lg border border-red-200 bg-red-50 overflow-hidden">
      <div class="px-5 py-4 border-b border-red-200 bg-red-100/60">
        <h2 class="text-base font-semibold text-red-800">{{ t('invoicing.company_reset_danger_zone') }}</h2>
      </div>
      <div class="px-5 py-5 space-y-4">
        <div>
          <h3 class="text-sm font-semibold text-red-900">{{ t('invoicing.company_reset_title') }}</h3>
          <p class="text-sm text-red-800/90 mt-1 max-w-2xl">{{ t('invoicing.company_reset_desc') }}</p>
        </div>
        <button
          type="button"
          class="invoicing-btn-secondary border-red-300 text-red-700 hover:bg-red-100"
          @click="showResetModal = true"
        >
          {{ t('invoicing.company_reset_action') }}
        </button>
      </div>
    </div>

    <div
      v-if="showResetModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
      role="dialog"
      aria-modal="true"
      @click.self="showResetModal = false"
    >
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-5 space-y-4">
        <h3 class="text-lg font-semibold text-gray-900">{{ t('invoicing.company_reset_confirm_title') }}</h3>
        <p class="text-sm text-gray-700">{{ t('invoicing.company_reset_confirm_desc') }}</p>
        <ul class="text-sm text-gray-600 list-disc pl-5 space-y-1">
          <li>{{ t('invoicing.company_reset_item_documents') }}</li>
          <li>{{ t('invoicing.company_reset_item_contacts') }}</li>
          <li>{{ t('invoicing.company_reset_item_expenses') }}</li>
          <li>{{ t('invoicing.company_reset_item_payments') }}</li>
          <li>{{ t('invoicing.company_reset_item_recurring') }}</li>
          <li>{{ t('invoicing.company_reset_item_sequences') }}</li>
        </ul>
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.company_reset_confirm_label', { name: companyDisplayName }) }}</label>
          <input v-model="resetConfirmName" type="text" class="invoicing-sf-input" />
        </div>
        <p v-if="resetError" class="text-sm text-red-600">{{ resetError }}</p>
        <div class="flex justify-end gap-3">
          <button type="button" class="invoicing-btn-secondary" :disabled="resetting" @click="showResetModal = false">
            {{ t('common.cancel') }}
          </button>
          <button
            type="button"
            class="invoicing-btn-primary bg-red-600 hover:bg-red-700 border-red-600"
            :disabled="resetting || !resetConfirmMatches"
            @click="resetCompanyData"
          >
            {{ resetting ? t('common.loading') : t('invoicing.company_reset_action') }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import api from '../../services/api';
import InvoicingJurisdictionSelect from './InvoicingJurisdictionSelect.vue';
import RegistryLookupField from './RegistryLookupField.vue';
import {
  countryAllowedForJurisdiction,
  countriesForJurisdiction,
  defaultCountryForJurisdiction,
  isUsJurisdiction,
  jurisdictionFromCountry,
} from '../../config/companyJurisdiction';
import {
  companyCurrencyOptions,
  syncCompanyDefaultCurrency,
} from '../../config/companyCurrencies';
import { defaultRegistryForJurisdiction } from '../../config/registryCountries';
import {
  useCompanyRegistryLookup,
  type CompanyRegistryFormState,
  type RegistrySummary,
} from '../../composables/useCompanyRegistryLookup';
import { useViesValidation } from '../../composables/useViesValidation';
import { useStoresStore } from '../../store/stores';

const props = defineProps<{
  companyId: string;
  company: Record<string, any> | null;
}>();

const emit = defineEmits<{
  updated: [company: Record<string, any>];
}>();

const { t, te } = useI18n();
const router = useRouter();
const storesStore = useStoresStore();

const activeTab = ref<'contact' | 'bank' | 'branding'>('contact');
const linkedStoreId = ref('');
const savedLinkedStoreId = ref('');
const userStores = computed(() => storesStore.stores);
const currencyOptions = computed(() => companyCurrencyOptions(bankForm.default_currency));
const savingContact = ref(false);
const savingBank = ref(false);
const uploadingLogo = ref(false);
const uploadingSignature = ref(false);
const saveError = ref('');
const showResetModal = ref(false);
const resetConfirmName = ref('');
const resetting = ref(false);
const resetError = ref('');
const logoPreviewUrl = ref<string | null>(null);
const signaturePreviewUrl = ref<string | null>(null);
const vatStatus = ref<'none' | 'payer' | 'partial'>('none');
const { loading: viesLoading, message: viesMessage, lastResult: viesResult, validate: validateVies } =
  useViesValidation();
const viesFeedback = computed(() => {
  if (viesMessage.value === 'unavailable') return t('invoicing.vies_unavailable');
  if (viesResult.value?.valid) return t('invoicing.vies_valid');
  if (viesResult.value && !viesResult.value.valid) {
    return viesResult.value.error_message || t('invoicing.vies_invalid');
  }
  return '';
});
const viesFeedbackClass = computed(() =>
  viesResult.value?.valid ? 'text-emerald-700' : 'text-amber-800'
);

const contactForm = reactive({
  legal_name: '',
  street: '',
  city: '',
  postal_code: '',
  state_region: '',
  country: 'SK',
  jurisdiction: 'eu_sk',
  registration_number: '',
  tax_id: '',
  vat_number: '',
  commercial_register: '',
  issuer_name: '',
  issuer_phone: '',
  issuer_email: '',
  website: '',
  legal_footer_note: '',
});

const bankForm = reactive({
  bank_name: '',
  bank_account: '',
  bank_code: '',
  iban: '',
  bic: '',
  default_currency: 'EUR',
});

const companyDisplayName = computed(
  () => props.company?.trade_name || props.company?.legal_name || ''
);

const resetConfirmMatches = computed(() => {
  const confirm = resetConfirmName.value.trim();
  const legal = String(props.company?.legal_name ?? '').trim();
  const trade = String(props.company?.trade_name ?? '').trim();
  return confirm !== '' && (confirm === legal || confirm === trade);
});

const isUs = computed(() => isUsJurisdiction(contactForm.jurisdiction));
const countryOptions = computed(() => countriesForJurisdiction(contactForm.jurisdiction));

function countryLabel(code: string): string {
  const key = `invoicing.country_${code.toLowerCase()}`;
  return te(key) ? t(key) : code;
}

const registryCountry = ref('sk');

const {
  suggestions,
  registryLoading,
  registryError,
  showSuggestions,
  registryOptions,
  scheduleSearch,
  clearSuggestions,
  selectSuggestionForCompany,
  registrySupportsAutocomplete,
} = useCompanyRegistryLookup();

function syncRegistryCountryFromForm() {
  registryCountry.value = defaultRegistryForJurisdiction(
    contactForm.jurisdiction,
    contactForm.country
  );
}

function applyRegistryCountryToForm(reg: string) {
  const iso = reg.toLowerCase() === 'uk' ? 'GB' : reg.toUpperCase();
  contactForm.country = iso.length === 2 ? iso : defaultCountryForJurisdiction(contactForm.jurisdiction);
  contactForm.jurisdiction = jurisdictionFromCountry(contactForm.country);
  if (isUsJurisdiction(contactForm.jurisdiction)) {
    if (!contactForm.state_region) contactForm.state_region = 'WY';
    vatStatus.value = 'none';
    contactForm.vat_number = '';
  }
}

function onLegalNameInput() {
  if (registrySupportsAutocomplete(registryCountry.value)) {
    scheduleSearch(contactForm.legal_name, registryCountry.value);
  } else {
    clearSuggestions();
  }
}

function onLegalNameFocus() {
  if (
    registrySupportsAutocomplete(registryCountry.value) &&
    contactForm.legal_name.trim().length >= 2
  ) {
    scheduleSearch(contactForm.legal_name, registryCountry.value);
  }
}

function onLegalNameBlur() {
  setTimeout(() => clearSuggestions(), 180);
}

function onRegistryCountryChange() {
  applyRegistryCountryToForm(registryCountry.value);
  if (
    registrySupportsAutocomplete(registryCountry.value) &&
    contactForm.legal_name.trim().length >= 2
  ) {
    scheduleSearch(contactForm.legal_name, registryCountry.value);
  } else {
    clearSuggestions();
  }
}

async function pickRegistrySuggestion(item: RegistrySummary) {
  await selectSuggestionForCompany(
    contactForm as unknown as CompanyRegistryFormState,
    item,
    registryCountry.value
  );
  if (contactForm.vat_number.trim()) {
    vatStatus.value = 'payer';
  }
  syncRegistryCountryFromForm();
}

function applyJurisdictionDefaults(jurisdiction: string, previous: string) {
  const prevCountry = contactForm.country;
  if (isUsJurisdiction(jurisdiction)) {
    contactForm.country = 'US';
    vatStatus.value = 'none';
    contactForm.vat_number = '';
    bankForm.bank_code = '';
  } else {
    if (previous === 'us' && contactForm.country === 'US') {
      contactForm.country = defaultCountryForJurisdiction(jurisdiction);
    }
    if (!countryAllowedForJurisdiction(contactForm.country, jurisdiction)) {
      contactForm.country = defaultCountryForJurisdiction(jurisdiction);
    }
  }
  bankForm.default_currency = syncCompanyDefaultCurrency(
    bankForm.default_currency,
    contactForm.country,
    jurisdiction,
    prevCountry
  );
  syncRegistryCountryFromForm();
}

watch(
  () => contactForm.jurisdiction,
  (jurisdiction: string, previous?: string) => {
    applyJurisdictionDefaults(jurisdiction, previous ?? jurisdiction);
    syncRegistryCountryFromForm();
    clearSuggestions();
    if (
      registrySupportsAutocomplete(registryCountry.value) &&
      contactForm.legal_name.trim().length >= 2
    ) {
      scheduleSearch(contactForm.legal_name, registryCountry.value);
    }
  }
);

watch(
  () => contactForm.country,
  (country, previous) => {
    contactForm.jurisdiction = jurisdictionFromCountry(country);
    bankForm.default_currency = syncCompanyDefaultCurrency(
      bankForm.default_currency,
      country,
      contactForm.jurisdiction,
      previous
    );
    syncRegistryCountryFromForm();
    clearSuggestions();
  }
);

function applyCompany(c: Record<string, any>) {
  contactForm.legal_name = c.legal_name ?? '';
  contactForm.street = c.street ?? '';
  linkedStoreId.value = c.stores?.[0]?.id ?? '';
  savedLinkedStoreId.value = linkedStoreId.value;
  contactForm.city = c.city ?? '';
  contactForm.postal_code = c.postal_code ?? '';
  contactForm.state_region = c.state_region ?? '';
  contactForm.jurisdiction = c.jurisdiction ?? 'eu_sk';
  if (isUsJurisdiction(contactForm.jurisdiction)) {
    contactForm.country = c.country && c.country !== 'SK' ? c.country : 'US';
  } else {
    contactForm.country = c.country ?? 'SK';
  }
  contactForm.registration_number = c.registration_number ?? '';
  contactForm.tax_id = c.tax_id ?? '';
  contactForm.vat_number = c.vat_number ?? '';
  contactForm.commercial_register = c.commercial_register ?? '';
  contactForm.issuer_name = c.issuer_name ?? '';
  contactForm.issuer_phone = c.issuer_phone ?? '';
  contactForm.issuer_email = c.issuer_email ?? '';
  contactForm.website = c.website ?? '';
  contactForm.legal_footer_note = c.legal_footer_note ?? '';

  bankForm.bank_name = c.bank_name ?? '';
  bankForm.bank_account = c.bank_account ?? '';
  bankForm.bank_code = c.bank_code ?? '';
  bankForm.iban = c.iban ?? '';
  bankForm.bic = c.bic ?? '';
  bankForm.default_currency = c.default_currency ?? 'EUR';

  if (isUsJurisdiction(contactForm.jurisdiction)) {
    vatStatus.value = 'none';
  } else {
    const vs = c.vat_status;
    vatStatus.value = vs === 'partial' || vs === 'payer' ? vs : c.vat_payer ? 'payer' : 'none';
  }
  logoPreviewUrl.value = c.logo_url ?? null;
  signaturePreviewUrl.value = c.signature_stamp_url ?? null;
  syncRegistryCountryFromForm();
  clearSuggestions();
}

watch(
  () => props.company,
  (c) => {
    if (c) applyCompany(c);
  },
  { immediate: true }
);

function vatPayload() {
  if (isUs.value) {
    return { vat_status: 'none', vat_payer: false, vat_number: null };
  }
  return {
    vat_status: vatStatus.value,
    vat_payer: vatStatus.value === 'payer' || vatStatus.value === 'partial',
  };
}

async function checkCompanyVies() {
  const country = (contactForm.country || 'SK').slice(0, 2).toUpperCase();
  await validateVies(contactForm.vat_number, country);
}

async function saveContact() {
  savingContact.value = true;
  saveError.value = '';
  try {
    const res = await api.patch(`/invoicing/companies/${props.companyId}`, {
      ...contactForm,
      ...vatPayload(),
    });
    let payload = res.data.data as Record<string, any>;
    if (linkedStoreId.value !== savedLinkedStoreId.value) {
      const storeRes = await api.patch(`/invoicing/companies/${props.companyId}/stores`, {
        store_ids: linkedStoreId.value ? [linkedStoreId.value] : [],
      });
      payload = {
        ...payload,
        stores: storeRes.data.data?.stores ?? storeRes.data.data,
      };
      savedLinkedStoreId.value = linkedStoreId.value;
    }
    emit('updated', payload);
  } catch (e: any) {
    saveError.value = extractSaveError(e);
  } finally {
    savingContact.value = false;
  }
}

onMounted(async () => {
  if (!storesStore.stores.length) {
    await storesStore.fetchStores();
  }
});

function extractSaveError(e: any): string {
  const errors = e?.response?.data?.errors;
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors).flat()[0];
    if (typeof first === 'string') {
      return first;
    }
  }
  return e?.response?.data?.message || t('common.error');
}

async function saveBank() {
  savingBank.value = true;
  saveError.value = '';
  try {
    const payload = { ...bankForm };
    if (isUs.value) {
      payload.bank_code = '';
    }
    const res = await api.patch(`/invoicing/companies/${props.companyId}`, payload);
    emit('updated', res.data.data);
  } catch (e: any) {
    saveError.value = extractSaveError(e);
  } finally {
    savingBank.value = false;
  }
}

async function uploadLogo(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  uploadingLogo.value = true;
  saveError.value = '';
  try {
    const fd = new FormData();
    fd.append('image', file);
    const res = await api.post(`/invoicing/companies/${props.companyId}/branding/logo`, fd);
    emit('updated', res.data.data);
  } catch (err: any) {
    saveError.value = err?.response?.data?.message || t('common.error');
  } finally {
    uploadingLogo.value = false;
    (e.target as HTMLInputElement).value = '';
  }
}

async function uploadSignature(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  uploadingSignature.value = true;
  saveError.value = '';
  try {
    const fd = new FormData();
    fd.append('image', file);
    const res = await api.post(`/invoicing/companies/${props.companyId}/branding/signature-stamp`, fd);
    emit('updated', res.data.data);
  } catch (err: any) {
    saveError.value = err?.response?.data?.message || t('common.error');
  } finally {
    uploadingSignature.value = false;
    (e.target as HTMLInputElement).value = '';
  }
}

async function removeLogo() {
  uploadingLogo.value = true;
  try {
    const res = await api.delete(`/invoicing/companies/${props.companyId}/branding/logo`);
    emit('updated', res.data.data);
  } finally {
    uploadingLogo.value = false;
  }
}

async function removeSignature() {
  uploadingSignature.value = true;
  try {
    const res = await api.delete(`/invoicing/companies/${props.companyId}/branding/signature-stamp`);
    emit('updated', res.data.data);
  } finally {
    uploadingSignature.value = false;
  }
}

async function resetCompanyData() {
  if (!resetConfirmMatches.value) return;
  resetting.value = true;
  resetError.value = '';
  try {
    await api.post(`/invoicing/companies/${props.companyId}/reset-data`, {
      confirm_name: resetConfirmName.value.trim(),
    });
    showResetModal.value = false;
    resetConfirmName.value = '';
    await router.push({ name: 'invoicing-invoices', params: { companyId: props.companyId } });
  } catch (e: any) {
    resetError.value = e?.response?.data?.message || t('errors.generic');
  } finally {
    resetting.value = false;
  }
}
</script>
