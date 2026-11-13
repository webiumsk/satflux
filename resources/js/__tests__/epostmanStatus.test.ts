import { describe, expect, it } from 'vitest';
import { enterpriseStatusFromRaw, epostmanKeyIsSet, isValidEpostmanReplacementKey } from '../utils/epostmanStatus';

describe('ePostman finite observation and credential parsing', () => {
  it.each([' ', ' key', 'key ', 'key value', 'key\n', 'á', 'x'.repeat(4097)])('rejects invalid key replacement bytes (%j)', (key) => {
    expect(isValidEpostmanReplacementKey(key)).toBe(false);
  });
  it.each([undefined, null, '', 'synthetic-key!'])('accepts omission, removal, blank keep and valid keys (%j)', (key) => {
    expect(isValidEpostmanReplacementKey(key)).toBe(true);
  });
  it('never infers saved status from secret bytes and suppresses it for an unfinished replacement', () => {
    expect(epostmanKeyIsSet({ app_settings: { efaktura_epostman_api_key: 'synthetic-key' } })).toBe(false);
    expect(epostmanKeyIsSet({ app_settings: { efaktura_epostman_api_key_set: true, efaktura_epostman_key_save_pending: true } })).toBe(false);
  });
  it.each(['VALIDATING', 'VALIDATED', 'DELIVERED_NON_PEPPOL', 'UNCONFIRMED', 'TIMEOUT', 'OTHER', 'AP', 'CA', 'SUCCESS', 'PROCESSED'])('retains supported finite tokens (%s)', (token) => {
    const observation = enterpriseStatusFromRaw({ status: token, mls_c3_status: token });
    expect(observation?.status).toBe(token); expect(observation?.c3).toBe(token);
  });
  it('rejects raw strings as date/status/error labels', () => {
    expect(enterpriseStatusFromRaw({ status: '<provider>', checked_at: 'invalid', error_code: '<error>' })).toMatchObject({
      status: 'OTHER', checkedAt: null, error: 'check_failed',
    });
  });
});
