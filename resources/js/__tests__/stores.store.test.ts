import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useStoresStore } from '../store/stores';

// Stub the api module so tests never hit the network
vi.mock('../services/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    },
    storesApi: {
        list: vi.fn(),
        get: vi.fn(),
        create: vi.fn(),
        delete: vi.fn(),
        dashboard: vi.fn(),
        apps: vi.fn(),
    },
}));

import { storesApi } from '../services/api';

describe('useStoresStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('initialises with empty stores list', () => {
        const store = useStoresStore();
        expect(store.stores).toEqual([]);
        expect(store.currentStore).toBeNull();
    });

    it('reports whether the store list was loaded successfully', async () => {
        const mockStore = { id: 'abc-123', name: 'Test Store', wallet_type: null, created_at: '', updated_at: '' };
        (storesApi.list as ReturnType<typeof vi.fn>).mockResolvedValueOnce([mockStore]);

        const storeModule = useStoresStore();
        await expect(storeModule.fetchStores()).resolves.toBe(true);
        expect(storeModule.stores).toEqual([mockStore]);

        (storesApi.list as ReturnType<typeof vi.fn>).mockRejectedValueOnce(new Error('network'));

        await expect(storeModule.fetchStores()).resolves.toBe(false);
        expect(storeModule.stores).toEqual([]);
    });

    it('fetchStore sets currentStore on success', async () => {
        const mockStore = { id: 'abc-123', name: 'Test Store', wallet_type: null, created_at: '', updated_at: '' };
        (storesApi.get as ReturnType<typeof vi.fn>).mockResolvedValueOnce(mockStore);

        const storeModule = useStoresStore();
        await storeModule.fetchStore('abc-123');

        expect(storeModule.currentStore).toEqual(mockStore);
    });

    it('fetchStore sets currentStore to null on error', async () => {
        (storesApi.get as ReturnType<typeof vi.fn>).mockRejectedValueOnce(new Error('404'));

        const storeModule = useStoresStore();
        await expect(storeModule.fetchStore('bad-id')).rejects.toThrow('404');
        expect(storeModule.currentStore).toBeNull();
    });

    it('createStore appends the created store to the list', async () => {
        const created = { id: 'new-1', name: 'New Store', wallet_type: null, created_at: '', updated_at: '' };
        (storesApi.create as ReturnType<typeof vi.fn>).mockResolvedValueOnce(created);

        const storeModule = useStoresStore();
        const payload = { name: 'New Store', default_currency: 'EUR', timezone: 'Europe/Vienna' };
        await expect(storeModule.createStore(payload)).resolves.toEqual(created);
        expect(storesApi.create).toHaveBeenCalledWith(payload);
        expect(storeModule.stores).toEqual([created]);
    });

    it('createStore rethrows and leaves the list untouched on error', async () => {
        (storesApi.create as ReturnType<typeof vi.fn>).mockRejectedValueOnce(new Error('422'));

        const storeModule = useStoresStore();
        await expect(
            storeModule.createStore({ name: 'x', default_currency: 'EUR', timezone: 'UTC' }),
        ).rejects.toThrow('422');
        expect(storeModule.stores).toEqual([]);
        expect(storeModule.loading).toBe(false);
    });

    it('fetchDashboard stores stats on success and clears them on error', async () => {
        const stats = { total_invoices: 3 };
        (storesApi.dashboard as ReturnType<typeof vi.fn>).mockResolvedValueOnce(stats);

        const storeModule = useStoresStore();
        await expect(storeModule.fetchDashboard('abc-123')).resolves.toEqual(stats);
        expect(storeModule.dashboard).toEqual(stats);

        (storesApi.dashboard as ReturnType<typeof vi.fn>).mockRejectedValueOnce(new Error('500'));
        await expect(storeModule.fetchDashboard('abc-123')).rejects.toThrow('500');
        expect(storeModule.dashboard).toBeNull();
    });

    it('fetchApps stores apps on success and clears them on error', async () => {
        const mockApps = [{ id: 'app-1', appType: 'PointOfSale' }];
        (storesApi.apps as ReturnType<typeof vi.fn>).mockResolvedValueOnce(mockApps);

        const storeModule = useStoresStore();
        await expect(storeModule.fetchApps('abc-123')).resolves.toEqual(mockApps);
        expect(storeModule.apps).toEqual(mockApps);

        (storesApi.apps as ReturnType<typeof vi.fn>).mockRejectedValueOnce(new Error('500'));
        await expect(storeModule.fetchApps('abc-123')).rejects.toThrow('500');
        expect(storeModule.apps).toEqual([]);
    });

    it('deleteStore removes the store and clears only its own dashboard', async () => {
        const storeA = { id: 'store-a', name: 'A', wallet_type: null, created_at: '', updated_at: '' };
        const storeB = { id: 'store-b', name: 'B', wallet_type: null, created_at: '', updated_at: '' };
        (storesApi.list as ReturnType<typeof vi.fn>).mockResolvedValueOnce([storeA, storeB]);
        (storesApi.dashboard as ReturnType<typeof vi.fn>).mockResolvedValueOnce({ total_invoices: 1 });
        (storesApi.delete as ReturnType<typeof vi.fn>).mockResolvedValue({ message: 'ok', btcpay_deleted: true });

        const storeModule = useStoresStore();
        await storeModule.fetchStores();
        await storeModule.fetchDashboard('store-a');

        // Deleting an unrelated store keeps store-a's dashboard
        await expect(storeModule.deleteStore('store-b')).resolves.toEqual({ message: 'ok', btcpay_deleted: true });
        expect(storeModule.stores).toEqual([storeA]);
        expect(storeModule.dashboard).not.toBeNull();

        // Deleting the dashboard's own store clears it
        await storeModule.deleteStore('store-a');
        expect(storeModule.stores).toEqual([]);
        expect(storeModule.dashboard).toBeNull();
    });
});
