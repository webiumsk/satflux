import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api';

export interface Store {
    id: string;
    name: string;
    wallet_type: 'blink' | 'aqua_boltz' | null;
    created_at: string;
    updated_at: string;
    checklist_items?: ChecklistItem[];
}

export interface ChecklistItem {
    key: string;
    description: string;
    link: string | null;
    completed_at: string | null;
    is_completed: boolean;
}

export const useStoresStore = defineStore('stores', () => {
    const stores = ref<Store[]>([]);
    const currentStore = ref<Store | null>(null);
    const loading = ref(false);

    async function fetchStores() {
        loading.value = true;
        try {
            const response = await api.get('/stores');
            stores.value = response.data.data || [];
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
        wallet_type: 'blink' | 'aqua_boltz';
    }) {
        loading.value = true;
        try {
            const response = await api.post('/stores', data);
            const store = response.data.data;
            stores.value.push(store);
            return store;
        } finally {
            loading.value = false;
        }
    }

    return {
        stores,
        currentStore,
        loading,
        fetchStores,
        fetchStore,
        createStore,
    };
});

