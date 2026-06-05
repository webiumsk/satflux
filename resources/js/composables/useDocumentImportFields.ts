/** Internal field keys for historical invoice import (labels via i18n). */

export const DOCUMENT_IMPORT_FIELD_KEYS = [
  'invoice_number',
  'variable_symbol',
  'constant_symbol',
  'specific_symbol',
  'issue_date',
  'delivery_date',
  'due_date',
  'client_registration_number',
  'client_tax_id',
  'client_vat_id',
  'client_name',
  'client_street',
  'client_city',
  'client_postal_code',
  'client_country',
  'client_phone',
  'client_email',
  'amount',
  'currency',
  'paid_at',
  'payment_method',
] as const;

export type DocumentImportFieldKey = (typeof DOCUMENT_IMPORT_FIELD_KEYS)[number];

export const REQUIRED_DOCUMENT_IMPORT_FIELDS: DocumentImportFieldKey[] = [
  'invoice_number',
  'issue_date',
  'due_date',
  'client_name',
  'amount',
];

export type DocumentImportSource = 'excel' | 'money' | 'pohoda' | 'isdoc' | 'idoklad';

export const DOCUMENT_IMPORT_SOURCES: {
  id: DocumentImportSource;
  enabled: boolean;
  labelKey: string;
}[] = [
  { id: 'excel', enabled: true, labelKey: 'invoicing.import_source_excel' },
  { id: 'money', enabled: false, labelKey: 'invoicing.import_source_money' },
  { id: 'pohoda', enabled: false, labelKey: 'invoicing.import_source_pohoda' },
  { id: 'isdoc', enabled: false, labelKey: 'invoicing.import_source_isdoc' },
  { id: 'idoklad', enabled: false, labelKey: 'invoicing.import_source_idoklad' },
];

export type DocumentImportMapping = Partial<Record<DocumentImportFieldKey, number | null>>;
