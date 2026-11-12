import { beforeEach, describe, expect, it, vi } from 'vitest';

const state = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
vi.mock('@/services/api', () => ({ default: state, invoicingApi: { companies: { list: vi.fn() } } }));
vi.mock('@/evolu/client', () => ({ allDocumentSnapshotsQuery: 'synthetic-query' }));
import { fetchEphemeralEfakturaStatus, sendEphemeralEfaktura, refreshEphemeralEfakturaStatus,
  testEphemeralEmailSmtp, downloadEphemeralPdf, type EphemeralSnapshotPayload } from '../evolu/ephemeralBridge';
const snapshot: EphemeralSnapshotPayload = { company: { legal_name: 'Synthetic issuer' }, contact: null,
  document: { type: 'invoice', status: 'issued' }, lines: [] };
const accepted = { id: 'synthetic-submission', provider: 'peppol', status: 'submitted', external_id: 'synthetic-provider-id', submitted_at: null };
beforeEach(() => { state.get.mockReset(); state.post.mockReset(); });

describe('company-bound ephemeral outbound transport', () => {
  it('sends ePostman directly to its verified company with the explicit same bridge identity', async () => {
    state.post.mockResolvedValue({ data: { data: accepted } });
    await expect(sendEphemeralEfaktura(snapshot, 'local-document', 'bridge-company', { requireBridge: true })).resolves.toEqual(accepted);
    expect(state.post).toHaveBeenCalledExactlyOnceWith('/invoicing/companies/bridge-company/documents/ephemeral/efaktura/send',
      { ...snapshot, evolu_document_id: 'local-document', bridge_company_id: 'bridge-company' });
  });

  it.each([404, 405, 500])('does not resend to an alternate route after ePostman HTTP %s', async (status) => {
    const error = { response: { status } }; state.post.mockRejectedValue(error);
    await expect(sendEphemeralEfaktura(snapshot, 'local-document', 'bridge-company', { requireBridge: true })).rejects.toBe(error);
    expect(state.post).toHaveBeenCalledTimes(1);
  });

  it('does not resend after an ambiguous ePostman connection failure', async () => {
    const error = new Error('synthetic timeout'); state.post.mockRejectedValue(error);
    await expect(sendEphemeralEfaktura(snapshot, 'local-document', 'bridge-company', { requireBridge: true })).rejects.toBe(error);
    expect(state.post).toHaveBeenCalledTimes(1);
  });

  it('rejects a missing required bridge before HTTP', async () => {
    await expect(sendEphemeralEfaktura(snapshot, 'local-document', null, { requireBridge: true })).rejects.toThrow('Missing company bridge');
    expect(state.post).not.toHaveBeenCalled();
  });

  it('preserves the established custom-provider fallback and carries the captured company identity', async () => {
    state.post.mockRejectedValueOnce({ response: { status: 404 } }).mockResolvedValueOnce({ data: { data: accepted } });
    await expect(sendEphemeralEfaktura(snapshot, 'local-document', 'bridge-company')).resolves.toEqual(accepted);
    expect(state.post.mock.calls.map((call) => call[0])).toEqual([
      '/invoicing/ephemeral/efaktura/send', '/invoicing/companies/bridge-company/documents/ephemeral/efaktura/send',
    ]);
    expect(state.post.mock.calls.every((call) => call[1].bridge_company_id === 'bridge-company')).toBe(true);
  });

  it('filters history by the same bridge company', async () => {
    state.get.mockResolvedValue({ data: { data: [accepted] } });
    await expect(fetchEphemeralEfakturaStatus('local-document', 'bridge-company')).resolves.toEqual([accepted]);
    expect(state.get).toHaveBeenCalledExactlyOnceWith('/invoicing/ephemeral/efaktura/status',
      { params: { evolu_document_id: 'local-document', bridge_company_id: 'bridge-company' } });
  });

  it.each([undefined, null, {}, { status: 'submitted' }, [null], [{}]])('rejects malformed company-bound history instead of treating it as empty (%j)', async (data) => {
    state.get.mockResolvedValue({ data: { data } });
    await expect(fetchEphemeralEfakturaStatus('local-document', 'bridge-company')).rejects.toThrow('Invalid e-invoice submission history');
  });

  it.each([undefined, null, {}, [], { status: 'submitted' }])('rejects malformed ePostman send responses without another POST (%j)', async (data) => {
    state.post.mockResolvedValue({ data: { data } });
    await expect(sendEphemeralEfaktura(snapshot, 'local-document', 'bridge-company', { requireBridge: true })).rejects.toThrow('Invalid e-invoice submission response');
    expect(state.post).toHaveBeenCalledTimes(1);
  });

  it('keeps malformed unbound legacy history behavior', async () => {
    state.get.mockResolvedValue({ data: {} });
    await expect(fetchEphemeralEfakturaStatus('local-document')).resolves.toEqual([]);
  });

  it('preserves unscoped history for legacy callers', async () => {
    state.get.mockResolvedValue({ data: { data: [] } });
    await fetchEphemeralEfakturaStatus('local-document');
    expect(state.get).toHaveBeenCalledExactlyOnceWith('/invoicing/ephemeral/efaktura/status',
      { params: { evolu_document_id: 'local-document' } });
  });

  it('passes the company binding through custom-provider refresh', async () => {
    state.post.mockResolvedValue({ data: { data: [accepted] } });
    await expect(refreshEphemeralEfakturaStatus('local-document', 'bridge-company')).resolves.toEqual([accepted]);
    expect(state.post).toHaveBeenCalledExactlyOnceWith('/invoicing/ephemeral/efaktura/refresh',
      { evolu_document_id: 'local-document', bridge_company_id: 'bridge-company' }, undefined);
  });
});


describe('Enterprise key document exclusion', () => {
  const withSecrets = () => ({ ...snapshot, company: { ...snapshot.company, app_settings: {
    efaktura_epostman_api_key: 'synthetic-enterprise-key',
    efaktura_epostman_api_key_encrypted: 'synthetic-enterprise-ciphertext',
    efaktura_epostman_status_generation: 'synthetic-internal-generation',
    show_pay_by_square: true,
  } } });
  it('strips Enterprise credentials from direct company-bound submission without modifying the input', async () => {
    const input = withSecrets(); state.post.mockResolvedValue({ data: { data: accepted } });
    await sendEphemeralEfaktura(input, 'local-document', 'bridge-company', { requireBridge: true });
    expect(JSON.stringify(state.post.mock.calls[0][1])).not.toContain('synthetic-enterprise-');
    expect(input.company.app_settings.efaktura_epostman_api_key).toBe('synthetic-enterprise-key');
  });
  it('strips raw company Enterprise credentials from SMTP tests', async () => {
    state.post.mockResolvedValue({ data: {} });
    await testEphemeralEmailSmtp(withSecrets().company, {}, 'synthetic@example.test', 'bridge-company');
    expect(state.post.mock.calls[0][1].company.app_settings).toEqual({ show_pay_by_square: true });
  });
  it('strips Enterprise credentials before PDF requests', async () => {
    state.post.mockRejectedValue(new Error('synthetic stop before browser download'));
    await expect(downloadEphemeralPdf(withSecrets(), 'synthetic.pdf')).rejects.toThrow('synthetic stop');
    expect(state.post.mock.calls[0][1].company.app_settings).toEqual({ show_pay_by_square: true });
  });
});
