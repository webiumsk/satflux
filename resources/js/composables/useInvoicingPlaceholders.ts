/** Canonical English placeholder tokens (legacy Slovak tokens still resolve on the backend). */

export const RECURRING_PLACEHOLDER_TOKENS = [
  '#INVOICE_NUMBER#',
  '#VARIABLE_SYMBOL#',
  '#DAY#',
  '#WEEK#',
  '#MONTH#',
  '#MONTH_NAME#',
  '#PREVIOUS_MONTH#',
  '#YEAR#',
  '#NEXT_YEAR#',
] as const;

export const RECURRING_INVOICE_NUMBER_TOKEN = '#INVOICE_NUMBER#';
