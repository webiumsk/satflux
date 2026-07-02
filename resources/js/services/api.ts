import axios from 'axios';
import { classifyApiErrorForFlash, GLOBAL_API_ERROR_MESSAGE_KEYS, type GlobalApiErrorKind } from './apiError';
import type { Store } from '../store/stores';
import type { BtcPayApp, StoreDashboardStats } from '../types/btcpay';

declare module 'axios' {
    export interface AxiosRequestConfig {
        /** Set true when the caller fully handles errors itself - suppresses the global error flash. */
        skipErrorFlash?: boolean;
    }
}

/** Standard Laravel API response wrapper: { data: ..., message?: ... } */
export interface ApiEnvelope<T> {
    data: T;
    message?: string;
}

const api = axios.create({
    baseURL: '/api',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
    withCredentials: true,
});

// Helper: get CSRF token from cookie (shared for api and postWeb)
function getCsrfToken(): string | null {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; XSRF-TOKEN=`);
    if (parts.length === 2) {
        return (parts.pop()?.split(';').shift() || null);
    }
    return null;
}

/** Session-authenticated GET for file downloads (PDF, etc.) - not under /api. */
export async function getWebBlob(path: string): Promise<Blob> {
    const { data } = await axios.get(path, {
        baseURL: '',
        responseType: 'blob',
        withCredentials: true,
    });
    return data;
}

export function businessDocumentPdfPath(companyId: string, documentId: string): string {
    return `/invoicing/companies/${companyId}/documents/${documentId}/pdf`;
}

export function businessDocumentIsdocPath(companyId: string, documentId: string): string {
    return `/invoicing/companies/${companyId}/documents/${documentId}/isdoc`;
}

export function businessDocumentUblPath(companyId: string, documentId: string): string {
    return `/invoicing/companies/${companyId}/documents/${documentId}/ubl`;
}

// Post to a web route (no /api prefix) - for password reset etc., avoids Sanctum auth
export async function postWeb<T = unknown>(path: string, data: object): Promise<T> {
    const csrf = getCsrfToken();
    const { data: result } = await axios.request<T>({
        method: 'post',
        url: path,
        baseURL: '',
        data,
        withCredentials: true,
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            ...(csrf ? { 'X-XSRF-TOKEN': decodeURIComponent(csrf) } : {}),
        },
    });
    return result;
}

// Ensure CSRF token is sent with all requests
// Axios automatically reads XSRF-TOKEN cookie and sets X-XSRF-TOKEN header,
// but we need to ensure the cookie is available
api.interceptors.request.use(
    async (config) => {
        const csrfToken = getCsrfToken();
        if (csrfToken) {
            config.headers['X-XSRF-TOKEN'] = decodeURIComponent(csrfToken);
        }
        // Let browser set Content-Type with boundary for FormData (file uploads)
        if (config.data instanceof FormData) {
            delete config.headers['Content-Type'];
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Global error fallback: network failures, 5xx and 429 surface as a flash
// message so silent catch blocks still give the user feedback. Callers that
// fully handle errors themselves opt out with { skipErrorFlash: true }.
// The flash store carries an i18n KEY (translated in FlashMessage.vue) so this
// module never imports i18n - i18n.ts imports api.ts and would cycle.
function notifyGlobalApiError(kind: GlobalApiErrorKind): void {
    void (async () => {
        try {
            const { useFlashStore } = await import('../store/flash');
            useFlashStore().errorKey(GLOBAL_API_ERROR_MESSAGE_KEYS[kind]);
        } catch {
            // Pinia not ready (boot-time failure) - nothing to show yet.
        }
    })();
}

// Response interceptor for error handling
api.interceptors.response.use(
    (response) => response,
    (error) => {
        // Don't redirect on 401 in interceptor - let router guard handle it
        // Redirecting here causes infinite loops when router guard calls fetchUser()
        // Router guard will handle authentication redirects properly
        const kind = classifyApiErrorForFlash(error);
        if (kind && !error?.config?.skipErrorFlash) {
            notifyGlobalApiError(kind);
        }
        return Promise.reject(error);
    }
);

// Locale management
export const setLocale = async (locale: string): Promise<void> => {
    try {
        await api.post('/locale', { locale });
    } catch (error) {
        console.error('Failed to set locale:', error);
        throw error;
    }
};

export interface CreateStorePayload {
    name: string;
    default_currency: string;
    timezone: string;
    /** Omit on first-step create; configure wallet in a follow-up step. */
    wallet_type?: 'blink' | 'aqua_boltz' | 'cashu' | null;
    preferred_exchange?: string;
    connection_string?: string;
    mint_url?: string;
    lightning_address?: string;
}

// Stores API - typed wrapper for /stores endpoints. New store-scoped calls
// belong here (typed, one place to change URLs), not inline in components.
export const storesApi = {
    async list(): Promise<Store[]> {
        const { data } = await api.get<ApiEnvelope<Store[]>>('/stores');
        return data.data ?? [];
    },
    async get(storeId: string): Promise<Store> {
        const { data } = await api.get<ApiEnvelope<Store>>(`/stores/${storeId}`);
        return data.data;
    },
    async create(payload: CreateStorePayload): Promise<Store> {
        const { data } = await api.post<ApiEnvelope<Store>>('/stores', payload);
        return data.data;
    },
    async delete(storeId: string): Promise<{ message?: string; btcpay_deleted?: boolean }> {
        const { data } = await api.delete<{ message?: string; btcpay_deleted?: boolean }>(`/stores/${storeId}`);
        return data;
    },
    async dashboard(storeId: string, params?: { source?: string; refresh?: boolean }): Promise<StoreDashboardStats> {
        const apiParams: Record<string, string | number> = {};
        if (params?.source) apiParams.source = params.source;
        if (params?.refresh) apiParams.refresh = 1;
        const { data } = await api.get<ApiEnvelope<StoreDashboardStats>>(`/stores/${storeId}/dashboard`, { params: apiParams });
        return data.data;
    },
    async apps(storeId: string): Promise<BtcPayApp[]> {
        const { data } = await api.get<ApiEnvelope<BtcPayApp[]>>(`/stores/${storeId}/apps`);
        return data.data ?? [];
    },
};

// Documentation API (locale ensures correct language content from backend)
export const documentationApi = {
    index: (params?: { category_id?: string; search?: string; locale?: string }) =>
        api.get('/documentation', { params }),
    show: (slug: string, params?: { locale?: string }) =>
        api.get(`/documentation/${slug}`, { params: params ?? {} }),
};

// FAQ API
export const faqApi = {
    index: (params?: { category_id?: string; search?: string }) => 
        api.get('/faq', { params }),
    show: (slug: string) => 
        api.get(`/faq/${slug}`),
    markHelpful: (slug: string) => 
        api.post(`/faq/${slug}/helpful`),
};

// Admin Documentation API
export const adminDocumentationApi = {
    articles: {
        index: (params?: { category_id?: string; is_published?: boolean; search?: string }) => 
            api.get('/admin/documentation/articles', { params }),
        show: (id: string) => 
            api.get(`/admin/documentation/articles/${id}`),
        create: (data: any) => 
            api.post('/admin/documentation/articles', data),
        update: (id: string, data: any) => 
            api.put(`/admin/documentation/articles/${id}`, data),
        delete: (id: string) => 
            api.delete(`/admin/documentation/articles/${id}`),
    },
    categories: {
        index: () => 
            api.get('/admin/documentation/categories'),
        show: (id: string) => 
            api.get(`/admin/documentation/categories/${id}`),
        create: (data: any) => 
            api.post('/admin/documentation/categories', data),
        update: (id: string, data: any) => 
            api.put(`/admin/documentation/categories/${id}`, data),
        delete: (id: string) => 
            api.delete(`/admin/documentation/categories/${id}`),
    },
};

// Admin FAQ API
export const adminFaqApi = {
    items: {
        index: (params?: { category_id?: string; is_published?: boolean; search?: string }) => 
            api.get('/admin/faq/items', { params }),
        show: (id: string) => 
            api.get(`/admin/faq/items/${id}`),
        create: (data: any) => 
            api.post('/admin/faq/items', data),
        update: (id: string, data: any) => 
            api.put(`/admin/faq/items/${id}`, data),
        delete: (id: string) => 
            api.delete(`/admin/faq/items/${id}`),
    },
    categories: {
        index: () => 
            api.get('/admin/faq/categories'),
        show: (id: string) => 
            api.get(`/admin/faq/categories/${id}`),
        create: (data: any) => 
            api.post('/admin/faq/categories', data),
        update: (id: string, data: any) => 
            api.put(`/admin/faq/categories/${id}`, data),
        delete: (id: string) => 
            api.delete(`/admin/faq/categories/${id}`),
    },
};

export default api;








