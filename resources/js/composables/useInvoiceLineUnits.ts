/** Preset unit values aligned with SuperFaktúra InvoiceUnitType. */
export const INVOICE_LINE_UNIT_CUSTOM = '__custom__';

export const INVOICE_LINE_UNIT_PRESETS: ReadonlyArray<{
  value: string;
  /** Shown in dropdown when no i18n labelKey. */
  label?: string;
  labelKey?: string;
}> = [
  { value: 'ks.', labelKey: 'unit_pcs' },
  { value: 'hod.', labelKey: 'unit_hours' },
  { value: 'm', label: 'm' },
  { value: 'km', label: 'km' },
  { value: 'bm', label: 'bm' },
  { value: 'm2', label: 'm2' },
  { value: 'm3', label: 'm3' },
  { value: 'kg', label: 'kg' },
  { value: 'mesiace', labelKey: 'unit_months' },
  { value: 'person', labelKey: 'unit_person' },
  { value: 'rok', labelKey: 'unit_year' },
];

const PRESET_SET = new Set(INVOICE_LINE_UNIT_PRESETS.map((p) => p.value));

export function isPresetInvoiceLineUnit(unit: string): boolean {
  return PRESET_SET.has(unit);
}

export const DEFAULT_INVOICE_LINE_UNIT = 'ks.';
