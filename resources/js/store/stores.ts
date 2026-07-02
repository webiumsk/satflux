import { defineStore } from 'pinia';
import { ref } from 'vue';
import { storesApi, type CreateStorePayload } from '../services/api';
import type { BtcPayApp, StoreDashboardStats } from '../types/btcpay';

export interface Store {
    id: string;
    name: string;
    archived?: boolean;
    wallet_type: 'blink' | 'aqua_boltz' | 'cashu' | null;
    created_at: string;
    updated_at: string;
    logo_url?: string | null;
    checklist_items?: ChecklistItem[];
    wallet_connection?: {
        id: string;
        type: 'blink' | 'aqua_descriptor';
        status: 'pending' | 'needs_support' | 'connected';
        masked_secret?: string;
        submitted_at?: string;
    };
}

export interface ChecklistItem {
    key: string;
    description: string;
    link: string | null;
    completed_at: string | null;
    is_completed: boolean;
}

export type InvoiceSource = 'pos' | 'pay_button' | 'ln_address' | 'tickets' | 'api' | 'other';

export type DashboardData = StoreDashboardStats;

export const useStoresStore = defineStore('stores', () => {
    const stores = ref<Store[]>([]);
    const currentStore = ref<Store | null>(null);
    const dashboard = ref<DashboardData | null>(null);
    const apps = ref<BtcPayApp[]>([]);
    const loading = ref(false);

    async function fetchStores(): Promise<boolean> {
        loading.value = true;
        try {
            stores.value = await storesApi.list();
            return true;
        } catch {
            // Global error flash covers network/5xx; callers branch on the boolean.
            stores.value = [];
            return false;
        } finally {
            loading.value = false;
        }
    }

    let fetchStoreGeneration = 0;

    async function fetchStore(id: string): Promise<Store> {
        const generation = ++fetchStoreGeneration;
        loading.value = true;
        try {
            const store = await storesApi.get(id);
            if (generation === fetchStoreGeneration) {
                currentStore.value = store;
            }
            return store;
        } catch (e) {
            if (generation === fetchStoreGeneration) {
                // Avoid showing the previous store when navigation fails (404/403/network).
                currentStore.value = null;
            }
            throw e;
        } finally {
            if (generation === fetchStoreGeneration) {
                loading.value = false;
            }
        }
    }

    async function createStore(data: CreateStorePayload): Promise<Store> {
        loading.value = true;
        try {
            const store = await storesApi.create(data);
            stores.value.push(store);
            return store;
        } finally {
            loading.value = false;
        }
    }

    async function fetchDashboard(storeId: string, params?: { source?: string; refresh?: boolean }): Promise<DashboardData> {
        loading.value = true;
        try {
            const stats = await storesApi.dashboard(storeId, params);
            dashboard.value = stats;
            return stats;
        } catch (error) {
            dashboard.value = null;
            throw error;
        } finally {
            loading.value = false;
        }
    }

    async function fetchApps(storeId: string): Promise<BtcPayApp[]> {
        loading.value = true;
        try {
            apps.value = await storesApi.apps(storeId);
            return apps.value;
        } catch (error) {
            apps.value = [];
            throw error;
        } finally {
            loading.value = false;
        }
    }

    async function deleteStore(storeId: string): Promise<{ message?: string; btcpay_deleted?: boolean }> {
        loading.value = true;
        try {
            const result = await storesApi.delete(storeId);

            const index = stores.value.findIndex(s => s.id === storeId);
            if (index > -1) {
                stores.value.splice(index, 1);
            }
            if (currentStore.value?.id === storeId) {
                currentStore.value = null;
            }
            dashboard.value = null;

            return result;
        } finally {
            loading.value = false;
        }
    }

    return {
        stores,
        currentStore,
        dashboard,
        apps,
        loading,
        fetchStores,
        fetchStore,
        createStore,
        fetchDashboard,
        fetchApps,
        deleteStore,
    };
});







