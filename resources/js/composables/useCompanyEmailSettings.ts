export type EmailTemplate = { subject: string; body: string };

export type EmailSmtpState = {
  username: string;
  password: string;
  host: string;
  port: number | null;
  from_name: string;
  encryption: 'tls' | 'ssl' | 'none';
  use_smtp_email_as_from: boolean;
  password_set?: boolean;
};

export type CompanyEmailSettingsState = {
  delivery_method: 'system' | 'smtp' | 'gmail' | 'office';
  smtp: EmailSmtpState;
  templates: Record<string, EmailTemplate>;
};

export const EMAIL_TEMPLATE_KEYS = [
  'invoice',
  'settlement_invoice',
  'invoice_from_proforma',
  'credit_note',
  'proforma',
  'quote',
  'delivery_note',
  'order_received',
  'order_issued',
  'reminder_sms',
  'reminder_email',
  'dunning_sms',
  'dunning_email',
  'thank_you',
] as const;

export type EmailTemplateKey = (typeof EMAIL_TEMPLATE_KEYS)[number];

export const EMAIL_TEMPLATE_LABEL_KEYS: Record<EmailTemplateKey, string> = {
  invoice: 'invoicing.email_tpl_invoice',
  settlement_invoice: 'invoicing.email_tpl_settlement',
  invoice_from_proforma: 'invoicing.email_tpl_from_proforma',
  credit_note: 'invoicing.email_tpl_credit_note',
  proforma: 'invoicing.email_tpl_proforma',
  quote: 'invoicing.email_tpl_quote',
  delivery_note: 'invoicing.email_tpl_delivery_note',
  order_received: 'invoicing.email_tpl_order_received',
  order_issued: 'invoicing.email_tpl_order_issued',
  reminder_sms: 'invoicing.email_tpl_reminder_sms',
  reminder_email: 'invoicing.email_tpl_reminder_email',
  dunning_sms: 'invoicing.email_tpl_dunning_sms',
  dunning_email: 'invoicing.email_tpl_dunning_email',
  thank_you: 'invoicing.email_tpl_thank_you',
};

export const EMAIL_PLACEHOLDERS = [
  { token: '#MOJA_FIRMA#', key: 'invoicing.email_ph_company' },
  { token: '#MENO#', key: 'invoicing.email_ph_sender' },
  { token: '#NAZOV_ODBERATELA#', key: 'invoicing.email_ph_client' },
  { token: '#NAZOV#', key: 'invoicing.email_ph_doc_name' },
  { token: '#CISLO#', key: 'invoicing.email_ph_number' },
  { token: '#CISLO_ZAL#', key: 'invoicing.email_ph_proforma_number' },
  { token: '#OBJEDNAVKA#', key: 'invoicing.email_ph_order' },
  { token: '#DODANIE#', key: 'invoicing.email_ph_delivery' },
  { token: '#PLATI_DO#', key: 'invoicing.email_ph_valid_until' },
  { token: '#POZNAMKA_NAD#', key: 'invoicing.email_ph_note_above' },
  { token: '#SUMA#', key: 'invoicing.email_ph_total' },
  { token: '#UHRADENA_SUMA#', key: 'invoicing.email_ph_paid' },
  { token: '#POSLEDNA_UHRADA#', key: 'invoicing.email_ph_last_payment' },
  { token: '#SPLATNOST#', key: 'invoicing.email_ph_due' },
  { token: '#FORMA_UHRADY#', key: 'invoicing.email_ph_payment_method' },
  { token: '#IBAN#', key: 'invoicing.email_ph_iban' },
  { token: '#UCET#', key: 'invoicing.email_ph_account' },
  { token: '#VAR#', key: 'invoicing.email_ph_vs' },
  { token: '#KONSTANTNY#', key: 'invoicing.email_ph_cs' },
  { token: '#SPECIFICKY#', key: 'invoicing.email_ph_ss' },
  { token: '#ONLINE_PLATBA#', key: 'invoicing.email_ph_online_pay' },
  { token: '#QR#', key: 'invoicing.email_ph_qr' },
] as const;

export function defaultEmailSettings(): CompanyEmailSettingsState {
  return {
    delivery_method: 'system',
    smtp: {
      username: '',
      password: '',
      host: '',
      port: null,
      from_name: '',
      encryption: 'tls',
      use_smtp_email_as_from: true,
    },
    templates: {},
  };
}

export function emailSettingsFromCompany(company: Record<string, unknown> | null): CompanyEmailSettingsState {
  const raw = (company?.email_settings ?? {}) as Partial<CompanyEmailSettingsState>;
  const base = defaultEmailSettings();
  const smtp = { ...base.smtp, ...(raw.smtp ?? {}) };
  smtp.password = '';
  const templates: Record<string, EmailTemplate> = { ...base.templates };
  if (raw.templates) {
    for (const [k, v] of Object.entries(raw.templates)) {
      templates[k] = {
        subject: (v as EmailTemplate)?.subject ?? '',
        body: (v as EmailTemplate)?.body ?? '',
      };
    }
  }
  return {
    delivery_method: raw.delivery_method ?? base.delivery_method,
    smtp,
    templates,
  };
}
