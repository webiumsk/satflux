import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { ref } from 'vue';

const mocks = vi.hoisted(() => ({ health: vi.fn(), list: vi.fn(), bridge: vi.fn(), loadQuery: vi.fn(), companyRows: [] as Record<string, unknown>[] }));
vi.mock('@/services/api', () => ({ invoicingApi: { efaktura: { inboundHealth: (...args: unknown[]) => mocks.health(...args) } } }));
vi.mock('@/evolu/client', () => ({
  evolu: { loadQuery: (...args: unknown[]) => mocks.loadQuery(...args) },
  allCompaniesQuery: 'companies',
  allCompaniesDetailQuery: 'company-details',
}));
vi.mock('@/evolu/flags', () => ({ isInvoicingLocalFirst: () => true }));
vi.mock('@/evolu/bridgeCompanyEnsure', () => ({ ensureBridgeCompanyIdForLocalCompany: (...args: unknown[]) => mocks.bridge(...args) }));
vi.mock('@/evolu/efakturaInboxImport', () => ({ fetchEfakturaInbox: (...args: unknown[]) => mocks.list(...args),
  reconcileEfakturaInboxWithLocalExpenses: async (_evolu: unknown, _company: string, _bridge: string, rows: unknown[]) => rows }));

beforeEach(() => {
  vi.resetModules(); vi.useFakeTimers();
  mocks.companyRows = ['company-a', 'company-b'].map((id) => ({ id, appSettingsJson: JSON.stringify({ efaktura_enabled: true, efaktura_inbound_enabled: true }) }));
  mocks.loadQuery.mockReset().mockImplementation(async (query: string) => query === 'company-details'
    ? mocks.companyRows
    : mocks.companyRows.map(({ id }) => ({ id, legalName: 'Synthetic company' })));
  mocks.health.mockReset().mockResolvedValue({ state: 'ok', last_poll_at: '2026-11-10T10:00:00Z', failed: 0 });
  mocks.list.mockReset().mockResolvedValue([]);
  mocks.bridge.mockReset().mockImplementation(async (id: string) => ({ ok: true, bridgeCompanyId: `bridge-${id}` }));
});
afterEach(() => { vi.clearAllTimers(); vi.useRealTimers(); });

it('reads inbound settings through the detailed company projection before fetching the inbox', async () => {
  const live = await import('@/evolu/efakturaInboxLive');
  live.initEfakturaInboxLive(ref('company-a'));

  await vi.waitFor(() => expect(mocks.list).toHaveBeenCalledWith('bridge-company-a'));
  expect(live.useEfakturaInboxLive().receivingEnabled.value).toBe(true);
  expect(live.useEfakturaInboxLive().bridgeCompanyId.value).toBe('bridge-company-a');
  expect(mocks.loadQuery).toHaveBeenCalledWith('company-details');
});

it('keeps failed receiving health visible despite a successful empty inbox fetch', async () => {
  mocks.health.mockResolvedValue({ state: 'failed', last_poll_at: '2026-11-10T10:00:00Z', failed: 1 });
  const live = await import('@/evolu/efakturaInboxLive');
  const company = ref('company-a'); live.initEfakturaInboxLive(company);
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().health.value?.state).toBe('failed'));
  expect(live.useEfakturaInboxLive().entries.value).toEqual([]);
  expect(live.useEfakturaInboxLive().receivingEnabled.value).toBe(true);
  expect(mocks.health).toHaveBeenCalledWith('bridge-company-a');
});

it('surfaces background request errors rather than silently claiming an empty healthy inbox', async () => {
  mocks.list.mockRejectedValue(new Error('synthetic_network_failure'));
  const live = await import('@/evolu/efakturaInboxLive');
  live.initEfakturaInboxLive(ref('company-a'));
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().health.value?.state).toBe('unavailable'));
  expect(live.useEfakturaInboxLive().entries.value).toEqual([]);
});

it('discards health responses from the previous company', async () => {
  let resolve!: (value: unknown) => void;
  mocks.health.mockImplementationOnce(() => new Promise((done) => { resolve = done; }));
  const live = await import('@/evolu/efakturaInboxLive');
  const company = ref('company-a'); live.initEfakturaInboxLive(company);
  await vi.waitFor(() => expect(mocks.health).toHaveBeenCalledTimes(1));
  company.value = 'company-b';
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().bridgeCompanyId.value).toBe('bridge-company-b'));
  resolve({ state: 'failed', last_poll_at: '2026-11-01T00:00:00Z', failed: 8 });
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok'));
  expect(live.useEfakturaInboxLive().health.value).toMatchObject({ state: 'ok', failed: 0 });
  expect(live.useEfakturaInboxLive().health.value?.last_poll_at).toBe('2026-11-10T10:00:00Z');
  expect(live.useEfakturaInboxLive().bridgeCompanyId.value).toBe('bridge-company-b');
});

it('rejects unknown status shapes instead of considering them healthy', async () => {
  const live = await import('@/evolu/efakturaInboxLive');
  expect(() => live.parseInboundHealth({ state: 'approved' })).toThrow('invalid_inbound_health');
  expect(() => live.parseInboundHealth(null)).toThrow('invalid_inbound_health');
});
