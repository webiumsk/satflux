import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../store/auth';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            name: 'landing',
            component: () => import('../pages/Landing.vue'),
            meta: { public: true },
        },
        {
            path: '/dashboard',
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
            redirect: to => {
                return { name: 'stores-show', params: { id: to.params.id }, query: { section: 'settings' } }
            }
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
            redirect: to => {
                return { name: 'stores-show', params: { id: to.params.id }, query: { section: 'invoices' } }
            }
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
            redirect: to => {
                return { name: 'stores-show', params: { id: to.params.id }, query: { section: 'exports' } }
            }
        },
        {
            path: '/support',
            name: 'support',
            component: () => import('../pages/Support.vue'),
            meta: { public: true },
        },
        {
            path: '/billing/success',
            name: 'billing-success',
            component: () => import('../pages/BillingSuccess.vue'),
            meta: { public: true },
        },
        {
            path: '/support/wallet-connections',
            name: 'support-wallet-connections',
            component: () => import('../pages/support/WalletConnections.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/admin/users',
            name: 'admin-users',
            component: () => import('../pages/admin/Users.vue'),
            meta: { requiresAuth: true, requiresAdmin: true },
        },
    ],
});

// Navigation guards
router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();

    // Fetch user info for all pages (except login/register/password-reset) to determine auth state
    // This is needed for landing page to show correct buttons
    if (!authStore.user && to.name !== 'login' && to.name !== 'register' && to.name !== 'password-reset') {
        try {
            await authStore.fetchUser();
        } catch (error) {
            // If fetch fails (401), user is null which is correct
            // This allows public pages to work correctly
        }
    }

    // Skip auth check for public pages
    if (to.meta.public) {
        next();
        return;
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        // Redirect to login, but prevent infinite loops
        if (to.name !== 'login') {
            next({ name: 'login', query: { redirect: to.fullPath } });
        } else {
            next(); // Already on login page, allow it
        }
    } else if (to.meta.requiresAdmin && authStore.user?.role !== 'admin') {
        // Redirect to dashboard if not admin
        next({ name: 'home' });
    } else if (to.meta.requiresGuest && authStore.isAuthenticated) {
        next({ name: 'home' });
    } else {
        next();
    }
});

export default router;
