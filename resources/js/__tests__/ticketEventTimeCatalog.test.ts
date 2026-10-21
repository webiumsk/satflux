import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useTicketsStore } from '../store/tickets';
const api = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock('../services/api', () => ({ default: api }));
beforeEach(() => { vi.resetAllMocks(); setActivePinia(createPinia()); });
describe('provider event time zone catalog', () => {
    it('uses the local store route and only returns the provider catalog', async () => {
        api.get.mockResolvedValue({ data: { data: { timeZones: ['UTC', 'Europe/Bratislava', 'UTC'] } } });
        expect(await useTicketsStore().fetchTimeZones('local-store')).toEqual(['UTC', 'Europe/Bratislava']);
        expect(api.get).toHaveBeenCalledWith('/stores/local-store/tickets/time-zones');
    });
    it.each([null, [], [''], [24], 'Europe/Bratislava'].map(zones => ({ zones })))('rejects malformed capability responses $zones without fabricating a zone', async ({ zones }) => {
        api.get.mockResolvedValue({ data: { data: { timeZones: zones } } });
        await expect(useTicketsStore().fetchTimeZones('local-store')).rejects.toThrow('Invalid event time zone catalog');
    });
    it('preserves404 so the editor can show the provider upgrade requirement', async () => {
        const failure = { response: { status: 404 } }; api.get.mockRejectedValue(failure);
        await expect(useTicketsStore().fetchTimeZones('local-store')).rejects.toBe(failure);
    });
});
