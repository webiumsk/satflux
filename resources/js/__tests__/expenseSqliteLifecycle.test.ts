import { beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
  terminate: vi.fn(), construct: vi.fn(), owner: vi.fn(), upsert: vi.fn(),
}));
vi.mock('node:worker_threads', () => { const Worker = class {
  constructor() { mocks.construct(); }
  on() {}
  postMessage() {}
  terminate() { return mocks.terminate(); }
}; return { Worker, default: { Worker } }; });
vi.mock('@evolu/common', () => ({
  createConsole: () => ({}), createRandom: () => ({}), createRandomBytes: () => ({}), createTime: () => ({}),
  createId: () => 'synthetic-id', SimpleName: { orThrow: (value: string) => value },
  createEvolu: () => () => ({ get appOwner() { return mocks.owner(); }, upsert: mocks.upsert }),
}));
vi.mock('@/evolu/ownerScope', () => ({ withCompanyOwnerScoping: (value: unknown) => value }));
vi.mock('@/evolu/schema', () => ({ InvoicingLocalSchema: {} }));
import { createExpenseSqliteProbe } from './expenseSqliteTestSupport';

beforeEach(() => {
  vi.resetAllMocks();
  mocks.owner.mockResolvedValue({ id: 'synthetic-owner' });
  mocks.terminate.mockResolvedValue(0);
  mocks.upsert.mockReturnValue({ ok: false });
});
describe('native SQLite probe initialization cleanup', () => {
  it('terminates a started worker when owner initialization rejects', async () => {
    const error = new Error('synthetic owner failure');
    mocks.owner.mockRejectedValue(error);
    await expect(createExpenseSqliteProbe()).rejects.toBe(error);
    expect(mocks.construct).toHaveBeenCalledOnce();
    expect(mocks.upsert).not.toHaveBeenCalled();
    expect(mocks.terminate).toHaveBeenCalledOnce();
  });
  it.each([1, 2, 3])('terminates when seed mutation %s fails', async (failedSeed) => {
    for (let index = 1; index < failedSeed; index++) {
      mocks.upsert.mockImplementationOnce((_table, _patch, options) => {
        options.onComplete(); return { ok: true };
      });
    }
    await expect(createExpenseSqliteProbe()).rejects.toThrow('synthetic_fixture_validation_failed');
    expect(mocks.upsert).toHaveBeenCalledTimes(failedSeed);
    expect(mocks.terminate).toHaveBeenCalledOnce();
  });
  it('keeps the original failure when terminating also fails', async () => {
    const error = new Error('synthetic owner failure');
    mocks.owner.mockRejectedValue(error);
    mocks.terminate.mockRejectedValue(new Error('synthetic cleanup failure'));
    await expect(createExpenseSqliteProbe()).rejects.toBe(error);
    expect(mocks.terminate).toHaveBeenCalledOnce();
  });
});
