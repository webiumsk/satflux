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
}));

import api from '../services/api';

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
        (api.get as ReturnType<typeof vi.fn>).mockResolvedValueOnce({ data: { data: [mockStore] } });

        const storeModule = useStoresStore();
        await expect(storeModule.fetchStores()).resolves.toBe(true);
        expect(storeModule.stores).toEqual([mockStore]);

        (api.get as ReturnType<typeof vi.fn>).mockRejectedValueOnce(new Error('network'));

        await expect(storeModule.fetchStores()).resolves.toBe(false);
        expect(storeModule.stores).toEqual([]);
    });

    it('fetchStore sets currentStore on success', async () => {
        const mockStore = { id: 'abc-123', name: 'Test Store', wallet_type: null, created_at: '', updated_at: '' };
        (api.get as ReturnType<typeof vi.fn>).mockResolvedValueOnce({ data: { data: mockStore } });

        const storeModule = useStoresStore();
        await storeModule.fetchStore('abc-123');

        expect(storeModule.currentStore).toEqual(mockStore);
    });

    it('fetchStore sets currentStore to null on error', async () => {
        (api.get as ReturnType<typeof vi.fn>).mockRejectedValueOnce(new Error('404'));

        const storeModule = useStoresStore();
        await expect(storeModule.fetchStore('bad-id')).rejects.toThrow('404');
        expect(storeModule.currentStore).toBeNull();
    });
});
