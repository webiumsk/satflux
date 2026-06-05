export const EXPENSE_IMPORT_FIELD_KEYS = [
  'title',
  'category',
  'supplier_name',
  'external_number',
  'internal_number',
  'issue_date',
  'due_date',
  'delivery_date',
  'total',
  'currency',
  'paid_at',
  'variable_symbol',
  'constant_symbol',
  'specific_symbol',
  'tags',
  'internal_note',
  'supplier_registration_number',
  'supplier_tax_id',
  'supplier_email',
  'supplier_street',
  'supplier_city',
  'supplier_country',
] as const;

export type ExpenseImportFieldKey = (typeof EXPENSE_IMPORT_FIELD_KEYS)[number];

export const REQUIRED_EXPENSE_IMPORT_FIELDS: ExpenseImportFieldKey[] = ['issue_date', 'total'];

export type ExpenseImportMapping = Partial<Record<ExpenseImportFieldKey, number | null>>;
