import './bootstrap';
import '@fontsource/outfit/400.css';
import '@fontsource/outfit/600.css';
import '@fontsource/outfit/700.css';
import '../css/app.css';
import './styles/invoicing-theme.css';

import { createApp, h, Fragment } from 'vue';
import { createPinia } from 'pinia';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import router from './router';
import i18n, { initLocaleFromBackend, preloadActiveLocale } from './i18n';
import App from './App.vue';
import GuestUpgradeModal from './components/account/GuestUpgradeModal.vue';

const el = document.getElementById('app');
const isInertia = el?.hasAttribute('data-page');

function mountSpa(): void {
    const app = createApp(App);
    app.use(createPinia());
    app.use(router);
    app.use(i18n);
    app.mount('#app');
}

function mountInertia(): void {
    createInertiaApp({
        title: (title) => (title ? `${title} - ${import.meta.env.VITE_APP_NAME || 'satflux.io'}` : 'satflux.io'),
        resolve: (name) =>
            resolvePageComponent(
                `./pages/${name}.vue`,
                import.meta.glob('./pages/**/*.vue'),
            ),
        setup({ el: mountEl, App: InertiaApp, props, plugin }) {
            const app = createApp({
                render: () => h(Fragment, null, [
                    h(InertiaApp, props),
                    h(GuestUpgradeModal),
                ]),
            });
            app.use(plugin);
            app.use(createPinia());
            app.use(i18n);
            app.provide('inertia', true);
            app.mount(mountEl);
        },
    });
}

// Initialize locale from backend without blocking first paint
preloadActiveLocale();

if (isInertia) {
    mountInertia();
} else {
    mountSpa();
}

void import('./services/analytics').then(({ loadAnalyticsIfConsented }) => loadAnalyticsIfConsented());
void initLocaleFromBackend();








