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

export default api;








