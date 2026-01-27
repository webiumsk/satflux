import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';
import api from '../services/api';
import { useStoresStore } from './stores';

export interface User {
    id: number;
    email: string;
    email_verified_at?: string;
    role?: string;
    name?: string;
}

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const loading = ref(false);

    const isAuthenticated = computed(() => user.value !== null);

    async function fetchUser() {
        try {
            const response = await api.get('/user');
            user.value = response.data;
        } catch (error) {
            user.value = null;
        }
    }

    async function login(email: string, password: string, remember = false) {
        loading.value = true;
        try {
            // Ensure CSRF cookie is set before login
            await axios.get('/sanctum/csrf-cookie', { withCredentials: true });

            const response = await api.post('/auth/login', {
                email,
                password,
                remember,
            });
            user.value = response.data.user;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function register(email: string, password: string, password_confirmation: string) {
        loading.value = true;
        try {
            // Ensure CSRF cookie is set before register
            await axios.get('/sanctum/csrf-cookie', { withCredentials: true });

            const response = await api.post('/auth/register', {
                email,
                password,
                password_confirmation,
            });
            user.value = response.data.user;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function logout() {
        try {
            await api.post('/auth/logout');
        } finally {
            user.value = null;
            // Clear stores to prevent data leakage between sessions
            const storesStore = useStoresStore();
            storesStore.stores = [];
            storesStore.currentStore = null;
        }
    }

    return {
        user,
        loading,
        isAuthenticated,
        fetchUser,
        login,
        register,
        logout,
    };
});




