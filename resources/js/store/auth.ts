import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
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

    async function register(name: string, email: string, password: string, password_confirmation: string) {
        loading.value = true;
        try {
            const response = await api.post('/auth/register', {
                name,
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

