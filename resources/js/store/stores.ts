import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api';

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

export interface DashboardData {
    paid_invoices_last_7d: number;
    total_invoices: number;
    recent_invoices: Array<{
        id: string;
        invoice_id: string;
        status: string;
        amount: string;
        currency: string;
        created_time: string;
        source?: InvoiceSource;
    }>;
    apps: {
        crowdfund: any[];
        point_of_sale: any[];
        payment_button: any[];
    };
    is_ready: boolean;
    has_wallet_connection: boolean;
    sales: {
        last_7_days: Array<{ date: string; count: number }>;
        last_30_days: Array<{ date: string; count: number }>;
        total_7d: number;
        total_30d: number;
    };
    top_items: Array<{
        name: string;
        count: number;
        total: number;
        currency: string;
    }>;
    can_filter_by_source?: boolean;
    by_source?: Record<InvoiceSource, unknown>;
}

export const useStoresStore = defineStore('stores', () => {
    const stores = ref<Store[]>([]);
    const currentStore = ref<Store | null>(null);
    const dashboard = ref<DashboardData | null>(null);
    const apps = ref<any[]>([]);
    const loading = ref(false);

    async function fetchStores() {
        loading.value = true;
        try {
            const response = await api.get('/stores');
            stores.value = response.data.data || [];
        } catch (error: any) {
            stores.value = [];
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
        } finally {
            loading.value = false;
        }
    }

    async function createStore(data: {
        name: string;
        default_currency: string;
        timezone: string;
        wallet_type: 'blink' | 'aqua_boltz' | 'cashu';
        preferred_exchange?: string;
        connection_string?: string;
        mint_url?: string;
        unit?: 'sat' | 'usd';
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







