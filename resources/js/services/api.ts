import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
    withCredentials: true,
});

// Ensure CSRF token is sent with all requests
// Axios automatically reads XSRF-TOKEN cookie and sets X-XSRF-TOKEN header,
// but we need to ensure the cookie is available
api.interceptors.request.use(
    async (config) => {
        // Get CSRF token from cookie (XSRF-TOKEN is the cookie name Sanctum uses)
        const getCookie = (name: string): string | null => {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) {
                return parts.pop()?.split(';').shift() || null;
            }
            return null;
        };

        const csrfToken = getCookie('XSRF-TOKEN');
        
        // If token exists in cookie, set it in header
        if (csrfToken) {
            config.headers['X-XSRF-TOKEN'] = decodeURIComponent(csrfToken);
        }

        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Response interceptor for error handling
api.interceptors.response.use(
    (response) => response,
    (error) => {
        // Don't redirect on 401 in interceptor - let router guard handle it
        // Redirecting here causes infinite loops when router guard calls fetchUser()
        // Router guard will handle authentication redirects properly
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

// Documentation API
export const documentationApi = {
    index: (params?: { category_id?: string; search?: string }) => 
        api.get('/documentation', { params }),
    show: (slug: string) => 
        api.get(`/documentation/${slug}`),
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








