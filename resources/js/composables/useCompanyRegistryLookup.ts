import { ref } from 'vue';
import api from '../services/api';
import type { ContactFormState } from './useCompanyContact';
import {
  DEFAULT_REGISTRY_OPTIONS,
  registrySupportsAutocomplete,
  type RegistryCountryOption,
} from '../config/registryCountries';

/** Fields filled from registry lookup. */
export type CompanyRegistryFormState = {
  legal_name: string;
  registration_number: string;
  tax_id: string;
  street: string;
  city: string;
  postal_code: string;
  state_region: string;
  country: string;
  vat_payer: boolean;
  vat_number: string;
  commercial_register: string;
};

export type RegistrySummary = {
  ico: string;
  name: string;
  address_line?: string;
  dic?: string;
  ic_dph?: string;
  registry_jurisdiction?: string;
  source?: string;
};

export type RegistryDetail = {
  ico: string;
  name: string;
  dic?: string;
  ic_dph?: string;
  street?: string;
  city?: string;
  postal_code?: string;
  state_region?: string;
  country?: string;
  country_code?: string;
  registry_note?: string;
  legal_form?: string;
  source?: string;
};

const SUBJEKT_COUNTRIES = new Set(['sk', 'cz']);

export function useCompanyRegistryLookup() {
  const suggestions = ref<RegistrySummary[]>([]);
  const registryLoading = ref(false);
  const registryError = ref('');
  const showSuggestions = ref(false);
  const registryOptions = ref<RegistryCountryOption[]>([...DEFAULT_REGISTRY_OPTIONS]);
  let debounceTimer: ReturnType<typeof setTimeout> | null = null;
  let coverageLoaded = false;

  async function loadCoverage() {
    if (coverageLoaded) return;
    try {
      const res = await api.get('/invoicing/company-registry/coverage');
      const options = res.data.data?.options;
      if (Array.isArray(options) && options.length) {
        registryOptions.value = options;
      }
      coverageLoaded = true;
    } catch {
      coverageLoaded = true;
    }
  }

  function clearSuggestions() {
    suggestions.value = [];
    showSuggestions.value = false;
  }

  function isSubjektCountry(country: string): boolean {
    return SUBJEKT_COUNTRIES.has(country.toLowerCase());
  }

  async function search(query: string, country: string) {
    const q = query.trim();
    const c = country.toLowerCase();
    if (q.length < 2 || !registrySupportsAutocomplete(c, registryOptions.value)) {
      clearSuggestions();
      return;
    }

    registryLoading.value = true;
    registryError.value = '';
    try {
      if (isSubjektCountry(c)) {
        const digits = q.replace(/\D/g, '');
        if (digits.length >= 6 && digits.length <= 12 && /^\d[\d\s]*$/.test(q.replace(/\s/g, ''))) {
          const detail = await fetchDetail(digits, c);
          if (detail) {
            suggestions.value = [summaryFromDetail(detail)];
            showSuggestions.value = true;
            return;
          }
        }
      }

      const res = await api.get('/invoicing/company-registry/search', {
        params: { q, country: c, limit: 8 },
      });
      suggestions.value = res.data.data?.results ?? [];
      showSuggestions.value = suggestions.value.length > 0;
      if (
        res.data.data?.error === 'search_unavailable' ||
        res.data.data?.error === 'auth_required'
      ) {
        registryError.value = 'unavailable';
      }
    } catch {
      registryError.value = 'unavailable';
      clearSuggestions();
    } finally {
      registryLoading.value = false;
    }
  }

  function summaryFromDetail(detail: RegistryDetail): RegistrySummary {
    return {
      ico: detail.ico,
      name: detail.name,
      address_line: [detail.street, detail.postal_code, detail.city].filter(Boolean).join(', '),
      dic: detail.dic,
      ic_dph: detail.ic_dph,
      registry_jurisdiction: detail.country_code,
    };
  }

  async function fetchDetail(id: string, country: string): Promise<RegistryDetail | null> {
    try {
      const res = await api.get(`/invoicing/company-registry/entities/${encodeURIComponent(id)}`, {
        params: { country: country.toLowerCase() },
      });
      return res.data.data ?? null;
    } catch {
      return null;
    }
  }

  function scheduleSearch(query: string, country: string) {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => search(query, country), 320);
  }

  function applyDetailToForm(form: ContactFormState, detail: RegistryDetail, item?: RegistrySummary) {
    form.name = detail.name || form.name;
    form.registration_number = detail.ico || form.registration_number;
    if (detail.dic) form.tax_id = detail.dic;
    if (detail.ic_dph) form.vat_id = detail.ic_dph;
    if (detail.street) form.street = detail.street;
    if (detail.city) form.city = detail.city;
    else if (item?.address_line) form.city = item.address_line;
    if (detail.postal_code) form.postal_code = detail.postal_code;
    if (detail.state_region) form.state_region = detail.state_region;
    if (detail.country_code) {
      form.country = detail.country_code;
    } else if (detail.country) {
      form.country = detail.country;
    } else if (item?.registry_jurisdiction) {
      form.country = item.registry_jurisdiction;
    }
    if (detail.registry_note && !form.notes.trim()) {
      form.notes = detail.registry_note;
    }
  }

  function applyDetailToCompanyForm(
    form: CompanyRegistryFormState,
    detail: RegistryDetail,
    item?: RegistrySummary
  ) {
    form.legal_name = detail.name || form.legal_name;
    form.registration_number = detail.ico || form.registration_number;
    if (detail.dic) form.tax_id = detail.dic;
    if (detail.ic_dph) {
      form.vat_number = detail.ic_dph;
      form.vat_payer = true;
    }
    if (detail.street) form.street = detail.street;
    if (detail.city) form.city = detail.city;
    else if (item?.address_line) form.city = item.address_line;
    if (detail.postal_code) form.postal_code = detail.postal_code;
    if (detail.state_region) form.state_region = detail.state_region;
    if (detail.country_code) {
      form.country = detail.country_code;
    } else if (item?.registry_jurisdiction) {
      form.country = item.registry_jurisdiction;
    }
    if (detail.registry_note && !form.commercial_register.trim()) {
      form.commercial_register = detail.registry_note;
    }
  }

  function applySummaryAddress(
    target: { street: string; city: string; postal_code: string },
    addressLine?: string
  ) {
    const line = (addressLine ?? '').trim();
    if (!line) return;
    if (target.street.trim()) {
      if (!target.city.trim()) target.city = line;
      return;
    }
    const parts = line.split(',').map((p) => p.trim());
    if (parts.length >= 3) {
      target.street = parts[0];
      target.postal_code = parts[1];
      target.city = parts.slice(2).join(', ');
    } else {
      target.city = line;
    }
  }

  function applySummaryFallback(
    form: ContactFormState | CompanyRegistryFormState,
    item: RegistrySummary,
    mode: 'contact' | 'company'
  ) {
    if (mode === 'contact') {
      const f = form as ContactFormState;
      f.name = item.name;
      f.registration_number = item.ico;
      if (item.dic) f.tax_id = item.dic;
      if (item.ic_dph) f.vat_id = item.ic_dph;
      applySummaryAddress(f, item.address_line);
      if (item.registry_jurisdiction) f.country = item.registry_jurisdiction;
    } else {
      const f = form as CompanyRegistryFormState;
      f.legal_name = item.name;
      f.registration_number = item.ico;
      if (item.dic) f.tax_id = item.dic;
      if (item.ic_dph) {
        f.vat_number = item.ic_dph;
        f.vat_payer = true;
      }
      applySummaryAddress(f, item.address_line);
      if (item.registry_jurisdiction) f.country = item.registry_jurisdiction;
    }
  }

  async function selectSuggestion(form: ContactFormState, item: RegistrySummary, country: string) {
    showSuggestions.value = false;
    const detail = await fetchDetail(item.ico, country);
    if (detail) {
      applyDetailToForm(form, detail, item);
    } else {
      applySummaryFallback(form, item, 'contact');
    }
  }

  async function selectSuggestionForCompany(
    form: CompanyRegistryFormState,
    item: RegistrySummary,
    country: string
  ) {
    showSuggestions.value = false;
    const detail = await fetchDetail(item.ico, country);
    if (detail) {
      applyDetailToCompanyForm(form, detail, item);
    } else {
      applySummaryFallback(form, item, 'company');
    }
  }

  void loadCoverage();

  return {
    suggestions,
    registryLoading,
    registryError,
    showSuggestions,
    registryOptions,
    loadCoverage,
    scheduleSearch,
    clearSuggestions,
    selectSuggestion,
    selectSuggestionForCompany,
    applyDetailToForm,
    applyDetailToCompanyForm,
    fetchDetail,
    registrySupportsAutocomplete: (c: string) => registrySupportsAutocomplete(c, registryOptions.value),
  };
}
