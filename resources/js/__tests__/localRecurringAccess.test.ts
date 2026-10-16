import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Evolu } from '@evolu/common/local-first';
import type { InvoicingLocalSchema } from '../evolu/schema';
import type { User } from '../store/auth';
const mocks = vi.hoisted(() => ({
  auth: { user: null as User | null, fetchUser: vi.fn() }, process: vi.fn(),
}));
vi.mock('../store/auth', () => ({ useAuthStore: () => mocks.auth }));
vi.mock('../store/flash', () => ({ useFlashStore: () => ({ success: vi.fn(), warning: vi.fn() }) }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('../evolu/flags', () => ({ isInvoicingLocalFirst: () => true }));
vi.mock('../evolu/recurringDueRunner', () => ({ processDueLocalRecurringProfiles: mocks.process }));
import { useLocalRecurringDueRunner } from '../composables/useLocalRecurringDueRunner';
const evolu = {} as Evolu<InvoicingLocalSchema>;
const user = (id: number): User => ({ id, email: 'synthetic@example.test', role: 'pro', is_guest: false, email_verified_at: '2026-01-01' });
describe('local recurring writes require fresh effective access', () => {
  beforeEach(() => { vi.clearAllMocks(); mocks.auth.user = user(1); mocks.auth.fetchUser.mockResolvedValue(true); mocks.process.mockResolvedValue({ generated: [], errors: 0 }); });
  it('preserves local data without generating documents when offline or access has ended', async () => {
    const runner = useLocalRecurringDueRunner();
    mocks.auth.fetchUser.mockResolvedValueOnce(false);
    await runner.runDueProfiles(evolu);
    expect(mocks.process).not.toHaveBeenCalled();
    mocks.auth.fetchUser.mockImplementationOnce(async () => { mocks.auth.user = { ...user(1), role: 'free' }; return true; });
    await runner.runDueProfiles(evolu);
    expect(mocks.process).not.toHaveBeenCalled();
  });
  it('does not write after refresh switches accounts', async () => {
    const runner = useLocalRecurringDueRunner();
    mocks.auth.fetchUser.mockImplementationOnce(async () => { mocks.auth.user = user(2); return true; });
    await runner.runDueProfiles(evolu);
    expect(mocks.process).not.toHaveBeenCalled();
  });
  it('does not write if the refreshed account is a guest', async () => {
    const runner = useLocalRecurringDueRunner();
    mocks.auth.fetchUser.mockImplementationOnce(async () => { mocks.auth.user = { ...user(1), is_guest: true }; return true; });
    await runner.runDueProfiles(evolu);
    expect(mocks.process).not.toHaveBeenCalled();
  });
  it('preserves manual PRO access but blocks new recurring writes for an unverified account', async () => {
    const runner = useLocalRecurringDueRunner();
    mocks.auth.fetchUser.mockImplementationOnce(async () => { mocks.auth.user = { ...user(1), email_verified_at: undefined }; return true; });
    await runner.runDueProfiles(evolu);
    expect(mocks.auth.user?.role).toBe('pro');
    expect(mocks.process).not.toHaveBeenCalled();
  });
  it('keeps rate limiting scoped to each account and blocks guest accounts', async () => {
    const runner = useLocalRecurringDueRunner();
    mocks.auth.user = user(101);
    await runner.runDueProfiles(evolu); await runner.runDueProfiles(evolu);
    expect(mocks.process).toHaveBeenCalledTimes(1);
    mocks.auth.user = user(102);
    await runner.runDueProfiles(evolu);
    expect(mocks.process).toHaveBeenCalledTimes(2);
    mocks.auth.user = { ...user(103), is_guest: true };
    await runner.runDueProfiles(evolu);
    expect(mocks.process).toHaveBeenCalledTimes(2);
  });
});
