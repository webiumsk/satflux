import { createRouter, createWebHistory } from 'vue-router';
import { updatePageMeta } from '../composables/usePageMeta';
import { runPublicRouteGuard } from './publicGuards';

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
            meta: { public: true },
        },
        {
            path: '/pricing',
            name: 'pricing',
            component: () => import('../pages/Pricing.vue'),
            meta: { public: true, titleKey: 'seo.pricing_title' },
        },
        {
            path: '/legal/terms',
            name: 'legal-terms',
            component: () => import('../pages/legal/TermsOfService.vue'),
            meta: { public: true, titleKey: 'seo.legal_terms_title', descriptionKey: 'seo.legal_terms_description' },
        },
        {
            path: '/legal/privacy',
            name: 'legal-privacy',
            component: () => import('../pages/legal/PrivacyPolicy.vue'),
            meta: { public: true, titleKey: 'seo.legal_privacy_title', descriptionKey: 'seo.legal_privacy_description' },
        },
        {
            path: '/legal/imprint',
            name: 'legal-imprint',
            component: () => import('../pages/legal/Imprint.vue'),
            meta: { public: true, titleKey: 'seo.legal_imprint_title', descriptionKey: 'seo.legal_imprint_description' },
        },
        {
            path: '/legal/dpa',
            name: 'legal-dpa',
            component: () => import('../pages/legal/DataProcessingAgreement.vue'),
            meta: { public: true, titleKey: 'seo.legal_dpa_title', descriptionKey: 'seo.legal_dpa_description' },
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
    ],
});

router.beforeEach(async (to, _from, next) => {
    await runPublicRouteGuard(to, next);
});

router.afterEach((to) => {
    updatePageMeta(to);
});

export default router;
