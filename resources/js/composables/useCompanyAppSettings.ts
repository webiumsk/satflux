export type CompanyAppSettingsState = {
  rounding_method: 'per_line' | 'per_document' | 'none';
  invoice_line_label: string;
  default_invoice_payment_terms_days: number;
  default_delivery_method: string;
  default_delivery_date_mode: 'empty' | 'issue_date' | 'due_date';
  default_payment_method: string;
  pdf_filename_pattern: string;
  expense_attachment_name_pattern: string;
  sort_lists_by: 'issue_date' | 'due_date' | 'number';
  number_documents_by: 'issue_date' | 'calendar_year';
  default_constant_symbol: string;
  tax_free_minimum: string;
  show_contextual_help: boolean;
  embed_isdoc_in_pdf: boolean;
  reverse_charge: boolean;
  reverse_charge_note: string;
  us_sales_tax_provider: 'manual' | 'stripe_tax' | 'avalara';
  stripe_tax_secret_key: string;
  show_pay_by_square: boolean;
  show_invoice_by_square: boolean;
  show_client_phone_on_invoices: boolean;
  show_payme_on_invoices: boolean;
  variable_symbol_from_proforma: boolean;
  show_prices_on_delivery_notes: boolean;
  show_prices_on_orders: boolean;
  show_line_suggester: boolean;
  show_summary_on_quotes: boolean;
  runs_eshop: boolean;
};

export const PDF_FILENAME_SHORTCUTS = [
  { token: '#NAZOV#', key: 'invoicing.pdf_token_name' },
  { token: '#TYP#', key: 'invoicing.pdf_token_type' },
  { token: '#FIRMA#', key: 'invoicing.pdf_token_company' },
  { token: '#CISLO#', key: 'invoicing.pdf_token_number' },
  { token: '#KLIENT#', key: 'invoicing.pdf_token_client' },
  { token: '#VYSTAVENE#', key: 'invoicing.pdf_token_issue_date' },
  { token: '#SUMA#', key: 'invoicing.pdf_token_total' },
  { token: '#MENA#', key: 'invoicing.pdf_token_currency' },
] as const;

export function defaultAppSettings(): CompanyAppSettingsState {
  return {
    rounding_method: 'per_line',
    invoice_line_label: '',
    default_invoice_payment_terms_days: 14,
    default_delivery_method: '',
    default_delivery_date_mode: 'empty',
    default_payment_method: '',
    pdf_filename_pattern: '#TYP#_#FIRMA#_#CISLO#',
    expense_attachment_name_pattern: '',
    sort_lists_by: 'issue_date',
    number_documents_by: 'issue_date',
    default_constant_symbol: '0308',
    tax_free_minimum: '0.00',
    show_contextual_help: true,
    embed_isdoc_in_pdf: true,
    reverse_charge: false,
    reverse_charge_note: '',
    us_sales_tax_provider: 'manual',
    stripe_tax_secret_key: '',
    show_pay_by_square: true,
    show_invoice_by_square: false,
    show_client_phone_on_invoices: false,
    show_payme_on_invoices: false,
    variable_symbol_from_proforma: false,
    show_prices_on_delivery_notes: false,
    show_prices_on_orders: true,
    show_line_suggester: true,
    show_summary_on_quotes: true,
    runs_eshop: false,
  };
}

export function appSettingsFromCompany(company: Record<string, unknown> | null): CompanyAppSettingsState {
  const raw = (company?.app_settings ?? {}) as Partial<CompanyAppSettingsState>;
  const base = defaultAppSettings();
  return {
    ...base,
    ...raw,
    invoice_line_label: raw.invoice_line_label ?? '',
    default_delivery_method: raw.default_delivery_method ?? '',
    default_payment_method: raw.default_payment_method ?? '',
    expense_attachment_name_pattern: raw.expense_attachment_name_pattern ?? '',
    default_constant_symbol: raw.default_constant_symbol ?? base.default_constant_symbol,
    tax_free_minimum: String(raw.tax_free_minimum ?? base.tax_free_minimum),
  };
}
