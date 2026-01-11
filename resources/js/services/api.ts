import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
    withCredentials: true,
});

// Request interceptor to ensure CSRF cookie is set
api.interceptors.request.use(
    async (config) => {
        // Ensure CSRF cookie is set before making requests
        try {
            await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
        } catch (error) {
            // CSRF cookie endpoint may fail on first request, continue anyway
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
        if (error.response?.status === 401) {
            // Handle unauthorized - redirect to login
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

export default api;

