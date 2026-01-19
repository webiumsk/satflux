import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../store/auth';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            name: 'home',
            component: () => import('../pages/Dashboard.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/login',
            name: 'login',
            component: () => import('../pages/auth/Login.vue'),
            meta: { requiresGuest: true },
        },
        {
            path: '/register',
            name: 'register',
            component: () => import('../pages/auth/Register.vue'),
            meta: { requiresGuest: true },
        },
        {
            path: '/password/reset',
            name: 'password-reset',
            component: () => import('../pages/auth/PasswordReset.vue'),
            meta: { requiresGuest: true },
        },
        {
            path: '/auth/verify-email/:id/:hash',
            name: 'verify-email',
            component: () => import('../pages/auth/VerifyEmail.vue'),
        },
        {
            path: '/account',
            name: 'account',
            component: () => import('../pages/account/Profile.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/stores',
            name: 'stores',
            component: () => import('../pages/stores/Index.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/stores/create',
            name: 'stores-create',
            component: () => import('../pages/stores/Create.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/stores/:id',
            name: 'stores-show',
            component: () => import('../pages/stores/Show.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/stores/:id/next-steps',
            name: 'stores-next-steps',
            component: () => import('../pages/stores/NextSteps.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/stores/:id/checklist',
            name: 'stores-checklist',
            component: () => import('../pages/stores/Checklist.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/stores/:id/settings',
            name: 'stores-settings',
            component: () => import('../pages/stores/Settings.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/stores/:id/wallet-connection',
            name: 'stores-wallet-connection',
            component: () => import('../pages/stores/WalletConnection.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/stores/:id/apps',
            name: 'stores-apps',
            component: () => import('../pages/stores/Apps.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/stores/:id/apps/create',
            name: 'stores-apps-create',
            component: () => import('../pages/stores/AppsCreate.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/stores/:id/lightning-addresses',
            name: 'stores-lightning-addresses',
            component: () => import('../pages/stores/LightningAddresses.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/stores/:id/api-keys',
            name: 'stores-api-keys',
            component: () => import('../pages/stores/ApiKeys.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/stores/:id/apps/:appId',
            name: 'stores-apps-show',
            component: () => import('../pages/stores/AppsShow.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/stores/:id/invoices',
            name: 'stores-invoices',
            component: () => import('../pages/stores/Invoices.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/stores/:id/invoices/:invoiceId',
            name: 'stores-invoices-show',
            component: () => import('../pages/stores/InvoiceShow.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/stores/:id/exports',
            name: 'stores-exports',
            component: () => import('../pages/stores/Exports.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/support/wallet-connections',
            name: 'support-wallet-connections',
            component: () => import('../pages/support/WalletConnections.vue'),
            meta: { requiresAuth: true },
        },
    ],
});

// Navigation guards
router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();
    
    // Only fetch user if not already loaded and not navigating to login/register
    // This prevents infinite loops
    if (!authStore.user && to.name !== 'login' && to.name !== 'register' && to.name !== 'password-reset') {
        try {
            await authStore.fetchUser();
        } catch (error) {
            // If fetch fails (401), user is null which is correct
            // Router guard will handle redirect below
        }
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        // Redirect to login, but prevent infinite loops
        if (to.name !== 'login') {
            next({ name: 'login', query: { redirect: to.fullPath } });
        } else {
            next(); // Already on login page, allow it
        }
    } else if (to.meta.requiresGuest && authStore.isAuthenticated) {
        next({ name: 'home' });
    } else {
        next();
    }
});

export default router;
