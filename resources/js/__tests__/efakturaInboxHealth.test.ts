import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { ref } from 'vue';

const mocks = vi.hoisted(() => ({ health: vi.fn(), list: vi.fn(), poll: vi.fn(), bridge: vi.fn(), loadQuery: vi.fn(), companyRows: [] as Record<string, unknown>[] }));
vi.mock('@/services/api', () => ({ invoicingApi: { efaktura: { inboundHealth: (...args: unknown[]) => mocks.health(...args), pollInbound: (...args: unknown[]) => mocks.poll(...args) } } }));
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
  mocks.poll.mockReset().mockResolvedValue({ imported: 0, failed: 0 });
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

it.each([new Error('synthetic health failure'), undefined])('retains successful inbox documents when health fails: %s', async (reason) => {
  const document = { inbox_id: 'synthetic-document' };
  mocks.list.mockResolvedValue([document]);
  mocks.health.mockRejectedValue(reason);
  const live = await import('@/evolu/efakturaInboxLive');
  live.initEfakturaInboxLive(ref('company-a'));
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().entries.value).toEqual([document]));
  expect(live.useEfakturaInboxLive().health.value?.state).toBe('unavailable');
  await expect(live.refreshEfakturaInboxLive(true, { throwOnError: true })).rejects.toBeDefined();
  expect(live.useEfakturaInboxLive().entries.value).toEqual([document]);
});

it('keeps fetched documents visible when the health response is malformed', async () => {
  const document = { inbox_id: 'synthetic-malformed-health-document' };
  mocks.list.mockResolvedValue([document]);
  mocks.health.mockResolvedValue({ state: 'unexpected' });
  const live = await import('@/evolu/efakturaInboxLive');
  live.initEfakturaInboxLive(ref('company-a'));
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().entries.value).toEqual([document]));
  expect(live.useEfakturaInboxLive().health.value?.state).toBe('unavailable');
});

it('publishes documents before slow health completes and ignores an obsolete health failure after a newer refresh', async () => {
  let rejectOld!: (error: unknown) => void;
  mocks.health.mockImplementationOnce(() => new Promise((_resolve, reject) => { rejectOld = reject; }));
  const document = { inbox_id: 'synthetic-pending-health-document' };
  mocks.list.mockResolvedValue([document]);
  const live = await import('@/evolu/efakturaInboxLive');
  live.initEfakturaInboxLive(ref('company-a'));
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().entries.value).toEqual([document]));
  await live.refreshEfakturaInboxLive(true, { throwOnError: true });
  expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok');
  rejectOld(new Error('obsolete health failure'));
  await vi.waitFor(() => expect(mocks.health).toHaveBeenCalledTimes(2));
  await Promise.resolve(); await Promise.resolve();
  expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok');
  expect(live.useEfakturaInboxLive().entries.value).toEqual([document]);
});

it('polls the active derived bridge then refreshes independent pending documents and health', async () => {
  const live = await import('@/evolu/efakturaInboxLive');
  live.initEfakturaInboxLive(ref('company-a'));
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok'));
  const document = { inbox_id: 'new-provider-document' };
  mocks.poll.mockImplementationOnce(async () => { mocks.list.mockResolvedValue([document]); });
  mocks.list.mockClear(); mocks.health.mockClear();
  expect(await live.checkEfakturaInboxMailbox('company-a')).toBe(true);
  expect(mocks.poll).toHaveBeenCalledExactlyOnceWith('bridge-company-a');
  expect(mocks.list).toHaveBeenCalledExactlyOnceWith('bridge-company-a');
  expect(mocks.health).toHaveBeenCalledExactlyOnceWith('bridge-company-a');
  expect(live.useEfakturaInboxLive().entries.value).toEqual([document]);
  expect(live.useEfakturaInboxLive().checkingMailbox.value).toBe(false);
  await live.refreshEfakturaInboxLive(true);
  expect(mocks.poll).toHaveBeenCalledTimes(1);
});

it('rejects a foreign local company and an unresolved bridge without provider calls', async () => {
  let finishBridge!: (value: unknown) => void;
  mocks.bridge.mockImplementationOnce(() => new Promise((resolve) => { finishBridge = resolve; }));
  const live = await import('@/evolu/efakturaInboxLive');
  live.initEfakturaInboxLive(ref('company-a'));
  await vi.waitFor(() => expect(mocks.bridge).toHaveBeenCalledTimes(1));
  expect(await live.checkEfakturaInboxMailbox('company-a')).toBe(false);
  finishBridge({ ok: true, bridgeCompanyId: 'bridge-company-a' });
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok'));
  expect(await live.checkEfakturaInboxMailbox('company-b')).toBe(false);
  expect(mocks.poll).not.toHaveBeenCalled();
});

it('guards duplicate checks and background refreshes throughout provider polling and its health refresh', async () => {
  let finishPoll!: () => void;
  const live = await import('@/evolu/efakturaInboxLive');
  live.initEfakturaInboxLive(ref('company-a'));
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok'));
  mocks.poll.mockImplementationOnce(() => new Promise<void>((resolve) => { finishPoll = resolve; }));
  mocks.list.mockClear(); mocks.health.mockClear();
  const checking = live.checkEfakturaInboxMailbox('company-a');
  await vi.waitFor(() => expect(mocks.poll).toHaveBeenCalledTimes(1));
  expect(live.useEfakturaInboxLive().checkingMailbox.value).toBe(true);
  expect(await live.checkEfakturaInboxMailbox('company-a')).toBe(false);
  await live.refreshEfakturaInboxLive(true);
  expect(mocks.list).not.toHaveBeenCalled();
  let finishHealth!: (value: unknown) => void;
  mocks.health.mockImplementationOnce(() => new Promise((resolve) => { finishHealth = resolve; }));
  finishPoll();
  await vi.waitFor(() => expect(mocks.list).toHaveBeenCalledTimes(1));
  expect(await live.checkEfakturaInboxMailbox('company-a')).toBe(false);
  await live.refreshEfakturaInboxLive(true);
  expect(mocks.list).toHaveBeenCalledTimes(1);
  finishHealth({ state: 'ok', last_poll_at: '2026-11-15T10:00:00Z', failed: 0 });
  expect(await checking).toBe(true);
  expect(live.useEfakturaInboxLive().checkingMailbox.value).toBe(false);
});

it('waits for admitted list reconciliation and does not poll after the company changes while waiting', async () => {
  const live = await import('@/evolu/efakturaInboxLive');
  const company = ref('company-a'); live.initEfakturaInboxLive(company);
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok'));
  let finishList!: (rows: unknown[]) => void;
  mocks.list.mockImplementationOnce(() => new Promise((resolve) => { finishList = resolve; }));
  const refresh = live.refreshEfakturaInboxLive(true);
  await vi.waitFor(() => expect(mocks.list).toHaveBeenCalledTimes(2));
  const checking = live.checkEfakturaInboxMailbox('company-a');
  await Promise.resolve();
  expect(mocks.poll).not.toHaveBeenCalled();
  company.value = 'company-b';
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().bridgeCompanyId.value).toBe('bridge-company-b'));
  finishList([]); await refresh;
  expect(await checking).toBe(false);
  expect(mocks.poll).not.toHaveBeenCalled();
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok'));
});

it('waits for a current inbox refresh before polling the provider', async () => {
  const live = await import('@/evolu/efakturaInboxLive');
  live.initEfakturaInboxLive(ref('company-a'));
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok'));
  let finishList!: (rows: unknown[]) => void;
  mocks.list.mockImplementationOnce(() => new Promise((resolve) => { finishList = resolve; }));
  const refresh = live.refreshEfakturaInboxLive(true);
  await vi.waitFor(() => expect(mocks.list).toHaveBeenCalledTimes(2));
  const checking = live.checkEfakturaInboxMailbox('company-a');
  await Promise.resolve();
  expect(mocks.poll).not.toHaveBeenCalled();
  finishList([]); await refresh;
  expect(await checking).toBe(true);
  expect(mocks.poll).toHaveBeenCalledExactlyOnceWith('bridge-company-a');
  expect(mocks.list).toHaveBeenCalledTimes(3);
});

it.each(['success', 'failure'])('does not refresh a previous company after an obsolete provider %s', async (result) => {
  const live = await import('@/evolu/efakturaInboxLive');
  const company = ref('company-a'); live.initEfakturaInboxLive(company);
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok'));
  let finish!: () => void;
  let fail!: (error: Error) => void;
  mocks.poll.mockImplementationOnce(() => new Promise<void>((resolve, reject) => { finish = resolve; fail = reject; }));
  const checking = live.checkEfakturaInboxMailbox('company-a');
  await vi.waitFor(() => expect(mocks.poll).toHaveBeenCalledTimes(1));
  company.value = 'company-b';
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().bridgeCompanyId.value).toBe('bridge-company-b'));
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok'));
  mocks.list.mockClear(); mocks.health.mockClear();
  if (result === 'success') finish(); else fail(new Error('Obsolete provider failure'));
  expect(await checking).toBe(false);
  expect(mocks.list).not.toHaveBeenCalled(); expect(mocks.health).not.toHaveBeenCalled();
  expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok');
  expect(live.useEfakturaInboxLive().checkingMailbox.value).toBe(false);
});

it('retires a check when the bridge changes for the same local company', async () => {
  const live = await import('@/evolu/efakturaInboxLive');
  live.initEfakturaInboxLive(ref('company-a'));
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok'));
  let finish!: () => void;
  mocks.poll.mockImplementationOnce(() => new Promise<void>((resolve) => { finish = resolve; }));
  const checking = live.checkEfakturaInboxMailbox('company-a');
  await vi.waitFor(() => expect(mocks.poll).toHaveBeenCalledTimes(1));
  mocks.bridge.mockResolvedValueOnce({ ok: true, bridgeCompanyId: 'replacement-bridge-a' });
  await live.reloadEfakturaInboxLive();
  mocks.list.mockClear(); mocks.health.mockClear();
  finish(); expect(await checking).toBe(false);
  expect(mocks.list).not.toHaveBeenCalled(); expect(mocks.health).not.toHaveBeenCalled();
  expect(live.useEfakturaInboxLive().bridgeCompanyId.value).toBe('replacement-bridge-a');
});

it('keeps partial provider documents after a failed check and permits a retry', async () => {
  const live = await import('@/evolu/efakturaInboxLive');
  live.initEfakturaInboxLive(ref('company-a'));
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok'));
  const document = { inbox_id: 'retained-partial-provider-document' };
  mocks.poll.mockRejectedValueOnce(new Error('Synthetic provider failure'));
  mocks.list.mockResolvedValue([document]);
  mocks.health.mockResolvedValue({ state: 'failed', last_poll_at: '2026-11-15T10:00:00Z', failed: 1 });
  await expect(live.checkEfakturaInboxMailbox('company-a')).rejects.toThrow('Synthetic provider failure');
  expect(live.useEfakturaInboxLive().entries.value).toEqual([document]);
  expect(live.useEfakturaInboxLive().health.value?.state).toBe('failed');
  expect(live.useEfakturaInboxLive().checkingMailbox.value).toBe(false);
  mocks.health.mockResolvedValue({ state: 'ok', last_poll_at: '2026-11-15T10:01:00Z', failed: 0 });
  expect(await live.checkEfakturaInboxMailbox('company-a')).toBe(true);
  expect(mocks.poll).toHaveBeenCalledTimes(2);
});

it('retains fetched documents and allows retry when post-poll health fails', async () => {
  const live = await import('@/evolu/efakturaInboxLive');
  live.initEfakturaInboxLive(ref('company-a'));
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok'));
  const document = { inbox_id: 'retained-post-poll-document' };
  mocks.list.mockResolvedValue([document]);
  mocks.health.mockRejectedValueOnce(new Error('Synthetic health unavailable'));
  await expect(live.checkEfakturaInboxMailbox('company-a')).rejects.toThrow('Synthetic health unavailable');
  expect(live.useEfakturaInboxLive().entries.value).toEqual([document]);
  expect(live.useEfakturaInboxLive().health.value?.state).toBe('unavailable');
  expect(live.useEfakturaInboxLive().checkingMailbox.value).toBe(false);
  expect(await live.checkEfakturaInboxMailbox('company-a')).toBe(true);
  expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok');
});

it('prevents an older pending health response from overwriting post-poll health', async () => {
  let finishOldHealth!: (value: unknown) => void;
  mocks.health.mockImplementationOnce(() => new Promise((resolve) => { finishOldHealth = resolve; }));
  const live = await import('@/evolu/efakturaInboxLive');
  live.initEfakturaInboxLive(ref('company-a'));
  await vi.waitFor(() => expect(mocks.list).toHaveBeenCalledTimes(1));
  await Promise.resolve(); await Promise.resolve();
  expect(await live.checkEfakturaInboxMailbox('company-a')).toBe(true);
  finishOldHealth({ state: 'failed', last_poll_at: '2026-11-01T00:00:00Z', failed: 5 });
  await Promise.resolve(); await Promise.resolve();
  expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok');
});

it('revokes the previous mailbox context when the active company becomes empty', async () => {
  const live = await import('@/evolu/efakturaInboxLive');
  const company = ref('company-a'); live.initEfakturaInboxLive(company);
  await vi.waitFor(() => expect(live.useEfakturaInboxLive().health.value?.state).toBe('ok'));
  company.value = '';
  expect(live.useEfakturaInboxLive().receivingEnabled.value).toBe(false);
  expect(live.useEfakturaInboxLive().bridgeCompanyId.value).toBeNull();
  expect(await live.checkEfakturaInboxMailbox('company-a')).toBe(false);
  expect(mocks.poll).not.toHaveBeenCalled();
});
