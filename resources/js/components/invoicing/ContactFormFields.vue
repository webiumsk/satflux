<template>
  <div class="space-y-8">
    <template v-if="activeTab === 'billing'">
    <RegistryLookupField
      v-if="!readonly"
      v-model="form.name"
      v-model:registry-country="registryCountry"
      :label="t('invoicing.contact_name_label')"
      :placeholder="t('invoicing.contact_name_placeholder')"
      required
      :suggestions="suggestions"
      :registry-options="registryOptions"
      :registry-loading="registryLoading"
      :registry-error="registryError"
      :show-suggestions="showSuggestions"
      @input="onNameInput"
      @focus="onNameFocus"
      @blur="onNameBlur"
      @registry-country-change="onRegistryCountryChange"
      @pick="pickSuggestion"
    />
    <div v-else>
      <label class="invoicing-sf-label">{{ t('invoicing.contact_name_label') }}</label>
      <p class="contact-readonly-value text-lg font-semibold text-center py-2">{{ form.name || '—' }}</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="space-y-3">
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.address') }}</label>
          <textarea
            v-if="!readonly"
            v-model="form.street"
            rows="3"
            class="invoicing-sf-input"
          />
          <p v-else class="contact-readonly-value whitespace-pre-wrap">{{ form.street || '—' }}</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.postal_code') }}</label>
            <input v-if="!readonly" v-model="form.postal_code" type="text" class="invoicing-sf-input" />
            <p v-else class="contact-readonly-value">{{ form.postal_code || '—' }}</p>
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.city') }}</label>
            <input v-if="!readonly" v-model="form.city" type="text" class="invoicing-sf-input" />
            <p v-else class="contact-readonly-value">{{ form.city || '—' }}</p>
          </div>
        </div>
        <div v-if="showUsAddressFields">
          <label class="invoicing-sf-label">{{ t('invoicing.state_region') }}</label>
          <input v-if="!readonly" v-model="form.state_region" type="text" maxlength="2" class="invoicing-sf-input uppercase" placeholder="CA" />
          <p v-else class="contact-readonly-value">{{ form.state_region || '—' }}</p>
        </div>
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.country') }}</label>
          <input v-if="!readonly" v-model="form.country" type="text" class="invoicing-sf-input" />
          <p v-else class="contact-readonly-value">{{ form.country || '—' }}</p>
        </div>
      </div>

      <div class="space-y-3">
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.registration_number') }}</label>
          <input v-if="!readonly" v-model="form.registration_number" type="text" class="invoicing-sf-input" />
          <p v-else class="contact-readonly-value">{{ form.registration_number || '—' }}</p>
        </div>
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.tax_id_label') }}</label>
          <input v-if="!readonly" v-model="form.tax_id" type="text" class="invoicing-sf-input" />
          <p v-else class="contact-readonly-value">{{ form.tax_id || '—' }}</p>
        </div>
        <div v-if="showSkEfakturaFields">
          <label class="invoicing-sf-label">{{ t('invoicing.contact_peppol_participant_id') }}</label>
          <input
            v-if="!readonly"
            v-model="form.peppol_participant_id"
            type="text"
            class="invoicing-sf-input font-mono text-sm"
            :placeholder="t('invoicing.contact_peppol_participant_id_placeholder')"
          />
          <p v-else class="contact-readonly-value font-mono text-sm">{{ form.peppol_participant_id || '—' }}</p>
          <p v-if="!readonly" class="text-xs text-gray-500 mt-1">{{ t('invoicing.contact_peppol_participant_id_hint') }}</p>
        </div>
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.vat_id') }}</label>
          <div v-if="!readonly" class="flex gap-2 items-start">
            <input v-model="form.vat_id" type="text" class="invoicing-sf-input flex-1 min-w-0" />
            <button
              type="button"
              class="shrink-0 px-2 py-2 text-xs border border-gray-300 rounded-lg hover:bg-gray-50"
              :disabled="viesLoading || !form.vat_id.trim()"
              @click="checkVies"
            >
              {{ viesLoading ? t('invoicing.vies_checking') : t('invoicing.vies_validate') }}
            </button>
          </div>
          <p v-else class="contact-readonly-value">{{ form.vat_id || '—' }}</p>
          <p v-if="!readonly" class="text-xs text-gray-500 mt-1">{{ t('invoicing.vat_number_hint') }}</p>
          <p v-if="!readonly && viesFeedback" class="text-xs mt-1" :class="viesFeedbackClass">{{ viesFeedback }}</p>
        </div>
      </div>

      <div class="space-y-3">
        <div>
          <label class="invoicing-sf-label">Email</label>
          <input v-if="!readonly" v-model="form.email" type="email" class="invoicing-sf-input" />
          <p v-else class="contact-readonly-value">{{ form.email || '—' }}</p>
        </div>
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.contact_phone') }}</label>
          <input v-if="!readonly" v-model="form.phone" type="text" class="invoicing-sf-input" />
          <p v-else class="contact-readonly-value">{{ form.phone || '—' }}</p>
        </div>
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.fax') }}</label>
          <input v-if="!readonly" v-model="form.fax" type="text" class="invoicing-sf-input" />
          <p v-else class="contact-readonly-value">{{ form.fax || '—' }}</p>
        </div>
      </div>
    </div>

    <div v-if="!readonly" class="text-center">
      <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
        <input v-model="showDelivery" type="checkbox" class="rounded border-gray-300" />
        {{ t('invoicing.add_delivery_address') }}
      </label>
    </div>

    <div
      v-if="showDelivery || (readonly && hasDelivery)"
      class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-2 border-t border-gray-100"
    >
      <div class="md:col-span-3">
        <h3 class="text-xs font-semibold uppercase text-gray-500 mb-3">{{ t('invoicing.delivery_address') }}</h3>
      </div>
      <div class="md:col-span-2 space-y-3">
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.address') }}</label>
          <textarea
            v-if="!readonly"
            v-model="form.delivery_street"
            rows="2"
            class="invoicing-sf-input"
          />
          <p v-else class="contact-readonly-value">{{ form.delivery_street || '—' }}</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.postal_code') }}</label>
            <input v-if="!readonly" v-model="form.delivery_postal_code" type="text" class="invoicing-sf-input" />
            <p v-else class="contact-readonly-value">{{ form.delivery_postal_code || '—' }}</p>
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.city') }}</label>
            <input v-if="!readonly" v-model="form.delivery_city" type="text" class="invoicing-sf-input" />
            <p v-else class="contact-readonly-value">{{ form.delivery_city || '—' }}</p>
          </div>
        </div>
      </div>
      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.country') }}</label>
        <input v-if="!readonly" v-model="form.delivery_country" type="text" class="invoicing-sf-input" />
        <p v-else class="contact-readonly-value">{{ form.delivery_country || '—' }}</p>
      </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 border-t border-gray-100 pt-6">
      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.bank_account') }}</label>
        <input v-if="!readonly" v-model="form.bank_account" type="text" class="invoicing-sf-input" />
        <p v-else class="contact-readonly-value">{{ form.bank_account || '—' }}</p>
      </div>
      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.bank_code') }}</label>
        <input v-if="!readonly" v-model="form.bank_code" type="text" class="invoicing-sf-input" />
        <p v-else class="contact-readonly-value">{{ form.bank_code || '—' }}</p>
      </div>
      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.iban') }}</label>
        <input v-if="!readonly" v-model="form.iban" type="text" class="invoicing-sf-input" />
        <p v-else class="contact-readonly-value break-all">{{ form.iban || '—' }}</p>
      </div>
      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.swift') }}</label>
        <input v-if="!readonly" v-model="form.swift" type="text" class="invoicing-sf-input" />
        <p v-else class="contact-readonly-value">{{ form.swift || '—' }}</p>
      </div>
    </div>

    <div>
      <div class="invoicing-section-divider">{{ t('invoicing.contact_persons') }}</div>
      <div
        v-for="(person, idx) in form.contact_persons"
        :key="idx"
        class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3 items-end"
      >
        <div>
          <label v-if="idx === 0" class="invoicing-sf-label">{{ t('invoicing.contact_person_name') }}</label>
          <input
            v-if="!readonly"
            v-model="person.name"
            type="text"
            class="invoicing-sf-input"
          />
          <p v-else class="contact-readonly-value">{{ person.name || '—' }}</p>
        </div>
        <div>
          <label v-if="idx === 0" class="invoicing-sf-label">{{ t('invoicing.contact_phone') }}</label>
          <input
            v-if="!readonly"
            v-model="person.phone"
            type="text"
            class="invoicing-sf-input"
          />
          <p v-else class="contact-readonly-value">{{ person.phone || '—' }}</p>
        </div>
        <div class="flex gap-2">
          <div class="flex-1">
            <label v-if="idx === 0" class="invoicing-sf-label">Email</label>
            <input
              v-if="!readonly"
              v-model="person.email"
              type="email"
              class="invoicing-sf-input"
            />
            <p v-else class="contact-readonly-value">{{ person.email || '—' }}</p>
          </div>
          <button
            v-if="!readonly && form.contact_persons.length > 1"
            type="button"
            class="invoicing-btn-secondary px-2 py-1.5 shrink-0 mb-0.5"
            :title="t('common.delete')"
            @click="removePerson(idx)"
          >
            🗑
          </button>
        </div>
      </div>
      <button
        v-if="!readonly"
        type="button"
        class="invoicing-link text-sm"
        @click="addPerson"
      >
        + {{ t('invoicing.add_contact_person') }}
      </button>
    </div>
    </template>

    <div v-if="activeTab === 'defaults'" class="max-w-xs">
      <label class="invoicing-sf-label">{{ t('invoicing.default_payment_terms') }}</label>
      <input
        v-if="!readonly"
        v-model.number="form.default_payment_terms_days"
        type="number"
        min="0"
        max="365"
        class="invoicing-sf-input"
      />
      <p v-else class="contact-readonly-value">
        {{ form.default_payment_terms_days != null ? form.default_payment_terms_days : '—' }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ContactFormState } from '../../composables/useCompanyContact';
import RegistryLookupField from './RegistryLookupField.vue';
import { useCompanyRegistryLookup, type RegistrySummary } from '../../composables/useCompanyRegistryLookup';
import { isCompanyEfakturaEligible } from '../../composables/useCompanyEfakturaSettings';
import { useEfakturaFeature } from '../../composables/useEfakturaFeature';
import type { VatPolicyCompany } from '../../composables/useCompanyVatPolicy';
import { useViesValidation } from '../../composables/useViesValidation';

const { t } = useI18n();

const form = defineModel<ContactFormState>({ required: true });

const registryCountry = ref('sk');
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
const {
  suggestions,
  registryLoading,
  registryError,
  showSuggestions,
  registryOptions,
  scheduleSearch,
  clearSuggestions,
  selectSuggestion,
  registrySupportsAutocomplete,
} = useCompanyRegistryLookup();

const props = withDefaults(
  defineProps<{
    readonly?: boolean;
    activeTab?: 'billing' | 'defaults';
    company?: VatPolicyCompany | Record<string, unknown> | null;
  }>(),
  { readonly: false, activeTab: 'billing', company: null }
);

const showUsAddressFields = computed(() => {
  const c = (form.value.country || '').toUpperCase();
  return c === 'US' || c === 'USA' || c.includes('UNITED STATES') || registryCountry.value === 'us';
});

const { enabled: efakturaGloballyEnabled, load: loadEfakturaFeature } = useEfakturaFeature();

const showSkEfakturaFields = computed(() => {
  if (!isCompanyEfakturaEligible(props.company, efakturaGloballyEnabled.value)) {
    return false;
  }
  const c = (form.value.country || '').trim().toUpperCase();
  return c === 'SK' || c === 'SVK' || c.includes('SLOV') || registryCountry.value === 'sk';
});

onMounted(() => {
  void loadEfakturaFeature();
});

const showDelivery = ref(
  Boolean(
    form.value.delivery_street ||
      form.value.delivery_postal_code ||
      form.value.delivery_city ||
      form.value.delivery_country
  )
);

const hasDelivery = computed(
  () =>
    Boolean(
      form.value.delivery_street ||
        form.value.delivery_postal_code ||
        form.value.delivery_city ||
        form.value.delivery_country
    )
);

watch(showDelivery, (on) => {
  if (!on && !props.readonly) {
    form.value.delivery_street = '';
    form.value.delivery_postal_code = '';
    form.value.delivery_city = '';
    form.value.delivery_country = '';
  }
});

function addPerson() {
  form.value.contact_persons.push({ name: '', phone: '', email: '' });
}

function removePerson(idx: number) {
  form.value.contact_persons.splice(idx, 1);
}

function onNameInput() {
  if (registrySupportsAutocomplete(registryCountry.value)) {
    scheduleSearch(form.value.name, registryCountry.value);
  } else {
    clearSuggestions();
  }
}

function onNameFocus() {
  if (suggestions.value.length) {
    showSuggestions.value = true;
  }
}

function onNameBlur() {
  window.setTimeout(() => clearSuggestions(), 180);
}

function onRegistryCountryChange() {
  const reg = registryCountry.value;
  if (reg.length === 2 && reg !== 'us') {
    form.value.country = reg.toUpperCase();
  }
  if (reg === 'us') {
    form.value.country = 'US';
  }
  if (registrySupportsAutocomplete(reg) && form.value.name.trim().length >= 2) {
    scheduleSearch(form.value.name, reg);
  } else {
    clearSuggestions();
  }
}

async function pickSuggestion(item: RegistrySummary) {
  await selectSuggestion(form.value, item, registryCountry.value);
  clearSuggestions();
}

async function checkVies() {
  await validateVies(form.value.vat_id, registryCountry.value.toUpperCase());
}
</script>
