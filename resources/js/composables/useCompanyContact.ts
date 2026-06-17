import { computed, type Ref } from 'vue';
import { useRoute } from 'vue-router';

export type ContactPerson = { name?: string; phone?: string; email?: string };

export type ContactStats = {
  invoiced_total: number;
  invoiced_count: number;
  overdue_total: number;
  overdue_count: number;
  avg_payment_days: number | null;
};

export type CompanyContactRow = {
  id: string;
  name: string;
  registration_number?: string | null;
  email?: string | null;
  phone?: string | null;
  fax?: string | null;
  tax_id?: string | null;
  peppol_participant_id?: string | null;
  vat_id?: string | null;
  street?: string | null;
  city?: string | null;
  postal_code?: string | null;
  state_region?: string | null;
  country?: string | null;
  bank_account?: string | null;
  bank_code?: string | null;
  iban?: string | null;
  swift?: string | null;
  delivery_street?: string | null;
  delivery_postal_code?: string | null;
  delivery_city?: string | null;
  delivery_country?: string | null;
  default_payment_terms_days?: number | null;
  notes?: string | null;
  contact_persons?: ContactPerson[] | null;
  is_active?: boolean;
  stats?: ContactStats;
};

export type ContactFormState = {
  name: string;
  registration_number: string;
  email: string;
  phone: string;
  fax: string;
  tax_id: string;
  peppol_participant_id: string;
  vat_id: string;
  street: string;
  city: string;
  postal_code: string;
  state_region: string;
  country: string;
  bank_account: string;
  bank_code: string;
  iban: string;
  swift: string;
  delivery_street: string;
  delivery_postal_code: string;
  delivery_city: string;
  delivery_country: string;
  default_payment_terms_days: number | null;
  notes: string;
  contact_persons: ContactPerson[];
  is_active: boolean;
};

export function emptyContactForm(): ContactFormState {
  return {
    name: '',
    registration_number: '',
    email: '',
    phone: '',
    fax: '',
    tax_id: '',
    peppol_participant_id: '',
    vat_id: '',
    street: '',
    city: '',
    postal_code: '',
    state_region: '',
    country: 'SK',
    bank_account: '',
    bank_code: '',
    iban: '',
    swift: '',
    delivery_street: '',
    delivery_postal_code: '',
    delivery_city: '',
    delivery_country: '',
    default_payment_terms_days: 14,
    notes: '',
    contact_persons: [{ name: '', phone: '', email: '' }],
    is_active: true,
  };
}

export function contactToForm(c: CompanyContactRow): ContactFormState {
  const persons =
    c.contact_persons?.length
      ? c.contact_persons.map((p) => ({
          name: p.name ?? '',
          phone: p.phone ?? '',
          email: p.email ?? '',
        }))
      : [{ name: '', phone: '', email: '' }];

  return {
    name: c.name ?? '',
    registration_number: c.registration_number ?? '',
    email: c.email ?? '',
    phone: c.phone ?? '',
    fax: c.fax ?? '',
    tax_id: c.tax_id ?? '',
    peppol_participant_id: c.peppol_participant_id ?? '',
    vat_id: c.vat_id ?? '',
    street: c.street ?? '',
    city: c.city ?? '',
    postal_code: c.postal_code ?? '',
    state_region: c.state_region ?? '',
    country: c.country ?? 'SK',
    bank_account: c.bank_account ?? '',
    bank_code: c.bank_code ?? '',
    iban: c.iban ?? '',
    swift: c.swift ?? '',
    delivery_street: c.delivery_street ?? '',
    delivery_postal_code: c.delivery_postal_code ?? '',
    delivery_city: c.delivery_city ?? '',
    delivery_country: c.delivery_country ?? '',
    default_payment_terms_days: c.default_payment_terms_days ?? null,
    notes: c.notes ?? '',
    contact_persons: persons,
    is_active: c.is_active !== false,
  };
}

export function formToPayload(form: ContactFormState, includeDelivery: boolean) {
  const persons = form.contact_persons
    .filter((p) => (p.name || p.phone || p.email || '').trim() !== '')
    .map((p) => ({
      name: p.name || null,
      phone: p.phone || null,
      email: p.email || null,
    }));

  const payload: Record<string, unknown> = {
    name: form.name.trim(),
    registration_number: form.registration_number || null,
    email: form.email || null,
    phone: form.phone || null,
    fax: form.fax || null,
    tax_id: form.tax_id || null,
    peppol_participant_id: form.peppol_participant_id?.trim() || null,
    vat_id: form.vat_id || null,
    street: form.street || null,
    city: form.city || null,
    postal_code: form.postal_code || null,
    state_region: form.state_region || null,
    country: form.country || null,
    bank_account: form.bank_account || null,
    bank_code: form.bank_code || null,
    iban: form.iban || null,
    swift: form.swift || null,
    default_payment_terms_days: form.default_payment_terms_days ?? 14,
    notes: form.notes || null,
    contact_persons: persons.length ? persons : null,
    is_active: form.is_active,
  };

  if (includeDelivery) {
    payload.delivery_street = form.delivery_street || null;
    payload.delivery_postal_code = form.delivery_postal_code || null;
    payload.delivery_city = form.delivery_city || null;
    payload.delivery_country = form.delivery_country || null;
  } else {
    payload.delivery_street = null;
    payload.delivery_postal_code = null;
    payload.delivery_city = null;
    payload.delivery_country = null;
  }

  return payload;
}

export function useContactRoutes(companyId: Ref<string> | (() => string)) {
  const route = useRoute();
  const id = computed(() => (typeof companyId === 'function' ? companyId() : companyId.value));

  function contactShowTo(contactId: string) {
    return { name: 'invoicing-contact-show', params: { companyId: id.value, contactId } };
  }

  function contactEditTo(contactId: string) {
    return { name: 'invoicing-contact-edit', params: { companyId: id.value, contactId } };
  }

  function contactNewTo() {
    return { name: 'invoicing-contact-new', params: { companyId: id.value } };
  }

  function contactListTo() {
    return { name: 'invoicing-contacts', params: { companyId: id.value } };
  }

  function issueInvoiceTo(contactId: string) {
    return {
      name: 'invoicing-invoice-new',
      params: { companyId: id.value },
      query: { contact: contactId },
    };
  }

  return { route, contactShowTo, contactEditTo, contactNewTo, contactListTo, issueInvoiceTo };
}

export function formatContactMoney(amount: number, currency = 'EUR') {
  return new Intl.NumberFormat('sk-SK', {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
}

export function formatStatPair(
  total: number,
  count: number,
  currency = 'EUR'
): { main: string; sub: string; subAlert: boolean } {
  const main = `${formatContactMoney(total, currency)} / ${count}`;
  const sub = `${formatContactMoney(0, currency)} / 0`;
  const subAlert = false;
  return { main, sub, subAlert };
}

export function formatOverduePair(
  invoicedTotal: number,
  invoicedCount: number,
  overdueTotal: number,
  overdueCount: number,
  currency = 'EUR'
) {
  const main = `${formatContactMoney(invoicedTotal, currency)} / ${invoicedCount}`;
  const sub = `${formatContactMoney(overdueTotal, currency)} / ${overdueCount}`;
  const subAlert = overdueTotal > 0 || overdueCount > 0;
  return { main, sub, subAlert };
}

export const ALPHABET_FILTER = [
  'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
  'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '#',
] as const;
