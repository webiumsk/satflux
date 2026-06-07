<template>
  <InvoicingPageShell :title="t('invoicing.company_form_title')">
    <template #back>
      <RouterLink :to="{ name: 'invoicing' }" class="invoicing-back">← {{ t('invoicing.title') }}</RouterLink>
    </template>

    <form class="invoicing-card-pad space-y-6" @submit.prevent="save">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.jurisdiction') }}</label>
          <InvoicingJurisdictionSelect v-model="form.jurisdiction" />
        </div>
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.currency') }}</label>
          <select v-model="form.default_currency" class="invoicing-sf-input">
            <option v-for="code in currencyOptions" :key="code" :value="code">{{ code }}</option>
          </select>
        </div>
      </div>

      <RegistryLookupField
        v-model="form.legal_name"
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

      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.address') }}</label>
        <input v-model="form.street" class="invoicing-sf-input" />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.city') }}</label>
          <input v-model="form.city" class="invoicing-sf-input" />
        </div>
        <div v-if="isUs">
          <label class="invoicing-sf-label">{{ t('invoicing.state_region') }} *</label>
          <input
            v-model="form.state_region"
            class="invoicing-sf-input uppercase"
            maxlength="2"
            placeholder="WY"
            required
          />
        </div>
        <div>
          <label class="invoicing-sf-label">{{ isUs ? t('invoicing.us_zip') : t('invoicing.postal_code') }}</label>
          <input
            v-model="form.postal_code"
            class="invoicing-sf-input"
            :required="isUs"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.country') }}</label>
          <select v-model="form.country" class="invoicing-sf-input">
            <option v-for="code in countryOptions" :key="code" :value="code">
              {{ countryLabel(code) }}
            </option>
          </select>
        </div>
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.btcpay_store') }}</label>
          <select v-model="form.store_id" class="invoicing-sf-input" :disabled="userStores.length === 0">
            <option value="">{{ t('invoicing.no_store') }}</option>
            <option v-for="s in userStores" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
          <p class="text-xs text-gray-500 mt-1">{{ t('invoicing.company_store_link_hint') }}</p>
        </div>
      </div>

      <template v-if="isUs">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.us_ein') }}</label>
            <input
              v-model="form.tax_id"
              class="invoicing-sf-input font-mono"
              placeholder="12-3456789"
            />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.us_state_file_number') }}</label>
            <input
              v-model="form.registration_number"
              class="invoicing-sf-input"
              :placeholder="t('invoicing.us_state_file_number_ph')"
            />
          </div>
        </div>
        <p class="text-xs text-indigo-800 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2">
          {{ t('invoicing.company_create_us_tax_hint') }}
        </p>
      </template>

      <template v-else>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.registration_number') }}</label>
            <input v-model="form.registration_number" class="invoicing-sf-input" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.tax_id_dic') }}</label>
            <input v-model="form.tax_id" class="invoicing-sf-input" />
          </div>
        </div>
      </template>

      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.company_bank_section') }}</label>
        <p class="text-xs text-gray-500 mb-2">{{ t('invoicing.company_branding_after_create') }}</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <input
            v-model="form.bank_name"
            class="invoicing-sf-input sm:col-span-2"
            :placeholder="t('invoicing.bank_name')"
          />
          <template v-if="isUs">
            <input
              v-model="form.bank_account"
              class="invoicing-sf-input"
              :placeholder="t('invoicing.us_routing_or_account')"
            />
            <input v-model="form.bic" class="invoicing-sf-input" placeholder="SWIFT / BIC" />
            <input
              v-model="form.iban"
              class="invoicing-sf-input sm:col-span-2"
              :placeholder="t('invoicing.us_iban_optional')"
            />
          </template>
          <template v-else>
            <input
              v-model="form.bank_account"
              class="invoicing-sf-input"
              :placeholder="t('invoicing.bank_account')"
            />
            <input
              v-model="form.bank_code"
              class="invoicing-sf-input"
              :placeholder="t('invoicing.bank_code')"
            />
            <input v-model="form.iban" class="invoicing-sf-input" placeholder="IBAN" />
            <input v-model="form.bic" class="invoicing-sf-input" placeholder="BIC / SWIFT" />
          </template>
        </div>
      </div>

      <label v-if="showVatPayer" class="flex items-center gap-2 text-sm text-gray-700">
        <input v-model="form.vat_payer" type="checkbox" class="rounded border-gray-300" />
        {{ t('invoicing.vat_payer') }}
      </label>

      <div class="flex flex-wrap gap-3 pt-2">
        <button type="submit" class="invoicing-btn-primary" :disabled="saving">
          {{ t('common.save') }}
        </button>
        <RouterLink :to="{ name: 'invoicing' }" class="invoicing-btn-secondary">
          {{ t('common.cancel') }}
        </RouterLink>
      </div>
    </form>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import InvoicingJurisdictionSelect from '../../components/invoicing/InvoicingJurisdictionSelect.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import RegistryLookupField from '../../components/invoicing/RegistryLookupField.vue';
import {
  countryAllowedForJurisdiction,
  countriesForJurisdiction,
  defaultCountryForJurisdiction,
  isUsJurisdiction,
  jurisdictionFromCountry,
} from '../../config/companyJurisdiction';
import {
  companyCurrencyOptions,
  defaultCurrencyForJurisdiction,
  syncCompanyDefaultCurrency,
} from '../../config/companyCurrencies';
import { countrySupportsVatPayer } from '../../config/euVatCountries';
import { defaultRegistryForJurisdiction } from '../../config/registryCountries';
import {
  useCompanyRegistryLookup,
  type RegistrySummary,
} from '../../composables/useCompanyRegistryLookup';
import api from '../../services/api';
import { useStoresStore } from '../../store/stores';

const { t, te } = useI18n();
const router = useRouter();
const storesStore = useStoresStore();
const saving = ref(false);

const form = reactive({
  legal_name: '',
  store_id: '',
  jurisdiction: 'eu_sk',
  default_currency: 'EUR',
  registration_number: '',
  tax_id: '',
  street: '',
  city: '',
  postal_code: '',
  state_region: '',
  country: 'SK',
  bank_name: '',
  bank_account: '',
  bank_code: '',
  iban: '',
  bic: '',
  vat_payer: false,
  vat_number: '',
  commercial_register: '',
});

const isUs = computed(() => isUsJurisdiction(form.jurisdiction));
const showVatPayer = computed(() => !isUs.value && countrySupportsVatPayer(form.country));
const userStores = computed(() => storesStore.stores);
const currencyOptions = computed(() => companyCurrencyOptions(form.default_currency));
const countryOptions = computed(() => countriesForJurisdiction(form.jurisdiction));

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
  registryCountry.value = defaultRegistryForJurisdiction(form.jurisdiction, form.country);
}

function applyRegistryCountryToForm(reg: string) {
  const iso = reg.toLowerCase() === 'uk' ? 'GB' : reg.toUpperCase();
  form.country = iso.length === 2 ? iso : defaultCountryForJurisdiction(form.jurisdiction);
  form.jurisdiction = jurisdictionFromCountry(form.country);
  if (isUsJurisdiction(form.jurisdiction) && !form.state_region) {
    form.state_region = 'WY';
  }
}

function onLegalNameInput() {
  if (registrySupportsAutocomplete(registryCountry.value)) {
    scheduleSearch(form.legal_name, registryCountry.value);
  } else {
    clearSuggestions();
  }
}

function onLegalNameFocus() {
  if (registrySupportsAutocomplete(registryCountry.value) && form.legal_name.trim().length >= 2) {
    scheduleSearch(form.legal_name, registryCountry.value);
  }
}

function onLegalNameBlur() {
  setTimeout(() => clearSuggestions(), 180);
}

function onRegistryCountryChange() {
  applyRegistryCountryToForm(registryCountry.value);
  if (registrySupportsAutocomplete(registryCountry.value) && form.legal_name.trim().length >= 2) {
    scheduleSearch(form.legal_name, registryCountry.value);
  } else {
    clearSuggestions();
  }
}

async function pickRegistrySuggestion(item: RegistrySummary) {
  await selectSuggestionForCompany(form, item, registryCountry.value);
  if (item.registry_jurisdiction) {
    form.country = item.registry_jurisdiction;
  }
}

function applyJurisdictionDefaults(jurisdiction: string) {
  const prevCountry = form.country;
  if (isUsJurisdiction(jurisdiction)) {
    form.country = 'US';
    if (!form.state_region) {
      form.state_region = 'WY';
    }
    form.vat_payer = false;
    form.vat_number = '';
    form.bank_code = '';
  } else {
    form.vat_payer = false;
    form.state_region = '';
    form.bank_code = form.bank_code || '';
    if (!countryAllowedForJurisdiction(form.country, jurisdiction)) {
      form.country = defaultCountryForJurisdiction(jurisdiction);
    }
  }
  form.default_currency = syncCompanyDefaultCurrency(
    form.default_currency,
    form.country,
    jurisdiction,
    prevCountry
  );
  syncRegistryCountryFromForm();
}

watch(
  () => form.jurisdiction,
  (jurisdiction: string) => {
    applyJurisdictionDefaults(jurisdiction);
    clearSuggestions();
    if (registrySupportsAutocomplete(registryCountry.value) && form.legal_name.trim().length >= 2) {
      scheduleSearch(form.legal_name, registryCountry.value);
    }
  }
);

watch(
  () => form.country,
  (country, previous) => {
    form.jurisdiction = jurisdictionFromCountry(country);
    form.default_currency = syncCompanyDefaultCurrency(
      form.default_currency,
      country,
      form.jurisdiction,
      previous
    );
    syncRegistryCountryFromForm();
    clearSuggestions();
    if (!countrySupportsVatPayer(form.country)) {
      form.vat_payer = false;
    }
  }
);

watch(showVatPayer, (visible) => {
  if (!visible) form.vat_payer = false;
});

syncRegistryCountryFromForm();
form.default_currency = defaultCurrencyForJurisdiction(form.jurisdiction, form.country);

onMounted(async () => {
  if (!storesStore.stores.length) {
    await storesStore.fetchStores();
  }
});

async function save() {
  saving.value = true;
  try {
    const payload = {
      legal_name: form.legal_name,
      store_id: form.store_id || null,
      jurisdiction: form.jurisdiction,
      default_currency: form.default_currency,
      registration_number: form.registration_number || null,
      tax_id: form.tax_id || null,
      street: form.street || null,
      city: form.city || null,
      postal_code: form.postal_code || null,
      state_region: form.state_region || null,
      country: form.country || null,
      bank_name: form.bank_name || null,
      bank_account: form.bank_account || null,
      bank_code: isUs.value ? null : form.bank_code || null,
      iban: form.iban || null,
      bic: form.bic || null,
      vat_number: isUs.value ? null : form.vat_number || null,
      commercial_register: form.commercial_register || null,
      vat_payer: showVatPayer.value ? form.vat_payer || Boolean(form.vat_number) : false,
      vat_status:
        !showVatPayer.value || (!form.vat_payer && !form.vat_number) ? 'none' : 'payer',
    };
    const res = await api.post('/invoicing/companies', payload);
    router.push({ name: 'invoicing-company', params: { companyId: res.data.data.id } });
  } finally {
    saving.value = false;
  }
}
</script>
