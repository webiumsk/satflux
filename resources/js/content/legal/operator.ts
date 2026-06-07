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

/** GDPR Art. 27 EU/UK representatives - replace placeholders before production use. */
export const LEGAL_GDPR_REPRESENTATIVES = {
  eu: {
    name: '[Name of provider]',
    address: '[full address in an EU member state]',
    email: '[email]',
  },
  uk: {
    name: '[Name of provider]',
    address: '[full UK address]',
    email: '[email]',
  },
} as const;

export function operatorAddressBlock(): string {
  return `${LEGAL_OPERATOR.name}\n${LEGAL_OPERATOR.addressLine1}\n${LEGAL_OPERATOR.cityStateZip}`;
}

export function gdprRepresentativeBlockEu(): string {
  const { name, address, email } = LEGAL_GDPR_REPRESENTATIVES.eu;
  return `EU representative: ${name}, ${address}. Contact: ${email}.`;
}

export function gdprRepresentativeBlockUk(): string {
  const { name, address, email } = LEGAL_GDPR_REPRESENTATIVES.uk;
  return `UK representative: ${name}, ${address}. Contact: ${email}.`;
}
