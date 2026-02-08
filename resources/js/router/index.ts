import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../store/auth';
import { updatePageMeta } from '../composables/usePageMeta';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            name: 'landing',
            component: () => import('../pages/Landing.vue'),
            meta: { public: true, titleKey: 'seo.landing_title', descriptionKey: 'seo.landing_description' },
        },
        {
            path: '/dashboard',
            name: 'home',
            component: () => import('../pages/Dashboard.vue'),
            meta: { requiresAuth: true, titleKey: 'seo.dashboard_title' },
        },
        {
            path: '/login',
            name: 'login',
            component: () => import('../pages/auth/Login.vue'),
            meta: { requiresGuest: true, titleKey: 'seo.login_title', descriptionKey: 'seo.login_description' },
        },
        {
            path: '/register',
            name: 'register',
            component: () => import('../pages/auth/Register.vue'),
            meta: { requiresGuest: true, titleKey: 'seo.register_title', descriptionKey: 'seo.register_description' },
        },
        {
            path: '/password/reset',
            name: 'password-reset',
            component: () => import('../pages/auth/PasswordReset.vue'),
            meta: { requiresGuest: true, titleKey: 'seo.password_reset_title', descriptionKey: 'seo.password_reset_description' },
        },
        {
            path: '/auth/verify-email/:id/:hash',
            name: 'verify-email',
            component: () => import('../pages/auth/VerifyEmail.vue'),
        },
        {
            path: '/pricing',
            name: 'pricing',
            component: () => import('../pages/Pricing.vue'),
            meta: { public: true, titleKey: 'seo.pricing_title' },
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
            meta: { requiresAuth: true, titleKey: 'seo.stores_title' },
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
            path: '/stores/:id/pay-button',
            name: 'stores-pay-button',
            component: () => import('../pages/stores/PayButton.vue'),
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
            meta: { public: true, titleKey: 'seo.support_title', descriptionKey: 'seo.support_description' },
        },
        {
            path: '/success',
            name: 'subscription-success',
            component: () => import('../pages/BillingSuccess.vue'),
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
            path: '/admin',
            name: 'admin-dashboard',
            component: () => import('../pages/admin/Dashboard.vue'),
            meta: { requiresAuth: true, requiresSupportOrAdmin: true },
        },
        {
            path: '/admin/users',
            name: 'admin-users',
            component: () => import('../pages/admin/Users.vue'),
            meta: { requiresAuth: true, requiresAdmin: true },
        },
        {
            path: '/admin/users/:id',
            name: 'admin-user-detail',
            component: () => import('../pages/admin/UserDetail.vue'),
            meta: { requiresAuth: true, requiresAdmin: true },
        },
        {
            path: '/documentation',
            name: 'documentation',
            component: () => import('../pages/documentation/Index.vue'),
            meta: { public: true, titleKey: 'seo.documentation_title', descriptionKey: 'seo.documentation_description' },
        },
        {
            path: '/documentation/:slug',
            name: 'documentation-show',
            component: () => import('../pages/documentation/Show.vue'),
            meta: { public: true, titleKey: 'seo.documentation_title', descriptionKey: 'seo.documentation_description' },
        },
        {
            path: '/faq',
            name: 'faq',
            component: () => import('../pages/faq/Index.vue'),
            meta: { public: true, titleKey: 'seo.faq_title', descriptionKey: 'seo.faq_description' },
        },
        {
            path: '/admin/documentation',
            name: 'admin-documentation',
            component: () => import('../pages/admin/documentation/Articles.vue'),
            meta: { requiresAuth: true, requiresSupportOrAdmin: true },
        },
        {
            path: '/admin/documentation/articles/create',
            name: 'admin-documentation-articles-create',
            component: () => import('../pages/admin/documentation/ArticleForm.vue'),
            meta: { requiresAuth: true, requiresSupportOrAdmin: true },
        },
        {
            path: '/admin/documentation/articles/:id/edit',
            name: 'admin-documentation-articles-edit',
            component: () => import('../pages/admin/documentation/ArticleForm.vue'),
            meta: { requiresAuth: true, requiresSupportOrAdmin: true },
        },
        {
            path: '/admin/documentation/categories',
            name: 'admin-documentation-categories',
            component: () => import('../pages/admin/documentation/Categories.vue'),
            meta: { requiresAuth: true, requiresSupportOrAdmin: true },
        },
        {
            path: '/admin/faq',
            name: 'admin-faq',
            component: () => import('../pages/admin/faq/Items.vue'),
            meta: { requiresAuth: true, requiresSupportOrAdmin: true },
        },
        {
            path: '/admin/faq/items/create',
            name: 'admin-faq-items-create',
            component: () => import('../pages/admin/faq/ItemForm.vue'),
            meta: { requiresAuth: true, requiresSupportOrAdmin: true },
        },
        {
            path: '/admin/faq/items/:id/edit',
            name: 'admin-faq-items-edit',
            component: () => import('../pages/admin/faq/ItemForm.vue'),
            meta: { requiresAuth: true, requiresSupportOrAdmin: true },
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
    } else if (to.meta.requiresSupportOrAdmin && (!authStore.user || (authStore.user.role !== 'support' && authStore.user.role !== 'admin'))) {
        // Redirect to dashboard if not support or admin
        next({ name: 'home' });
    } else if (to.meta.requiresGuest && authStore.isAuthenticated) {
        next({ name: 'home' });
    } else {
        next();
    }
});

// Update document title and meta description on route change
router.afterEach((to) => {
    updatePageMeta(to);
});

export default router;
