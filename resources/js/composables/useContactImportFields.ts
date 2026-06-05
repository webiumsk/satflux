export const CONTACT_IMPORT_FIELD_KEYS = [
  'name',
  'street',
  'postal_code',
  'city',
  'country',
  'registration_number',
  'tax_id',
  'vat_id',
  'email',
  'phone',
  'fax',
  'delivery_name',
  'delivery_street',
  'delivery_postal_code',
  'delivery_city',
  'delivery_country',
  'web',
  'notes',
  'default_payment_terms_days',
  'iban',
  'swift',
] as const;

export type ContactImportFieldKey = (typeof CONTACT_IMPORT_FIELD_KEYS)[number];

export const REQUIRED_CONTACT_IMPORT_FIELDS: ContactImportFieldKey[] = ['name'];

export type ContactImportMapping = Partial<Record<ContactImportFieldKey, number | null>>;
