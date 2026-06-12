import './bootstrap';
import '../css/public.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router/public';
import i18n, { initLocaleFromBackend, preloadActiveLocale } from './i18n';
import AppPublic from './AppPublic.vue';

function revealApp(): void {
    document.getElementById('landing-shell')?.remove();
    document.getElementById('app')?.classList.remove('sf-app-pending');
}

function scheduleAnalyticsLoad(): void {
    void import('./services/analytics').then(({ loadAnalyticsIfConsented }) => loadAnalyticsIfConsented());
}

function mountPublicSpa(): void {
    const app = createApp(AppPublic);
    app.use(createPinia());
    app.use(router);
    app.use(i18n);
    app.mount('#app');

    void router.isReady().then(() => {
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                revealApp();
                scheduleAnalyticsLoad();
            });
        });
    });
}

preloadActiveLocale();
mountPublicSpa();
void initLocaleFromBackend();
