/** Public legal identity of the satflux.io operator (also used in legal document copy). */
export const LEGAL_OPERATOR = {
  name: 'Webium LLC',
  addressLine1: '30 N Gould St Ste N',
  cityStateZip: 'Sheridan, WY 82801',
  jurisdiction: 'Wyoming, United States',
  ein: '61-2348057',
  serviceName: 'satflux.io',
  contactEmail: 'hello@satflux.io',
  privacyEmail: 'privacy@satflux.io',
  supportEmail: 'support@satflux.io',
} as const;

export function operatorAddressBlock(): string {
  return `${LEGAL_OPERATOR.name}\n${LEGAL_OPERATOR.addressLine1}\n${LEGAL_OPERATOR.cityStateZip}`;
}
