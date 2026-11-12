import { describe, expect, it } from 'vitest';
import { formatPeppolEndpoint, resolvePeppolEndpoint } from '../utils/peppolEndpoint';

// Parity table with the PHP source of truth (SkUblProfile::resolveEndpoint,
// covered by SkUblProfileTest + CompanyEfakturaSettingsTest) - the ordering
// must stay identical: explicit ID > SK DIC (0245) > SK ICO (0158).
describe('resolvePeppolEndpoint', () => {
  it('derives 0245 from the SK company DIC', () => {
    expect(
      resolvePeppolEndpoint({
        tax_id: '2023980035',
        registration_number: '47615681',
        country: 'SK',
      }),
    ).toEqual({ scheme: '0245', id: '2023980035' });
  });

  it('falls back to 0158 from the Slovak ICO without a DIC', () => {
    expect(
      resolvePeppolEndpoint({
        tax_id: null,
        registration_number: '47615681',
        jurisdiction: 'eu_sk',
      }),
    ).toEqual({ scheme: '0158', id: '47615681' });
  });

  it('uses the jurisdiction as the company country fallback', () => {
    expect(
      resolvePeppolEndpoint({ tax_id: '2023980035', jurisdiction: 'eu_sk' }),
    ).toEqual({ scheme: '0245', id: '2023980035' });
  });

  it('parses an explicit participant ID with its scheme', () => {
    expect(
      resolvePeppolEndpoint({
        peppol_participant_id: '0245:1234567890',
        tax_id: '9999999999',
      }),
    ).toEqual({ scheme: '0245', id: '1234567890' });
  });

  it('treats a digits-only explicit ID as a DIC', () => {
    expect(resolvePeppolEndpoint({ peppol_participant_id: 'SK1234567890' })).toEqual({
      scheme: '0245',
      id: '1234567890',
    });
  });

  it('non-SK entities resolve to 0208 from the registration number', () => {
    expect(
      resolvePeppolEndpoint({
        tax_id: '111',
        registration_number: '222',
        country: 'CZ',
      }),
    ).toEqual({ scheme: '0208', id: '222' });
  });

  it('returns null without any registration data', () => {
    expect(resolvePeppolEndpoint({ tax_id: null, registration_number: '', country: 'SK' })).toBeNull();
    expect(resolvePeppolEndpoint(null)).toBeNull();
  });

  it('formats an endpoint as scheme:id', () => {
    expect(formatPeppolEndpoint({ scheme: '0245', id: '2023980035' })).toBe('0245:2023980035');
    expect(formatPeppolEndpoint(null)).toBeNull();
  });

  it.each(['9950:sk1234567890', '9950:SK1234567890', '0088:abc-123_456.789'])('preserves explicit opaque identifier %s', (value) => {
    expect(formatPeppolEndpoint(resolvePeppolEndpoint({ peppol_participant_id: value, country: 'SK' }))).toBe(value);
  });

  it.each(['bad:12345', '245:12345', '0245:', '9950:sk12:34', '9950:sk12 34', '9950:sk12\t34', '9950:sk12/34', `9950:${'a'.repeat(129)}`])('rejects malformed explicit address %s instead of deriving another address', (value) => {
    expect(resolvePeppolEndpoint({ peppol_participant_id: value, tax_id: '9999999999', country: 'SK' })).toBeNull();
  });
});
