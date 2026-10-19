import { beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, reactive } from 'vue';
import type { User } from '../store/auth';
const state = vi.hoisted(() => ({ auth: { user: null as User | null }, get: vi.fn() }));
vi.mock('../store/auth', () => ({ useAuthStore: () => state.auth }));
vi.mock('../services/api', () => ({ default: { get: state.get } }));
import { useAccountLimits, type AccountLimitsData } from '../composables/useAccountLimits';
const data = (max: number): AccountLimitsData => ({
  stores: { current: 1, max, unlimited: false },
  api_keys: { current: 0, max, unlimited: false },
  ln_addresses: { current: 0, max, unlimited: false },
});
function account(id: number): User { return { id, email: 'synthetic@example.test' }; }
function setup() {
  const scope = effectScope();
  const limits = scope.run(() => useAccountLimits())!;
  return { ...limits, stop: () => scope.stop() };
}
describe('account limits snapshot isolation', () => {
  beforeEach(() => { state.auth = reactive({ user: account(1) }); state.get.mockReset(); });
  it('separates account, store and policy snapshots', async () => {
    const limits = setup(); limits.clearCache();
    state.get.mockResolvedValueOnce({ data: data(1) });
    await limits.load('store-a');
    state.get.mockResolvedValueOnce({ data: data(3) });
    await limits.load('store-b');
    expect(limits.limits.value?.stores.max).toBe(3);
    await limits.load('store-a');
    expect(limits.limits.value?.stores.max).toBe(1);
    state.auth.user = account(2);
    expect(limits.limits.value).toBeNull();
    state.get.mockResolvedValueOnce({ data: data(2) });
    await limits.load('store-a');
    expect(limits.limits.value?.stores.max).toBe(2);
    state.auth.user!.effective_access = { policy_version: 'new' } as User['effective_access'];
    expect(limits.limits.value).toBeNull();
    state.get.mockResolvedValueOnce({ data: data(4) });
    await limits.load('store-a');
    expect(state.get).toHaveBeenCalledTimes(4);
    limits.stop();
  });
  it('does not publish a late response after the user changes', async () => {
    const limits = setup(); limits.clearCache();
    let complete!: (value: { data: AccountLimitsData }) => void;
    state.get.mockReturnValueOnce(new Promise(resolve => { complete = resolve; }));
    const pending = limits.load('store-a');
    state.auth.user = account(2);
    complete({ data: data(3) }); await pending;
    expect(limits.limits.value).toBeNull(); limits.stop();
  });
  it('clears rejected requests so failed quota loading can retry', async () => {
    const limits = setup(); limits.clearCache();
    state.get.mockRejectedValueOnce(new Error('offline'));
    await expect(limits.load()).rejects.toThrow('offline');
    expect(limits.limits.value).toBeNull();
    state.get.mockResolvedValueOnce({ data: data(3) });
    await limits.load();
    expect(limits.limits.value?.stores.max).toBe(3); limits.stop();
  });
  it('does not resurrect a response after cache invalidation', async () => {
    const limits = setup(); limits.clearCache();
    let complete!: (value: { data: AccountLimitsData }) => void;
    state.get.mockReturnValueOnce(new Promise(resolve => { complete = resolve; }));
    const pending = limits.load(); limits.clearCache();
    complete({ data: data(3) }); await pending;
    expect(limits.limits.value).toBeNull(); limits.stop();
  });
});
