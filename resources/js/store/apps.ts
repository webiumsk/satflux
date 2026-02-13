import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../services/api';

export interface App {
    id: string;
    name: string;
    app_type: 'PointOfSale' | 'PaymentButton' | 'LightningAddress' | 'Tickets';
    archived?: boolean;
    config?: any;
    metadata?: any;
    btcpay_app_url?: string;
    created_at?: string;
    updated_at?: string;
}

export const useAppsStore = defineStore('apps', () => {
    const apps = ref<App[]>([]);
    const currentApp = ref<App | null>(null);
    const loading = ref(false);

    async function fetchApps(storeId: string) {
        loading.value = true;
        try {
            const response = await api.get(`/stores/${storeId}/apps`);
            apps.value = response.data.data || [];
            return apps.value;
        } catch (error) {
            apps.value = [];
            throw error;
        } finally {
            loading.value = false;
        }
    }

    async function createApp(storeId: string, appType: string, config: { name: string; config?: any }) {
        loading.value = true;
        try {
            const response = await api.post(`/stores/${storeId}/apps`, {
                app_type: appType,
                name: config.name,
                config: config.config || {},
            });
            const app = response.data.data;
            apps.value.push(app);
            return app;
        } finally {
            loading.value = false;
        }
    }

    async function updateApp(storeId: string, appId: string, config: { name?: string; config?: any; archived?: boolean }) {
        loading.value = true;
        try {
            const response = await api.put(`/stores/${storeId}/apps/${appId}`, config);
            const app = response.data.data;
            const index = apps.value.findIndex(a => a.id === appId);
            if (index !== -1) {
                apps.value[index] = app;
            }
            if (currentApp.value?.id === appId) {
                currentApp.value = app;
            }
            return app;
        } finally {
            loading.value = false;
        }
    }

    async function archiveApp(storeId: string, appId: string) {
        return updateApp(storeId, appId, { archived: true });
    }

    async function unarchiveApp(storeId: string, appId: string) {
        return updateApp(storeId, appId, { archived: false });
    }

    async function deleteApp(storeId: string, appId: string) {
        loading.value = true;
        try {
            await api.delete(`/stores/${storeId}/apps/${appId}`);
            apps.value = apps.value.filter(a => a.id !== appId);
            if (currentApp.value?.id === appId) {
                currentApp.value = null;
            }
        } finally {
            loading.value = false;
        }
    }

    async function fetchApp(storeId: string, appId: string) {
        loading.value = true;
        try {
            const response = await api.get(`/stores/${storeId}/apps/${appId}`);
            currentApp.value = response.data.data;
            return currentApp.value;
        } finally {
            loading.value = false;
        }
    }

    function getAppsByType(type: string): App[] {
        return apps.value.filter(app => app.app_type.toLowerCase() === type.toLowerCase());
    }

    return {
        apps,
        currentApp,
        loading,
        fetchApps,
        createApp,
        updateApp,
        archiveApp,
        unarchiveApp,
        deleteApp,
        fetchApp,
        getAppsByType,
    };
});

