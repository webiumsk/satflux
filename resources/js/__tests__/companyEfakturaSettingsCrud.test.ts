import { beforeEach, describe, expect, it, vi } from 'vitest';
import { updateLocalEfakturaSettings } from '@/evolu/companySettingsCrud';
import type { CompanyEfakturaSettingsState } from '@/composables/useCompanyEfakturaSettings';

vi.mock('@/evolu/client', () => ({ allCompaniesDetailQuery: 'company-details' }));
let saved: Record<string, unknown>;
const client = {
  getQueryRows: () => [{ id: 'company-a', appSettingsJson: JSON.stringify(saved) }],
  update: vi.fn((_table: string, patch: { appSettingsJson: string }) => {
    saved = JSON.parse(patch.appSettingsJson) as Record<string, unknown>;
    return { ok: true };
  }),
};
const update = (partial: Partial<CompanyEfakturaSettingsState>) => updateLocalEfakturaSettings(client as never, 'company-a' as never, partial);

beforeEach(() => {
  client.update.mockClear();
  saved = { efaktura_enabled: true, efaktura_sapi_base_url: 'https://postman.slovakodata.com',
    efaktura_sapi_client_id: 'synthetic-client', efaktura_sapi_client_secret: 'synthetic-old-secret',
    efaktura_sapi_client_secret_set: true, efaktura_sapi_client_secret_encrypted: 'synthetic-legacy-ciphertext' };
});

describe('local eFaktura credential retention', () => {
  it.each(['   ', '\t\n', '\u00a0'])('rejects whitespace-only replacements before writing: %j', (secret) => {
    const before = JSON.stringify(saved);
    expect(update({ efaktura_sapi_client_secret: secret })).toEqual({ ok: false });
    expect(client.update).not.toHaveBeenCalled();
    expect(JSON.stringify(saved)).toBe(before);
  });

  it('preserves meaningful replacement bytes and removes obsolete ciphertext on an unchanged connection', () => {
    const replacement = '  synthetic-new-secret  ';
    expect(update({ efaktura_sapi_client_secret: replacement })).toEqual({ ok: true });
    expect(saved.efaktura_sapi_client_secret).toBe(replacement);
    expect(saved.efaktura_sapi_client_secret_set).toBe(true);
    expect(saved).not.toHaveProperty('efaktura_sapi_client_secret_encrypted');
  });
  it.each([
    { efaktura_sapi_base_url: 'https://testpostman.slovakodata.com' },
    { efaktura_sapi_client_id: 'synthetic-replacement-client' },
  ])('clears persisted secrets and saved-secret flag for a disabled changed connection: %j', (changed) => {
    expect(update({ ...changed, efaktura_enabled: false })).toEqual({ ok: true });
    expect(saved).not.toHaveProperty('efaktura_sapi_client_secret');
    expect(saved).not.toHaveProperty('efaktura_sapi_client_secret_encrypted');
    expect(saved.efaktura_sapi_client_secret_set).toBe(false);
    // A second save/remount-style read cannot restore the old secret.
    update({ efaktura_enabled: true });
    expect(saved).not.toHaveProperty('efaktura_sapi_client_secret');
    expect(saved.efaktura_sapi_client_secret_set).toBe(false);
  });

  it('retains an unchanged secret when the same normalized connection is saved with a blank secret', () => {
    update({ efaktura_sapi_base_url: 'https://POSTMAN.SLOVAKODATA.COM:443/', efaktura_sapi_client_secret: '' });
    expect(saved.efaktura_sapi_client_secret).toBe('synthetic-old-secret');
    expect(saved.efaktura_sapi_client_secret_set).toBe(true);
    expect(saved.efaktura_sapi_client_secret_encrypted).toBe('synthetic-legacy-ciphertext');
  });

  it('stores only the supplied replacement secret for a changed connection', () => {
    update({ efaktura_sapi_client_id: 'synthetic-new-client', efaktura_sapi_client_secret: 'synthetic-new-secret' });
    expect(saved.efaktura_sapi_client_secret).toBe('synthetic-new-secret');
    expect(saved.efaktura_sapi_client_secret_set).toBe(true);
    expect(saved).not.toHaveProperty('efaktura_sapi_client_secret_encrypted');
  });
});
