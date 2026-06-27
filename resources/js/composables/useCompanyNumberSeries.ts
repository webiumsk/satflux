export type NumberSeriesRow = {
  id: number | string;
  company_id: string;
  name: string;
  document_type: string;
  format: string;
  reset_period: 'yearly' | 'monthly' | 'never';
  is_default: boolean;
  period_key: string | null;
  last_number: number;
  next_number_preview: string;
};

export type NumberSeriesFormState = {
  name: string;
  document_type: string;
  format: string;
  reset_period: 'yearly' | 'monthly' | 'never';
  is_default: boolean;
  last_number: number;
};

export const DOCUMENT_TYPE_OPTIONS = [
  { value: 'invoice', labelKey: 'invoicing.series_type_invoice' },
  { value: 'credit_note', labelKey: 'invoicing.series_type_credit_note' },
  { value: 'proforma', labelKey: 'invoicing.series_type_proforma' },
  { value: 'delivery_note', labelKey: 'invoicing.series_type_delivery_note' },
  { value: 'quote', labelKey: 'invoicing.series_type_quote' },
  { value: 'order_received', labelKey: 'invoicing.series_type_order_received' },
  { value: 'order_issued', labelKey: 'invoicing.series_type_order_issued' },
] as const;

export const FORMAT_PRESETS = [
  'INVYYYYNNNN',
  'CNYYYYNNNN',
  'PFYYYYNNNN',
  'DELYYYYNNNN',
  'QTYYYYNNNN',
  'POYYYYNNNN',
  'SOYYYYNNNN',
  'EXPYYYYNNNN',
  'YYYYNNNN',
] as const;

export function emptySeriesForm(): NumberSeriesFormState {
  return {
    name: '',
    document_type: 'invoice',
    format: 'INVYYYYNNNN',
    reset_period: 'yearly',
    is_default: false,
    last_number: 0,
  };
}

export function seriesToForm(row: NumberSeriesRow): NumberSeriesFormState {
  return {
    name: row.name,
    document_type: row.document_type,
    format: row.format,
    reset_period: row.reset_period,
    is_default: row.is_default,
    last_number: row.last_number,
  };
}
