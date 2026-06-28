import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../services/api';
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
            const response = await api.get('/stores');
            stores.value = response.data.data || [];
            return true;
        } catch (error: any) {
            stores.value = [];
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function fetchStore(id: string) {
        loading.value = true;
        try {
            const response = await api.get(`/stores/${id}`);
            currentStore.value = response.data.data;
            return response.data.data;
        } catch (e) {
            // Avoid showing the previous store when navigation fails (404/403/network).
            currentStore.value = null;
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function createStore(data: {
        name: string;
        default_currency: string;
        timezone: string;
        /** Omit on first-step create; configure wallet in a follow-up step. */
        wallet_type?: 'blink' | 'aqua_boltz' | 'cashu' | null;
        preferred_exchange?: string;
        connection_string?: string;
        mint_url?: string;
        lightning_address?: string;
    }) {
        loading.value = true;
        try {
            // CSRF is already handled by api interceptor, no need for explicit fetch
            const response = await api.post('/stores', data);
            const store = response.data.data;
            stores.value.push(store);
            return store;
        } finally {
            loading.value = false;
        }
    }

    async function fetchDashboard(storeId: string, params?: { source?: string; refresh?: boolean }) {
        loading.value = true;
        try {
            const apiParams: Record<string, string | number> = {};
            if (params?.source) apiParams.source = params.source;
            if (params?.refresh) apiParams.refresh = 1;
            const response = await api.get(`/stores/${storeId}/dashboard`, { params: apiParams });
            dashboard.value = response.data.data;
            return dashboard.value;
        } catch (error: any) {
            dashboard.value = null;
            throw error;
        } finally {
            loading.value = false;
        }
    }

    async function fetchApps(storeId: string) {
        loading.value = true;
        try {
            const response = await api.get(`/stores/${storeId}/apps`);
            apps.value = response.data.data || [];
            return apps.value;
        } catch (error: any) {
            apps.value = [];
            throw error;
        } finally {
            loading.value = false;
        }
    }

    async function deleteStore(storeId: string) {
        loading.value = true;
        try {
            const response = await api.delete(`/stores/${storeId}`);
            
            // Remove store from local list
            const index = stores.value.findIndex(s => s.id === storeId);
            if (index > -1) {
                stores.value.splice(index, 1);
            }
            // Clear current store if it was deleted
            if (currentStore.value?.id === storeId) {
                currentStore.value = null;
            }
            // Clear dashboard if it was for deleted store
            if (dashboard.value) {
                dashboard.value = null;
            }
            
            // Return response (may contain btcpay_deleted flag)
            return response;
        } catch (error: any) {
            // Re-throw error so it can be handled in the component
            throw error;
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







