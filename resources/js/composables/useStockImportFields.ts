export const STOCK_IMPORT_FIELD_KEYS = [
  'name',
  'sku',
  'sale_unit_price',
  'purchase_unit_price',
  'purchase_currency',
  'unit',
  'quantity_on_hand',
  'description',
  'import_document_ref',
  'internal_note',
] as const;

export type StockImportFieldKey = (typeof STOCK_IMPORT_FIELD_KEYS)[number];

export const REQUIRED_STOCK_IMPORT_FIELDS: StockImportFieldKey[] = ['name'];

export type StockImportMapping = Partial<Record<StockImportFieldKey, number | null>>;
